<?php

	// 종목정보
	$code = isset($_GET['code']) ? $_GET['code'] : '';
	$name = isset($_GET['name']) ? $_GET['name'] : ''; 

	$pageTitle = "실시간 1Month-$name";
	
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
<head>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function openPopupXrayTick(code, date) {
    var url = "/boot/common/popup/stock_xray_tick.php?code=" + code + "&date=" + date;
    var newWindow = window.open(url, "pop", "width=1200,height=1500,scrollbars=yes,resizable=yes");
    if (window.focus) {
        newWindow.focus();
    }
}

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

function toggleGraph(chartId, code, date, imgElement) {
    var originalSrc = imgElement.getAttribute('data-original-src');
    
    if (imgElement.tagName === 'IMG') {
        fetch('/boot/common/popup/stock_xray_tick_graph.php?code=' + code + '&date=' + date)
            .then(response => response.json())
            .then(data => {
                if (window.popupChart && typeof window.popupChart.destroy === 'function') {
                    window.popupChart.destroy();
                    window.popupChart = null;
                }

                var canvas = document.createElement('canvas');
                canvas.className = 'img-fluid chart-image';
                canvas.id = 'dynamicChart';
                canvas.width = imgElement.width;
                canvas.height = imgElement.height;

                imgElement.parentNode.replaceChild(canvas, imgElement);

                var ctx = canvas.getContext('2d');
                window.popupChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.times,
                        datasets: [{
                            label: '거래대금 (억)',
                            type: 'bar',
                            data: data.amounts,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            yAxisID: 'y1'
                        }, {
                            label: '누적 대금 (억)',
                            type: 'line',
                            data: data.cum_amts,
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            fill: false,
                            yAxisID: 'y2'
                        }, {
                            label: '등락률 (%)',
                            type: 'line',
                            data: data.change_rates,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                            fill: false,
                            yAxisID: 'y3'
                        }]
                    },
                    options: {
                        scales: {
                            x: {
                                type: 'category',
                                labels: data.times
                            },
                            y1: {
                                type: 'linear',
                                position: 'left',
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value + ' 억';
                                    }
                                }
                            },
                            y2: {
                                type: 'linear',
                                position: 'right',
                                beginAtZero: false,
                                ticks: {
                                    callback: function(value) {
                                        return value + ' 억';
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            },
                            y3: {
                                type: 'linear',
                                position: 'right',
                                beginAtZero: false,
                                ticks: {
                                    callback: function(value) {
                                        return value + ' %';
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching data:', error);
            });
    } else if (imgElement.tagName === 'CANVAS') {
        var img = document.createElement('img');
        img.className = 'img-fluid';
        img.id = chartId;
        img.setAttribute('data-original-src', originalSrc);
        img.src = originalSrc;
        img.onclick = function() {
            toggleGraph(chartId, code, date, img);
        };
        
        imgElement.parentNode.replaceChild(img, imgElement);

        if (window.popupChart && typeof window.popupChart.destroy === 'function') {
            window.popupChart.destroy();
            window.popupChart = null;
        }
    }
}

function showGraphPopup(event, code, date, chartId) {
    var apiUrl = '/boot/common/popup/stock_xray_tick_graph.php?code=' + code + '&date=' + date;
    var imgElement = document.getElementById(chartId);

    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (window.popupChart && typeof window.popupChart.destroy === 'function') {
                window.popupChart.destroy();
                window.popupChart = null;
            }

            var canvas = document.createElement('canvas');
            canvas.className = 'img-fluid chart-image';
            canvas.id = 'dynamicChart';
            canvas.width = imgElement.width;
            canvas.height = imgElement.height;

            imgElement.parentNode.replaceChild(canvas, imgElement);

            var ctx = canvas.getContext('2d');
            window.popupChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.times,
                    datasets: [{
                        label: '거래대금 (억)',
                        type: 'bar',
                        data: data.amounts,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }, {
                        label: '누적 대금 (억)',
                        type: 'line',
                        data: data.cum_amts,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        fill: false,
                        yAxisID: 'y2'
                    }, {
                        label: '등락률 (%)',
                        type: 'line',
                        data: data.change_rates,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        fill: false,
                        yAxisID: 'y3'
                    }]
                },
                options: {
                    scales: {
                        x: {
                            type: 'category',
                            labels: data.times
                        },
                        y1: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' 억';
                                }
                            }
                        },
                        y2: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: false,
                            ticks: {
                                callback: function(value) {
                                    return value + ' 억';
                                }
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        y3: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: false,
                            ticks: {
                                callback: function(value) {
                                    return value + ' %';
                                }
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error fetching data:', error);
        });
}

function hideGraphPopup(chartId, originalSrc) {
    var canvas = document.getElementById('dynamicChart');
    var imgElement = document.createElement('img');
    imgElement.src = originalSrc;
    imgElement.className = 'img-fluid';
    imgElement.id = chartId;

    if (canvas && canvas.parentNode) {
        canvas.parentNode.replaceChild(imgElement, canvas);
    }

    if (window.popupChart && typeof window.popupChart.destroy === 'function') {
        window.popupChart.destroy();
        window.popupChart = null;
    }
}
</script>
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
.chart-image, #popupChart {
	width: 100%;  /* 전체 너비 */
	height: 295px;  /* 고정된 높이, 필요에 따라 조정 가능 */
	object-fit: contain;  /* 이미지 크기를 맞추기 위한 옵션 */
}

</style>
</head>
<body>
<form name="form1" method='POST' action='xrayTick_script.php' onsubmit="return false">
<?php
$pgmId = (isset($_GET['pgmId'])) ? $_GET['pgmId'] : '';

$search_date   = (isset($_GET['search_date'])  ) ? $_GET['search_date']   : date('Ymd');
$increase_rate = (isset($_GET['increase_rate'])) ? $_GET['increase_rate'] : 10;
$trade_amt     = (isset($_GET['trade_amt'])    ) ? $_GET['trade_amt'] : 0;

$sector   = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme 	  = (isset($_GET['theme']))  ? $_GET['theme']  : '';

$buy_cnt 	= (isset($_GET['buy_cnt']))     ? $_GET['buy_cnt']    : '';
$buy_period = (isset($_GET['buy_period']))  ? $_GET['buy_period'] : '';

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
		, CASE WHEN Z.rate >= 0 THEN CONCAT('<font color=red> +',Z.rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.rate),'% </font>') END close_rate_str
		, FLOOR(Z.acc_trade_amount/100) acc_trade_amount";
	$trade_table = "kiwoom_realtime";
} else {
	// 당일 시세 정보 가져오기
	$trade_qry = "
	, DATE_FORMAT(Z.date, '%m/%d') trade_date
	, Z.close trade_price
	, CASE WHEN Z.close_rate >= 0 THEN CONCAT('<font color=red> +',Z.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.close_rate),'% </font>') END close_rate_str
	, FLOOR(Z.amount/100000000) acc_trade_amount";
	$trade_table = "daily_price";
}

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
	
	echo "<table class='table table-sm table-bordered text-dark'>";
	$d = 0;
	$pre_group = '';
	foreach ($watchlist_data as $row) {
		// 그룹별 분리표시
		if ($pre_group != $row['group_key']) {
			if ($pre_group != '') {
				echo "</tr>";
			}
			echo "<tr><td colspan=2 class='table-danger'><b>▷ " . $row['group_key_str'] . "</b></td></tr>";
		}
	
		// 가장 최근 0일차 상승이유
		echo "<tr><td rowspan=2>";
		if ($row['group_key_str'] != '') {
			$info_0day = " <b>(" . $row['uprsn'] . ")</b> " . $row['close_rate_str'] . " / " . $row['tot_trade_amt_str'] . "</font>" . " &nbsp; ";
		} else {
			$info_0day = "<font class='h5'>&nbsp</font>";
		}
	
		// 종목 거래내역 // 장중 - 실시간데이터, 이외 - 마감데이터, 예상체결 - 예상체결데이터
		$realtime_data = "";
		if ($row['trade_date'] != '') {
			$realtime_data = "<font class='h5'>" . number_format($row['acc_trade_amount']) . "억  &nbsp " . $row['close_rate_str'] . " </font> &nbsp";
			$realtime_data .= "<font class='text-dark'>" . number_format($row['trade_price']) . "&nbsp " . $row['trade_date'] . "</font> ";
		}
	
		// 모차십 0일차 등록건이 있는 경우 건수 표시되게 함.
		$mochaten_cnt = '';
		if ($row['mochaten_cnt'] > 0) {
			$mochaten_cnt = '<font color=red>(' . $row['mochaten_cnt'] . ')</font>';
		}
	
		$stock_name = $row['name'];
	
		// xray_tick 조회 화면 연결
		$xray_tick_detail = "<a href='../siriens/xrayTick_stock.php?stock=" . $row['code'] . "&stock_nm=" . $stock_name . "' onclick='window.open(this.href, \"stock\", '');return false;' target='_blank'>(+)</a>";
	
		echo "<div class='row no-gutters align-items-center'>
				<div class='col mr-0'>
					<div class='font-weight-bold text-primary text-uppercase mb-1' style='height:35px; line-height:35px;'>$mochaten_cnt
						<font class='h4'><span class='draggable' id=stock_nm$d draggable='true'><b><a href='../siriens/stock_B.php?code=" . $row['code'] . "&name=" . $stock_name . "&brWidth=2500' onclick='window.open(this.href, \"stock\", 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>" . $stock_name . "</a></b></span>" . $row['talent_fg'] . "</font> &nbsp; $xray_tick_detail &nbsp;" . $realtime_data . "
					</div>
					<div class='font-weight-bold mb-1 style='margin: 0;'>
						$info_0day
					</div>
					<div style='margin: 0;'>
						<img class='img-fluid chart-image' id='stockChart_$d' data-original-src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{$row['code']}.png?sidcode=1705826920773' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{$row['code']}.png?sidcode=1705826920773' onclick='toggleImage(\"stockChart_$d\", \"{$row['code']}\")'>
					</div>
				</div>
			</div>";
	
		$pre_group = $row['group_key'];
		echo "</td>";

		$code = $row['code'];  // 현재 행의 코드 사용
		$zeroday_date = $row['0day_date'];  // 현재 행의 0day일자 사용
	
		// 종목 이슈 데이터 구해오기
		$today_issue = '';
		$query3 = "SELECT CONCAT('[',A.signal_grp
						, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
						, A.title today_issue
				FROM	rawdata_siri_report A
				WHERE	page_date = (select max(date) from calendar where date <= '$zeroday_date')
				AND  page_fg = 'E'
				AND  code =  '$code'";
	
		$result3 = $mysqli->query($query3);
	
		while ($row = $result3->fetch_array(MYSQLI_BOTH)) {
			$today_issue = $row['today_theme'] . " <b>" . $row['today_issue'] . "</b>";
		}
	
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
					WHERE cal.date >= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD('$search_date', INTERVAL -27 DAY), '%Y%m%d')))
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
	
		while ($row = $result2->fetch_array(MYSQLI_BOTH)) {
			$xray_date .= "<th align=center style='width:80px; height: 30px;'><a href=\"javascript:void(0)\" onmouseover=\"showGraphPopup(event, '{$code}', '" . $row['date'] . "', 'stockChart_$d')\" onmouseout=\"hideGraphPopup('stockChart_$d', 'https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{$code}.png?sidcode=1705826920773')\" onclick=\"openPopupXrayTick('{$code}', '".$row['date']."')\">" . $row['date_str'] . "</a></th>";
	
			if ($row['cnt'] > 0) {
	
				if ($row['close_rate'] > 29.5)
					$rate_style = "class='text-danger font-weight-bold'";
				else if ($row['close_rate'] > 15)
					$rate_style = "class='text-danger'";
				else
					$rate_style = "";
	
				if ($row['tot_trade_amt'] > 1000)
					$tot_amt_style = "background-color:#ffccd5;";
				else if ($row['tot_trade_amt'] > 500)
					$tot_amt_style = "background-color:#fde2e4;";
				else
					$tot_amt_style = "";
	
				if ($row['amount'] > 500)
					$amt_style = "mark text-danger font-weight-bold h6";
				else if ($row['amount'] > 100)
					$amt_style = "text-danger font-weight-bold h6";
				else
					$amt_style = "font-weight-bold";
	
				$xray_close_rate .= "<td align=center style='width:80px; height: 30px;' {$rate_style}>" . $row['close_rate'] . "%</td>";
				$xray_tot_amount .= "<td align=center style='width:80px; height: 30px;{$tot_amt_style}'>" . number_format($row['tot_trade_amt']) . "억</td>";
				$xray_cnt .= "<td align=center style='width:80px; height: 30px;'>" . number_format($row['cnt']) . "건</td>";
				// $xray_amount .= "<td align=center style='width:80px; height: 30px;' class='" . "$amt_style" . "'>" . number_format($row['amount']) . "억</td>";
				$xray_amount .= "<td align=center style='width:80px; height: 30px;' class='" . "$amt_style" . "' onclick=\"toggleGraph('stockChart_$d', '{$code}', '{$row['date']}', 'https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{$code}.png?sidcode=1705826920773')\">" . number_format($row['amount']) . "억</td>";
			} else {
				$xray_close_rate .= "<td align=center>-</td>";
				$xray_tot_amount .= "<td align=center>-</td>";
				$xray_cnt .= "<td align=center>-</td>";
				$xray_amount .= "<td align=center>-</td>";
			}
		}
	
		echo "<table class='table table-sm table-bordered small text-dark' style='table-layout: fixed;' >";
		echo "<tr align=center style='background-color:#fdf9f5;'>" . $xray_date . "</tr>";
		echo "<tr align=center>" . $xray_close_rate . "</tr>";
		echo "<tr align=center>" . $xray_tot_amount . "</tr>";
		echo "<tr align=center><td colspan=100></td></tr>";
		echo "<tr align=center>" . $xray_cnt . "</tr>";
		echo "<tr align=center>" . $xray_amount . "</tr>";
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
}
?>
<input type="hidden" name='proc_fg'>
<input type="hidden" name='today' value='<?=$today?>'>
<input type="hidden" name='tot_cnt' value='<?=$d?>'>
</form>

<iframe name="saveFrame" src="xrayTick_script.php" style='border:0px;' width=0 height=0>
</iframe>

</body>
</html>
