<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$trade_date = (isset($_GET['trade_date'])) ? $_GET['trade_date'] : date('Ymd',time());
$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;

$query = " SELECT A.buysell_fg,
				  B.code, B.name
			 FROM siriens_trade A
			INNER JOIN stock B
			   ON B.code = A.code
			  AND B.last_yn = 'Y'
			WHERE A.trade_date = $trade_date
			  AND A.regi_id = 'sophia'
			ORDER BY A.buysell_fg, B.code";

$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm small">
<?php
	$pre_buysell_fg = "";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($pre_buysell_fg != $row['buysell_fg'])
		{
			echo "<tr class='table-danger  text-dark' align=center><th colspan=3><b>".$row['buysell_fg']."</b></th></tr>";
		}
		echo "<tr>";
		echo "<td><a href=\"javascript:callFrameR('".$trade_date."','".$row['code']."')\">".$row['name']."</a></td>";
		echo "</tr>" ;

		$pre_buysell_fg  = $row['buysell_fg'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameR(date, cd) {
	window.parent.viewTrade(date, cd);
}
</script>
</html>
