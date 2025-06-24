<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$bb  = $_GET['bb'] ?? '';
$ema = $_GET['ema'] ?? '';
$rsi = $_GET['rsi'] ?? '';
$minute = $_GET['minute'] ?? '60';
$table = "futures_bb_rsi_features_{$minute}m";

$where = [
  "bb_level = '" . $mysqli->real_escape_string($bb) . "'",
  "ema_cross = '" . $mysqli->real_escape_string($ema) . "'",
  "rsi_range = '" . $mysqli->real_escape_string($rsi) . "'"
];

if (!empty($_GET['macd_hist'])) {
  $where[] = "macd_hist = '" . $mysqli->real_escape_string($_GET['macd_hist']) . "'";
}
if (isset($_GET['body_pct_min'])) {
  $where[] = "body_pct >= " . floatval($_GET['body_pct_min']);
}
if (isset($_GET['ema_bb_gap_min'])) {
  $where[] = "ema_bb_gap >= " . floatval($_GET['ema_bb_gap_min']);
}
if (!empty($_GET['bb_slope'])) {
  $where[] = "bb_slope = '" . $mysqli->real_escape_string($_GET['bb_slope']) . "'";
}

$whereSql = implode(" AND ", $where);

$sql = "
SELECT date, gap, gap_dir, next_close_dir,
       bb_width, bb_slope, bb_position, ema_gap, ema_bb_gap,
       macd, macd_hist, macd_hist_sign, macd_position, macd_vs_signal, macd_hist_change,
       rsi_14, candle_type,
       body_pct, upper_tail_pct, lower_tail_pct,
       volume
FROM {$table}
WHERE {$whereSql}
ORDER BY date DESC
LIMIT 100
";
$result = $mysqli->query($sql);
?>

<a href="futures_gap_cases_download.php?bb=<?= $bb ?>&ema=<?= $ema ?>&rsi=<?= $rsi ?>&minute=<?= $minute ?>" target="_blank">📥 CSV</a>
<table class="case-table" id="caseTable">
  <thead>
      <th onclick="sortCaseTable(0)">날짜</th>
      <th onclick="sortCaseTable(1)">갭</th>
      <th onclick="sortCaseTable(2)">시가</th>
      <th onclick="sortCaseTable(3)">60분 후</th>
      <th onclick="sortCaseTable(4)">밴드폭</th>
      <th onclick="sortCaseTable(5)">기울기</th>
      <th onclick="sortCaseTable(6)">위치</th>
      <th onclick="sortCaseTable(7)">EMA간격</th>
      <th onclick="sortCaseTable(8)">EMA-BB</th>
      <th onclick="sortCaseTable(9)">MACD</th>
      <th onclick="sortCaseTable(10)">MACD<br>HIST</th>
      <th onclick="sortCaseTable(11)">MACD<br>POSITION</th>
      <th onclick="sortCaseTable(12)">MACD<br>VS SIGNAL</th>
      <th onclick="sortCaseTable(13)">MACD-HIST<br>증감</th>
      <th onclick="sortCaseTable(14)">RSI</th>
      <th onclick="sortCaseTable(15)">캔들</th>
      <th onclick="sortCaseTable(16)">몸통%</th>
      <th onclick="sortCaseTable(17)">위꼬리%</th>
      <th onclick="sortCaseTable(18)">아래꼬리%</th>
      <th onclick="sortCaseTable(19)">거래량</th>
  </thead>
  <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td>
        <a href="#" onclick="openChartPopup('<?= $row['date'] ?>','<?= $minute ?>'); return false;">
          <?= $row['date'] ?>
        </a>
      </td>
      <td><?= $row['gap'] ?></td>
      <td><?= $row['gap_dir'] ?></td>
      <td><?= $row['next_close_dir'] ?></td>
      <td><?= round($row['bb_width'], 2) ?></td>
      <td><?= $row['bb_slope'] ?></td>
      <td><?= round($row['bb_position'],2) ?></td>
      <td><?= round($row['ema_gap'], 3) ?></td>
      <td><?= round($row['ema_bb_gap'], 3) ?></td>
      <td><?= round($row['macd'],2) ?></td>
      <td><?= round($row['macd_hist'],2) ?></td>
      <td><?= $row['macd_position'] ?></td>
      <td><?= $row['macd_vs_signal'] ?></td>
      <td><?= $row['macd_hist_change'] ?></td>
      <td><?= round($row['rsi_14'], 1) ?></td>
      <td><?= $row['candle_type'] ?></td>
      <td><?= round($row['body_pct'], 2) ?></td>
      <td><?= round($row['upper_tail_pct'], 2) ?></td>
      <td><?= round($row['lower_tail_pct'], 2) ?></td>
      <td><?= number_format($row['volume']) ?></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
