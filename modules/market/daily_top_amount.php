<?php
$pageTitle = "일별 거래대금 상위 20종목";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

$searchMonth = isset($_GET['search_month']) ? $_GET['search_month'] : date('Y-m');
list($year, $month) = explode('-', $searchMonth);
$startDate = "$year-$month-01";
$endDate = date("Y-m-t", strtotime($startDate));

// 이전/다음 월 계산
$prevMonth = date('Y-m', strtotime('-1 month', strtotime($startDate)));
$nextMonth = date('Y-m', strtotime('+1 month', strtotime($startDate)));

function formatAmountToEok($amount) {
    return number_format($amount / 1.0e8, 0) . '억';
}

function getAmountBarColor($amount) {
    if ($amount >= 10.0e12) return '#e74c3c';
    elseif ($amount >= 8.0e12) return '#f39c12';
    elseif ($amount >= 6.0e12) return '#3498db';
    else return '#95a5a6';
}

$sql = "
    SELECT dar.date, dar.code, s.name, dar.amount, dp.close, dp.close_rate
    FROM daily_amount_rank dar
    LEFT JOIN stock s ON dar.code = s.code AND s.last_yn = 'Y'
    LEFT JOIN daily_price dp ON dp.date = dar.date AND dp.code = dar.code
    WHERE dar.date BETWEEN ? AND ?
    AND dar.rank <= 20
    ORDER BY dar.date ASC, dar.rank ASC
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

$dailyTopStocks = [];
$stockCountMap = [];
$maxAmount = 0;
while ($row = $result->fetch_assoc()) {
    $date = $row['date'];
    if (!isset($dailyTopStocks[$date])) $dailyTopStocks[$date] = [];
    $dailyTopStocks[$date][] = $row;

    $code = $row['code'];
    $name = $row['name'] ?: $code;
    if (!isset($stockCountMap[$code])) {
        $stockCountMap[$code] = ['name' => $name, 'count' => 1];
    } else {
        $stockCountMap[$code]['count']++;
    }

    if ($row['amount'] > $maxAmount) {
        $maxAmount = $row['amount'];
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
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            color: #333;
        }
        .content-column {
            display: flex;
            flex-direction: column;
            width: 100%;
            padding: 20px;
        }
        .top-bar form { display: inline-block; margin-right: 10px; }
        .summary-box {
            margin: 15px 0;
            padding: 10px;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .summary-box span {
            margin: 0;
            padding: 3px 8px;
            background: #f1f1f1;
            border-radius: 4px;
        }
        .date-section { margin-bottom: 30px; }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        .stock-card {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stock-title { font-weight: bold; margin-bottom: 8px; }
        .stock-code { color: #666; font-size: 0.9em; }
        .stock-info { margin: 5px 0; font-size: 14px; }
        .bar-container {
            height: 6px;
            background: #eee;
            border-radius: 3px;
            margin-top: 5px;
        }
        .bar {
            height: 100%;
            border-radius: 3px;
        }
    </style>
</head>
<body>
<div id="container">
    <div class="content-column">
        <div class="top-bar">
            <form method="GET">
                <input type="month" name="search_month" value="<?= htmlspecialchars($searchMonth) ?>">
                <button type="submit">조회</button>
            </form>
            <form method="GET" style="display:inline-block;">
                <input type="hidden" name="search_month" value="<?= $prevMonth ?>">
                <button type="submit">&lt; 이전달</button>
            </form>
            <form method="GET" style="display:inline-block;">
                <input type="hidden" name="search_month" value="<?= $nextMonth ?>">
                <button type="submit">다음달 &gt;</button>
            </form>
        </div>

        <div class="summary-box">
            <strong style="width: 100%; margin-bottom: 5px;">종목별 등장 횟수 (10회 이상):</strong>
            <?php
            uasort($stockCountMap, function($a, $b) {
                return $b['count'] - $a['count'];
            });
            foreach ($stockCountMap as $code => $info):
                if ($info['count'] >= 10): ?>
                <span><?= htmlspecialchars($info['name']) ?> (<?= $code ?>): <?= $info['count'] ?>회</span>
            <?php endif; endforeach; ?>
        </div>

        <?php foreach ($dailyTopStocks as $date => $stocks): ?>
            <div class="date-section">
                <h2><?= $date ?></h2>
                <div class="grid-container">
                    <?php foreach ($stocks as $idx => $stock):
                        $rank = $idx + 1;
                        $rate = floatval($stock['close_rate']);
                        $rateColor = $rate > 0 ? 'red' : ($rate < 0 ? 'blue' : '#333');
                        $barWidth = $maxAmount > 0 ? ($stock['amount'] / $maxAmount) * 100 : 0;
                        $barColor = getAmountBarColor($stock['amount']);
                    ?>
                        <div class="stock-card">
                            <div class="stock-title">
                                <?= $rank ?>위. <?= htmlspecialchars($stock['name'] ?: $stock['code']) ?>
                                <div class="stock-code">(<?= $stock['code'] ?>)</div>
                            </div>
                            <div class="stock-info">종가: <?= number_format($stock['close']) ?>원</div>
                            <div class="stock-info" style="color: <?= $rateColor ?>;">등락률: <?= $stock['close_rate'] ?>%</div>
                            <div class="stock-info">거래대금: <?= formatAmountToEok($stock['amount']) ?></div>
                            <div class="bar-container">
                                <div class="bar" style="width: <?= $barWidth ?>%; background: <?= $barColor ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
