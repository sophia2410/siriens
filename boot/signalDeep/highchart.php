<!DOCTYPE html>
<html>
<head>
    <title>Candlestick Chart for 003160</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.highcharts.com/stock/highstock.js"></script>
</head>
<body>
    <div id="container" style="height: 600px; min-width: 310px"></div>

    <script>
    $(document).ready(function() {
        $.getJSON('highchart_script.php', function(data) {
            // 캔들 차트 데이터를 준비합니다.
            var ohlc = [],
                volume = [],
                dataLength = data.length,
                i = 0;

            for (i; i < dataLength; i += 1) {
                ohlc.push([
                    data[i][0], // 날짜
                    data[i][1], // 시가
                    data[i][2], // 고가
                    data[i][3], // 저가
                    data[i][4]  // 종가
                ]);

                volume.push([
                    data[i][0], // 날짜
                    data[i][5]  // 거래량
                ]);
            }

            // Highcharts 차트를 생성합니다.
            Highcharts.stockChart('container', {
                rangeSelector: {
                    selected: 1
                },
                title: {
                    text: '003160 Stock Price'
                },
                yAxis: [{
                    labels: {
                        align: 'right',
                        x: -3
                    },
                    title: {
                        text: 'OHLC'
                    },
                    height: '60%',
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
                    top: '65%',
                    height: '35%',
                    offset: 0,
                    lineWidth: 2
                }],
                tooltip: {
                    split: true
                },
                series: [{
                    type: 'candlestick',
                    name: '003160',
                    data: ohlc,
                    dataGrouping: {
                        units: [
                            [
                                'week', // 단위 이름
                                [1] // 허용된 배수
                            ], [
                                'month',
                                [1, 2, 3, 4, 6]
                            ]
                        ]
                    }
                }, {
                    type: 'column',
                    name: 'Volume',
                    data: volume,
                    yAxis: 1,
                    dataGrouping: {
                        units: [
                            [
                                'week', // 단위 이름
                                [1] // 허용된 배수
                            ], [
                                'month',
                                [1, 2, 3, 4, 6]
                            ]
                        ]
                    }
                }]
            });
        });
    });
    </script>
</body>
</html>
