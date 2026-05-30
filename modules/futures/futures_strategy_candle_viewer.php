<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
session_start();

// POST 우선, 없으면 GET, 없으면 기본값
function p($name, $default=null) {
  if (isset($_POST[$name])) return $_POST[$name];
  if (isset($_GET[$name]))  return $_GET[$name];
  return $default;
}

$rsi_from = p('rsi_from', 0);
$rsi_to   = p('rsi_to', 100);
$gap_from = p('gap_from', 100);
$gap_to   = p('gap_to', 100);

$filter_interval = p('filter_interval', '1m');
$chart_interval  = p('chart_interval', '1m5m');

$bar_index = max(1, (int) p('bar_index', 1));

$candle_dir        = p('candle_dir', 'all');
$candle_size_from  = p('candle_size_from', '');
$candle_size_to    = p('candle_size_to', '');
$candle_range_from = p('candle_range_from', '');
$candle_range_to   = p('candle_range_to', '');

$m5_combo    = p('m5_combo', '');
$bar_preset  = p('bar_preset', '1m200_5m75');

$show_sma_1m = (int)p('show_sma_1m', 1); // 기본 ON
$show_sma_5m = (int)p('show_sma_5m', 1); // 기본 ON

// ✅ 특정일자: 줄바꿈/스페이스/콤마 모두 허용, 중복 제거
$only_dates_raw = trim((string)p('only_dates', ''));
if ($only_dates_raw !== '') {
  $tmp = preg_split('/[,\s]+/u', $only_dates_raw, -1, PREG_SPLIT_NO_EMPTY);
  $tmp = array_values(array_unique(array_filter($tmp, fn($d)=>preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))));
  $only_dates_list = $tmp;
} else {
  $only_dates_list = [];
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
  WHERE prev_rsi14 BETWEEN ? AND ?
    AND gap_pt BETWEEN ? AND ?
";

$types  = 'idddd';
$params = [$bar_index, $rsi_from, $rsi_to, $gap_from, $gap_to];

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

if ($m5_combo !== '') {
  $map  = ['양'=>'1','음'=>'0'];
  $norm = '';
  foreach (preg_split('//u', $m5_combo, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
    if (isset($map[$ch])) $norm .= $map[$ch];
    elseif ($ch==='1' || $ch==='0') $norm .= $ch;
  }
  $len = strlen($norm);
  if ($len >= 3 && $len <= 4) {
    $cols  = ['up_5m','up_5m_2','up_5m_3','up_5m_4'];
    $parts = [];
    for ($i=0; $i<$len; $i++) { $parts[] = $cols[$i] . ' = ?'; $types .= 'i'; $params[] = (int)$norm[$i]; }
    $sql .= ' AND ' . implode(' AND ', $parts);
  }
}

// 특정 일자(IN) 조회
if (!empty($only_dates_list)) {
  $sql .= " AND date IN (" . implode(',', array_fill(0, count($only_dates_list), '?')) . ")";
  $types .= str_repeat('s', count($only_dates_list));
  $params = array_merge($params, $only_dates_list);
}

$sql .= " AND date >= '2025-01-01'";
$sql .= " ORDER BY date DESC";
// $sql .= " ORDER BY date";

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
  <title>전략별 분봉 비교</title>
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
    .chart-pair.triple { grid-template-columns: 1fr 1.5fr 1.5fr; }
    .chart-one { display:flex; flex-direction:column; min-width:0; } /* ← 줄바꿈 허용 */
    /* 5분봉 / 15분봉 배경색 */
    .chart-one.bg-5m  { background: rgba(255, 250, 205, 0.8); font-weight:bold;}  /* 연한 노랑 */
    .chart-one.bg-15m { background: rgba(213, 252, 216, 0.94); font-weight:bold;}  /* 연한 녹색 */
    .chart-subtitle { font-size:12px; color:#666; margin:0 0 4px 2px; }
    .chart-canvas { height:var(--chart-h, 350px); min-width:0; }      /* ← 컨테이너 축소 허용 */

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
    전일RSI:
    <input type="number" step="1" name="rsi_from" value="<?= htmlspecialchars($rsi_from, ENT_QUOTES) ?>" style="width:40px">~
    <input type="number" step="1" name="rsi_to"   value="<?= htmlspecialchars($rsi_to,   ENT_QUOTES) ?>" style="width:40px">

    갭(pt):
    <input type="number" step="0.1" name="gap_from" value="<?= htmlspecialchars($gap_from, ENT_QUOTES) ?>" style="width:45px">~
    <input type="number" step="0.1" name="gap_to"   value="<?= htmlspecialchars($gap_to,   ENT_QUOTES) ?>" style="width:45px">

    조건기준:
    <select name="filter_interval" id="filter_interval">
      <option value="1m"  <?= $filter_interval==='1m'  ? 'selected' : '' ?>>1분봉</option>
      <option value="5m"  <?= $filter_interval==='5m'  ? 'selected' : '' ?>>5분봉</option>
      <option value="15m" <?= $filter_interval==='15m' ? 'selected' : '' ?>>15분봉</option>
      <option value="60m" <?= $filter_interval==='60m' ? 'selected' : '' ?>>60분봉</option>
    </select>

    조건봉(몇 번째):
    <input type="number" name="bar_index"
       value="<?= htmlspecialchars($bar_index, ENT_QUOTES) ?>"
       min="1" step="1" style="width:30px">

    방향:
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
    <input type="number" name="candle_range_to"   value="<?= htmlspecialchars($candle_range_to,   ENT_QUOTES) ?>" step="0.01" style="width:30px">

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
      <option value="1m200_5m75" <?= $bar_preset==='1m200_5m75' ? 'selected' : '' ?>>1분 200 / 5분 75</option>
      <option value="40"       <?= $bar_preset==='40'       ? 'selected' : '' ?>>전체 40</option>
      <option value="75"       <?= $bar_preset==='75'       ? 'selected' : '' ?>>전체 75</option>
      <option value="100"      <?= $bar_preset==='100'      ? 'selected' : '' ?>>전체 100</option>
      <option value="160"      <?= $bar_preset==='160'      ? 'selected' : '' ?>>전체 160</option>
      <option value="240"      <?= $bar_preset==='240'      ? 'selected' : '' ?>>전체 240</option>
      <option value="420"      <?= $bar_preset==='420'      ? 'selected' : '' ?>>전체 420</option>
    </select>

    5분봉 패턴:
    <select name="m5_combo">
      <option value=""         <?= $m5_combo===''         ? 'selected' : '' ?>>전체</option>
      <option value="양양양"     <?= $m5_combo==='양양양'     ? 'selected' : '' ?>>양양양 (111)</option>
      <option value="음음음"     <?= $m5_combo==='음음음'     ? 'selected' : '' ?>>음음음 (000)</option>
      <option value="양양음"     <?= $m5_combo==='양양음'     ? 'selected' : '' ?>>양양음 (110)</option>
      <option value="양음음"     <?= $m5_combo==='양음음'     ? 'selected' : '' ?>>양음음 (100)</option>
      <option value="양양양양"   <?= $m5_combo==='양양양양'   ? 'selected' : '' ?>>양양양양 (1111)</option>
      <option value="양양양음"   <?= $m5_combo==='양양양음'   ? 'selected' : '' ?>>양양양음 (1110)</option>
      <option value="음음음음"   <?= $m5_combo==='음음음음'   ? 'selected' : '' ?>>음음음음 (0000)</option>
      <option value="음음음양"   <?= $m5_combo==='음음음양'   ? 'selected' : '' ?>>음음음양 (0001)</option>
    </select>

    특정일자(콤마):
    <textarea name="only_dates" rows="3" style="width:200px"
      placeholder="2025-10-24, 2025-10-02&#10;2025-09-19"><?= htmlspecialchars(implode(', ', $only_dates_list) ?: $only_dates_raw, ENT_QUOTES) ?></textarea>

    <!-- 1m SMA -->
    <input type="hidden" name="show_sma_1m" value="0">
    <label style="display:flex;align-items:center;gap:6px;">
      <input type="checkbox" name="show_sma_1m" value="1" <?= $show_sma_1m ? 'checked' : '' ?>>
      1mSMA
    </label>

    <!-- 5m SMA -->
    <input type="hidden" name="show_sma_5m" value="0">
    <label style="display:flex;align-items:center;gap:6px;">
      <input type="checkbox" name="show_sma_5m" value="1" <?= $show_sma_5m ? 'checked' : '' ?>>
      5mSMA
    </label>

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
    const VOL_THRESHOLD_1M = 3300;
    const VOL_THRESHOLD_5M = 12000;

    // ✅ 이평선 색상
    const SMA5_COLOR   = '#d32f2f';
    const SMA20_COLOR  = '#f9a825';
    const SMA120_COLOR = '#757575';

    const SHOW_SMA_1M = <?= json_encode((bool)$show_sma_1m) ?>;
    const SHOW_SMA_5M = <?= json_encode((bool)$show_sma_5m) ?>;
    
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
      if (barPreset === '1m200_5m75') {
        if (interval === '1m') return 200;
        if (interval === '5m') return 75;
        return 75; // 15m, 60m은 필요 시 기본값
      }
      if (barPreset === '40') return 40;
      if (barPreset === '75') return 75;
      if (barPreset === '100') return 100;
      if (barPreset === '160') return 160;
      if (barPreset === '240') return 240;
      if (barPreset === '420') return 420;
      return 75;
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
      chart.redraw();
    }

    // 캔들 수 → 카드 높이(px)
    function computeHeight(n) { if (n <= 20) return 280; if (n <= 40) return 300; if (n > 400) return 500; return 350; }

    // 화면용(span) 매핑은 기존 spanForPreset(sel) 유지
    function spanForPreset(sel) {
      if (barPreset === '1m200_5m75') return 12; // 하루당 한 줄 전체폭

      const isBoth = (sel === '1m5m' || sel === '5m15m');
      const isTriple = (sel === '1m5m15m' || sel === '5m15m60m');

      if (isBoth) {
        if (barPreset === '40')  return 4;
        if (barPreset === '75')  return 6;
        return 12;
      } else if (isTriple) {
        if (barPreset === '40')  return 4;
        return 12;
      } else {
        if (barPreset === '40')  return 2;
        if (barPreset === '75')  return 4;
        if (barPreset === '240')  return 12;
        if (barPreset === '420')  return 12;
        return 6;
      }
    }

    // 인쇄용(pspan) 매핑 – "더 넓게" 보이게 설계
    function printSpanForPreset(sel) {
      if (barPreset === '1m200_5m75') return 8; // 인쇄도 하루당 한 줄

      const isBoth = (sel === '1m5m' || sel === '5m15m' || sel === '1m5m15m' || sel === '5m15m60m');
      if (isBoth) return 4;
      if (barPreset === '420') return 8;
      if (barPreset === '240') return 8;
      if (barPreset === '160') return 8;
      if (barPreset === '100') return 8;
      if (barPreset === '75')  return 8;
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
      Highcharts.charts.forEach(ch => { if (ch) { try { ch.reflow(); } catch(e){} } });
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

      // const yAxis = chart.yAxis[0];
      // clearEarlyLines(chart);

      // const colorHi = 'rgba(255,79,179,0.95)';     // H
      // const colorLo = 'rgba(79,195,255,0.95)';     // L

      // // 15분 고/저 우선 막기. 시뮬레이션 위해 2026.03.22
      // yAxis.addPlotLine({
      //   id: 'hi30_main',
      //   value: +h30,
      //   color: colorHi,
      //   width: 2,
      //   dashStyle: 'Solid',
      //   zIndex: 5,
      //   label: {
      //     text: `H ${(+h30).toFixed(2)}`,
      //     align: 'left',
      //     style: { fontSize: '10px', color: colorHi }
      //   }
      // })
      // yAxis.addPlotLine({
      //   id: 'lo30_main',
      //   value: +l30,
      //   color: colorLo,
      //   width: 2,
      //   dashStyle: 'Solid',
      //   zIndex: 5,
      //   label: {
      //     text: `L ${(+l30).toFixed(2)}`,
      //     align: 'left',
      //     style: { fontSize: '10px', color: colorLo }
      //   }
      // });

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

      const ext = s.getExtremes();
      if (!isFinite(ext.dataMin) || !isFinite(ext.dataMax)) return;

      const range = Math.max(0.01, ext.dataMax - ext.dataMin);
      const pad = range * extraPaddingPct;

      chart.yAxis[0].setExtremes(ext.dataMin - pad, ext.dataMax + pad, true, false);
    }

    // ── 공통 차트 드로잉
    function drawChartForInterval(containerId, date, interval, opts = {}) {
      const fetchBars = barsFor(interval);
      const priceSeriesId = `price-${interval}`;

      $.getJSON(`./get_1min_data.php?date=${encodeURIComponent(date)}&interval=${encodeURIComponent(interval)}&limit=${fetchBars}`, function(data) {
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
        const viewData = (displayBars < data.length) ? data.slice(-displayBars) : data;

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
              { id:`sma120-${interval}`, type:'line', name:'SMA 120', data:sma120, color:SMA120_COLOR, lineWidth:1, zIndex:1, enableMouseTracking:false, dataGrouping:{enabled:false} },
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
            lineWidth: 3,
            zIndex: 10,
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
              load(){
                this.customShowTooltip = false;

                // ✅ 1분봉만: SMA 때문에 눌리는 느낌 해결 + 캔들 크게
                if (interval === '1m') {
                  fitYAxisToCandles(this, priceSeriesId, 0.18);
                }
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
        } else {
          full.sma5   = toLine(fullRows, 'sma_5');
          full.sma20  = toLine(fullRows, 'sma_20');
          full.sma120 = toLine(fullRows, 'sma_120');
        }

        chart._fullSeries = full;

        // 현재 표시 봉수 기록(내보내기/리사이즈 대응)
        chart._displayBars = displayBars;

        // 생성 직후 실제 plotWidth 기준으로 한 번 더 맞춤(정밀 보정)
        if (isAdaptive) {
          setTimeout(() => { try { applyAdaptiveSlice(chart); } catch(e){} }, 0);
        }

        if (el) {
          el._chart = chart;
          chart._interval = interval;
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
          const ts0930 = toTS(dayStr + ' 09:30:00');
          const ts1000 = toTS(dayStr + ' 10:00:00');
          const ts1030 = toTS(dayStr + ' 10:30:00');
          const ts1100 = toTS(dayStr + ' 11:00:00');
          const ts1130 = toTS(dayStr + ' 11:30:00');

          if (interval === '1m' || interval === '5m' ) {
            xa.addPlotLine({
              id: `_line-0900-${dayStr}-${interval}`,
              value: ts0900,
              color: 'rgba(253, 250, 38, 1)',
              width: 5,
              zIndex: 0
            });
          }

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

          xa.addPlotLine({
            id: `_line-1030-${dayStr}-${interval}`,
            value: ts1030,
            color: 'rgba(167, 248, 215, 1)',
            width: 5,
            zIndex: 2
          });


          if (interval === '1m' || interval === '5m' ) {
            xa.addPlotLine({
              id: `_line-1100-${dayStr}-${interval}`,
              value: ts1100,
              color: 'rgba(253, 250, 38, 1)',
              width: 5,
              zIndex: 0
            });
          }

          xa.addPlotLine({
            id: `_line-1130-${dayStr}-${interval}`,
            value: ts1130,
            color: 'rgba(167, 248, 215, 1)',
            width: 5,
            zIndex: 2
          });

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
        `# 전략별 분봉 비교 (RSI ${rsi_from}~${rsi_to}, Gap ${gap_from}~${gap_to}pt)`,
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
