<!DOCTYPE html>
<html>
<head>
    <title>003160 Stock Chart</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.highcharts.com/stock/highstock.js"></script>
    <script src="https://code.highcharts.com/modules/annotations.js"></script>
</head>
<body>
    <div id="container" style="height: 800px; min-width: 310px"></div>
    
    <script>
    $(document).ready(function() {
        $.getJSON('chart_script.php', function(data) {
            var ohlc = [],
                volume = [],
                xAxisPlotLines = [], // xAxis plotLines array
                annotations = [],
                dataLength = data.length,
                ma3 = [],
                ma5 = [],
                ma20 = [],
                ma60 = [],
                ma120 = [],
                ma224 = [],
                i = 0;

            for (i; i < dataLength; i += 1) {
                var date = data[i][0];
                var open = data[i][1];
                var high = data[i][2];
                var low = data[i][3];
                var close = data[i][4];
                var amount = data[i][6];
                var xray_amount = data[i][7];

                ohlc.push([date, open, high, low, close]);
                volume.push([date, data[i][5]]);

                if (amount > 100000000000) {
                    xAxisPlotLines.push({
                        color: 'orange',
                        width: 2,
                        value: date,
                        dashStyle: 'solid',
                        zIndex: 5
                    });
                }

                if (xray_amount > 10000000000) {
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
                                color: 'yellow',
                                fontSize: '12px',
                                fontWeight: 'bold'
                            }
                        }]
                    });
                }

                // 이동평균선 계산
                if (i >= 2) ma3.push([date, (data[i][4] + data[i-1][4] + data[i-2][4]) / 3]);
                if (i >= 4) ma5.push([date, (data[i][4] + data[i-1][4] + data[i-2][4] + data[i-3][4] + data[i-4][4]) / 5]);
                if (i >= 19) ma20.push([date, (function() {
                    var sum = 0;
                    for (var j = 0; j < 20; j++) sum += data[i-j][4];
                    return sum / 20;
                })()]);
                if (i >= 59) ma60.push([date, (function() {
                    var sum = 0;
                    for (var j = 0; j < 60; j++) sum += data[i-j][4];
                    return sum / 60;
                })()]);
                if (i >= 119) ma120.push([date, (function() {
                    var sum = 0;
                    for (var j = 0; j < 120; j++) sum += data[i-j][4];
                    return sum / 120;
                })()]);
                if (i >= 223) ma224.push([date, (function() {
                    var sum = 0;
                    for (var j = 0; j < 224; j++) sum += data[i-j][4];
                    return sum / 224;
                })()]);
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
                title: {
                    text: '003160 Stock Price'
                },
                xAxis: {
                    plotLines: xAxisPlotLines // xAxis에 세로선을 추가
                },
                yAxis: [{
                    labels: {
                        align: 'right',
                        x: -3
                    },
                    title: {
                        text: 'OHLC'
                    },
                    height: '70%', // OHLC 차트의 높이를 70%로 설정
                    lineWidth: 2,
                    resize: {
                        enabled: true
                    }
                }, {
                    labels: {
                        align: 'right',
                        x: -3
                    },
                    title: {
                        text: 'Volume'
                    },
                    top: '75%', // Volume 차트의 시작 위치를 75%로 설정
                    height: '25%', // Volume 차트의 높이를 25%로 설정
                    offset: 0,
                    lineWidth: 2
                }],
                tooltip: {
                    split: false,
                    positioner: function () {
                        return { x: 80, y: 50 };
                    }
                },
                annotations: annotations,
                series: [{
                    type: 'candlestick',
                    name: '003160',
                    data: ohlc,
                    color: 'blue',  // 음봉 색상
                    upColor: 'red' // 양봉 색상
                }, {
                    type: 'column',
                    name: 'Volume',
                    data: volume,
                    yAxis: 1
                }, {
                    type: 'line',
                    name: 'MA 3',
                    data: ma3,
                    color: 'blue',
                    dashStyle: 'shortdash'
                }, {
                    type: 'line',
                    name: 'MA 5',
                    data: ma5,
                    color: 'green',
                    dashStyle: 'shortdot'
                }, {
                    type: 'line',
                    name: 'MA 20',
                    data: ma20,
                    color: 'purple',
                    dashStyle: 'longdash'
                }, {
                    type: 'line',
                    name: 'MA 60',
                    data: ma60,
                    color: 'orange',
                    dashStyle: 'solid'
                }, {
                    type: 'line',
                    name: 'MA 120',
                    data: ma120,
                    color: 'red',
                    dashStyle: 'solid'
                }, {
                    type: 'line',
                    name: 'MA 224',
                    data: ma224,
                    color: 'black',
                    dashStyle: 'solid'
                }]
            });
        });
    });
    </script>
</body>
</html>
