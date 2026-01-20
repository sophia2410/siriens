<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php"; // $mysqli 제공

$date = $_GET['date'] ?? '';
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

// 선물 일봉 테이블에서 전략정보 구해오기
$sql = "
    SELECT
        f1.*,
        ROUND((f1.open - f2.close) / f2.close * 100, 2) AS open_change_pct,
        ROUND((f1.close - f2.close) / f2.close * 100, 2) AS close_change_pct,
        ROUND((f1.high - f2.close) / f2.close * 100, 2) AS high_change_pct,
        ROUND((f1.low - f2.close) / f2.close * 100, 2) AS low_change_pct,
        f1.open - f2.close AS open_change_pt,
        f1.close - f2.close AS close_change_pt,
        f1.high - f2.close AS high_change_pt,
        f1.low - f2.close AS low_change_pt,
        fa.gap_percent,
        fa.gap_type,
        fa.pattern_0845_0859,
        fa.tick_range_0845_0859,
        fa.vol_0845_0859,
        fa.candle_0900_dir,
        fa.diff_0900_pt,
        fa.tick_range_0900,
        fa.match_last5_and_0900,
        fa.match_last5_and_0901,
        fa.is_morning_breakout,
        fa.entry_direction_a,
        fa.open_0845_0859,
        fa.close_0845_0859,
        fa.diff_0845_0859_pt,
        fa.created_at,
        r5.high_5bd,
        r5.low_5bd
    FROM futures_1day f1
    LEFT JOIN futures_1day f2
        ON f2.date = (
            SELECT MAX(date)
            FROM futures_1day
            WHERE date < f1.date
        )
    LEFT JOIN futures_analysis fa ON f1.date = fa.date
    LEFT JOIN (
      SELECT
          MAX(fx.high) AS high_5bd,
          MIN(fx.low)  AS low_5bd
      FROM futures_1day fx
      JOIN (
          SELECT date
          FROM calendar
          WHERE date <= ?
          ORDER BY date DESC
          LIMIT 5
      ) l ON l.date = fx.date
    ) r5 ON 1=1
    WHERE f1.date = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ss", $date, $date);
$stmt->execute();
$result = $stmt->get_result();
$strategy = $result->fetch_assoc();

// morning report title
$sql = "
    SELECT morning_report_title
    FROM market_report
    WHERE date = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$stmt->bind_result($morning_report);
$stmt->fetch();
$stmt->close();

// evening report title
$sql = "
    SELECT evening_report_title
    FROM market_report
    WHERE date = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$stmt->bind_result($evening_report);
$stmt->fetch();
$stmt->close();

// Fetch KOSPI and KOSDAQ index data
$index_query = "
    SELECT close_rate
    FROM market_index
    WHERE market_fg = 'NASDAQ'
    AND date = ?
";
$stmt = $mysqli->prepare($index_query);
$stmt->bind_param("s", $prev_date);
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

// 스냅샷 데이터 조회하기
$sql = "SELECT *
        FROM futures_snapshot_momentum fsm
        WHERE date = ? ORDER BY datetime ASC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

// 불필요한 항목 정리 필요 250509
$labels = [];
$prices = [];
$volumes = [];
$sma1_5 = [];
$sma1_20 = [];
$sma1_120 = [];
$sma5_5 = [];
$sma5_20 = [];
$sma5_120 = [];
$sma10_5 = [];
$sma10_20 = [];
$sma10_120 = [];
$sma15_5 = [];
$sma15_20 = [];
$sma15_120 = [];
$sma60_5 = [];
$sma60_20 = [];
$sma60_120 = [];

// 강제로 특정 분까지 데이터 조회 위한 하드코딩
// $minute = "08:50";
// if ($minute) {
//   $rows = array_filter($rows, function($row) use ($minute) {
//     return substr($row['time'], 0, 5) <= $minute;
//   });
// }

foreach ($rows as $row) {
  $labels[] = substr($row['time'], 0, 5);
  $prices[] = $row['price'];
  $volumes[] = $row['volume'];

  $sma1_5[]           = $row['sma_1min_5'];
  $sma1_20[]          = $row['sma_1min_20'];
  $sma1_120[]         = $row['sma_1min_120'];
  $sma5_5[]           = $row['sma_5min_5'];
  $sma5_20[]          = $row['sma_5min_20'];
  $sma5_120[]         = $row['sma_5min_120'];
  $sma10_5[]          = $row['sma_10min_5'];
  $sma10_20[]         = $row['sma_10min_20'];
  $sma10_120[]        = $row['sma_10min_120'];
  $sma15_5[]          = $row['sma_15min_5'];
  $sma15_20[]         = $row['sma_15min_20'];
  $sma15_120[]        = $row['sma_15min_120'];
  $sma60_5[]          = $row['sma_60min_5'];
  $sma60_20[]         = $row['sma_60min_20'];
  $sma60_120[]        = $row['sma_60min_120'];
}

$chart_data = [
  'labels' => $labels,
  'prices' => $prices,
  'sma1_5' => $sma1_5,
  'sma1_20' => $sma1_20,
  'sma1_120' => $sma1_120,
  'sma5_5' => $sma5_5,
  'sma5_20' => $sma5_20,
  'sma10_5' => $sma10_5,
  'sma15_5' => $sma15_5,
  'sma60_5' => $sma60_5,
  'volume' => $volumes
];

// 필요한 컬럼들에서 값 추출
$values = [];
foreach ($rows as $row) {
    foreach (['sma_5min_5', 'sma_5min_20', 'sma_1min_5', 'sma_1min_20', 'sma_1min_120'] as $key) {
        if (!empty($row[$key]) && is_numeric($row[$key])) {
            $values[] = floatval($row[$key]);
        }
    }
}

// y축 범위 계산 (데이터가 없으면 기본값 설정)
if (count($values) > 0) {
    $min = min($values);
    $max = max($values);
    $range = $max - $min;

    if ($range < 10) {
        // 범위가 너무 좁으면 여유를 줘서 8칸 확보
        $center = ($min + $max) / 2;
        $minY = floor($center - 4);  // 중심 기준으로 좌우 5pt
        $maxY = ceil($center + 4);
    } else {
        $minY = floor($min);
        $maxY = ceil($max);
    }
} else {
    $minY = 0;
    $maxY = 10;
}

// JavaScript로 넘기기
$y_axis_config = ['min' => $minY, 'max' => $maxY];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title><?= $date ?> 전략 차트</title>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
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
      grid-template-columns: 6fr 3fr 2fr 3fr 3fr;
      grid-template-rows: 100px 300px 300px auto;
      gap: 2px;
      padding: 2px;
      box-sizing: border-box;
      height: 95vh;
      overflow-x: hidden; /* 👈 가로 스크롤 제거 */
    }
    #metric-content {
      grid-column: 1 / 18;
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
    #chart-1m, #snapshotChart {
      grid-column: 1 / 18;
      height: 100%;
    }
  </style>
</head>


<form method="get" action="futures_chart.php" style="padding: 2px; border-bottom: 1px solid #ccc;">
  📅 날짜 선택:
  <input type="date" id="date-picker" value="<?= $date ?>">
  <button type="button" onclick="reloadChart()">조회</button>
  &nbsp;
  <button type="submit" name="date" value="<?= $prev_date ?>">◀ 이전</button>
  <button type="submit" name="date" value="<?= $next_date ?>">다음 ▶</button>
</form>
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
    <!-- 🔸 <b><?= $strategy['pattern_0845_0859'] ?? '-' ?> ( 갭: <?= $strategy['gap_percent'] ?? '-' ?>% )</b><br> -->
    <!-- 🔁 일치: 09:00 <?= $strategy['match_last5_and_0900'] ? '✔' : '✘' ?><?= ' ('.$strategy['diff_0900_pt'] ?? '-' ?>pt, <?= $strategy['tick_range_0900'] ?? '-' ?>pt) , 09:01 <?= $strategy['match_last5_and_0901'] ? '✔' : '✘' ?><br> -->
    <!-- 🟡 첫 15분봉: <b><?= $strategy['open_0845_0859'] ?? '-' ?> ~ <?= $strategy['close_0845_0859'] ?? '-' ?></b>  | 시종갭: <?= $strategy['diff_0845_0859_pt'] ?>pt<br> -->

    🔥 나스닥: <?= colorize($market_index) ?? null ?>% | Morning Report: <?= $morning_report ?? null ?> / 🔥Evening Report: <?= $evening_report ?? null ?> <br>
    🟡 시가: <?= $strategy['open'] ?>pt , <?= colorize($strategy['open_change_pct'] ?? null) ?>% , <?= colorize($strategy['open_change_pt'] ?? null) ?> pt | 고가: <?= $strategy['high'] ?>pt, <?= colorize($strategy['high_change_pct'] ?? null) ?>%, <?= colorize($strategy['high_change_pt'] ?? null) ?> pt | 저가: <?= $strategy['low'] ?>pt, <?= colorize($strategy['low_change_pct'] ?? null) ?>%, <?= colorize($strategy['low_change_pt'] ?? null) ?> pt | 종가: <?= $strategy['close'] ?>pt, <?= colorize($strategy['close_change_pct'] ?? null) ?>%, <?= colorize($strategy['close_change_pt'] ?? null) ?> pt | 변동성: <?= $strategy['high']-$strategy['low'] ?>pt | 5일 최고: <?= $strategy['high_5bd'] ?>pt | 5일 최저: <?= $strategy['low_5bd'] ?>pt | 5일 변동성: <?= $strategy['high_5bd']-$strategy['low_5bd'] ?>pt
    <br>
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
    <div id="chart-60m" class="chart-box"></div>
    <div id="chart-day" class="chart-box"></div>
    <div id="chart-week" class="chart-box"></div>

    <div id="chart-1m" class="chart-box"></div>

    <div id="snapshotChart">
      <canvas id="lineChart" height="55"></canvas>
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
  window.location.href = `futures_chart.php?date=${newDate}`;
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
  renderChart('chart-60m', r60.candles || [], r60.data || []);
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


const data = <?= json_encode($chart_data) ?>;
const yAxisConfig = <?= json_encode($y_axis_config) ?>;

new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: data.labels,
    datasets: [
      {
       label: '종가',
       data: data.prices,
       borderColor: 'gray',
       pointRadius: 0,
       fill: false,
       yAxisID: 'y1',
       tension: 0.2
      },
      {
        label: '5분 5이평',
        data: data.sma5_5,
        borderColor: 'red',
        borderDash: [2, 2],
        pointRadius: 0,
        fill: false,
        yAxisID: 'y1',
        tension: 0.2
      },
      {
        label: '5분 20이평',
        data: data.sma5_20,
        borderColor: 'orange',
        borderDash: [2, 2],
        pointRadius: 0,
        fill: false,
        yAxisID: 'y1',
        tension: 0.2
      },
      {
        label: '1분 5이평',
        data: data.sma1_5,
        borderColor: '#8A2BE2', // BlueViolet
        borderDash: [10, 10],
        borderWidth: 1,
        pointRadius: 0,
        fill: false,
        yAxisID: 'y1',
        tension: 0.2
      },
      {
        label: '1분 20이평',
        data: data.sma1_20,
        borderColor: '#228B22', // ForestGreen
        borderDash: [10, 10],
        borderWidth: 1,
        pointRadius: 0,
        fill: false,
        yAxisID: 'y1',
        tension: 0.2
      },
      {
        label: '1분 120이평',
        data: data.sma1_120,
        borderColor: '#A9A9A9', // DarkGray
        borderDash: [10, 10],
        pointRadius: 0,
        fill: false,
        yAxisID: 'y1',
        tension: 0.2
      },
      {
        type: 'bar',
        label: '거래량',
        data: data.volume,
        backgroundColor: 'rgba(200, 200, 200, 0.4)',
        yAxisID: 'y2'
      }
    ]
  },
  options: {
    scales: {
      y1: {
        type: 'linear',
        position: 'left',
        title: { display: true, text: '가격 (pt)' },
        grid: {
          drawOnChartArea: true,   // ✅ 가로선 표시
          color: 'rgba(0, 0, 0, 0.2)'  // 🔍 가로줄 색(연하게)
        },
        min: yAxisConfig.min,
        max: yAxisConfig.max,
        ticks: {
          stepSize: 1,  // ✅ 1pt 간격으로 눈금 및 가로줄
          callback: function(value) {
            return value.toFixed(2);  // 소수 둘째자리까지 표시
          }
        }
      },
      y2: {
        type: 'linear',
        position: 'right',
        title: { display: true, text: '거래량' },
        grid: {
          drawOnChartArea: false // ✅ 가로줄 제거!
        }
      }
    },
    plugins: {
      legend: { position: 'top' },
      title: { display: false, text: '종가 + 이평선 + 거래량' },
      tooltip: {
        mode: 'index',     // ✅ x축 기준으로 모든 dataset 툴팁 표시
        intersect: false   // ✅ 포인터가 바를 찍지 않아도 툴팁 활성화
      }
    }
  }
});
</script>
</body>
</html>
