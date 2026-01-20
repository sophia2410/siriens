<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php"; // 공통 DB 연결

// ✅ 조회할 일자(YYYY-MM-DD). 미지정 시 오늘
$date = $_GET['date'] ?? date('Y-m-d');

// 간단한 형식 검증(유효하지 않으면 오늘로 대체)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  $date = date('Y-m-d');
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>특정일자 1분봉/5분봉 차트</title>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body { margin: 0; padding: 10px; font-family: sans-serif; }
    .filter-box {
      margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
    }
    .charts {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
      gap: 16px;
    }
    .chart-card {
      border: 1px solid #ccc; background: #fff; border-radius: 6px;
      padding: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .chart-title { font-weight: bold; margin-bottom: 6px; font-size: 14px; color: #333; }
  </style>
</head>
<body>
  <!-- ✅ 간단 필터: 날짜만 선택 -->
  <form method="get" class="filter-box" id="filterForm">
    조회일자:
    <input type="date" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES) ?>">
    <button type="submit">조회</button>
  </form>

  <div class="charts">
    <div class="chart-card">
      <div class="chart-title">📈 <?= htmlspecialchars($date) ?> | 1분봉</div>
      <div id="chart-1m" style="height:320px;"></div>
    </div>
    <div class="chart-card">
      <div class="chart-title">📈 <?= htmlspecialchars($date) ?> | 5분봉</div>
      <div id="chart-5m" style="height:320px;"></div>
    </div>
  </div>

  <script>
    const queryDate = '<?= $date ?>';

    // 공통 차트 드로어
    function drawChart(containerId, interval) {
      $.getJSON(`./get_1min_data.php?date=${encodeURIComponent(queryDate)}&interval=${encodeURIComponent(interval)}&limit=100`, function(data) {
        if (!Array.isArray(data) || data.length === 0) {
          document.getElementById(containerId).innerHTML =
            '<div style="padding:12px;color:#666">데이터가 없습니다.</div>';
          return;
        }

        const toNum = v => (v == null ? null : parseFloat(v));
        const ts = r => new Date(r.datetime).getTime();

        const openPrice = toNum(data[0].open);

        const candles = data.map(r => [ ts(r), toNum(r.open), toNum(r.high), toNum(r.low), toNum(r.close) ]);
        const sma5    = data.map(r => [ ts(r), toNum(r.sma_5) ]);
        const sma20   = data.map(r => [ ts(r), toNum(r.sma_20) ]);
        const volume  = data.map(r => ({
          x: ts(r), y: toNum(r.volume),
          color: toNum(r.close) > toNum(r.open) ? '#f45b5b' : '#2f7ed8'
        }));

        const volumeMax = (interval === '1m') ? 8000 : 30000;

        const chart = Highcharts.stockChart(containerId, {
          chart: {
            height: 300,
            zooming: { mouseWheel: { enabled: false }, type: null },
            panning: false, panKey: null,
            events: { load(){ this.customShowTooltip = false; } }
          },
          mapNavigation: { enabled: false },
          tooltip: {
            shared: true, split: false, useHTML: true,
            formatter: function () {
              const chart =
                (this.points && this.points.length && this.points[0].series && this.points[0].series.chart)
                ? this.points[0].series.chart
                : (this.point && this.point.series ? this.point.series.chart : null);
              if (!chart || !chart.customShowTooltip) return false;

              const esc = s => (s == null ? '' : String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
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
              height: '70%', lineWidth: 1,
              plotLines: [{
                color: 'gray', value: openPrice, width: 1, dashStyle: 'Dash',
                label: { text: `시가 ${openPrice}`, align: 'right', style: { color: '#666', fontSize: '11px' } }
              }]
            },
            { top: '75%', height: '25%', offset: 0, lineWidth: 1, min: 0, max: volumeMax }
          ],
          series: [
            { type: 'candlestick', name: 'Price', data: candles },
            { type: 'line', name: 'SMA 5', data: sma5, color: '#db1bb4' },
            { type: 'line', name: 'SMA 20', data: sma20, color: '#ffaa00' },
            { type: 'column', name: 'Volume', data: volume, yAxis: 1 }
          ],
          plotOptions: {
            series: { states: { hover: { enabled: false } } },
            candlestick: { color: '#2f7ed8', upColor: '#f45b5b', lineColor: '#2f7ed8', upLineColor: '#f45b5b' }
          }
        });

        // === 클릭-툴팁 모드(기존 로직 유지) ===
        function hitRect(point, ev) {
          if (!point || !point.shapeArgs) return false;
          const sa = point.shapeArgs;
          const x0 = (sa.x ?? 0) + chart.plotLeft;
          const y0 = (sa.y ?? 0) + chart.plotTop;
          const x1 = x0 + (sa.width ?? 0);
          const y1 = y0 + (sa.height ?? 0);
          return ev.chartX >= x0 && ev.chartX <= x1 && ev.chartY >= y0 && ev.chartY <= y1;
        }
        function nearPoint(point, ev, thresholdPx = 24) {
          if (!point) return false;
          if (hitRect(point, ev)) return true;
          if (typeof point.plotX !== 'number' || typeof point.plotY !== 'number') return false;
          const px = point.plotX + chart.plotLeft;
          const py = point.plotY + chart.plotTop;
          const dx = ev.chartX - px, dy = ev.chartY - py;
          return Math.hypot(dx, dy) <= thresholdPx;
        }
        function getPointsAtEvent(e) {
          const ev = chart.pointer.normalize(e);
          if (!chart.isInsidePlot(ev.chartX - chart.plotLeft, ev.chartY - chart.plotTop, { series: true })) return [];
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
        document.addEventListener('click', function (e) {
          if (!chart.container.contains(e.target)) {
            chart.customShowTooltip = false;
            chart.tooltip.hide(0);
            chart.xAxis[0].hideCrosshair();
          }
        });
        chart.container.addEventListener('mousemove', function () {
          if (!chart.customShowTooltip) chart.tooltip.hide(0);
        });
      }).fail(function() {
        document.getElementById(containerId).innerHTML =
          '<div style="padding:12px;color:#c00">데이터 요청에 실패했습니다.</div>';
      });
    }

    // ✅ 두 차트 그리기
    drawChart('chart-1m', '1m');
    drawChart('chart-5m', '5m');
  </script>
</body>
</html>
