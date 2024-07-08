function formatDate(timestamp) {
    var date = new Date(timestamp);
    var year = date.getFullYear();
    var month = ('0' + (date.getMonth() + 1)).slice(-2);
    var day = ('0' + date.getDate()).slice(-2);
    return year + '-' + month + '-' + day;
}

function formatAmount(amount) {
    return (amount / 100000000).toFixed(2) + '억';
}

$(document).ready(function() {
    // HTML 요소에서 data-code와 data-name 값을 가져옵니다.
    var code = $('#container').data('code');
    var name = $('#container').data('name');

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
            xAxisPlotLines = [],
            yAxisPlotLines = [],
            yAxisPlotBands = [],
            annotations = [],
            dataLength = data.length,
            ma3 = [],
            ma5 = [],
            ma8 = [],
            ma20 = [],
            ma60 = [],
            ma120 = [],
            ma224 = [],
            navigatorPlotLines = [],
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
            if (xray_amount > 100000000000) {
                console.log('xray_amount > 1천억이상:', formatAmount(xray_amount), 'Date:', formatDate(date), 'High:', high);
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
            } else if (xray_amount > 50000000000) {
                console.log('xray_amount > 5백억이상:', formatAmount(xray_amount), 'Date:', formatDate(date), 'High:', high);
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
            } else if (xray_amount > 10000000000) {
                console.log('xray_amount > 1백억이상:', formatAmount(xray_amount), 'Date:', formatDate(date), 'High:', high);
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
                console.log('xray_amount > 3십억이상:', formatAmount(xray_amount), 'Date:', formatDate(date), 'High:', high);
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
            } else if (xray_amount > 1000000000) {
                console.log('xray_amount > 1십억이상:', formatAmount(xray_amount), 'Date:', formatDate(date), 'High:', high);
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
            } else {
                console.log('No condition met for xray_amount:', formatAmount(xray_amount), 'Date:', formatDate(date));
            }
        }

        // stock_price_zone 데이터를 yAxisPlotLines와 yAxisPlotBands에 추가
        for (var j = 0; j < zones.length; j++) {
            if (zones[j].end_price) {
                yAxisPlotBands.push({
                    color: zones[j].color,
                    from: zones[j].start_price,
                    to: zones[j].end_price,
                    label: {
                        text: zones[j].zone_type + ': ' + zones[j].start_price.toLocaleString() + ' ~ ' + zones[j].end_price.toLocaleString(),
                        align: 'left', // 레이블을 왼쪽으로 정렬
                        x: -10, // 레이블 위치를 왼쪽으로 조정
                        style: {
                            color: zones[j].color,
                            fontWeight: 'bold'
                        }
                    }
                });
            } else {
                yAxisPlotLines.push({
                    color: zones[j].color,
                    width: 2,
                    value: zones[j].start_price,
                    dashStyle: zones[j].dash_style,
                    label: {
                        text: zones[j].zone_type + ': ' + zones[j].start_price.toLocaleString(),
                        align: 'left', // 레이블을 왼쪽으로 정렬
                        x: -10, // 레이블 위치를 왼쪽으로 조정
                        style: {
                            color: zones[j].color,
                            fontWeight: 'bold'
                        }
                    }
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
                marginRight: 80, // 오른쪽 여백 추가
                events: {
                    load: function() {
                        var chart = this;
                        $(document).on('keydown', function(e) {
                            var extremes = chart.xAxis[0].getExtremes();
                            var shift = (extremes.dataMax - extremes.dataMin) / dataLength; // 캔들 하나의 범위
                            if (e.keyCode === 37) { // 왼쪽 화살표
                                chart.xAxis[0].setExtremes(extremes.min - shift, extremes.max - shift);
                            } else if (e.keyCode === 39) { // 오른쪽 화살표
                                chart.xAxis[0].setExtremes(extremes.min + shift, extremes.max + shift);
                            }
                        });
                    }
                }
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
                plotLines: yAxisPlotLines, // yAxis에 가로선을 추가
                plotBands: yAxisPlotBands // yAxis에 범위를 추가
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

        // 차트 생성 후 주석 추가
        annotations.forEach(function(annotation) {
            console.log('Adding annotation:', annotation);
            chart.addAnnotation(annotation);
        });
    });
});
