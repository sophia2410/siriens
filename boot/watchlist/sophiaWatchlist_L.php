<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

if(isset($_GET['stock_nm'])) {
	$where = "INNER JOIN stock B ON B.code = A.code AND B.name like '%".$_GET['stock_nm']."%'";
} else {
	$where = '';
}

$query = "	SELECT A.sector, A.theme, A.category, min(A.sort_theme) sort_theme
			FROM watchlist_sophia A
			$where
			GROUP BY A.sector, A.theme, A.category
			ORDER BY A.sector, sort_theme";
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
			echo "<td><a href=\"javascript:callViewChart('".$row['sector']."','','')\"><b>".$row['sector']."</b></a></td>" ;
			echo "<td><a href=\"javascript:callxrayTick('".$row['sector']."','')\"><img style='width:20px; height:20px; border:solid thin' src='https://siriens.mycafe24.com/image/view_review.png'></a></td>";
			echo "</tr>";
		}

		// // theme / category 출력
		// echo "<tr>";

		// if($pre_theme != $row['sector'].$row['theme'])
		// 	echo "<td><a href=\"javascript:callViewChart('".$row['sector']."','".$row['theme']."','')\"><b>".$row['theme']."</b></a></td>" ;
		// else
		// 	echo "<td>&nbsp;</td>" ;

		// echo "<td><a href=\"javascript:callViewChart('".$row['sector']."','".$row['theme']."','".$row['category']."')\"><b>".$row['category']."</b></a></td>";
		// echo "</tr>" ;
		
		// $pre_sector = $row['sector'];
		// $pre_theme  = $row['sector'].$row['theme'];

		// theme 출력
		echo "<tr>";

		if($pre_theme != $row['sector'].$row['theme']) {
			echo "<td><a href=\"javascript:callViewChart('".$row['sector']."','".$row['theme']."','')\"><b>".$row['theme']."</b></a></td>" ;
			echo "<td><a href=\"javascript:callxrayTick('".$row['sector']."','".$row['theme']."')\"><img style='width:20px; height:20px; border:solid thin' src='https://siriens.mycafe24.com/image/view_review.png'></a></td>";
		}
		echo "</tr>" ;
		
		$pre_sector = $row['sector'];
		$pre_theme  = $row['sector'].$row['theme'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callViewChart(sector, theme, category) {
	window.parent.viewChart(sector, theme, category);
}

// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callxrayTick(sector, theme) {
	window.parent.xrayTick(sector, theme);
}
</script>
</html>