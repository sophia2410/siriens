<!-- 20분봉 고중저 3레벨 전략 분석용 화면 -->
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
$bar_index = max(1, (int)p('bar_index', 1));

$candle_dir        = p('candle_dir', 'all');
$candle_size_from  = p('candle_size_from', '');
$candle_size_to    = p('candle_size_to', '');
$candle_range_from = p('candle_range_from', '');
$candle_range_to   = p('candle_range_to', '');

$break_base    = p('break_base', '5m');   // '5m' or '1m'
$confirm_next  = (int)p('confirm_next', 1); // 1: 다음봉 유지 확인(기본 ON)

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

// 특정 일자(IN) 조회
if (!empty($only_dates_list)) {
  $sql .= " AND date IN (" . implode(',', array_fill(0, count($only_dates_list), '?')) . ")";
  $types .= str_repeat('s', count($only_dates_list));
  $params = array_merge($params, $only_dates_list);
}

$sql .= " AND date >= '2025-01-01'";
// $sql .= " ORDER BY date DESC";

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
  <title>60분봉 레벨 전략(고/저/중간) 분석</title>
  <?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/highcharts.php"; ?>
  <script src="https://code.highcharts.com/modules/exporting.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <style>
    body { margin:0; padding:10px; font-family:sans-serif; }
    .filter-box { margin-bottom:16px; display:flex; flex-wrap:wrap; gap:12px; align-items:center; }

    .cards { display:flex; flex-direction:column; gap:14px; }

    .card {
      border:1px solid #ccc; padding:10px; background:#fff; border-radius:8px;
      box-shadow:0 2px 6px rgba(0,0,0,.06);
    }

    .card-title {
      font-weight:700; font-size:14px; color:#222; margin:0 0 8px 0;
      display:flex; flex-wrap:wrap; gap:10px; align-items:center;
    }
    .pill { font-size:12px; padding:2px 8px; border-radius:999px; background:#f3f4f6; }
    .pill strong { margin-right:4px; }

    .grid2{
      display:grid;
      grid-template-columns: 1fr 1fr 1.6fr; /* 5m 조금 넓게 */
      gap:10px;
    }

    .cell { min-width:0; border:1px solid #eee; border-radius:6px; padding:6px; background:#fafafa; }
    .cell-head { font-size:12px; color:#444; margin:0 0 4px 2px; font-weight:700; }
    .chart { min-width:0; }

    .h-top    { height: 360px; }   /* 상단 3개 */
    .h-bottom { height: 400px; }   /* 하단 1분봉 길게 */
    .span-all { grid-column: 1 / -1; } /* 1분봉: 전체 폭 */

    @media print {
      @page { size: A4 portrait; margin: 10mm; }
      .filter-box, button { display:none !important; }
      .card { break-inside: avoid-page; page-break-inside: avoid; }
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>

<form method="post" accept-charset="utf-8" class="filter-box" id="filterForm">
  전일 RSI:
  <input type="number" step="1" name="rsi_from" value="<?= htmlspecialchars($rsi_from, ENT_QUOTES) ?>" style="width:50px"> ~
  <input type="number" step="1" name="rsi_to"   value="<?= htmlspecialchars($rsi_to,   ENT_QUOTES) ?>" style="width:50px">

  갭(pt):
  <input type="number" step="0.1" name="gap_from" value="<?= htmlspecialchars($gap_from, ENT_QUOTES) ?>" style="width:70px"> ~
  <input type="number" step="0.1" name="gap_to"   value="<?= htmlspecialchars($gap_to,   ENT_QUOTES) ?>" style="width:70px">

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
    min="1" step="1" style="width:55px">

  방향:
  <select name="candle_dir">
    <option value="all"  <?= $candle_dir==='all'  ? 'selected' : '' ?>>전체</option>
    <option value="up"   <?= $candle_dir==='up'   ? 'selected' : '' ?>>양봉</option>
    <option value="down" <?= $candle_dir==='down' ? 'selected' : '' ?>>음봉</option>
  </select>

  크기:
  <input type="number" name="candle_size_from" value="<?= htmlspecialchars($candle_size_from, ENT_QUOTES) ?>" step="0.01" style="width:70px">~
  <input type="number" name="candle_size_to"   value="<?= htmlspecialchars($candle_size_to,   ENT_QUOTES) ?>" step="0.01" style="width:70px">

  최대변동:
  <input type="number" name="candle_range_from" value="<?= htmlspecialchars($candle_range_from, ENT_QUOTES) ?>" step="0.01" style="width:70px">~
  <input type="number" name="candle_range_to"   value="<?= htmlspecialchars($candle_range_to,   ENT_QUOTES) ?>" step="0.01" style="width:70px">

  특정일자(콤마/줄바꿈):
  <textarea name="only_dates" rows="2" style="width:320px"
    placeholder="2025-10-24, 2025-10-02&#10;2025-09-19"><?= htmlspecialchars(implode(', ', $only_dates_list) ?: $only_dates_raw, ENT_QUOTES) ?></textarea>

  <!-- 1m SMA -->
  <input type="hidden" name="show_sma_1m" value="0">
  <label style="display:flex;align-items:center;gap:6px;">
    <input type="checkbox" name="show_sma_1m" value="1" <?= $show_sma_1m ? 'checked' : '' ?>>
    1m SMA 표시
  </label>

  <!-- 5m SMA -->
  <input type="hidden" name="show_sma_5m" value="0">
  <label style="display:flex;align-items:center;gap:6px;">
    <input type="checkbox" name="show_sma_5m" value="1" <?= $show_sma_5m ? 'checked' : '' ?>>
    5m SMA 표시
  </label>

  <button type="submit">조회</button>
</form>

<div class="cards">
  <?php foreach ($dates as $i => $d): ?>
    <div class="card" data-date="<?= htmlspecialchars($d['date'], ENT_QUOTES) ?>" id="card-<?= $i ?>">
      <div class="card-title" id="title-<?= $i ?>">
        <span style="font-size:15px;">📅 <?= htmlspecialchars($d['date']) ?></span>
        <span class="pill"><strong>RSI</strong><?= htmlspecialchars($d['rsi']) ?></span>
        <span class="pill"><strong>Gap</strong><?= ($d['gap']>0?'+':'') . htmlspecialchars($d['gap']) ?> pt</span>
        <span class="pill"><strong>Ret</strong><?= ($d['ret']>0?'+':'') . htmlspecialchars($d['ret']) ?> pt</span>
        <span class="pill" id="lvl-<?= $i ?>">레벨 로딩...</span>
      </div>

      <div class="grid2">
        <div class="cell">
            <div class="cell-head">60분 (최근 5거래일+당일)</div>
            <div id="c60-<?= $i ?>" class="chart h-top"></div>
        </div>
        <div class="cell">
            <div class="cell-head">15분 (전일+당일)</div>
            <div id="c15-<?= $i ?>" class="chart h-top"></div>
        </div>
        <div class="cell">
            <div class="cell-head">5분 (당일)</div>
            <div id="c5-<?= $i ?>" class="chart h-top"></div>
        </div>

        <div class="cell span-all">
            <div class="cell-head">1분 (~12:45)</div>
            <div id="c1-<?= $i ?>" class="chart h-bottom"></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script>
  const dateList = <?= json_encode($dates) ?>;

  const SHOW_SMA_1M = <?= json_encode((bool)$show_sma_1m) ?>;
  const SHOW_SMA_5M = <?= json_encode((bool)$show_sma_5m) ?>;

  // ✅ Highcharts 전체 옵션: 마우스휠/핀치 줌 비활성화(한 번만)
  Highcharts.setOptions({
    chart: {
      zooming: { mouseWheel: { enabled: false } },
      pinchType: ''
    }
  });

  function toTSLocal(dateStr, timeStr){
    // 'YYYY-MM-DD', 'HH:MM:SS' => local ms
    const s = `${dateStr} ${timeStr}`;
    const m = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/.exec(s);
    if (!m) return Date.parse(s.replace(' ', 'T'));
    const [_, Y,M,D,h,mi,sc] = m.map(Number);
    return new Date(Y, M-1, D, h, mi, sc).getTime();
  }

  function toCandle(rows){
    return rows.map(r => [ +r.ts, +r.open, +r.high, +r.low, +r.close ]);
  }

  function toLine(rows, key){
    const out = [];
    for (const r of rows){
      const v = r[key];
      if (v === null || v === undefined || v === '') continue;
      out.push([ +r.ts, +v ]);
    }
    return out;
  }

  function toVolume(rows){
    return rows.map(r => ({
      x:+r.ts,
      y:+(r.volume ?? 0),
      color: (+r.close > +r.open) ? '#f45b5b' : '#2f7ed8'
    }));
  }

  function isVwapInterval(interval){
    return interval === '1m' || interval === '5m' || interval === '15m';
  }

  function fitYAxisToCandles(chart, candleSeriesId, extraPaddingPct = 0.18) {
    const s = chart.get(candleSeriesId);
    if (!s) return;

    const ext = s.getExtremes();
    if (!isFinite(ext.dataMin) || !isFinite(ext.dataMax)) return;

    let min = ext.dataMin;
    let max = ext.dataMax;

    const range = Math.max(0.01, max - min);
    const pad = range * extraPaddingPct;

    chart.yAxis[0].setExtremes(min - pad, max + pad, true, false);
  }

  function renderChart(containerId, dateStr, interval, payload){
    const rows = (payload?.data?.[interval] || []);
    if (!rows.length) return;

    const meta = payload.meta || {};

    const openPrice = (meta.session_open != null) ? +meta.session_open : +rows[0].open;
    const lvlH = (meta.session_high30 != null) ? +meta.session_high30 : null;
    const lvlL = (meta.session_low30  != null) ? +meta.session_low30  : null;
    const lvlM = (meta.session_mid30  != null) ? +meta.session_mid30  : null;

    const candles = toCandle(rows);
    const volume  = toVolume(rows);

    const sma5    = toLine(rows, 'sma_5');
    const sma20   = toLine(rows, 'sma_20');
    const sma120  = toLine(rows, 'sma_120');

    const vwap    = toLine(rows, 'vwap_session');

    const is1m = (interval === '1m');
    const useVwap = isVwapInterval(interval);

    const COLOR_H = 'rgba(255,79,179,0.95)';     // H
    const COLOR_L = 'rgba(79,195,255,0.95)';     // L

    const ts0915 = toTSLocal(dateStr, '09:15:00');
    const ts0930 = toTSLocal(dateStr, '09:30:00');
    const ts1120 = toTSLocal(dateStr, '11:20:00');
    const ts1530 = toTSLocal(dateStr, '15:30:00');

    const candleId = `price-${containerId}`; // ✅ card 여러 개라 id 충돌 방지

    // ✅ 지표 시리즈
    // - 1m/5m: VWAP + SMA(5/20/120)
    // - 15m/60m: SMA만
    // ✅ 1m/5m만 SMA 표시 여부를 조회조건으로 제어
    const showSma =
      (interval === '1m') ? SHOW_SMA_1M :
      (interval === '5m') ? SHOW_SMA_5M :
      true; // 15m/60m은 항상 SMA

    const indicatorSeries = [
      // ✅ SMA (조건부)
      ...(showSma ? (
          (interval === '1m' || interval === '5m')
            // ✅ 1m/5m: 5/20선만
            ? [
                { id:`SMA20-${containerId}`, type:'line', name:'SMA 20', data:sma20, color:'#ffaa00', lineWidth:1, zIndex:1, enableMouseTracking:false },
                { id:`SMA5-${containerId}`,   type:'line', name:'SMA 5',   data:sma5,   color:'#db1bb4',   lineWidth:2, zIndex:1, enableMouseTracking:false }
              ]
            // ✅ 15m/60m: 5/20/120 전부
            : [
                { id:`SMA120-${containerId}`, type:'line', name:'SMA 120', data:sma120, color:'#666666cc', lineWidth:1, zIndex:1, enableMouseTracking:false },
                { id:`SMA20-${containerId}`,  type:'line', name:'SMA 20',  data:sma20,  color:'#ffaa00',   lineWidth:1, zIndex:1, enableMouseTracking:false },
                { id:`SMA5-${containerId}`,   type:'line', name:'SMA 5',   data:sma5,   color:'#db1bb4',   lineWidth:2, zIndex:1, enableMouseTracking:false }
              ]
        ) : []),

      // ✅ VWAP은 1m/5m에서만(항상 표시)
      ...(useVwap ? [{
        id:`VWAP-${containerId}`,
        type:'line',
        name:'VWAP (Session)',
        data:vwap,
        color:'rgba(115, 15, 182, 0.89)',
        lineWidth:2,
        zIndex:1,
        enableMouseTracking:false
      }] : [])
    ];


    Highcharts.stockChart(containerId, {
      chart: {
        height: null,
        zoomType: '',
        panning: false,
        pinchType: '',
        events: {
          load() {
            // ✅ 1분봉에서만: 120선 때문에 눌리는 문제 해결(현 구조 유지)
            if (interval === '1m') {
              fitYAxisToCandles(this, candleId, 0.18);
            }
          }
        },
        zooming: {
          type: undefined,
          mouseWheel: { enabled: false }
        }
      },
      exporting: { enabled: false },
      navigator: { enabled: false },
      scrollbar: { enabled: false },
      rangeSelector: { enabled: false },
      title: { text: '' },
      time: { useUTC: false },
      tooltip: { enabled: false },

      xAxis: {
        type: 'datetime',
        ordinal: true,
        min: is1m ? toTSLocal(dateStr, '08:45:00') : undefined,
        max: is1m ? toTSLocal(dateStr, '13:30:00') : undefined,
        labels: { style: { fontSize: '9px' } },
        crosshair: { width: 1, color: '#888', dashStyle: 'ShortDot' },

        plotLines: [
          {
            id: `t0915-${containerId}`,
            value: ts0915,
            color: 'rgba(253, 250, 38, 1)',
            width: 5,
            zIndex: 2,
            label: { text: '09:15', align: 'left', x: 5, style: { fontSize: '10px', color: '#0a7a5a', fontWeight: 'bold' } }
          },
          {
            id: `t0930-${containerId}`,
            value: ts0930,
            color: 'rgba(167, 248, 215, 1)',
            width: 5,
            zIndex: 2,
            label: { text: '09:30', align: 'left', x: 5, style: { fontSize: '10px', color: '#0a7a5a', fontWeight: 'bold' } }
          },
          {
            id: `t1120-${containerId}`,
            value: ts1120,
            color: 'rgba(167, 248, 215, 1)',
            width: 5,
            zIndex: 2,
            label: { text: '11:20', align: 'left', x: 5, style: { fontSize: '10px', color: '#0a7a5a', fontWeight: 'bold' } }
          },
          // {
          //   id: `t1145-${containerId}`,
          //   value: ts1530,
          //   color: 'rgba(167, 248, 215, 1)',
          //   width: 5,
          //   zIndex: 2,
          //   label: { text: '15:30', align: 'left', x: 5, style: { fontSize: '10px', color: '#0a7a5a', fontWeight: 'bold' } }
          // }
        ]
      },

      yAxis: [
        {
          height: '80%',
          lineWidth: 1,

          // ✅ 추가: 틱 라운딩/기본 여백 최소화
          startOnTick: false,
          endOnTick: false,
          minPadding: 0.01,
          maxPadding: 0.01,

          plotLines: [
            { // 시가
              value: openPrice,
              color: 'gray',
              width: 1,
              dashStyle: 'Dash',
              zIndex: 3,
              label: { text: `시가 ${openPrice.toFixed(2)}`, align: 'left', x: 5, style: { color:'#555', fontSize:'11px' } }
            },
            ...(lvlH==null?[]:[{
              value: lvlH, color: COLOR_H, width: 2, zIndex: 4,
              label: { text: `H ${lvlH.toFixed(2)}`, align:'left', x:5, style:{ fontSize:'11px', color: COLOR_H } }
            }]),
            ...(lvlL==null?[]:[{
              value: lvlL, color: COLOR_L, width: 2, zIndex: 4,
              label: { text: `L ${lvlL.toFixed(2)}`, align:'left', x:5, style:{ fontSize:'11px', color: COLOR_L } }
            }]),
          ]
        },
        {
          top: '83%', height: '17%', offset: 0, lineWidth: 1,
          min: 0
        }
      ],

      series: [
        // ✅ 지표(조건 분기)
        ...indicatorSeries,

        // 가격/거래량
        { id: candleId, type:'candlestick', name:'Price', data:candles, zIndex:3, dataGrouping:{ enabled:false } },
        { type:'column', name:'Volume', data:volume, yAxis:1, zIndex:0 }
      ],

      plotOptions: {
        candlestick: { color:'#2f7ed8', upColor:'#f45b5b', lineColor:'#2f7ed8', upLineColor:'#f45b5b' }
      }
    });
  }

  function loadAndRenderCard(idx, dateStr){
    $.getJSON(`./get_level_bundle.php?date=${encodeURIComponent(dateStr)}`, function(payload){
      const meta = payload?.meta || {};
      if (meta.lvl_high != null) {
        const txt = `레벨(H/L): ${(+meta.lvl_high).toFixed(2)} / ${(+meta.lvl_low).toFixed(2)}`;
        const el = document.getElementById(`lvl-${idx}`);
        if (el) el.textContent = txt;
      } else {
        const el = document.getElementById(`lvl-${idx}`);
        if (el) el.textContent = '레벨 없음';
      }

      renderChart(`c60-${idx}`, dateStr, '60m', payload);
      renderChart(`c15-${idx}`, dateStr, '15m', payload);
      renderChart(`c5-${idx}`,  dateStr, '5m',  payload);
      renderChart(`c1-${idx}`,  dateStr, '1m',  payload);
    });
  }

  dateList.forEach((d, idx) => loadAndRenderCard(idx, d.date));
</script>

</body>
</html>
