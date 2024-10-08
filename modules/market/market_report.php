<?php
$pageTitle = "마켓 리포트"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");

require($_SERVER['DOCUMENT_ROOT']."/modules/market/event_register_form.php");
require($_SERVER['DOCUMENT_ROOT']."/modules/market/event_list.php");

$report_date = isset($_GET['report_date']) ? $_GET['report_date'] : date('Y-m-d', time());

// 현재 report_date 기준으로 이전 날짜와 다음 날짜 가져오기
$previous_date_query = "
    SELECT date FROM calendar 
    WHERE date < '$report_date' 
    ORDER BY date DESC 
    LIMIT 1";
$previous_date_result = $mysqli->query($previous_date_query);
$previous_date_row = $previous_date_result->fetch_assoc();
$previous_date = isset($previous_date_row['date']) ? $previous_date_row['date'] : null;

$next_date_query = "
    SELECT date FROM calendar 
    WHERE date > '$report_date' 
    ORDER BY date ASC 
    LIMIT 1";
$next_date_result = $mysqli->query($next_date_query);
$next_date_row = $next_date_result->fetch_assoc();
$next_date = isset($next_date_row['date']) ? $next_date_row['date'] : null;


// Fetch market comment, overview and titles
$overview_query = "SELECT market_review, market_overview, us_market_overview, other_market_overview, morning_report_title, morning_news_link, evening_report_title FROM market_report WHERE date = '$report_date'";
$overview_result = $mysqli->query($overview_query);
$overview_row = $overview_result->fetch_assoc();
$market_review = isset($overview_row) ? $overview_row['market_review'] : '';
$market_overview = isset($overview_row) ? $overview_row['market_overview'] : '';
$us_market_overview = isset($overview_row) ? $overview_row['us_market_overview'] : '';
$other_market_overview = isset($overview_row) ? $overview_row['other_market_overview'] : '';
$morning_report_title = isset($overview_row) ? $overview_row['morning_report_title'] : '';
$morning_news_link = isset($overview_row) ? $overview_row['morning_news_link'] : '';
$evening_report_title = isset($overview_row) ? $overview_row['evening_report_title'] : '';

// Fetch group data
$group_query = "
    SELECT theme_group.group_label,
        theme_group.theme,
        theme_group.issue,
        theme_group.hot_theme,
        theme_group.code,
        theme_group.name,
        theme_group.stock_comment,
        theme_group.stock_change,
        theme_group.stock_amount,
        theme_group.hot_theme,
        theme_group.is_leader,
        theme_group.is_watchlist,
        theme_group.keyword_group_name,
        theme_group.remaining_keywords
    FROM (
        SELECT group_label, keyword_group_name, theme, issue, hot_theme, code, name, stock_comment, 
            close_rate AS stock_change, trade_amount AS stock_amount, is_leader, is_watchlist,
            MAX(trade_amount) OVER (PARTITION BY keyword_group_name) AS max_amount,
            SUBSTRING(keyword_group_name, INSTR(keyword_group_name, ' ') + 1) AS remaining_keywords
        FROM v_market_event
        WHERE date = '$report_date'
    ) AS theme_group
    ORDER BY theme_group.hot_theme DESC,   -- 핫테마 우선 정렬
            theme_group.keyword_group_name ASC,   -- 키워드 이름 순서로 정렬
            theme_group.max_amount DESC,   -- 키워드에서 최대 거래대금 키워드순 정렬
            theme_group.is_leader DESC,    -- 주도주 종목 우선 정렬
            theme_group.is_watchlist DESC, -- 관심종목 종목 우선 정렬
            theme_group.stock_change DESC  -- 동일한 키워드 내에서 등락률 기준으로 종목 정렬
";
$sector_result = $mysqli->query($group_query);
$group_data = [];

// 오늘 날짜 확인
$today = date("Y-m-d");
$current_time = date("H:i");
$cutoff_time = "19:30";

// 오늘 일자인지 여부 확인
$is_today = ($report_date == $today);

// 오늘 일자이고, 19:30 이전인 경우
if ($sector_result->num_rows > 0 && !($is_today && $current_time < $cutoff_time)) {
    // 과거 일자 또는 19:30 이후에는 기존 v_market_event 데이터 사용
    while ($row = $sector_result->fetch_assoc()) {
        $group_data[$row['group_label']][] = $row;
    }
}
else {
    // 오늘 19:30 이전에는 v_daily_price 데이터 사용
    $alternative_query = "
        SELECT a.code, a.name, a.close_rate AS stock_change, a.amount AS stock_amount,
               a.is_leader, a.is_watchlist, '' AS stock_comment,
               REPLACE(SUBSTRING_INDEX(c.group_name, ' ', 1), '#', '') AS group_label,
               SUBSTRING(c.group_name, INSTR(c.group_name, ' ') + 1) AS remaining_keywords
        FROM 
        (   SELECT code, name, close_rate, volume, amount, source,
                   CASE WHEN amount > 1000 THEN '1' ELSE '0' END is_leader,
                   CASE WHEN close_rate > 15 THEN '1' ELSE '0' END is_watchlist
            FROM v_daily_price
            WHERE date = '$report_date'
            AND ((amount > 10 AND close_rate > 20) OR (amount > 300 AND close_rate > 5))
            ORDER BY close_rate DESC
        ) a
        LEFT JOIN 
        (   SELECT code,
                   (SELECT 
                       keyword_group_id
                    FROM 
                        market_events me 
                    WHERE 
                        me.event_id = mes.event_id 
                    ORDER BY 
                        me.date DESC 
                    LIMIT 1
                   ) AS group_id
            FROM 
                market_event_stocks mes
            GROUP BY 
                code
        ) b
        ON b.code = a.code
        LEFT JOIN keyword_groups c
        ON c.group_id = b.group_id
        ORDER BY SUM(amount) OVER (PARTITION BY c.group_name) DESC, a.close_rate DESC
    ";

    // Database_logQuery($alternative_query,[]);
    $alternative_result = $mysqli->query($alternative_query);

    while ($row = $alternative_result->fetch_assoc()) {
        // 다른 쿼리 결과 처리
        $group_data[$row['group_label']][] = $row;
    }
}

// Fetch recent 5 days themes and stocks
$recent_themes_query = "
    SELECT 
        CASE 
            WHEN me.group_label = me.theme THEN me.group_label 
            ELSE CONCAT(me.group_label, ' ', me.theme) 
        END AS theme,   -- group_label 과 theme 값을 연결하여 하나의 값으로 계산
        mes.code, 
        mes.name, 
        MAX(mes.trade_amount) AS max_trade_amount,  -- 종목별 가장 높은 거래대금
        MAX(mes.close_rate) AS max_close_rate,       -- 종목별 가장 높은 등락률
        MAX(mes.stock_comment) AS stock_comment
    FROM 
        market_events me
    JOIN 
        market_event_stocks mes 
        ON mes.event_id = me.event_id
    JOIN 
        (SELECT date
         FROM calendar
         WHERE date <= '$report_date'
		 ORDER BY date DESC
         LIMIT 7) c
        ON c.date = me.date 
    WHERE (mes.is_leader = '1' OR mes.is_watchlist = '1')
    GROUP BY 
        me.theme, mes.code, mes.name
    ORDER BY 
        MAX(me.date) OVER (PARTITION BY theme) DESC,        -- 가장 최근 테마 기준 정렬
        me.theme,                 -- 테마 순
        MAX(mes.close_rate) DESC  -- 테마 내에서 등락률 기준 역순 정렬
    ";

// Database_logQuery($recent_themes_query, [$report_date]);
$recent_themes_result = $mysqli->query($recent_themes_query);
$recent_themes = [];
while($row = $recent_themes_result->fetch_assoc()) {
    $recent_themes[$row['theme']][] = $row;
}

// Fetch stocks with more than 20% change
$stocks_20_query = "
    SELECT name, dp.close_rate stock_change
    FROM v_market_event vme
	JOIN daily_price dp ON dp.date = vme.date AND dp.code = vme.code AND dp.close_rate > 20
    WHERE vme.date BETWEEN DATE_ADD('$report_date', INTERVAL -5 DAY) AND '$report_date'
    ORDER BY dp.close_rate DESC";
$stocks_20_result = $mysqli->query($stocks_20_query);
$stocks_20 = [];
while($row = $stocks_20_result->fetch_assoc()) {
    $stocks_20[] = $row;
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


// Fetch Market Issues
$issueQuery = $mysqli->prepare("
    SELECT mi.*, kg.group_name 
    FROM market_issues mi 
    LEFT JOIN keyword_groups kg 
    ON mi.issue_id = kg.group_id 
    WHERE mi.date = ?
    ORDER BY kg.group_name ASC
");
$issueQuery->bind_param('s', $report_date);
$issueQuery->execute();
$issueResult = $issueQuery->get_result();

?>

<head>
    <!-- 페이지 전용 스타일 -->
    <style>
        #wrapper {
            display: grid;
            grid-template-columns: 3fr 5fr 2fr;
            gap: 10px;
            padding: 10px;
            width: 99%;
        }

        #controls {
            grid-column: span 3;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        #date-controls {
            display: flex;
            flex-direction: column; /* 달력과 버튼이 위아래로 정렬 */
            align-items: center;
        }

        #date-navigation {
            display: flex;
            gap: 10px; /* 버튼 간 간격 */
            margin-top: 5px;
        }

        .nav-button {
            padding: 5px 10px;
            font-size: 12px; /* 버튼을 작게 설정 */
            background-color: #ff5f5f;
            border: none;
            color: white;
            cursor: pointer;
        }

        #report_date {
            width: 150px;
            padding: 5px;
            font-size: 14px;
            text-align: center;
        }

        #save-controls {
            display: flex;
            align-items: center;
        }

        #index-section {
            margin-top: 0;
            flex: 1 1 80%; /* 지수 섹션의 넓이를 50%로 설정 */
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .index-item {
            flex: 1;
            height: 90px; /* 텍스트 입력 영역의 높이를 크게 조정 */
            text-align: center;
            background: white;
            padding: 10px;
            border: 1px solid #ddd;
            margin: 0 10px;
        }

        #index-section h3 {
            margin-bottom: 10px;
        }

        #left-content, #middle-content, #right-content {
            background-color: white;
            border: 1px solid #ddd;
            padding: 20px;
            height: calc(100vh - 150px); /* 원하는 높이로 설정 (헤더, 인덱스 등 다른 요소들을 고려해서 조정) */
            overflow-y: auto; /* 세로 스크롤이 생기게 설정 */
            box-sizing: border-box; /* 패딩이 포함된 높이를 정확하게 계산 */
        }

        .report-content {
            /* display: inline; 한 줄로 표시 */
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #242;
        }

        textarea {
            height: 80px; /* 텍스트 입력 영역의 높이를 크게 조정 */
            margin-bottom: 15px;
        }

        textarea.large {
            height: 150px; /* 텍스트 입력 영역의 높이를 크게 조정 */
        }

        textarea.middle {
            height: 100px; /* 텍스트 입력 영역의 높이를 크게 조정 */
        }

        textarea.small {
            height: 50px; /* 텍스트 입력 영역의 높이를 크게 조정 */
        }

        #middle-content {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .group-container {
            display: flex;
            flex-wrap: wrap;  /* 여러 줄을 사용할 수 있게 함 */
            gap: 10px;  /* 카드 간격 */
            justify-content: space-between;  /* 좌우 간격을 균일하게 분배 */
        }

        .group-card {
            background-color: white;
            border: 1px solid #ddd;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            flex: 1 1 calc(33.33% - 20px);  /* 3열이 나오도록 설정 (100%를 3으로 나눈 값) */
            min-width: 280px;  /* 최소 너비 설정 */
            height: auto;
        }

        .keyword-row h4 {
            font-size: 1em;
            margin-top: 10px;
            color: #007bff !important;
        }

        .stock-item {
            padding: 10px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .stock-item:last-child {
            border-bottom: none;
        }

        .stock-row {
            display: flex;
            justify-content: space-between; /* 좌우 정렬 */
            align-items: center; /* 세로 가운데 정렬 */
            padding: 3px 0;
        }

        .stock-name {
            text-align: left;
            font-weight: bold;
            color: #333;
            max-width: 60%; /* 이름은 좌측 공간을 차지하게 함 */
        }

        .stock-change {
            text-align: right;
            font-weight: bold;
            color: #333;
            flex-shrink: 0; /* 우측 공간을 차지하게 함 */
        }

        .stock-change span {
            margin-left: 5px;
        }

        .stock-amount {
            font-size: 0.9em;
            color: #666;
        }

        .stock-comment {
            text-align: left;
            font-size: 0.9em;
            color: #999;
            margin-top: 5px;
        }

        .recent-themes-container {
            background-color: #f4f4f9; /* Subtle background difference */
            padding: 15px;
            border-radius: 8px;
        }

        .theme-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: space-between;
        }

        .theme-card {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            width: calc(100% - 10px); /* 두 개씩 보여줄 수 있도록 넓이 조정 */
            min-width: 300px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
        }

        .theme-card:hover {
            background-color: #f0f8ff;
        }

        .stock-list {
            margin-top: 10px;
        }

        hr {
            border: none;
            border-top: 2px solid #ccc;
            margin: 20px 20px; /* 수평선 위아래 간격 */
        }

        /* Container for the Today's Issue title and Add Theme button */
        .issues-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        /* 이슈 리스트 */
        #issue_list_container {
            padding: 10px;
            overflow-y: auto;
        }

        .issue-card {
            padding: 10px;
            background-color: #f8f8f8;
            margin-bottom: 10px;
            border: 1px solid #ddd;
        }

        .issue-title {
            font-weight: bold;
            font-size: 1.2em;
        }

        .issue-link {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
            display: inline-block;
        }

        .issue-keywords {
            color: #333;
            font-weight: bold;
            margin-top: 10px;
        }

        .issue-keywords span {
            background-color: #e0e7ff;
            padding: 5px;
            border-radius: 4px;
            margin-right: 5px;
            display: inline-block;
        }
    </style>

</head>

<body>
<div id="container">

<div id="wrapper">
    <!-- Date and Save Controls -->
    <div id="controls">
        <div id="date-controls">
            <input type="date" id="report_date" value="<?= $report_date ?>" onchange="search()">
            <div id="date-navigation">
                <!-- 이전 날짜 버튼 -->
                <?php if ($previous_date): ?>
                    <button class="nav-button" onclick="window.location.href='market_report.php?report_date=<?= $previous_date ?>'">
                        &lt;&lt; 이전
                    </button>
                <?php endif; ?>

                <!-- 다음 날짜 버튼 -->
                <?php if ($next_date): ?>
                    <button class="nav-button" onclick="window.location.href='market_report.php?report_date=<?= $next_date ?>'">
                        다음 &gt;&gt;
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div id="save-controls">
            <button onclick="saveReport()">Save Report</button>
        </div>

        <div id="index-section">
            <?php
            // 각 마켓의 이름과 관련된 정보를 배열로 설정
            $markets = ['S&P 500', 'NASDAQ', 'KOSPI', 'KOSDAQ'];

            // 루프를 통해 각 마켓의 데이터를 출력
            foreach ($markets as $market) {
                // Close Rate 색상을 빨간색(양수) 또는 파란색(음수)으로 구분
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
    </div>

    <!-- Morning Report 제목 -->
    <div id="left-content">
        <p class="report-content">
            <a href="<?= htmlspecialchars($morning_news_link) ?>" target="_blank" class="no-underline">
                <?= htmlspecialchars($morning_report_title) ?>
            </a>
        </p>

        <h3>US Market Overview</h3>
        <textarea id="us_market_overview"><?= htmlspecialchars($us_market_overview) ?></textarea>

        <h3>Other Market Overview</h3>
        <textarea class="small" id="other_market_overview"><?= htmlspecialchars($other_market_overview) ?></textarea>

        <h3>Market Overview</h3>
        <textarea id="market_overview"><?= htmlspecialchars($market_overview) ?></textarea>
        
        <hr>
        
        <p class="report-content"><?= htmlspecialchars($evening_report_title) ?></p>

        <h3>Market Review</h3>
        <textarea class="large" id="market_review"><?= htmlspecialchars($market_review) ?></textarea>

        <!-- Add the "Today's Themes" section here -->
        <hr>

        <!-- Today's Themes Section -->
        <div class="issues-header">
            <h3>Today's Issues</h3>
            <button id="add-theme-btn" class="button-green" onclick="window.open('issue_register.php?date=<?= htmlspecialchars($report_date) ?>', '_blank')">Add Issues</button>
        </div>
        <!-- 이슈 리스트 -->
        <div id="issue_list_container">
            <?php while ($issue = $issueResult->fetch_assoc()): ?>
                <div class="issue-card">
                <div class="issue-title"><?= htmlspecialchars($issue['issue_title'], ENT_QUOTES | ENT_HTML401); ?></div>
                <p>링크: <a href="<?= htmlspecialchars($issue['issue_link'], ENT_QUOTES | ENT_HTML401); ?>" target="_blank" class="issue-link">
                    <?= htmlspecialchars($issue['issue_link'], ENT_QUOTES | ENT_HTML401); ?>
                </a></p>
                <p class="issue-keywords">
                    <?php foreach (Utility_GgetIssueKeywords($report_date, $issue['issue_id']) as $keyword): ?>
                        <span>
                            <a href="javascript:void(0);" class="no-underline"
                            onclick="openKeywordPopup('<?= htmlspecialchars($keyword['keyword'], ENT_QUOTES | ENT_HTML401); ?>');">
                            #<?= htmlspecialchars($keyword['keyword'], ENT_QUOTES | ENT_HTML401); ?>
                            <?= $keyword['stock_cnt']; ?>
                        </a>
                        </span>
                    <?php endforeach; ?>
                </p>
            </div>
            <?php endwhile; ?>
        </div>
    </div>


    <!-- Group and Stock Events (플렉스박스 형태로 구성) -->
    
    <div id="middle-content" class="group-container">
    <?php 
    $current_group_label = '';  // 현재 출력 중인 group_label
    $current_keyword = '';  // 현재 출력 중인 theme

    foreach ($group_data as $group => $stocks): ?>
        <div class="group-card">
            <!-- 그룹 라벨을 먼저 출력 -->
            <h3><?= htmlspecialchars($group) ?></h3>

            <?php foreach ($stocks as $stock): ?>
                <?php if ($stock['remaining_keywords'] !== $current_keyword): ?>
                    <!-- 테마가 바뀔 때마다 테마가 그룹 라벨과 다르면 출력 -->
                    <div class="keyword-row">
                        <h4 style="color: #888; margin-bottom: 10px;"><?= htmlspecialchars($stock['remaining_keywords']) ?></h4>
                    </div>
                    <?php $current_keyword = $stock['remaining_keywords']; // 새로운 테마 저장 ?>
                <?php endif; ?>

                <!-- 종목 출력 -->
                <div class="stock-item">
                    <div class="stock-row">
                        <div class="stock-name <?= $stock['is_leader'] === '1' ? 'leader' : '' ?> <?= $stock['is_watchlist'] === '1' ? 'watchlist' : '' ?>">
                            <?= htmlspecialchars($stock['name']) ?>
                        </div>
                        <div class="stock-change">
                            <span class="<?= Utility_GetCloseRateClass($stock['stock_change']) ?>">
                                <?= number_format($stock['stock_change'], 2) ?>%
                            </span>
                            <span class="stock-amount <?= Utility_GetAmountClass($stock['stock_amount']) ?>">
                                (<?= number_format($stock['stock_amount']) ?>억)
                            </span>
                        </div>
                    </div>
                    <div class="stock-comment">
                        <?= htmlspecialchars($stock['stock_comment']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

    <!-- Recent Themes and Stocks -->
    <div id="right-content">
        <div class="recent-themes-container">
            <h3>최근 7일간의 주도주 및 관심종목</h3>
            <div class="theme-list">
            <?php foreach($recent_themes as $theme => $stocks): ?>
                <div class="theme-card">
                    <h4><?= htmlspecialchars($theme) ?></h4>
                    <div class="stock-list">
                        <?php foreach ($stocks as $stock): ?>
                            <div class="stock-item">
                                <span class="stock-name"><?= htmlspecialchars($stock['name']) ?> (<?= htmlspecialchars($stock['code']) ?>)</span>
                                <span class="stock-change"><?= number_format($stock['max_close_rate'], 2) ?>% </span>
                                <span class="stock-amount"><?= number_format($stock['max_trade_amount']) ?>억</span>
                            </div>
                            <p class="stock-comment"><?= htmlspecialchars($stock['stock_comment']) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 20% 이상 종목 -->
    <!-- <div class="card stocks-20-section">
        <h3>20% 이상 종목</h3>
        <table>
            <thead>
                <tr>
                    <th>종목</th>
                    <th>변동</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($stocks_20 as $stock): ?>
                    <tr>
                        <td><?= htmlspecialchars($stock['name']) ?></td>
                        <td><?= htmlspecialchars($stock['stock_change']) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div> -->
</div>

</div>


<script>
    function search() {
        var selectedDate = document.getElementById('report_date').value;
        window.location.href = 'market_report.php?report_date=' + selectedDate;
    }

    function saveReport() {
        var report_date_element = document.getElementById('report_date');
        var market_overview_element = document.getElementById('market_overview');
        var us_market_overview_element = document.getElementById('us_market_overview');
        var other_market_overview_element = document.getElementById('other_market_overview');
        var market_review_element = document.getElementById('market_review');

        if (!report_date_element || !market_overview_element || !us_market_overview_element || !other_market_overview_element || !market_review_element) {
            console.error('One or more required elements are not found.');
            return;
        }

        var report_date = report_date_element.value;
        var market_overview = market_overview_element.value;
        var us_market_overview = us_market_overview_element.value;
        var other_market_overview = other_market_overview_element.value;
        var market_review = market_review_element.value;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'market_process.php?action=save_report', true);
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                if (xhr.status == 200) {
                    var response = JSON.parse(xhr.responseText); // JSON 응답을 파싱
                    if (response.status === 'success') {
                        alert(response.message); // 성공 메시지 표시
                    } else {
                        alert('Error: ' + response.message); // 오류 메시지 표시
                    }
                } else {
                    alert('Server error: ' + xhr.status); // HTTP 상태 코드에 따른 오류 표시
                }
            }
        };

        // 데이터를 전송할 때 각 필드를 포함해서 보내는지 확인합니다.
        xhr.send(
            '&report_date=' + encodeURIComponent(report_date) + 
            '&market_overview=' + encodeURIComponent(market_overview) + 
            '&us_market_overview=' + encodeURIComponent(us_market_overview) + 
            '&other_market_overview=' + encodeURIComponent(other_market_overview) + 
            '&market_review=' + encodeURIComponent(market_review)
        );
    }

    window.onload = function() {
        var reportDate = getParameterByName('report_date');
        if (!reportDate) {
            var select = document.getElementById('report_date');
            if (select.options.length > 0) {
                search(); // 날짜 자동 선택 시 검색 트리거
            }
        }
    }
    
    function openKeywordPopup(keyword) {
        const url = `keyword_group_list.php?keyword=${encodeURIComponent(keyword)}`;
        window.open(url, '_blank');
    }
</script>
</body>
</html>
