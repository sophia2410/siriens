<?php
$date = $_GET['date'] ?? '';
if (!$date) exit('날짜 없음');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title><?= $date ?> 전략 차트</title>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
  <script src="https://code.highcharts.com/stock/indicators/indicators.js"></script>
  <script src="https://code.highcharts.com/stock/indicators/sma.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', sans-serif;
    }
    #layout {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      grid-template-rows: 200px 300px 400px auto;
      gap: 5px;
      padding: 5px;
      box-sizing: border-box;
      height: 100vh;
    }
    #metric-content {
      grid-column: 1 / 4;
      font-size: 14px;
      padding: 10px;
      background: #f9f9f9;
      border: 1px solid #ccc;
    }
    .chart-box {
      border: 1px solid #ccc;
      min-height: 100px;
    }
    #chart-1m, #empty {
      grid-column: 1 / 4;
      height: 100%;
    }
  </style>
</head>
<body>
  <div id="layout">
    <div id="metric-content">일자를 선택하면 전략 수치가 이곳에 표시됩니다.</div>
    <div id="chart-5m" class="chart-box"></div>
    <div id="chart-15m" class="chart-box"></div>
    <div id="chart-day" class="chart-box"></div>
    <div id="chart-1m" class="chart-box"></div>
    <div id="empty"></div>
  </div>

<script>
const dateStr = "<?= $date ?>";

function renderChart(divId, dataArr, plotLines = []) {
  const el = document.getElementById(divId);
  if (el.chart) el.chart.destroy();
  if (!Array.isArray(dataArr) || dataArr.length === 0) {
    el.innerHTML = '<p style="text-align:center;padding-top:40px;color:#888;">No data</p>';
    return;
  }
  el.innerHTML = '';
  const cleanData = dataArr.map(row => [row[0], parseFloat(row[1]), parseFloat(row[2]), parseFloat(row[3]), parseFloat(row[4])]);

  const sma = (period) => {
    const result = [];
    for (let i = 0; i < cleanData.length; i++) {
      if (i < period - 1) {
        result.push([cleanData[i][0], cleanData[i][4]]); // 첫 봉부터 close값 유지
      } else {
        const slice = cleanData.slice(i - period + 1, i + 1);
        const sum = slice.reduce((acc, cur) => acc + cur[4], 0);
        result.push([cleanData[i][0], sum / period]);
      }
    }
    return result;
  };

  el.chart = Highcharts.stockChart(el, {
    chart: { height: el.clientHeight },
    rangeSelector: { enabled: false },
    navigator: { enabled: false },
    title: { text: '' },
    xAxis: {
      type: 'datetime',
      plotLines: plotLines
    },
    tooltip: {
      split: false,
      shared: true,
      valueDecimals: 2,
      pointFormat: '<b>O:</b>{point.open} <b>H:</b>{point.high} <b>L:</b>{point.low} <b>C:</b>{point.close}',
      positioner: function (labelWidth, labelHeight, point) {
        return { x: 0, y: 0 };
      }
    },
    plotOptions: {
      candlestick: {
        dataGrouping: { enabled: false },
        color: '#0066ff',
        lineColor: '#0066ff',
        upColor: '#ff3333',
        upLineColor: '#ff3333'
      }
    },
    series: [
      { type: 'candlestick', id: 'price', name: 'Price', zIndex: 5, data: cleanData },
      { type: 'sma', linkedTo: 'price', params: { period: 5 }, color: '#f7a35c' , zIndex: 1},
      { type: 'sma', linkedTo: 'price', params: { period: 20 }, color: '#90ed7d' , zIndex: 1},
      { type: 'sma', linkedTo: 'price', params: { period: 120 }, color: '#8085e9' , zIndex: 1}
    ]
  });
}

async function fetchCandle(date, tf) {
  const res = await fetch(`/modules/futures/get_candles.php?date=${date}&tf=${tf}`);
  return await res.json();
}

async function loadCharts(dateStr) {
  const [r1d, r5, r15, r1m] = await Promise.all([
    fetchCandle(dateStr, '1day'),
    fetchCandle(dateStr, '5m'),
    fetchCandle(dateStr, '15m'),
    fetchCandle(dateStr, '1m')
  ]);

  const r5Data = r5.candles || [];
  const r1mData = r1m.candles || [];

  const plotLines5m = r5Data.length >= 3 ? [{ color: 'rgb(255, 255, 0)', width: 6, value: r5Data[2][0], dashStyle: 'solid', zIndex: 3 }] : [];
  const nine = r1mData.find(r => new Date(r[0]).getHours() === 9 && new Date(r[0]).getMinutes() === 0);
  const plotLines1m = nine ? [{ color: 'rgb(255, 255, 0)', width: 6, value: nine[0], dashStyle: 'solid', zIndex: 3 }] : [];
  renderChart('chart-day', r1d.candles || []);
  renderChart('chart-5m',  r5Data, plotLines5m);
  renderChart('chart-15m', r15.candles || []);
  renderChart('chart-1m',  r1mData, plotLines1m);

  const f5 = r5Data.filter(row => {
    const t = new Date(row[0]);
    const h = t.getHours(), m = t.getMinutes();
    return h === 8 && m >= 45;
  });
  const f1 = r1mData.filter(row => {
    const t = new Date(row[0]);
    return t.getHours() === 9 && (t.getMinutes() === 0 || t.getMinutes() === 1);
  });

  const formatPt = n => (Math.round(n * 100) / 100).toFixed(2);
  const d1day = r1d.candles || [];
  const gap = d1day.length >= 2 ? formatPt(d1day[d1day.length - 1][1] - d1day[d1day.length - 2][4]) : '-';
  const vol_5min = f5.map(r => r[2] - r[3]);
  const vol_1min = f1.map(r => r[2] - r[3]);

  document.getElementById('metric-content').innerHTML = `
    <b>📅 ${dateStr}</b><br>
    🕘 시가 갭 (전일 종가 대비): <b>${gap}pt</b><br>
    🔄 5분봉 08:45~08:59 변동폭: <b>${vol_5min.map(formatPt).join(', ')}</b><br>
    ⏱️ 1분봉 09:00~09:01 변동폭: <b>${vol_1min.map(formatPt).join(', ')}</b>
  `;
}

loadCharts(dateStr);
</script>
</body>
</html>
