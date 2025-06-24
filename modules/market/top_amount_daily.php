<?php
$pageTitle = "일별 거래대금 상위 20종목";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

$searchMonth = $_GET['search_month'] ?? date('Y-m');
list($year, $month) = explode('-', $searchMonth);
$startDate = "$year-$month-01";
$endDate = date("Y-m-t", strtotime($startDate));

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

// 현재 월 데이터
$sql = "
    SELECT dar.date, dar.code, s.name, dar.amount, dp.close, dp.close_rate
    FROM daily_amount_rank dar
    LEFT JOIN stock s ON dar.code = s.code AND s.last_yn = 'Y'
    LEFT JOIN daily_price dp ON dp.date = dar.date AND dp.code = dar.code
    WHERE dar.date BETWEEN ? AND ?
    AND dar.close_rate > 0
    AND dar.rank <= 30
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

// 전월 데이터 쿼리
$prevStart = date('Y-m-01', strtotime('-1 month', strtotime($startDate)));
$prevEnd = date('Y-m-t', strtotime($prevStart));
$prevSql = "
    SELECT code, COUNT(*) as cnt
    FROM daily_amount_rank
    WHERE date BETWEEN ? AND ?
    AND close_rate > 0
    AND rank <= 30
    GROUP BY code
";
$prevStmt = $mysqli->prepare($prevSql);
$prevStmt->bind_param("ss", $prevStart, $prevEnd);
$prevStmt->execute();
$prevResult = $prevStmt->get_result();

$prevMap = [];
while ($row = $prevResult->fetch_assoc()) {
    $prevMap[$row['code']] = intval($row['cnt']);
}

// 연속 등장 종목 (전월 7회 이상만)
$continuedStocks = [];
foreach ($stockCountMap as $code => $currInfo) {
    if (isset($prevMap[$code]) && $prevMap[$code] >= 7) {
        $continuedStocks[$code] = [
            'name' => $currInfo['name'],
            'prev_count' => $prevMap[$code],
            'curr_count' => $currInfo['count']
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        body, html {
            margin: 0; padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9; color: #333;
        }
        .content-column {
            display: flex; flex-direction: column;
            width: 100%; padding: 20px;
        }
        .top-bar form { display: inline-block; margin-right: 10px; }
        .summary-box {
            margin: 10px 0; padding: 10px;
            background: #ffffff; border: 1px solid #ddd;
            border-radius: 6px; font-size: 14px;
            display: flex; flex-wrap: wrap; gap: 10px;
        }
        .summary-box span {
            margin: 0; padding: 3px 8px;
            background: #f1f1f1; border-radius: 4px;
            cursor: pointer;
        }
        .highlight-stock {
            background-color: #ffe4b3;
            font-weight: bold;
            border: 1px solid #f39c12;
        }
        .selected-card {
            border: 2px solid #e67e22 !important;
            box-shadow: 0 0 6px rgba(230, 126, 34, 0.6);
        }
        .date-section { margin-bottom: 30px; }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        .stock-card {
            background: #fff; border-radius: 8px;
            padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: border 0.3s, box-shadow 0.3s;
        }
        .stock-title { font-weight: bold; margin-bottom: 8px; }
        .stock-code { color: #666; font-size: 0.9em; }
        .stock-info { margin: 5px 0; font-size: 14px; }
        .bar-container {
            height: 6px; background: #eee;
            border-radius: 3px; margin-top: 5px;
        }
        .bar { height: 100%; border-radius: 3px; }
        #topBtn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            background-color: #333;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        #topBtn:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_nav_menu.php"); ?>
<div id="content">
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
            <strong style="width: 100%; margin-bottom: 5px;">종목별 등장 횟수 (7회 이상):</strong>
            <?php
            uasort($stockCountMap, fn($a, $b) => $b['count'] - $a['count']);
            foreach ($stockCountMap as $code => $info):
                if ($info['count'] >= 7): ?>
                    <span class="summary-stock" data-code="<?= $code ?>">
                        <?= htmlspecialchars($info['name']) ?> : <?= $info['count'] ?>회
                    </span>
            <?php endif; endforeach; ?>
        </div>

        <?php if (count($continuedStocks) > 0): ?>
        <div class="summary-box">
            <strong style="width: 100%; margin-bottom: 5px;">
                📌 <?= date('Y년 m월', strtotime($prevStart)) ?> (7회 이상) → <?= date('m월', strtotime($startDate)) ?>에도 계속 등장한 종목:
            </strong>
            <?php
            uasort($continuedStocks, fn($a, $b) => $b['curr_count'] - $a['curr_count']);
            foreach ($continuedStocks as $code => $info):
                $highlight = ($info['prev_count'] >= 7 && $info['curr_count'] >= 7) ? 'highlight-stock' : '';
            ?>
                <span class="<?= $highlight ?>">
                    <?= htmlspecialchars($info['name']) ?> : <?= $info['prev_count'] ?>→<?= $info['curr_count'] ?>회
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($dailyTopStocks as $date => $stocks): ?>
            <div class="date-section">
                <h2><?= $date ?></h2>
                <div class="grid-container">
                    <?php foreach ($stocks as $idx => $stock):
                        $rank = $idx + 1;
                        $code = $stock['code'];

                        // 등장 순번 누적
                        if (!isset($stockAppearanceOrder[$code])) {
                            $stockAppearanceOrder[$code] = 1;
                        } else {
                            $stockAppearanceOrder[$code]++;
                        }
                        $appearanceNum = $stockAppearanceOrder[$code];

                        $rate = floatval($stock['close_rate']);
                        $rateColor = $rate > 0 ? 'red' : ($rate < 0 ? 'blue' : '#333');
                        $barWidth = $maxAmount > 0 ? ($stock['amount'] / $maxAmount) * 100 : 0;
                        $barColor = getAmountBarColor($stock['amount']);
                        $isContinued = isset($prevMap[$code]) && $prevMap[$code] >= 7;
                        $continueLabel = $isContinued ? "<span style='color: green; font-size: 12px;'>(전월도 등장)</span>" : "";
                    ?>
                        <div class="stock-card" data-code="<?= $stock['code'] ?>" onclick="openStockHistory('<?= $stock['code'] ?>')" style="cursor: pointer;">
                            <div class="stock-title">
                                <?= $rank ?>위. <?= htmlspecialchars($stock['name'] ?: $stock['code']) ?>
                                <div class="stock-code">
                                    (<?= $code ?>) <?= $appearanceNum ?>번 <?= $continueLabel ?>
                                </div>
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


    <!-- TOP 버튼 -->
    <button id="topBtn" onclick="scrollToTop()">TOP ↑</button>
<script>
function openStockHistory(code) {
    const url = "top_amount_stock_history.php?code=" + code;
    window.open(url, "stockHistory", "width=1200,height=1500,scrollbars=yes,resizable=yes");
}

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".summary-stock").forEach(el => {
        el.addEventListener("click", function () {
            const code = this.dataset.code;
            document.querySelectorAll(".stock-card").forEach(card => {
                card.classList.remove("selected-card");
            });
            document.querySelectorAll(`.stock-card[data-code='${code}']`).forEach(card => {
                card.classList.add("selected-card");
                card.scrollIntoView({ behavior: "smooth", block: "center" });
            });
        });
    });
});

function scrollToTop() {
    document.body.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

</body>
</html>
