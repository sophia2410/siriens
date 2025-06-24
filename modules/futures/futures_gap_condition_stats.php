<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
?>

<?php
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
$whereBase = implode(" AND ", $where);

function getRate($mysqli, $table, $where, $extraCondition) {
  $sql = "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN gap_dir = '상승' THEN 1 ELSE 0 END) AS up,
            SUM(CASE WHEN gap_dir = '하락' THEN 1 ELSE 0 END) AS down
          FROM {$table}
          WHERE {$where} AND {$extraCondition}";
  $res = $mysqli->query($sql);
  $row = $res->fetch_assoc();
  $total = $row['total'] ?? 0;
  $up = $row['up'] ?? 0;
  $down = $row['down'] ?? 0;
  $rate_up = ($total > 0) ? round($up * 100 / $total, 1) : 0;
  $rate_down = ($total > 0) ? round($down * 100 / $total, 1) : 0;
  return [$total, $up, $down, $rate_up, $rate_down];
}

$items = [
  ["조건", "필터", "상승건수", "하락건수", "총건수", "상승률(%)", "하락률(%)"]
];

$items = [
  ["조건", "필터", "상승", "하락", "총", "상승(%)", "하락(%)"]
];

$conditions = [
  // 📏 body_pct 조건
  ["body_pct >= 1", "body_pct >= 1"],
  ["0.9 <= body_pct < 1", "body_pct >= 0.9 AND body_pct < 1"],
  ["0.8 <= body_pct < 0.9", "body_pct >= 0.8 AND body_pct < 0.9"],
  ["0.7 <= body_pct < 0.8", "body_pct >= 0.7 AND body_pct < 0.8"],
  ["0.6 <= body_pct < 0.7", "body_pct >= 0.6 AND body_pct < 0.7"],
  ["0.5 <= body_pct < 0.6", "body_pct >= 0.5 AND body_pct < 0.6"],
  ["0.4 <= body_pct < 0.5", "body_pct >= 0.4 AND body_pct < 0.5"],
  ["0.3 <= body_pct < 0.4", "body_pct >= 0.3 AND body_pct < 0.4"],
  ["0.2 <= body_pct < 0.3", "body_pct >= 0.2 AND body_pct < 0.3"],
  ["0.1 <= body_pct < 0.2", "body_pct >= 0.1 AND body_pct < 0.2"],
  ["body_pct < 0.1", "body_pct < 0.1"],

  // 📊 봉 종류 (양봉/음봉)
  ["candle = 양봉", "candle_type = '양봉'"],
  ["candle = 음봉", "candle_type = '음봉'"],
  ["candle = 도지", "candle_type = '도지'"],

  // 📏 bb_width 조건
  ["bb_width >= 20", "bb_width >= 20"],
  ["15 <= bb_width < 20", "bb_width >= 15 AND bb_width < 20"],
  ["10 <= bb_width < 15", "bb_width >= 10 AND bb_width < 15"],
  ["9 <= bb_width < 10", "bb_width >= 9 AND bb_width < 10"],
  ["8 <= bb_width < 9", "bb_width >= 8 AND bb_width < 9"],
  ["7 <= bb_width < 8", "bb_width >= 7 AND bb_width < 8"],
  ["6 <= bb_width < 7", "bb_width >= 6 AND bb_width < 7"],
  ["5 <= bb_width < 6", "bb_width >= 5 AND bb_width < 6"],
  ["4 <= bb_width < 5", "bb_width >= 4 AND bb_width < 5"],
  ["3 <= bb_width < 4", "bb_width >= 3 AND bb_width < 4"],
  ["2 <= bb_width < 3", "bb_width >= 2 AND bb_width < 3"],
  ["bb_width < 2", "bb_width < 2"],

  // 🔁 MACD 값 구간
  ["macd >= 2", "macd >= 2"],
  ["1 <= macd < 2", "macd >= 1 AND macd < 2"],
  ["0.5 <= macd < 1", "macd >= 0.5 AND macd < 1"],
  ["-0.5 <= macd < 0.5", "macd >= -0.5 AND macd < 0.5"],
  ["-1 <= macd < -0.5", "macd >= -1 AND macd < -0.5"],
  ["-2 <= macd < -1", "macd >= -2 AND macd < -1"],
  ["macd < -2", "macd < -2"],

  // 🔁 MACD hist + position 조합
  ["macd_hist=양봉 & macd=양수", "macd_hist_sign = '양봉' AND macd_position = '양수'"],
  ["macd_hist=양봉 & macd=음수", "macd_hist_sign = '양봉' AND macd_position = '음수'"],
  ["macd_hist=음봉 & macd=양수", "macd_hist_sign = '음봉' AND macd_position = '양수'"],
  ["macd_hist=음봉 & macd=음수", "macd_hist_sign = '음봉' AND macd_position = '음수'"],

  // 🔁 MACD 변화 방향
  ["macd_hist 증가", "macd_hist_change = '증가'"],
  ["macd_hist 감소", "macd_hist_change = '감소'"],

  // 🔁 MACD hist 세부 구간
  ["macd_hist >= 0.5", "macd_hist >= 0.5"],
  ["0.4 <= macd_hist < 0.5", "macd_hist >= 0.4 AND macd_hist < 0.5"],
  ["0.3 <= macd_hist < 0.4", "macd_hist >= 0.3 AND macd_hist < 0.4"],
  ["0.2 <= macd_hist < 0.3", "macd_hist >= 0.2 AND macd_hist < 0.3"],
  ["0.1 <= macd_hist < 0.2", "macd_hist >= 0.1 AND macd_hist < 0.2"],
  ["0 <= macd_hist < 0.1", "macd_hist >= 0 AND macd_hist < 0.1"],
  ["-0.1 <= macd_hist < 0", "macd_hist >= -0.1 AND macd_hist < 0"],
  ["-0.2 <= macd_hist < -0.1", "macd_hist >= -0.2 AND macd_hist < -0.1"],
  ["-0.3 <= macd_hist < -0.2", "macd_hist >= -0.3 AND macd_hist < -0.2"],
  ["-0.4 <= macd_hist < -0.3", "macd_hist >= -0.4 AND macd_hist < -0.3"],
  ["-0.5 <= macd_hist < -0.4", "macd_hist >= -0.5 AND macd_hist < -0.4"],
  ["macd_hist < -0.5", "macd_hist < -0.5"],

  // 📉 EMA-BB 간격
  ["ema_bb_gap >= 0.5", "ema_bb_gap >= 0.5"],
  ["0.4 <= ema_bb_gap < 0.5", "ema_bb_gap >= 0.4 AND ema_bb_gap < 0.5"],
  ["0.3 <= ema_bb_gap < 0.4", "ema_bb_gap >= 0.3 AND ema_bb_gap < 0.4"],
  ["0.2 <= ema_bb_gap < 0.3", "ema_bb_gap >= 0.2 AND ema_bb_gap < 0.3"],
  ["0.1 <= ema_bb_gap < 0.2", "ema_bb_gap >= 0.1 AND ema_bb_gap < 0.2"],
  ["0 <= ema_bb_gap < 0.1", "ema_bb_gap >= 0 AND ema_bb_gap < 0.1"],
  ["-0.1 <= ema_bb_gap < 0", "ema_bb_gap >= -0.1 AND ema_bb_gap < 0"],
  ["-0.2 <= ema_bb_gap < -0.1", "ema_bb_gap >= -0.2 AND ema_bb_gap < -0.1"],
  ["-0.3 <= ema_bb_gap < -0.2", "ema_bb_gap >= -0.3 AND ema_bb_gap < -0.2"],
  ["-0.4 <= ema_bb_gap < -0.3", "ema_bb_gap >= -0.4 AND ema_bb_gap < -0.3"],
  ["-0.5 <= ema_bb_gap < -0.4", "ema_bb_gap >= -0.5 AND ema_bb_gap < -0.4"],
  ["ema_bb_gap < -0.5", "ema_bb_gap < -0.5"],

  // 📈 BB 기울기
  ["bb_slope = 상승", "bb_slope = '상승'"],
  ["bb_slope = 하락", "bb_slope = '하락'"],
  ["bb_slope = 수평", "bb_slope = '수평'"],


  // ✅ 양봉/음봉 + body_pct 조건 조합
  ["양봉 & body_pct >= 1", "candle_type = '양봉' AND body_pct >= 1"],
  ["양봉 & 0.9 <= body_pct < 1", "candle_type = '양봉' AND body_pct >= 0.9 AND body_pct < 1"],
  ["양봉 & 0.8 <= body_pct < 0.9", "candle_type = '양봉' AND body_pct >= 0.8 AND body_pct < 0.9"],
  ["양봉 & 0.7 <= body_pct < 0.8", "candle_type = '양봉' AND body_pct >= 0.7 AND body_pct < 0.8"],
  ["양봉 & 0.6 <= body_pct < 0.7", "candle_type = '양봉' AND body_pct >= 0.6 AND body_pct < 0.7"],
  ["양봉 & 0.5 <= body_pct < 0.6", "candle_type = '양봉' AND body_pct >= 0.5 AND body_pct < 0.6"],
  ["양봉 & 0.4 <= body_pct < 0.5", "candle_type = '양봉' AND body_pct >= 0.4 AND body_pct < 0.5"],
  ["양봉 & 0.3 <= body_pct < 0.4", "candle_type = '양봉' AND body_pct >= 0.3 AND body_pct < 0.4"],
  ["양봉 & 0.2 <= body_pct < 0.3", "candle_type = '양봉' AND body_pct >= 0.2 AND body_pct < 0.3"],
  ["양봉 & 0.1 <= body_pct < 0.2", "candle_type = '양봉' AND body_pct >= 0.1 AND body_pct < 0.2"],
  ["양봉 & body_pct < 0.1", "candle_type = '양봉' AND body_pct < 0.1"],

  ["음봉 & body_pct >= 1", "candle_type = '음봉' AND body_pct >= 1"],
  ["음봉 & 0.9 <= body_pct < 1", "candle_type = '음봉' AND body_pct >= 0.9 AND body_pct < 1"],
  ["음봉 & 0.8 <= body_pct < 0.9", "candle_type = '음봉' AND body_pct >= 0.8 AND body_pct < 0.9"],
  ["음봉 & 0.7 <= body_pct < 0.8", "candle_type = '음봉' AND body_pct >= 0.7 AND body_pct < 0.8"],
  ["음봉 & 0.6 <= body_pct < 0.7", "candle_type = '음봉' AND body_pct >= 0.6 AND body_pct < 0.7"],
  ["음봉 & 0.5 <= body_pct < 0.6", "candle_type = '음봉' AND body_pct >= 0.5 AND body_pct < 0.6"],
  ["음봉 & 0.4 <= body_pct < 0.5", "candle_type = '음봉' AND body_pct >= 0.4 AND body_pct < 0.5"],
  ["음봉 & 0.3 <= body_pct < 0.4", "candle_type = '음봉' AND body_pct >= 0.3 AND body_pct < 0.4"],
  ["음봉 & 0.2 <= body_pct < 0.3", "candle_type = '음봉' AND body_pct >= 0.2 AND body_pct < 0.3"],
  ["음봉 & 0.1 <= body_pct < 0.2", "candle_type = '음봉' AND body_pct >= 0.1 AND body_pct < 0.2"],
  ["음봉 & body_pct < 0.1", "candle_type = '음봉' AND body_pct < 0.1"],


  // ✅ 양봉/음봉 + ema_bb_gap 조건 조합
  ["양봉 & ema_bb_gap >= 0.5", "candle_type = '양봉' AND ema_bb_gap >= 0.5"],
  ["양봉 & 0.4 <= ema_bb_gap < 0.5", "candle_type = '양봉' AND ema_bb_gap >= 0.4 AND ema_bb_gap < 0.5"],
  ["양봉 & 0.3 <= ema_bb_gap < 0.4", "candle_type = '양봉' AND ema_bb_gap >= 0.3 AND ema_bb_gap < 0.4"],
  ["양봉 & 0.2 <= ema_bb_gap < 0.3", "candle_type = '양봉' AND ema_bb_gap >= 0.2 AND ema_bb_gap < 0.3"],
  ["양봉 & 0.1 <= ema_bb_gap < 0.2", "candle_type = '양봉' AND ema_bb_gap >= 0.1 AND ema_bb_gap < 0.2"],
  ["양봉 & 0 <= ema_bb_gap < 0.1", "candle_type = '양봉' AND ema_bb_gap >= 0 AND ema_bb_gap < 0.1"],
  ["양봉 & -0.1 <= ema_bb_gap < 0", "candle_type = '양봉' AND ema_bb_gap >= -0.1 AND ema_bb_gap < 0"],
  ["양봉 & -0.2 <= ema_bb_gap < -0.1", "candle_type = '양봉' AND ema_bb_gap >= -0.2 AND ema_bb_gap < -0.1"],
  ["양봉 & -0.3 <= ema_bb_gap < -0.2", "candle_type = '양봉' AND ema_bb_gap >= -0.3 AND ema_bb_gap < -0.2"],
  ["양봉 & -0.4 <= ema_bb_gap < -0.3", "candle_type = '양봉' AND ema_bb_gap >= -0.4 AND ema_bb_gap < -0.3"],
  ["양봉 & -0.5 <= ema_bb_gap < -0.4", "candle_type = '양봉' AND ema_bb_gap >= -0.5 AND ema_bb_gap < -0.4"],
  ["양봉 & ema_bb_gap < -0.5", "candle_type = '양봉' AND ema_bb_gap < -0.5"],

  ["음봉 & ema_bb_gap >= 0.5", "candle_type = '음봉' AND ema_bb_gap >= 0.5"],
  ["음봉 & 0.4 <= ema_bb_gap < 0.5", "candle_type = '음봉' AND ema_bb_gap >= 0.4 AND ema_bb_gap < 0.5"],
  ["음봉 & 0.3 <= ema_bb_gap < 0.4", "candle_type = '음봉' AND ema_bb_gap >= 0.3 AND ema_bb_gap < 0.4"],
  ["음봉 & 0.2 <= ema_bb_gap < 0.3", "candle_type = '음봉' AND ema_bb_gap >= 0.2 AND ema_bb_gap < 0.3"],
  ["음봉 & 0.1 <= ema_bb_gap < 0.2", "candle_type = '음봉' AND ema_bb_gap >= 0.1 AND ema_bb_gap < 0.2"],
  ["음봉 & 0 <= ema_bb_gap < 0.1", "candle_type = '음봉' AND ema_bb_gap >= 0 AND ema_bb_gap < 0.1"],
  ["음봉 & -0.1 <= ema_bb_gap < 0", "candle_type = '음봉' AND ema_bb_gap >= -0.1 AND ema_bb_gap < 0"],
  ["음봉 & -0.2 <= ema_bb_gap < -0.1", "candle_type = '음봉' AND ema_bb_gap >= -0.2 AND ema_bb_gap < -0.1"],
  ["음봉 & -0.3 <= ema_bb_gap < -0.2", "candle_type = '음봉' AND ema_bb_gap >= -0.3 AND ema_bb_gap < -0.2"],
  ["음봉 & -0.4 <= ema_bb_gap < -0.3", "candle_type = '음봉' AND ema_bb_gap >= -0.4 AND ema_bb_gap < -0.3"],
  ["음봉 & -0.5 <= ema_bb_gap < -0.4", "candle_type = '음봉' AND ema_bb_gap >= -0.5 AND ema_bb_gap < -0.4"],
  ["음봉 & ema_bb_gap < -0.5", "candle_type = '음봉' AND ema_bb_gap < -0.5"],

  // ✅ 양봉/음봉 + bb_width 조건 조합
  ["양봉 & bb_width >= 20", "candle_type = '양봉' AND bb_width >= 20"],
  ["양봉 & 15 <= bb_width < 20", "candle_type = '양봉' AND bb_width >= 15 AND bb_width < 20"],
  ["양봉 & 10 <= bb_width < 15", "candle_type = '양봉' AND bb_width >= 10 AND bb_width < 15"],
  ["양봉 & 9 <= bb_width < 10", "candle_type = '양봉' AND bb_width >= 9 AND bb_width < 10"],
  ["양봉 & 8 <= bb_width < 9", "candle_type = '양봉' AND bb_width >= 8 AND bb_width < 9"],
  ["양봉 & 7 <= bb_width < 8", "candle_type = '양봉' AND bb_width >= 7 AND bb_width < 8"],
  ["양봉 & 6 <= bb_width < 7", "candle_type = '양봉' AND bb_width >= 6 AND bb_width < 7"],
  ["양봉 & 5 <= bb_width < 6", "candle_type = '양봉' AND bb_width >= 5 AND bb_width < 6"],
  ["양봉 & 4 <= bb_width < 5", "candle_type = '양봉' AND bb_width >= 4 AND bb_width < 5"],
  ["양봉 & 3 <= bb_width < 4", "candle_type = '양봉' AND bb_width >= 3 AND bb_width < 4"],
  ["양봉 & 2 <= bb_width < 3", "candle_type = '양봉' AND bb_width >= 2 AND bb_width < 3"],
  ["양봉 & bb_width < 2", "candle_type = '양봉' AND bb_width < 2"],

  ["음봉 & bb_width >= 20", "candle_type = '음봉' AND bb_width >= 20"],
  ["음봉 & 15 <= bb_width < 20", "candle_type = '음봉' AND bb_width >= 15 AND bb_width < 20"],
  ["음봉 & 10 <= bb_width < 15", "candle_type = '음봉' AND bb_width >= 10 AND bb_width < 15"],
  ["음봉 & 9 <= bb_width < 10", "candle_type = '음봉' AND bb_width >= 9 AND bb_width < 10"],
  ["음봉 & 8 <= bb_width < 9", "candle_type = '음봉' AND bb_width >= 8 AND bb_width < 9"],
  ["음봉 & 7 <= bb_width < 8", "candle_type = '음봉' AND bb_width >= 7 AND bb_width < 8"],
  ["음봉 & 6 <= bb_width < 7", "candle_type = '음봉' AND bb_width >= 6 AND bb_width < 7"],
  ["음봉 & 5 <= bb_width < 6", "candle_type = '음봉' AND bb_width >= 5 AND bb_width < 6"],
  ["음봉 & 4 <= bb_width < 5", "candle_type = '음봉' AND bb_width >= 4 AND bb_width < 5"],
  ["음봉 & 3 <= bb_width < 4", "candle_type = '음봉' AND bb_width >= 3 AND bb_width < 4"],
  ["음봉 & 2 <= bb_width < 3", "candle_type = '음봉' AND bb_width >= 2 AND bb_width < 3"],
  ["음봉 & bb_width < 2", "candle_type = '음봉' AND bb_width < 2"]

];

foreach ($conditions as [$label, $cond]) {
  list($total, $up, $down, $rate_up, $rate_down) = getRate($mysqli, $table, $whereBase, $cond);
  $items[] = [$label, $cond, $up, $down, $total, $rate_up, $rate_down];
}
?>

<table style="margin-top:10px; font-size:13px;">
<tr><?php foreach ($items[0] as $th) echo "<th>{$th}</th>"; ?></tr>
<?php foreach (array_slice($items,1) as $row): ?>
<tr>
  <td><?= $row[0] ?></td>
  <td>
    <?php
    $filter = $row[1];

    if (strpos($filter, '>=') !== false) {
        [$field, $val] = explode('>=', $filter, 2);
        $field = trim($field);
        $value = '>=' . trim($val);
    } elseif (strpos($filter, '<') !== false) {
        [$field, $val] = explode('<', $filter, 2);
        $field = trim($field);
        $value = '<' . trim($val);
    } elseif (strpos($filter, '=') !== false) {
        [$field, $val] = explode('=', $filter, 2);
        $field = trim($field);
        $value = trim($val, " '");
    } else {
        $field = trim($filter);
        $value = '';
    }

    // 각 인자 json encode + escape 처리
    $bb_js    = htmlspecialchars(json_encode($bb), ENT_QUOTES, 'UTF-8');
    $ema_js   = htmlspecialchars(json_encode($ema), ENT_QUOTES, 'UTF-8');
    $rsi_js   = htmlspecialchars(json_encode($rsi), ENT_QUOTES, 'UTF-8');
    $field_js = htmlspecialchars(json_encode($field), ENT_QUOTES, 'UTF-8');
    $value_js = htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
    $label    = htmlspecialchars($row[1]); // 실제로 보이는 필터 이름
    ?>

    <a href='#' onclick='loadCasesExtra(<?= $bb_js ?>, <?= $ema_js ?>, <?= $rsi_js ?>, <?= $field_js ?>, <?= $value_js ?>); return false;'>
        <?= $label ?>
    </a>
  </td>
  <td><?= $row[2] ?></td>
  <td><?= $row[3] ?></td>
  <td><?= $row[4] ?></td>
  <td><b><?= $row[5] ?>%</b></td>
  <td><b><?= $row[6] ?>%</b></td>
</tr>
<?php endforeach; ?>
</table>