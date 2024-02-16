<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");


$date = (isset($_GET['date']) ) ? $_GET['date'] : '';
$minute = (isset($_GET['minute']) && $_GET['minute'] != '' ) ? $_GET['minute'] : date('Hi');  // 현재 시간을 구함;
$specific_datetime = $date.$minute;

$where = '';
if(isset($_GET['stock_nm'])) {
	$where = "INNER JOIN stock s ON s.code = w.code AND w.name like '%".$_GET['stock_nm']."%'";
}

$where2 = '';
if(isset($_GET['min_amount']) && $_GET['min_amount'] > 0) {
	$where2 = "WHERE z.amount_last_min > ".$_GET['min_amount']."*100";
}

$query = "SELECT z.sector, z.theme, z.sort_theme, ROUND(z.amount_10_min/100,2) AS bamount_10_min, ROUND(z.amount_last_min/100,2) AS amount_last_min
			FROM (
					SELECT w.sector, w.theme, min(w.sort_theme) as sort_theme, 
							SUM(CASE WHEN amount_10min > 0 THEN (amount_10min-amount_before_10min) ELSE amount_10min END) AS amount_10_min,
							SUM(CASE WHEN amount_10min > 0 THEN amount_10min ELSE amount_before_10min END) AS amount_last_min
					FROM (
							SELECT
								m.code,
								MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') BETWEEN  t.specific_datetime - INTERVAL 9 MINUTE AND t.specific_datetime THEN acc_trade_amount ELSE 0 END) AS amount_10min,
								MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') <= t.specific_datetime - INTERVAL 10 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_before_10min
							FROM
								kiwoom_realtime_minute m
							JOIN 
							(SELECT STR_TO_DATE('$specific_datetime', '%Y%m%d%H%i') specific_datetime) t
							WHERE
								m.date = DATE_FORMAT(t.specific_datetime, '%Y%m%d') AND
								m.minute <= DATE_FORMAT(t.specific_datetime, '%H%i')
							GROUP BY m.code
						) u
					JOIN watchlist_sophia w
					ON u.code = w.code
					$where
					WHERE w.realtime_yn = 'Y'
					GROUP BY w.sector, w.theme) z
			$where2
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
			echo "<td>".number_format($row['bamount_10_min'],2)."</td>" ;
			echo "<td>".number_format($row['amount_last_min'],2)."</td>" ;
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