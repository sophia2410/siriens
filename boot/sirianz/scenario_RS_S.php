<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$trade_date = (isset($_GET['trade_date'])) ? $_GET['trade_date'] : date('Ymd',time());
$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;

$query = " SELECT sector,
				  theme
			 FROM scenario
			WHERE (theme is not null AND theme != '')
			GROUP BY sector,
				  theme
			ORDER BY sector,
				  theme";

$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm small">
<?php
	$pre_buysell_fg = "";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		echo "<tr>";
		echo "<td>".$row['sector']."</td>";
		echo "<td><a href=\"javascript:setPPG('".$row['sector']."','".$row['theme']."')\">".$row['theme']."</a></td>";
		echo "</tr>" ;
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function setPPG(sector, theme) {
	ppg = window.parent.document.forms[0];
	ppg.sector.value = sector;
	ppg.theme.value = theme;
}
</script>
</html>
