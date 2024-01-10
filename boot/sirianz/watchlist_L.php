<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$watchlist_date = (isset($_GET['watchlist_date'])) ? $_GET['watchlist_date'] : date('Ymd',time());
$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;

// 다음거래일 구하기 (시나리오일자)
$query = "SELECT MIN(date) scenario_date FROM calendar WHERE date > ".date('Ymd',time());
$result = $mysqli->query($query);
$row = $result->fetch_array(MYSQLI_BOTH);
$scenario_date = $row['scenario_date'];

// $search_tracking_yn = (isset($_GET['tracking_yn'])) ? "AND A.tracking_yn ='".$_GET['tracking_yn'] ."'" : "AND A.tracking_yn ='Y'";
$search_buy_pick    = (isset($_GET['buy_pick'])    && $_GET['buy_pick']    != '') ? "AND A.buy_pick    ='".$_GET['buy_pick'] ."'" :'';

$query = " SELECT Z.*
			FROM (SELECT STR_TO_DATE(A.watchlist_date, '%Y%m%d') watchlist_date_str
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',B.close_rate,'% </font>') END kospi_index
						, CASE WHEN C.close_rate >= 0 THEN CONCAT('<font color=red> ▲',C.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',C.close_rate,'% </font>') END kosdaq_index
						, A.watchlist_date
						, D.code
						, D.name
						, A.regi_reason
						, A.close_rate
						, A.volume
						, A.tot_trade_amt
						, A.market_cap
						, A.tracking_yn
						, CASE WHEN A.theme is null OR  A.theme = '' THEN A.sector ELSE A.theme END uprsn
						, E.evening_subject
					FROM sirianz_watchlist A
					LEFT OUTER JOIN market_index B
					on B.date = A.watchlist_date
					and B.market_fg = 'KOSPI'
					LEFT OUTER JOIN market_index C
					on C.date = A.watchlist_date
					and C.market_fg = 'KOSDAQ'
					INNER JOIN stock D
					ON D.code = A.code
					AND D.last_yn = 'Y'
					LEFT OUTER JOIN sirianz_report E
					ON E.report_date = A.watchlist_date
					WHERE A.watchlist_date >= (select DATE_FORMAT(DATE_ADD('$watchlist_date', INTERVAL -30 DAY), '%Y%m%d'))
					AND   A.watchlist_date <= '$watchlist_date'
				 ) Z
			ORDER BY Z.watchlist_date desc, Z.tot_trade_amt desc";
// echo "<pre>$query</pre>";
$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm">
<!-- <table class="table table-sm small"> -->
<?php
	// 일자 변경확인을 위한 변수
	$pre_watchlist_date = "";

	// 출력된 종목명을 저장할 배열
	$printed = array();
	
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$watchlist_date = $row['watchlist_date'];
		if($pre_watchlist_date != $row['watchlist_date']) {
			echo "<tr class='table-danger  text-dark' align=left><th colspan=6>[관종일] <b>▶ <a href=\"javascript:callFrameRD('".$row['watchlist_date']."')\">".$row['watchlist_date_str']."</b></a> &nbsp;&nbsp; (코스피 : ".$row['kospi_index']." , 코스닥 : ".$row['kosdaq_index'].") </th></tr>";
			
			if($row['evening_subject'] != '') 
				echo "<tr class='table-info text-dark' align=left><th colspan=6>'".$row['evening_subject']."</th></tr>";
		}
		// 종목명을 변수에 저장
		$name = $row['name'];
		
		// 1000억이 넘는지 확인
		if($row['tot_trade_amt'] > 1000) {
			$stock_class = 'text-danger';
		}
		else {
			$stock_class = 'text-dark';
		}

		// 배열에 이미 있는지 확인
		if(in_array($name, $printed)) {
			$stock_style = 'text-decoration: line-through;';
		}
		else {
			$stock_style = '';
			array_push($printed, $name);
		}

		echo "<tr>";
		echo "<td class='text-info' align=center><b>".$row['uprsn']."</b></td>" ;
		echo "<td><a href=\"javascript:callFrameRS('".$row['watchlist_date']."','$scenario_date','".$row['code']."','".$row['name']."')\" class='$stock_class' style='$stock_style'><b>".$row['name']."</b></a></td>";
		echo "<td>[".$row['regi_reason']."]</td>" ;
		echo "<td class='text-danger' align=right>".$row['close_rate']." %</td>" ;
		echo "<td class='text-danger' align=right>".number_format($row['tot_trade_amt'])." </td>" ;
		echo "</tr>" ;
		
		$pre_watchlist_date =  $row['watchlist_date'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameRS(wdate, sdate, cd, nm) {
	window.parent.viewStock(wdate, sdate, cd, nm);
}

// parent 함수 호출, 오른쪽 프레임 일자별 내용 표시
function callFrameRD(date, cd, nm, idx) {
	window.parent.viewDay(date, cd, nm, idx);
}
</script>
</html>