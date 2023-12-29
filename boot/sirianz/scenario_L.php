<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$search_date = (isset($_GET['search_date'])) ? $_GET['search_date'] : date('Ymd',time());
$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;

// $search_tracking_yn = (isset($_GET['tracking_yn'])) ? "AND A.tracking_yn ='".$_GET['tracking_yn'] ."'" : "AND A.tracking_yn ='Y'";
$search_buy_pick    = (isset($_GET['buy_pick'])    && $_GET['buy_pick']    != '') ? "AND A.buy_pick    ='".$_GET['buy_pick'] ."'" :'';

// sirianz_evening 기준. 
// 거래대금 1000억 + 상한가 + 최대거래량 종목 추출

$query = " SELECT Z.*
			FROM (SELECT STR_TO_DATE(A.trade_date, '%Y%m%d') trade_date_str
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',B.close_rate,'% </font>') END kospi_index
						, CASE WHEN C.close_rate >= 0 THEN CONCAT('<font color=red> ▲',C.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',C.close_rate,'% </font>') END kosdaq_index
						, A.trade_date
						, A.mavg
						, A.status
						, D.code
						, D.name
						, A.close_rate
						, A.volume
						, A.tot_trade_amt
						, A.market_cap
						, CASE WHEN A.theme is null OR  A.theme = '' THEN A.sector ELSE A.theme END uprsn
						, A.stock_keyword
						, A.tracking_yn
						, A.tracking_reason
						, A.scenario
						, A.buy_band
						, CASE WHEN A.buy_pick = 'Y' THEN '<b>B</b>' ELSE '' END buy_pick
						, A.tracking_index
						, (SELECT COUNT(*) FROM calendar WHERE date <= A.trade_date and date > last_0day) tracking_day
						, E.evening_subject
					FROM scenario A
					LEFT OUTER JOIN market_index B
					on B.date = A.trade_date
					and B.market_fg = 'KOSPI'
					LEFT OUTER JOIN market_index C
					on C.date = A.trade_date
					and C.market_fg = 'KOSDAQ'
					INNER JOIN stock D
					ON D.code = A.code
					AND D.last_yn = 'Y'
					LEFT OUTER JOIN sirianz_report E
					ON E.report_date = A.trade_date
					WHERE A.trade_date >= (select DATE_FORMAT(DATE_ADD('$search_date', INTERVAL -30 DAY), '%Y%m%d'))
					AND   A.trade_date <= '$search_date'
					$search_buy_pick
				 ) Z
			ORDER BY Z.trade_date desc, Z.tracking_day, Z.buy_pick desc,  Z.tot_trade_amt desc, SUBSTR(Z.tracking_index,1,8) desc";
echo "<pre>$query</pre>";
$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm small">
<?php
	$pre_trade_date = "";
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$trade_date = $row['trade_date'];
		if($pre_trade_date != $row['trade_date']) {
			echo "<tr class='table-danger  text-dark' align=left><th colspan=6><b>▶ <a href=\"javascript:callFrameRD('".$row['trade_date']."')\">".$row['trade_date_str']."</b></a> &nbsp;&nbsp; (코스피 : ".$row['kospi_index']." , 코스닥 : ".$row['kosdaq_index'].") </th></tr>";
			
			if($row['evening_subject'] != '') 
				echo "<tr class='table-info  text-dark' align=left><th colspan=6>'".$row['evening_subject']."</th></tr>";
		}

		$bgstyle = ($row['tracking_day'] > 0) ? "background-color:#F6F6F6;" : "";
		echo "<tr style='$bgstyle'>";
		echo "<td class='text-danger' align=right>".$row['buy_pick']." </td>" ;
		echo "<td class='text-danger' align=center>".$row['uprsn']." </td>" ;
		echo "<td><a href=\"javascript:callFrameRS('".$row['trade_date']."','".$row['code']."','".$row['name']."','".$row['tracking_index']."')\"><b>".$row['name']."</b> [".$row['mavg']." / ".$row['status']."]</a></td>";
		echo "<td class='text-danger' align=right>".$row['close_rate']." %</td>" ;
		echo "<td class='text-danger' align=right>".number_format($row['tot_trade_amt'])." </td>" ;
		echo "<td class='text-danger' align=right>+".$row['tracking_day']."</td>" ;
		echo "</tr>" ;
		
		$pre_trade_date =  $row['trade_date'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameRS(date, cd, nm, idx) {
	window.parent.viewStock(date, cd, nm, idx);
}

// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameRD(date, cd, nm, idx) {
	window.parent.viewDay(date, cd, nm, idx);
}
</script>
</html>
