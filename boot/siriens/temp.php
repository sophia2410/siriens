<script>
$(document).ready(function() {
    var code = '<?= $code ?>';
    
    $.getJSON('/boot/common/ajax/ajaxHighcharts.php', { code: code }, function(data) {
        if (data.message) {
            alert(data.message);
            return;
        }
        var ohlc = [],
            volume = [],
            xAxisPlotLines = [], // xAxis plotLines array
            annotations = [],
            ma3 = [],
            ma5 = [],
            ma20 = [],
            ma60 = [],
            ma120 = [],
            ma224 = [],
            i = 0;

        for (i; i < data.length; i += 1) {
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
                color: isUp ? 'red' : 'blue',
                borderColor: isUp ? 'red' : 'blue',
                amount: amount,
                xray_amount: xray_amount
            });

            volume.push({
                x: date,
                y: volume_val,
                color: isUp ? 'red' : 'blue'
            });

            // Calculate moving averages
            if (i >= 2) ma3.push({ date: date, value: (data[i][4] + data[i-1][4] + data[i-2][4]) / 3 });
            if (i >= 4) ma5.push({ date: date, value: (data[i][4] + data[i-1][4] + data[i-2][4] + data[i-3][4] + data[i-4][4]) / 5 });
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

            // Add annotations
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
            } else if (xray_amount > 5000000000) {
                annotations.push({
                    labels: [{
                        point: {
                            xAxis: 0,
                            yAxis: 0,
                            x: date,
                            y: high
                        },
                        text: '☆',
                        style: {
                            color: 'red',
                            fontSize: '12px',
                            fontWeight: 'bold'
                        },
                        backgroundColor: 'transparent',
                        borderColor: 'transparent'
                    }]
                });
            } else if (xray_amount > 10000000000) {
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
            } else if (xray_amount > 3000000000) {
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
                            color: 'black',
                            fontSize: '12px',
                            fontWeight: 'bold'
                        },
                        backgroundColor: 'transparent',
                        borderColor: 'transparent'
                    }]
                });
            } else if (xray_amount > 1000000000) {
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

        // Highcharts 차트를 생성합니다.
        var chart = Highcharts.stockChart('container', {
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
                marginRight: 100 // 오른쪽 여백 추가
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
                    x: 60, // 라벨을 차트 밖으로 밀어냄
                    formatter: function() {
                        return Highcharts.numberFormat(this.value, 0, '.', ','); // 모든 값을 포맷팅하여 표시
                    }
                },
                title: {
                    text: 'OHLC'
                },
                height: '85%', // OHLC 차트의 높이
                lineWidth: 2,
                resize: {
                    enabled: true
                }
            }, {
                labels: {
                    align: 'right',
                    x: 60, // 라벨을 차트 밖으로 밀어냄
                    formatter: function() {
                        return Highcharts.numberFormat(this.value, 0, '.', ','); // 모든 값을 포맷팅하여 표시
                    }
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
                text: '★: 1천억 이상, ☆: 5백억 이상, ▲: 1백억 이상, ▲: 30억 이상, ↑: 10억 이상',
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
                    tooltipHtml += '<b>' + Highcharts.dateFormat('%Y-%m-%d', this.x) + '</b><br>';
                    tooltipHtml += '시가: ' + Highcharts.numberFormat(this.points[0].point.open, 0, '.', ',') + '<br>';
                    tooltipHtml += '고가: ' + Highcharts.numberFormat(this.points[0].point.high, 0, '.', ',') + '<br>';
                    tooltipHtml += '저가: ' + Highcharts.numberFormat(this.points[0].point.low, 0, '.', ',') + '<br>';
                    tooltipHtml += '종가: ' + Highcharts.numberFormat(this.points[0].point.close, 0, '.', ',') + '<br>';
                    tooltipHtml += '3이평: ' + (index >= 2 ? Highcharts.numberFormat(ma3[index].value, 0, '.', ',') : 'N/A') + '<br>';
                    tooltipHtml += '5이평: ' + (index >= 4 ? Highcharts.numberFormat(ma5[index].value, 0, '.', ',') : 'N/A') + '<br>';
                    tooltipHtml += '20이평: ' + (index >= 19 ? Highcharts.numberFormat(ma20[index].value, 0, '.', ',') : 'N/A') + '<br>';
                    tooltipHtml += '60이평: ' + (index >= 59 ? Highcharts.numberFormat(ma60[index].value, 0, '.', ',') : 'N/A') + '<br>';
                    tooltipHtml += '120이평: ' + (index >= 119 ? Highcharts.numberFormat(ma120[index].value, 0, '.', ',') : 'N/A') + '<br>';
                    tooltipHtml += '224이평: ' + (index >= 223 ? Highcharts.numberFormat(ma224[index].value, 0, '.', ',') : 'N/A') + '<br>';
                    tooltipHtml += '거래량: ' + Highcharts.numberFormat(volume[index].y / 1000, 0, '.', ',') + 'K<br>';
                    tooltipHtml += '거래대금: ' + Highcharts.numberFormat(amount / 100000000, 1, '.', ',') + '억<br>';
                    tooltipHtml += 'Xray 거래대금: ' + Highcharts.numberFormat(xray_amount / 100000000, 1, '.', ',') + '억<br>';
                    tooltipHtml += '</div>';
                    return tooltipHtml;
                },
                positioner: function (labelWidth, labelHeight, point) {
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
                borderColor: 'transparent', // 캔들 외곽선 제거
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
                                            text: 'Close: ' + Highcharts.numberFormat(point.close, 0, '.', ','),
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
                color: 'blue',
                dashStyle: 'shortdash',
                zIndex: 1,
                lineWidth: 1 // 이동평균선의 두께를 1로 설정
            }, {
                type: 'line',
                name: 'MA 5',
                data: ma5.map(m => [m.date, m.value]),
                color: 'green',
                dashStyle: 'shortdot',
                zIndex: 1,
                lineWidth: 1 // 이동평균선의 두께를 1로 설정
            }, {
                type: 'line',
                name: 'MA 20',
                data: ma20.map(m => [m.date, m.value]),
                color: 'purple',
                dashStyle: 'longdash',
                zIndex: 1,
                lineWidth: 1 // 이동평균선의 두께를 1로 설정
            }, {
                type: 'line',
                name: 'MA 60',
                data: ma60.map(m => [m.date, m.value]),
                color: 'orange',
                dashStyle: 'solid',
                zIndex: 1,
                lineWidth: 1 // 이동평균선의 두께를 1로 설정
            }, {
                type: 'line',
                name: 'MA 120',
                data: ma120.map(m => [m.date, m.value]),
                color: 'red',
                dashStyle: 'solid',
                zIndex: 1,
                lineWidth: 1 // 이동평균선의 두께를 1로 설정
            }, {
                type: 'line',
                name: 'MA 224',
                data: ma224.map(m => [m.date, m.value]),
                color: 'black',
                dashStyle: 'solid',
                zIndex: 1,
                lineWidth: 1 // 이동평균선의 두께를 1로 설정
            }]
        });

        // 키보드 화살표로 차트 이동
        $(document).keydown(function(e) {
            var xExtremes = chart.xAxis[0].getExtremes();
            var step = (xExtremes.max - xExtremes.min) / 10; // 10% 이동

            switch (e.which) {
                case 37: // left arrow key
                    chart.xAxis[0].setExtremes(xExtremes.min - step, xExtremes.max - step);
                    break;

                case 39: // right arrow key
                    chart.xAxis[0].setExtremes(xExtremes.min + step, xExtremes.max + step);
                    break;

                default:
                    return; // exit this handler for other keys
            }
            e.preventDefault(); // prevent the default action (scroll / move caret)
        });
    });
});
</script>
