<?php
	// 화면에서 일자 등 정보 클릭 시 해당 일자 그래프를 바로 보는 여러가지 버전을 시도했으나 속도 이슈로 중단. 현재는 팝업이 최선. // 24.06.28
	
	// 종목정보
	$code = isset($_GET['code']) ? $_GET['code'] : '';
	$name = isset($_GET['name']) ? $_GET['name'] : ''; 

	$pageTitle = "실시간 1Month-$name";
	
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>

<?php
$pgmId = (isset($_GET['pgmId'])) ? $_GET['pgmId'] : '';

$search_date   = (isset($_GET['search_date'])  ) ? $_GET['search_date']   : date('Ymd');
$increase_rate = (isset($_GET['increase_rate'])) ? $_GET['increase_rate'] : 10;
$trade_amt     = (isset($_GET['trade_amt'])    ) ? $_GET['trade_amt'] : 0;

$sector   = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme 	  = (isset($_GET['theme']))  ? $_GET['theme']  : '';

$buy_cnt 	= (isset($_GET['buy_cnt']))     ? $_GET['buy_cnt']    : '';
$buy_period = (isset($_GET['buy_period']))  ? $_GET['buy_period'] : '';
$zeroday_view = (isset($_GET['zeroday_view']))  ? $_GET['zeroday_view'] : '';

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
		, CONCAT('*',  DATE_FORMAT(crawl_time, '%H:%i')) trade_date
		, Z.current_price trade_price
		, CASE WHEN Z.change_rate >= 0 THEN CONCAT('<font color=red> +',Z.change_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.change_rate),'% </font>') END trade_rate_str
		, FLOOR((Z.volume*Z.current_price)/100000000) acc_trade_amount
		, round(Z.market_cap/1000,2) AS market_cap";
	$trade_table = "naver_finance_stock";
} else {
	// 당일 시세 정보 가져오기
	$trade_qry = "
	, DATE_FORMAT(Z.date, '%m/%d') trade_date
	, Z.close trade_price
	, CASE WHEN Z.close_rate >= 0 THEN CONCAT('<font color=red> +',Z.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.close_rate),'% </font>') END trade_rate_str
	, FLOOR(Z.amount/100000000) acc_trade_amount
	, (SELECT round(market_cap/1000,2) FROM naver_finance_stock NF WHERE NF.date = Z.date AND NF.code = Z.code) AS market_cap";
	$trade_table = "daily_price";
}
?>

<style>
    .flex-view {
        display: flex;
        height: 100vh;
        width: 100vw;
    }
    .left {
        flex: 26;
        overflow-y: auto;
        border-right: 1px solid #ccc;
        height: 100%;
    }
    .middle {
        flex: 58;
        overflow-y: auto;
        border-right: 1px solid #ccc;
        height: 100%;
    }
    .right {
        flex: 17;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .iframe-container {
        flex: 1;
        width: 100%;
        height: 100%;
    }
    iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
</style>

<body>
<?php
if($pgmId == '') {
	echo "<h3>원하는 검색 버튼 선택</h3>";
	$ready = 'N';
} else {
	if($pgmId == '0dayStocks') { //0dayStocks.php
		$filename = $pgmId."_".$increase_rate;
		$file_orderby = "ORDER BY V.watchlist_date DESC , V.tot_trade_amt DESC";

		// 0일차 관종 목록 구해오기
		if($increase_rate == '29.5') $sub_where = "AND B.close_rate >= $increase_rate";
		else $sub_where = "AND ( B.close_rate >= $increase_rate OR B.tot_trade_amt > $trade_amt)";

		$query = "	SELECT STR_TO_DATE(A.date, '%Y%m%d') group_key
						, CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(B.watchlist_date, '%Y%m%d'),'</font>') group_key_str
						, A.date 0day_date
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
					FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) A
					INNER JOIN daily_watchlist B
					ON A.date = B.watchlist_date
					$sub_where
					INNER JOIN (	SELECT G.sector, G.theme, SUM(G.tot_trade_amt) AS group_sum, MAX(G.watchlist_date) AS group_max_date
									FROM daily_watchlist G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) S
									ON S.date = G.watchlist_date
									GROUP BY G.sector, G.theme
								) R
					ON B.sector = R.sector
					AND B.theme  = R.theme
					INNER JOIN (	SELECT G.sector, SUM(G.tot_trade_amt) AS sector_sum, MAX(G.watchlist_date) AS sector_max_date
									FROM daily_watchlist G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) S
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
	} else if($pgmId == 'sophiaWatchlist') { // sophiaWatchlist.php
		$filename = $pgmId."_".$sector."_".$theme;
		$file_orderby = "ORDER BY V.sort_theme, V.sort_stock";

		$query = "SELECT RTRIM(CONCAT(A.sector, A.theme, A.category)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme, '  ',A.category)) AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						, CASE WHEN talent_fg != '' THEN CONCAT('<font color=violet><b>',talent_fg,'</b></font>') ELSE '' END talent_fg
						, A.sector, A.sort_theme, A.sort_stock, A.news_title, A.news_link
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM watchlist_sophia A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.sector = '$sector'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					ORDER BY A.sector, A.sort_theme, A.sort_stock";
	} else if($pgmId == 'astarWatchlist') { // astarWatchlist.php
		$filename = $pgmId."_".$sector."_".$theme;
		$file_orderby = "ORDER BY V.sort_theme, V.stock_idx";

		$query = "SELECT RTRIM(CONCAT(A.sector, A.theme)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme)) AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						, '' talent_fg
						, A.sector, A.sort_theme, A.stock_idx
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM watchlist_astar A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.sector = '$sector'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					ORDER BY A.sector, A.sort_theme, A.stock_idx";
	} else if($pgmId == 'mochaten') { // 모차십
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.cha_fg, V.tot_trade_amt DESC";

		$query = "	SELECT CONCAT(Q.cha_fg_nm,' - ',cha_comment) group_key
						 , CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(Q.watchlist_date, '%Y%m%d'),'</font>') group_key_str
						 , Q.watchlist_date 0day_date
						 , Q.code
						 , Q.name
						 , '' talent_fg
						 , CASE WHEN Q.close_rate >= 0 THEN CONCAT('<font color=red> ▲',Q.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',Q.close_rate,'% </font>') END close_rate_str
						 , CASE WHEN Q.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(Q.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(Q.tot_trade_amt,'억') END tot_trade_amt_str
						 , Q.tot_trade_amt
						 , Q.uprsn
						 , Q.stock_keyword
						 , Q.watchlist_date
						 $trade_qry
						 , M.mochaten_cnt
					FROM (	SELECT A.cha_fg
								, C.nm cha_fg_nm
								, C.nm_sub1 cha_comment
								, A.code
								, A.name
								, CASE WHEN A.cha_fg = 'MC000' THEN A.close_rate ELSE B.close_rate end close_rate
								, CASE WHEN A.cha_fg = 'MC000' THEN A.tot_trade_amt ELSE B.tot_trade_amt end tot_trade_amt
								, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
								, B.stock_keyword
								, B.watchlist_date
							FROM mochaten A
							LEFT OUTER JOIN (SELECT * FROM daily_watchlist 
											WHERE (watchlist_date, code)
											IN 	(SELECT MAX(watchlist_date), code FROM daily_watchlist WHERE watchlist_date <= $search_date GROUP BY code)) B
							ON A.code = B.code
							INNER JOIN comm_cd C
							ON C.cd = A.cha_fg
							WHERE A.trade_date = '$search_date'
						) Q
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = Q.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = Q.code
					ORDER BY cha_fg, tot_trade_amt DESC";
	} else if($pgmId == 'xraytick') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.amount DESC";

		$query = "	SELECT A.date AS group_key
						, CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(A.date, '%Y%m%d'),'</font>') AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						, A.amount
						, '' talent_fg
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT
						  	date, code, name, round(tot_amt/100000000,1) amount,  tot_cnt cnt
						  FROM
						  	kiwoom_xray_tick_summary
						  WHERE
						  	date BETWEEN DATE_FORMAT(DATE_SUB('{$search_date}', INTERVAL 20 DAY), '%Y%m%d') AND DATE_FORMAT('{$search_date}', '%Y%m%d')
						 ) A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.date = '$search_date'
					ORDER BY A.amount DESC";
	} else if($pgmId == 'buy_streak') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.group_key DESC, V.tot_trade_amt DESC ";

		if($zeroday_view == 'true')
			$sub_where = "";
		else 
			$sub_where = "WHERE (B.watchlist_date is Null OR STR_TO_DATE(B.watchlist_date, '%Y%m%d') < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

		$query = "	SELECT occurrence_days  AS group_key
						, CONCAT(' &nbsp; ', occurrence_days, '회 이상') AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						 , '' talent_fg
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM (
							SELECT 
								ks.code, 
								ks.name,
								COUNT(DISTINCT ks.date) AS occurrence_days
							FROM
								kiwoom_xray_tick_summary ks
							JOIN (
								SELECT date
								FROM calendar
								WHERE date <= '{$search_date}'
								ORDER BY date DESC
								LIMIT $buy_period
							) rd ON ks.date = rd.date
							WHERE ks.tot_amt >= 300000000
							GROUP BY
								ks.code, ks.name
							HAVING
								occurrence_days >= $buy_cnt
						) A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					$sub_where
					ORDER BY A.occurrence_days DESC, B.tot_trade_amt DESC";
	}

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
?>
	
<div class="flex-view">
    <div class="left">
			<table class='table table-sm table-bordered text-dark'>
				<?php
		$d=0;
		$pre_group = '';
		foreach ($watchlist_data as $row) {
			// 그룹별 분리표시
			if($pre_group != $row['group_key']) {
				if($pre_group != '') {
					echo "</tr>";
				}
				echo "<tr><td class='table-danger'><b>▷ ".$row['group_key_str']."</b></td></tr>";
			}

			// 가장 최근 0일차 상승이유
			echo "<tr><td>";
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

			//그래프를 잘 보기 위해 팝업으로 연결
			$xray_tick_detail = "<a href=\"javascript:viewHighchart('".$row['code']."','{$stock_name}')\">(+)</a>";

			// echo "<div class='col-xl-3 col-md-6 mb-4' style='margin: 0; margin-left:10px margin-right:10px'>
			echo "<div class='row no-gutters align-items-center'>
					<div class='col mr-0'>
						<div class='font-weight-bold text-primary text-uppercase mb-1' style='height:35px; line-height:35px;'>$mochaten_cnt
							<font class='h4'><span class='draggable' id=stock_nm$d draggable='true'><b><a href='../siriens/stock_B.php?code=".$row['code']."&name=".$stock_name."&brWidth=2500' onclick='window.open(this.href, \'stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>".$stock_name."</a></b></span>".$row['talent_fg']."</font> $xray_tick_detail &nbsp;".$realtime_data."
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

			echo "</td></tr><tr>";

			$code = $row['code'];  // 현재 행의 코드 사용
			$zeroday_date = $row['0day_date'];  // 현재 행의 0day일자 사용

			// 종목 이슈 데이터 구해오기 // 0일차 이슈 구해오기
			$today_issue = '';
			$query3 = "SELECT CONCAT('[',A.signal_grp
							, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
							, A.title today_issue
					FROM	rawdata_siri_report A
					WHERE	page_date = (select max(date) from calendar where date <= '$zeroday_date')
					AND  page_fg = 'E'
					AND  code =  '$code'" ;

			// 0일차가 아니어도 시그널이브닝에 이슈가 들어오는 경우가 있어, 수정해봄. 더 적합한 데이터로 변경 예정 24.06.29
			$query3 = "SELECT CONCAT(A.page_date
							, ' [',A.signal_grp
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

			echo "<td width=60%>$today_issue</td></tr><tr><td>";

			
			echo "</td></tr>";

			$d++;
		}
		?>
        </table>
    </div>
    <div class="middle">
        <div class="iframe-container">
            <iframe id="iframeL" src=""></iframe>
        </div>
    </div>
    <div class="right">
        <div class="iframe-container">
            <iframe id="iframeR" src=""></iframe>
        </div>
    </div>
</div>
<?php
}
?>

<script>
// 모차십 종목 선택 시 오른쪽 프레임에 내역 조회
function viewHighchart(code, name) {
	brWidth = window.innerWidth;
	iframeL.src = "xrayTick_Stock_L.php?code="+code+"&name="+name+"&brWidth="+brWidth;
	iframeR.src = "";
	return;
}

// 일자 선택 시 상세 내역 조회
function viewDetail(date, code) {
	brWidth = window.innerWidth;
	iframeR.src = "/boot/common/popup/stock_xray_tick.php?date="+date+"&code="+code+"&brWidth="+brWidth;
	return;
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