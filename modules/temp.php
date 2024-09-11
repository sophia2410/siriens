<?php
$pageTitle = "마켓 리포트"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");

require($_SERVER['DOCUMENT_ROOT']."/modules/issues/issue_register_form.php");
require($_SERVER['DOCUMENT_ROOT']."/modules/issues/issue_list.php");

$report_date = isset($_GET['report_date']) ? $_GET['report_date'] : date('Ymd', time());
$report_date = str_replace('-', '', $report_date);

// Fetch dates for the select box
$query = "
SELECT report_date, CONCAT(SUBSTRING(report_date, 1, 4), '년 ', SUBSTRING(report_date, 5, 2), '월 ', SUBSTRING(report_date, 7, 2), '일') report_date_str
FROM market_report
ORDER BY report_date DESC
LIMIT 50";

$result = $mysqli->query($query);
$dates = [];
while($row = $result->fetch_assoc()) {
    $dates[] = $row;
}

// Fetch market comment, overview and titles
$overview_query = "SELECT market_review, market_overview, us_market_overview, other_market_overview, morning_report_title, evening_report_title FROM market_report WHERE report_date = '$report_date'";
$overview_result = $mysqli->query($overview_query);
$overview_row = $overview_result->fetch_assoc();
$market_review = isset($overview_row) ? $overview_row['market_review'] : '';
$market_overview = isset($overview_row) ? $overview_row['market_overview'] : '';
$us_market_overview = isset($overview_row) ? $overview_row['us_market_overview'] : '';
$other_market_overview = isset($overview_row) ? $overview_row['other_market_overview'] : '';
$morning_report_title = isset($overview_row) ? $overview_row['morning_report_title'] : '';
$evening_report_title = isset($overview_row) ? $overview_row['evening_report_title'] : '';

// Fetch sector data
$sector_query = "
    SELECT CASE WHEN theme != '' THEN theme WHEN sector != '' THEN sector ELSE '미분류' END AS theme,
           CASE WHEN theme != '' THEN 'theme' ELSE 'sector' END AS group_type, 
           issue, hot_theme, code, name, stock_comment, close_rate AS stock_change, trade_amount AS stock_amount
    FROM v_market_issue vmi
    WHERE date = '$report_date'
    ORDER BY CASE WHEN theme != '' THEN 'theme' ELSE 'sector' END DESC, -- 테마 우선 정렬
    hot_theme DESC,
    CASE WHEN theme != '' THEN theme WHEN sector != '' THEN sector ELSE '미분류' END,
    close_rate DESC";
$sector_result = $mysqli->query($sector_query);

$theme_data = [];
while ($row = $sector_result->fetch_assoc()) {
    $theme_data[$row['theme']][] = $row;
}

// Fetch recent 5 days themes and stocks
$recent_themes_query = "
    SELECT mi.theme, mis.code, mis.name
    FROM market_issues mi
    JOIN market_issue_stocks mis ON mis.issue_id = mi.issue_id
    WHERE mi.date BETWEEN DATE_ADD('$report_date', INTERVAL -5 DAY) AND '$report_date'
    AND ( mis.is_leader = '1' OR mis.is_watchlist = '1')
    GROUP BY mi.theme, mis.code, mis.name";
$recent_themes_result = $mysqli->query($recent_themes_query);
$recent_themes = [];
while($row = $recent_themes_result->fetch_assoc()) {
    $recent_themes[] = $row;
}

// Fetch KOSPI and KOSDAQ index data
$index_query = "
    SELECT market_fg, close, 
           close_rate, 
           ROUND(amount / 1000000000000, 2) AS amount_in_trillion
    FROM market_index 
    WHERE (market_fg IN ('S&P 500', 'NASDAQ') AND date = (
                    -- S&P 500과 NASDAQ의 전 거래일 데이터를 가져옴
                    SELECT MAX(c.date) 
                    FROM calendar c 
                    WHERE c.date < '$report_date'
               ))
       OR (market_fg NOT IN ('S&P 500', 'NASDAQ') AND date = '$report_date')
    ORDER BY market_fg ASC;
";

$index_result = $mysqli->query($index_query);

$index_data = [];
while ($row = $index_result->fetch_assoc()) {
    $index_data[$row['market_fg']] = [
        'close' => $row['close'],
        'close_rate' => $row['close_rate'],
        'amount_in_trillion' => $row['amount_in_trillion']
    ];
}

?>

<head>
    <!-- 페이지 전용 스타일 -->
    <style>
        /* 전체 화면을 3개의 칼럼으로 나눔 */
        #wrapper {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr; /* left, middle, right로 나눔 */
            gap: 10px;
            padding: 10px;
        }

        #left-content, #middle-content, #right-content {
            background-color: white;
            border: 1px solid #ddd;
            padding: 20px;
        }

        /* 인덱스 섹션 스타일 */
        #index-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .index-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            margin: 0 10px;
        }

        /* 카드 스타일 */
        .sector-card {
            background-color: white;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* textarea 스타일 */
        textarea {
            width: 100%;
            margin-bottom: 20px;
        }

        textarea.large {
            height: 200px;
        }

        textarea.medium {
            height: 150px;
        }

        textarea.small {
            height: 100px;
        }

        /* 컨트롤 버튼과 인덱스 섹션 레이아웃 */
        #controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        /* Date input 스타일 */
        #report_date {
            font-size: 16px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        button {
            padding: 10px 20px;
            background-color: red;
            color: white;
            border: none;
            border-radius: 4px;
        }
    </style>
</head>

<body>
<div id="container">
    <!-- Controls Section (일자 선택과 Save 버튼) -->
    <div id="controls">
        <input type="date" id="report_date" value="<?= date('Y-m-d', strtotime($report_date)) ?>" onchange="search()">
        <button onclick="saveReport()">Save Report</button>
    </div>

    <!-- 인덱스 섹션 -->
    <div id="index-section">
        <?php
        // 각 마켓의 이름과 관련된 정보를 배열로 설정
        $markets = ['S&P 500', 'NASDAQ', 'KOSPI', 'KOSDAQ'];

        // 루프를 통해 각 마켓의 데이터를 출력
        foreach ($markets as $market) {
            $color = (isset($index_data[$market]['close_rate']) && $index_data[$market]['close_rate'] < 0) ? 'blue' : 'red';
            
            // 마켓 이름을 기반으로 데이터 출력
            echo "
            <div class='index-item' style='font-weight: bold; color: $color;'>
                <h3>$market</h3>
                <p>" . (isset($index_data[$market]) ? number_format($index_data[$market]['close'],2)."(".htmlspecialchars($index_data[$market]['close_rate']) . '%)' : 'No data') . "</p>";

            // 'KOSPI'와 'KOSDAQ'에 대해서만 Amount 출력
            if (in_array($market, ['KOSPI', 'KOSDAQ'])) {
                echo "<p style='font-size: 0.8em; color: #666;'>" . (isset($index_data[$market]) ? htmlspecialchars($index_data[$market]['amount_in_trillion']) . '조' : '') . "</p>";
            }

            echo "</div>";
        }
        ?>
    </div>
    
    <div id="wrapper">
        <!-- Left Content: Textarea 입력 -->
        <div id="left-content">
            <h3>US Market Overview</h3>
            <textarea class="medium" id="us_market_overview"><?= htmlspecialchars($us_market_overview) ?></textarea>

            <h3>Other Market Overview</h3>
            <textarea class="medium" id="other_market_overview"><?= htmlspecialchars($other_market_overview) ?></textarea>

            <h3>Market Overview</h3>
            <textarea class="large" id="market_overview"><?= htmlspecialchars($market_overview) ?></textarea>

            <h3>Market Review</h3>
            <textarea class="large" id="market_review"><?= htmlspecialchars($market_review) ?></textarea>
        </div>

        <!-- Middle Content: Sector Cards (플렉스박스) -->
        <div id="middle-content">
            <?php foreach ($theme_data as $theme => $stocks): ?>
                <div class="sector-card">
                    <h4><?= htmlspecialchars($theme) ?></h4>
                    <?php foreach ($stocks as $stock): ?>
                        <p>
                            <strong><?= htmlspecialchars($stock['name']) ?> (<?= htmlspecialchars($stock['code']) ?>)</strong>
                            <span><?= htmlspecialchars($stock['stock_comment']) ?></span>
                            <span><?= htmlspecialchars($stock['stock_change']) ?>%</span>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Right Content: 최근 5일간의 테마와 종목 -->
        <div id="right-content">
            <h3>최근 5일간의 주도주 및 관심종목</h3>
            <table>
                <thead>
                    <tr>
                        <th>테마</th>
                        <th>종목</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_themes as $theme): ?>
                        <tr>
                            <td><?= htmlspecialchars($theme['theme']) ?></td>
                            <td><?= htmlspecialchars($theme['name']) ?> (<?= htmlspecialchars($theme['code']) ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    function search() {
        var selectedDate = document.getElementById('report_date').value;
        window.location.href = 'market_report.php?report_date=' + selectedDate;
    }

    function saveReport() {
        var report_date = document.getElementById('report_date').value;
        var market_overview = document.getElementById('market_overview').value;
        var us_market_overview = document.getElementById('us_market_overview').value;
        var other_market_overview = document.getElementById('other_market_overview').value;
        var market_review = document.getElementById('market_review').value;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api.php?action=save_report', true);
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                alert('Report saved successfully!');
            }
        };
        xhr.send('report_date=' + report_date + 
                '&market_overview=' + encodeURIComponent(market_overview) +
                '&us_market_overview=' + encodeURIComponent(us_market_overview) +
                '&other_market_overview=' + encodeURIComponent(other_market_overview) +
                '&market_review=' + encodeURIComponent(market_review));
    }
</script>
</body>
</html>
