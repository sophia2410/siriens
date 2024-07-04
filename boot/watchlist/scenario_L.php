<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$search_date = (isset($_GET['search_date'])) ? $_GET['search_date'] : date('Ymd',time()) + 3;
$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;

// $search_tracking_yn = (isset($_GET['tracking_yn'])) ? "AND A.tracking_yn ='".$_GET['tracking_yn'] ."'" : "AND A.tracking_yn ='Y'";
$search_buy_pick    = (isset($_GET['buy_pick'])    && $_GET['buy_pick']    != '') ? "AND A.buy_pick    ='".$_GET['buy_pick'] ."'" :'';

// 시나리오 작성 리스트 조회

$query = " SELECT Z.*
			FROM (SELECT STR_TO_DATE(A.scenario_date, '%Y%m%d') scenario_date_str
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',B.close_rate,'% </font>') END kospi_index
						, CASE WHEN C.close_rate >= 0 THEN CONCAT('<font color=red> ▲',C.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',C.close_rate,'% </font>') END kosdaq_index
						, A.scenario_date
						, A.mavg
						, A.status
						, D.code
						, D.name
						, S.watchlist_date
						, S.close_rate
						, S.volume
						, S.tot_trade_amt
						, S.market_cap
						, CASE WHEN S.theme is null OR  S.theme = '' THEN S.sector ELSE S.theme END uprsn
						, S.stock_keyword
						, S.tracking_yn
						, S.tracking_reason
						, A.scenario
						, A.buy_band
						, CASE WHEN A.buy_pick = 'Y' THEN '<b>B</b>' ELSE '' END buy_pick
						, (SELECT COUNT(*) FROM calendar WHERE date <= A.scenario_date and date > A.watchlist_date) tracking_day
						, E.evening_subject
					FROM daily_watchlist_scenario A
					LEFT OUTER JOIN market_index B
					on B.date = A.scenario_date
					and B.market_fg = 'KOSPI'
					LEFT OUTER JOIN market_index C
					on C.date = A.scenario_date
					and C.market_fg = 'KOSDAQ'
					INNER JOIN stock D
					ON D.code = A.code
					AND D.last_yn = 'Y'
					LEFT OUTER JOIN market_report E
					ON E.report_date = A.scenario_date
					INNER JOIN daily_watchlist S
					ON S.watchlist_date = A.watchlist_date
					AND S.code = A.code
					WHERE A.scenario_date >= (select DATE_FORMAT(DATE_ADD('$search_date', INTERVAL -30 DAY), '%Y%m%d'))
					AND   A.scenario_date <= '$search_date'
					$search_buy_pick
				 ) Z
			ORDER BY Z.scenario_date desc, Z.watchlist_date desc, Z.tot_trade_amt desc";
// echo "<pre>$query</pre>";
$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm">
<?php
	$pre_scenario_date = "";
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$scenario_date = $row['scenario_date'];
		if($pre_scenario_date != $row['scenario_date']) {
			echo "<tr class='table-danger  text-dark' align=left><th colspan=6><b>▶ <a href=\"javascript:callFrameRD('".$row['scenario_date']."')\">".$row['scenario_date_str']."</b></a> 의 시나리오! &nbsp;&nbsp; (코스피 : ".$row['kospi_index']." , 코스닥 : ".$row['kosdaq_index'].") </th></tr>";
			
			if($row['evening_subject'] != '') 
				echo "<tr class='table-info  text-dark' align=left><th colspan=6>'".$row['evening_subject']."</th></tr>";
		}

		$bgstyle = ($row['tracking_day'] > 0) ? "background-color:#F6F6F6;" : "";
		echo "<tr style='$bgstyle'>";
		echo "<td class='text-danger' align=right>".$row['buy_pick']." </td>" ;
		echo "<td class='text-danger' align=center>".$row['uprsn']." </td>" ;
		echo "<td><a href=\"javascript:callFrameRS('".$row['watchlist_date']."','".$row['scenario_date']."','".$row['code']."','".$row['name']."')\"><b>".$row['name']."</b> [".$row['mavg']." / ".$row['status']."]</a></td>";
		echo "<td class='text-danger' align=right>".$row['close_rate']." %</td>" ;
		echo "<td class='text-danger' align=right>".number_format($row['tot_trade_amt'])." </td>" ;
		echo "<td class='text-danger' align=right>+".$row['tracking_day']."</td>" ;
		echo "</tr>" ;
		
		$pre_scenario_date =  $row['scenario_date'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameRS(wdate, sdate, cd, nm) {
	window.parent.viewStock(wdate, sdate, cd, nm);
}

// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameRD(date, cd, nm, idx) {
	window.parent.viewDay(date, cd, nm, idx);
}
</script>
</html>