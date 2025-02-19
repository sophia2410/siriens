<?php
$pageTitle = "Market Report";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/tinymce_module.php");

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

// Market Report
$overview_query = "SELECT market_review, market_overview, morning_report_title, morning_news_link, evening_report_title FROM market_report WHERE date = '$report_date'";
$overview_result = $mysqli->query($overview_query);
$overview_row = $overview_result->fetch_assoc();
$market_review = isset($overview_row) ? $overview_row['market_review'] : '';
$market_overview = isset($overview_row) ? $overview_row['market_overview'] : '';
$morning_report_title = isset($overview_row) ? "【 ".$overview_row['morning_report_title']." 】": '';
$morning_news_link = isset($overview_row) ? $overview_row['morning_news_link'] : '';
$evening_report_title = isset($overview_row) && !empty($overview_row['evening_report_title']) ? "【 " . $overview_row['evening_report_title'] . " 】" : '';

// Fetch group data
$group_query = "
    SELECT tg.group_label,
        tg.theme,
        tg.hot_theme,
        tg.code,
        tg.name,
        tg.stock_comment,
        tg.stock_change,
        tg.stock_amount,
        tg.hot_theme,
        tg.is_leader,
        tg.is_watchlist,
        tg.keyword_group_name,
        tg.remaining_keywords
    FROM (
        SELECT group_label, keyword_group_name, theme, hot_theme, code, name, stock_comment, 
            close_rate AS stock_change, trade_amount AS stock_amount, is_leader, is_watchlist,
            MAX(trade_amount) OVER (PARTITION BY group_label) AS max_amount_group,
            MAX(trade_amount) OVER (PARTITION BY keyword_group_name) AS max_amount,
            SUBSTRING(keyword_group_name, INSTR(keyword_group_name, ' ') + 1) AS remaining_keywords
        FROM v_market_event
        WHERE date = '$report_date'
    ) AS tg
    WHERE (tg.stock_change > 15 OR tg.stock_amount > 500)
    ORDER BY tg.hot_theme DESC,        -- 핫테마 우선 정렬
            tg.max_amount_group DESC,  -- 그룹 거래대금 우선 정렬
            tg.keyword_group_name ASC, -- 키워드 이름 순서로 정렬
            tg.max_amount DESC,   -- 키워드에서 최대 거래대금 키워드순 정렬
            tg.is_leader DESC,    -- 주도주 종목 우선 정렬
            tg.is_watchlist DESC, -- 관심종목 종목 우선 정렬
            tg.stock_change DESC  -- 동일한 키워드 내에서 등락률 기준으로 종목 정렬
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
            AND ((amount > 100 AND close_rate > 20) OR (amount > 300 AND close_rate > 10))
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
        ORDER BY SUM(amount) OVER (PARTITION BY c.group_name) DESC, a.amount DESC
    ";

    // Database_logQuery($alternative_query,[]);
    $alternative_result = $mysqli->query($alternative_query);

    while ($row = $alternative_result->fetch_assoc()) {
        // 다른 쿼리 결과 처리
        $group_data[$row['group_label']][] = $row;
    }
}

?>

<head>

<style>
        form {
            display: contents; /* 레이아웃에 영향을 주지 않음 */
        }

        #wrapper {
            display: grid;
            grid-template-columns: 4fr 4fr 3fr;
            gap: 10px;
            padding: 10px;
            width: 100%;
        }

        #controls {
            grid-column: span 3;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0px;
        }

        #date-controls {
            display: flex;
            align-items: center;
        }

        #date-navigation {
            display: flex;
            gap: 2px; /* 버튼 간 간격 */
            margin-top: 5px;
        }

        .nav-button {
            padding: 5px 10px;
            background-color: #ff5f5f;
            border: none;
            color: white;
            cursor: pointer;
        }

        #report_date {
            width: 150px;
            padding: 10px;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
        }
        #index-section {
            margin-top: 0;
            flex: 1 1 70%; /* 지수 섹션의 넓이를 80%로 설정 */
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .index-item {
            flex: 1;
            height: auto; /* 텍스트 영역의 높이 자동 조정 */
            text-align: left; /* 왼쪽 정렬로 변경 */
            background: white;
            padding: 10px;
            border: 1px solid #ddd;
            margin: 0 10px;
            display: flex; /* 플렉스 박스로 설정 */
            justify-content: space-between; /* 왼쪽과 오른쪽에 각각 정렬 */
            align-items: center; /* 수직 가운데 정렬 */
        }

        .market-name {
            font-size: 1em;
            margin-right: 10px; /* 마켓 이름과 데이터 사이 간격 */
        }

        .market-data {
            font-size: 1.2em;
        }

        .market-amount {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
        }

        #left-content, #middle-content, #right-content {
            background-color: white;
            border: 1px solid #ddd;
            padding: 15px;
            height: calc(100vh - 150px) !important; /* 원하는 높이로 설정 (헤더, 인덱스 등 다른 요소들을 고려해서 조정) */
            overflow-y: auto !important; /* 세로 스크롤이 생기게 설정 */
            box-sizing: border-box; /* 패딩이 포함된 높이를 정확하게 계산 */
        }

        .report-content {
            /* display: inline; 한 줄로 표시 */
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #242;
        }

        .report-content-input {
            display: inline-block; /* 한 줄로 표시 */
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #242;
            border: 1px solid #ccc; /* 입력 박스 테두리 */
            padding: 5px 10px; /* 입력 박스 내부 여백 */
            border-radius: 4px; /* 모서리 둥글게 */
            width: 100%; /* 박스 크기 조정 */
            box-sizing: border-box; /* 패딩과 테두리 포함한 크기 계산 */
        }

        #right-content {
            display: block; /* Masonry.js 적용을 위해 block 설정 */
        }

        .group-card {
            background-color: white;
            border: 1px solid #ddd;
            padding: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px; /* 카드 간의 세로 간격 */
            margin-right: 15px;  /* 카드 간의 가로 간격 */
            width: 100%;
            box-sizing: border-box;
            min-width: 280px; /* 카드의 최소 너비 설정 */
            max-width: 290px; /* 카드의 최대 너비 설정 */
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
                    <button class="nav-button" onclick="window.location.href='market_report_register.php?report_date=<?= $previous_date ?>'">
                        &lt;&lt; 이전
                    </button>
                <?php endif; ?>

                <!-- 다음 날짜 버튼 -->
                <?php if ($next_date): ?>
                    <button class="nav-button" onclick="window.location.href='market_report_register.php?report_date=<?= $next_date ?>'">
                        다음 &gt;&gt;
                    </button>
                <?php endif; ?>

                <button class="button-green" onclick="saveReport()">Save Report</button>
            </div>
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
                <div class='index-item'>
                    <span class='market-name' style='font-weight: bold; color: black;'>$market</span>
                    <span class='market-data' style='font-weight: bold; color: $color;'>
                        " . (isset($index_data[$market]) ? number_format($index_data[$market]['close'],2)."(".htmlspecialchars($index_data[$market]['close_rate']) . '%)' : 'No data') . "
                    </span>";

                // 'KOSPI'와 'KOSDAQ'에 대해서만 Amount 출력
                if (in_array($market, ['KOSPI', 'KOSDAQ'])) {
                    echo "<p class='market-amount' style='font-size: 0.8em; color: #666;'>" . (isset($index_data[$market]) ? htmlspecialchars($index_data[$market]['amount_in_trillion']) . '조' : '') . "</p>";
                } else {
                    echo "<p class='market-amount' style='font-size: 0.8em; color: #666;'> &nbsp; </p>";
                }

                echo "</div>";
            }
            ?>
        </div>
    </div>

    <div id="left-content">
        <!-- 마켓 오버뷰 -->
        <label for="market_overview">마켓 오버뷰:</label>
        <p class="report-content">
            <a href="<?= htmlspecialchars($morning_news_link) ?>" target="_blank" class="no-underline">
                <?= htmlspecialchars($morning_report_title) ?>
            </a>
        </p>
        <textarea class="editor" name="market_overview" id="market_overview" rows="10"></textarea>
    </div>

    <div id="middle-content">
        <!-- 마켓 리뷰 -->
        <label for="market_review">마켓 리뷰:</label>
        <?php if ($evening_report_title === ''): ?>
            <!-- 입력 박스를 출력 -->
            <p><input type="text" class="report-content-input" id="evening_report_title" placeholder="저녁 리포트 제목을 입력하세요" /></p>
        <?php else: ?>
            <!-- 기존 텍스트를 출력 -->
            <p class="report-content"><?= htmlspecialchars($evening_report_title) ?></p>
        <?php endif; ?>
        <textarea class="editor" name="market_review" id="market_review" rows="10"></textarea>
        <?php loadTinyMCE('.editor', 1000); ?>
        <?php loadTinyMCEScripts(); ?>
    </div>
    
    <form id="report_form" action="market_process.php" method="POST">

    </form>

    <!-- Group and Stock Events (Masonry 적용) -->
    <div id="right-content">
        <?php 
        $current_group_label = '';  // 현재 출력 중인 group_label
        $current_keyword = '';  // 현재 출력 중인 theme

        foreach ($group_data as $group => $stocks): ?>
            <div class="group-card">
                <!-- 그룹 라벨을 먼저 출력 -->
                <h3>［<?= htmlspecialchars($group) ?>］</h3>

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
</div>
</div>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>

<!-- Masonry.js 라이브러리 추가 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/masonry/4.2.2/masonry.pkgd.min.js"></script>

<!-- Masonry.js를 활용한 레이아웃 초기화 -->
<script>
    function search() {
        var selectedDate = document.getElementById('report_date').value;
        window.location.href = 'market_report_register.php?report_date=' + selectedDate;
    }

    var elem = document.querySelector('#right-content');
    var msnry = new Masonry(elem, {
        itemSelector: '.group-card', // 카드 셀렉터
        columnWidth: '.group-card',  // 카드의 너비를 기준으로 배치
        percentPosition: true        // 퍼센트 기반의 배치
    });

    function saveReport() {
        var form = document.getElementById('report_form');
        var market_overview = tinymce.get('market_overview').getContent();
        var market_review = tinymce.get('market_review').getContent();
        var evening_report_title = document.getElementById('evening_report_title') ? 
                                document.getElementById('evening_report_title').value : "";

        // 로딩 표시 추가
        document.body.style.cursor = 'wait'; // 로딩 중 커서 변경

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'market_process.php?action=save_report', true);
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                document.body.style.cursor = 'default'; // 로딩 완료 후 커서 복구

                if (xhr.status == 200) {
                    var response = JSON.parse(xhr.responseText);

                    if (response.status === 'success') {
                        // 성공 처리 후 리로드
                        alert(response.message);

                        // 현재 URL 처리
                        var url = new URL(window.location.href);
                        url.searchParams.set('report_date', document.getElementById('report_date').value); // report_date 값 설정
                        window.location.href = url.toString(); // 업데이트된 URL로 이동
                    } else {
                        alert('Error: ' + response.message);
                    }
                } else {
                    alert('서버 오류: ' + xhr.status);
                }
            }
        };

        // 데이터 전송
        xhr.send(
            'report_date=' + encodeURIComponent(document.getElementById('report_date').value) +
            '&market_overview=' + encodeURIComponent(market_overview) +
            '&market_review=' + encodeURIComponent(market_review) +
            '&evening_report_title=' + encodeURIComponent(evening_report_title)
        );
    }

    window.onload = function() {
        setTimeout(function () {
            setTinyMCEContent('market_overview',<?= json_encode($market_overview) ?>);
            setTinyMCEContent('market_review',<?= json_encode($market_review) ?>);
        }, 100); // 초기화 대기
    }
</script>
</body>