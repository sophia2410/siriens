<?php
// strategy_calendar.php — 전략 A 백테스트 달력 + 차트 대시보드
$pageTitle = "전략 성과 달력";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

$year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year  = max(2020, min(2030, $year));
$month = max(1,    min(12,   $month));

$firstDay = new DateTime("$year-$month-01");
$lastDay  = (clone $firstDay)->modify('last day of this month');

$calendars = [];
for ($i = 0; $i < 3; $i++) {
    $ym     = (new DateTime("$year-$month-01"))->modify("+{$i} month");
    $first  = new DateTime($ym->format('Y-m-01'));
    $last   = (clone $first)->modify('last day of this month');
    $label  = $first->format('Y-m');
    $status = [];

    for ($d = 1; $d <= (int) $last->format('j'); $d++) {
        $ds = sprintf('%04d-%02d-%02d', $first->format('Y'), $first->format('n'), $d);
        $status[$ds] = 'none';

        $sql = "SELECT TIME_FORMAT(time,'%H:%i') AS t, open, close
                FROM futures_1min
                WHERE date = ? AND time IN ('08:55:00','08:59:00','09:00:00','09:01:00')";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $ds);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($rows) < 4) continue;

        $map = [];
        foreach ($rows as $r) $map[$r['t']] = $r;
        $dir = ($map['08:59']['close'] > $map['08:55']['open']) ? 1 : -1;
        $pnl = ($map['09:01']['close'] - $map['09:00']['open']) * $dir;
        $status[$ds] = $pnl > 0 ? 'success' : ($pnl < 0 ? 'fail' : 'even');
    }

    $calendars[] = [
        'label' => $label,
        'first' => $first,
        'last'  => $last,
        'status'=> $status
    ];
}

$prev = (clone $firstDay)->modify('-1 month');
$next = (clone $firstDay)->modify('+1 month');
?>

<style>
#wrapper { display: flex; height: calc(100vh - 60px); }
#side { width: 260px; padding: 10px; border-right: 1px solid #ccc; }
#side select, #side button { height: 30px; font-size: 13px; }
#calendar-wrap { display: flex; flex-direction: column; gap: 20px; }
.calendar-box table { width: 100%; border-collapse: collapse; font-size: 12px; }
.calendar-box th, .calendar-box td {
  border: 1px solid #ccc; text-align: center; height: 60px; position: relative; cursor: pointer;
}
.calendar-box td.success { background: #d4edda; }
.calendar-box td.fail    { background: #f8d7da; }
.calendar-box td.even    { background: #fff3cd; }
.calendar-box td.none    { background: #fff; }
.calendar-box div.day { position: absolute; top: 2px; right: 2px; font-size: 11px; color: #666; }
#chart-frame-wrap { flex: 1; padding: 0; border: none; }
iframe { width: 100%; height: 100%; border: none; }
</style>
<body style="margin-left:180px">
<div id="wrapper">
  <div id="side">
    <div style="display:flex;gap:6px;margin-bottom:8px;">
      <button onclick="navMonth(<?= $prev->format('Y') ?>, <?= $prev->format('n') ?>)">◀</button>
      <select id="ySel" onchange="gotoMonth()">
        <?php for ($y = 2020; $y <= 2030; $y++): ?>
          <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <select id="mSel" onchange="gotoMonth()">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $m ?>월</option>
        <?php endfor; ?>
      </select>
      <button onclick="navMonth(<?= $next->format('Y') ?>, <?= $next->format('n') ?>)">▶</button>
    </div>

    <div id="calendar-wrap">
      <?php foreach ($calendars as $cal): ?>
        <div class="calendar-box">
          <h4 style="margin:5px 0; padding-left:5px;">📅 <?= $cal['label'] ?></h4>
          <table>
            <thead><tr>
              <th>일</th><th>월</th><th>화</th><th>수</th><th>목</th><th>금</th><th>토</th>
            </tr></thead>
            <tbody>
            <?php
              $wd  = (int) $cal['first']->format('w');
              $tot = (int) $cal['last']->format('j');
              $cnt = 0;
              echo '<tr>';
              for ($i = 0; $i < $wd; $i++) { echo '<td></td>'; $cnt++; }
              for ($d = 1; $d <= $tot; $d++) {
                $ds  = sprintf('%04d-%02d-%02d', $cal['first']->format('Y'), $cal['first']->format('n'), $d);
                $cls = $cal['status'][$ds] ?? 'none';
                echo "<td class='$cls' onclick=loadChart('$ds')><div class='day'>$d</div></td>";
                if (++$cnt % 7 == 0) echo '</tr><tr>';
              }
              while ($cnt % 7 !== 0) { echo '<td></td>'; $cnt++; }
              echo '</tr>';
            ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div id="chart-frame-wrap">
    <iframe id="chartFrame" src="/modules/futures/futures_chart.php?date=<?= $firstDay->format('Y-m-d') ?>"></iframe>
  </div>
</div>

<script>
function navMonth(y, m) { location.href = `?year=${y}&month=${m}`; }
function gotoMonth()    { navMonth(ySel.value, mSel.value); }
function loadChart(date) {
  document.getElementById("chartFrame").src = `/modules/futures/futures_chart.php?date=${date}`;
}
</script>
</body>
<?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"; ?>
