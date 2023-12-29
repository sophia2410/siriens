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
	echo "$key =>  $val \n";
}
if(isset($_POST['regi_id']) && $_POST['regi_id'] != '') {

	if($_POST['regi_id'] == 'nomad') {
		$chart_comment = $_POST['nomad_commnet'];
		$chart_grade   = $_POST['nomad_grade'];

		// 목민쌤은 관리하지 않는 항목
		$gae = '';
		$cha = '';
		$jae = '';
		$si  = '';
	} else if($_POST['regi_id'] == 'sophia') {
		$geo = $_POST['sophia_geo'];
		$cha = $_POST['sophia_cha'];
		$jae = $_POST['sophia_jae'];
		$si  = $_POST['sophia_si'];
		$chart_comment = $_POST['sophia_commnet'];
		$chart_grade = (isset($_POST['sophia_grade'])) ? $_POST['sophia_grade'] : "";
	} else if($_POST['regi_id'] == 'sister') {
		$geo = $_POST['sister_geo'];
		$cha = $_POST['sister_cha'];
		$jae = $_POST['sister_jae'];
		$si  = $_POST['sister_si'];
		$chart_comment = $_POST['sister_commnet'];
		$chart_grade = (isset($_POST['sister_grade'])) ? $_POST['sister_grade'] : "";
	}

	$qry = "DELETE FROM mochaten_comment WHERE regi_id = '".$_POST['regi_id']."' and mochaten_date='".$_POST['mochaten_date']."' AND code='".$_POST['code']."' ";
	// echo $qry;
	$result = $mysqli->query($qry);
	
	if($geo.$cha.$jae.$si.$chart_comment != "" || $chart_grade != "") {
		$qry = "INSERT INTO mochaten_comment (mochaten_date, code, regi_id, chart_comment, chart_grade, chart_pick, geo, cha, jae, si) 
					VALUES ('".$_POST['mochaten_date']."', '".$_POST['code']."', '".$_POST['regi_id']."', '$chart_comment', '$chart_grade', NULL, '$geo', '$cha', '$jae', '$si')";
		echo $qry;
		$result = $mysqli->query($qry);
	}
} else if(isset($_POST['proc_fg'])) {

	$code = isset($_POST['code']) ? $_POST['code'] : "";
	
	if($_POST['proc_fg'] == 'save') {	// mochaten_B.php
		for($i=0; $i<$keyword_cnt; $i++){
			$k_id     = 'k_id'.$i;
			$keyword  = 'keyword'.$i;
			$k_remark = 'k_remark'.$i;
	
			$qry = "UPDATE stock_keyword
					   SET keyword = '$_POST[$keyword]'
						 , remark   = '$_POST[$k_remark]'
					 WHERE id = '$_POST[$k_id]'";
			echo $qry."<br><br>";
			$mysqli->query($qry);
		}

		if($_POST['keywordNew'] != '') {
			$qry = "INSERT INTO stock_keyword (code, keyword, remark, create_dtime, last_dtime) values('$code', '".$_POST['keywordNew']."', '".$_POST['k_remarkNew']."', now(), now())";
			echo $qry."<br><br>";
			$mysqli->query($qry);
		}

		for($i=0; $i<$comment_cnt; $i++){
			$c_chk    = 'c_chk'.$i;
			$comment  = 'comment'.$i;
			$c_remark = 'c_remark'.$i;
	
			$qry = "UPDATE stock_keyword
					   SET keyword = '$_POST[$keyword]'
						 , remark   = '$_POST[$c_remark]'
					 WHERE id = '$_POST[$k_id]'";
			echo $qry."<br><br>";
			$mysqli->query($qry);
		}

		if($_POST['keywordNew'] != '') {
			$qry = "INSERT INTO stock_keyword (code, keyword, remark, create_dtime, last_dtime) values('$code', '".$_POST['commentNew']."', '".$_POST['c_remarkNew']."', now(), now())";
			echo $qry."<br><br>";
			$mysqli->query($qry);
		}
	} else if($_POST['proc_fg'] == 'del') {	// mochaten_B.php
		for($i=0; $i<$keyword_cnt; $i++){
			$k_id   = 'k_id'.$i;
			$k_chk  = 'k_chk'.$i;
	
			if(isset($_POST[$k_chk])) {
				$qry = "DELETE FROM stock_keyword
						WHERE id = '$_POST[$k_id]'";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}
		}

		for($i=0; $i<$comment_cnt; $i++){
			$c_id   = 'c_id'.$i;
			$c_chk  = 'c_chk'.$i;
	
			if(isset($_POST[$k_chk])) {
				$qry = "DELETE FROM stock_comment
						WHERE id = '$_POST[$k_id]'";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}
		}
	} else if($_POST['proc_fg'] == 'keyword') {	// mochaten_B.php
		$day0_date = $_POST['day0_date'];
		$day0_keyword1 = $_POST['day0_keyword1'];
		$day0_keyword2 = $_POST['day0_keyword2'];
		$day0_keyword3 = $_POST['day0_keyword3'];

		$qry = "UPDATE mochaten
				   SET keyword1 = \"$day0_keyword1\"
					 , keyword2 = \"$day0_keyword2\"
					 , keyword3 = \"$day0_keyword3\"
					 , keyevent = \"$day0_keyevent\"
				 WHERE mochaten_date='".$day0_date."' AND code='".$_POST['code']."'";
		echo $qry."<br><br>";
		$mysqli->query($qry);
	} else if($_POST['proc_fg'] == 'keywordList') {	// mochatenKeywordStr_B.php
		for($i=0; $i<$_POST['keyword_cnt']; $i++){
			$trade_date = 'trade_date'.$i;
			$code       = 'code'.$i;
			$keyword1   = 'keyword1'.$i;
			$keyword2   = 'keyword2'.$i;
			$keyword3   = 'keyword3'.$i;
			$keyevent   = 'keyevent'.$i;

			$keyevent_val = str_replace("'", "\'", $_POST[$keyevent]);
			$keyevent_val = str_replace('"', '\"', $keyevent_val);

			$qry = "UPDATE mochaten
					   SET keyword1 = \"$_POST[$keyword1]\"
						 , keyword2 = \"$_POST[$keyword2]\"
						 , keyword3 = \"$_POST[$keyword3]\"
						 , keyevent = \"$keyevent_val\"
					 WHERE trade_date ='".$_POST[$trade_date]."' AND code='".$_POST[$code]."'";
			echo $qry."<br>";
			$mysqli->query($qry);
		}
	} else if($_POST['proc_fg'] == 'saveMochatenKeyword') {	// mochatenKeyword_B.php
		for($i=0; $i<$_POST['cnt']; $i++){
			$check      = 'check'.$i;
			$trade_date = 'trade_date'.$i;
			$code       = 'code'.$i;
			$stock_keyword     = 'stock_keyword'.$i;
			$org_stock_keyword = 'org_stock_keyword'.$i;

			$keyword = array();
			if($_POST['keyword_cd1'] != '') $keyword[] = $_POST['keyword_cd1'];
			if($_POST['keyword_cd2'] != '') $keyword[] = $_POST['keyword_cd2'];
			if($_POST['keyword_cd3'] != '') $keyword[] = $_POST['keyword_cd3'];

			// 체크박스 체크 종목 선택 키워드 등록처리
			if(isset($_POST[$check])) {

				$qry = "REPLACE INTO  sirianz_report_stock (report_date, code, name)
						SELECT '".$_POST[$trade_date]."'
							 , A.code
							 , A.name
						  FROM stock A
						 WHERE code = '".$_POST[$code]."'
						   AND last_yn = 'Y'
						   AND NOT EXISTS (SELECT * FROM sirianz_report_stock Z WHERE Z.report_date = '".$_POST[$trade_date]."' AND Z.code = A.code)";

				echo $qry."<br>";
				$mysqli->query($qry);

				for($j=0; $j<count($keyword); $j++) {
					$qry = "REPLACE INTO  sirianz_report_keyword (report_date, code, keyword_cd, create_dtime)
							VALUES ('".$_POST[$trade_date]."', '".$_POST[$code]."', '".$keyword[$j]."', NOW()) ";

					echo $qry."<br>";
					$mysqli->query($qry);
				}
			}
			// 종목 키워드 변경건의 경우 업데이트 처리
			if($_POST[$stock_keyword] != $_POST[$org_stock_keyword]) {
				$qry = "UPDATE mochaten
							SET stock_keyword = \"$_POST[$stock_keyword]\"
							WHERE trade_date ='".$_POST[$trade_date]."' AND code='".$_POST[$code]."'";
				echo $qry."<br>";
				$mysqli->query($qry);
			}
		}
	} else if($_POST['proc_fg'] == 'delMochatenKeyword') {	// mochatenKeyword_B.php
		for($i=0; $i<$_POST['cnt']; $i++){
			$check      = 'check'.$i;
			$code       = 'code'.$i;
			$trade_date = 'trade_date'.$i;

			$keyword = array();
			if($_POST['keyword_cd1'] != '') $keyword[] = $_POST['keyword_cd1'];
			if($_POST['keyword_cd2'] != '') $keyword[] = $_POST['keyword_cd2'];
			if($_POST['keyword_cd3'] != '') $keyword[] = $_POST['keyword_cd3'];

			// 체크박스 체크 종목 선택 키워드 등록처리
			if(isset($_POST[$check])) {
				if(count($keyword) == 0) {
					//키워드 삭제
					$qry = "DELETE FROM sirianz_report_keyword
							WHERE report_date = '".$_POST[$trade_date]."'
							AND code = '".$_POST[$code]."'";

					echo $qry."<br>";
					$mysqli->query($qry);
					
					//today stock 삭제
					$qry = "DELETE FROM sirianz_report_stock
							WHERE report_date = '".$_POST[$trade_date]."'
							AND code = '".$_POST[$code]."'";

					echo $qry."<br>";
					$mysqli->query($qry);

				} else {
					for($j=0; $j<count($keyword); $j++) {
						$qry = "DELETE FROM sirianz_report_keyword
								WHERE report_date = '".$_POST[$trade_date]."'
								  AND code = '".$_POST[$code]."'
								  AND keyword_cd = '".$keyword[$j]."'";
	
						echo $qry."<br>";
						$mysqli->query($qry);
					}
				}
			}
		}
	}
}

$mysqli->close();
?>
</body>
<?php

if(isset($_POST['regi_id'])) {
	if($_POST['regi_id'] == ! 'nomad') {
		//echo "<script>window.parent.parent.searchMochaten('".$_POST['code']."');</script>";
	}
}
?>
</html>