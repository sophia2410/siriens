<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");


$date = (isset($_GET['date']) ) ? $_GET['date'] : '';
$minute = (isset($_GET['minute']) && $_GET['minute'] != '' ) ? $_GET['minute'] : date('Hi');  // 현재 시간을 구함;
$specific_datetime = $date.$minute;

$query = "SELECT z.sector, z.theme, z.sort_theme, ROUND(z.exp_amount/100000000,2) exp_amount, z.cnt
			FROM (
					SELECT w.sector, w.theme, min(w.sort_theme) as sort_theme, SUM(exp_price * exp_vol) exp_amount, count(*) cnt
					FROM kiwoom_opt10029 u
					LEFT OUTER JOIN watchlist_sophia w
					ON u.code = w.code
					WHERE u.date = '$date'
					AND(w.sector LIKE '0%' 
					OR w.sector LIKE '1%' 
					OR w.sector LIKE '2%' 
					OR w.sector LIKE '3%' 
					OR w.sector LIKE '4%' 
					OR w.sector LIKE '5%' 
					OR w.sector LIKE '6%' 
					OR w.sector LIKE '7%' 
					OR w.sector LIKE '8%' 
					OR w.sector LIKE '9%')
					GROUP BY w.sector, w.theme) z
			ORDER BY z.sector, z.sort_theme";
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
			echo "<td colspan=3><b>".$row['sector']."</b></a></td>" ;
			echo "</tr>";
		}
		echo "<tr align=right>";

		if($pre_theme != $row['sector'].$row['theme'])
			echo "<td align=left><a href=\"javascript:callRealtime('".$row['sector']."','".$row['theme']."')\"><b>".$row['theme']."</b></a></td>" ;
			echo "<td>".$row['cnt']."</td>" ;
			echo "<td>".number_format($row['exp_amount'],2)."</td>" ;
		echo "</tr>" ;
		
		$pre_sector = $row['sector'];
		$pre_theme  = $row['sector'].$row['theme'];
	}
?>
</table>

<!-- <?php 
	echo "<pre>$query</pre>";
?> -->
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callRealtime(sector, theme) {
	window.parent.viewRealtime(sector, theme);
}
</script>
</html>