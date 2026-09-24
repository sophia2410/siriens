<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
session_start();

// POST 우선, 없으면 GET, 없으면 기본값
function p($name, $default=null) {
  if (isset($_POST[$name])) return $_POST[$name];
  if (isset($_GET[$name]))  return $_GET[$name];
  return $default;
}

// MD 저장 API 호환용 값이며 조회 조건에는 사용하지 않는다.
$rsi_from = 0;
$rsi_to   = 100;
$gap_from = p('gap_from', 100);
$gap_to   = p('gap_to', 100);

$filter_interval = p('filter_interval', '1m');
$chart_interval  = p('chart_interval', '1m5m');
$run_id          = max(0, (int)p('run_id', 0));

$bar_index = max(1, (int) p('bar_index', 1));

$candle_dir        = p('candle_dir', 'all');
$candle_size_from  = p('candle_size_from', '');
$candle_size_to    = p('candle_size_to', '');
$candle_range_from = p('candle_range_from', '');
$candle_range_to   = p('candle_range_to', '');

$m15_combo = p('m15_combo', '');
$m15_patterns = [
  '양양'   => [1, 1],
  '양음'   => [1, 0],
  '음음'   => [0, 0],
  '음양'   => [0, 1],
  '양양양' => [1, 1, 1],
  '양양음' => [1, 1, 0],
  '음음양' => [0, 0, 1],
  '음음음' => [0, 0, 0],
];
if (!is_string($m15_combo) || !isset($m15_patterns[$m15_combo])) $m15_combo = '';
$bar_preset  = p('bar_preset', '1m200_5m83');

$show_sma_1m = (int)p('show_sma_1m', 1); // 기본 ON
$show_sma_5m = (int)p('show_sma_5m', 1); // 기본 ON
$show_bs     = (int)p('show_bs', 1);     // 기본 ON

// ✅ 특정일자/월: 2026-07-15 또는 2026-07 형식, 줄바꿈/스페이스/콤마 허용
$only_dates_raw = trim((string)p('only_dates', ''));
$only_dates_list = [];
$only_months_list = [];
if ($only_dates_raw !== '') {
  $tokens = preg_split('/[,\s]+/u', $only_dates_raw, -1, PREG_SPLIT_NO_EMPTY);
  foreach ($tokens as $token) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $token)) {
      $only_dates_list[] = $token;
    } elseif (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $token)) {
      $only_months_list[] = $token;
    }
  }
  $only_dates_list = array_values(array_unique($only_dates_list));
  $only_months_list = array_values(array_unique($only_months_list));
}

// 분봉 테이블 매핑 (조건기준에 맞춤)
$cond_table =
  $filter_interval === '5m'   ? 'futures_5min'  :
  ($filter_interval === '15m' ? 'futures_15min' :
  ($filter_interval === '60m' ? 'futures_60min' : 'futures_1min'));

// 날짜별 N번째 봉의 방향/변동/크기 계산
$joinSql = "
JOIN (
  SELECT d, up, ret, rng
  FROM (
    SELECT
      DATE(datetime)                 AS d,
      (close > open)                 AS up,
      (close - open)                 AS ret,
      (high - low)                   AS rng,
      ROW_NUMBER() OVER (
        PARTITION BY DATE(datetime)
        ORDER BY datetime ASC
      ) AS rn
    FROM {$cond_table}
  ) z
  WHERE z.rn = ?
) f ON f.d = rule_based_rowdata.date
";

$sql = "
  SELECT date, ROUND(prev_rsi14,1) AS prev_rsi14, gap_pt, f.ret AS ret
  FROM rule_based_rowdata
  {$joinSql}
  WHERE gap_pt BETWEEN ? AND ?
";

$types  = 'idd';
$params = [$bar_index, $gap_from, $gap_to];

if ($candle_dir !== 'all') {
  $sql   .= " AND f.up = ?";
  $types .= 'i';
  $params[] = ($candle_dir === 'up') ? 1 : 0;
}
if ($candle_size_from !== '') {
  $sql   .= " AND f.ret >= ?";
  $types .= 'd';
  $params[] = $candle_size_from;
}
if ($candle_size_to !== '') {
  $sql   .= " AND f.ret <= ?";
  $types .= 'd';
  $params[] = $candle_size_to;
}
if ($candle_range_from !== '') {
  $sql   .= " AND f.rng >= ?";
  $types .= 'd';
  $params[] = $candle_range_from;
}
if ($candle_range_to !== '') {
  $sql   .= " AND f.rng <= ?";
  $types .= 'd';
  $params[] = $candle_range_to;
}

// 당일 첫 2개 또는 3개의 15분봉 패턴. 도지는 음(0)에 포함한다.
if ($m15_combo !== '') {
  $pattern = $m15_patterns[$m15_combo];
  $bar_count = count($pattern);
  $having = [];

  foreach ($pattern as $index => $up) {
    $rn = $index + 1;
    $having[] = "MAX(CASE WHEN c15.rn = {$rn} THEN c15.up END) = ?";
    $types .= 'i';
    $params[] = $up;
  }

  $sql .= " AND rule_based_rowdata.date IN (
    SELECT c15.d
    FROM (
      SELECT DATE(datetime) AS d,
             (close > open) AS up,
             ROW_NUMBER() OVER (
               PARTITION BY DATE(datetime)
               ORDER BY datetime ASC
             ) AS rn
      FROM futures_15min
    ) c15
    WHERE c15.rn <= {$bar_count}
    GROUP BY c15.d
    HAVING " . implode(' AND ', $having) . "
  )";
}

// 특정 일자(IN)와 월 범위를 하나의 OR 조건으로 조회
if (!empty($only_dates_list) || !empty($only_months_list)) {
  $dateParts = [];

  if (!empty($only_dates_list)) {
    $dateParts[] = "date IN (" . implode(',', array_fill(0, count($only_dates_list), '?')) . ")";
    $types .= str_repeat('s', count($only_dates_list));
    $params = array_merge($params, $only_dates_list);
  }

  foreach ($only_months_list as $month) {
    $monthStart = $month . '-01';
    $nextMonth = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');
    $dateParts[] = "(date >= ? AND date < ?)";
    $types .= 'ss';
    $params[] = $monthStart;
    $params[] = $nextMonth;
  }

  $sql .= " AND (" . implode(' OR ', $dateParts) . ")";
}

// 선택 회차에 손익이 등록된 날짜만 표시
if ($run_id > 0) {
  $sql .= " AND EXISTS (
              SELECT 1
              FROM futures_sim_run_day rd
              WHERE rd.run_id = ?
                AND rd.trade_date = rule_based_rowdata.date
                AND rd.pnl_points <> 0
            )";
  $types .= 'i';
  $params[] = $run_id;
}

$sql .= " AND date >= '2025-01-01'";
// $sql .= " ORDER BY date DESC";
$sql .= " ORDER BY date";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
while ($row = $result->fetch_assoc()) {
  $dates[] = [
    'date' => $row['date'],
    'rsi'  => $row['prev_rsi14'],
    'gap'  => $row['gap_pt'],
    'ret'  => $row['ret']
  ];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>차트 모아보기(가림막)</title>
  <?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/highcharts.php"; ?>
  <script src="https://code.highcharts.com/modules/exporting.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body { margin:0; padding:10px; font-family:sans-serif; }
    .filter-box { margin-bottom:20px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; }

    /* ▶ 12칸 고정 그리드 + span 가변 */
    .chart-grid { display:grid; grid-template-columns:repeat(12, minmax(0,1fr)); gap:16px; }
    .chart-item { border:1px solid #ccc; padding:8px; background:#fff; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,.05); }
    .chart-item.span-2  { grid-column:span 2; }   /* 1/6 */
    .chart-item.span-3  { grid-column:span 3; }   /* 1/4 */
    .chart-item.span-4  { grid-column:span 4; }   /* 1/3 */
    .chart-item.span-6  { grid-column:span 6; }   /* 1/2 */
    .chart-item.span-8  { grid-column:span 8; }   /* 2/3 */
    .chart-item.span-12 { grid-column:span 12; }  /* 전체 */

    .chart-title { font-weight:bold; margin-bottom:5px; font-size:14px; color:#333; }
    .chart-pair { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .chart-pair.single { grid-template-columns:1fr; }
    .chart-pair.duo-5m1m { grid-template-columns: 1fr 2fr; } /* 5분 : 1분 = 1 : 2 */
    .chart-pair.triple { grid-template-columns: 1fr 1.5fr 2.5fr; }
    .chart-one { display:flex; flex-direction:column; min-width:0; } /* ← 줄바꿈 허용 */
    /* 5분봉 / 15분봉 배경색 */
    .chart-one.bg-5m  { background: rgba(255, 250, 205, 0.8); font-weight:bold;}  /* 연한 노랑 */
    .chart-one.bg-15m { background: rgba(213, 252, 216, 0.94); font-weight:bold;}  /* 연한 녹색 */
    .chart-subtitle { font-size:12px; color:#666; margin:0 0 4px 2px; }
    .chart-canvas { height:var(--chart-h, 350px); min-width:0; }      /* ← 컨테이너 축소 허용 */

    /* 09:15 이후를 가리는 날짜별 연습용 가림막 */
    .prediction-controls {
      display:inline-flex;
      align-items:center;
      gap:3px;
      margin-left:10px;
      vertical-align:middle;
    }
    .prediction-btn {
      padding:2px 8px;
      border:1px solid #aaa;
      border-radius:4px;
      background:#f7f7f7;
      color:#333;
      font-size:11px;
      cursor:pointer;
    }
    .prediction-btn:hover { background:#e9ecef; }
    .prediction-current {
      min-width:42px;
      color:#17795e;
      font-size:11px;
      font-weight:bold;
      text-align:center;
    }
    .prediction-mask {
      position:absolute;
      display:none;
      background:#fff;
      border-left:2px solid rgba(75, 192, 160, .85);
      box-sizing:border-box;
      z-index:50;
      pointer-events:auto;
    }
    .prediction-mask.active { display:block; }

    /* 인쇄 전용 – "화면과 별개"로 더 넓게 보이게 */
    @media print {
      @page { size: A4 portrait; margin: 10mm; }

      /* 인쇄는 8칸 그리드로: 화면보다 ‘칸 수 적게’ → 카드가 자연히 넓어짐 */
      .chart-grid {
          display: grid !important;
          grid-template-columns: repeat(8, minmax(0, 1fr)) !important;
          gap: 3mm !important;
      }

      /* 인쇄 전용 폭 클래스 */
      .chart-item { grid-column: span 8 !important; }   /* 기본값=가득(한 줄 1개) */
      .chart-item.pspan-2 { grid-column: span 2 !important; }  /* 한 줄 4 */
      .chart-item.pspan-4  { grid-column: span 4 !important; }  /* 한 줄 2개 */
      .chart-item.pspan-6  { grid-column: span 6 !important; }  /* 한 줄 1~2개 혼합 */
      .chart-item.pspan-8  { grid-column: span 8 !important; }  /* 한 줄 1개(가득) */

      /* 1m·5m는 가로 유지 */
      .chart-pair { grid-template-columns: 1fr 1fr !important; gap: 2mm !important; }
      .chart-pair.single { grid-template-columns: 1fr !important; }
      .chart-pair.duo-5m1m { grid-template-columns: 1fr 2fr !important; }
      .chart-pair.triple { grid-template-columns: 1fr 1fr 1fr !important; }

      /* 잘림 방지 & 색상 */
      .chart-one, .chart-canvas { min-width: 0; }
      .highcharts-container { overflow: visible !important; width: 100% !important; }
      .highcharts-root { width: 100% !important; }
      .chart-item, .chart-pair, .chart-one, .chart-canvas,
      .chart-title, .chart-item [id^="chart-"], .chart-item .highcharts-container,
      .chart-item .highcharts-root, .chart-item svg { break-inside: avoid-page; page-break-inside: avoid; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .chart-canvas { height: 175px !important; } /* 필요 시 조절 */
      .chart-title { margin: 0 0 2mm !important; font-size: 10px !important; color: #000 !important; }

      /* 필터/툴바 숨기기 */
      .filter-box, #filterForm, button, .highcharts-range-selector, .highcharts-exporting-group {
          display: none !important;
      }
    }
  </style>
</head>
<body>
  <form method="post" accept-charset="utf-8" class="filter-box" id="filterForm">
    갭(pt):
    <input type="number" step="0.1" name="gap_from" value="<?= htmlspecialchars($gap_from, ENT_QUOTES) ?>" style="width:40px">~
    <input type="number" step="0.1" name="gap_to"   value="<?= htmlspecialchars($gap_to,   ENT_QUOTES) ?>" style="width:40px">

    표시차트:
    <select name="chart_interval" id="chart_interval">
      <option value="1m"     <?= $chart_interval==='1m'     ? 'selected' : '' ?>>1m</option>
      <option value="5m"     <?= $chart_interval==='5m'     ? 'selected' : '' ?>>5m</option>
      <option value="15m"    <?= $chart_interval==='15m'    ? 'selected' : '' ?>>15m</option>
      <option value="60m"    <?= $chart_interval==='60m'    ? 'selected' : '' ?>>60m</option>
      <!-- (표시는 60,15,5,1 순으로 배치되도록 JS에서 제어) -->
      <option value="1m5m"     <?= $chart_interval==='1m5m'     ? 'selected' : '' ?>>5m+1m</option>
      <option value="5m15m"    <?= $chart_interval==='5m15m'    ? 'selected' : '' ?>>15m+5m</option>
      <option value="1m5m15m"  <?= $chart_interval==='1m5m15m'  ? 'selected' : '' ?>>15m+5m+1m</option>
      <option value="5m15m60m" <?= $chart_interval==='5m15m60m' ? 'selected' : '' ?>>60m+15m+5m</option>
    </select>

    캔들 수:
    <select name="bar_preset" id="bar_preset">
      <option value="1m200_5m83" <?= $bar_preset==='1m200_5m83' ? 'selected' : '' ?>>1분 200 / 5분 83</option>
      <option value="40"       <?= $bar_preset==='40'       ? 'selected' : '' ?>>전체 40</option>
      <option value="83"       <?= $bar_preset==='83'       ? 'selected' : '' ?>>전체 83</option>
      <option value="100"      <?= $bar_preset==='100'      ? 'selected' : '' ?>>전체 100</option>
      <option value="160"      <?= $bar_preset==='160'      ? 'selected' : '' ?>>전체 160</option>
      <option value="240"      <?= $bar_preset==='240'      ? 'selected' : '' ?>>전체 240</option>
      <option value="420"      <?= $bar_preset==='420'      ? 'selected' : '' ?>>전체 420</option>
    </select>

    15분봉 패턴:
    <select name="m15_combo"
            title="당일 첫 2개 또는 3개의 15분봉 기준 · 도지는 음봉에 포함">
      <option value="" <?= $m15_combo === '' ? 'selected' : '' ?>>전체</option>
      <?php foreach ($m15_patterns as $label => $pattern): ?>
        <option value="<?= $label ?>"
                <?= $m15_combo === $label ? 'selected' : '' ?>>
          <?= $label ?>
        </option>
      <?php endforeach; ?>
    </select>

    특정일자:
    <textarea name="only_dates" rows="3" style="width:200px"
      placeholder="2026-07 or 2026-07-15&#10;쉼표/줄바꿈으로 구분"><?= htmlspecialchars($only_dates_raw, ENT_QUOTES) ?></textarea>

    <!-- 1m SMA -->
    <input type="hidden" name="show_sma_1m" value="0">
    <label style="display:flex;align-items:center;gap:6px;">
      <input type="checkbox" name="show_sma_1m" value="1" <?= $show_sma_1m ? 'checked' : '' ?>>
      <font size=2>1mSMA</font>
    </label>

    <!-- 5m SMA -->
    <input type="hidden" name="show_sma_5m" value="0">
    <label style="display:flex;align-items:center;gap:6px;">
      <input type="checkbox" name="show_sma_5m" value="1" <?= $show_sma_5m ? 'checked' : '' ?>>
      <font size=2>5mSMA</font>
    </label>

    회차:
    <select id="simRunSelect" name="run_id" style="min-width:180px;">
      <option value="">(회차 선택)</option>
    </select>

    <label style="display:flex;align-items:center;gap:6px;">
      <input type="hidden" name="show_bs" value="0">
      <input type="checkbox" id="showBs" name="show_bs" value="1" <?= $show_bs ? 'checked' : '' ?>>
      B/S 표시
    </label>
    
    [기준:
    <select name="filter_interval" id="filter_interval">
      <option value="1m"  <?= $filter_interval==='1m'  ? 'selected' : '' ?>>1분봉</option>
      <option value="5m"  <?= $filter_interval==='5m'  ? 'selected' : '' ?>>5분봉</option>
      <option value="15m" <?= $filter_interval==='15m' ? 'selected' : '' ?>>15분봉</option>
      <option value="60m" <?= $filter_interval==='60m' ? 'selected' : '' ?>>60분봉</option>
    </select>

    몇 번째봉:
    <input type="number" name="bar_index"
       value="<?= htmlspecialchars($bar_index, ENT_QUOTES) ?>"
       min="1" step="1" style="width:30px">

    양음:
    <select name="candle_dir">
      <option value="all"  <?= $candle_dir==='all'  ? 'selected' : '' ?>>전체</option>
      <option value="up"   <?= $candle_dir==='up'   ? 'selected' : '' ?>>양봉</option>
      <option value="down" <?= $candle_dir==='down' ? 'selected' : '' ?>>음봉</option>
    </select>

    크기:
    <input type="number" name="candle_size_from" value="<?= htmlspecialchars($candle_size_from, ENT_QUOTES) ?>" step="0.01" style="width:30px">~
    <input type="number" name="candle_size_to"   value="<?= htmlspecialchars($candle_size_to,   ENT_QUOTES) ?>" step="0.01" style="width:30px">

    최대변동:
    <input type="number" name="candle_range_from" value="<?= htmlspecialchars($candle_range_from, ENT_QUOTES) ?>" step="0.01" style="width:30px">~
    <input type="number" name="candle_range_to"   value="<?= htmlspecialchars($candle_range_to,   ENT_QUOTES) ?>" step="0.01" style="width:30px">]

    <button type="submit">조회</button>
    <button type="button" id="btnExportMd">MD 내보내기</button>
  </form>

  <div class="chart-grid">
    <?php foreach ($dates as $i => $d): ?>
      <div class="chart-item">
        <div class="chart-title">
          <a href="./futures_strategy_candle_1m5m.php?date=<?= urlencode($d['date']) ?>"
            onclick="return openIntradayPopup(this.href);" style="text-decoration:none;">🗕️</a>
          <?= htmlspecialchars($d['date']) ?> |
          RSI <?= htmlspecialchars($d['rsi']) ?> |
          Gap <?= ($d['gap']>0?'+':'') . htmlspecialchars($d['gap']) ?> pt |
          Ret <?= ($d['ret']>0?'+':'') . htmlspecialchars($d['ret']) ?> pt
          <span class="prediction-controls"
                data-date="<?= htmlspecialchars($d['date'], ENT_QUOTES) ?>">
            <button type="button" class="prediction-btn" onclick="stepPredictionView(this, -5)">◀ 5분</button>
            <button type="button" class="prediction-btn" onclick="stepPredictionView(this, 5)">5분 ▶</button>
            <button type="button" class="prediction-btn" onclick="setPredictionView(this, '09:15')">09:15</button>
            <button type="button" class="prediction-btn" onclick="setPredictionView(this, '09:30')">09:30</button>
            <button type="button" class="prediction-btn" onclick="setPredictionView(this, '10:00')">10:00</button>
            <button type="button" class="prediction-btn" onclick="setPredictionView(this, '10:30')">10:30</button>
            <button type="button" class="prediction-btn" onclick="setPredictionView(this, '11:00')">11:00</button>
            <button type="button" class="prediction-btn" onclick="setPredictionView(this, '13:00')">13:00</button>
            <button type="button" class="prediction-btn" onclick="showPredictionFull(this)">전체</button>
            <span class="prediction-current">09:15</span>
          </span>
        </div>

        <!-- ✅ DOM(칸) 이름을 L/M/R로 고정 -->
        <div class="chart-pair">
          <div class="chart-one">
            <div class="chart-subtitle">L</div>
            <div id="chart-l-<?= $i ?>" class="chart-canvas"></div>
          </div>
          <div class="chart-one">
            <div class="chart-subtitle">M</div>
            <div id="chart-m-<?= $i ?>" class="chart-canvas"></div>
          </div>
          <div class="chart-one">
            <div class="chart-subtitle">R</div>
            <div id="chart-r-<?= $i ?>" class="chart-canvas"></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    const dateList     = <?= json_encode($dates) ?>;
    const intervalSel  = '<?= htmlspecialchars($chart_interval, ENT_QUOTES) ?>';
    const barPreset    = '<?= htmlspecialchars($bar_preset, ENT_QUOTES) ?>';
    const TRADE_API    = './futures_replay_plan_trade_api.php';
    const SERVER_RUN_ID = <?= json_encode($run_id) ?>;
    const VOL_THRESHOLD_1M = 3300;
    const VOL_THRESHOLD_5M = 12000;

    // ✅ 이평선 색상
    const SMA5_COLOR   = '#d32f2f';
    const SMA20_COLOR  = '#f9a825';
    const SMA120_COLOR = '#757575';

    const SHOW_SMA_1M = <?= json_encode((bool)$show_sma_1m) ?>;
    const SHOW_SMA_5M = <?= json_encode((bool)$show_sma_5m) ?>;

    let simRunId = Number(SERVER_RUN_ID || 0);
    let showBS = <?= json_encode((bool)$show_bs) ?>;
    const chartsByDate = new Map();
    const tradesByDate = new Map();
    const predictionCutoffs = new Map(
      dateList.map(d => [d.date, predictionTimeTs(d.date, '09:15')])
    );
    const predictionFullDates = new Set();
    
    window._cache1m = window._cache1m || new Map();              // 1분 캐시

    // 5분 버킷 도우미
    const M5 = 5 * 60 * 1000;

    // 5분 시작 시각으로 내림
    function floorTo5m(ts) { return Math.floor(ts / M5) * M5; }

    // 교차 시각 → 해당 5분 봉 구간 [from, to)
    function bandRangeFor5m(ts) {
      const from = floorTo5m(ts);
      return { from, to: from + M5 };
    }

    // ★ x축에 세로 띠 + 양쪽 경계선 추가
    function addBandWithEdges(xAxis, from, to, fillColor, edgeColor) {
      xAxis.addPlotBand({
        id: `band-${from}-${to}-${fillColor}`,
        from, to, color: fillColor, zIndex: 1
      });
      xAxis.addPlotLine({
        id: `bandl-${from}`, value: from, color: edgeColor,
        width: 1, dashStyle: 'ShortDash', zIndex: 2
      });
      xAxis.addPlotLine({
        id: `bandr-${to}`, value: to, color: edgeColor,
        width: 1, dashStyle: 'ShortDash', zIndex: 2
      });
    }

    // 5분 한 캔들의 가운데 x를 기준으로 띠 범위를 계산
    function bandRangeAlignedToCandle(priceSeries, ts) {
      const M5 = 5 * 60 * 1000;
      const { from } = bandRangeFor5m(ts);
      const x = (priceSeries?.points?.find(p => p.x >= from && p.x < from + M5)?.x)
            ?? from;

      const xs = priceSeries?.xData || [];
      const idx = xs.indexOf(x);
      let half = M5 / 2;
      if (idx >= 0) {
        const prevGap = idx > 0 ? (xs[idx] - xs[idx - 1]) : M5;
        const nextGap = idx < xs.length - 1 ? (xs[idx + 1] - xs[idx]) : prevGap;
        half = Math.min(prevGap, nextGap) / 2;
      }
      return { from: x - half, to: x + half };
    }

    // 조건기준 변경 시 표시기준 자동 동기화
    document.getElementById('filter_interval').addEventListener('change', function () {
      const ci = document.getElementById('chart_interval');
      if (ci.value !== '1m5m' && ci.value !== '5m15m' && ci.value !== '1m5m15m' && ci.value !== '5m15m60m') {
        ci.value = this.value;
      }
    });

    // ── 프리셋 → interval별 캔들 수
    function barsFor(interval) {
      if (barPreset === '1m200_5m83') {
        if (interval === '1m') return 200;
        if (interval === '5m') return 83;
        return 83; // 15m, 60m은 필요 시 기본값
      }
      if (barPreset === '40') return 40;
      if (barPreset === '83') return 83;
      if (barPreset === '100') return 100;
      if (barPreset === '160') return 160;
      if (barPreset === '240') return 240;
      if (barPreset === '420') return 420;
      return 83;
    }

    // === triple(L)에서만 표시 봉 수를 폭에 맞춰 줄이기(과거가 잘리도록 tail 유지) ===
    const MIN_PX_PER_CANDLE = { '1m': 7, '5m': 8, '15m': 9, '60m': 10 };
    const MIN_BARS_BY_INTERVAL = { '1m': 20, '5m': 25, '15m': 25, '60m': 30 };

    function clamp(n, lo, hi) { return Math.max(lo, Math.min(hi, n)); }

    // 컨테이너 폭(대략) → 표시 봉 수 추정 (차트 생성 전 1차)
    function estimateDisplayBarsByContainer(containerEl, interval, capBars) {
      if (!containerEl) return capBars;

      const w = containerEl.getBoundingClientRect().width || 600;

      // yAxis 라벨/여백 등을 대충 보정(너무 공격적이면 숫자만 조정)
      const plotW = Math.max(120, w - 90);

      const minPx = MIN_PX_PER_CANDLE[interval] ?? 8;
      const minBars = MIN_BARS_BY_INTERVAL[interval] ?? 20;

      const n = Math.floor(plotW / minPx);
      return clamp(n, minBars, capBars);
    }

    // 차트 생성 후 실제 plotWidth 기준으로 다시 계산/적용 (리사이즈 대응)
    function applyAdaptiveSlice(chart) {
      if (!chart || !chart._adaptiveSlice) return;

      const meta = chart._adaptiveMeta || {};
      const interval = meta.interval;
      const capBars = meta.capBars;
      if (!interval || !capBars) return;

      const minPx = MIN_PX_PER_CANDLE[interval] ?? 8;
      const minBars = MIN_BARS_BY_INTERVAL[interval] ?? 20;

      const plotW = chart.plotWidth || 600;
      const n = clamp(Math.floor(plotW / minPx), minBars, capBars);

      if (chart._displayBars === n) return;

      chart._displayBars = n;

      const full = chart._fullSeries;
      const targets = chart._sliceTargets || [];
      for (const t of targets) {
        const s = chart.get(t.id);
        const arr = full?.[t.key];
        if (s && Array.isArray(arr)) s.setData(arr.slice(-n), false);
      }
      chart.redraw(false);
      fitYAxisToCandles(chart, chart._priceSeriesId, 0.12);
    }

    // 캔들 수 → 카드 높이(px)
    function computeHeight(n) { if (n <= 20) return 280; if (n <= 40) return 300; if (n > 400) return 500; return 350; }

    // 화면용(span) 매핑은 기존 spanForPreset(sel) 유지
    function spanForPreset(sel) {
      if (barPreset === '1m200_5m83') return 12; // 하루당 한 줄 전체폭

      const isBoth = (sel === '1m5m' || sel === '5m15m');
      const isTriple = (sel === '1m5m15m' || sel === '5m15m60m');

      if (isBoth) {
        if (barPreset === '40')  return 4;
        if (barPreset === '83')  return 6;
        return 12;
      } else if (isTriple) {
        if (barPreset === '40')  return 4;
        return 12;
      } else {
        if (barPreset === '40')  return 2;
        if (barPreset === '83')  return 4;
        if (barPreset === '240')  return 12;
        if (barPreset === '420')  return 12;
        return 6;
      }
    }

    // 인쇄용(pspan) 매핑 – "더 넓게" 보이게 설계
    function printSpanForPreset(sel) {
      if (barPreset === '1m200_5m83') return 8; // 인쇄도 하루당 한 줄

      const isBoth = (sel === '1m5m' || sel === '5m15m' || sel === '1m5m15m' || sel === '5m15m60m');
      if (isBoth) return 4;
      if (barPreset === '420') return 8;
      if (barPreset === '240') return 8;
      if (barPreset === '160') return 8;
      if (barPreset === '100') return 8;
      if (barPreset === '83')  return 8;
      if (barPreset === '40')  return 4;
      return 2;
    }

    function applyCardSpan(cardEl, sel) {
      if (!cardEl) return;
      cardEl.classList.remove('span-2','span-3','span-4','span-6','span-8','span-12');
      cardEl.classList.add(`span-${spanForPreset(sel)}`);

      cardEl.classList.remove('pspan-4','pspan-6','pspan-8');
      cardEl.classList.add(`pspan-${printSpanForPreset(sel)}`);

      scheduleReflows();
    }

    // ── 리플로우 유틸
    function reflowAll() {
      if (!Highcharts || !Highcharts.charts) return;
      Highcharts.charts.forEach(ch => {
        if (!ch) return;
        try { ch.reflow(); } catch(e){}
        if (ch._predictionMasked) {
          try { updatePredictionMask(ch); } catch(e){}
        }
      });
    }
    function scheduleReflows() {
      reflowAll();
      setTimeout(reflowAll, 0);
      setTimeout(reflowAll, 100);
      setTimeout(reflowAll, 300);
    }

    // ResizeObservers
    const chartResizeObserver = new ResizeObserver(entries => {
      for (const entry of entries) {
        const el = entry.target, chart = el && el._chart;
        if (chart) {
          try { chart.reflow(); } catch(e){}
          if (chart._predictionMasked) {
            setTimeout(() => { try { updatePredictionMask(chart); } catch(e){} }, 0);
          }
          if (chart._adaptiveSlice) {
            setTimeout(() => { try { applyAdaptiveSlice(chart); } catch(e){} }, 0);
          }
        }
      }
    });
    const cardResizeObserver = new ResizeObserver(() => { scheduleReflows(); });

    // === 초반 고/저 수평선(4봉 + 12봉) 유틸 ===
    function clearEarlyLines(chart) {
      if (!chart || !chart.yAxis || !chart.yAxis[0]) return;
      const yAxis = chart.yAxis[0];
      ['hi30_main','lo30_main','hi60_aux','lo60_aux','mid60_aux'].forEach(id => {
        try { yAxis.removePlotLine(id); } catch (e) {}
      });
    }

    function drawEarlyLines(chart, baseRow, dayStr, interval) {
      if (!chart || !chart.yAxis || !chart.yAxis[0] || !baseRow) return;

      const h30 = baseRow.session_high30;
      const l30 = baseRow.session_low30;
      const m20 = (h30 != null && l30 != null) ? ((+h30 + +l30) / 2) : null;
      const h60 = baseRow.session_high60;
      const l60 = baseRow.session_low60;
      const m60 = (h60 != null && l60 != null) ? ((+h60 + +l60) / 2) : null;

      if (h30 == null || l30 == null) return;
      // if (interval !== '1m' && interval !== '5m' && interval !== '15m') return; // 전체 차트 그리기 위해 주석처리.. 향후 삭제 가능할듯 26.01.08

      const yAxis = chart.yAxis[0];
      clearEarlyLines(chart);

      const colorHi = 'rgba(255,79,179,0.95)';     // H
      const colorLo = 'rgba(79,195,255,0.95)';     // L

      // 15분 고/저 우선 막기. 시뮬레이션 위해 2026.03.22
      yAxis.addPlotLine({
        id: 'hi30_main',
        value: +h30,
        color: colorHi,
        width: 2,
        dashStyle: 'Solid',
        zIndex: 5,
        label: {
          text: `H ${(+h30).toFixed(2)}`,
          align: 'left',
          style: { fontSize: '10px', color: colorHi }
        }
      })
      yAxis.addPlotLine({
        id: 'lo30_main',
        value: +l30,
        color: colorLo,
        width: 2,
        dashStyle: 'Solid',
        zIndex: 5,
        label: {
          text: `L ${(+l30).toFixed(2)}`,
          align: 'left',
          style: { fontSize: '10px', color: colorLo }
        }
      });

      // 30분 고저로 전략 변경. 우선 주석처리 26.01.24
      // if (h60 != null) {
      //   yAxis.addPlotLine({
      //     id: 'hi60_aux',
      //     value: +h60,
      //     color: colorHi,
      //     width: 2,
      //     dashStyle: 'ShortDot',
      //     zIndex: 4,
      //     label: {
      //       text: `(60) 고 ${(+h60).toFixed(2)}`,
      //       align: 'left',
      //       x: 5,
      //       style: { fontSize: '10px', color: colorHi }
      //     }
      //   });
      // }

      // if (l60 != null) {
      //   yAxis.addPlotLine({
      //     id: 'lo60_aux',
      //     value: +l60,
      //     color: colorLo,
      //     width: 2,
      //     dashStyle: 'ShortDot',
      //     zIndex: 4,
      //     label: {
      //       text: `(60) 저 ${(+l60).toFixed(2)}`,
      //       align: 'left',
      //       x: 5,
      //       style: { fontSize: '10px', color: colorLo }
      //     }
      //   });
      // }
    }


    function fitYAxisToCandles(chart, candleSeriesId, extraPaddingPct = 0.18) {
      const s = chart.get(candleSeriesId);
      if (!s) return;

      const source = (s.points?.length ? s.points : (s.options?.data || []));
      let low = Infinity;
      let high = -Infinity;

      source.forEach(p => {
        const pointLow = Number(p?.low ?? p?.[3]);
        const pointHigh = Number(p?.high ?? p?.[2]);
        if (Number.isFinite(pointLow)) low = Math.min(low, pointLow);
        if (Number.isFinite(pointHigh)) high = Math.max(high, pointHigh);
      });

      if (!Number.isFinite(low) || !Number.isFinite(high)) return;

      const range = Math.max(0.01, high - low);
      const pad = range * extraPaddingPct;

      chart.yAxis[0].setExtremes(low - pad, high + pad, true, false);
    }

    // ── 회차별 B/S 표시
    const BS_FLAG_W = 14;
    const BS_FONT = '10px';
    const BS_Y_BUY = 6;
    const BS_Y_SELL = -28;
    const BS_CARRY_COLOR = '#111827';

    function apiPost(mode, payload = {}) {
      const fd = new FormData();
      fd.append('mode', mode);
      Object.entries(payload).forEach(([k, v]) => fd.append(k, v));

      return fetch(TRADE_API, { method:'POST', body:fd, cache:'no-store' })
        .then(r => r.json())
        .then(j => {
          if (!j?.ok) throw new Error(j?.msg || '체결 API 오류');
          return j;
        });
    }

    function renderRuns(runs, selectedRunId = simRunId) {
      const sel = document.getElementById('simRunSelect');
      if (!sel) return;

      sel.innerHTML = '<option value="">(회차 선택)</option>';
      (runs || []).forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.run_id;
        opt.textContent = `${r.run_id}. ${r.run_name}`;
        opt.selected = String(r.run_id) === String(selectedRunId);
        sel.appendChild(opt);
      });
    }

    function normalizeTrades(trades) {
      const arr = Array.isArray(trades) ? trades.slice() : [];
      arr.sort((a, b) => Number(a.seq || 0) - Number(b.seq || 0));

      let pos = 0;
      const out = [];

      for (const t of arr) {
        const action = String(t.action || '').toUpperCase();
        const qty = Math.max(1, parseInt(t.qty || '1', 10) || 1);
        let side = null;

        if (action === 'OPEN_LONG') { side = 'B'; pos += qty; }
        else if (action === 'OPEN_SHORT') { side = 'S'; pos -= qty; }
        else if (action === 'CLOSE_PART') {
          if (pos > 0) { side = 'S'; pos = Math.max(0, pos - qty); }
          else if (pos < 0) { side = 'B'; pos = Math.min(0, pos + qty); }
        }
        else if (action === 'CLOSE_ALL') {
          if (pos > 0) side = 'S';
          else if (pos < 0) side = 'B';
          pos = 0;
        }
        else if (action === 'CLOSE_CARRY_LONG') { side = 'S'; pos = Math.max(0, pos - qty); }
        else if (action === 'CLOSE_CARRY_SHORT') { side = 'B'; pos = Math.min(0, pos + qty); }

        if (side) {
          out.push({
            ...t,
            _side: side,
            _isCarry: action === 'CLOSE_CARRY_LONG' || action === 'CLOSE_CARRY_SHORT'
          });
        }
      }
      return out;
    }

    function ensureBS(chart) {
      if (!chart || chart._bs) return chart?._bs || null;

      const candle = chart.get(chart._priceSeriesId);
      if (!candle) return null;

      const common = {
        type:'flags',
        onSeries:chart._priceSeriesId,
        shape:'circlepin',
        width:BS_FLAG_W,
        lineWidth:0,
        stackDistance:14,
        enableMouseTracking:false,
        dataGrouping:{enabled:false},
        style:{color:'#fff', fontWeight:'900', fontSize:BS_FONT}
      };

      chart._bs = {
        buy: chart.addSeries({ ...common, name:'B', onKey:'low', y:BS_Y_BUY, fillColor:'rgba(244,67,54,0.90)', data:[] }, false),
        sell: chart.addSeries({ ...common, name:'S', onKey:'high', y:BS_Y_SELL, fillColor:'rgba(33,150,243,0.90)', data:[] }, false),
        carryBuy: chart.addSeries({ ...common, name:'Carry B', onKey:'low', y:BS_Y_BUY, fillColor:BS_CARRY_COLOR, data:[] }, false),
        carrySell: chart.addSeries({ ...common, name:'Carry S', onKey:'high', y:BS_Y_SELL, fillColor:BS_CARRY_COLOR, data:[] }, false)
      };
      return chart._bs;
    }

    function clearBS(chart) {
      if (!chart?._bs) return;
      Object.values(chart._bs).forEach(s => s.setData([], false));
      chart.redraw(false);
    }

    function tradeTimeToMs(dt) {
      const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(String(dt || ''));
      if (!m) return NaN;
      return new Date(+m[1], +m[2]-1, +m[3], +m[4], +m[5], +(m[6] || 0)).getTime();
    }

    function intervalMinutes(interval) {
      return interval === '60m' ? 60 : interval === '15m' ? 15 : interval === '5m' ? 5 : 1;
    }

    function snapTradeToCandle(chart, dt) {
      const raw = tradeTimeToMs(dt);
      const xs = chart.get(chart._priceSeriesId)?.xData || [];
      if (!Number.isFinite(raw) || !xs.length) return null;

      const d = new Date(raw);
      const sameDayXs = xs.filter(x => {
        const xd = new Date(x);
        return xd.getFullYear() === d.getFullYear()
          && xd.getMonth() === d.getMonth()
          && xd.getDate() === d.getDate();
      });
      if (!sameDayXs.length) return null;

      // 날짜별 실제 첫 캔들을 기준으로 계산해 08:45/09:45 장 모두 지원한다.
      const anchor = sameDayXs[0];
      const unit = intervalMinutes(chart._interval) * 60 * 1000;
      const bucket = anchor + Math.floor((raw - anchor) / unit) * unit;

      let best = null;
      let gap = Infinity;
      for (const x of sameDayXs) {
        const g = Math.abs(x - bucket);
        if (g < gap) { gap = g; best = x; }
      }
      return gap <= unit / 2 ? best : null;
    }

    function applyBSToChart(chart, trades) {
      if (!chart) return;
      if (!showBS || !simRunId) { clearBS(chart); return; }

      const bs = ensureBS(chart);
      if (!bs) return;

      const maps = { buy:new Map(), sell:new Map(), carryBuy:new Map(), carrySell:new Map() };
      normalizeTrades(trades).forEach(t => {
        const x = snapTradeToCandle(chart, t.bar_dt || t.datetime);
        if (x == null) return;
        const key = t._isCarry ? (t._side === 'B' ? 'carryBuy' : 'carrySell') : (t._side === 'B' ? 'buy' : 'sell');
        maps[key].set(x, (maps[key].get(x) || 0) + 1);
      });

      const points = (map, label) => [...map.entries()]
        .sort((a, b) => a[0] - b[0])
        .map(([x, count]) => ({ x, title:count > 1 ? `${label}${count}` : label, text:'' }));

      bs.buy.setData(points(maps.buy, 'B'), false);
      bs.sell.setData(points(maps.sell, 'S'), false);
      bs.carryBuy.setData(points(maps.carryBuy, 'B'), false);
      bs.carrySell.setData(points(maps.carrySell, 'S'), false);
      chart.redraw(false);
    }

    function loadTradesForDate(date) {
      if (!simRunId) return Promise.resolve([]);
      const key = `${simRunId}:${date}`;
      if (!tradesByDate.has(key)) {
        tradesByDate.set(key,
          apiPost('day_get', { run_id:String(simRunId), trade_date:date })
            .then(j => j.trades || [])
            .catch(err => { tradesByDate.delete(key); throw err; })
        );
      }
      return tradesByDate.get(key);
    }

    function registerChart(date, chart) {
      if (!chartsByDate.has(date)) chartsByDate.set(date, []);
      chartsByDate.get(date).push(chart);

      if (!predictionFullDates.has(date)) {
        const cutoff = predictionCutoffs.get(date);
        setTimeout(() => applyPredictionMask(chart, date, cutoff), 0);
      }

      if (showBS && simRunId) {
        loadTradesForDate(date).then(trades => applyBSToChart(chart, trades)).catch(console.error);
      }
    }

    // ── 날짜별 09:15 이후 HTML 가림막
    function predictionTimeTs(date, hhmm) {
      const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(date));
      const t = /^(\d{2}):(\d{2})$/.exec(String(hhmm));
      if (!m || !t) return NaN;
      return new Date(+m[1], +m[2] - 1, +m[3], +t[1], +t[2], 0).getTime();
    }

    function predictionTimeText(ts) {
      if (!Number.isFinite(ts)) return '-';
      const d = new Date(ts);
      return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }

    function ensurePredictionMask(chart) {
      if (!chart || !chart.renderTo) return null;

      const host = chart.renderTo;
      if (getComputedStyle(host).position === 'static') {
        host.style.position = 'relative';
      }

      let mask = host.querySelector(':scope > .prediction-mask');
      if (!mask) {
        mask = document.createElement('div');
        mask.className = 'prediction-mask';
        mask.setAttribute('aria-hidden', 'true');
        host.appendChild(mask);
      }
      return mask;
    }

    function predictionMaskLeft(chart, cutoff) {
      const interval = chart._interval || '';
      const priceSeries = chart.get(`price-${interval}`);

      // 09:15에 시작하는 봉은 미래 봉이므로 그 봉의 왼쪽 경계부터 가린다.
      const futurePoint = priceSeries?.points?.find(p => p && p.x >= cutoff);
      if (futurePoint?.shapeArgs && Number.isFinite(futurePoint.shapeArgs.x)) {
        return chart.plotLeft + futurePoint.shapeArgs.x;
      }

      return chart.xAxis[0].toPixels(cutoff, false);
    }

    function updatePredictionMask(chart) {
      if (!chart?._predictionMasked || !chart._tradeDate) return;

      const mask = ensurePredictionMask(chart);
      const cutoff = chart._predictionCutoff;
      if (!mask || !Number.isFinite(cutoff)) return;

      const plotRight = chart.plotLeft + chart.plotWidth;
      const rawLeft = predictionMaskLeft(chart, cutoff);
      // 다음 봉의 테두리·꼬리가 경계 밖으로 살짝 보이지 않도록 4px 겹쳐 가린다.
      const maskOverlap = 4;
      const left = Math.max(chart.plotLeft, Math.min(plotRight, rawLeft - maskOverlap));

      mask.style.left = `${Math.round(left)}px`;
      mask.style.top = `${Math.round(chart.plotTop)}px`;
      mask.style.width = `${Math.max(0, Math.round(plotRight - left))}px`;
      mask.style.height = `${Math.round(chart.plotHeight)}px`;
      mask.classList.add('active');
    }

    function fitPredictionYAxis(chart, cutoff) {
      if (!chart?.yAxis?.[0] || !Number.isFinite(cutoff)) return;

      const interval = chart._interval || '';
      const priceSeries = chart.get(`price-${interval}`);
      const visiblePoints = (priceSeries?.points || []).filter(p =>
        p && p.x < cutoff && Number.isFinite(p.low) && Number.isFinite(p.high)
      );
      if (!visiblePoints.length) return;

      // 전체 차트의 원래 Y축은 최초 한 번만 보관한다.
      if (!chart._predictionFullYExtremes) {
        const ext = chart.yAxis[0].getExtremes();
        chart._predictionFullYExtremes = { min: ext.min, max: ext.max };
      }

      const low = Math.min(...visiblePoints.map(p => p.low));
      const high = Math.max(...visiblePoints.map(p => p.high));
      const range = Math.max(0.01, high - low);
      const pad = Math.max(0.05, range * 0.15);

      chart.yAxis[0].setExtremes(low - pad, high + pad, false, false);

      // 거래량도 미래 최대 거래량의 영향을 받지 않도록 공개 구간만으로 맞춘다.
      const volumeSeries = chart.get(`volume-${interval}`);
      const volumeAxis = chart.yAxis[1];
      if (volumeAxis && !chart._predictionFullVolumeExtremes) {
        const volExt = volumeAxis.getExtremes();
        chart._predictionFullVolumeExtremes = { min: volExt.min, max: volExt.max };
      }
      const visibleVolumes = (volumeSeries?.points || [])
        .filter(p => p && p.x < cutoff && Number.isFinite(p.y))
        .map(p => p.y);
      if (volumeAxis && visibleVolumes.length) {
        const volumeMax = Math.max(1, ...visibleVolumes);
        volumeAxis.setExtremes(0, volumeMax * 1.08, false, false);
      }

      chart.redraw(false);
    }

    function updateOneMinuteWindow(chart, cutoff, showFull = false) {
      if (chart?._interval !== '1m' || !chart._fullSeries) return;

      const fullCandles = chart._fullSeries.candles || [];
      const windowSize = chart._oneMinuteWindowSize || 200;
      if (!fullCandles.length) return;

      let endExclusive;
      if (showFull) {
        endExclusive = fullCandles.length;
      } else {
        const firstFuture = fullCandles.findIndex(row => row[0] >= cutoff);
        const visibleEnd = firstFuture < 0 ? fullCandles.length : firstFuture;

        // 오전에는 기존 200봉 구성을 유지하고, 범위를 넘을 때부터 5분씩 이동한다.
        endExclusive = Math.min(
          fullCandles.length,
          Math.max(windowSize, visibleEnd)
        );
      }

      const start = Math.max(0, endExclusive - windowSize);
      const targets = chart._sliceTargets || [];
      targets.forEach(target => {
        const series = chart.get(target.id);
        const source = chart._fullSeries[target.key];
        if (series && Array.isArray(source)) {
          series.setData(source.slice(start, endExclusive), false);
        }
      });

      chart._oneMinuteWindowStart = start;
      chart.redraw(false);
    }

    function fitCurrentOneMinuteYAxis(chart) {
      if (chart?._interval !== '1m' || !chart.yAxis?.[0]) return;

      const priceSeries = chart.get('price-1m');
      const points = (priceSeries?.points || []).filter(p =>
        p && Number.isFinite(p.low) && Number.isFinite(p.high)
      );
      if (points.length) {
        const low = Math.min(...points.map(p => p.low));
        const high = Math.max(...points.map(p => p.high));
        const range = Math.max(0.01, high - low);
        const pad = Math.max(0.05, range * 0.15);
        chart.yAxis[0].setExtremes(low - pad, high + pad, false, false);
      }

      const volumeSeries = chart.get('volume-1m');
      const volumes = (volumeSeries?.points || [])
        .filter(p => p && Number.isFinite(p.y))
        .map(p => p.y);
      if (chart.yAxis[1] && volumes.length) {
        chart.yAxis[1].setExtremes(0, Math.max(1, ...volumes) * 1.08, false, false);
      }
      chart.redraw(false);
    }

    function restorePredictionYAxis(chart) {
      if (!chart?.yAxis?.[0]) return;

      const ext = chart._predictionFullYExtremes;
      if (ext && Number.isFinite(ext.min) && Number.isFinite(ext.max)) {
        chart.yAxis[0].setExtremes(ext.min, ext.max, false, false);
      } else {
        chart.yAxis[0].setExtremes(null, null, false, false);
      }

      const volExt = chart._predictionFullVolumeExtremes;
      if (chart.yAxis[1]) {
        if (volExt && Number.isFinite(volExt.min) && Number.isFinite(volExt.max)) {
          chart.yAxis[1].setExtremes(volExt.min, volExt.max, false, false);
        } else {
          chart.yAxis[1].setExtremes(null, null, false, false);
        }
      }
      chart.redraw(false);
    }

    function applyPredictionMask(chart, date, cutoff) {
      if (!chart) return;
      chart._tradeDate = date;
      chart._predictionCutoff = cutoff;
      chart._predictionMasked = true;
      updateOneMinuteWindow(chart, cutoff, false);
      fitPredictionYAxis(chart, cutoff);
      updatePredictionMask(chart);
    }

    function removePredictionMask(chart) {
      if (!chart?.renderTo) return;
      chart._predictionMasked = false;
      chart.renderTo
        .querySelector(':scope > .prediction-mask')
        ?.classList.remove('active');
      restorePredictionYAxis(chart);
    }

    function predictionControls(source) {
      return source?.closest('.prediction-controls') || null;
    }

    function updatePredictionCurrent(controls, text) {
      const label = controls?.querySelector('.prediction-current');
      if (label) label.textContent = text;
    }

    function applyPredictionCutoff(controls, cutoff) {
      if (!controls || !Number.isFinite(cutoff)) return;
      const date = controls.dataset.date;
      const charts = chartsByDate.get(date) || [];
      predictionCutoffs.set(date, cutoff);
      predictionFullDates.delete(date);
      charts.forEach(chart => applyPredictionMask(chart, date, cutoff));
      updatePredictionCurrent(controls, predictionTimeText(cutoff));
    }

    function setPredictionView(button, hhmm) {
      const controls = predictionControls(button);
      if (!controls) return;
      applyPredictionCutoff(controls, predictionTimeTs(controls.dataset.date, hhmm));
    }

    function stepPredictionView(button, minutes) {
      const controls = predictionControls(button);
      if (!controls) return;
      const date = controls.dataset.date;
      const current = predictionCutoffs.get(date) ?? predictionTimeTs(date, '09:15');
      applyPredictionCutoff(controls, current + Number(minutes) * 60 * 1000);
    }

    function showPredictionFull(button) {
      const controls = predictionControls(button);
      if (!controls) return;
      const date = controls.dataset.date;
      predictionFullDates.add(date);
      (chartsByDate.get(date) || []).forEach(chart => {
        if (chart._interval === '1m') {
          updateOneMinuteWindow(chart, NaN, true);
          removePredictionMask(chart);
          fitCurrentOneMinuteYAxis(chart);
        } else {
          removePredictionMask(chart);
          fitYAxisToCandles(chart, chart._priceSeriesId, 0.12);
        }
      });
      updatePredictionCurrent(controls, '전체');
    }

    async function refreshAllBS() {
      if (!showBS || !simRunId) {
        chartsByDate.forEach(charts => charts.forEach(clearBS));
        return;
      }

      for (const [date, charts] of chartsByDate.entries()) {
        const trades = await loadTradesForDate(date);
        charts.forEach(chart => applyBSToChart(chart, trades));
      }
    }

    async function initBSControls() {
      const j = await apiPost('run_list');
      const runs = j.runs || [];
      const serverRunExists = runs.some(r => Number(r.run_id) === Number(SERVER_RUN_ID));
      const selectedRunId = serverRunExists ? Number(SERVER_RUN_ID) : 0;
      renderRuns(runs, selectedRunId);

      // 비동기로 회차 목록을 만든 직후 내부 회차값도 동일하게 맞춘다.
      simRunId = Number(selectedRunId || 0);

      await refreshAllBS();
    }

    // 회차 선택값과 B/S 조회에 사용하는 내부 run_id 동기화
    document.getElementById('simRunSelect')?.addEventListener('change', function () {
      simRunId = Number(this.value || 0);
      tradesByDate.clear();
    });

    // 조회 제출 직전에도 선택된 회차값을 최종 확정
    document.getElementById('filterForm')?.addEventListener('submit', function () {
      const select = document.getElementById('simRunSelect');
      simRunId = Number(select?.value || 0);
    });

    document.getElementById('showBs')?.addEventListener('change', async e => {
      showBS = !!e.target.checked;
      try { await refreshAllBS(); } catch (err) { console.error(err); alert(err.message || err); }
    });

    // ── 공통 차트 드로잉
    // 날짜가 바뀌는 두 캔들 사이에 구분선 표시 (가격·거래량 영역 전체).
    // 렌더링된 좌표를 사용하므로 휴장일 압축, 리사이즈, 표시 구간 이동에도 맞춰진다.
    function drawDaySeparators(chart, priceSeriesId) {
      if (chart._daySeparatorGroup) chart._daySeparatorGroup.destroy();
      const group = chart.renderer.g('day-separators')
        .attr({ zIndex: 2 }).css({ pointerEvents: 'none' }).add();
      chart._daySeparatorGroup = group;

      const points = chart.get(priceSeriesId)?.points || [];
      const axis = chart.xAxis[0];
      const left = chart.plotLeft;
      const right = left + chart.plotWidth;
      for (let i = 1; i < points.length; i++) {
        const previous = points[i - 1];
        const current = points[i];
        if (chart.time.dateFormat('%Y-%m-%d', previous.x) ===
            chart.time.dateFormat('%Y-%m-%d', current.x)) continue;

        const x = Math.round((axis.toPixels(previous.x, false) +
                              axis.toPixels(current.x, false)) / 2) + 0.5;
        if (!Number.isFinite(x) || x <= left || x >= right) continue;
        chart.renderer.path([
          'M', x, chart.plotTop,
          'L', x, chart.plotTop + chart.plotHeight
        ]).attr({ stroke: '#7b8794', 'stroke-width': 1, dashstyle: 'Dash' }).add(group);
      }
    }

    function drawChartForInterval(containerId, date, interval, opts = {}) {
      const fetchBars = barsFor(interval);
      const priceSeriesId = `price-${interval}`;

      const requestLimit =
        interval === '1m' ? 500 :
        interval === '5m' ? 100 :
        fetchBars;
      const allDayParam = interval === '1m' ? '&all_day=1' : '';

      $.getJSON(`./get_1min_data.php?date=${encodeURIComponent(date)}&interval=${encodeURIComponent(interval)}&limit=${requestLimit}${allDayParam}`, function(data) {
        if (!data || !data.length) return;

        // ✅ triple + L 일 때만 표시 봉 수를 줄임(과거 잘리도록 tail slice)
        const el = document.getElementById(containerId);
        const isAdaptive = !!(opts && opts.triple && opts.slot === 'L');

        const capBars = Math.min(fetchBars, data.length);
        let displayBars = capBars;

        if (isAdaptive) {
          displayBars = estimateDisplayBarsByContainer(el, interval, capBars);
        }

        // ✅ 과거(왼쪽)부터 잘리게: 마지막 displayBars만 사용
        const viewData = (displayBars < data.length)
          ? (interval === '1m' ? data.slice(0, displayBars) : data.slice(-displayBars))
          : data;

        // 높이 세팅
        const h = computeHeight(fetchBars);
        if (el) {
          el.style.height = `${h}px`;
          el.style.setProperty('--chart-h', `${h}px`);

          // ★ 5분봉 / 15분봉일 때 chart-one 박스 배경색 넣기
          const wrap = el.closest('.chart-one');
          if (wrap) {
            wrap.classList.remove('bg-5m', 'bg-15m');
            if (interval === '5m') wrap.classList.add('bg-5m');
            else if (interval === '15m') wrap.classList.add('bg-15m');
          }
        }

        // ── 안전한 로컬타임 파서
        function toTS(s){
          const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/.exec(String(s));
          if (!m) return Date.parse(String(s).replace(' ', 'T'));
          const [_, Y,M,D,h,mn,sc] = m.map(Number);
          return new Date(Y, M-1, D, h, mn, sc).getTime();
        }
        function tsLocal(r){
          if (r.ts != null) {
            const v = +r.ts;
            return v < 1e12 ? v * 1000 : v;
          }
          return toTS(r.datetime);
        }

        // 렌더된 캔들의 실제 픽셀폭을 이용해 [from,to]를 축 값으로 환산
        function bandRangeByRenderedCandle(priceSeries, ts, xAxis) {
          const xs = (priceSeries && priceSeries.xData) || [];
          if (!xs.length) {
            const step = 60*1000;
            const midPx = xAxis.toPixels(ts + step/2, true);
            const prevPx = xAxis.toPixels(ts, true);
            const wPx = Math.abs(midPx - prevPx) * 2;
            const leftPx  = (xAxis.toPixels(ts, true)) - (wPx/2);
            const rightPx = leftPx + wPx;
            return { from: xAxis.toValue(leftPx, true), to: xAxis.toValue(rightPx, true) };
          }

          let lo=0, hi=xs.length-1;
          while (lo < hi) { const mid=(lo+hi)>>1; (xs[mid] < ts ? lo=mid+1 : hi=mid); }
          let idx = lo;
          if (idx>0 && Math.abs(xs[idx]-ts) >= Math.abs(ts-xs[idx-1])) idx--;

          const pt = priceSeries.points && priceSeries.points.find(p => p.x === xs[idx]);
          let wPx;
          if (pt && pt.shapeArgs && pt.shapeArgs.width) {
            wPx = pt.shapeArgs.width;
          } else {
            const nextGap = (idx+1<xs.length) ? (xs[idx+1]-xs[idx]) : (idx>0 ? (xs[idx]-xs[idx-1]) : 60*1000);
            const px0 = xAxis.toPixels(xs[idx], true);
            const px1 = xAxis.toPixels(xs[idx] + nextGap, true);
            wPx = Math.abs(px1 - px0);
          }

          const centerPx = pt ? pt.plotX : xAxis.toPixels(xs[idx], true);
          const leftPx  = centerPx - wPx/2;
          const rightPx = centerPx + wPx/2;
          return { from: xAxis.toValue(leftPx, true), to: xAxis.toValue(rightPx, true) };
        }

        function pickFieldFromRows(rows, candidates){
          if (!Array.isArray(rows)) return null;
          for (const r of rows){
            for (const k of candidates){
              if (r && r[k] != null && r[k] !== '' && isFinite(+r[k])) return k;
            }
          }
          return null;
        }
        function toLine(rows, field){
          const out=[];
          if (!field) return out;
          rows.forEach(r=>{
            const v = +r[field];
            if (isFinite(v)) out.push([tsLocal(r), v]);
          });
          return out;
        }

        const vwapKey = pickFieldFromRows(data, ['vwap_session']);

        const dayStr = date;
        const firstTodayRow = data.find(r => String(r.datetime).slice(0,10) === dayStr);
        const firstTS = firstTodayRow ? tsLocal(firstTodayRow) : null;

        const baseRow       = data[0] || {};
        const sessionOpen   = baseRow.session_open    ?? null;

        const openPrice = sessionOpen != null
          ? +sessionOpen
          : parseFloat(firstTodayRow ? firstTodayRow.open : data[0].open);

        const rows = viewData;          // ✅ 화면에 그릴 데이터(슬라이스 적용)
        const fullRows = data;          // ✅ 캐시/리사이즈용 전체(요청 limit 범위)

        const volumeMax = interval==='1m' ? 8000 : (interval==='5m' ? 30000 : 50000);

        const isVwap  = (interval === '1m' || interval === '5m' || interval === '15m');
        const isShort = (interval === '1m' || interval === '5m'); // ✅ 1m/5m 구분용

        // ✅ 1m/5m만 SMA 표시 여부를 조회조건으로 제어
        const showSma =
          (interval === '1m') ? SHOW_SMA_1M :
          (interval === '5m') ? SHOW_SMA_5M :
          true; // 15m/60m은 항상 SMA

        const candles = rows.map(r => [ tsLocal(r), +r.open, +r.high, +r.low, +r.close ]);
        const vwap    = isVwap ? toLine(rows, vwapKey) : [];

        const volume = rows.map(r => ({
          x: tsLocal(r),
          y: +r.volume,
          color: (+r.close >= +r.open) ? '#f45b5b' : '#2f7ed8'
        }));

        // ✅ 1m/5m은 20선만 만들고, 15m/60m은 5/20/120 전부 생성
        const sma20  = showSma ? toLine(rows, 'sma_20') : [];
        const sma5   = (showSma) ? toLine(rows, 'sma_5')   : [];
        const sma120 = (showSma && !isShort) ? toLine(rows, 'sma_120') : [];

        const series = [];

        // ✅ SMA는 showSma일 때만
        if (showSma) {
          if (isShort) {
            // ✅ 1m/5m: 5/20선만
            series.push(
              { id:`sma20-${interval}`, type:'line', name:'SMA 20', data:sma20, color:SMA20_COLOR, lineWidth:2, zIndex:1, enableMouseTracking:false, dataGrouping:{enabled:false} },
              { id:`sma5-${interval}`,   type:'line', name:'SMA 5',   data:sma5,   color:SMA5_COLOR,   lineWidth:2, zIndex:1, enableMouseTracking:false, dataGrouping:{enabled:false} }
            );
          } else {
            // ✅ 15m/60m: 5/20/120
            series.push(
              { id:`sma120-${interval}`, type:'line', name:'SMA 120', data:sma120, color:SMA120_COLOR, lineWidth:0, zIndex:1, enableMouseTracking:false, dataGrouping:{enabled:false} },
              { id:`sma20-${interval}`,  type:'line', name:'SMA 20',  data:sma20,  color:SMA20_COLOR,  lineWidth:2, zIndex:1, enableMouseTracking:false, dataGrouping:{enabled:false} },
              { id:`sma5-${interval}`,   type:'line', name:'SMA 5',   data:sma5,   color:SMA5_COLOR,   lineWidth:1, zIndex:1, enableMouseTracking:false, dataGrouping:{enabled:false} }
            );
          }
        }

        // ✅ 캔들 먼저
        series.push({ id: priceSeriesId, type:'candlestick', name:'Price', data:candles, zIndex:3, dataGrouping:{enabled:false} });

        // ✅ 거래량 추가
        series.push({
          id: `volume-${interval}`,
          type: 'column',
          name: 'Volume',
          data: volume,
          yAxis: 1,
          zIndex: 1,
          borderWidth: 0,
          pointPadding: 0.05,
          groupPadding: 0.05,
          dataGrouping: { enabled: false }
        });

        // ✅ VWAP은 1m/5m에서만 (캔들 뒤 + zIndex 높게)
        if (isVwap) {
          series.push({
            id:`vwap-${interval}`,
            type:'line',
            name:'VWAP',
            data: vwap,
            lineWidth: 2,
            zIndex: 1,
            color: '#6d28d9',
            dataGrouping:{enabled:false}
          });
        }

        // 거래량 max 표시
        // const thresholds = { '1m': VOL_THRESHOLD_1M, '5m': VOL_THRESHOLD_5M };
        // const volThreshold = thresholds[interval] ?? null;
        // const volAxisMax  = volThreshold ? Math.max(volumeMax, volThreshold * 1.05) : volumeMax;

        const volThreshold = null;
        const volAxisMax = null;

        const TOOLTIP_TIME_ANCHOR = 'end';

        const chart = Highcharts.stockChart(containerId, {
          chart:{
            height:null,
            zooming:{ mouseWheel:{enabled:false}, type:null },
            panning:false,
            panKey:null,
            events:{
              render(){
                drawDaySeparators(this, priceSeriesId);
              },
              load(){
                this.customShowTooltip = false;

                // ✅ 이평선은 유지하고 캔들 고가/저가 기준으로 Y축 확대
                fitYAxisToCandles(this, priceSeriesId, 0.12);
              }
            }
          },
          exporting: { enabled: false },
          navigator:{enabled:false}, scrollbar:{enabled:false}, rangeSelector:{enabled:false},
          title:{text:''}, time:{useUTC:false},
          xAxis: {
            type: 'datetime',
            ordinal: true,
            startOnTick: false,
            endOnTick: false,
            minPadding: 0,
            maxPadding: 0,
            tickPositioner: function (min, max) {
              if (!firstTS) return this.tickPositions || [];
              const H = 3600 * 1000;
              const ticks = [];
              ticks.push(firstTS);
              for (let t = Math.ceil(firstTS / H) * H; t <= max; t += H) ticks.push(t);
              return ticks.filter(t => t >= min && t <= max);
            },
            labels: { format: '{value:%H:%M}', style: { fontSize: '8px' } },
            crosshair: { width: 1, color: '#888', dashStyle: 'ShortDot' }
          },
          yAxis:[
            { height:'83%', lineWidth:1, startOnTick:false, endOnTick:false, minPadding:0.01, maxPadding:0.01,
              plotLines:[{ color:'gray', value:openPrice, width:1, dashStyle:'Dash',
              // label:{ text:`시가 ${openPrice}`, align:'left', style:{ color:'#666', fontSize:'11px' } } 
              }]
            },
            {
              top:'83%', height:'17%', offset:0, lineWidth:1,
              min:0, max: volAxisMax,
              plotLines: volThreshold ? [{
                id: 'vol-12k',
                value: volThreshold,
                color: '#888',
                dashStyle: 'Dash',
                width: 1,
                zIndex: 5,
                label: {
                  text: 'Vol '+ volThreshold,
                  align: 'right',
                  y: -2,
                  style: { color:'#666', fontSize:'10px' }
                }
              }] : []
            }
          ],
          series: series,
          tooltip:{
            shared:true, split:false, useHTML:true,
            formatter:function(){
              const ch = (this.points && this.points[0]?.series?.chart) ? this.points[0].series.chart : (this.point?.series?.chart || null);
              if (!ch || !ch.customShowTooltip) return false;

              const candleP = (this.points||[]).find(p => p.series.type==='candlestick') || this.point;
              const s = candleP?.series;
              let t = candleP?.x ?? this.x;

              if (s && Array.isArray(s.xData)) {
                const xs = s.xData;
                let i = xs.indexOf(t);
                if (i < 0) {
                  let lo=0, hi=xs.length-1;
                  while(lo<hi){ const mid=(lo+hi)>>1; (xs[mid]<t?lo=mid+1:hi=mid); }
                  i = (lo>0 && Math.abs(xs[lo]-t) >= Math.abs(t-xs[lo-1])) ? lo-1 : lo;
                }
                const prev = (i>0) ? xs[i]-xs[i-1] : ((xs[i+1]??xs[i]) - xs[i]);
                const next = (i+1<xs.length) ? xs[i+1]-xs[i] : prev;
                const barMs = Math.max( prev>0?prev:0, next>0?next:0 ) || 60*1000;

                if (TOOLTIP_TIME_ANCHOR === 'end')   t = t + barMs;
                if (TOOLTIP_TIME_ANCHOR === 'center') t = t + barMs/2;
              }

              const tm = ch.time.dateFormat('%Y-%m-%d %H:%M', t);

              let html = `<b>${tm}</b><br/>--------------<br/>`;
              (this.points||[this.point]).forEach(p=>{
                const sname = String(p.series.name||'');
                if (p.series.type==='candlestick') {
                  const o=Highcharts.numberFormat(p.point.open,2), h=Highcharts.numberFormat(p.point.high,2),
                        l=Highcharts.numberFormat(p.point.low,2),  c=Highcharts.numberFormat(p.point.close,2);
                  html += `<span style="font-weight:600">${sname}</span>: <br/> O ${o} <br/> H ${h} <br/> L ${l} <br/> C ${c}<br/>`;
                } else {
                  html += `${sname}: ${Highcharts.numberFormat(p.y, p.series.type==='column'?0:2)}<br/>`;
                }
              });
              return html;
            }
          },
          plotOptions:{
            series:{ states:{ hover:{enabled:false} } },
            candlestick:{ color:'#2f7ed8', upColor:'#f45b5b', lineColor:'#2f7ed8', upLineColor:'#f45b5b' }
          }
        });

        chart._sliceTargets = [
          { id: priceSeriesId,        key: 'candles' },
          { id: `volume-${interval}`, key: 'volume'  },
          ...(isVwap ? [{ id: `vwap-${interval}`, key: 'vwap' }] : []),
          ...(showSma ? [
            { id: `sma120-${interval}`, key: 'sma120' },
            { id: `sma20-${interval}`,  key: 'sma20'  },
            { id: `sma5-${interval}`,   key: 'sma5'   }
          ] : [])
        ];

        // ✅ adaptive slice를 위한 원본(가져온 구간) 저장: capBars만큼(프리셋 범위 내)
        chart._priceSeriesId = priceSeriesId;
        chart._adaptiveSlice = isAdaptive;
        chart._adaptiveMeta  = { interval, capBars };

        // full series(가져온 데이터 기준) 만들어 저장
        // (여기서는 "data"로 full을 만들고, 이미 위에서는 viewData로 표시 생성함)
        const fullCandles = fullRows.map(r => [ tsLocal(r), +r.open, +r.high, +r.low, +r.close ]);
        const fullVolume  = fullRows.map(r => ({ x: tsLocal(r), y:+r.volume, color: r.close>r.open ? '#f45b5b':'#2f7ed8' }));

        const full = { candles: fullCandles, volume: fullVolume };

        if (isVwap) {
          full.vwap = toLine(fullRows, vwapKey);
        }
        if (showSma) {
          full.sma5   = toLine(fullRows, 'sma_5');
          full.sma20  = toLine(fullRows, 'sma_20');
          full.sma120 = toLine(fullRows, 'sma_120');
        }

        chart._fullSeries = full;
        chart._oneMinuteWindowSize = interval === '1m' ? fetchBars : null;

        // 현재 표시 봉수 기록(내보내기/리사이즈 대응)
        chart._displayBars = displayBars;

        // 생성 직후 실제 plotWidth 기준으로 한 번 더 맞춤(정밀 보정)
        if (isAdaptive) {
          setTimeout(() => { try { applyAdaptiveSlice(chart); } catch(e){} }, 0);
        }

        if (el) {
          el._chart = chart;
          chart._interval = interval;
          chart._tradeDate = date;
          registerChart(date, chart);
          chartResizeObserver.observe(el);
          const cardEl = el.closest('.chart-item');
          if (cardEl) cardResizeObserver.observe(cardEl);
          setTimeout(()=>{ try{ chart.reflow(); }catch(e){} }, 0);
        }

        drawEarlyLines(chart, baseRow, dayStr, interval);
        // === 09:00 & 09:30 기준선 ===
        if (interval === '1m' || interval === '5m' || interval === '15m') setTimeout(()=>{
          const xa   = chart.xAxis[0];

          const ts0900 = toTS(dayStr + ' 09:00:00');
          const ts0915 = toTS(dayStr + ' 09:15:00');
          const ts0930 = toTS(dayStr + ' 09:30:00');
          const ts1000 = toTS(dayStr + ' 10:00:00');
          const ts1100 = toTS(dayStr + ' 11:00:00');

          if (interval === '1m' || interval === '5m' ) {
            xa.addPlotLine({
              id: `_line-0900-${dayStr}-${interval}`,
              value: ts0900,
              color: 'rgba(253, 250, 38, 1)',
              width: 5,
              zIndex: 0
            });
          }

          // xa.addPlotLine({
          //   id: `_line-0915-${dayStr}-${interval}`,
          //   value: ts0915,
          //   color: 'rgba(167, 248, 215, 1)',
          //   width: 5,
          //   zIndex: 2
          // });

          xa.addPlotLine({
            id: `_line-0930-${dayStr}-${interval}`,
            value: ts0930,
            color: 'rgba(167, 248, 215, 1)',
            width: 5,
            zIndex: 2
          });

          if (interval === '1m' || interval === '5m' ) {
            xa.addPlotLine({
              id: `_line-1000-${dayStr}-${interval}`,
              value: ts1000,
              color: 'rgba(253, 250, 38, 1)',
              width: 5,
              zIndex: 0
            });
          }

          if (interval === '1m' || interval === '5m' ) {
            xa.addPlotLine({
              id: `_line-1100-${dayStr}-${interval}`,
              value: ts1100,
              color: 'rgba(253, 250, 38, 1)',
              width: 5,
              zIndex: 0
            });
          }

        }, 0);

        // 클릭 기반 툴팁/크로스헤어
        function hitRect(point, ev){ if(!point||!point.shapeArgs) return false;
          const sa=point.shapeArgs, x0=(sa.x??0)+chart.plotLeft, y0=(sa.y??0)+chart.plotTop;
          const x1=x0+(sa.width??0), y1=y0+(sa.height??0);
          return ev.chartX>=x0 && ev.chartX<=x1 && ev.chartY>=y0 && ev.chartY<=y1; }
        function nearPoint(point, ev, th=24){ if(!point) return false; if(hitRect(point,ev)) return true;
          if(typeof point.plotX!=='number'||typeof point.plotY!=='number') return false;
          const px=point.plotX+chart.plotLeft, py=point.plotY+chart.plotTop;
          return Math.hypot(ev.chartX-px, ev.chartY-py) <= th; }
        function getPointsAtEvent(e){ const ev=chart.pointer.normalize(e);
          if(!chart.isInsidePlot(ev.chartX-chart.plotLeft, ev.chartY-chart.plotTop, {series:true})) return [];
          const cands=chart.series.filter(s=>s.visible&&s.options.enableMouseTracking!==false)
              .map(s=>s.searchPoint(ev,true)).filter(Boolean);
          const near=cands.filter(p=>nearPoint(p,ev)); if(!near.length) return [];
          const xVal=near[0].x;
          return chart.series.filter(s=>s.visible&&s.points).map(s=>s.points.find(pt=>pt&&pt.x===xVal)).filter(Boolean); }
        chart.container.addEventListener('click', e=>{
          const ev=chart.pointer.normalize(e); const pts=getPointsAtEvent(e);
          if(pts.length){ chart.customShowTooltip=true; chart.tooltip.refresh(pts,ev); chart.xAxis[0].drawCrosshair(ev, pts[0]); }
          else { chart.customShowTooltip=false; chart.tooltip.hide(0); chart.xAxis[0].hideCrosshair(); }
        });
        document.addEventListener('click', e=>{ if(!chart.container.contains(e.target)){
          chart.customShowTooltip=false; chart.tooltip.hide(0); chart.xAxis[0].hideCrosshair(); } });
        chart.container.addEventListener('mousemove', ()=>{ if(!chart.customShowTooltip) chart.tooltip.hide(0); });
      });
    }

    function detectIntervalBySubtitle(el) {
      const label = el.closest('.chart-one')
                      ?.querySelector('.chart-subtitle')
                      ?.textContent || '';
      if (label.includes('1분'))  return '1m';
      if (label.includes('5분'))  return '5m';
      if (label.includes('15분')) return '15m';
      if (label.includes('60분')) return '60m';
      return '1m';
    }

    // ✅ 날짜 카드 그리기 (DOM은 L/M/R 고정, 내용만 갈아끼움)
    function drawPerDate(idx, date) {
      const boxL = document.getElementById(`chart-l-${idx}`);
      const boxM = document.getElementById(`chart-m-${idx}`);
      const boxR = document.getElementById(`chart-r-${idx}`);

      const pair = boxL.closest('.chart-pair');
      const card = pair.closest('.chart-item');
      const sel  = intervalSel;

      applyCardSpan(card, sel);

      const wrapL = boxL.closest('.chart-one');
      const wrapM = boxM.closest('.chart-one');
      const wrapR = boxR.closest('.chart-one');

      // 기본값: 모두 숨김
      [wrapL, wrapM, wrapR].forEach(w => { if (w) w.style.display = 'none'; });
      pair.classList.remove('single', 'triple', 'duo-5m1m');

      function show(wrap, boxId, labelText, interval, opts = {}) {
        if (wrap) {
          wrap.style.display = '';
          const st = wrap.querySelector('.chart-subtitle');
          if (st) st.textContent = labelText;
        }
        drawChartForInterval(boxId, date, interval, opts);
      }

      // (표시 순서: 60 → 15 → 5 → 1, 가능한 조합 내에서 유지)
      if (sel === '1m5m') {
        pair.classList.add('duo-5m1m');
        // 5m(좌) + 1m(우)
        show(wrapL, `chart-l-${idx}`, '5분', '5m');
        show(wrapR, `chart-r-${idx}`, '1분', '1m');
      } else if (sel === '5m15m') {
        // 15m(좌) + 5m(우)
        show(wrapL, `chart-l-${idx}`, '15분', '15m');
        show(wrapR, `chart-r-${idx}`, '5분',  '5m');

      } else if (sel === '1m5m15m') {
        pair.classList.add('triple');
        show(wrapL, `chart-l-${idx}`, '15분', '15m', { triple: true, slot: 'L' }); // ✅ L만
        show(wrapM, `chart-m-${idx}`, '5분',  '5m');
        show(wrapR, `chart-r-${idx}`, '1분',  '1m');

      } else if (sel === '5m15m60m') {
        pair.classList.add('triple');
        show(wrapL, `chart-l-${idx}`, '60분', '60m', { triple: true, slot: 'L' }); // ✅ L만
        show(wrapM, `chart-m-${idx}`, '15분', '15m');
        show(wrapR, `chart-r-${idx}`, '5분',  '5m');
      } else {
        // 단일 모드 (1m / 5m / 15m / 60m) → 좌측(L)만 사용
        pair.classList.add('single');

        if (sel === '1m')  show(wrapL, `chart-l-${idx}`, '1분',  '1m');
        if (sel === '5m')  show(wrapL, `chart-l-${idx}`, '5분',  '5m');
        if (sel === '15m') show(wrapL, `chart-l-${idx}`, '15분', '15m');
        if (sel === '60m') show(wrapL, `chart-l-${idx}`, '60분', '60m');
      }
    }

    // 전체 렌더 + 보강 리플로우
    dateList.forEach((d, idx) => drawPerDate(idx, d.date));
    initBSControls().catch(err => { console.error(err); alert(err.message || err); });
    scheduleReflows();
    window.addEventListener('resize', () => scheduleReflows());

    // 인쇄 훅
    if (window.matchMedia) {
      const mq = window.matchMedia('print');
      (mq.addEventListener ? mq.addEventListener('change', onPrintChange) : mq.addListener(onPrintChange));
      function onPrintChange() { scheduleReflows(); }
    }
    window.onbeforeprint = () => scheduleReflows();
    window.onafterprint  = () => scheduleReflows();

    // 팝업
    const POPUP_W=1500, POPUP_H=480;
    function openIntradayPopup(url, name='intraday_1m5m'){
      const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
      const dualScreenTop  = window.screenTop  ?? window.screenY ?? 0;
      const w = window.innerWidth||document.documentElement.clientWidth||screen.width;
      const h = window.innerHeight||document.documentElement.clientHeight||screen.height;
      const left = dualScreenLeft + Math.max(0,(w-POPUP_W)/2);
      const top  = dualScreenTop  + Math.max(0,(h-POPUP_H)/2);
      const features=[`width=${POPUP_W}`,`height=${POPUP_H}`,`left=${left}`,`top=${top}`,
        'menubar=no','toolbar=no','location=no','status=no','resizable=yes','scrollbars=yes'].join(',');
      const win = window.open(url, name, features);
      if (!win) { window.open(url,'_blank','noopener,noreferrer'); return false; }
      try { win.focus(); } catch(e) {}
      return false;
    }

    // ── SVG 저장
    async function uploadSvgToObsidian({ date, interval, index, bars, svgText }) {
      const res = await fetch('futures_strategy_candle_save_svg.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date, interval, index, bars, svg: svgText })
      });
      if (!res.ok) throw new Error('SVG 업로드 실패');
      const data = await res.json(); if (!data.ok) throw new Error(data.message || '업로드 실패');
      return data.rel;
    }
    // ── MD 저장
    async function saveMdToObsidian(mdText, { rsi_from, rsi_to, gap_from, gap_to, bar_preset }) {
      const res = await fetch('futures_strategy_candle_save_md.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ md: mdText, rsi_from, rsi_to, gap_from, gap_to, bar_preset })
      });
      if (!res.ok) throw new Error('MD 저장 실패');
      const data = await res.json(); if (!data.ok) throw new Error('MD 저장 실패');
      return data;
    }

    // ── 내보내기 (✅ L/M/R 기반으로 “보이는 캔버스만” 수집)
    async function exportMdDirectToObsidian() {
      const tasks = [];

      dateList.forEach((d, idx) => {
        const card = document.getElementById(`chart-l-${idx}`)?.closest('.chart-item');
        if (!card) return;

        const canvases = [...card.querySelectorAll('.chart-canvas')]
          .filter(el => el.offsetParent !== null); // 화면에 보이는 것만

        canvases.forEach(el => {
          const chart = Highcharts.charts.find(ch => ch && ch.renderTo === el);
          if (!chart) return;

          const actualInterval = chart._interval || detectIntervalBySubtitle(el);

          const svgText = chart.getSVG({
            exporting: {
              sourceWidth:  chart.chartWidth,
              sourceHeight: chart.chartHeight
            }
          });

          const nBars = chart._displayBars ?? barsFor(actualInterval);

          tasks.push(
            uploadSvgToObsidian({
              date: d.date,
              interval: actualInterval,
              index: idx,
              bars: nBars,
              svgText
            }).then(rel => ({ date: d.date, interval: actualInterval, rel }))
          );
        });
      });

      if (!tasks.length) { alert('내보낼 차트가 없습니다.'); return; }

      const uploaded = await Promise.all(tasks);
      const byDate = {};
      uploaded.forEach(it => { byDate[it.date] = byDate[it.date] || {}; byDate[it.date][it.interval] = it.rel; });

      const createdAt = new Date(); const pad = n => (n<10?'0'+n:''+n);
      const stamp = `${createdAt.getFullYear()}-${pad(createdAt.getMonth()+1)}-${pad(createdAt.getDate())} ${pad(createdAt.getHours())}:${pad(createdAt.getMinutes())}`;
      const rsi_from = <?= json_encode($rsi_from) ?>, rsi_to = <?= json_encode($rsi_to) ?>;
      const gap_from = <?= json_encode($gap_from) ?>, gap_to = <?= json_encode($gap_to) ?>;

      const meta = [
        `# 전략별 분봉 비교 (Gap ${gap_from}~${gap_to}pt)`,
        `생성: ${stamp}`, ``,
        `- 조건기준(조회): <?= htmlspecialchars($filter_interval, ENT_QUOTES) ?>`,
        `- 표시차트(렌더): <?= htmlspecialchars($chart_interval, ENT_QUOTES) ?>`,
        `- 캔들 프리셋: ${barPreset}`, ``, `---`, ``].join('\n');

      let body = '';
      dateList.forEach(d => {
        const imgs = byDate[d.date] || {};

        // ✅ 표도 60 → 15 → 5 → 1 순으로 정렬
        const columns = [];
        [['60m','60분'], ['15m','15분'], ['5m','5분'], ['1m','1분']]
          .forEach(([iv, label]) => {
            if (imgs[iv]) columns.push({ iv, label, src: imgs[iv] });
          });

        if (!columns.length) return;

        const head = '| ' + columns.map(c => c.label).join(' | ') + ' |\n'
                  + '| ' + columns.map(() => '---').join(' | ') + ' |';

        const row  = '| ' + columns.map(c => `![${c.iv}](<${c.src}>)`).join(' | ') + ' |';

        body += [
          `## ${d.date}`,
          `RSI ${d.rsi} | Gap ${d.gap>0?'+':''}${d.gap} pt | Ret ${d.ret>0?'+':''}${d.ret} pt`,
          ``,
          head,
          row,
          ``
        ].join('\n');
      });

      const mdText = meta + body;
      const saved = await saveMdToObsidian(mdText, { rsi_from, rsi_to, gap_from, gap_to, bar_preset: barPreset });

      const blob = new Blob([mdText], { type:'text/markdown;charset=utf-8' });
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      a.href = url; a.download = saved.file; document.body.appendChild(a); a.click();
      setTimeout(()=>{ URL.revokeObjectURL(url); a.remove(); },0);

      alert('저장 완료!\nMD: ' + saved.path + '\n(이미지는 Obsidian Attachments 폴더에 저장됨)');
    }

    document.getElementById('btnExportMd').addEventListener('click', () => {
      exportMdDirectToObsidian().catch(err => { console.error(err); alert('내보내기 중 오류: ' + (err?.message || err)); });
    });
  </script>
</body>
</html>
