<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$date = $_GET['date'] ?? '';
$interval = $_GET['interval'] ?? '60';
if (!$date || !in_array($interval, ['15', '30', '60'])) {
  echo "날짜 또는 분봉(interval) 값이 올바르지 않습니다.";
  exit;
}

switch ($interval) {
  case '15':
    $table = 'futures_bb_rsi_15m'; $dayRange = 2; break;
  case '30':
    $table = 'futures_bb_rsi_30m'; $dayRange = 5; break;
  default:
    $table = 'futures_bb_rsi_60m'; $dayRange = 20; break;
}

$dates = [];
$res = $mysqli->query("SELECT DISTINCT date FROM $table ORDER BY date");
while ($row = $res->fetch_assoc()) $dates[] = $row['date'];

$index = array_search($date, $dates);
if ($index === false) {
  echo "해당 날짜의 데이터가 없습니다.";
  exit;
}

$start = max(0, $index - $dayRange);
$end = min(count($dates) - 1, $index + 1);
$targetDates = array_slice($dates, $start, $end - $start + 1);
$inClause = "('" . implode("','", array_map(fn($d) => $mysqli->real_escape_string($d), $targetDates)) . "')";

$sql = "SELECT * FROM $table WHERE date IN $inClause ORDER BY date, time ASC";
$result = $mysqli->query($sql);

$data = $rsi = $bb = $ema20 = $ema60 = $volume = $macd = $macdHist = [];
$plotLines = [];
$seenDates = [];

while ($row = $result->fetch_assoc()) {
    $ts = strtotime($row['date'] . ' ' . $row['time']) * 1000 + (15 * 60 * 1000);

    $open = (float)$row['open'];
    $close = (float)$row['close'];
    $vol = (float)$row['volume'];
    $color = $close > $open ? '#f45b5b' : '#2f7ed8';

    $data[] = [$ts, $open, (float)$row['high'], (float)$row['low'], $close];
    $rsi[] = [$ts, round($row['rsi14'], 2)];
    $bb[] = [$ts, round($row['bb_lower'], 2), round($row['bb_center'], 2), round($row['bb_upper'], 2)];
    $ema20[] = [$ts, round($row['ema20'], 2)];
    $ema60[] = [$ts, round($row['ema60'], 2)];
    $volume[] = ['x' => $ts, 'y' => $vol, 'color' => $color];

    $macdVal = [
        'ts' => $ts,
        'macd' => round($row['macd'], 2),
        'signal' => round($row['macd_signal'], 2),
        'hist' => round($row['macd_hist'], 2)
    ];
    $macd[] = $macdVal;
    $macdHist[] = [
        'x' => $ts,
        'y' => $macdVal['hist'],
        'color' => $macdVal['hist'] >= 0 ? '#ff4136' : '#0074d9'
    ];

    $dateKey = date('Y-m-d', $ts / 1000);
    if (!isset($seenDates[$dateKey])) {
        $seenDates[$dateKey] = true;
        $plotLines[] = [
            'color' => '#cccccc',
            'width' => 1,
            'value' => $ts,
            'dashStyle' => 'ShortDot',
            'label' => [
                'text' => date('m-d', $ts / 1000),
                'rotation' => 0,
                'align' => 'left',
                'y' => 12,
                'style' => ['color' => '#666', 'fontSize' => '10px']
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?= $date ?> / <?= $interval ?>분봉 차트</title>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
  <script src="https://code.highcharts.com/highcharts-more.js"></script>
  <style>
    html, body {
      margin: 0; padding: 0; height: 100%;
    }
    #container { height: 800px; width: 100vw; }
    #info-box {
      padding: 10px;
      font-size: 14px;
      color: #333;
      background: #f8f8f8;
      border-top: 1px solid #ccc;
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      line-height: 1.5em;
    }
    #info-box div {
      min-width: 180px;
    }
  </style>
</head>
<body>
  <h2 style="margin:0; padding:10px;">📅 <?= $date ?> / <?= $interval ?>분봉 차트</h2>
  <div id="container"></div>
  <div id="info-box">🖱 캔들을 클릭하면 상세 정보가 여기에 표시됩니다.</div>

  <script>
    const macdData = <?= json_encode($macd) ?>;
    const macdHist = <?= json_encode($macdHist) ?>;

    Highcharts.stockChart('container', {
      tooltip: { enabled: false },
      chart: { zoomType: 'x' },
      title: { text: '<?= $date ?> / <?= $interval ?>분봉 차트' },
      xAxis: {
        type: 'datetime',
        tickInterval: 3600 * 1000,
        labels: { enabled: false },
        plotLines: <?= json_encode($plotLines) ?>
      },
      navigator: { enabled: false },
      scrollbar: { enabled: true },
      plotOptions: {
        candlestick: {
          point: {
            events: {
              click: function () {
                const ts = this.x;
                const chart = this.series.chart;
                const getVal = (name) => {
                  const s = chart.series.find(s => s.name === name);
                  const p = s?.data.find(p => p.x === ts);
                  return p?.y ?? '-';
                };
                const macd = macdData.find(m => m.ts === ts);
                const info = `
                  <div><b>${Highcharts.dateFormat('%Y-%m-%d %H:%M', ts)}</b></div>
                  <div>
                    <b>시가</b>: ${this.open}<br>
                    <b>고가</b>: ${this.high}<br>
                    <b>저가</b>: ${this.low}<br>
                    <b>종가</b>: ${this.close}
                  </div>
                  <div>
                    <b>EMA20</b>: ${getVal('EMA20')}<br>
                    <b>EMA60</b>: ${getVal('EMA60')}
                  </div>
                  <div>
                    <b>BB 상단</b>: ${getVal('BB 상단')}<br>
                    <b>BB 중심</b>: ${getVal('중심선')}<br>
                    <b>BB 하단</b>: ${getVal('BB 하단')}
                  </div>
                  <div>
                    <b>RSI</b>: ${getVal('RSI')}
                  </div>
                  <div>
                    <b>MACD</b>: ${macd?.macd ?? '-'}<br>
                    <b>Signal</b>: ${macd?.signal ?? '-'}<br>
                    <b>Histogram</b>: ${macd?.hist ?? '-'}
                  </div>
                `;
                document.getElementById('info-box').innerHTML = info;
              }
            }
          }
        }
      },
      yAxis: [
        {
          title: { text: 'Price', style: { color: '#2f7ed8' } },
          height: '55%',
          lineWidth: 2,
          gridLineWidth: 1,
          gridLineColor: '#cccccc'
        },
        {
          title: { text: 'Volume', style: { color: '#333' } },
          top: '55%',
          height: '10%',
          offset: 0,
          lineWidth: 2,
          gridLineWidth: 1,
          gridLineColor: '#999999'
        },
        {
          title: { text: 'RSI', style: { color: '#ff5733' } },
          top: '65%',
          height: '15%',
          offset: 0,
          lineWidth: 2,
          gridLineWidth: 1,
          gridLineColor: '#999999',
          min: 0,
          max: 100,
          plotLines: [
            { value: 70, color: 'gray', dashStyle: 'Dash', width: 1, label: { text: '70', align: 'right' } },
            { value: 30, color: 'gray', dashStyle: 'Dash', width: 1, label: { text: '30', align: 'right' } }
          ]
        },
        {
          title: { text: 'MACD', style: { color: '#0074d9' } },
          top: '80%',
          height: '20%',
          offset: 0,
          lineWidth: 2,
          gridLineWidth: 1,
          gridLineColor: '#999999'
        }
      ],
      series: [
        {
          type: 'candlestick',
          name: 'Price',
          data: <?= json_encode($data) ?>,
          color: '#2f7ed8',
          upColor: '#f45b5b'
        },
        {
          type: 'line',
          name: 'EMA20',
          data: <?= json_encode($ema20) ?>,
          color: 'orange',
          lineWidth: 1.5
        },
        {
          type: 'line',
          name: 'EMA60',
          data: <?= json_encode($ema60) ?>,
          color: 'green',
          lineWidth: 1.5
        },
        {
          type: 'arearange',
          name: '볼린저밴드',
          yAxis: 0,
          data: <?= json_encode(array_map(fn($r) => [$r[0], $r[1], $r[3]], $bb)) ?>,
          color: 'rgba(200,200,200,0.3)',
          fillOpacity: 0.3,
          lineWidth: 0,
          linkedTo: ':previous'
        },
        {
          type: 'line',
          name: '중심선',
          data: <?= json_encode(array_map(fn($r) => [$r[0], $r[2]], $bb)) ?>,
          dashStyle: 'ShortDot',
          color: '#000000'
        },
        {
          type: 'line',
          name: 'BB 상단',
          data: <?= json_encode(array_map(fn($r) => [$r[0], $r[3]], $bb)) ?>,
          color: '#e00000',
          dashStyle: 'ShortDash'
        },
        {
          type: 'line',
          name: 'BB 하단',
          data: <?= json_encode(array_map(fn($r) => [$r[0], $r[1]], $bb)) ?>,
          color: '#00aacc',
          dashStyle: 'ShortDash'
        },
        {
          type: 'column',
          name: '거래량',
          data: <?= json_encode($volume) ?>,
          yAxis: 1
        },
        {
          type: 'line',
          name: 'RSI',
          data: <?= json_encode($rsi) ?>,
          yAxis: 2,
          color: '#888888',
          zones: [
            { value: 30, color: '#3b82f6' },
            { value: 70, color: '#888888' },
            { color: '#ef4444' }
          ]
        },
        {
          type: 'line',
          name: 'MACD',
          data: <?= json_encode(array_map(fn($r) => [$r['ts'], $r['macd']], $macd)) ?>,
          yAxis: 3,
          color: '#0074d9'
        },
        {
          type: 'line',
          name: 'MACD Signal',
          data: <?= json_encode(array_map(fn($r) => [$r['ts'], $r['signal']], $macd)) ?>,
          yAxis: 3,
          color: '#ff4136'
        },
        {
          type: 'column',
          name: 'MACD Histogram',
          data: macdHist,
          yAxis: 3
        }
      ]
    });
  </script>
</body>
</html>
