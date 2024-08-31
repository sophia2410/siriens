<?php
	// 화면에서 일자 등 정보 클릭 시 해당 일자 그래프를 바로 보는 여러가지 버전을 시도했으나 속도 이슈로 중단. 현재는 팝업이 최선. // 24.06.28
	
	// 종목정보
	$code = isset($_GET['code']) ? $_GET['code'] : '';
	$name = isset($_GET['name']) ? $_GET['name'] : ''; 

	$pageTitle = "실시간 1Month-$name";
	
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
	require("xrayTick_queries.php");
?>

<?php
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

		// 가장 최근 0일차 상승이유
		echo "<tr><td rowspan=2>";
		if($row['group_key_str'] != '') {
			$info_0day =" <b>(".$row['uprsn'].")</b> ".$row['close_rate_str']." / ".$row['tot_trade_amt_str']."</font>"." &nbsp; ";
		} else {
			$info_0day = "<font class='h5'>&nbsp</font>";
		}
		
		// 종목 거래내역 // 장중 - 실시간데이터, 이외 - 마감데이터, 예상체결 - 예상체결데이터
		$realtime_data = "";
		if($row['trade_date'] != '') {
			$realtime_data = "<font class='h5'>".number_format($row['acc_trade_amount'])."억  &nbsp ".$row['trade_rate_str']." </font> &nbsp";
			$realtime_data .= "<font class='text-dark'>".number_format($row['trade_price'])."&nbsp ".number_format($row['market_cap'],2)."&nbsp ".$row['trade_date']."</font> ";
		}

		// 모차십 0일차 등록건이 있는 경우 건수 표시되게 함.
		$mochaten_cnt = '';
		if($row['mochaten_cnt'] > 0) {
			$mochaten_cnt = '<font color=red>('.$row['mochaten_cnt'].')</font>';
		}

		$stock_name = $row['name'];

		// xray_tick 조회 화면 연결
		$xray_tick_detail1 = "<a href='../watchlist/xrayTick_stock.php?stock=".$row['code']."&stock_nm=".$stock_name."' onclick='window.open(this.href, \'stock\', '');return false;' target='_blank'>(→)</a>";
		//그래프를 잘 보기 위해 팝업으로 연결
		$xray_tick_detail2 = "<a href='#' onclick=\"window.open('../watchlist/xrayTick_Stock_L.php?page_fg=popup&code=".$row['code']."&name=".$stock_name."', 'stock', 'width=1400,height=1800,left=680,top=0,screenX=680,screenY=0,scrollbars=yes'); return false;\" target='_blank'>(+)</a>";

		// echo "<div class='col-xl-3 col-md-6 mb-4' style='margin: 0; margin-left:10px margin-right:10px'>
		echo "<div class='row no-gutters align-items-center'>
				<div class='col mr-0'>
					<div class='font-weight-bold text-primary text-uppercase mb-1' style='height:35px; line-height:35px;'>$mochaten_cnt
						<font class='h4'><span class='draggable' id=stock_nm$d draggable='true'><b><a href='../siriens/stock_B.php?code=".$row['code']."&name=".$stock_name."&brWidth=2500' onclick='window.open(this.href, \'stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>".$stock_name."</a></b></span></font> $xray_tick_detail1 $xray_tick_detail2 &nbsp;".$realtime_data."
					</div>
					<div class='font-weight-bold mb-1 style='margin: 0;'>
						$info_0day
					</div>
					<div style='margin: 0; width:610px;'>
						<img class='img-fluid' id='stockChart_$d' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{$row['code']}.png?sidcode=1705826920773' onclick='toggleImage(\"stockChart_$d\", \"{$row['code']}\")' width='600'>
					</div>

				</div>
			</div>";
				
		$pre_group  = $row['group_key'];

		echo "</td>";

		$code = $row['code'];  // 현재 행의 코드 사용
		$zeroday_date = $row['0day_date'];  // 현재 행의 0day일자 사용

		// 종목 이슈 데이터 구해오기 // 0일차 이슈 구해오기
		$today_issue = '';

		if($pgmId == 'marketIssue' && $row['stock_comment'] != '') {
			$today_issue = $row['stock_comment'];
		} else {
			// 0일차가 아니어도 시그널이브닝에 이슈가 들어오는 경우가 있어, 수정해봄. 더 적합한 데이터로 변경 예정 24.06.29

			// $query3 = "SELECT CONCAT('[',A.signal_grp
			// 				, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
			// 				, A.title today_issue
			// 		FROM	rawdata_siri_report A
			// 		WHERE	page_date = (select max(date) from calendar where date <= '$zeroday_date')
			// 		AND  page_fg = 'E'
			// 		AND  code =  '$code'" ;
			$query3 = "SELECT CONCAT('[',A.signal_grp
							, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
							, A.title today_issue
					FROM	rawdata_siri_report A
					WHERE	page_date = (select max(date) from rawdata_siri_report where date <= '$search_date' and code = '$code')
					AND  page_fg = 'E'
					AND  code =  '$code'" ;

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
		$query2 = "SELECT cal.date, SUBSTR(STR_TO_DATE(cal.date, '%Y%m%d'),6) date_str, xray.close_rate, xray.tot_trade_amt, xray.amount, xray.cnt
					FROM calendar cal
					LEFT OUTER JOIN 
						(
							SELECT xr.code, xr.name, xr.date, dp.close_rate close_rate, round(dp.amount/100000000,0) tot_trade_amt, round(xr.tot_amt/100000000,1) amount,  xr.tot_cnt cnt
							FROM kiwoom_xray_tick_summary  xr
							LEFT OUTER JOIN daily_price dp
							ON dp.date = xr.date
							AND dp.code = xr.code
							WHERE xr.code = '$code'
						) xray
					ON xray.date = cal.date
					WHERE cal.date >= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD('$search_date', INTERVAL -30 DAY), '%Y%m%d')))
					AND cal.date <= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD('$search_date', INTERVAL 0 DAY), '%Y%m%d')))
					ORDER BY cal.date desc";
		// echo "<pre>$query2</pre>";
		$result2 = $mysqli->query($query2);

		// 종목 X-RAY 체결량 표시 - 변수 초기화
		$xray_date = "";
		$xray_close_rate = "";
		$xray_tot_amount = "";
		$xray_amount = "";
		$xray_cnt = "";

		while($row = $result2->fetch_array(MYSQLI_BOTH)) {
			$xray_date .= "<th align=center style='width:80px; height: 30px;'><a href=\"javascript:openPopupXrayTick('{$code}', '".$row['date']."')\">". $row['date_str']."</a></th>";
			
			if($row['cnt'] > 0) {

				// 등락률 따라 스타일 적용
				if($row['close_rate'] > 29.5)
					$rate_style = "class='text-danger font-weight-bold'";
				else if($row['close_rate'] > 15)
					$rate_style = "class='text-danger'";
				else
					$rate_style = "";

				// 총 거래대금에 따라 스타일 적용
				if($row['tot_trade_amt'] > 1000)
					$tot_amt_style = "background-color:#ffccd5;";
				else if($row['tot_trade_amt'] > 500)
					$tot_amt_style = "background-color:#fde2e4;";
				else
					$tot_amt_style = "";

				// xray 거래대금에 따라 스타일 적용
				if($row['amount'] > 500)
					$amt_style = "mark text-danger font-weight-bold h6";
				else if($row['amount'] > 100)
					$amt_style = "text-danger font-weight-bold h6";
				else
					$amt_style = "font-weight-bold";

				$xray_close_rate.= "<td align=center style='width:80px; height: 30px;' {$rate_style}>". $row['close_rate']."%</td>";
				$xray_tot_amount.= "<td align=center style='width:80px; height: 30px;{$tot_amt_style}'>". number_format($row['tot_trade_amt'])."억</td>";
				$xray_cnt       .= "<td align=center style='width:80px; height: 30px;'>". number_format($row['cnt'])."건</td>";
				$xray_amount    .= "<td align=center style='width:80px; height: 30px;' class='"."$amt_style"."'>". number_format($row['amount'])."억</td>";
			} else {
				$xray_close_rate.= "<td align=center>-</td>";
				$xray_tot_amount.= "<td align=center>-</td>";
				$xray_cnt       .= "<td align=center>-</td>";
				$xray_amount    .= "<td align=center>-</td>";
			}
		}

		// X-RAY 체결량 내역 표시
		echo "<table class='table table-sm table-bordered small text-dark' style='table-layout: fixed;' >";
		echo "<tr align=center style='background-color:#fdf9f5;'>".$xray_date."</tr>";
		echo "<tr align=center>".$xray_close_rate."</tr>";
		echo "<tr align=center>".$xray_tot_amount."</tr>";
		echo "<tr align=center><td colspan=100></td></tr>";
		echo "<tr align=center>".$xray_cnt."</tr>";
		echo "<tr align=center>".$xray_amount."</tr>";
		echo "</table>";
		$result2->free();

		
		// 등록 코멘트 불러오기
		$query = "SELECT comment, pick_yn, comment_date FROM kiwoom_xtay_tick_comments WHERE code = '$code' AND comment_date = '$today'";
		$result = $mysqli->query($query);
		if ($row = $result->fetch_assoc()) {
			$comment = $row['comment'];
			$pick = ($row['pick_yn'] == 'Y') ? 'checked' : '';
		} else {
			$comment = "";
			$pick    = "";
		}

		// 최근 코멘트를 가져와서 표시하는 부분 추가
		$query = "SELECT comment, pick_yn, comment_date FROM kiwoom_xtay_tick_comments WHERE code = '$code' AND comment_date < '$today' ORDER BY comment_date DESC LIMIT 1";
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
				<textarea class='form-control' style='display:flex' id=comment$d name=comment$d rows='2'>$comment</textarea>
			</div>";

		
		echo "</td></tr>";

		$d++;
	}
	
	echo "</table>";
?>
	<input type="hidden" name='proc_fg'>
	<input type="hidden" name='today' value='<?=$today?>'>
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

function toggleImage(imgId, code) {
    var img = document.getElementById(imgId);
    var candleURL = 'https://ssl.pstatic.net/imgfinance/chart/item/candle/day/' + code + '.png?sidcode=1705826920773';
    var areaURL = 'https://ssl.pstatic.net/imgfinance/chart/item/area/day/' + code + '.png?sidcode=1705826920773';

    if (img.src === candleURL) {
        img.src = areaURL;
    } else {
        img.src = candleURL;
    }
}
</script>
</body>
</html>