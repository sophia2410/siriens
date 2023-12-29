<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_date = (isset($_GET['report_date'])) ? $_GET['report_date'] : date('Ymd',time());
$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;

// sirianz_evening 기준. 
// 거래대금 1000억 + 상한가 + 최대거래량 종목 추출

$query = " SELECT Z.*
			FROM (
					SELECT A.id
						, A.report_date
						, A.signal_grp
						, A.theme
						, IF(A.issue, '', CONCAT(' [',A.issue,']')) issue_str
						, A.keyword
						, A.code
						, A.name
						, E.close_rate
						, IFNULL(F.volume, ROUND(E.volume/1000)) volume
						, IFNULL(F.tot_trade_amt, IFNULL(ROUND(E.amount/1000000000,0), ROUND(E.volume * E.close /1000000000,0))) tot_trade_amt
						, F.trade_date
					FROM sirianz_evening A
					LEFT OUTER JOIN daily_price E
						ON  E.date = A.report_date
						AND E.code = A.code
					LEFT OUTER JOIN (select trade_date, code, MAX(volume) volume, MAX(tot_trade_amt) tot_trade_amt FROM mochaten group by trade_date, code ) F
						ON F.trade_date = A.report_date
						AND F.code = A.code
					WHERE A.report_date >= (select DATE_ADD('$report_date', INTERVAL -20 DAY) yyyymmdd)
					AND   A.report_date <= '$report_date'
				 ) Z
			WHERE (Z.tot_trade_amt >= 1000) OR (Z.close_rate > 29 AND Z.tot_trade_amt >= 300)
			ORDER BY Z.report_date desc, Z.id ";
// echo "<pre>$query</pre>";
$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm small">
<?php
	$pre_report_date = "";
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($pre_report_date != $row['report_date']) {
			echo "<tr class='table-danger  text-dark' align=left><th colspan=3><b>▶ ".$row['report_date']."</b></th></tr>";
		}
		echo "<td><a href=\"javascript:callFrameR('".$report_date."','".$row['code']."','".$row['name']."')\"><b>".$row['name']."</b>".$row['issue_str']."</a></td>";
		echo "<td class='text-danger' align=right>".$row['close_rate']." %</td>" ;
		echo "<td class='text-danger' align=right>".$row['tot_trade_amt']." </td>" ;
		echo "</tr>" ;
		
		$pre_report_date =  $row['report_date'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameR(date, cd, nm) {
	window.parent.viewMochaten(date, cd, nm);
}
</script>
</html>
