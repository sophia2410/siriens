<?php
$pageTitle = "2주 단위 종목별 등장 횟수 요약";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

$startDate = date('Y-m-01', strtotime('-36 months'));
$endDate = date('Y-m-d');

$sql = "
    SELECT
        CONCAT(DATE_FORMAT(dar.date, '%Y-%m-'),
               LPAD(IF(DAY(dar.date) <= 15, '01', '16'), 2, '0')) AS period_start,
        CONCAT(DATE_FORMAT(dar.date, '%Y-%m-'),
               LPAD(IF(DAY(dar.date) <= 15, '15', LAST_DAY(dar.date)), 2, '0')) AS period_end,
        dar.code,
        s.name,
        COUNT(*) AS cnt,
        MIN(dar.date) AS first_date
    FROM daily_amount_rank dar
    LEFT JOIN stock s ON dar.code = s.code AND s.last_yn = 'Y'
    WHERE dar.date BETWEEN ? AND ?
      AND dar.rank <= 20
    GROUP BY period_start, period_end, dar.code
    HAVING cnt >= 5
    ORDER BY period_start ASC, cnt DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

$periodMap = [];
$firstAppearance = [];
while ($row = $result->fetch_assoc()) {
    $period = $row['period_start'] . " ~ " . $row['period_end'];
    $code = $row['code'];
    $name = $row['name'] ?: $code;
    $cnt = $row['cnt'];
    $first_date = $row['first_date'];
    $periodMap[$period][] = ['code' => $code, 'name' => $name, 'count' => $cnt, 'first_date' => $first_date];

    if (!isset($firstAppearance[$code]) || $firstAppearance[$code] > $first_date) {
        $firstAppearance[$code] = $first_date;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/common.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f9f9f9;
        }
        h2 {
            margin-bottom: 15px;
        }
        .period-section {
            margin-bottom: 30px;
        }
        .period-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .summary-box {
            background: #fff;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 14px;
        }
        .stock-box {
            padding: 10px 12px;
            border-radius: 6px;
            background: #f1f1f1;
            color: #333;
            min-width: 150px;
            transition: background-color 0.2s ease;
        }
        .first-appearance {
            border-left: 6px solid #2ecc71;
        }
        .stock-box[data-code]:hover {
            background-color: #d1ecf1 !important;
        }
        .stock-box[data-code].highlight {
            background-color: #d1ecf1 !important;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const boxes = document.querySelectorAll('.stock-box');
            boxes.forEach(box => {
                box.addEventListener('mouseenter', () => {
                    const code = box.dataset.code;
                    document.querySelectorAll('.stock-box[data-code="' + code + '"]').forEach(el => {
                        el.classList.add('highlight');
                    });
                });
                box.addEventListener('mouseleave', () => {
                    const code = box.dataset.code;
                    document.querySelectorAll('.stock-box[data-code="' + code + '"]').forEach(el => {
                        el.classList.remove('highlight');
                    });
                });
            });
        });
    </script>
</head>
<body>
<div id="container">
    <h2>2주 단위 종목별 등장 횟수 (Top 20 중, 5회 이상)</h2>
    <?php foreach ($periodMap as $period => $stocks): ?>
        <div class="period-section">
            <div class="period-title">📅 <?= $period ?></div>
            <div class="summary-box">
                <?php foreach ($stocks as $info):
                    $isFirst = ($info['first_date'] === $firstAppearance[$info['code']]);
                    $boxClass = $isFirst ? 'stock-box first-appearance' : 'stock-box';
                ?>
                    <div class="<?= $boxClass ?>" data-code="<?= $info['code'] ?>">
                        <?= htmlspecialchars($info['name']) ?> : <?= $info['count'] ?>회
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
