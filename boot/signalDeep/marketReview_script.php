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

if(isset($_POST['proc_fg'])) {	
	//키워드 저장
	if($_POST['proc_fg'] == 'save') {	// SiriensReport_Stock_B.php
		for($i=0; $i<$_POST['cnt']; $i++){
			$cd  = 'idx_'.$i;
			$str = 'content_'.$i;

			$content_val = str_replace("'", "\'", $_POST[$str]);
			$content_val = str_replace('"', '\"', $content_val);

			$query = "UPDATE siriens_market_review
						 SET content_str = '$content_val'
						WHERE report_date = '".$_POST['report_date']."'
						AND content_cd = '$_POST[$cd]'";
			echo $query."<br>";
			$mysqli->query($query);
		}
	}
}

$mysqli->close();
?>
</body>

<script>
// parent.location.reload();
</script>
</html>