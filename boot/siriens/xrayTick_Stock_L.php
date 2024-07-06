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
$code = (isset($_GET['code'])) ? $_GET['code'] : '';
$name = (isset($_GET['name'])) ? $_GET['name'] : '';
?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.highcharts.com/stock/highstock.js"></script>
    <script src="https://code.highcharts.com/modules/annotations.js"></script>
</head>

<body>
<form name="form1" method='POST' action='siriensEvening_script.php' onsubmit="return false">

<?php
if($code == '') {
	echo "<h3>종목명 입력 후 엔터</h3>";
	$ready = 'N';
}
else {
?>

	<div id="container" style="height: 600px; min-width: 310px;"></div>
    
    <script>
    $(document).ready(function() {
        var code = '<?= $code ?>';
        var name = '<?= $name ?>';

    // 기존 데이터 로드
    $.getJSON('/boot/common/ajax/ajaxHighcharts.php', { code: code, name: name }, function(response) {
        if (response.message) {
            alert(response.message);
            return;
        }
        
        var data = response.data;
        var zones = response.zones;
            var ohlc = [],
                volume = [],
                xAxisPlotLines = [], // xAxis plotLines array
                yAxisPlotLines = [],
                annotations = [],
                dataLength = data.length,
                ma3 = [],
                ma5 = [],
                ma8 = [],
                ma20 = [],
                ma60 = [],
                ma120 = [],
                ma224 = [],
                navigatorPlotLines = [], // Navigator plotLines array
                i = 0;

            for (i; i < dataLength; i += 1) {
                var date = data[i][0];
                var open = data[i][1];
                var high = data[i][2];
                var low = data[i][3];
                var close = data[i][4];
                var close_rate = data[i][5];
                var volume_val = data[i][6];
                var amount = data[i][7];
                var xray_amount = data[i][8];

                var isUp = close > open;

                ohlc.push({
                    x: date,
                    open: open,
                    high: high,
                    low: low,
                    close: close,
                    amount: amount,
                    xray_amount: xray_amount
                });

                volume.push({
                    x: date,
                    y: volume_val,
                    color: isUp ? 'red' : 'blue'
                });

                // 이동평균선 계산
                if (i >= 2) ma3.push({ date: date, value: (data[i][4] + data[i-1][4] + data[i-2][4]) / 3 });
                if (i >= 4) ma5.push({ date: date, value: (data[i][4] + data[i-1][4] + data[i-2][4] + data[i-3][4] + data[i-4][4]) / 5 });
                if (i >= 7) ma8.push({ date: date, value: (data[i][4] + data[i-1][4] + data[i-2][4] + data[i-3][4] + data[i-4][4] + data[i-5][4] + data[i-6][4] + data[i-7][4]) / 8 });
                if (i >= 19) ma20.push({ date: date, value: (function() {
                    var sum = 0;
                    for (var j = 0; j < 20; j++) sum += data[i-j][4];
                    return sum / 20;
                })() });
                if (i >= 59) ma60.push({ date: date, value: (function() {
                    var sum = 0;
                    for (var j = 0; j < 60; j++) sum += data[i-j][4];
                    return sum / 60;
                })() });
                if (i >= 119) ma120.push({ date: date, value: (function() {
                    var sum = 0;
                    for (var j = 0; j < 120; j++) sum += data[i-j][4];
                    return sum / 120;
                })() });
                if (i >= 223) ma224.push({ date: date, value: (function() {
                    var sum = 0;
                    for (var j = 0; j < 224; j++) sum += data[i-j][4];
                    return sum / 224;
                })() });

                if (amount > 100000000000 && close_rate > 15) {
                    xAxisPlotLines.push({
                        color: 'rgb(204, 255, 204)',
                        width: 6,
                        value: date,
                        dashStyle: 'solid',
                        zIndex: 3 // 캔들보다 낮은 zIndex 설정
                    });
                    navigatorPlotLines.push({
                        color: 'rgb(204, 255, 204)',
                        width: 2,
                        value: date,
                        dashStyle: 'solid'
                    });
                }
				
				// xray-tick 대금에 따른 표시
				// 1천억 이상
				if (xray_amount > 100000000000) {
                    annotations.push({
                        labels: [{
                            point: {
                                xAxis: 0,
                                yAxis: 0,
                                x: date,
                                y: high
                            },
                            text: '★',
                            style: {
                                color: 'red',
                                fontSize: '12px',
                                fontWeight: 'bold'
                            },
							backgroundColor: 'transparent',
							borderColor: 'transparent'
                        }]
                    });
                }
				// 5백억 이상
				else if (xray_amount > 50000000000) {
                    annotations.push({
                        labels: [{
                            point: {
                                xAxis: 0,
                                yAxis: 0,
                                x: date,
                                y: high
                            },
                            text: '◆',
                            style: {
                                color: 'red',
                                fontSize: '12px',
                                fontWeight: 'bold'
                            },
							backgroundColor: 'transparent',
							borderColor: 'transparent'
                        }]
                    });
                }
				// 1백억 이상
				else if (xray_amount > 10000000000) {
                    annotations.push({
                        labels: [{
                            point: {
                                xAxis: 0,
                                yAxis: 0,
                                x: date,
                                y: high
                            },
                            text: '▲',
                            style: {
                                color: 'red',
                                fontSize: '12px',
                                fontWeight: 'bold'
                            },
							backgroundColor: 'transparent',
							borderColor: 'transparent'
                        }]
                    });
                }
                // 30억 이상
				else if (xray_amount > 3000000000) {
                    annotations.push({
                        labels: [{
                            point: {
                                xAxis: 0,
                                yAxis: 0,
                                x: date,
                                y: high
                            },
                            text: '△',
                            style: {
                                color: 'black',
                                fontSize: '12px',
                                fontWeight: 'bold'
                            },
							backgroundColor: 'transparent',
							borderColor: 'transparent'
                        }]
                    });
                }
				// 10억 이상
				else if (xray_amount > 1000000000) {
                    annotations.push({
                        labels: [{
                            point: {
                                xAxis: 0,
                                yAxis: 0,
                                x: date,
                                y: high
                            },
                            text: '↑',
                            style: {
                                color: 'black',
                                fontSize: '12px',
                                fontWeight: 'bold'
                            },
							backgroundColor: 'transparent',
							borderColor: 'transparent'
                        }]
                    });
                }

            }

            // stock_price_zone 데이터를 yAxisPlotLines에 추가
            for (var j = 0; j < zones.length; j++) {
                yAxisPlotLines.push({
                    color: 'red',
                    width: 2,
                    value: zones[j].start_price,
                    dashStyle: 'dash',
                    label: {
                        text: zones[j].zone_type + ': ' + zones[j].start_price,
                        align: 'left', // 레이블을 왼쪽으로 정렬
                        x: -10, // 레이블 위치를 왼쪽으로 조정
                        style: {
                            color: 'red',
                            fontWeight: 'bold'
                        }
                    }
                });
            }
            // Highcharts 차트를 생성합니다.
            Highcharts.stockChart('container', {
                rangeSelector: {
                    selected: 2, // 6개월 기본 조회로 설정
                    buttons: [{
                        type: 'month',
                        count: 1,
                        text: '1m'
                    }, {
                        type: 'month',
                        count: 3,
                        text: '3m'
                    }, {
                        type: 'month',
                        count: 6,
                        text: '6m'
                    }, {
                        type: 'ytd',
                        text: 'YTD'
                    }, {
                        type: 'year',
                        count: 1,
                        text: '1y'
                    }, {
                        type: 'all',
                        text: 'All'
                    }]
                },
                chart: {
                    marginRight: 80 // 오른쪽 여백 추가
                },
                xAxis: {
                    plotLines: xAxisPlotLines, // xAxis에 세로선을 추가
                    labels: {
                        formatter: function() {
                            return Highcharts.dateFormat('%m/%d', this.value);
                        },
                        x: 10 // 오른쪽 여백 추가
                    }
                },
                yAxis: [{
                    labels: {
                        align: 'right',
                        x: 50, // 왼쪽 여백 추가
                        formatter: function() {
                            return Highcharts.numberFormat(this.value, 0, '.', ','); // 모든 값을 포맷팅하여 표시
                        }
                    },
                    height: '85%', // OHLC 차트의 높이
                    lineWidth: 2,
                    resize: {
                        enabled: true
                    },
                    plotLines: yAxisPlotLines // yAxis에 가로선을 추가
                }, {
                    labels: {
                        align: 'right',
                        x: -10 // 왼쪽 여백 추가
                    },
                    title: {
                        text: 'Volume'
                    },
                    top: '87%', // Volume 차트의 시작 위치
                    height: '13%', // Volume 차트의 높이
                    offset: 0,
                    lineWidth: 2
                }],
                subtitle: {
                    text: '['+ code + ']' + name + ' - ' + '★: 1천억 이상, ◆: 5백억 이상, ▲: 1백억 이상, △: 30억 이상, ↑: 10억 이상',
                    align: 'center',
                    verticalAlign: 'bottom',
                    style: {
                        color: '#707070',
                        fontSize: '12px'
                    }
                },
                tooltip: {
                    split: false,
                    shared: true,
                    useHTML: true,
                    formatter: function () {
                        var index = this.points[0].point.index;
                        var amount = this.points[0].point.amount;
                        var xray_amount = this.points[0].point.xray_amount;
                        var tooltipHtml = '<div style="text-align: left;">';
                        tooltipHtml += '<b>' + name + '</b><br>';
                        tooltipHtml += '<b>' + Highcharts.dateFormat('%Y-%m-%d', this.x) + '</b><br>';
                        tooltipHtml += '시가: ' + Highcharts.numberFormat(this.points[0].point.open, 0, '.', ',') + '<br>';
                        tooltipHtml += '고가: ' + Highcharts.numberFormat(this.points[0].point.high, 0, '.', ',') + '<br>';
                        tooltipHtml += '저가: ' + Highcharts.numberFormat(this.points[0].point.low, 0, '.', ',') + '<br>';
                        tooltipHtml += '종가: ' + Highcharts.numberFormat(this.points[0].point.close, 0, '.', ',') + '<br>';
                        tooltipHtml += '<br>';
                        tooltipHtml += '거래량: ' + Highcharts.numberFormat(this.points[1].y / 1000, 0, '.', ',') + 'K<br>';
                        tooltipHtml += '거래대금: ' + Highcharts.numberFormat(data[index][7] / 100000000, 1, '.', ',') + '억<br>';
                        tooltipHtml += 'Xray 거래대금: ' + Highcharts.numberFormat(data[index][8] / 100000000, 1, '.', ',') + '억<br>';
                        tooltipHtml += '<br>';
                        tooltipHtml += '3이평: ' + (index >= 2 ? Highcharts.numberFormat(ma3.find(m => m.date === this.x).value, 0, '.', ',') : 'N/A') + '<br>';
                        tooltipHtml += '5이평: ' + (index >= 4 ? Highcharts.numberFormat(ma5.find(m => m.date === this.x).value, 0, '.', ',') : 'N/A') + '<br>';
                        tooltipHtml += '20이평: ' + (index >= 19 ? Highcharts.numberFormat(ma20.find(m => m.date === this.x).value, 0, '.', ',') : 'N/A') + '<br>';
                        tooltipHtml += '60이평: ' + (index >= 59 ? Highcharts.numberFormat(ma60.find(m => m.date === this.x).value, 0, '.', ',') : 'N/A') + '<br>';
                        tooltipHtml += '120이평: ' + (index >= 119 ? Highcharts.numberFormat(ma120.find(m => m.date === this.x).value, 0, '.', ',') : 'N/A') + '<br>';
                        tooltipHtml += '224이평: ' + (index >= 223 ? Highcharts.numberFormat(ma224.find(m => m.date === this.x).value, 0, '.', ',') : 'N/A') + '<br>';
                        tooltipHtml += '</div>';
                        return tooltipHtml;
                    },
                    positioner: function (labelWidth, labelHeight, point) {
                        var chart = this.chart;
                        return {
                            x: 10,
                            y: 50
                        };
                    }
                },
                annotations: annotations,
                series: [{
                    type: 'candlestick',
                    name: code,
                    data: ohlc,
                    color: 'blue',  // 음봉 색상
                    upColor: 'red', // 양봉 색상
                    zIndex: 5, // 캔들의 zIndex를 높게 설정
                    lineWidth: 1,
                    lineColor: 'blue', // 캔들 외곽선 색상
                    upLineColor: 'red', // 양봉 외곽선 색상
                    borderColor: 'blue', // 캔들 외곽선 색상
                    upBorderColor: 'red', // 양봉 외곽선 색상
                    dataGrouping: {
                        units: [[
                            'week', // unit name
                            [1] // allowed multiples
                        ], [
                            'month',
                            [1, 2, 3, 4, 6]
                        ]]
                    },
                    events: {
                        mouseOver: function (event) {
                            var point = event.target.series.chart.hoverPoint;
                            if (point) {
                                var chart = this.chart;
                                chart.update({
                                    yAxis: [{
                                        plotLines: [{
                                            value: point.close,
                                            color: 'red',
                                            width: 2,
                                            id: 'plot-line-close',
                                            label: {
                                                text: 'Close: ' + point.close,
                                                align: 'right',
                                                style: {
                                                    color: 'red',
                                                    fontWeight: 'bold'
                                                }
                                            }
                                        }]
                                    }]
                                });
                            }
                        },
                        mouseOut: function () {
                            var chart = this.chart;
                            chart.update({
                                yAxis: [{
                                    plotLines: [{
                                        id: 'plot-line-close',
                                        value: null,
                                        width: 0
                                    }]
                                }]
                            });
                        }
                    }
                }, {
                    type: 'column',
                    name: 'Volume',
                    data: volume,
                    yAxis: 1,
                    zIndex: 0 // Volume 차트의 zIndex를 낮게 설정
                }, {
                    type: 'line',
                    name: 'MA 3',
                    data: ma3.map(m => [m.date, m.value]),
                    color: 'rgb(219, 27, 180)',
                    dashStyle: 'shortdash',
                    zIndex: 1,
                    lineWidth: 1 // 이동평균선의 두께를 1로 설정
                }, {
                    type: 'line',
                    name: 'MA 5',
                    data: ma5.map(m => [m.date, m.value]),
                    color: 'rgb(219, 27, 180)',
                    dashStyle: 'solid',
                    zIndex: 1,
                    lineWidth: 2 // 이동평균선의 두께를 1로 설정
                }, {
                    type: 'line',
                    name: 'MA 8',
                    data: ma8.map(m => [m.date, m.value]),
                    color: 'green',
                    dashStyle: 'longdash',
                    zIndex: 1,
                    lineWidth: 1 // 이동평균선의 두께를 1로 설정
                }, {
                    type: 'line',
                    name: 'MA 20',
                    data: ma20.map(m => [m.date, m.value]),
                    color: 'rgb(239, 174, 0)',
                    dashStyle: 'solid',
                    zIndex: 1,
                    lineWidth: 2 // 이동평균선의 두께를 1로 설정
                }, {
                    type: 'line',
                    name: 'MA 60',
                    data: ma60.map(m => [m.date, m.value]),
                    color: 'rgb(10, 41, 174)',
                    dashStyle: 'solid',
                    zIndex: 1,
                    lineWidth: 1 // 이동평균선의 두께를 1로 설정
                }, {
                    type: 'line',
                    name: 'MA 120',
                    data: ma120.map(m => [m.date, m.value]),
                    color: 'rgb(77, 77, 77)',
                    dashStyle: 'shortdot',
                    zIndex: 1,
                    lineWidth: 1 // 이동평균선의 두께를 1로 설정
                }, {
                    type: 'line',
                    name: 'MA 224',
                    data: ma224.map(m => [m.date, m.value]),
                    color: 'rgb(119, 119, 119)',
                    dashStyle: 'solid',
                    zIndex: 1,
                    lineWidth: 2 // 이동평균선의 두께를 1로 설정
                }],
                navigator: {
                    xAxis: {
                        plotLines: navigatorPlotLines // Navigator에 세로선을 추가
                    }
                }
            });
        });
    });
    </script>

<?php

		//X-RAY 순간체결 거래량
		$query = "	SELECT cal.date, SUBSTR(STR_TO_DATE(cal.date, '%Y%m%d'),6) date_str, xray.close_rate, xray.close_amt, xray.tot_trade_amt, xray.amount, xray.cnt
					FROM calendar cal
					LEFT OUTER JOIN 
						(
							SELECT xr.code, xr.name, xr.date, dp.close_rate close_rate, dp.close close_amt, round(dp.amount/100000000,0) tot_trade_amt, round(xr.tot_amt/100000000,1) amount, xr.tot_cnt cnt
							FROM kiwoom_xray_tick_summary xr
							LEFT OUTER JOIN daily_price dp
							ON dp.date = xr.date
							AND dp.code = xr.code
							WHERE xr.code = '$code'
						) xray
					ON xray.date = cal.date
					WHERE cal.date >= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL -5 MONTH), '%Y%m%d')))
					AND cal.date <= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL 0 DAY), '%Y%m%d')))
					AND cal.date >= '20240226'
					ORDER BY cal.date desc
					";

		// 데이터베이스 쿼리 실행 및 결과 저장
		$result = $mysqli->query($query);

		// 주차별 데이터 배열 초기화
		$weekly_data = [];

		// 데이터 분류
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			$date = $row['date'];
			$day_of_week = date('D', strtotime($date));  // 요일 추출 (Mon, Tue, ...)
			$week_number = date('W', strtotime($date));  // 주 번호 추출

			if (!isset($weekly_data[$week_number])) {
				$weekly_data[$week_number] = [
					'Mon' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
					'Tue' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
					'Wed' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
					'Thu' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
					'Fri' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
				];
			}
			// 요일별 데이터 저장
			if (isset($weekly_data[$week_number][$day_of_week])) {
				$weekly_data[$week_number][$day_of_week]['date'] = "<td align=center width=90  style='background-color:#fae4f1;'><b><a href=\"javascript:callFrameR('".$date."')\">". $row['date_str']."</a></b></td>";
				if($row['cnt'] > 0) {
					// 등락률 따라 스타일 적용
					if($row['close_rate'] > 29.5)
						$rate_style = "class='text-danger font-weight-bold'";
					else if($row['close_rate'] > 15)
						$rate_style = "class='text-danger'";
					else
						$rate_style = "";

					// 총 거래대금에 따라 스타일 적용
					if($row['tot_trade_amt'] >= 1000)
						$tot_amt_style = "style='color:#fcb9b2;' class='font-weight-bold'";
					else if($row['tot_trade_amt'] >= 500)
						$tot_amt_style = "style='color:#ffccd5;' class='font-weight-bold'";
					else if($row['tot_trade_amt'] < 9)
						$tot_amt_style = "class='small'";
					else
						$tot_amt_style = "";

					// xray 거래대금에 따라 스타일 적용
					if($row['amount'] >= 500)
						$amt_style = "mark text-danger font-weight-bold h6";
					else if($row['amount'] >= 100)
						$amt_style = "text-danger font-weight-bold h6";
					else if($row['amount'] < 9)
						$amt_style = "small";
					else
						$amt_style = "font-weight-bold";

					$weekly_data[$week_number][$day_of_week]['close_rate'] = "<td align=center $rate_style>". $row['close_rate']."%</td>";
					$weekly_data[$week_number][$day_of_week]['tot_trade_amt'] = "<td align=center $tot_amt_style>". number_format($row['tot_trade_amt'])."</td>";
					$weekly_data[$week_number][$day_of_week]['close_amt'] = "<td align=center>". number_format($row['close_amt'])."</td>";
					$weekly_data[$week_number][$day_of_week]['cnt'] = "<td align=right>". number_format($row['cnt'])."</td>";
					$weekly_data[$week_number][$day_of_week]['amount'] = "<td align=right class='{$amt_style}'>". number_format($row['amount'])."</td>";
				} else {
					$weekly_data[$week_number][$day_of_week]['close_rate'] = "<td align=center>&nbsp;</td>";
					$weekly_data[$week_number][$day_of_week]['tot_trade_amt'] = "<td align=center>&nbsp;</td>";
					$weekly_data[$week_number][$day_of_week]['close_amt'] = "<td align=center>&nbsp;</td>";
					$weekly_data[$week_number][$day_of_week]['cnt'] = "<td align=center>&nbsp;</td>";
					$weekly_data[$week_number][$day_of_week]['amount'] = "<td align=center>&nbsp;</td>";
				}
			}
		}

		// 주차별 데이터 내림차순 정렬 (가장 최근 주가 위로)
		krsort($weekly_data);

		// 주 데이터를 세 개씩 묶어서 처리
		$paired_weeks = array_chunk($weekly_data, 3, true);

		// HTML 출력
		echo "<table class='table table-sm table-bordered text-dark'>";
		foreach ($paired_weeks as $pair) {
			echo "<tr>";  // 날짜 헤더 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['date'] ?? "<th></th>";
				}
			}
			echo "</tr><tr>";  // 종가 비율 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['close_rate'] ?? "<td></td>";
				}
			}
			echo "</tr><tr>";  // 총 거래대금 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['tot_trade_amt'] ?? "<td></td>";
				}
			}
			echo "</tr><tr>";  // 종가
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['close_amt'] ?? "<td></td>";
				}
			}
			// echo "</tr><tr>";  // 거래량 행
			// foreach ($pair as $week => $data) {
			// 	foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
			// 		echo $data[$day]['cnt'] ?? "<td></td>";
			// 	}
			// }
			echo "</tr><tr>";  // 거래대금 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['amount'] ?? "<td></td>";
				}
			}
			echo "</tr>";
		}
		echo "</table>";
}
?>
</form>
<script>
function callFrameR(date) {
	window.parent.viewDetail(date);
}
</script>
</body>
</html>