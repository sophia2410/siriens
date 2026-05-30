<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php"; // 공통 DB 연결

// ✅ 필터 값 처리
$rsi_from = $_GET['rsi_from'] ?? 0;
$rsi_to   = $_GET['rsi_to'] ?? 30;
$gap_from = $_GET['gap_from'] ?? 2;
$gap_to   = $_GET['gap_to'] ?? 3;
$interval = $_GET['interval'] ?? '1m';

// ✅ 조건에 맞는 날짜 데이터 조회
$sql = "
  SELECT date, prev_rsi14, gap_pt
  FROM rule_based_rowdata
  WHERE prev_rsi14 BETWEEN ? AND ?
    AND gap_pt BETWEEN ? AND ?
  ORDER BY date DESC
  LIMIT 100
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('dddd', $rsi_from, $rsi_to, $gap_from, $gap_to);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
while ($row = $result->fetch_assoc()) {
  $dates[] = [
    'date' => $row['date'],
    'rsi' => $row['prev_rsi14'],
    'gap' => $row['gap_pt']
  ];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>전략별 1분봉 비교</title>
  <?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/highcharts.php"; ?>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body {
      margin: 0; padding: 10px; font-family: sans-serif;
    }
    .filter-box {
      margin-bottom: 20px; display: flex; gap: 12px; align-items: center;
    }
    .chart-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
      gap: 16px;
    }
    .chart-item {
      border: 1px solid #ccc;
      padding: 8px;
      background: #fff;
      border-radius: 6px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .chart-title {
      font-weight: bold;
      margin-bottom: 5px;
      font-size: 14px;
      color: #333;
    }
  </style>
</head>
<body>
  <form method="get" class="filter-box">
    전일 RSI:
    <input type="number" step="1" name="rsi_from" value="<?= $rsi_from ?>"> ~
    <input type="number" step="1" name="rsi_to" value="<?= $rsi_to ?>">

    갭(포인트):
    <input type="number" step="0.1" name="gap_from" value="<?= $gap_from ?>"> ~
    <input type="number" step="0.1" name="gap_to" value="<?= $gap_to ?>">

    분봉:
    <select name="interval">
      <option value="1m" <?= $interval === '1m' ? 'selected' : '' ?>>1분봉</option>
      <option value="5m" <?= $interval === '5m' ? 'selected' : '' ?>>5분봉</option>
    </select>

    <button type="submit">조회</button>
  </form>

  <div class="chart-grid">
    <?php foreach ($dates as $i => $d): ?>
      <div class="chart-item">
        <div class="chart-title">
          📅 <?= $d['date'] ?> | RSI <?= $d['rsi'] ?> | Gap <?= ($d['gap'] > 0 ? '+' : '') . $d['gap'] ?> pt
        </div>
        <div id="chart-<?= $i ?>" style="height:300px;"></div>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    const dateList = <?= json_encode($dates) ?>;
    const interval = '<?= $interval ?>';

    function drawChart(idx, date) {
      $.getJSON(`./get_1min_data.php?date=${date}&interval=${interval}`, function(data) {
        const openPrice = parseFloat(data[0].open);

        const candles = data.map(row => [
          new Date(row.datetime).getTime(),
          parseFloat(row.open),
          parseFloat(row.high),
          parseFloat(row.low),
          parseFloat(row.close)
        ]);

        const sma5 = data.map(row => [new Date(row.datetime).getTime(), parseFloat(row.sma_5)]);
        const sma20 = data.map(row => [new Date(row.datetime).getTime(), parseFloat(row.sma_20)]);
        const volume = data.map(row => ({
          x: new Date(row.datetime).getTime(),
          y: parseFloat(row.volume),
          color: row.close > row.open ? '#f45b5b' : '#2f7ed8'
        }));

        Highcharts.stockChart('chart-' + idx, {
          chart: {
            height: 280,
            zooming: {
              mouseWheel: { enabled: false },
              type: null
            },
            panning: false,
            panKey: null
          },
          mapNavigation: { enabled: false },
          tooltip: { enabled: false },
          navigator: { enabled: false },
          scrollbar: { enabled: false },
          rangeSelector: { enabled: false },
          title: { text: '' },
          time: { useUTC: false },
          xAxis: {
            type: 'datetime',
            labels: { format: '{value:%H:%M}' }
          },
          yAxis: [
            {
              height: '70%',
              lineWidth: 1,
              plotLines: [{
                color: 'gray',
                value: openPrice,
                width: 1,
                dashStyle: 'Dash',
                label: {
                  text: `시가 ${openPrice}`,
                  align: 'right',
                  style: { color: '#666', fontSize: '11px' }
                }
              }]
            },
            {
              top: '75%',
              height: '25%',
              offset: 0,
              lineWidth: 1,
              min: 0,
              max: 8000
            }
          ],
          series: [
            { type: 'candlestick', name: 'Price', data: candles },
            { type: 'line', name: 'SMA 5', data: sma5, color: '#db1bb4' },
            { type: 'line', name: 'SMA 20', data: sma20, color: '#ffaa00' },
            { type: 'column', name: 'Volume', data: volume, yAxis: 1 }
          ],
          plotOptions: {
            candlestick: {
              color: '#2f7ed8',
              upColor: '#f45b5b',
              lineColor: '#2f7ed8',
              upLineColor: '#f45b5b'
            }
          }
        });
      });
    }

    dateList.forEach((d, idx) => drawChart(idx, d.date));
  </script>
</body>
</html>
