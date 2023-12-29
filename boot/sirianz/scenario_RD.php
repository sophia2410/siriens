<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
<link rel="shortcut icon" href="#">
<style>
	.content {
	height: 400px;
	border: 0px solid hsl(0, 0%, 75%);
	}
	.content > img {
	width: auto;
	height: 100%;
	}
	input[type=radio] { margin-left: 5px }
	.scroll_box {
		width: 1800px;
		height: 183px;
		display: flex;
		overflow-x: auto;
	}
	table th, tr, td{
		padding: 0.1rem;
	}
</style>
</head>

<?php
$trade_date = (isset($_GET['trade_date'])) ? $_GET['trade_date'] : '';

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';
?>

<body>
<form name="form1" method='POST' action='scenario_script.php'>

<?php
	if($trade_date == ''){
		echo "<h3></h3>";
	} else {

		// 종목 키워드
		$query = "SELECT STR_TO_DATE(A.date, '%Y%m%d') date 
						, B.close kospi_close
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',B.close_rate,'% </font>') END kospi_index
						, C.close kosdaq_close
						, CASE WHEN C.close_rate >= 0 THEN CONCAT('<font color=red> ▲',C.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',C.close_rate,'% </font>') END kosdaq_index
					FROM calendar A
					LEFT OUTER JOIN market_index B
					ON B.date = A.date
					AND B.market_fg = 'KOSPI'
					LEFT OUTER JOIN market_index C
					ON C.date = A.date
					AND C.market_fg = 'KOSDAQ'
					WHERE A.date = '$trade_date'";

		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);

		$market_index = $row['date']."&nbsp;";
		$market_index .= " [ 코스피 ] ".number_format($row['kospi_close'])."&nbsp;";
		$market_index .= $row['kospi_index']."&nbsp;";
		$market_index .= " [ 코스닥 ] ".number_format($row['kosdaq_close'])."&nbsp;";
		$market_index .= $row['kosdaq_index'];
		
		// 지수
		echo "<div class='row'>
				<div class='card-body h5 mb-0 font-weight-bold text-gray-800'>
				$market_index 
				</div>
			  </div>";
		// //  지수 차트
		// echo "<div class='row'>
		// 		<div class='col-xl-3 col-md-6 mb-4'>
		// 			<div class='card-body'>
		// 				<div class='text-center'>
		// 					<img class='img-fluid' src='http://cichart.paxnet.co.kr/pax/chart/candleChart/V201714/paxCandleChartV201714Daily.jsp?abbrSymbol=001'>
		// 				</div>
		// 			</div>
		// 		</div>
		// 		<div class='col-xl-3 col-md-6 mb-4'>
		// 			<div class='card-body'>
		// 				<div class='text-center'>
		// 					<img class='img-fluid' src='http://cichart.paxnet.co.kr/pax/chart/candleChart/V201714/paxCandleChartV201714Min60.jsp?abbrSymbol=001'>
		// 				</div>
		// 			</div>
		// 		</div>
		// 		<div class='col-xl-3 col-md-6 mb-4'>
		// 			<div class='card-body'>
		// 				<div class='text-center'>
		// 					<img class='img-fluid' src='http://cichart.paxnet.co.kr/pax/chart/candleChart/V201714/paxCandleChartV201714Daily.jsp?abbrSymbol=501'>
		// 				</div>
		// 			</div>
		// 		</div>
		// 		<div class='col-xl-3 col-md-6 mb-4'>
		// 			<div class='card-body'>
		// 				<div class='text-center'>
		// 					<img class='img-fluid' src='http://cichart.paxnet.co.kr/pax/chart/candleChart/V201714/paxCandleChartV201714Min60.jsp?abbrSymbol=501'>
		// 				</div>
		// 			</div>
		// 		</div>
		// 	</div>";
			  
		echo "<div class='card-body h5 mb-0 font-weight-bold text-gray-800'>▷ 1일차 매매 대상 종목</div>";

		// 1일차 매매 시나리오
		$query = "	SELECT STR_TO_DATE(A.trade_date, '%Y%m%d') trade_date_str
						, A.trade_date
						, A.code
						, A.name
						, A.mavg
						, A.status
						, CASE WHEN A.close_rate >= 0 THEN CONCAT('<font color=red> ▲',A.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',A.close_rate,'% </font>') END close_rate
						, CASE WHEN A.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',A.tot_trade_amt,'억</b></font>') ELSE  CONCAT(A.tot_trade_amt,'억') END tot_trade_amt
						, A.volume
						, A.market_cap
						, A.issue
						, A.stock_keyword
						, A.last_0day
						, A.tracking_index
					FROM scenario A
					INNER JOIN (SELECT MAX(date) pre_date FROM calendar WHERE date < '$trade_date') B
					ON A.trade_date = B.pre_date
					WHERE A.status LIKE '0일차%'
					ORDER BY A.tot_trade_amt DESC";

		$result = $mysqli->query($query);
		echo "<div class='row'>";
		$i=0;
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			echo "<div class='col-xl-3 col-md-6 mb-4'>
					<div style='margin: 0; margin-left:10px'>
						<div class='row no-gutters align-items-center'>
							<div class='col mr-0'>
								<div class='font-weight-bold text-primary text-uppercase mb-1'>
								 ".$row['name']." (".$row['issue'].") ".$row['close_rate']." / ".$row['tot_trade_amt']."
								</div>
								<div style='margin: 0;'>
									<img class='img-fluid' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$row['code'].".png?sidcode=1681518352718'>
								</div>
							</div>
						</div>
					</div>
				  </div>";

			$i++;

			if($i%4 == 0) {
				echo "</div>";
				echo "<div class='row'>";
			}
		}
		echo "</div>";

		$query = "	SELECT STR_TO_DATE(A.trade_date, '%Y%m%d') trade_date_str
						, A.trade_date
						, A.buysell_reason
						, A.buysell_review
						, A.remark
						, A.tracking_index
					FROM scenario A
					WHERE A.trade_date = '$trade_date'
					AND A.code = 'DAY'";

		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);
		$buysell_review = (isset($row['buysell_review'])) ? $row['buysell_review'] : '';

		echo "<table class='table table-sm text-dark'>";
		echo "<tr align=left>";
		echo "<td width='100px'> [매매복기] </td>";
		echo "<td width='80px'> 매매이유 </td>";
		echo "<td><input type=text name='buysell_reason' value='1일차매매' readonly></td>";
		echo "<td><input type=button class='btn btn-danger btn-sm' onclick=\"save()\" value='저장'></td>";
		echo "</tr>";
		echo "<tr align=left>";
		echo "<td colspan=4 style='height:155px;'>";
		echo "<textarea name='buysell_review' style='width:95%; height:150px;'>$buysell_review</textarea>";
		echo "</td>";
		echo "</tr>";
		echo "</table>";

	}
?>
<input type=hidden name=proc_fg>
<input type=hidden name=trade_date value=<?=$trade_date?>>
<input type=hidden name=code value='DAY'>
</form>
<iframe name="saveFrame" src="scenario_script.php" style='border:0px;' width=1000 height=700>
</iframe>
</body>
<script>
// 일자 매매복기 저장
function save() {
	form = document.form1;
	form.proc_fg.value = 'saveDayReview';
	form.target = "saveFrame";
	form.submit();
}

// 거래일자 트레킹 대상 종목 가져오기
function getData(date) {
	form = document.form1;
	form.proc_fg.value = 'getData';
	form.trade_date.value = date;
	form.target = "saveFrame";
	form.submit();
}
</script>
</html>