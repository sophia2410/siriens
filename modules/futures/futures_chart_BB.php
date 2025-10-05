<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php"; // $mysqli 제공

$date = $_GET['date'] ?? '';
$interval = $_GET['interval'] ?? '60';

if (!$date) {
?>
  <button onclick="startFlask()">🚀 Flask 서버 실행</button>
  <span id="flask-status"></span>

  <!-- 페이지 끝나기 직전에 JS 넣기 -->
  <script>
  function startFlask() {
    fetch('/modules/futures/start_flask.php')
      .then(res => res.json())
      .then(data => {
        const statusEl = document.getElementById('flask-status');
        if (data.status === 'already running') {
          statusEl.innerText = 'Flask 서버 이미 실행 중';
        } else if (data.status === 'started') {
          statusEl.innerText = 'Flask 서버 실행됨!';
        } else {
          statusEl.innerText = '실행 실패';
        }
      });
  }
  </script>
<?php
  exit();
}

$date = $_GET['date'] ?? date('Y-m-d');

// 이전일 / 다음일 조회
$stmt_prev = $mysqli->prepare("SELECT MAX(date) FROM calendar WHERE date < ?");
$stmt_prev->bind_param('s', $date);
$stmt_prev->execute();
$stmt_prev->bind_result($prev_date);
$stmt_prev->fetch();
$stmt_prev->close();

$stmt_next = $mysqli->prepare("SELECT MIN(date) FROM calendar WHERE date > ?");
$stmt_next->bind_param('s', $date);
$stmt_next->execute();
$stmt_next->bind_result($next_date);
$stmt_next->fetch();
$stmt_next->close();

// 선물 일봉 테이블 정보 받아오기
$sql = "
    SELECT
        f1.*,
        ROUND((f1.open - f2.close) / f2.close * 100, 2) AS open_change_pct,
        ROUND((f1.close - f2.close) / f2.close * 100, 2) AS close_change_pct,
        f1.open - f2.close AS open_change_pt,
        f1.close - f2.close AS close_change_pt,
        fa.created_at
    FROM futures_1day f1
    LEFT JOIN futures_1day f2
        ON f2.date = (
            SELECT MAX(date)
            FROM futures_1day
            WHERE date < f1.date
        )
    LEFT JOIN futures_analysis fa ON f1.date = fa.date
    WHERE f1.date = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
$strategy = $result->fetch_assoc();

// Fetch KOSPI and KOSDAQ index data
$index_query = "
    SELECT close_rate
    FROM market_index
    WHERE market_fg = 'NASDAQ'
    AND date = (-- S&P 500과 NASDAQ의 전 거래일 데이터를 가져옴
                SELECT MAX(c.date)
                FROM calendar c
                WHERE c.date < ? )
";
$stmt = $mysqli->prepare($index_query);
$stmt->bind_param("s", $date);
$stmt->execute();
$stmt->bind_result($market_index);
$stmt->fetch();
$stmt->close();


// 상승/하락에 따른 색상 설정
function colorize($value) {
  if (!isset($value)) return '-';

    // 숫자가 아니면 그대로 반환
    if (!is_numeric($value)) return $value;

    // 소수점 자릿수 계산 (정수형도 커버)
    $formatted = (floor($value) != $value)
        ? number_format($value, 2)  // 소수점 있으면 2자리 고정
        : number_format($value);    // 정수면 그냥


  if ($value > 0) {
      return "<span style='color:red;'>$formatted</span>";
  } elseif ($value < 0) {
      return "<span style='color:blue;'>$formatted</span>";
  } else {
      return $formatted;
  }
}

// BB-RSI-EMA 전략 조회를 위한 차트 정보 조회
switch ($interval) {
  case '15':
    $table = 'futures_15min'; $table_ft = 'futures_bb_rsi_features_15m'; $dayRange = 2; break;
  default:
    $table = 'futures_60min'; $table_ft = 'futures_bb_rsi_features_60m'; $dayRange = 15; break;
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
$targetDates = array_slice($dates, $start, $end - $start); // 기준일까지 조회로 변경. BACKTEST 때문.. 이후 다시 조정 필요. 25.06.29
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
    $rsi[] = [$ts, round($row['rsi_14'], 2)];
    $bb[] = [$ts, round($row['bb_lower'], 2), round($row['bb_center'], 2), round($row['bb_upper'], 2)];
    $ema20[] = [$ts, round($row['ema_20'], 2)];
    $ema60[] = [$ts, round($row['ema_60'], 2)];
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
// 캔들 추가정보 조회
$features = [];
$featuresRes = $mysqli->query("SELECT * FROM $table_ft WHERE date IN $inClause ORDER BY date ASC");
while ($row = $featuresRes->fetch_assoc()) {
    $ts = $row['date'];
    $features[$ts] = [
        'body_pct' => round($row['body_pct'], 2),
        'ema_gap' => round($row['ema_gap'], 2),
        'macd_hist' => round($row['macd_hist'], 2),
        'macd' => round($row['macd'], 2),
        'candle_type' => $row['candle_type'],
        'bb_width' => round($row['bb_width'], 2),
        'ema_bb_gap' => round($row['ema_bb_gap'], 2),
        'macd_hist_change' => round($row['macd_hist_change'], 2),
        'index_vs_ema20' => round($row['index_vs_ema20'], 2),
        'index_vs_ema60' => round($row['index_vs_ema60'], 2),
        'rsi_14' => round($row['rsi_14'], 2),
        'bb_position' => round($row['bb_position'], 2),
        'lower_tail_pct' => round($row['lower_tail_pct'], 2)
    ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title><?= $date ?> 전략 차트</title>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
  <script src="https://code.highcharts.com/highcharts-more.js"></script>
  <script src="https://code.highcharts.com/stock/indicators/indicators.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Roboto', sans-serif;
      overflow-x: hidden;
    }
    #layout {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      grid-template-rows: 80px 280px 620px auto;
      gap: 2px;
      padding: 2px;
      box-sizing: border-box;
      height: 95vh;
      overflow-x: hidden; /* 👈 가로 스크롤 제거 */
    }
    #metric-content {
      grid-column: 1 / 6;
      font-size: 16px;
      padding: 5px;
      /* background: #f9f9f9; */
      border: 1px solid #ccc;
    }
    .chart-box {
      border: 1px solid #ccc;
      min-height: 100px;
      overflow: hidden; /* 모든 방향의 오버플로우 숨김 */
      width: 100%; /* 너비 100%로 제한 */
      box-sizing: border-box; /* 패딩과 테두리를 너비에 포함 */
    }
    #chart-60m, #info-box {
      grid-column: 1 / 6;
      height: 100%;
    }
    #candle-info, #feature-info {
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

<!-- <form method="get" action="futures_chart_BB.php" style="padding: 2px; border-bottom: 1px solid #ccc;">
  📅 날짜 선택:
  <input type="date" id="date-picker" value="<?= $date ?>">
  <button type="button" onclick="reloadChart()">조회</button>
  &nbsp;
  <button type="submit" name="date" value="<?= $prev_date ?>">◀ 이전</button>
  <button type="submit" name="date" value="<?= $next_date ?>">다음 ▶</button>
</form> -->
<input type=hidden id="date-picker" value="<?= $date ?>">

  <div id="layout">
  <?php if ($strategy): ?>
  <div id="metric-content">
    <?php
      $dateObj = new DateTime($date);
      $weekday = ['일', '월', '화', '수', '목', '금', '토'];
      $dayOfWeek = $weekday[$dateObj->format('w')];
    ?>
    <b>
      📅
      <a href="#" onclick="openMatchPopup('<?= $date ?>'); return false;" style="text-decoration: none; color: inherit;">
        <?= $date ?> (<?= $dayOfWeek ?>)
      </a>
    </b>
    <hr style="margin:3px 0;">
    🔥 나스닥: <?= colorize($market_index) ?? null ?>% | 🟡 시가: <?= $strategy['open'] ?>pt , <?= colorize($strategy['open_change_pct'] ?? null) ?>% , <?= colorize($strategy['open_change_pt'] ?? null) ?> pt | 종가: <?= $strategy['close'] ?>pt, <?= colorize($strategy['close_change_pct'] ?? null) ?>%, <?= colorize($strategy['close_change_pt'] ?? null) ?> pt&nbsp;&nbsp;&nbsp;
    🟢 순매수 현황 (천만원):
    외국인: <b><?= colorize($strategy['net_foreign'] ?? null) ?>(<?= colorize($strategy['cum_net_foreign'] ?? null) ?>)</b> |
    기관: <b><?= colorize($strategy['net_institution'] ?? null) ?>(<?= colorize($strategy['cum_net_institution'] ?? null) ?>)</b> |
    개인: <b><?= colorize($strategy['net_individual'] ?? null) ?>(<?= colorize($strategy['cum_net_individual'] ?? null) ?>)</b> |
    거래량: <?= number_format($strategy['volume']) ?? '-' ?>

  </div>
  <?php else: ?>
  <div id="metric-content">
    📅 <b><?= $date ?></b><br>
    <span style="color: #999;">전략 분석 데이터가 없습니다.</span>
  </div>
  <?php endif; ?>

    <div id="chart-5m" class="chart-box"></div>
    <div id="chart-15m" class="chart-box"></div>
    <div id="chart-day" class="chart-box"></div>
    <div id="chart-week" class="chart-box"></div>
    <div id="chart-60m" class="chart-box"></div>
    <div id="info-box">
        <div id="candle-info">🖱 캔들을 클릭하면 상세 정보가 여기에 표시됩니다.</div>
        <div id="feature-info">📊 Feature 정보는 분봉 종가만 표시됩니다.</div>
    </div>
  </div>

<script>
function openMatchPopup(date) {
  window.open(
    "futures_snapshot_match.php?base_date=" + date,
    "patternMatchPopup",
    "width=2500,height=1500,scrollbars=yes,resizable=yes"
  );
}

const dateStr = "<?= $date ?>";

// flask 실행
async function startFlask() {
  const res = await fetch('start_flask.php');
  const data = await res.json();
  const statusEl = document.getElementById('flask-status');

  if (data.status === 'already running') {
    statusEl.innerText = 'Flask 서버 이미 실행 중';
  } else if (data.status === 'started') {
    statusEl.innerText = 'Flask 서버 실행됨!';
  } else {
    statusEl.innerText = '실행 실패';
  }
}

function reloadChart() {
  const newDate = document.getElementById('date-picker').value;
  if (!newDate) {
    alert('날짜를 선택해주세요.');
    return;
  }

  // ✅ 선택된 날짜로 페이지 새로 요청
  window.location.href = `futures_chart_BB.php?date=${newDate}`;
}

document.getElementById('date-picker').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') reloadChart();
});

function renderChart(divId, candleData = [], fullData = [], plotLines = [], plotBands = []) {
  const el = document.getElementById(divId);
  if (el.chart) el.chart.destroy();

  if (!Array.isArray(candleData) || candleData.length === 0) {
    el.innerHTML = '<p style="text-align:center;padding-top:40px;color:#888;">No data</p>';
    return;
  }

  el.innerHTML = '';

  const cleanData = candleData.map(row => [
    row[0], parseFloat(row[1]), parseFloat(row[2]), parseFloat(row[3]), parseFloat(row[4])
  ]);

  const parseDatetime = (val) => {
    const t = new Date(val);
    return isNaN(t.getTime()) ? null : t.getTime();
  };

  // ✅ 기본 yAxis 설정
  const yAxis = [{ plotBands }];

  // ✅ 기본 시리즈
  const series = [
    { type: 'candlestick', id: 'price', name: 'Price', data: cleanData },
    { type: 'line', name: 'SMA 5',   data: fullData.map(d => [parseDatetime(d.datetime), d.sma_5]), color: 'rgb(219, 27, 180)', zIndex: 1, lineWidth: 2 },
    { type: 'line', name: 'SMA 20',  data: fullData.map(d => [parseDatetime(d.datetime), d.sma_20]), color: 'rgb(239, 174, 0)', zIndex: 1, lineWidth: 2 },
    { type: 'line', name: 'SMA 120', data: fullData.map(d => [parseDatetime(d.datetime), d.sma_120]), color: 'rgb(77, 77, 77)', zIndex: 1, lineWidth: 2 }
  ];

  // ✅ RSI 값이 존재하면 보조 차트 추가
  const hasRSI = fullData.some(d => d.rsi_14 !== undefined && d.rsi_14 !== null);

  if (hasRSI) {
    yAxis[0].height = '80%';
    yAxis.push({
      // title: { text: 'RSI(14)' },
      top: '80%',
      height: '20%',
      offset: 0,
      lineWidth: 1,
      min: 0,
      max: 100,
      plotLines: [
        { value: 70, color: 'red', dashStyle: 'Dash', width: 1 },
        { value: 30, color: 'blue', dashStyle: 'Dash', width: 1 }
      ],
      plotBands: [
        {
          from: 70,
          to: 100,
          color: 'rgba(255, 0, 0, 0.15)',  // 🔴 Overbought zone
          label: {
            // text: 'Overbought',
            style: { color: 'red' }
          }
        },
        {
          from: 0,
          to: 30,
          color: 'rgba(0, 0, 255, 0.15)',  // 🔵 Oversold zone
          label: {
            // text: 'Oversold',
            style: { color: 'blue' }
          }
        }
      ]
    });

    const rsiSeries = fullData
      .map(d => {
        const t = parseDatetime(d.datetime);
        const rsi = parseFloat(d.rsi_14);
        return t !== null && !isNaN(rsi) ? [t, rsi] : null;
      })
      .filter(item => item !== null);

    series.push({
      type: 'line',
      name: 'RSI(14)',
      yAxis: 1,
      data: rsiSeries,
      color: '#FF6600', // 주황색 강조
      tooltip: { valueDecimals: 2 },
      lineWidth: 1.5,
      dashStyle: 'Solid'
    });
  }

  el.chart = Highcharts.stockChart(el, {
    chart: { height: el.clientHeight },
    rangeSelector: { enabled: false },
    navigator: { enabled: false },
    scrollbar: { enabled: false },
    title: { text: '' },
    xAxis: { type: 'datetime', plotLines },
    yAxis,
    tooltip: {
      split: false,
      shared: true,
      useHTML: true,
      valueDecimals: 2,
      formatter: function () {
        const point = this.points ? this.points[0].point : this.point;
        const date = Highcharts.dateFormat('%Y-%m-%d %H:%M', point.x);
        return `
          <b>${date}</b>
          O: <b>${point.open}</b>
          H: <b>${point.high}</b>
          L: <b>${point.low}</b>
          C: <b>${point.close}</b>
        `;
      },
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
    series
  });
}

// async function fetchCandle(date, tf) {
//   const res = await fetch(`/modules/futures/get_candles.php?date=${date}&tf=${tf}`);
//   return await res.json();
// }

async function fetchCandle(date, tf) {
  const sma = '5,20,120';  // 원하는 이평선 주기
  let url = `http://localhost:5000/api/candles?date=${date}&tf=${tf}&sma=${sma}`;

  // ✅ 1m일 때만 시간 필터 추가
  if (tf === '1m') {
    // url += `&from_time=08:45:00&to_time=11:30:00`;
    url += `&from_time=08:45:00&to_time=15:45:00`;
  }

  const res = await fetch(url);
  const raw = await res.json();

  // API 데이터를 기존 구조로 변환
  const candles = raw.map(row => [
    new Date(row.datetime).getTime(),
    parseFloat(row.open),
    parseFloat(row.high),
    parseFloat(row.low),
    parseFloat(row.close)
  ]);

  return {
    candles,
    data: raw  // SMA 포함된 원본 row들 유지
  };
}

async function loadCharts(dateStr) {
  const [r1w, r1d, r5, r15, r60, r1m] = await Promise.all([
    fetchCandle(dateStr, '1week'),
    fetchCandle(dateStr, '1day'),
    fetchCandle(dateStr, '5m'),
    fetchCandle(dateStr, '15m'),
    fetchCandle(dateStr, '60m'),
    fetchCandle(dateStr, '1m')
  ]);

  const r5Data = r5.candles || [];
  const r1mData = r1m.candles || [];
  // console.log(r1mData[0]);
  const plotLines5m = r5Data.length >= 3 ? [{ color: 'lime', width: 6, value: r5Data[2][0] }] : [];
  const plotLines1m = r1mData.length >= 16 ? [{ color: 'yellow', width: 6, value: r1mData[15][0] }] : [];

  // ✅ 첫 15분봉 시가/종가 plotBand 생성
  const yAxisPlotBands = [];
  const first15m = r15.data?.[0];
  if (first15m) {
    const open = parseFloat(first15m.open);
    const close = parseFloat(first15m.close);
    const from = Math.min(open, close);
    const to = Math.max(open, close);
    const diff = Math.abs(open - close).toFixed(2);  // ✅ 포인트 갭
    const volume = Number(first15m.volume).toLocaleString();  // ✅ 거래량 (거래대금)

    yAxisPlotBands.push({
      color: 'rgba(255, 215, 0, 0.2)',  // 노란색 투명
      // color: 'rgba(255, 17, 0, 0.89)',  // 라인표시될 경우 빨강색
      from: from,
      to: to,
      label: {
        text: `15분봉 (${from}~${to}) | 시종갭: ${diff}pt | 거래량: ${volume}`,
        align: 'left',
        x: -10,
        style: { color: '#333', fontWeight: 'bold' }
      }
    });
  }

  renderChart('chart-week', r1w.candles || [], r1w.data || []);
  renderChart('chart-day', r1d.candles || [], r1d.data || []);
  renderChart('chart-5m',  r5Data, r5.data || [], plotLines5m, yAxisPlotBands);
  renderChart('chart-15m', r15.candles || [], r15.data || []);
  renderChart('chart-1m',  r1mData, r1m.data || [], plotLines1m, yAxisPlotBands);


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
}

loadCharts(dateStr);


// BB-RSI-EMA 차트 보여주기
const featureMap = <?= json_encode($features) ?>;

function showCandleInfo(chart, ts, o, h, l, c) {
    const getVal = (name) => {
    const s = chart.series.find(s => s.name === name);
    const p = s?.data.find(p => p.x === ts);
    return p?.y ?? '-';
    };
    const macd = macdData.find(m => m.ts === ts);
    const info = `
    <div><b>${Highcharts.dateFormat('%Y-%m-%d', ts)}</b></div>
    <div>
        <b>시가</b>: ${o}<br>
        <b>고가</b>: ${h}
    </div>
    <div>
        <b>저가</b>: ${l}<br>
        <b>종가</b>: ${c}
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
    document.getElementById('candle-info').innerHTML = info;
}

function showFeatureInfo(ts) {
    const dateStr = Highcharts.dateFormat('%Y-%m-%d', ts);
    const f = featureMap[dateStr];
    if (!f) {
    document.getElementById('feature-info').innerHTML = "<i>해당 날짜의 feature 데이터가 없습니다.</i>";
    return;
    }

    const line = (...args) => args.map(([k, v]) => `<b>${k}</b>: ${v}`).join('&nbsp;&nbsp;&nbsp;&nbsp;');

    const info = `
    <div><b>🧬 Feature (${dateStr})</b></div>
    <div>
        <b>candle_type</b>    : ${f.candle_type}
    </div>
    <div>
        <b>body_pct</b>       : ${f.body_pct}<br>
        <b>lower_tail_pct</b> : ${f.lower_tail_pct}
    </div>
    <div>
        <b>bb_position</b>    : ${f.bb_position}<br>
        <b>bb_width</b>       : ${f.bb_width}
    </div>
    <div>
        <b>ema_gap</b>        : ${f.ema_gap}<br>
        <b>ema_bb_gap</b>     : ${f.ema_bb_gap}
    </div>
    <div>
        <b>index_vs_ema20</b> : ${f.index_vs_ema20}<br>
        <b>index_vs_ema60</b> : ${f.index_vs_ema60}
    </div>
    <div>
        <b>macd</b>             : ${f.macd}<br>
        <b>macd_hist</b>        : ${f.macd_hist}<br>
        <b>macd_hist_change</b> : ${f.macd_hist_change}
    </div>
    `;

    document.getElementById('feature-info').innerHTML = info;
}


const macdData = <?= json_encode($macd) ?>;
const macdHist = <?= json_encode($macdHist) ?>;

Highcharts.stockChart('chart-60m', {
    tooltip: { enabled: false },
    chart: { zoomType: 'x' ,
    events: {
        load: function () {
        // 차트 로딩 직후 실행할 코드
        const chart = this;
        const series = chart.series.find(s => s.name === 'Price');
        if (series) {
            const points = series.data.filter(p => {
            const d = new Date(p.x);
            return Highcharts.dateFormat('%Y-%m-%d', p.x) === '<?= $date ?>';
            });
            const last = points.at(-1);
            if (last) {
            showCandleInfo(chart, last.x, last.open, last.high, last.low, last.close);
            showFeatureInfo(last.x);
            }
        }
        }
    }
    },
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
            const chart = this.series.chart;
            showCandleInfo(chart, this.x, this.open, this.high, this.low, this.close);
            showFeatureInfo(this.x);
            }
        }
        }
    }
    },
    yAxis: [
    {
        title: { text: 'Price', style: { color: '#2f7ed8' } },
        height: '65%',
        lineWidth: 2,
        gridLineWidth: 1,
        gridLineColor: '#cccccc'
    },
    {
        title: { text: 'Volume', style: { color: '#333' } },
        top: '65%',
        height: '10%',
        offset: 0,
        lineWidth: 2,
        gridLineWidth: 1,
        gridLineColor: '#999999'
    },
    {
        title: { text: 'RSI', style: { color: '#ff5733' } },
        top: '75%',
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
        top: '90%',
        height: '10%',
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
        upColor: '#f45b5b',
        zIndex: 5 // 캔들의 zIndex를 높게 설정
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
