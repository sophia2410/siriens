<?php
// strategy_calendar.php — 전략 A 백테스트용 달력 및 차트 대시보드
$pageTitle = "전략 성과 달력";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php";

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = max(2020, min(2030, $year));
$month = max(1, min(12, $month));

$firstDay = new DateTime("$year-$month-01");
$lastDay = (clone $firstDay)->modify('last day of this month');

function get_day_result(string $date): ?int {
  global $mysqli;
  $sql = "SELECT TIME_FORMAT(time,'%H:%i') AS t, open, close
          FROM futures_1min
          WHERE date = ?
          AND time IN ('08:55:00','08:59:00','09:00:00','09:01:00')";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('s', $date);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  if (count($rows) < 4) return null;
  $map = [];
  foreach ($rows as $r) $map[$r['t']] = $r;
  foreach (['08:55','08:59','09:00','09:01'] as $k) {
    if (!isset($map[$k])) return null;
  }
  $dir = ($map['08:59']['close'] > $map['08:55']['open']) ? 1 : -1;
  $pnl = ($map['09:01']['close'] - $map['09:00']['open']) * $dir;
  return $pnl > 0 ? 1 : ($pnl < 0 ? -1 : 0);
}

$statusMap = [];
for ($d = 1; $d <= (int)$lastDay->format('j'); $d++) {
  $ds = sprintf('%04d-%02d-%02d', $year, $month, $d);
  $r = get_day_result($ds);
  if ($r !== null) $statusMap[$ds] = $r === 1 ? 'success' : ($r === -1 ? 'fail' : 'breakeven');
}
$prevMonth = (clone $firstDay)->modify('-1 month');
$nextMonth = (clone $firstDay)->modify('+1 month');
?>

<style>
#container { display: flex; height: calc(100vh - 60px); margin-left: 100px; width: calc(100% - 100px); }
#nav-area { display: flex; gap: 6px; margin-bottom: 8px; }
#calendar { border-collapse: collapse; width: 100%; font-size: 14px; }
#calendar th { background: #f2f2f2; padding: 6px; border: 1px solid #ddd; }
#calendar td { width: 14%; height: 80px; text-align: center; vertical-align: top; border: 1px solid #ddd; position: relative; cursor: pointer; }
#calendar td div.day { position: absolute; top: 4px; right: 4px; font-size: 11px; color: #555; }
#calendar td.success { background: #d4edda; }
#calendar td.fail { background: #f8d7da; }
#calendar td.breakeven { background: #fff3cd; }
#chart-area { flex: 1; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 10px; padding: 10px; }
#chart-area > div { border: 1px solid #ccc; min-height: 240px; }
</style>

<div id="container">
  <div style="width: 260px; padding: 10px; border-right: 1px solid #ccc;">
    <div id="nav-area">
      <button onclick="navMonth(<?= $prevMonth->format('Y') ?>, <?= $prevMonth->format('n') ?>)">◀</button>
      <select id="yearSel" onchange="gotoMonth()">
        <?php for($y=2020;$y<=2030;$y++): ?>
        <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <select id="monthSel" onchange="gotoMonth()">
        <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= $m ?>월</option>
        <?php endfor; ?>
      </select>
      <button onclick="navMonth(<?= $nextMonth->format('Y') ?>, <?= $nextMonth->format('n') ?>)">▶</button>
    </div>
    <table id="calendar">
      <thead><tr><th>일</th><th>월</th><th>화</th><th>수</th><th>목</th><th>금</th><th>토</th></tr></thead>
      <tbody>
        <?php
        $wd = (int)$firstDay->format('w');
        $tot = (int)$lastDay->format('j');
        $cnt = 0;
        echo '<tr>';
        for ($i=0; $i<$wd; $i++) { echo '<td></td>'; $cnt++; }
        for ($d=1; $d<=$tot; $d++) {
          $ds = sprintf('%04d-%02d-%02d', $year, $month, $d);
          $cls = $statusMap[$ds] ?? '';
          echo "<td class='$cls' onclick=loadCharts('$ds')><div class='day'>$d</div></td>";
          $cnt++;
          if ($cnt % 7 === 0) echo '</tr><tr>';
        }
        while ($cnt % 7 !== 0) { echo '<td></td>'; $cnt++; }
        echo '</tr>'; ?>
      </tbody>
    </table>
  </div>
  <div id="chart-area">
    <div id="chart-day"></div>
    <div id="chart-15m"></div>
    <div id="chart-5m"></div>
    <div id="chart-1m"></div>
  </div>
</div>

<script src="https://code.highcharts.com/stock/highstock.js"></script>
<script src="https://code.highcharts.com/stock/indicators/indicators.js"></script>
<script src="https://code.highcharts.com/stock/indicators/sma.js"></script>
<script>
function navMonth(y,m){ location.href = `?year=${y}&month=${m}`; }
function gotoMonth(){ navMonth(yearSel.value, monthSel.value); }
async function fetchCandle(dateStr, tf){
  const res = await fetch(`get_candles.php?date=${dateStr}&tf=${tf}`);
  if (!res.ok) return [];
  try { return await res.json(); } catch(e){ return []; }
}

function renderChart(divId, dataArr) {
  const el = document.getElementById(divId);

  if (el.chart) {
    el.chart.destroy();
    el.chart = null;
  }

  if (!Array.isArray(dataArr) || dataArr.length === 0 || !Array.isArray(dataArr[0])) {
    el.innerHTML = '<p style="text-align:center;padding-top:40px;font-size:13px;color:#888;">No data</p>';
    return;
  }

  el.innerHTML = '';

  el.chart = Highcharts.stockChart(el, {
    chart: { height: 250 },
    rangeSelector: { enabled: false },
    title: { text: '' },

    xAxis: {
      type: 'datetime',
      min: dataArr[0][0],
      max: dataArr[dataArr.length - 1][0]
    },
    yAxis: {
      startOnTick: false,
      endOnTick: false
    },

    tooltip: {
      split: false,
      shared: true,
      valueDecimals: 2,
      pointFormat: '<b>O:</b> {point.open} <b>H:</b> {point.high} <b>L:</b> {point.low} <b>C:</b> {point.close}'
    },

    series: [{
      type: 'candlestick',
      id: 'price',
      name: 'Price',
      data: dataArr
    }]
  });
}


async function loadCharts(dateStr){

    // const d1 = await fetchCandle(dateStr,'1day');
    // console.log('1day:', d1);  // ← 실제 응답 확인
    // renderChart('chart-day', d1);

  const [d1,d15,d5,d1m] = await Promise.all([
    fetchCandle(dateStr,'1day'),
    fetchCandle(dateStr,'15m'),
    fetchCandle(dateStr,'5m'),
    fetchCandle(dateStr,'1m')
  ]);
  renderChart('chart-day', d1);
  renderChart('chart-15m', d15);
  renderChart('chart-5m', d5);
  renderChart('chart-1m', d1m);
}

loadCharts('2024-07-10')
</script>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/modules/common/common_footer.php'; ?>