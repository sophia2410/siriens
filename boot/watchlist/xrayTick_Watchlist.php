<?php

	// 종목정보
	$code = isset($_GET['code']) ? $_GET['code'] : '';
	$name = isset($_GET['name']) ? $_GET['name'] : ''; 

	$pageTitle = "실시간 1Month-$name";
	
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
<head>
<style>
.small-fraction {
    font-size: 0.75em; /* 소수점 이하 값을 작게 표시 */
}
.cut-text {
    max-width: 120px; /* 최대 너비 설정 */
    white-space: nowrap; /* 텍스트를 한 줄로 표시 */
    overflow: hidden; /* 내용이 넘칠 경우 숨김 */
    text-overflow: ellipsis; /* 넘친 내용을 생략 부호로 표시 */
    /* cursor: help; */ /* 마우스 오버 시 커서 모양 변경 */
}
.cut-text2 {
    max-width: 75px; /* 최대 너비 설정 */
    white-space: nowrap; /* 텍스트를 한 줄로 표시 */
    overflow: hidden; /* 내용이 넘칠 경우 숨김 */
    text-overflow: ellipsis; /* 넘친 내용을 생략 부호로 표시 */
    cursor: help; /* 마우스 오버 시 커서 모양 변경 */
}
</style>
</head>

<?php
$search_date  = (isset($_GET['search_date'])  ) ? $_GET['search_date']   : '';

// 기준일자를 구하는 쿼리.
$query = "
    SELECT MAX(date) AS base_date
    FROM calendar 
    WHERE date <= (
        SELECT DATE_FORMAT(
            DATE_ADD(
                STR_TO_DATE($search_date, '%Y%m%d'), 
                INTERVAL IF(CURTIME() < '09:00:00', -1, 0) DAY
            ), 
            '%Y%m%d'
        )
    )";
$result = $mysqli->query($query);
$row = $result->fetch_array(MYSQLI_BOTH);
$base_date = $row['base_date'];

// $pgmId sophiaWatchlist 만 실행되도록 함. + 테마정보가 있는 경우만 실행되도록 추가 수정. 24.01.24
date_default_timezone_set('Asia/Seoul');
$today = date('Ymd');  // 현재 일자를 구함
$now = date('H:i:s');  // 현재 시간을 구함

if ($today == $base_date && $now >= '09:00:0' && $now <= '18:30:00') {
	// 당일 시세 정보 등록전에는 실시간 등록 정보에서 받아오기..
	$trade_qry = "
		, CONCAT('*',  DATE_FORMAT(concat(Z.date, Z.time), '%m/%d %H:%i')) trade_date
		, Z.price trade_price
		, CASE WHEN Z.rate >= 0 THEN CONCAT('<font color=red> +',Z.rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.rate),'% </font>') END trade_rate_str
		, FLOOR(Z.acc_trade_amount/100) acc_trade_amount";
	$trade_table = "kiwoom_realtime";
} else {
	// 당일 시세 정보 가져오기
	$trade_qry = "
	, DATE_FORMAT(Z.date, '%m/%d') trade_date
	, Z.close trade_price
	, CASE WHEN Z.close_rate >= 0 THEN CONCAT('<font color=red> +',Z.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.close_rate),'% </font>') END trade_rate_str
	, FLOOR(Z.amount/100000000) acc_trade_amount";
	$trade_table = "daily_price";
}
?>

<body>
<form name="form1" method='POST' action='siriensEvening_script.php' onsubmit="return false">

<?php
if($search_date == '') {
	echo "<h3>일자 정보가 없습니다.</h3>";
	$ready = 'N';
} else {
	// 0일차 관종 목록 구해오기
	$query = "	SELECT STR_TO_DATE(A.date, '%Y%m%d') group_key
					, CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(B.watchlist_date, '%Y%m%d'),'</font>') watchlist_date_str
					, B.code
					, B.name
					, '' talent_fg
					, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
					, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
					, B.tot_trade_amt
					, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
					, B.stock_keyword
					, B.watchlist_date
					, X.sector_max_date
					, X.sector_sum
					, R.group_sum
					$trade_qry
					, M.mochaten_cnt
				FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 5) A
				INNER JOIN daily_watchlist B
				ON A.date = B.watchlist_date
				INNER JOIN (	SELECT G.sector, G.theme, SUM(G.tot_trade_amt) AS group_sum, MAX(G.watchlist_date) AS group_max_date
								FROM daily_watchlist G
								INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 5) S
								ON S.date = G.watchlist_date
								GROUP BY G.sector, G.theme
							) R
				ON B.sector = R.sector
				AND B.theme  = R.theme
				INNER JOIN (	SELECT G.sector, SUM(G.tot_trade_amt) AS sector_sum, MAX(G.watchlist_date) AS sector_max_date
								FROM daily_watchlist G
								INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 5) S
								ON S.date = G.watchlist_date
								GROUP BY G.sector
							) X
				ON B.sector = X.sector
				LEFT OUTER JOIN $trade_table Z
				ON Z.code = B.code
				AND Z.date = '$base_date'
				LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
				ON M.code = B.code
				ORDER BY B.watchlist_date DESC, B.tot_trade_amt DESC";

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
			echo "<tr><td colspan=2 class='table-danger'>▷ ".$row['group_key']."</td></tr>";
		}

		// 가장 최근 0일차 상승이유
		echo "<tr><td rowspan=2>";
		if($row['watchlist_date_str'] != '') {
			$info_0day =" <b>(".$row['uprsn'].")</b> ".$row['close_rate_str']." / ".$row['tot_trade_amt_str']."</font>"." &nbsp; ".$row['watchlist_date_str'];
		} else {
			$info_0day = "<font class='h5'>&nbsp</font>";
		}
		
		// 종목 거래내역 // 장중 - 실시간데이터, 이외 - 마감데이터, 예상체결 - 예상체결데이터
		$realtime_data = "";
		if($row['trade_date'] != '') {
			$realtime_data = "<font class='h5'>".number_format($row['acc_trade_amount'])."억  &nbsp ".$row['trade_rate_str']." </font> &nbsp";
			$realtime_data .= "<font class='text-dark'>".number_format($row['trade_price'])."&nbsp ".$row['trade_date']."</font> ";
		}
		
		// 모차십 0일차 등록건이 있는 경우 건수 표시되게 함.
		$mochaten_cnt = '';
		if($row['mochaten_cnt'] > 0) {
			$mochaten_cnt = '<font color=red>('.$row['mochaten_cnt'].')</font>';
		}

		// echo "<div class='col-xl-3 col-md-6 mb-4' style='margin: 0; margin-left:10px margin-right:10px'>
		echo "<div class='row no-gutters align-items-center'>
				<div class='col mr-0'>
					<div class='font-weight-bold text-primary text-uppercase mb-1' style='height:35px; line-height:35px;'>$mochaten_cnt
						<font class='h4'><span class='draggable' id=stock_nm$d draggable='true'><b>[[".$row['name']."]]</b></span>".$row['talent_fg']."<a href='../siriens/stock_B.php?code=".$row['code']."&name=".$row['name']."&brWidth=2500' onclick='window.open(this.href, \'stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>..</a></font> &nbsp;".$realtime_data."
					</div>
					<div class='font-weight-bold mb-1 style='margin: 0;'>
						$info_0day
					</div>
					<div style='margin: 0;'>
						<img class='img-fluid' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$row['code'].".png?sidcode=1705826920773'>
					</div>
				</div>
			</div>";
				
		$d++;
		$pre_group  = $row['group_key'];

		echo "</td>";

		$code = $row['code'];  // 현재 행의 코드 사용

		// 종목 이슈 데이터 구해오기
		$today_issue = '';
		$query3 = "SELECT CONCAT('[',A.signal_grp
						, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
						, A.title today_issue
				FROM	rawdata_siri_report A
				WHERE	page_date = (select max(date) from calendar where date <= '$search_date')
				AND  page_fg = 'E'
				AND  today_pick = 'Y'
				AND  stock =  '$code'" ;

		// echo "<pre>$query</pre>";
		$result3 = $mysqli->query($query3);

		while($row = $result3->fetch_array(MYSQLI_BOTH) ){
			$today_issue.= $row['today_theme']."<b>".$row['today_issue']."</b>";
		}

		// 다 없을 경우 최근뉴스 가져오기
		if($today_issue == '') {
			$query4 = "SELECT  date
							, title
							, link
						FROM signals B
						WHERE B.code =  '$code'
						AND B.date <= (select max(date) from calendar where date <= '$search_date')
						ORDER BY date DESC
						LIMIT 1 " ;

			// echo "<pre>$query</pre>";
			$result4 = $mysqli->query($query4);

			while( $row = $result4->fetch_array(MYSQLI_BOTH) ){
				$today_issue = '('.$row['date'].')'.$row['title'];
			}
		}

		echo "<td width=60%>$today_issue</td></tr><tr><td>";

		// X-RAY 순간체결 거래량 쿼리 실행
		$query2 = "SELECT cal.date, SUBSTR(STR_TO_DATE(cal.date, '%Y%m%d'),6) date_str, xray.close_rate, xray.amount, xray.cnt
					FROM calendar cal
					LEFT OUTER JOIN 
						(
							SELECT xr.code, xr.name, xr.date, max(dp.close_rate) close_rate, round(sum(xr.volume*xr.current_price)/100000000,1) amount,  count(*) cnt, min(xr.time), max(xr.time) 
							FROM kiwoom_xray_tick_executions xr
							LEFT OUTER JOIN daily_price dp
							ON dp.date = xr.date
							AND dp.code = xr.code
							WHERE xr.code = '$code'
							GROUP BY xr.date
						) xray
					ON xray.date = cal.date
					WHERE cal.date >= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL -20 DAY), '%Y%m%d')))
					AND cal.date <= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL 0 DAY), '%Y%m%d')))
					ORDER BY cal.date desc";
		// echo "<pre>$query2</pre>";
		$result2 = $mysqli->query($query2);

		// 종목 X-RAY 체결량 표시 - 변수 초기화
		$xray_date = "";
		$xray_close_rate = "";
		$xray_amount = "";
		$xray_cnt = "";
		
		while($row = $result2->fetch_array(MYSQLI_BOTH)) {
			$xray_date .= "<th align=center style='width:80px; height: 30px;'><a href=\"javascript:openPopupXrayTick('{$code}', '".$row['date']."')\">". $row['date_str']."</a></th>";
			
			if($row['cnt'] > 0) {
				// 거래대금에 따라 스타일 적용
				if($row['amount'] > 500)
					$amt_style = "mark text-danger font-weight-bold h6";
				else if($row['amount'] > 100)
					$amt_style = "text-danger font-weight-bold h6";
				else
					$amt_style = "font-weight-bold";

				$xray_close_rate.= "<td align=center style='width:80px; height: 30px;'>". $row['close_rate']."%</td>";
				$xray_cnt       .= "<td align=center style='width:80px; height: 30px;'>". number_format($row['cnt'])."</td>";
				$xray_amount    .= "<td align=center style='width:80px; height: 30px;' class='"."$amt_style"."'>". number_format($row['amount'])."억</td>";
			} else {
				$xray_close_rate.= "<td align=center>-</td>";
				$xray_cnt       .= "<td align=center>-</td>";
				$xray_amount    .= "<td align=center>-</td>";
			}
		}

		// X-RAY 체결량 내역 표시
		echo "<table class='table table-sm table-bordered small text-dark' style='table-layout: fixed;' >";
		echo "<tr align=center style='background-color:#fdf9f5;'>".$xray_date."</tr>";
		echo "<tr align=center>".$xray_close_rate."</tr>";
		echo "<tr align=center>".$xray_cnt."</tr>";
		echo "<tr align=center>".$xray_amount."</tr>";
		echo "</table>";
		$result2->free();
		
		echo "</td></tr>";
	}
	
	echo "</table>";
}
?>
	




</form>
</body>
</html>