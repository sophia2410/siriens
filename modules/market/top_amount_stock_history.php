<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

$code = $_GET['code'] ?? '';
if (!$code) {
    echo "종목코드가 지정되지 않았습니다."; exit;
}

$sql = "
    SELECT dar.date, dar.rank, dar.amount, dp.close, dp.close_rate, s.name
    FROM daily_amount_rank dar
    LEFT JOIN daily_price dp ON dp.date = dar.date AND dp.code = dar.code
    LEFT JOIN stock s ON s.code = dar.code AND s.last_yn = 'Y'
    WHERE dar.code = ?
    AND dar.rank <= 30
    AND dar.date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
    ORDER BY dar.date DESC
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
$name = '';
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
    $name = $row['name'] ?? $code;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($name) ?> 거래대금 이력</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px; }
        h2 { margin-bottom: 10px; }
        table {
            border-collapse: collapse;
            width: 100%;
            background: #fff;
            margin-bottom: 30px;
        }
        th, td {
            padding: 8px 12px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th { background: #f1f1f1; }
        .chart-container {
            width: 100%; max-width: 1200px; margin: auto;
            background: #fff; padding: 20px;
            border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<h2><?= htmlspecialchars($name) ?> (<?= $code ?>)</h2>
<p>최근 거래대금 상위 30위 내 등장 이력</p>

<div class="chart-container">
    <canvas id="amountChart"></canvas>
</div>

<table>
    <thead>
        <tr>
            <th>월</th>
            <th>날짜</th>
            <th>순위</th>
            <th>거래대금</th>
            <th>종가</th>
            <th>등락률</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $grouped = [];
    foreach ($history as $row) {
        $month = substr($row['date'], 0, 7); // YYYY-MM
        $grouped[$month][] = $row;
    }

    foreach ($grouped as $month => $rows):
        $rowspan = count($rows);
        foreach ($rows as $idx => $row):
    ?>
    <tr>
        <?php if ($idx === 0): ?>
            <td rowspan="<?= $rowspan ?>" style="background:#f9f9f9; font-weight:bold;"><?= $month ?></td>
        <?php endif; ?>
        <td><?= $row['date'] ?></td>
        <td><?= $row['rank'] ?>위</td>
        <td><?= number_format($row['amount'] / 1.0e8, 0) ?>억</td>
        <td><?= number_format($row['close']) ?>원</td>
        <td style="color: <?= $row['close_rate'] > 0 ? 'red' : ($row['close_rate'] < 0 ? 'blue' : '#000') ?>">
            <?= $row['close_rate'] ?>%
        </td>
    </tr>
    <?php endforeach; endforeach; ?>
    </tbody>

</table>

<script>
const ctx = document.getElementById('amountChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($history, 'date')) ?>,
        datasets: [{
            label: '거래대금 (억)',
            data: <?= json_encode(array_map(fn($r) => round($r['amount'] / 1.0e8), $history)) ?>,
            backgroundColor: 'rgba(52, 152, 219, 0.6)',
        }]
    },
    options: {
        responsive: true,
        scales: {
            x: { ticks: { autoSkip: true, maxTicksLimit: 20 } },
            y: { beginAtZero: true }
        }
    }
});
</script>

</body>
</html>
