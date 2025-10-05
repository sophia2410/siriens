<?php
// 📁 futures_strategy_monthly_summary.php
$pageTitle = "전략 월별 수익 분석";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

$selected_month = $_GET['month'] ?? '';
$selected_strategy = $_GET['strategy'] ?? '';

// 전략 목록
$strategies = [];
$res = $mysqli->query("SELECT id, name FROM futures_bb_rsi_strategy");
while ($row = $res->fetch_assoc()) {
    $strategies[$row['id']] = $row['name'];
}

// 1. 월별 요약 조회
$summary = [];
$res = $mysqli->query("SELECT DATE_FORMAT(date, '%Y-%m') as month,
    COUNT(*) as cnt,
    SUM(trade_profit) as total_profit,
    SUM(CASE WHEN exp_dir = '상승' AND point_profit > 0 THEN 1
             WHEN exp_dir = '하락' AND point_profit < 0 THEN 1 ELSE 0 END) as win_count
  FROM futures_bb_rsi_strategy_result
  GROUP BY month ORDER BY month DESC");
while ($row = $res->fetch_assoc()) {
    $row['win_rate'] = $row['cnt'] > 0 ? round($row['win_count'] / $row['cnt'] * 100, 1) : 0;
    $summary[] = $row;
}

// 2. 선택된 월의 전략별 요약
$strategy_monthly = [];
if ($selected_month) {
    $res = $mysqli->query("
        SELECT 
            r.strategy_id,
            s.name AS strategy_name,
            COUNT(*) AS cnt,
            SUM(r.trade_profit) AS total_profit,
            SUM(CASE WHEN r.point_profit > 0 THEN 1 ELSE 0 END) AS win_count,
            ROUND(AVG(CASE WHEN r.point_profit > 0 THEN 1 ELSE 0 END) * 100, 1) AS win_rate
        FROM futures_bb_rsi_strategy_result r
        JOIN futures_bb_rsi_strategy s ON r.strategy_id = s.id
        WHERE DATE_FORMAT(r.date, '%Y-%m') = '$selected_month'
        GROUP BY r.strategy_id
        ORDER BY s.name ASC
    ");

    while ($row = $res->fetch_assoc()) {
        $row['strategy_name'] = $strategies[$row['strategy_id']] ?? '';
        $strategy_monthly[] = $row;
    }
}

?><!DOCTYPE html>
<html>
<head>
    <title>전략 월별 수익 분석</title>
    <style>
        .container { display: flex; gap: 20px; }
        .left-panel { width: 40%; }
        .right-panel { width: 60%; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: center; }
        tr:hover { background: #f0f0f0; cursor: pointer; }
        .highlight { background: #ffe0b2; }
    </style>
    <script>
        function openChartPopup(date) {
          const w = 1440, h = 1220;
          const left = (screen.width - w) / 2;
          const top = (screen.height - h) / 2;
          window.open(
            `./futures_chart_BB.php?date=${date}`,
            'bb_rsi_chart',
            `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=no`
          );
        }
    </script>

</head>

<body style="margin-left:150px">
<?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_nav_menu.php"); ?>
<h2>📊 전략 월별 수익 분석</h2>
<div class="container">
    <div class="left-panel">
        <h3>📅 월별 요약</h3>
        <table>
            <tr><th>월</th><th>총건수</th><th>총수익</th><th>승률</th></tr>
            <?php foreach ($summary as $row): 
                $highlight = ($row['month'] == $selected_month) ? 'class="highlight"' : '';
            ?>
            <tr <?= $highlight ?> onclick="location.href='?month=<?= $row['month'] ?>'">
                <td><?= $row['month'] ?></td>
                <td><?= $row['cnt'] ?></td>
                <td><?= number_format($row['total_profit']) ?></td>
                <td><?= $row['win_rate'] ?>%</td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($selected_month): ?>
        <h3>🧠 전략별 요약 (<?= $selected_month ?>)</h3>
        <table>
          <thead>
            <tr>
              <th onclick="sortTable(0, this, event)">전략</th>
              <th onclick="sortTable(2)">건수</th>
              <th onclick="sortTable(3)">수익</th>
              <th onclick="sortTable(4)">승률</th>
            </tr>
            <?php foreach ($strategy_monthly as $s): 
                $highlight = ($s['strategy_id'] == $selected_strategy) ? 'class="highlight"' : '';
            ?>
          </thead>
          <tbody id="strategy-body">
            <tr <?= $highlight ?> onclick="location.href='?month=<?= $selected_month ?>&strategy=<?= $s['strategy_id'] ?>'">
                <td style='text-align:left;'><?= $s['strategy_name'] ?></td>
                <td><?= $s['cnt'] ?></td>
                <td><?= number_format($s['total_profit']) ?></td>
                <td><?= $s['win_rate'] ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="right-panel" id="detail-panel">
        <?php include "futures_strategy_monthly_detail.php"; ?>
    </div>
</div>
</body>
</html>
