<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

if(isset($_GET['stock_nm'])) {
	$where = "INNER JOIN stock B ON B.code = A.code AND B.name like '%".$_GET['stock_nm']."%'";
} else {
	$where = '';
}

$query = " SELECT sector, theme, category, min(sort_theme) sort_theme
			 FROM watchlist_sophia A
			$where
			GROUP BY sector, theme, category
			ORDER BY sector, sort_theme";
// echo "<pre>$query</pre>";
$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm small">
<!-- <table class="table table-sm small"> -->
<?php
	// 출력된 종목명을 저장할 배열
	$printed = array();
	$pre_sector = "";
	$pre_theme  = "";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($pre_sector != $row['sector']) {
			echo "<tr class='table-danger'>";
			echo "<td colspan=2><a href=\"javascript:callViewChart('".$row['sector']."','','','')\"><b>".$row['sector']."</b></a></td>" ;
			echo "</tr>";
		}

		echo "<tr>";

		if($pre_theme != $row['sector'].$row['theme'])
			echo "<td><a href=\"javascript:callViewChart('".$row['sector']."','".$row['theme']."','','Y')\"><b>".$row['theme']."</b></a></td>" ;
		else
			echo "<td>&nbsp;</td>" ;

		echo "<td><a href=\"javascript:callViewChart('".$row['sector']."','".$row['theme']."','".$row['category']."','Y')\"><b>".$row['category']."</b></a></td>";
		echo "</tr>" ;
		
		$pre_sector = $row['sector'];
		$pre_theme  = $row['sector'].$row['theme'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callViewChart(sector, theme, category, getRearData) {
	window.parent.viewChart(sector, theme, category, getRearData);
}
</script>
</html>