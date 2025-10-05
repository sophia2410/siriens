<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

function quoteIfNeeded($val) {
  if (is_numeric($val)) return $val;
  return "'" . addslashes($val) . "'";
}

$date = $_GET['date'] ?? null;
$selectedDate = null;

if ($date) {
  $selectedDate = $mysqli->real_escape_string($date);
  $sql = "
    SELECT *
    FROM futures_60min
    WHERE date = '{$selectedDate}'
    ORDER BY datetime DESC
    LIMIT 1
  ";
  $row = $mysqli->query($sql)->fetch_assoc();
  if ($row) {
    $_GET['close'] = $row['close'];
    $_GET['rsi'] = $row['rsi_14'];
    $_GET['ema20'] = $row['ema_20'];
    $_GET['ema60'] = $row['ema_60'];
    $_GET['bb_center'] = $row['bb_center'];
    $_GET['bb_upper'] = $row['bb_upper'];
    $_GET['bb_lower'] = $row['bb_lower'];
  }
}

$close = floatval($_GET['close'] ?? 0);
$rsi = floatval($_GET['rsi'] ?? 0);
$ema20 = floatval($_GET['ema20'] ?? 0);
$ema60 = floatval($_GET['ema60'] ?? 0);
$bb_center = floatval($_GET['bb_center'] ?? 0);
$bb_upper = floatval($_GET['bb_upper'] ?? 0);
$bb_lower = floatval($_GET['bb_lower'] ?? 0);

$bb_width = $bb_upper - $bb_lower;
$bb_position = $bb_width > 0 ? ($close - $bb_lower) / $bb_width : 0;
$bb_level =
    $bb_position > 1.0  ? '상단 위' :
    ($bb_position >= 0.85 ? '상단 근처' :
    ($bb_position >= 0.6 ? '중심선 위' :
    ($bb_position >= 0.4 ? '중심선 근처' :
    ($bb_position >= 0.15 ? '중심선 아래' :
    ($bb_position >= 0.0 ? '하단 근처' : '하단 아래')))));

$bb_slope = $bb_center > $ema60 ? '상승' : ($bb_center < $ema60 ? '하락' : '수평');
$ema_cross = $ema20 > $ema60 ? '정배열' : '역배열';
$rsi_range = $rsi >= 70 ? '과열' : ($rsi >= 50 ? '강세' : ($rsi >= 30 ? '중립' : '과매도'));

$ema_gap = $ema20 - $ema60;
$ema_bb_gap = $ema20 - $bb_center;
$index_vs_ema20 = $close - $ema20;
$index_vs_ema60 = $close - $ema60;

$strategySql = "
  SELECT id, name, exp_dir, description
  FROM futures_bb_rsi_strategy
  WHERE bb_level = '{$bb_level}'
    AND rsi_range = '{$rsi_range}'
    AND ema_cross = '{$ema_cross}'
";
$res = $mysqli->query($strategySql);

$rise = []; $fall = [];
while ($row = $res->fetch_assoc()) {
  $sid = $row['id'];
  ob_start();
  echo "<b>📘 {$row['name']}</b> (전략 {$sid})<br>";
  echo nl2br(htmlspecialchars($row['description'])) . "<br>";
  echo "<pre>WHERE\n";

  $group_sqls = [];
  $cres = $mysqli->query("SELECT group_id, feature_name, operator, value1, value2 FROM futures_bb_rsi_strategy_conditions WHERE strategy_id = $sid ORDER BY group_id ASC, id ASC");
  $groups = [];
  while ($r = $cres->fetch_assoc()) {
    $gid = $r['group_id'];
    if (!isset($groups[$gid])) $groups[$gid] = [];
    $op = strtoupper($r['operator']);
    $v1 = quoteIfNeeded($r['value1']);
    $v2 = quoteIfNeeded($r['value2']);
    $cond = $op === 'BETWEEN' ? "({$r['feature_name']} BETWEEN $v1 AND $v2)" : "({$r['feature_name']} $op $v1)";
    $groups[$gid][] = $cond;
  }
  if (!empty($groups)) {
    echo "  AND (\n";
    $group_clauses = [];
    foreach ($groups as $conds) {
      $group_clauses[] = "    (" . implode(" AND ", $conds) . ")";
    }
    echo implode(" OR\n", $group_clauses) . "\n";
    echo "  )";
  }
  echo "</pre>";
  $desc = ob_get_clean();

  if ($row['exp_dir'] === '상승') {
    $rise[] = $desc;
  } else {
    $fall[] = $desc;
  }
}

$summarySql = "
  SELECT bb_level, ema_cross, rsi_range,
         COUNT(*) AS total,
         SUM(CASE WHEN gap_dir='상승' THEN 1 ELSE 0 END) AS up_cnt,
         SUM(CASE WHEN gap_dir='하락' THEN 1 ELSE 0 END) AS down_cnt,
         ROUND(SUM(CASE WHEN gap_dir='상승' THEN 1 ELSE 0 END) * 100 / COUNT(*), 1) AS up_rate
  FROM futures_bb_rsi_features_60m
  WHERE bb_level = '{$bb_level}' AND ema_cross = '{$ema_cross}' AND rsi_range = '{$rsi_range}'
  " . ($selectedDate ? "AND date != '{$selectedDate}'" : "") . "
";
$summaryRes = $mysqli->query($summarySql);
$summaryRow = $summaryRes->fetch_assoc();

$case_url = "futures_gap_cases.php?bb=" . urlencode($bb_level)
          . "&ema=" . urlencode($ema_cross)
          . "&rsi=" . urlencode($rsi_range)
          . "&minute=60";
if ($selectedDate) {
  $case_url .= "&exclude_date=" . urlencode($selectedDate);
}

$stmt_prev = $mysqli->prepare("SELECT MAX(date) FROM calendar WHERE date < ?");
$stmt_prev->bind_param('s', $date);
$stmt_prev->execute();
$stmt_prev->bind_result($prev_date);
$stmt_prev->fetch();
$stmt_prev->close();

$stmt_next = $mysqli->prepare("SELECT MIN(date) FROM calendar WHERE date > ?");
$stmt_next->bind_param('s', $date);
$stmt_next->execute();
$stmt_next->bind_result($next_date);
$stmt_next->fetch();
$stmt_next->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>⬆️ 전략 진입 기준 설정</title>
  <style>
    body { font-family: sans-serif; }
    .container { display: flex; }
    .left, .center, .right {
      padding: 10px;
      border: 1px solid #ccc;
      margin: 5px;
    }
    .left { width: 30%; display: flex; flex-direction: column; gap: 20px; }
    .center { width: 25%; }
    .right { flex: 1; }
    input { width: 100px; margin: 3px; }
    h3 { margin-top: 10px; }
    pre {
      background: #f6f6f6;
      padding: 10px;
      font-size: 13px;
      overflow-x: auto;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      font-size: 13px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 5px;
      text-align: center;
    }
    th { background: #f0f0f0; }
    .box { border: 1px solid #ddd; padding: 10px; background: #fff; }
  </style>
</head>
<form id="dateForm" method="get" style="padding: 10px; background: #f9f9f9; border-bottom: 1px solid #ccc;">
  📅 날짜 선택:
  <input type="date" name="date" id="date-input" value="<?= htmlspecialchars($date ?? '') ?>">
  <button type="submit">조회</button>
  &nbsp;
  <?php if ($prev_date): ?>
    <button type="button" onclick="moveToDate('<?= $prev_date ?>')">◀ 이전</button>
  <?php endif; ?>
  <?php if ($next_date): ?>
    <button type="button" onclick="moveToDate('<?= $next_date ?>')">다음 ▶</button>
  <?php endif; ?>
</form>

<div class="container">
  <div class="left">
    <div class="box">
      <h3>📅 현재 지표 입력</h3>
      📉 종가: <input name="close" type="text" value="<?= htmlspecialchars($_GET['close'] ?? '') ?>"><br>
      📈 RSI: <input name="rsi" type="text" value="<?= htmlspecialchars($_GET['rsi'] ?? '') ?>"><br>
      📘 EMA20: <input name="ema20" type="text" value="<?= htmlspecialchars($_GET['ema20'] ?? '') ?>"><br>
      📙 EMA60: <input name="ema60" type="text" value="<?= htmlspecialchars($_GET['ema60'] ?? '') ?>"><br>
      🎓 BB 중심선: <input name="bb_center" type="text" value="<?= htmlspecialchars($_GET['bb_center'] ?? '') ?>"><br>
      🔺 BB 상단: <input name="bb_upper" type="text" value="<?= htmlspecialchars($_GET['bb_upper'] ?? '') ?>"><br>
      🔻 BB 하단: <input name="bb_lower" type="text" value="<?= htmlspecialchars($_GET['bb_lower'] ?? '') ?>">
    </div>
    <!-- 값 출력 -->
    <div class="box">
    <h3>📏 계산된 지표 값</h3>
    <table>
        <tr>
            <th>bb_width</th>
            <th>bb_position</th>
            <th>ema_gap</th>
            <th>ema_bb_gap</th>
            <th>index_vs_ema20</th>
            <th>index_vs_ema60</th>
        </tr>
        <tr>
            <td><?= round($bb_width, 3) ?></td>
            <td><?= round($bb_position, 1) ?></td>
            <td><?= round($ema_gap, 3) ?></td>
            <td><?= round($ema_bb_gap, 3) ?></td>
            <td><?= round($index_vs_ema20, 3) ?></td>
            <td><?= round($index_vs_ema60, 3) ?></td>
        </tr>
    </table>
    </div>

    <div class="box">
      <h3>📈 조건별 통계</h3>
      <?php if ($summaryRow): ?>
        <table>
          <tr><th>BB</th><th>RSI</th><th>EMA</th><th>총건수</th><th>상승건수</th><th>하락건수</th><th>상승%</th><th>하락%</th></tr>
          <tr>
            <td><?= $summaryRow['bb_level'] ?></td>
            <td><?= $summaryRow['rsi_range'] ?></td>
            <td><?= $summaryRow['ema_cross'] ?></td>
            <td><?= $summaryRow['total'] ?></td>
            <td><?= $summaryRow['up_cnt'] ?></td>
            <td><?= $summaryRow['down_cnt'] ?></td>
            <td><?= $summaryRow['up_rate'] ?>%</td>
            <td><?= 100 - floatval($summaryRow['up_rate']) ?>%</td>
          </tr>
        </table>
      <?php else: ?>
        <i>데이터 없음</i>
      <?php endif; ?>
    </div>

    <div class="box">
      <h3>📉 상승 조건</h3>
      <?= empty($rise) ? '-' : implode("<hr>", $rise) ?>
      <hr>
      <h3>📈 하락 조건</h3>
      <?= empty($fall) ? '-' : implode("<hr>", $fall) ?>
    </div>
  </div>

  <div class="right">
    <h3>🕛 일자별 사례</h3>
    <div id="casePanel"><i>로딩 중...</i></div>
  </div>
</div>

<script>
function moveToDate(d) {
  document.getElementById('date-input').value = d;
  document.getElementById('dateForm').submit();
}

window.addEventListener('DOMContentLoaded', () => {
  fetch("<?= $case_url ?>")
    .then(res => res.text())
    .then(html => {
      document.getElementById("casePanel").innerHTML = html;
    });
});

window.addEventListener('message', e => {
  if (e.data.type === 'indicator_data') {
    const d = e.data.payload;
    document.querySelector('[name=close]').value = d.close;
    document.querySelector('[name=rsi]').value = d.rsi;
    document.querySelector('[name=ema20]').value = d.ema20;
    document.querySelector('[name=ema60]').value = d.ema60;
    document.querySelector('[name=bb_center]').value = d.bb_center;
    document.querySelector('[name=bb_upper]').value = d.bb_upper;
    document.querySelector('[name=bb_lower]').value = d.bb_lower;
  }
});

function openChartPopup(date, minute) {
  const w = 1440, h = 1220;
  const left = (screen.width - w) / 2;
  const top = (screen.height - h) / 2;
  window.open(
    `./futures_chart_BB.php?date=${date}&interval=${minute}`,
    'bb_rsi_chart',
    `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=no`
  );
}


function sortCaseTable(colIndex) {
  const table = document.getElementById("caseTable");
  const thead = table.querySelector("thead");
  const tbody = table.querySelector("tbody");
  const rows = Array.from(tbody.rows);

  const textCols = [0, 2, 3, 6, 10, 16, 17, 18]; // 날짜, 시가, 60분 후, 캔들, 기울기, MACD POSITION, MACD VS SIGNAL, MACD-HIST 증감
  const isNumeric = !textCols.includes(colIndex);

  const currentSort = table.getAttribute("data-sort-col");
  const isAsc = table.getAttribute("data-sort-asc") !== "true";

  // 정렬
  rows.sort((a, b) => {
    const aVal = a.cells[colIndex].innerText.trim().replace(',', '').replace('%', '');
    const bVal = b.cells[colIndex].innerText.trim().replace(',', '').replace('%', '');

    if (isNumeric) {
      const aNum = parseFloat(aVal);
      const bNum = parseFloat(bVal);
      return isAsc ? aNum - bNum : bNum - aNum;
    } else {
      return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    }
  });

  rows.forEach(row => tbody.appendChild(row));
  table.setAttribute("data-sort-col", colIndex);
  table.setAttribute("data-sort-asc", isAsc ? "true" : "false");

  // ▲▼ 표시 업데이트
  const ths = thead.querySelectorAll("th");
  ths.forEach((th, i) => {
    const label = th.innerText.replace(/[\u25B2\u25BC]/g, '').trim(); // 기존 화살표 제거
    if (i === colIndex) {
      th.innerText = label + (isAsc ? " ▲" : " ▼");
    } else {
      th.innerText = label;
    }
  });
}
</script>
</body>
</html>
