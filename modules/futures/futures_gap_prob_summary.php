<?php
$pageTitle = "갭 상승 확률 분석";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

// 선택된 분봉값
$minute = $_GET['minute'] ?? '60';
$table = "futures_bb_rsi_features_{$minute}m";

$selected = [
  'bb' => $_GET['bb'] ?? '',
  'ema' => $_GET['ema'] ?? '',
  'rsi' => $_GET['rsi'] ?? ''
];
?>

<style>
.container { display: flex; gap: 16px; }
.left-panel { width: 40%; border-right: 1px solid #ccc; padding: 12px; font-size: 13px; }
.right-panel { flex: 1; padding: 12px; font-size: 13px; }
table { border-collapse: collapse; width: 100%; margin-top: 10px; font-size: 13px; }
th, td { border: 1px solid #ccc; padding: 5px; text-align: center; }
th { background: #f0f0f0; }
select { font-size: 13px; padding: 4px; }
tr.selected-row { background-color: #ffe8a1 !important; font-weight: bold; }
</style>

<script>
function updateMinute() {
  const minute = document.getElementById("minuteSelect").value;
  window.location.href = `?minute=${minute}`;
}

function loadCases(bb, ema, rsi) {
  const q = `bb=${encodeURIComponent(bb)}&ema=${encodeURIComponent(ema)}&rsi=${encodeURIComponent(rsi)}&minute=<?= $minute ?>`;
  fetch(`futures_gap_cases.php?${q}`)
    .then(res => res.text())
    .then(html => {
      document.getElementById("casePanel").innerHTML = html;
    });

  fetch(`futures_gap_condition_stats.php?${q}`)
    .then(res => res.text())
    .then(html => {
      document.getElementById("comboStats").innerHTML = html;
    });

  // 하이라이트 갱신
  const rows = document.querySelectorAll("#comboTable tr[data-key]");
  rows.forEach(row => {
    row.classList.remove("selected-row");
    if (
      row.dataset.bb === bb &&
      row.dataset.ema === ema &&
      row.dataset.rsi === rsi
    ) {
      row.classList.add("selected-row");
    }
  });
}

function loadCasesExtra(bb, ema, rsi, field, value) {
  const params = new URLSearchParams({ bb, ema, rsi, minute: '<?= $minute ?>' });
  if (field === 'body_pct') {
    if (value.startsWith('>=')) params.append('body_pct_min', parseFloat(value.slice(2)));
    else if (value.startsWith('<')) params.append('body_pct_max', parseFloat(value.slice(1)));
  }
  if (field === 'ema_bb_gap') {
    if (value.startsWith('>=')) params.append('ema_bb_gap_min', parseFloat(value.slice(2)));
    else if (value.startsWith('<')) params.append('ema_bb_gap_max', parseFloat(value.slice(1)));
  }
  if (field === 'bb_slope') params.append('bb_slope', value);

  fetch(`futures_gap_cases.php?${params.toString()}`)
    .then(res => res.text())
    .then(html => {
      document.getElementById("casePanel").innerHTML = html;
    });
}

function sortTable(colIndex) {
  const table = document.getElementById("comboTable");
  const tbody = table.tBodies[0];
  const rows = Array.from(tbody.rows);

  const isNumeric = colIndex >= 3;
  const currentSort = table.getAttribute("data-sort-col");
  const isAsc = table.getAttribute("data-sort-asc") !== "true";

  rows.sort((a, b) => {
    const aVal = a.cells[colIndex].innerText.trim().replace('%','');
    const bVal = b.cells[colIndex].innerText.trim().replace('%','');

    const aParsed = isNumeric ? parseFloat(aVal) : aVal;
    const bParsed = isNumeric ? parseFloat(bVal) : bVal;

    if (aParsed < bParsed) return isAsc ? -1 : 1;
    if (aParsed > bParsed) return isAsc ? 1 : -1;
    return 0;
  });

  rows.forEach(row => tbody.appendChild(row));
  table.setAttribute("data-sort-col", colIndex);
  table.setAttribute("data-sort-asc", isAsc ? "true" : "false");
}

function sortCaseTable(colIndex) {
  const table = document.getElementById("caseTable");
  const thead = table.querySelector("thead");
  const tbody = table.querySelector("tbody");
  const rows = Array.from(tbody.rows);

  const textCols = [0, 2, 3, 5, 11, 12, 13, 15]; // 날짜, 시가, 60분 후, 기울기, MACD POSITION, MACD VS SIGNAL, MACD-HIST 증감, 캔들
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

function openChartPopup(date, minute) {
  const w = 1400, h = 1200;
  const left = (screen.width - w) / 2;
  const top = (screen.height - h) / 2;
  window.open(
    `./futures_gap_case_chart.php?date=${date}&interval=${minute}`,
    'bb_rsi_chart',
    `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=no`
  );
}
</script>

<div class="container">
  <!-- 왼쪽 패널 -->
  <div class="left-panel">
    <div>
      <label><b>분봉 선택:</b></label>
      <select id="minuteSelect" onchange="updateMinute()">
        <option value="15" <?= $minute==='15' ? 'selected' : '' ?>>15분</option>
        <option value="30" <?= $minute==='30' ? 'selected' : '' ?>>30분</option>
        <option value="60" <?= $minute==='60' ? 'selected' : '' ?>>60분</option>
      </select>
    </div>

    <h3 style="margin-top:16px">📊 전체 조합 리스트</h3>
    <?php
    $res = $mysqli->query("SELECT bb_level, ema_cross, rsi_range,
        COUNT(*) AS total,
        SUM(CASE WHEN gap_dir='상승' THEN 1 ELSE 0 END) AS up_cnt,
        SUM(CASE WHEN gap_dir = '하락' THEN 1 ELSE 0 END) AS down_cnt,
        ROUND(SUM(CASE WHEN gap_dir='상승' THEN 1 ELSE 0 END) * 100 / COUNT(*), 1) AS up_rate
      FROM {$table}
      GROUP BY bb_level, ema_cross, rsi_range
      ORDER BY up_rate DESC");
    ?>
    <table id="comboTable">
      <thead>
        <tr>
          <th onclick="sortTable(0)">BB</th>
          <th onclick="sortTable(1)">RSI</th>
          <th onclick="sortTable(2)">EMA</th>
          <th onclick="sortTable(3)">상승건수</th>
          <th onclick="sortTable(4)">하락건수</th>
          <th onclick="sortTable(5)">총건수</th>
          <th onclick="sortTable(6)">상승%</th>
          <th onclick="sortTable(7)">하락%</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $res->fetch_assoc()):
        $isSelected = (
          $row['bb_level'] === $selected['bb'] &&
          $row['ema_cross'] === $selected['ema'] &&
          $row['rsi_range'] === $selected['rsi']
        );
        $down_rate = round(100 - $row['up_rate'], 1);
      ?>
        <tr data-key data-bb="<?= $row['bb_level'] ?>" data-ema="<?= $row['ema_cross'] ?>" data-rsi="<?= $row['rsi_range'] ?>"
            class="<?= $isSelected ? 'selected-row' : '' ?>">
          <td><?= $row['bb_level'] ?></td>
          <td><?= $row['rsi_range'] ?></td>
          <td><?= $row['ema_cross'] ?></td>
          <td><?= $row['up_cnt'] ?></td>
          <td><?= $row['down_cnt'] ?></td>
          <td>
            <a href="#" onclick="loadCases('<?= $row['bb_level'] ?>','<?= $row['ema_cross'] ?>','<?= $row['rsi_range'] ?>'); return false;">
              <?= $row['total'] ?>
            </a>
          </td>
          <td><?= $row['up_rate'] ?>%</td>
          <td><?= $down_rate ?>%</td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>

    <div id="comboStats" style="margin-top:20px;"><i>← 조합을 선택하면 조건별 확률이 표시됩니다</i></div>
  </div>

  <!-- 오른쪽 패널 -->
  <div class="right-panel">
    <h3>📅 조건별 사례 목록</h3>
    <div id="casePanel"><i>← 조합을 선택하면 여기에 사례가 표시됩니다</i></div>
  </div>
</div>
