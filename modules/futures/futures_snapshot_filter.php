<?php
$pageTitle = "스냅샷 조건 검색";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

$dist_min      = $_GET['dist_min'] ?? '';
$pos_1day_20   = $_GET['pos_1day_20'] ?? '';

$where = [];
if ($pos_1day_20 !== '') {
  $where[] = "s.pos_1day_20 = " . intval($pos_1day_20);
}
$whereSql = count($where) ? "AND " . implode(" AND ", $where) : "";

// 1단계: 날짜 리스트 추출
$having = $dist_min !== '' ? "HAVING MAX(s.dist_pt_5min_5) >= " . floatval($dist_min) : "";
$sql_dates = "
SELECT s.date
FROM futures_snapshot_momentum s
JOIN calendar c ON s.date = c.date
WHERE 
  s.datetime BETWEEN 
    CONCAT(c.date, ' ', c.futures_start_time) AND 
    DATE_ADD(CONCAT(c.date, ' ', c.futures_start_time), INTERVAL 15 MINUTE)
  $whereSql
GROUP BY s.date
$having
ORDER BY s.date DESC
LIMIT 100";

$dates = [];
$res = $mysqli->query($sql_dates);
while ($row = $res->fetch_assoc()) {
  $dates[] = "'" . $mysqli->real_escape_string($row['date']) . "'";
}

if (count($dates)) {
  $dateListSql = implode(",", $dates);
  $sql = "
  SELECT * 
  FROM futures_snapshot_momentum 
  WHERE time = '09:00:00' 
    AND date IN ($dateListSql)
  ORDER BY date DESC";
  $result = $mysqli->query($sql);
} else {
  $result = false;
}
?>

<body style="margin-left:150px">
<?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_nav_menu.php"); ?>

<style>
body { font-family: 'Roboto', sans-serif; margin: 0; display: flex; height: 100vh; }
.left-panel { width: 18%; padding: 16px; overflow-y: auto; border-right: 1px solid #ccc; }
.right-panel { flex: 1; padding: 0; }
table { border-collapse: collapse; width: 100%; margin-top: 8px; font-size: 14px; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; }
th { background: #f0f0f0; }
iframe { width: 100%; height: 100%; border: none; }
</style>

<div class="left-panel">
  <h2>📊 스냅샷 전략 조건 검색</h2>
  <form method="get" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; font-size:13px;">

    <label>🕐 시작 15분 내 5분 이격(pt) ≥
      <input type="number" name="dist_min" value="<?= htmlspecialchars($dist_min) ?>" step="0.01" style="width:60px;">
    </label>

    <label>일봉 위치:
      <select name="pos_1day_20">
        <option value="">-전체-</option>
        <option value="1"  <?= $pos_1day_20 === '1'  ? 'selected' : '' ?>>상방</option>
        <option value="0"  <?= $pos_1day_20 === '0'  ? 'selected' : '' ?>>중립</option>
        <option value="-1" <?= $pos_1day_20 === '-1' ? 'selected' : '' ?>>하방</option>
      </select>
    </label>

    <button type="submit">검색</button>
  </form>

  <p style="margin-top:10px; font-size:13px;">🔍 검색 결과: <b><?= isset($result) ? $result->num_rows : 0 ?></b>건</p>

  <table>
    <tr>
      <th>날짜</th>
      <th>차트</th>
      <th>가격</th>
      <th>5분차 이격</th>
      <th>일봉 위치</th>
    </tr>
    <?php if ($result): while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?= $row['date'] ?></td>
      <td><a href="#" onclick="showChart('<?= $row['date'] ?>'); return false;">📈</a></td>
      <td><?= $row['price'] ?></td>
      <td><?= $row['dist_pt_5min_5'] ?></td>
      <td><?= $row['pos_1day_20'] === '1' ? '↑' : ($row['pos_1day_20'] === '-1' ? '↓' : '→') ?></td>
    </tr>
    <?php endwhile; endif; ?>
  </table>
</div>

<div class="right-panel">
  <iframe id="chartFrame" src="futures_chart.php?date="></iframe>
</div>

<script>
function showChart(date) {
  document.getElementById("chartFrame").src = "futures_chart.php?date=" + date;
}
</script>
</body>
</html>
