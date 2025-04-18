<?php
$pageTitle = "전략 조건 검색";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

$pattern     = $_GET['pattern'] ?? '';
$gap_min     = $_GET['gap_min'] ?? '';
$gap_max     = $_GET['gap_max'] ?? '';
$dir0900     = $_GET['dir0900'] ?? '';
$match_0900  = isset($_GET['match_0900']) ? 1 : null;
$match_0901  = isset($_GET['match_0901']) ? 1 : null;
$entry_dir   = $_GET['entry_dir'] ?? '';

$where = [];
if ($pattern)      $where[] = "pattern_0845_0859 = '" . $mysqli->real_escape_string($pattern) . "'";
if ($gap_min !== '') $where[] = "gap_percent >= " . floatval($gap_min);
if ($gap_max !== '') $where[] = "gap_percent <= " . floatval($gap_max);
if ($dir0900)      $where[] = "candle_0900_dir = '" . $mysqli->real_escape_string($dir0900) . "'";
if ($match_0900 !== null) $where[] = "match_last5_and_0900 = 1";
if ($match_0901 !== null) $where[] = "match_last5_and_0901 = 1";
if ($entry_dir)    $where[] = "entry_direction_a = '" . $mysqli->real_escape_string($entry_dir) . "'";

$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT date, pattern_0845_0859, gap_percent, diff_0845_0859_pt, tick_range_0845_0859, diff_0900_pt, tick_range_0900, match_last5_and_0900, match_last5_and_0901, entry_direction_a FROM futures_1day $whereSql ORDER BY date DESC LIMIT 100";
$result = $mysqli->query($sql);

$pattern_counts = [];
$res = $mysqli->query("SELECT pattern_0845_0859, COUNT(*) AS cnt FROM futures_1day GROUP BY pattern_0845_0859");
while ($row = $res->fetch_assoc()) {
  $pattern_counts[$row['pattern_0845_0859']] = $row['cnt'];
}
?>

<body style="margin-left:180px">
<?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_nav_menu.php"); ?>

<style>
body { font-family: 'Roboto', sans-serif; margin: 0; display: flex; height: 100vh; }
.left-panel { width: 40%; padding: 16px; overflow-y: auto; border-right: 1px solid #ccc; }
.right-panel { flex: 1; padding: 0; }
table { border-collapse: collapse; width: 100%; margin-top: 8px; font-size: 14px; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; }
th { background: #f0f0f0; }
iframe { width: 100%; height: 100%; border: none; }
</style>

<div class="left-panel">
  <h2>▼ 전략 조건 검색</h2>
  <form method="get" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; font-size:13px;">
  <label>패턴:
    <select name="pattern">
        <option value="">--전체--</option>
        <?php 
        $patterns = ["양양양","양양음","양음양","음양양","음음양","음양음","양음음","음음음"];
        foreach ($patterns as $p): 
            $label = isset($pattern_counts[$p]) ? "$p ({$pattern_counts[$p]}건)" : $p;
        ?>
        <option value="<?= $p ?>" <?= $pattern == $p ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    </label>

    <label>갭 (%):
      <input type="number" name="gap_min" value="<?= htmlspecialchars($gap_min) ?>" step="0.1" style="width:60px;"> ~
      <input type="number" name="gap_max" value="<?= htmlspecialchars($gap_max) ?>" step="0.1" style="width:60px;">
    </label>

    <label>09:00:
      <select name="dir0900">
        <option value="">--전체--</option>
        <option value="U" <?= $dir0900=='U' ? 'selected':'' ?>>양봉</option>
        <option value="D" <?= $dir0900=='D' ? 'selected':'' ?>>음봉</option>
      </select>
    </label>

    <label><input type="checkbox" name="match_0900" <?= $match_0900!==null ? 'checked':'' ?>> ↔ 09:00</label>
    <label><input type="checkbox" name="match_0901" <?= $match_0901!==null ? 'checked':'' ?>> ↔ 09:01</label>

    <label>방향:
      <select name="entry_dir">
        <option value="">--전체--</option>
        <option value="buy" <?= $entry_dir=='buy' ? 'selected':'' ?>>매수</option>
        <option value="sell" <?= $entry_dir=='sell' ? 'selected':'' ?>>매도</option>
      </select>
    </label>

    <button type="submit">검색</button>
  </form>

  <p style="margin-top:10px; font-size:13px;">🔍 검색 결과: <b><?= isset($result) ? $result->num_rows : 0 ?></b>건</p>

  <table>
    <tr>
        <th>날짜</th>
        <th>패턴</th>
        <th>갭%</th>
        <th>5분 등락</th>
        <th>5분 폭</th>
        <th>09:00 등락</th>
        <th>09:00 폭</th>
        <th>↔ 0900</th>
        <th>↔ 0901</th>
        <th>방향</th>
        <th>차트</th>
    </tr>
    <?php if ($result): while ($row = $result->fetch_assoc()): ?>
        <tr>
        <td><?= $row['date'] ?></td>
        <td><?= $row['pattern_0845_0859'] ?></td>
        <td><?= $row['gap_percent'] ?></td>
        <td><?= $row['diff_0845_0859_pt'] ?? '-' ?></td>
        <td><?= $row['tick_range_0845_0859'] ?? '-' ?></td>
        <td><?= $row['diff_0900_pt'] ?? '-' ?></td>
        <td><?= $row['tick_range_0900'] ?? '-' ?></td>
        <td><?= $row['match_last5_and_0900'] ? '✔' : '' ?></td>
        <td><?= $row['match_last5_and_0901'] ? '✔' : '' ?></td>
        <td><?= $row['entry_direction_a'] ?></td>
        <td><a href="#" onclick="showChart('<?= $row['date'] ?>'); return false;">📈</a></td>
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
