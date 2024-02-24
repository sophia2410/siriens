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

	// 테마그룹 명 변경 시 저장
	if($_POST['proc_fg'] == 'saveTM') {
		for($i=0; $i<$_POST['rowcnt']; $i++) {
			$today_theme = "today_theme".$i;
			$id			 = "id".$i;

			$qry = " UPDATE rawdata_infostock
						SET str3 = '".$_POST[$today_theme]."'
					  WHERE id   = '".$_POST[$id]."'";

			echo $qry;
			$result = $mysqli->query($qry);
		}
	}
	// 테마그룹 종목 정보의 그룹명 변경, 저장 // 테마그룹과 명칭 동일하게 등록되어야 하므로, 별도 저장로직이 부적합해보임 / saveTM, save 로직 합칠필요 있을듯. 23.05.13 / 크롤링 시 Daily_Theme_Stock 에 테마그룹명 구하는 로직으로 변경했기에 가능함. 23.05.13
	else if($_POST['proc_fg'] == 'save') {
		for($i=0; $i<$_POST['rowcnt']; $i++) {
			$today_theme = "today_theme".$i;
			$theme_nm    = "theme_nm".$i;

			$qry = " UPDATE rawdata_infostock
						SET str3 = '".$_POST[$today_theme]."'
					  WHERE str1 = 'Daily_Theme_Stock'
						AND str2 = '".$_POST['infostock_date']."'
						AND str4 = '".$_POST[$theme_nm]."'";

			echo $qry;

			$result = $mysqli->query($qry);
		}
	}
	// 크롤링 데이터 infostock_today_theme, infostock_theme_history, infostock_today_stock / siriens_infostock_theme 테이블에 반영
	// siriens_infostock_theme 테이블에 반영 로직을 infostock_Stock_script 로 이동. 일괄처리 되도록...
	else if($_POST['proc_fg'] == 'proc') {
		// $qry = " REPLACE
		// 			INTO infostock_today_theme(infostock_date, today_theme_cd, today_theme_nm, issue, detail, today_theme_rank, create_dtime)
		// 		  SELECT str2
		// 			   , CONCAT(str2, lpad(@RNUM:=@RNUM+1,2,'0'))
		// 			   , str3
		// 			   , str7
		// 			   , str8
		// 			   , @RNUM:=@RNUM
		// 			   , NOW()
		// 			FROM rawdata_infostock A, (SELECT @RNUM:=0) R
		// 		   WHERE str1 = 'Daily_Theme_Group'
		// 			 AND str2 = '".$_POST['infostock_date']."'";
		// echo "<pre>$qry</pre>";
		// $result = $mysqli->query($qry);

		// // today Theme 반영 여부 처리
		// $qry = "  UPDATE rawdata_infostock A
		// 		   INNER JOIN infostock_today_theme B
		// 			  ON B.infostock_date = A.str2
		// 			 AND B.today_theme_nm = A.str3
		// 			 SET A.today_theme_cd = B.today_theme_cd
		// 		   WHERE A.str2 = '".$_POST['infostock_date']."'
		// 			 AND A.str3 IS NOT NULL ";
		// echo "<pre>$qry</pre>";
		// $result = $mysqli->query($qry);

		// // theme history 반영
		// $qry = " REPLACE
		// 			INTO infostock_theme_history(infostock_date, theme_cd, issue, today_theme_cd, today_theme_rank, create_dtime)
		// 		  SELECT A.infostock_date
		// 			   , A.theme_cd
		// 			   , B.issue
		// 			   , B.today_theme_cd
		// 			   , B.today_theme_rank
		// 			   , NOW()
		// 			FROM (SELECT  str2 infostock_date
		// 						, str3 today_theme_nm
		// 						, theme_cd
		// 		 			FROM rawdata_infostock 
		// 				   WHERE str1 = 'Daily_Theme_Stock'
		// 					 AND str2 = '".$_POST['infostock_date']."' 
		// 				   GROUP BY today_theme_nm, theme_cd) A
		// 				, infostock_today_theme B
		// 		   WHERE B.infostock_date = A.infostock_date
		// 			 AND B.today_theme_nm = A.today_theme_nm";
		// echo "<pre>$qry</pre>";
		// $result = $mysqli->query($qry);

		// // today_stock theme 정보 반영
		// $qry = " REPLACE
		// 			INTO infostock_today_stock(infostock_date, code, name, theme_cd, today_theme_cd, create_dtime)
		// 		  SELECT A.infostock_date, A.code, A.name, A.theme_cd, A.today_theme_cd, NOW()
		// 			FROM ( SELECT str2 infostock_date
		// 						, str3 today_theme_nm
		// 						, str5 code
		// 						, str6 name
		// 						, max(theme_cd) theme_cd
		// 						, min(today_theme_cd) today_theme_cd
		// 					 FROM rawdata_infostock
		// 					WHERE str1 = 'Daily_Theme_Stock'
		// 					  AND str2 = '".$_POST['infostock_date']."' 
		// 					GROUP BY str2, str3, str5, str6 )  A
		// 			";

		// echo "<pre>$qry</pre>";
		// $result = $mysqli->query($qry);

		// // today Theme 반영 여부 처리
		// $qry = "  UPDATE rawdata_infostock
		// 			 SET proc_yn = 'Y'
		// 		   WHERE str1 in ('Daily_Theme_Group', 'Daily_Theme_Stock')
		// 			 AND str2 = '".$_POST['infostock_date']."' ";
		// echo "<pre>$qry</pre>";
		// $result = $mysqli->query($qry);

		// // siriens_evening_theme 반영
		// $qry = " REPLACE
		// 			INTO siriens_infostock_theme(report_date, today_theme_cd, today_theme_nm, issue, detail, today_theme_rank, create_dtime)
		// 		  SELECT infostock_date, today_theme_cd, today_theme_nm, issue, detail, today_theme_rank, NOW()
		// 			FROM infostock_today_theme
		// 		   WHERE infostock_date = '".$_POST['infostock_date']."'";
		// echo "<pre>$qry</pre>";
		// $result = $mysqli->query($qry);
	}
}

$mysqli->close();
?>
</body>
<script>
console.log('저장끝');
console.log(parent.parent);
// parent.src = "infostock_B.php?infostock_date="+<?=$_POST['infostock_date']?>;
console.log('스크립트 실행 끝');
</script>
</html>