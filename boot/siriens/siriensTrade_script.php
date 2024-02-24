<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
</head>

<?php
// post 방식으로 넘어온 변수 확인
foreach($_POST as $key=>$val){
	echo "$key =>  $val \n";
}
if(isset($_POST['proc_fg'])) {

	$code = $_POST['code'];
	
	//차트 코멘트 등록
	$daily_comment = str_replace("'", "\'", $_POST['daily_comment']);
	$daily_comment = str_replace('"', '\"', $daily_comment);
	
	$good_comment = str_replace("'", "\'", $_POST['good_comment']);
	$good_comment = str_replace('"', '\"', $good_comment);
	
	$bad_comment = str_replace("'", "\'", $_POST['bad_comment']);
	$bad_comment = str_replace('"', '\"', $bad_comment);

	if($_POST['proc_fg'] == 'save') {
		$qry = "DELETE FROM siriens_trade WHERE regi_id = '".$_POST['regi_id']."' AND trade_date='".$_POST['trade_date']."' AND code='".$_POST['code']."' ";
		echo $qry;
		$result = $mysqli->query($qry);

		$qry = "INSERT INTO siriens_trade (trade_date, code, regi_id, daily_comment, good_comment, bad_comment, trade_rate, buysell_fg, create_dtime) 
				VALUES ('".$_POST['trade_date']."', '".$_POST['code']."', '".$_POST['regi_id']."', '".$daily_comment."', '".$good_comment."', '".$bad_comment."', '".$_POST['trade_rate']."', '".$_POST['buysell_fg']."', now())";
		echo $qry;
		$result = $mysqli->query($qry);

	} else if($_POST['proc_fg'] == 'delete') {
		$qry = "DELETE FROM siriens_trade WHERE regi_id = '".$_POST['regi_id']."' AND trade_date='".$_POST['trade_date']."' AND code='".$_POST['code']."' ";
		echo $qry;
		$result = $mysqli->query($qry);
	}
}

$mysqli->close();
?>
</html>