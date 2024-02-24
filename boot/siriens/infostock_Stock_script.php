<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
</head>

<body>
<?php
// post 방식으로 넘어온 변수 확인
foreach($_POST as $key=>$val){
	// echo "$key =>  $val \n";
}

if(isset($_POST['proc_fg'] )) {
	if($_POST['proc_fg'] == 'setCode') {
		// today Stock 중 코드미등록 종목 처리
		$qry = " UPDATE rawdata_infostock A
					SET str5
					= (SELECT code
						 FROM stock B
						WHERE B.name = A.str6)
				  WHERE (str5 = '' or str5 IS null)
					AND str1 IN ('Daily_Kosdaq', 'Daily_Kospi')
					AND str2 = '".$_POST['infostock_date']."'";
		echo $qry;
		$result = $mysqli->query($qry);
	}
	else if($_POST['proc_fg'] == 'proc') {
		// infostock_Theme_script '반영' 로직 가져오기. 일괄처리. START. 2023.05.14  -------------------------------------------------------------------------------
		$qry = " REPLACE
					INTO infostock_today_theme(infostock_date, today_theme_cd, today_theme_nm, issue, detail, today_theme_rank, create_dtime)
				SELECT str2
					, CONCAT(str2, lpad(@RNUM:=@RNUM+1,2,'0'))
					, str3
					, str7
					, str8
					, @RNUM:=@RNUM
					, NOW()
					FROM rawdata_infostock A, (SELECT @RNUM:=0) R
				WHERE str1 = 'Daily_Theme_Group'
					AND str2 = '".$_POST['infostock_date']."'";

		echo "<pre>$qry</pre>";
		$result = $mysqli->query($qry);

		// today Theme 반영 여부 처리
		$qry = "  UPDATE rawdata_infostock A
				INNER JOIN infostock_today_theme B
					ON B.infostock_date = A.str2
					AND B.today_theme_nm = A.str3
					SET A.today_theme_cd = B.today_theme_cd
				WHERE A.str2 = '".$_POST['infostock_date']."'
					AND A.str3 IS NOT NULL ";
		echo "<pre>$qry</pre>";
		$result = $mysqli->query($qry);

		// theme history 반영
		$qry = " REPLACE
				INTO infostock_theme_history(infostock_date, theme_cd, issue, today_theme_cd, today_theme_rank, create_dtime)
				SELECT A.infostock_date
					, A.theme_cd
					, B.issue
					, B.today_theme_cd
					, B.today_theme_rank
					, NOW()
					FROM (SELECT  str2 infostock_date
								, str3 today_theme_nm
								, theme_cd
							FROM rawdata_infostock 
						WHERE str1 = 'Daily_Theme_Stock'
							AND str2 = '".$_POST['infostock_date']."' 
						GROUP BY today_theme_nm, theme_cd) A
						, infostock_today_theme B
				WHERE B.infostock_date = A.infostock_date
					AND B.today_theme_nm = A.today_theme_nm";
		echo "<pre>$qry</pre>";
		$result = $mysqli->query($qry);

		// infostock_today_stock 정보 반영 
		// ---- 아래 infostock_today_stock과 합치기

		// today Theme 반영 여부 처리
		$qry = "  UPDATE rawdata_infostock
					SET proc_yn = 'Y'
					WHERE str1 in ('Daily_Theme_Group', 'Daily_Theme_Stock')
					AND str2 = '".$_POST['infostock_date']."' ";
		echo "<pre>$qry</pre>";
		$result = $mysqli->query($qry);

		// siriens_evening_theme 반영
		$qry = " REPLACE
					INTO siriens_infostock_theme(report_date, today_theme_cd, today_theme_nm, issue, detail, today_theme_rank, create_dtime)
				SELECT infostock_date, today_theme_cd, today_theme_nm, issue, detail, today_theme_rank, NOW()
					FROM infostock_today_theme
				WHERE infostock_date = '".$_POST['infostock_date']."'";
		echo "<pre>$qry</pre>";
		$result = $mysqli->query($qry);

		// infostock_Theme_script '반영' 로직 가져오기. 일괄처리. END. 2023.05.14   -------------------------------------------------------------------------------

		// today Stock 반영
		$qry = " REPLACE
					INTO infostock_today_stock(infostock_date, code, name, issue, detail, theme_cd, today_theme_cd, create_dtime)
				  SELECT str2 infostock_date
					   , str5 code
					   , str6 name
					   , max(str7) issue
					   , max(str8) detail
					   , max(theme_cd) theme_cd
					   , min(today_theme_cd) today_theme_cd
					   , NOW()
					FROM rawdata_infostock 
				   WHERE str1 in ('Daily_Stock', 'Daily_Kospi', 'Daily_Kosdaq', 'Daily_Theme_Stock')
					 AND str2 = '".$_POST['infostock_date']."' 
				   GROUP BY str2, str5, str6";

		echo $qry;
		$result = $mysqli->query($qry);

		// today_hot에 등록된 상세 정보 참조해서 theme group 만들어주기!!!!
		$qry = "UPDATE infostock_today_stock a 
				INNER JOIN  infostock_today_theme b
				   ON b.infostock_date = a.infostock_date
				  AND b.detail like concat('%',a.name,'%')
				  SET a.today_theme_cd = b.today_theme_cd
				WHERE a.infostock_date = '".$_POST['infostock_date']."' 
				  AND a.today_theme_cd is null ";
		echo $qry;
		$result = $mysqli->query($qry);

		// today Stock 반영 여부 처리
		$qry = "  UPDATE rawdata_infostock
					 SET proc_yn = 'Y'
				   WHERE str1 in ('Daily_Stock', 'Daily_Kospi', 'Daily_Kosdaq')
					 AND str2 = '".$_POST['infostock_date']."' ";
		echo $qry;
		$result = $mysqli->query($qry);

		// siriens_evening 테이블에 넣어주기
		$qry = "  REPLACE
					  INTO siriens_infostock(report_date, code, name, issue, detail, today_theme_cd, create_dtime)
					SELECT infostock_date, code, name, issue, detail, today_theme_cd, now()
					  FROM infostock_today_stock 
					 WHERE infostock_date = '".$_POST['infostock_date']."'";

		echo $qry;
		$result = $mysqli->query($qry);
	}
}

$mysqli->close();
?>
</body>
<script>
console.log('저장끝');

// parent.src = "infostock_B.php?infostock_date="+<?=$_POST['infostock_date']?>;
console.log('스크립트 실행 끝');
</script>
</html>