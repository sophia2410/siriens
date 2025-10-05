<?php
// 📁 futures_strategy_monthly_detail.php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$ym = $_GET['month'] ?? '';
$sid = $_GET['strategy'] ?? '';

if (!$ym) {
    echo "<p>📌 월을 선택해주세요.</p>";
    return;
}

$rows = [];
$strategy_name = $sid ? '' : '전체 전략';

if ($sid) {
    // 전략명 가져오기
    $res = $mysqli->query("SELECT name FROM futures_bb_rsi_strategy WHERE id = $sid");
    $strategy = $res->fetch_assoc();
    $strategy_name = $strategy['name'] ?? '';

    // 전략 포함 여부와 관계없이 features 기준으로 모든 날짜 조회
    $query = "
        SELECT f.date, f.bb_level, f.rsi_range, f.ema_cross, 
               r.trade_profit, r.return_pct, r.exp_dir, r.gap_dir, 
               r.close, r.next_open
        FROM futures_bb_rsi_features_60m f
        JOIN futures_bb_rsi_strategy_result r 
          ON f.date = r.date AND r.strategy_id = $sid
        WHERE DATE_FORMAT(f.date, '%Y-%m') = '$ym'
        GROUP BY f.date
        ORDER BY f.date ASC
    ";
} else {
    // 전체 전략 상세 내역
    $query = "
        SELECT f.date, f.bb_level, f.rsi_range, f.ema_cross, 
               r.point_profit, r.trade_profit, r.return_pct, r.exp_dir, r.gap_dir, 
               r.close, r.next_open, s.name AS strategy_name
        FROM futures_bb_rsi_features_60m f
        LEFT JOIN futures_bb_rsi_strategy_result r ON f.date = r.date
        LEFT JOIN futures_bb_rsi_strategy s ON r.strategy_id = s.id
        WHERE DATE_FORMAT(f.date, '%Y-%m') = '$ym'
        GROUP BY f.date, r.strategy_id
        ORDER BY f.date ASC, r.strategy_id ASC
    ";
}

$res = $mysqli->query($query);
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
?>

<h3>📄 상세 내역: <?= $strategy_name ?> (<?= $ym ?>)</h3>
<?php if (!$rows): ?>
  <p>🔍 해당 데이터가 없습니다.</p>
<?php else: ?>
  <table>
    <tr>
      <th>날짜</th>
      <th>마감상황</th>
      <?php if (!$sid): ?><th>전략</th><?php endif; ?>
      <th>방향</th><th>실제결과</th>
      <th>진입가</th><th>청산가</th><th>PT</th><th>수익</th><th>수익률</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>
          <a href="#" onclick="openChartPopup('<?= $r['date'] ?>'); return false;">
            <?= $r['date'] ?>
          </a>
        </td>
        <td><?= $r['bb_level'].' - '.$r['rsi_range'].' - '.$r['ema_cross'] ?></td>
        <?php if (!$sid): ?>
          <td><?= $r['strategy_name'] ?? '-' ?></td>
        <?php endif; ?>
        <td><?= $r['exp_dir'] ?? '-' ?></td>
        <td><?= $r['gap_dir'] ?? '-' ?></td>
        <td><?= $r['close'] ?? '-' ?></td>
        <td><?= $r['next_open'] ?? '-' ?></td>
        <td><?= isset($r['point_profit']) ? number_format($r['point_profit'],2) : '-' ?></td>
        <td><?= isset($r['trade_profit']) ? number_format($r['trade_profit']) : '-' ?></td>
        <td><?= isset($r['return_pct']) ? number_format($r['return_pct'], 2) . '%' : '-' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<?php if ($sid): ?>
  <br><h4>📌 전략 조건 세부 내역</h4>
  <table>
    <tr>
      <th>그룹</th><th>feature</th><th>operator</th><th>값1</th><th>값2</th>
    </tr>
    <?php
      $description = '';
      $res = $mysqli->query("
        SELECT s.id AS sid, s.name, s.exp_dir, s.description, 
               c.group_id, c.feature_name, c.operator, c.value1, c.value2
        FROM futures_bb_rsi_strategy s
        LEFT JOIN futures_bb_rsi_strategy_conditions c ON s.id = c.strategy_id
        WHERE s.id = $sid
        ORDER BY s.id ASC, c.group_id ASC, c.id ASC
      ");
      while ($r = $res->fetch_assoc()):
        if (!$description && $r['description']) {
          $description = $r['description'];
        }
    ?>
      <tr>
        <td><?= $r['group_id'] ?></td>
        <td><?= $r['feature_name'] ?></td>
        <td><?= $r['operator'] ?></td>
        <td><?= $r['value1'] ?></td>
        <td><?= $r['value2'] ?? '-' ?></td>
      </tr>
    <?php endwhile; ?>
    <tr>
      <td colspan="5" style="background:#f4f4f4; padding:15px; text-align:left;">
        <?= nl2br(htmlspecialchars($description)) ?>
      </td>
    </tr>
  </table>
<?php endif; ?>

<?php if ($sid): ?>
  <br><h4>🧠 전략 조건 SQL 쿼리</h4>
  <pre style="background:#f9f9f9; padding:10px; border:1px solid #ccc;">
<?php
  function quoteIfNeeded($val) {
    return is_numeric($val) ? $val : ("'" . $val . "'");
  }

  $res = $mysqli->query("SELECT * FROM futures_bb_rsi_strategy WHERE id = $sid");
  $strategy = $res->fetch_assoc();

  echo "-- 전략 {$strategy['id']}: {$strategy['bb_level']} - {$strategy['rsi_range']} - {$strategy['ema_cross']} - {$strategy['exp_dir']}\n";
  echo "WHERE\n";
  echo "  bb_level = '" . $strategy['bb_level'] . "'\n";
  echo "  AND rsi_range = '" . $strategy['rsi_range'] . "'\n";
  echo "  AND ema_cross = '" . $strategy['ema_cross'] . "'\n";

  // 그룹 조건 처리
  $group_sqls = [];
  $res = $mysqli->query("
    SELECT group_id, feature_name, operator, value1, value2
    FROM futures_bb_rsi_strategy_conditions
    WHERE strategy_id = $sid
    ORDER BY group_id ASC, id ASC
  ");
  $groups = [];
  while ($row = $res->fetch_assoc()) {
    $gid = $row['group_id'];
    if (!isset($groups[$gid])) $groups[$gid] = [];
    $op = strtoupper($row['operator']);
    $v1 = quoteIfNeeded($row['value1']);
    $v2 = quoteIfNeeded($row['value2']);
    if ($op === 'BETWEEN') {
      $cond = "({$row['feature_name']} BETWEEN $v1 AND $v2)";
    } else {
      $cond = "({$row['feature_name']} $op $v1)";
    }
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
?>
  </pre>
<?php endif; ?>
