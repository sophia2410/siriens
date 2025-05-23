<?php
$pageTitle = "일별 거래대금 상위 20종목";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

// 날짜 파라미터 (기본값: 이번 달)
$searchMonth = isset($_GET['search_month']) ? $_GET['search_month'] : date('Y-m');
list($year, $month) = explode('-', $searchMonth);
$startDate = "$year-$month-01";
$endDate = date("Y-m-t", strtotime($startDate));

// 억 단위 표시 함수
function formatAmountToEok($amount) {
    return number_format($amount / 1.0e8, 0) . '억';
}

// 색상 바
function getAmountBarColor($amount) {
    if ($amount >= 10.0e12) return 'background: linear-gradient(to right, #ff3333, #ff9999);';
    elseif ($amount >= 8.0e12) return 'background: linear-gradient(to right, #ff9900, #ffcc66);';
    elseif ($amount >= 6.0e12) return 'background: linear-gradient(to right, #0099ff, #66ccff);';
    else return 'background: linear-gradient(to right, #555555, #aaaaaa);';
}

// 데이터 조회
$sql = "
    SELECT dp.date, dp.code, s.name, dp.close, dp.close_rate, dp.amount
    FROM daily_price dp
    LEFT JOIN stock s ON dp.code = s.code AND s.last_yn = 'Y'
    WHERE dp.date BETWEEN ? AND ?
    ORDER BY dp.date ASC, dp.amount DESC
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

$dailyTopStocks = [];
$stockCountMap = [];
while ($row = $result->fetch_assoc()) {
    $date = $row['date'];
    if (!isset($dailyTopStocks[$date])) $dailyTopStocks[$date] = [];
    if (count($dailyTopStocks[$date]) < 20) {
        $dailyTopStocks[$date][] = $row;

        // 종목별 등장 횟수 계산
        $code = $row['code'];
        $name = $row['name'] ?: $code;
        if (!isset($stockCountMap[$code])) {
            $stockCountMap[$code] = ['name' => $name, 'count' => 1];
        } else {
            $stockCountMap[$code]['count']++;
        }
    }
}

// 메뉴 경로
if($_SERVER["HTTP_HOST"] == 'localhost') {
    $PATH = "http://localhost/";
} else {
    $PATH = "https://siriens.mycafe24.com/";
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
            overflow-x: hidden;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            height: 100vh;
            color: #858796;
        }
        #container {
            display: flex;
            height: 100vh;
            margin-left: 100px !important;
            flex-direction: column;
            width: calc(100% - 100px);
            padding: 20px;
            box-sizing: border-box;
        }
        .top-bar form { display: inline-block; margin-right: 10px; }
        .summary-box {
            margin: 15px 0;
            padding: 10px;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 6px;
            max-height: 200px;
            overflow-y: auto;
            font-size: 14px;
        }
        .summary-box span {
            margin-right: 10px;
            display: inline-block;
            margin-bottom: 5px;
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
            margin-top: 10px;
            height: 14px;
            border-radius: 7px;
            background: #eee;
            overflow: hidden;
        }
        .bar {
            height: 100%;
            border-radius: 7px;
        }
    </style>
</head>
<body>
    <?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/side_menu.php"); ?>
    <div id="container">
        <div class="top-bar">
            <form method="GET">
                <input type="month" name="search_month" value="<?= htmlspecialchars($searchMonth) ?>">
                <button type="submit">조회</button>
            </form>
        </div>

        <!-- 종목별 등장 횟수 표시 -->
        <div class="summary-box">
            <strong>종목별 등장 횟수:</strong><br>
            <?php
            uasort($stockCountMap, function($a, $b) {
                return $b['count'] - $a['count'];
            });
            foreach ($stockCountMap as $code => $info): ?>
                <span><?= htmlspecialchars($info['name']) ?> (<?= $code ?>): <?= $info['count'] ?>회</span>
            <?php endforeach; ?>
        </div>

        <?php foreach ($dailyTopStocks as $date => $stocks): ?>
        <div class="date-section">
            <h2><?= $date ?></h2>
            <div class="grid-container">
                <?php foreach ($stocks as $idx => $stock): 
                    $rank = $idx + 1;
                    $rate = floatval($stock['close_rate']);
                    $rateColor = $rate > 0 ? 'red' : ($rate < 0 ? 'blue' : '#333');
                    $barColor = getAmountBarColor($stock['amount']);
                    $barWidth = min(100, ($stock['amount'] / 15.0e12) * 100);
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
                        <div class="bar" style="width: <?= $barWidth ?>%; <?= $barColor ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
