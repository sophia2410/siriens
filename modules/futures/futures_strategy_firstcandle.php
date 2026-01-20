<?php

// futures_strategy_candle_viewer.php에서 확장. 해당 페이지 제거 예정

require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php"; // 공통 DB 연결

// ✅ 필터 값 처리
$rsi_from = $_GET['rsi_from'] ?? 0;
$rsi_to   = $_GET['rsi_to'] ?? 100;
$gap_from = $_GET['gap_from'] ?? 100;
$gap_to   = $_GET['gap_to'] ?? 100;
$filter_interval = $_GET['filter_interval'] ?? '1m'; // 조회 조건 기준 분봉
$chart_interval = $_GET['chart_interval'] ?? $filter_interval; // 차트 표시용 분봉
$first_candle_dir = $_GET['first_candle_dir'] ?? 'all'; // all / up / down
$first_candle_size_from = $_GET['first_candle_size_from'] ?? '';
$first_candle_size_to = $_GET['first_candle_size_to'] ?? '';
$first_candle_range_from = $_GET['first_candle_range_from'] ?? '';
$first_candle_range_to = $_GET['first_candle_range_to'] ?? '';
$m5_combo = $_GET['m5_combo'] ?? '';

// ✅ 조건에 맞는 날짜 데이터 조회
$col_dir = $filter_interval === '5m' ? 'up_5m' : 'up_1m';
$col_size = $filter_interval === '5m' ? 'ret_5m' : 'ret_1m';
$col_range = $filter_interval === '5m' ? 'range_5m' : 'range_1m';

$sql = "
  SELECT date, ROUND(prev_rsi14,1) as prev_rsi14, gap_pt, $col_size as ret
  FROM rule_based_rowdata
  WHERE prev_rsi14 BETWEEN ? AND ?
    AND gap_pt BETWEEN ? AND ?
";

$params = [$rsi_from, $rsi_to, $gap_from, $gap_to];
$types = 'dddd';

if ($first_candle_dir !== 'all') {
  $sql .= " AND $col_dir = ?";
  $types .= 'i';
  $params[] = ($first_candle_dir === 'up') ? 1 : 0;
}
if ($first_candle_size_from !== '') {
  $sql .= " AND $col_size >= ?";
  $types .= 'd';
  $params[] = $first_candle_size_from;
}
if ($first_candle_size_to !== '') {
  $sql .= " AND $col_size <= ?";
  $types .= 'd';
  $params[] = $first_candle_size_to;
}
if ($first_candle_range_from !== '') {
  $sql .= " AND $col_range >= ?";
  $types .= 'd';
  $params[] = $first_candle_range_from;
}
if ($first_candle_range_to !== '') {
  $sql .= " AND $col_range <= ?";
  $types .= 'd';
  $params[] = $first_candle_range_to;
}

if ($m5_combo !== '') {
  // 한글/숫자 매핑
  $map = [
    '양' => '1', '음' => '0'
  ];

  // 글자 단위 변환 (유니코드)
  $norm = '';
  foreach (preg_split('//u', $m5_combo, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
    if (isset($map[$ch]))      $norm .= $map[$ch];
    elseif ($ch === '1' || $ch === '0') $norm .= $ch;
    // 그 외 문자는 무시
  }

  // 3~4자리만 허용
  $len = strlen($norm);
  if ($len >= 3 && $len <= 4) {
    $cols = ['up_5m','up_5m_2','up_5m_3','up_5m_4'];
    $parts = [];
    for ($i = 0; $i < $len; $i++) {
      $parts[] = $cols[$i] . ' = ?';
      $types  .= 'i';
      $params[] = (int)$norm[$i]; // '1' or '0'
    }
    $sql .= ' AND ' . implode(' AND ', $parts);
  }
}

$sql .= " ORDER BY date DESC LIMIT 500";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
while ($row = $result->fetch_assoc()) {
  $dates[] = [
    'date' => $row['date'],
    'rsi' => $row['prev_rsi14'],
    'gap' => $row['gap_pt'],
    'ret' => $row['ret']
  ];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>전략별 분봉 비교</title>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body {
      margin: 0; padding: 10px; font-family: sans-serif;
    }
    .filter-box {
      margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
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

    /* ✅ 인쇄용 스타일: 3열(한 줄 3개) + 카드 쪼개짐 방지 */
    @media print {
      /* 페이지 방향/여백 */
      @page { size: A4 portrait; margin: 10mm; }

      /* 🔧 열(칸) 수를 조절하려면 아래 repeat(3, 1fr)의 숫자 3을 바꾸세요.
         예) 2열: repeat(2, 1fr) / 4열: repeat(4, 1fr) */
      .chart-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr); /* ← 여기 숫자가 "칸 수" */
        gap: 8mm;                               /* 카드 간 간격 */
      }

      .chart-item {
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        break-inside: avoid-page;   /* 카드가 페이지 중간에서 쪼개지지 않도록 */
        page-break-inside: avoid;
      }

      .chart-title,
      .chart-item [id^="chart-"],
      .chart-item .highcharts-container,
      .chart-item .highcharts-root,
      .chart-item svg {
        break-inside: avoid-page;
        page-break-inside: avoid;
      }

      /* 필터/버튼/불필요 요소 인쇄 제외 */
      .filter-box,
      #filterForm,
      button,
      .highcharts-range-selector,
      .highcharts-exporting-group {
        display: none !important;
      }

      /* 색상 유지 */
      body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      /* 🔧 인쇄용 차트 높이: 칸 수가 늘면 값을 줄이세요.
         예) 2열이면 200~240px / 3열이면 160~200px 권장 */
      .chart-item [id^="chart-"] {
        height: 180px !important; /* ← 여기 숫자가 "인쇄 시 차트 높이" */
      }

      .chart-title {
        margin: 0 0 4mm !important;
        font-size: 12px !important;
        color: #000 !important;
      }
    }
  </style>
</head>
<body>
  <form method="get" class="filter-box" id="filterForm">
    전일 RSI:
    <input type="number" step="1" name="rsi_from" value="<?= $rsi_from ?>" style="width:50px"> ~
    <input type="number" step="1" name="rsi_to" value="<?= $rsi_to ?>" style="width:50px">

    갭(pt):
    <input type="number" step="0.1" name="gap_from" value="<?= $gap_from ?>" style="width:50px"> ~
    <input type="number" step="0.1" name="gap_to" value="<?= $gap_to ?>" style="width:50px">
    /
    조건기준:
    <select name="filter_interval" id="filter_interval">
      <option value="1m" <?= $filter_interval === '1m' ? 'selected' : '' ?>>1분봉</option>
      <option value="5m" <?= $filter_interval === '5m' ? 'selected' : '' ?>>5분봉</option>
      <option value="60m" <?= $filter_interval === '60m' ? 'selected' : '' ?>>60분봉</option>
    </select>
    표시차트:
    <select name="chart_interval" id="chart_interval">
      <option value="1m" <?= $chart_interval === '1m' ? 'selected' : '' ?>>1분봉</option>
      <option value="5m" <?= $chart_interval === '5m' ? 'selected' : '' ?>>5분봉</option>
      <option value="60m" <?= $chart_interval === '60m' ? 'selected' : '' ?>>60분봉</option>
    </select>

    첫 캔들:
    <select name="first_candle_dir">
      <option value="all" <?= $first_candle_dir === 'all' ? 'selected' : '' ?>>전체</option>
      <option value="up" <?= $first_candle_dir === 'up' ? 'selected' : '' ?>>양봉</option>
      <option value="down" <?= $first_candle_dir === 'down' ? 'selected' : '' ?>>음봉</option>
    </select>

    크기:
    <input type="number" name="first_candle_size_from" value="<?= $first_candle_size_from ?>" step="0.01" style="width:50px"> ~
    <input type="number" name="first_candle_size_to" value="<?= $first_candle_size_to ?>" step="0.01" style="width:50px">

    최대변동:
    <input type="number" name="first_candle_range_from" value="<?= $first_candle_range_from ?>" step="0.01" style="width:50px"> ~
    <input type="number" name="first_candle_range_to" value="<?= $first_candle_range_to ?>" step="0.01" style="width:50px">

    5분봉 패턴:
    <select name="m5_combo">
      <option value="" <?= $m5_combo === '' ? 'selected' : '' ?>>전체</option>
      <option value="양양양" <?= $m5_combo === '양양양' ? 'selected' : '' ?>>양양양 (111)</option>
      <option value="음음음" <?= $m5_combo === '음음음' ? 'selected' : '' ?>>음음음 (000)</option>
      <option value="양양음" <?= $m5_combo === '양양음' ? 'selected' : '' ?>>양양음 (110)</option>
      <option value="양음음" <?= $m5_combo === '양음음' ? 'selected' : '' ?>>양음음 (100)</option>
      <option value="양양양양"   <?= $m5_combo === '양양양양'   ? 'selected' : '' ?>>양양양양 (1111)</option>
      <option value="양양양음"   <?= $m5_combo === '양양양음'   ? 'selected' : '' ?>>양양양음 (1110)</option>
      <option value="음음음음"   <?= $m5_combo === '음음음음'   ? 'selected' : '' ?>>음음음음 (0000)</option>
      <option value="음음음양"   <?= $m5_combo === '음음음양'   ? 'selected' : '' ?>>음음음양 (0001)</option>
    </select>

    <button type="submit">조회</button>
  </form>

  <div class="chart-grid">
    <?php foreach ($dates as $i => $d): ?>
      <div class="chart-item">    
        <div class="chart-title">
          <a href="./futures_strategy_firstcandle_1m5m.php?date=<?= urlencode($d['date']) ?>" onclick="return openIntradayPopup(this.href);" style="text-decoration:none;">
          🗕️
          </a>
          <?= $d['date'] ?> | RSI <?= $d['rsi'] ?> | Gap <?= ($d['gap'] > 0 ? '+' : '') . $d['gap'] ?> pt | Ret <?= ($d['ret'] > 0 ? '+' : '') . $d['ret'] ?> pt
        </div>
        <div id="chart-<?= $i ?>" style="height:300px;"></div>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    const dateList = <?= json_encode($dates) ?>;
    const interval = '<?= $chart_interval ?>';

    // 조건기준 선택 시 표시기준 자동 동기화
    document.getElementById('filter_interval').addEventListener('change', function () {
      document.getElementById('chart_interval').value = this.value;
    });

    // 아래 drawChart와 차트 표시 관련 코드는 기존 유지됨

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

        const volumeMax = interval === '1m' ? 8000 : 30000;

        const chart = Highcharts.stockChart('chart-' + idx, {
          chart: {
            height: 280,
            zooming: { mouseWheel: { enabled: false }, type: null },
            panning: false,
            panKey: null,
            events: {
              load() { this.customShowTooltip = false; } // 클릭 모드 플래그 초기화
            }
          },
          mapNavigation: { enabled: false },
          tooltip: {
            shared: true,
            split: false,
            useHTML: true,
            formatter: function () {
              const chart =
                (this.points && this.points.length && this.points[0].series && this.points[0].series.chart)
                  ? this.points[0].series.chart
                  : (this.point && this.point.series ? this.point.series.chart : null);

              if (!chart || !chart.customShowTooltip) return false;

              // 문자열 이스케이프 함수
              const esc = s => (s == null ? '' : String(s)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'));

              const time = Highcharts.dateFormat('%Y-%m-%d %H:%M', this.x, false);
              let html = `<b>${time}</b><br/>`;

              const pts = this.points || [this.point];
              pts.forEach(p => {
                const sname = esc(p.series.name || '');
                if (p.series.type === 'candlestick') {
                  const o = Highcharts.numberFormat(p.point.open, 2);
                  const h = Highcharts.numberFormat(p.point.high, 2);
                  const l = Highcharts.numberFormat(p.point.low, 2);
                  const c = Highcharts.numberFormat(p.point.close, 2);
                  html += `<span style="font-weight:600">${sname}</span>: O ${o} / H ${h} / L ${l} / C ${c}<br/>`;
                } else {
                  html += `${sname}: ${Highcharts.numberFormat(p.y, p.series.type === 'column' ? 0 : 2)}<br/>`;
                }
              });

              return html;
            }
          },
          navigator: { enabled: false },
          scrollbar: { enabled: false },
          rangeSelector: { enabled: false },
          title: { text: '' },
          time: { useUTC: false },
          xAxis: {
            type: 'datetime',
            labels: { format: '{value:%H:%M}' },
            crosshair: { width: 1, color: '#888', dashStyle: 'ShortDot' }
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
              max: volumeMax
            }
          ],
          series: [
            { type: 'candlestick', name: 'Price', data: candles },
            { type: 'line', name: 'SMA 5', data: sma5, color: '#db1bb4' },
            { type: 'line', name: 'SMA 20', data: sma20, color: '#ffaa00' },
            { type: 'column', name: 'Volume', data: volume, yAxis: 1 }
          ],
          plotOptions: {
            series: {
              states: { hover: { enabled: false } }
            },
            candlestick: {
              color: '#2f7ed8',
              upColor: '#f45b5b',
              lineColor: '#2f7ed8',
              upLineColor: '#f45b5b'
            }
          }
        });

        // 헬퍼: 캔들/컬럼 rect 히트 체크
        function hitRect(point, ev) {
          if (!point || !point.shapeArgs) return false;
          const sa = point.shapeArgs;
          const x0 = (sa.x ?? 0) + chart.plotLeft;
          const y0 = (sa.y ?? 0) + chart.plotTop;
          const x1 = x0 + (sa.width ?? 0);
          const y1 = y0 + (sa.height ?? 0);
          return ev.chartX >= x0 && ev.chartX <= x1 && ev.chartY >= y0 && ev.chartY <= y1;
        }
        // 헬퍼: 라인/포인트 근접성 체크
        function nearPoint(point, ev, thresholdPx = 24) {
          if (!point) return false;
          if (hitRect(point, ev)) return true;
          if (typeof point.plotX !== 'number' || typeof point.plotY !== 'number') return false;
          const px = point.plotX + chart.plotLeft;
          const py = point.plotY + chart.plotTop;
          const dx = ev.chartX - px;
          const dy = ev.chartY - py;
          return Math.hypot(dx, dy) <= thresholdPx;
        }
        // 클릭 위치에서 포인트 찾기
        function getPointsAtEvent(e) {
          const ev = chart.pointer.normalize(e);
          if (!chart.isInsidePlot(ev.chartX - chart.plotLeft, ev.chartY - chart.plotTop, { series: true })) {
            return [];
          }
          const candidates = chart.series
            .filter(s => s.visible && s.options.enableMouseTracking !== false)
            .map(s => s.searchPoint(ev, true))
            .filter(Boolean);

          const nearOnes = candidates.filter(p => nearPoint(p, ev));
          if (!nearOnes.length) return [];

          const xVal = nearOnes[0].x;
          return chart.series
            .filter(s => s.visible && s.points)
            .map(s => s.points.find(pt => pt && pt.x === xVal))
            .filter(Boolean);
        }

        // 클릭 이벤트
        chart.container.addEventListener('click', function (e) {
          const ev = chart.pointer.normalize(e);
          const pts = getPointsAtEvent(e);
          if (pts.length) {
            chart.customShowTooltip = true;
            chart.tooltip.refresh(pts, ev);
            chart.xAxis[0].drawCrosshair(ev, pts[0]);
          } else {
            chart.customShowTooltip = false;
            chart.tooltip.hide(0);
            chart.xAxis[0].hideCrosshair();
          }
        });

        // 차트 밖 클릭 시 닫기
        document.addEventListener('click', function (e) {
          if (!chart.container.contains(e.target)) {
            chart.customShowTooltip = false;
            chart.tooltip.hide(0);
            chart.xAxis[0].hideCrosshair();
          }
        });

        // 마우스 이동 시 hover 툴팁 방지
        chart.container.addEventListener('mousemove', function () {
          if (!chart.customShowTooltip) chart.tooltip.hide(0);
        });
      });
    }

    dateList.forEach((d, idx) => drawChart(idx, d.date));
    // ✅ 인쇄 전/후 훅: 툴팁/크로스헤어 숨기고 레이아웃 안정화
    (function(){
      function hideAllChartOverlays() {
        if (!Highcharts || !Highcharts.charts) return;
        Highcharts.charts.forEach(ch => {
          if (!ch) return;
          ch.customShowTooltip = false;
          try { ch.tooltip && ch.tooltip.hide(0); } catch(e){}
          try { ch.xAxis && ch.xAxis[0] && ch.xAxis[0].hideCrosshair(); } catch(e){}
        });
      }
      function reflowCharts() {
        if (!Highcharts || !Highcharts.charts) return;
        Highcharts.charts.forEach(ch => { try { ch.reflow(); } catch(e){} });
      }

      if (window.matchMedia) {
        const mq = window.matchMedia('print');
        // 일부 브라우저는 addListener만 지원
        (mq.addEventListener ? mq.addEventListener('change', onChange) : mq.addListener(onChange));
        function onChange(mql) {
          if (mql.matches) { hideAllChartOverlays(); reflowCharts(); }
          else { setTimeout(reflowCharts, 100); }
        }
      }
      window.onbeforeprint = () => { hideAllChartOverlays(); reflowCharts(); };
      window.onafterprint  = () => { setTimeout(reflowCharts, 100); };
    })();


    // 1,5분봉 모아서 보기 팝업 
    const POPUP_W = 1500;
    const POPUP_H = 480;

    function openIntradayPopup(url, name = 'intraday_1m5m') {
      const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
      const dualScreenTop  = window.screenTop  ?? window.screenY ?? 0;

      const w = window.innerWidth  || document.documentElement.clientWidth  || screen.width;
      const h = window.innerHeight || document.documentElement.clientHeight || screen.height;

      // 화면 중앙 배치
      const left = dualScreenLeft + Math.max(0, (w - POPUP_W) / 2);
      const top  = dualScreenTop  + Math.max(0, (h - POPUP_H) / 2);

      const features = [
        `width=${POPUP_W}`,
        `height=${POPUP_H}`,
        `left=${left}`,
        `top=${top}`,
        'menubar=no',
        'toolbar=no',
        'location=no',
        'status=no',
        'resizable=yes',
        'scrollbars=yes'
      ].join(',');

      // 같은 이름(name)으로 열면 중복 생성 대신 재사용/포커스
      const win = window.open(url, name, features);

      // 팝업 차단 시 새 탭 fallback
      if (!win) {
        window.open(url, '_blank', 'noopener,noreferrer');
        return false;
      }
      try { win.focus(); } catch (e) {}
      return false; // 기본 링크 이동 막기
    }
  </script>

</body>
</html>