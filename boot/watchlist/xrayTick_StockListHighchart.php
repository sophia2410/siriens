<?php
// 화면에서 일자 등 정보 클릭 시 해당 일자 그래프를 바로 보는 여러가지 버전을 시도했으나 속도 이슈로 중단. 현재는 팝업이 최선. // 24.06.28

// 종목정보
$code = isset($_GET['code']) ? $_GET['code'] : '';
$name = isset($_GET['name']) ? $_GET['name'] : ''; 

$pageTitle = "실시간 1Month-$name";

require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
require("xrayTick_queries.php");
	
$pgmId = (isset($_GET['pgmId'])) ? $_GET['pgmId'] : '';

$search_date   = (isset($_GET['search_date'])  ) ? $_GET['search_date']   : date('Ymd');
$increase_rate = (isset($_GET['increase_rate'])) ? $_GET['increase_rate'] : 10;
$trade_amt     = (isset($_GET['trade_amt'])    ) ? $_GET['trade_amt'] : 0;

$sector   = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme 	  = (isset($_GET['theme']))  ? $_GET['theme']  : '';
$category = '';

$buy_cnt 	= (isset($_GET['buy_cnt']))     ? $_GET['buy_cnt']    : '';
$buy_period = (isset($_GET['buy_period']))  ? $_GET['buy_period'] : '';
$zeroday_view = (isset($_GET['zeroday_view']))  ? $_GET['zeroday_view'] : '';

$chart_status = (isset($_GET['chart_status']))  ? $_GET['chart_status'] : '';
$frequency = (isset($_GET['frequency']))  ? $_GET['frequency'] : '';

// 시세 정보 불러오기 위한 처리 (실시간 시세를 구해올 수 없어 장중엔 naver 크롤링 데이터 보기)
$query_basedate = getBaseDateQuery($search_date);
$result_basedate = $mysqli->query($query_basedate);
$row = $result_basedate->fetch_array(MYSQLI_BOTH);
$base_date = $row['base_date'];

$result_trade = getTradeQuery($base_date);
$trade_qry = $result_trade['query'];
$trade_table = $result_trade['table'];
$today = $result_trade['today'];
?>

<style>
    th, td {
        padding: .2rem !important;
    }
</style>

<body>
<form name="form1" method='POST' action='xrayTick_script.php' onsubmit="return false">

<?php
if($pgmId == '') {
	echo "<h3>원하는 검색 버튼 선택</h3>";
	$ready = 'N';
} else {
	$result = getQuery($pgmId, $search_date, $increase_rate, $trade_amt, $sector, $theme, $category, $buy_cnt, $buy_period, $zeroday_view, $chart_status, $frequency, $trade_qry, $trade_table, $base_date);
	$query = $result['query'];
	$filename = $result['filename'];
	$file_orderby = $result['file_orderby'];
	// echo "<pre>$query</pre>";

	// 특징주 등록을 위한 엑셀파일용 쿼리문, 파이썬 프로그램에서 사용.
	$text =  "xraytick_".$filename. "\n" .$file_orderby. "\n" .$query;
	file_put_contents('E:/Project/202410/www/pyObsidian/vars_downExcel.txt', $text);

	$result1 = $mysqli->query($query);
	$watchlist_data = [];
	
	while ($row = $result1->fetch_assoc()) {
		$watchlist_data[] = $row;
	}
	
	$result1->free();
	
	echo "<table class='table table-sm table-bordered text-dark'>";
				
	$d=0;
	$pre_group = '';
	foreach ($watchlist_data as $row) {
		// 그룹별 분리표시
		if($pre_group != $row['group_key']) {
			if($pre_group != '') {
				echo "</tr>";
			}
			echo "<tr><td colspan=2 class='table-danger'><b>▷ ".$row['group_key_str']."</b></td></tr>";
		}

		echo "<tr><td rowspan=2>";

			// 전체 HTML 출력
			echo generateStockHtml($row, false, 'commonStockLink');
				
		$pre_group  = $row['group_key'];
		$stock_name = $row['name'];

		echo "</td>";

		$code = $row['code'];  // 현재 행의 코드 사용
		$zeroday_date = $row['date'];  // 현재 행의 0day일자 사용

		// 종목 이슈 데이터 구해오기 // 0일차 이슈 구해오기
		$today_issue = '';

		if($pgmId == 'marketIssue' && $row['stock_comment'] != '') {
			$today_issue = $row['stock_comment'];
		} else {
			// 0일차가 아니어도 시그널이브닝에 이슈가 들어오는 경우가 있어, 수정해봄. 더 적합한 데이터로 변경 예정 24.06.29
			// $query3 = "SELECT CONCAT('[',A.signal_grp
			// 				, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
			// 				, A.title today_issue
			// 		FROM	signal_evening A
			// 		WHERE	page_date = (select max(date) from calendar where date <= '$zeroday_date')
			// 		AND  page_fg = 'E'
			// 		AND  code =  '$code'" ;

			$query3 = "SELECT CONCAT('[',A.keyword_group_name, ']<BR>') today_theme
							, A.stock_comment today_issue
					FROM	v_market_event A
					WHERE	date <= '$search_date'
					AND  code =  '$code'
					ORDER BY date DESC
					LIMIT 1" ;

			// echo "<pre>$query3</pre>";
			$result3 = $mysqli->query($query3);

			while($row = $result3->fetch_array(MYSQLI_BOTH)) {
				$today_issue = $row['today_theme']." <b>".$row['today_issue']."</b>";
			}
		}

		// 로딩속도 이슈로 잠시 막아두기 24.06.19
		// 없을 경우 최근뉴스 가져오기
		// if($today_issue == '') {
			// $query4 = "SELECT  date
			// 				, title
			// 				, link
			// 			FROM signals B
			// 			WHERE B.code =  '$code'
			// 			AND B.date <= (select max(date) from calendar where date <= '$search_date')
			// 			ORDER BY date DESC
			// 			LIMIT 1 " ;

			// // echo "<pre>$query4</pre>";
			// $result4 = $mysqli->query($query4);

			// while( $row = $result4->fetch_array(MYSQLI_BOTH)) {
			// 	$today_issue = '('.$row['date'].')'.$row['title'];
			// }
		// }

		echo "<td width=60%>$today_issue</td></tr><tr><td>";

		// X-RAY 순간체결 거래량 쿼리 실행
		$query2 = "SELECT cal.date, DATE_FORMAT(cal.date, '%m-%d') mm_dd, xray.close_rate, xray.high_rate, xray.low_rate, xray.trade_amount, xray.amount, xray.cnt
					FROM (
						SELECT date
						FROM calendar
						WHERE date <= '$search_date'
						ORDER BY date DESC
						LIMIT 22
					) cal
					LEFT OUTER JOIN (
						SELECT code, 
							date, 
							MAX(close_rate) AS close_rate, 
							MAX(high_rate) AS high_rate, 
							MAX(low_rate) AS low_rate, 
							SUM(trade_amount) AS trade_amount, 
							SUM(amount) AS amount, 
							SUM(cnt) AS cnt
						FROM (
							-- daily_price 테이블에서 데이터를 가져옵니다.
							SELECT dp.code, dp.date, dp.close_rate, dp.high_rate, dp.low_rate, 
								ROUND(dp.amount / 100000000, 0) AS trade_amount, 
								NULL AS amount, 
								NULL AS cnt
							FROM daily_price dp
							WHERE dp.code = '$code'

							UNION ALL

							-- naver_finance_stock에서 대체 데이터를 가져옵니다. daily_price에 데이터가 없는 경우에만.
							SELECT nfs.code, nfs.date, nfs.change_rate AS close_rate, 
								0.0 AS high_rate, 0.0 AS low_rate, 
								ROUND(nfs.volume * nfs.current_price / 100000000, 2) AS trade_amount, 
								NULL AS amount, 
								NULL AS cnt
							FROM naver_finance_stock nfs
							WHERE nfs.code = '$code'
							AND NOT EXISTS (
								SELECT 1
								FROM daily_price dp
								WHERE dp.code = nfs.code
								AND dp.date = nfs.date
								AND dp.amount IS NOT NULL
							)

							UNION ALL

							-- xraytick_summary에서 데이터를 가져옵니다.
							SELECT kxt.code, kxt.date, NULL AS close_rate, NULL AS high_rate, NULL AS low_rate, 
								NULL AS trade_amount, 
								ROUND(kxt.tot_amt / 100000000, 1) AS amount, 
								kxt.tot_cnt AS cnt
							FROM xraytick_summary kxt
							WHERE kxt.code = '$code'
						) AS combined_data
						GROUP BY code, date
					) xray
					ON xray.date = cal.date
					ORDER BY cal.date DESC";
		// echo "<pre>$query2</pre>";
		$result2 = $mysqli->query($query2);

		// 종목 X-RAY 체결량 표시 - 변수 초기화
		$xray_date = "";
		$xray_close_rate = "";
		$xray_highlow_rate = "";
		$xray_tot_amount = "";
		$xray_amount = "";
		$xray_cnt = "";

		while($row = $result2->fetch_array(MYSQLI_BOTH)) {
			$xray_date .= "<th align=center style='width:80px; height:25px;'><a href=\"javascript:openPopupXrayTick('{$code}', '".$row['date']."')\">". $row['mm_dd']."</a></th>";
			
			if($row['cnt'] > 0) {

				// 등락률 따라 스타일 적용
				if($row['close_rate'] > 29.5)
					$rate_style = "class='text-danger font-weight-bold'";
				else if($row['close_rate'] > 15)
					$rate_style = "class='text-danger'";
				else
					$rate_style = "";

				// 총 거래대금에 따라 스타일 적용
				if($row['trade_amount'] > 1000)
					$tot_amt_style = "background-color:#ffccd5;";
				else if($row['trade_amount'] > 500)
					$tot_amt_style = "background-color:#fde2e4;";
				else
					$tot_amt_style = "";

				// xray 거래대금에 따라 스타일 적용
				if($row['amount'] > 500)
					$amt_style = "mark text-danger font-weight-bold";
				else if($row['amount'] > 100)
					$amt_style = "text-danger font-weight-bold";
				else
					$amt_style = "font-weight-bold";

				$xray_close_rate.= "<td align=center style='width:80px; height: 25px;' {$rate_style}>". $row['close_rate']."%</td>";
				$xray_highlow_rate.= "<td align=center style='width:80px; height: 25px; font-size:10px'>{$row['high_rate']} / {$row['low_rate']}</td>";
				$xray_tot_amount.= "<td align=center style='width:80px; height: 25px;{$tot_amt_style}'>". number_format($row['trade_amount'])."억</td>";
				$xray_cnt       .= "<td align=center style='width:80px; height: 25px;'>". number_format($row['cnt'])."건</td>";
				$xray_amount    .= "<td align=center style='width:80px; height: 25px;' class='"."$amt_style"."'>". number_format($row['amount'])."억</td>";
			} else {
				$xray_close_rate.= "<td align=center>-</td>";
				$xray_highlow_rate.= "<td align=center>-</td>";
				$xray_tot_amount.= "<td align=center>-</td>";
				$xray_cnt       .= "<td align=center>-</td>";
				$xray_amount    .= "<td align=center>-</td>";
			}
		}

		// X-RAY 체결량 내역 표시
		echo "<table class='table table-sm table-bordered small text-dark' style='table-layout: fixed;' >";
		echo "<tr align=center style='background-color:#fdf9f5;'>".$xray_date."</tr>";
		echo "<tr align=center>".$xray_close_rate."</tr>";
		echo "<tr align=center>".$xray_highlow_rate."</tr>";
		echo "<tr align=center>".$xray_tot_amount."</tr>";
		echo "<tr align=center><td colspan=100></td></tr>";
		echo "<tr align=center>".$xray_cnt."</tr>";
		echo "<tr align=center>".$xray_amount."</tr>";
		echo "</table>";
		$result2->free();

		
		// 등록 코멘트 불러오기
		$query = "SELECT comment, pick_yn, comment_date FROM xraytick_review_comments WHERE code = '$code' AND comment_date = '$search_date'";
		$result = $mysqli->query($query);
		if ($row = $result->fetch_assoc()) {
			$comment = $row['comment'];
			$pick = ($row['pick_yn'] == 'Y') ? 'checked' : '';
		} else {
			$comment = "";
			$pick    = "";
		}

		// 최근 코멘트를 가져와서 표시하는 부분 추가
		$query = "SELECT comment, pick_yn, comment_date FROM xraytick_review_comments WHERE code = '$code' AND comment_date < '$search_date' ORDER BY comment_date DESC LIMIT 1";
		$result = $mysqli->query($query);
		if ($row = $result->fetch_assoc()) {
			$pick_yn = ($row['pick_yn'] =='Y') ? "<b><font color=red>PICK</font></b>" : "";
			
			echo "<div class='recent-comment'>
				<small>작성일: {$row['comment_date']}</small>
				<p>{$pick_yn} {$row['comment']}</p>
			</div>";

			// <h5>최근 코멘트:</h5>
		}

		echo "<input type='hidden' name=code$d value='$code'>
			<input type='hidden' name=name$d value='$stock_name'>
			<div class='form-group' style='display: flex; align-items: center;'>
				<input type=checkbox name=pick_yn$d value='Y' style='margin-right: 10px;'>
				<textarea class='form-control' style='display:flex' id=comment$d name=comment$d rows='1'>$comment</textarea>
			</div>";

		
		echo "</td></tr>";

		$d++;
	}
	
	echo "</table>";
?>
	<input type="hidden" name='proc_fg'>
	<input type="hidden" name='save_date' value='<?=$search_date?>'>
	<input type="hidden" name='tot_cnt' value='<?=$d?>'>
</form>
<?php
}
?>
<iframe name="saveFrame" src="xrayTick_script.php" style='border:0px;' width=0 height=0>
</iframe>

<script>
function openPopupXrayTick(code, date) {
    var url = "/boot/common/popup/stock_xray_tick.php?code=" + code + "&date=" + date;
    var newWindow = window.open(url, "pop", "width=1200,height=1500,scrollbars=yes,resizable=yes");
    if (window.focus) {
        newWindow.focus();
    }
}

// 코멘트 저장
function saveComment() {
	form = document.forms[0];
	form.proc_fg.value = 'CS';
	form.target = "saveFrame";
	form.submit();
}

// 화면 리자이즈 시 이미지 로드 되는 문제인가 해서 일단 막아봄 24.11.14
// function toggleImage(imgId, code) {
//     var img = document.getElementById(imgId);
//     var candleURL = 'https://ssl.pstatic.net/imgfinance/chart/item/candle/day/' + code + '.png?sidcode=1705826920773';
//     var areaURL = 'https://ssl.pstatic.net/imgfinance/chart/item/area/day/' + code + '.png?sidcode=1705826920773';

//     if (img.src === candleURL) {
//         img.src = areaURL;
//     } else {
//         img.src = candleURL;
//     }
// }


// 종목명 클릭 시 팝업창
function openStockPopup(code, name) {
    var url = "/modules/market/stock_report_popup.php?code=" + encodeURIComponent(code) + "&name=" + encodeURIComponent(name);
    window.open(url, '_blank');
}
</script>
</body>
</html>