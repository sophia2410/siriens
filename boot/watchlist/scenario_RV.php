<?php
// 관심종목 + 시나리오 = 우측, 리뷰일별 종목리뷰 조회화면

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
	table {
		margin-left: 20px;
		margin-right: auto;
		margin-top: auto;
		margin-bottom: auto;
	}
	::placeholder {
		/* color: #B1CBDB; */
		color: #C0C0C0;
	}
	::-ms-input-placeholder {
		color: #C0C0C0;
	}
</style>
</head>

<body>
<form name="form1" method='POST' action='scenario_script.php'>

<?php
$search_fg   = (isset($_GET['search_fg'])) ? $_GET['search_fg'] : '';
$review_date = (isset($_GET['review_date'])) ? $_GET['review_date'] : '';

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';

echo "<b>리뷰일자 : $review_date</b> &nbsp;&nbsp;";
echo "종목추가기간 <input type=text name=day_cnt style='width:50px' value=30> <button class='btn btn-secondary btn-sm' onclick=addReviewStock()>추가하기</button>";

if($review_date == ''){
	echo "<h3></h3>";
} else {

		// 리뷰일 장 코멘트
		$query = "	SELECT STR_TO_DATE(A.review_date, '%Y%m%d') review_date_str
						, A.review_date
						, A.issue_status
					FROM daily_watchlist_review A
					WHERE A.review_date = '$review_date'
					AND A.code = 'DAY'";

		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);
		$day_issue_status = (isset($row['issue_status'])) ? $row['issue_status'] : '';

		echo "<div style='margin-left:10px;'>";
			echo "<div class='row' style='margin-left:10px;width:96%;'>";
			echo "<div class='font-weight-bold text-gray-800' style='width:20%; margin:0px; margin-left:10px; margin-top:10px; margin-bottom:10px;'>▷ $review_date 마켓 리뷰 ";
			echo "<input type=button class='btn btn-danger btn-sm' onclick=saveRV() value='리뷰저장'></div>";
			echo "</div>";
			
			echo "<div class='row' style='margin-left:10px;margin-bottom:10px;width:96%;'>";
			echo "<td><textarea name='day_issue_status' style='width:96%;height:80px' placeholder='시장리뷰'>".$day_issue_status."</textarea>";
			echo "</div>";
		echo "</div>";

	// 종목 리뷰
	echo "<div>";
	echo "<table style='width:95%;' class='table table-bordered small table-sm text-dark'>";
	// echo "<tr align=center class='table-success'>";
	// echo "<th rowspan=3>종목</th>";
	// 	echo "<th>최근관종일자</th>";
	// 	echo "<th>섹터-테마</th>";
	// 	echo "<th>이슈</th>";
	// 	echo "<th colspan=2>0,1일차 등락률</th>";
	// echo "</tr>";
	// echo "<tr align=center class='table-success'>";
	// 	echo "<th colspan=2>인접이평선</th>";
	// 	echo "<th>재료코멘트</th>";
	// 	echo "<th colspan=2>차트코멘트</th>";
	// echo "</tr>";
	// echo "<tr align=center class='table-success'>";
	// 	echo "<th>PICK</th>";
	// 	echo "<th>WHO?</th>";
	// 	echo "<th>매매코멘트</th>";
	// 	echo "<th>WIN?</th>";
	// 	echo "<th>성공실패리뷰</th>";
	// echo "</tr>";

	// 리뷰종목 리스트
	$orderby = "A.review_date DESC, B.tot_trade_amt DESC";
	$orderby = "B.sector, B.theme, A.review_date DESC, C.close_rate DESC";
	$orderby = "X.sector_sum DESC, R.group_sum DESC, B.tot_trade_amt DESC";

	$query="SELECT STR_TO_DATE(A.review_date, '%Y%m%d') review_date_str
				, A.review_date
				, A.code
				, A.name
				, A.mavg
				, A.issue_status
				, A.issue_score
				, A.chart_status
				, A.chart_score
				, A.chart_status
				, A.pick_yn
				, A.feat
				, A.buy_yn
				, A.buy_date
				, A.sell_yn
				, A.sell_date
				, A.buysell_review
				, A.win_yn
				, A.win_loss_review
				, CASE WHEN B.close_rate >= 0 THEN CONCAT('[0일차] <font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('0일차 <font color=blue> ▼',B.close_rate,'% </font>') END close_rate
				, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',B.tot_trade_amt,'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt
				, CONCAT('[시총] ',B.market_cap, '억 ') market_cap
				, CASE WHEN C.date <=A.review_date THEN CONCAT('[1일차] (시) ', C.open_rate, '%, (고) ', C.high_rate, '% (저) ', C.low_rate, '% (종) ', C.close_rate,'%  / ') ELSE '' END nextday_rate
				, CASE WHEN C.date <=A.review_date THEN CONCAT('',ROUND(C.amount/100000000,0),'억') ELSE '' END nextday_amt
				, B.volume
				, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
				, B.issue
				, B.0day_date
				, R.group_sum
				, X.sector_sum
			FROM daily_watchlist_review A
			INNER JOIN 0day_stocks B
			ON A.0day_date = B.0day_date
			AND A.code = B.code
			LEFT OUTER JOIN daily_price C
			ON C.date = (SELECT min(date) FROM calendar WHERE date > B.0day_date)
			AND C.code = B.code
			INNER JOIN (	SELECT G.sector, G.theme, SUM(G.tot_trade_amt) AS group_sum, MAX(G.0day_date) AS group_max_date
							FROM daily_watchlist_review S
							INNER JOIN 0day_stocks G
							ON S.0day_date = G.0day_date
							AND S.code = G.code
							WHERE S.review_date = '$review_date'
							GROUP BY G.sector, G.theme
						) R
			ON B.sector = R.sector
			AND B.theme  = R.theme
			INNER JOIN (	SELECT G.sector, SUM(G.tot_trade_amt) AS sector_sum, MAX(G.0day_date) AS sector_max_date
							FROM daily_watchlist_review S
							INNER JOIN 0day_stocks G
							ON S.0day_date = G.0day_date
							AND S.code = G.code
							WHERE S.review_date = '$review_date'
							GROUP BY G.sector
						) X
			ON B.sector = X.sector
			WHERE A.review_date = '$review_date'
			ORDER BY $orderby";

	// echo "<pre>".$query."</pre><br>";
	$result = $mysqli->query($query);

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($i>0) {
			//종목 구별하기 위한 tr 추가
			echo "<tr align=center>";
				echo "<td colspan=6 style='padding:0px; height:2px; background-color:#888888'></td>";
			echo "</tr>";
		}

		$checked1 = ($row['pick_yn'] == 'Y') ? " checked" : "";
		$checked2 = ($row['win_yn']  == 'Y') ? " checked" : "";
		$checked3 = ($row['sell_yn'] == 'Y') ? " checked" : "";
		
		// 이전 매수 일자가 있는 경우는 매수 일자 표시. 없는 경우는 체크박스 표시
		if($row['buy_yn']  == 'Y') {
			if($row['buy_date'] != $review_date) {
				$buy_yn =  substr($row['buy_date'],4,4);
			} else {
				$buy_yn = "<input type=checkbox name=buy_yn$i value='Y' checked>";
			}
			
			$buytdclass = 'table-danger';
		} else {
			$buy_yn = "<input type=checkbox name=buy_yn$i value='Y'>";
			$buytdclass = '';
		}

		if($row['pick_yn'] == 'Y') $trclass = 'table-info';
		else  $trclass = 'table-secondary';
		echo "<tr align=center class=table-hover>";
			echo "<td rowspan=3 class='$trclass h6'><br><b>".$row['name']."</b></td>";
			echo "<td width=100>".$row['review_date_str']."</td>";
			echo "<td width=250>".$row['uprsn']."</td>";
			echo "<td><input type=text style='width:95%; border: solid thin #f77d72;' name=mavg$i placeholder='차트자리요약' value='".$row['mavg']."'></td>";
			echo "<td colspan=2>".$row['close_rate'].$row['tot_trade_amt']." &nbsp; ".$row['market_cap']." &nbsp; ".$row['nextday_rate'].$row['nextday_amt']."</td>";
		echo "</tr>";
		// 등록하면서 재료,차트점수는 수치로는 등록을 안해서 일단 화면에서 제외하기 24.01.18
		// echo "<tr align=center class=table-hover'>";
		// 	echo "<td colspan=2><input type=text style='width:95%; border: solid thin #f77d72;' name=mavg$i placeholder='인접이평선' value='".$row['mavg']."'>";
		// 	echo "<input type=text style='width:47%' name=issue_score$i placeholder='재료점수(1-10)' value=".$row['issue_score'].">";
		// 	echo "<input type=text style='width:47%' name=chart_score$i placeholder='차트점수(1-10)' value=".$row['chart_score']."></td>";
		// 	echo "<td><textarea name='issue_status$i' style='width:95%; height:85px;' placeholder='재료코멘트'>".$row['issue_status']."</textarea></td>";
		// 	echo "<td colspan=2><textarea name='chart_status$i' style='width:95%; height:85px;' placeholder='차트코멘트'>".$row['chart_status']."</textarea></td>";
		// echo "</tr>";
		echo "<tr align=center class=table-hover'>";
			echo "<td colspan=2 align=left> &nbsp; ".$row['issue']."</td>";
			echo "<td colspan=3 rowspan=2><textarea name='chart_status$i' style='width:99%; height:75px;' placeholder='차트코멘트'>".$row['chart_status']."</textarea></td>";
		echo "</tr>";
		echo "<tr align=center class=table-hover'>";
			echo "<td colspan=2><textarea name='issue_status$i' style='width:95%;' placeholder='재료코멘트'>".$row['issue_status']."</textarea></td>";
		echo "</tr>";
		echo "<tr align=center>";
			echo "<td>매매대상픽? <input type=checkbox name=pick_yn$i value='Y' $checked1><br>트래킹종료? <input type=checkbox name=traking_yn$i value='N'></td>";
			echo "<td align=left class='$buytdclass'>매수? $buy_yn<br>";
			echo "매도? <input type=checkbox name=sell_yn$i value='Y' $checked3></td>";
			
			echo "<td><textarea name='feat$i' style='width:95%; height:60px; ;' placeholder='빌리언/차읽남'>".$row['feat']."</textarea>";
			echo "<td><textarea name='buysell_review$i' style='width:95%; height:60px; ;' placeholder='매매리뷰'>".$row['buysell_review']."</textarea>";
			echo "<td width=80>수익? <input type=checkbox name=win_yn$i value='Y' $checked2></td>";
			echo "<td><textarea name='win_loss_review$i' style='width:95%; height:60px; ;' placeholder='성공실패복기'>".$row['win_loss_review']."</textarea>";
			echo "	  <input type=hidden name=code$i value=".$row['code']."></td>";
		echo "</tr>";
		$i++;
	}
	echo "</table>";
	echo "</div>";
}
?>
<input type=hidden name=proc_fg>
<input type=hidden name=review_date value=<?=$review_date?>>
<input type=hidden name=search_fg value=<?=$search_fg?>>
<input type=hidden name=cnt value=<?=$i?>>
<!-- 관심종목 가져오기 처리하기 위해 추가 -->
<input type=hidden name=0day_date>
<input type=hidden name=code>
</form>
<iframe name="saveFrame" src="scenario_script.php" style='border:0px;' width=1000 height=700>
</iframe>
</body>
<script>
// 일자 매매복기 저장
function saveRV() {
	form = document.form1;
	form.proc_fg.value = 'saveReview';
	form.target = "saveFrame";
	form.submit();
}

// 리뷰일자 트레킹 대상 종목 가져오기
function addReviewStock(date) {
	form = document.form1;
	form.proc_fg.value = 'getReviewData';
	form.target = "saveFrame";
	form.submit();
}


// 관종등록일자 0일차 종목 가져오기
function getWatchlist(date) {
	form = document.form1;
	form.proc_fg.value = 'getWatchlist';
	form.0day_date.value = date;
	form.target = "saveFrame";
	form.submit();
}

// 입력 종목 추가하기
function addWatchlist(date, stock) {
	form = document.form1;
	form.proc_fg.value = 'addWatchlist';
	form.0day_date.value = date;
	form.stock.value = stock;
	form.target = "saveFrame";
	form.submit();
}
</script>
</html>