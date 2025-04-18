<?php
$date = $_GET['date'] ?? '';
if (!$date) exit('날짜 없음');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title><?= $date ?> 차트</title>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
  <script src="https://code.highcharts.com/stock/indicators/indicators.js"></script>
  <script src="https://code.highcharts.com/stock/indicators/ema.js"></script>
  <script src="https://code.highcharts.com/stock/indicators/sma.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body { margin: 0; font-family: 'Roboto', sans-serif; }
    #chart-area { display: flex; flex-direction: column; height: 100vh; }
    #chart-area > div { flex: 1; min-height: 200px; }
    #metric-content { padding: 10px; font-size: 14px; background: #f9f9f9; border-top: 1px solid #ddd; }
  </style>
</head>
<body>
  <div id="chart-area">
    <div id="chart-5m"></div>
    <div id="chart-day"></div>
    <div id="chart-1m"></div>
  </div>
  <div id="metric-content"></div>

<script>
const dateStr = "<?= $date ?>";

function renderChart(divId, dataArr) {
  const el = document.getElementById(divId);
  if (el.chart) el.chart.destroy();
  if (!Array.isArray(dataArr) || dataArr.length === 0) {
    el.innerHTML = '<p style="text-align:center;padding-top:40px;color:#888;">No data</p>';
    return;
  }
  el.innerHTML = '';
  const cleanData = dataArr.map(row => [
    row[0], parseFloat(row[1]), parseFloat(row[2]), parseFloat(row[3]), parseFloat(row[4])
  ]);
  el.chart = Highcharts.stockChart(el, {
    chart: { height: (divId === 'chart-1m' ? 350 : 250) },
    rangeSelector: { enabled: false },
    navigator: { enabled: false },
    title: { text: '' },
    xAxis: { type: 'datetime' },
    tooltip: {
      split: false, shared: true, valueDecimals: 2,
      pointFormat: '<b>O:</b>{point.open} <b>H:</b>{point.high} <b>L:</b>{point.low} <b>C:</b>{point.close}'
    },
    plotOptions: {
      candlestick: {
        dataGrouping: { enabled: false },
        color: '#0066ff', lineColor: '#0066ff',
        upColor: '#ff3333', upLineColor: '#ff3333'
      }
    },
    series: [
      {
        type: 'candlestick', id: 'price', name: 'Price', zIndex: 5, data: cleanData
      },
      {
        type: 'sma', linkedTo: 'price', params: { period: 5 }, dashStyle: 'solid', color: '#f7a35c'
      },
      {
        type: 'sma', linkedTo: 'price', params: { period: 20 }, dashStyle: 'solid', color: '#90ed7d'
      },
      {
        type: 'sma', linkedTo: 'price', params: { period: 120 }, dashStyle: 'solid', color: '#8085e9'
      }
    ]
  });
}

async function fetchCandle(date, tf) {
  const res = await fetch(`/modules/futures/get_candles.php?date=${date}&tf=${tf}`);
  return await res.json();
}

async function loadCharts(dateStr) {
  const [r1d, r5, r1m] = await Promise.all([
    fetchCandle(dateStr, '1day'),
    fetchCandle(dateStr, '5m'),
    fetchCandle(dateStr, '1m')
  ]);

  const d1day = r1d.candles || [];
  const d5 = r5.candles || [];
  const d1m = r1m.candles || [];

  renderChart('chart-day', d1day);
  renderChart('chart-5m', d5);
  renderChart('chart-1m', d1m);

  const f5 = d5.filter(row => {
    const t = new Date(row[0]);
    const h = t.getHours(), m = t.getMinutes();
    return (h === 8 && m >= 45 && m <= 59);
  });
  const f1 = d1m.filter(row => {
    const t = new Date(row[0]);
    return t.getHours() === 9 && (t.getMinutes() === 0 || t.getMinutes() === 1);
  });

  const formatPt = n => (Math.round(n * 100) / 100).toFixed(2);
  const gap = d1day.length >= 2 ? formatPt(d1day[d1day.length - 1][1] - d1day[d1day.length - 2][4]) : '-';
  const vol_5min = f5.map(r => r[2] - r[3]);
  const vol_1min = f1.map(r => r[2] - r[3]);

  document.getElementById('metric-content').innerHTML = `
    📅 <b>${dateStr}</b><br>
    🕘 시가 갭 (전일 종가 대비): <b>${gap}pt</b><br>
    🔄 5분봉 08:45~08:59 변동폭: <b>${vol_5min.map(formatPt).join(', ')}</b><br>
    ⏱️ 1분봉 09:00~09:01 변동폭: <b>${vol_1min.map(formatPt).join(', ')}</b>
  `;
}

loadCharts(dateStr);
</script>
</body>
</html>
