<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$query = " SELECT sector, theme, category, min(sort_theme) sort_theme
			 FROM watchlist_sophia A
			GROUP BY sector, theme, category
			ORDER BY sector, sort_theme";
// echo "<pre>$query</pre>";
$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm small">
<!-- <table class="table table-sm small"> -->
<?php
	// 일자 변경확인을 위한 변수
	$pre_watchlist_date = "";

	// 출력된 종목명을 저장할 배열
	$printed = array();
	$pre_sector = "";
	$pre_theme  = "";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		echo "<tr>";
		if($pre_sector != $row['sector'])
			echo "<td><a href=\"javascript:callFrameRS('".$row['sector']."','','')\"><b>".$row['sector']."</b></a></td>" ;
		else
			echo "<td>&nbsp;</td>" ;

		if($pre_theme != $row['sector'].$row['theme'])
			echo "<td><a href=\"javascript:callFrameRS('".$row['sector']."','".$row['theme']."','')\"><b>".$row['theme']."</b></a></td>" ;
		else
			echo "<td>&nbsp;</td>" ;

		echo "<td><a href=\"javascript:callFrameRS('".$row['sector']."','".$row['theme']."','".$row['category']."')\"><b>".$row['category']."</b></a></td>";
		echo "</tr>" ;
		
		$pre_sector = $row['sector'];
		$pre_theme  = $row['sector'].$row['theme'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameRS(sector, theme, category) {
	window.parent.viewStock(sector, theme, category);
}
</script>
</html>