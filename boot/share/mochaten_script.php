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

if(isset($_POST['regi_id'])) {

	if($_POST['regi_id'] == 'nomad') {
		$chart_comment = $_POST['nomad_commnet'];
		$chart_grade   = $_POST['nomad_grade'];
	} else if($_POST['regi_id'] == 'sophia') {
		$chart_comment = $_POST['sophia_commnet'];
		$chart_grade   = $_POST['sophia_grade'];
	} else if($_POST['regi_id'] == 'sister') {
		$chart_comment = $_POST['sister_commnet'];
		$chart_grade   = $_POST['sister_grade'];
	}

	$qry = "DELETE FROM mochaten_comment WHERE regi_id = '".$_POST['regi_id']."' and mochaten_date='".$_POST['mochaten_date']."' AND code='".$_POST['code']."' ";
	// echo $qry;
	$result = $mysqli->query($qry);
	
	if($chart_comment != "" || $chart_grade != "") {
		$qry = "INSERT INTO mochaten_comment (mochaten_date, code, regi_id, chart_comment, chart_grade, chart_pick) 
					VALUES ('".$_POST['mochaten_date']."', '".$_POST['code']."', '".$_POST['regi_id']."', '$chart_comment', '$chart_grade', NULL)";
		echo $qry;
		$result = $mysqli->query($qry);
	}
} 

$mysqli->close();
?>
</body>
<?php
if($_POST['regi_id'] == ! 'nomad') {
	//echo "<script>window.parent.parent.searchMochaten('".$_POST['code']."');</script>";
}
?>
</html>