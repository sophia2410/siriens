<?php
$pageTitle = "패턴 유사일 검색";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

$base_date      = $_GET['base_date'] ?? null;
$first_candle   = $_GET['first_candle'] ?? '';
$above_sma5     = $_GET['above_sma5'] ?? '';

function euclideanDistance($vec1, $vec2) {
  $sum = 0;
  foreach ($vec1 as $i => $row1) {
    foreach ($row1 as $j => $val1) {
      $diff = $val1 - $vec2[$i][$j];
      $sum += $diff * $diff;
    }
  }
  return sqrt($sum);
}

$similarDates = [];

if ($base_date) {
  $sql_base = "
    SELECT dist_pt_1min_5, dist_pt_1min_20, dist_pt_1min_120
    FROM futures_snapshot_momentum
    WHERE date = '$base_date'
      AND time BETWEEN '08:45:00' AND '09:15:00'
    ORDER BY time ASC";
  $res_base = $mysqli->query($sql_base);
  $basePattern = [];
  while ($row = $res_base->fetch_assoc()) {
    $basePattern[] = array_map('floatval', $row);
  }

  $sql_dates = "
    SELECT DISTINCT date 
    FROM futures_snapshot_momentum 
    WHERE time = '09:00:00' AND date != '$base_date'
    ORDER BY date DESC";
  $res_dates = $mysqli->query($sql_dates);

  while ($r = $res_dates->fetch_assoc()) {
    $date = $r['date'];

    // 첫 5분봉 필터링
    $openSql = "
      SELECT open, close FROM futures_1min
      WHERE date = '$date' AND time = '08:45:00'
      LIMIT 1";
    $openRes = $mysqli->query($openSql);
    $openRow = $openRes->fetch_assoc();

    if ($openRow) {
      $open  = floatval($openRow['open']);
      $close = floatval($openRow['close']);

      if ($first_candle === 'up' && $close <= $open) continue;
      if ($first_candle === 'down' && $close >= $open) continue;
    }

    if ($above_sma5 !== '') {
      $smaSql = "
        SELECT price, sma_1min_5 FROM futures_snapshot_momentum
        WHERE date = '$date' AND time = '09:00:00'
        LIMIT 1";
      $smaRes = $mysqli->query($smaSql);
      $smaRow = $smaRes->fetch_assoc();

      if ($smaRow) {
        $price = floatval($smaRow['price']);
        $sma5  = floatval($smaRow['sma_1min_5']);

        if ($above_sma5 === '1' && $price <= $sma5) continue;
        if ($above_sma5 === '0' && $price >= $sma5) continue;
      }
    }

    $compareSql = "
      SELECT dist_pt_1min_5, dist_pt_1min_20, dist_pt_1min_120
      FROM futures_snapshot_momentum
      WHERE date = '$date'
        AND time BETWEEN '08:45:00' AND '09:15:00'
      ORDER BY time ASC";
    $res_cmp = $mysqli->query($compareSql);
    $cmpPattern = [];
    while ($cmp = $res_cmp->fetch_assoc()) {
      $cmpPattern[] = array_map('floatval', $cmp);
    }

    if (count($cmpPattern) === count($basePattern)) {
      $distance = euclideanDistance($basePattern, $cmpPattern);
      $similarDates[] = ['date' => $date, 'distance' => $distance];
    }
  }

  usort($similarDates, fn($a, $b) => $a['distance'] <=> $b['distance']);
  $similarDates = array_slice($similarDates, 0, 10);
}
?>

<body style="margin-left:150px">
<?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_nav_menu.php"); ?>

<style>
body { font-family: 'Roboto', sans-serif; margin: 0; display: flex; height: 100vh; }
.left-panel { width: 15%; padding: 16px; overflow-y: auto; border-right: 1px solid #ccc; }
.right-panel { flex: 1; padding: 0; }
table { border-collapse: collapse; width: 100%; margin-top: 8px; font-size: 14px; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; }
th { background: #f0f0f0; }
iframe { width: 100%; height: 100%; border: none; }
</style>

<div class="left-panel">
  <h2>🔍 기준일 패턴 유사일 검색</h2>
  <form method="get" style="font-size:13px; display:flex; flex-direction:column; gap:10px;">
    <label>기준일:
      <input type="date" name="base_date" value="<?= htmlspecialchars($base_date) ?>">
    </label>

    <label>첫 5분봉:
      <select name="first_candle">
        <option value="">-전체-</option>
        <option value="up" <?= $first_candle === 'up' ? 'selected' : '' ?>>양봉</option>
        <option value="down" <?= $first_candle === 'down' ? 'selected' : '' ?>>음봉</option>
      </select>
    </label>

    <label>5일선 대비:
      <select name="above_sma5">
        <option value="">-무관-</option>
        <option value="1" <?= $above_sma5 === '1' ? 'selected' : '' ?>>위</option>
        <option value="0" <?= $above_sma5 === '0' ? 'selected' : '' ?>>아래</option>
      </select>
    </label>

    <button type="submit">검색</button>
  </form>

  <?php if ($base_date): ?>
    <p style="margin-top:10px; font-size:13px;">기준일: <a href="#" onclick="showChart('<?= $base_date ?>'); return false;" style="text-decoration: none; color: inherit;"><b><?= $base_date ?></b></a></p>
    <table>
      <tr><th>유사일</th><th>차트</th><th>거리</th></tr>
      <?php foreach ($similarDates as $match): ?>
        <tr>
          <td><?= $match['date'] ?></td>
          <td><a href="#" onclick="showChart('<?= $match['date'] ?>'); return false;">📈</a></td>
          <td><?= number_format($match['distance'], 3) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<div class="right-panel">
  <iframe id="chartFrame" src=""></iframe>
</div>

<script>
function showChart(date) {
  document.getElementById("chartFrame").src = "futures_chart.php?date=" + date;
}

<?php if ($base_date): ?>
window.addEventListener("DOMContentLoaded", function() {
  showChart("<?= $base_date ?>");
});
<?php endif; ?>
</script>
</body>
</html>
