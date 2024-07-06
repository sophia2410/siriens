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
            xAxisPlotLines = [],
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
                    zIndex: 3
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
                    align: 'right',
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
                selected: 2,
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
                marginRight: 80
            },
            xAxis: {
                plotLines: xAxisPlotLines,
                labels: {
                    formatter: function() {
                        return Highcharts.dateFormat('%m/%d', this.value);
                    },
                    x: 10
                }
            },
            yAxis: [{
                labels: {
                    align: 'right',
                    x: 50,
                    formatter: function() {
                        return Highcharts.numberFormat(this.value, 0, '.', ',');
                    }
                },
                height: '85%',
                lineWidth: 2,
                resize: {
                    enabled: true
                },
                plotLines: yAxisPlotLines // yAxis에 가로선을 추가
            }, {
                labels: {
                    align: 'right',
                    x: -10
                },
                title: {
                    text: 'Volume'
                },
                top: '87%',
                height: '13%',
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
                color: 'blue',
                upColor: 'red',
                zIndex: 5,
                lineWidth: 1,
                lineColor: 'blue',
                upLineColor: 'red',
                borderColor: 'blue',
                upBorderColor: 'red',
                dataGrouping: {
                    units: [[
                        'week',
                        [1]
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
                                });
                            }
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
                zIndex: 0
            }, {
                type: 'line',
                name: 'MA 3',
                data: ma3.map(m => [m.date, m.value]),
                color: 'rgb(219, 27, 180)',
                dashStyle: 'shortdash',
                zIndex: 1,
                lineWidth: 1
            }, {
                type: 'line',
                name: 'MA 5',
                data: ma5.map(m => [m.date, m.value]),
                color: 'rgb(219, 27, 180)',
                dashStyle: 'solid',
                zIndex: 1,
                lineWidth: 2
            }, {
                type: 'line',
                name: 'MA 8',
                data: ma8.map(m => [m.date, m.value]),
                color: 'green',
                dashStyle: 'longdash',
                zIndex: 1,
                lineWidth: 1
            }, {
                type: 'line',
                name: 'MA 20',
                data: ma20.map(m => [m.date, m.value]),
                color: 'rgb(239, 174, 0)',
                dashStyle: 'solid',
                zIndex: 1,
                lineWidth: 2
            }, {
                type: 'line',
                name: 'MA 60',
                data: ma60.map(m => [m.date, m.value]),
                color: 'rgb(10, 41, 174)',
                dashStyle: 'solid',
                zIndex: 1,
                lineWidth: 1
            }, {
                type: 'line',
                name: 'MA 120',
                data: ma120.map(m => [m.date, m.value]),
                color: 'rgb(77, 77, 77)',
                dashStyle: 'shortdot',
                zIndex: 1,
                lineWidth: 1
            }, {
                type: 'line',
                name: 'MA 224',
                data: ma224.map(m => [m.date, m.value]),
                color: 'rgb(119, 119, 119)',
                dashStyle: 'solid',
                zIndex: 1,
                lineWidth: 2
            }],
            navigator: {
                xAxis: {
                    plotLines: navigatorPlotLines
                }
            }
        });
    });
});
