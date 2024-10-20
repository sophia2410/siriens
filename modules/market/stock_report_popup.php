<?php
// 공통 설정 파일 포함
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header_sub.php");

// 종목 코드와 이름 파라미터로 받음
$code = isset($_GET['code']) ? $_GET['code'] : '';
$name = isset($_GET['name']) ? $_GET['name'] : '';
$report_date = date('Y-m-d');

// 종목 코멘트 조회
$stock_comment = '';
$query = "
    SELECT CONCAT(CASE WHEN LENGTH(comment) > 30 THEN '<br>' ELSE '' END, '#', comment, ' ') AS comment 
    FROM stock_comment 
    WHERE code = '$code' 
    ORDER BY id";
$result = $mysqli->query($query);
while ($row = $result->fetch_assoc()) {
    $stock_comment .= $row['comment'];
}

// 최근 15일간의 등락률과 거래대금 조회 (가로로 풀어서 표시)
$recent_changes = [];
$query = "
    SELECT cal.date, DATE_FORMAT(cal.date, '%m-%d') mm_dd, xray.close_rate, xray.high_rate, xray.low_rate, xray.trade_amount, xray.amount, xray.cnt
    FROM (
        SELECT date
        FROM calendar
        WHERE date <= '$report_date'
        ORDER BY date DESC
        LIMIT 150
    ) cal
    LEFT JOIN (
        SELECT dp.code, dp.date, dp.close_rate, dp.high_rate, dp.low_rate, 
            ROUND(dp.amount / 100000000, 0) trade_amount, 
            ROUND(xr.tot_amt / 100000000, 1) amount,  
            xr.tot_cnt cnt
        FROM daily_price dp
        LEFT OUTER JOIN kiwoom_xray_tick_summary xr
        ON dp.date = xr.date
        AND dp.code = xr.code
        WHERE dp.code = '$code'
    ) xray
    ON xray.date = cal.date
    ORDER BY cal.date DESC";
$result = $mysqli->query($query);
while ($row = $result->fetch_assoc()) {
    $recent_changes[] = $row;
}

// 이벤트에 등록된 종목 정보 조회
$event_stocks = [];
$query = "
    SELECT date, group_label, keyword_group_name, theme, name, close_rate, trade_amount, stock_comment
    FROM v_market_event 
    WHERE code = '$code' 
    AND date <= '$report_date'
    ORDER BY date DESC";
$result = $mysqli->query($query);
while ($row = $result->fetch_assoc()) {
    $event_stocks[] = $row;
}

// 종목 관련 뉴스 조회
$news_list = [];
$query = "
    SELECT news_date, title, content, publisher, writer, link, keyword 
    FROM signals 
    WHERE code = '$code' 
    ORDER BY news_date DESC";
$result = $mysqli->query($query);
while ($row = $result->fetch_assoc()) {
    $news_list[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>종목 대시보드</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.highcharts.com/stock/highstock.js"></script>
    <script src="https://code.highcharts.com/modules/annotations.js"></script>
</head>
<body>
<div id="dashboard-wrapper">
    <!-- 차트 영역 -->
    <div id="chart-section">
        <h2><?php echo $name; ?></h2>
        <!-- 차트를 표시할 div -->
        <div id="chart-container" data-code='<?php echo $code; ?>' data-name='<?php echo $name; ?>' data-selected="5" style="height: 580px; min-width: 310px;"></div>
    </div>

    <!-- 최근 등락률 (가로로 표시) -->
    <div id="price-section">
        <h3>최근 등락률 및 거래대금</h3>
        <div class="scrollable-x-content"> <!-- 가로 스크롤 추가 -->
            <table class="small-table">
                <thead>
                    <tr>
                        <?php foreach ($recent_changes as $change): ?>
                            <th style="width:100px"><?= $change['mm_dd'] ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($recent_changes as $change): ?>
                            <td class="<?= Utility_GetCloseRateClass($change['close_rate']) ?>" style="width:100px"><?= $change['close_rate'] ?>%</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($recent_changes as $change): ?>
                            <td><?= $change['high_rate'] ?> / <?= $change['low_rate'] ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($recent_changes as $change): ?>
                            <td class="<?= Utility_GetAmountClass($change['trade_amount']) ?>"><?= number_format($change['trade_amount']) ?>억</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="border-strong">
                        <?php foreach ($recent_changes as $change): ?>
                            <td style='text-align: right;'><?= number_format($change['cnt']) ?>건</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($recent_changes as $change): ?>
                            <td class="<?= Utility_GetAmountClass($change['amount']) ?>"><?= number_format($change['amount']) ?>억</td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 종목 코멘트 -->
    <div id="comment-section">
        <h3>종목 코멘트</h3>
        <div class="scrollable-content"><?= $stock_comment ?></div>
    </div>

    <!-- 이벤트에 등록된 종목 내역 (그리드 형태) -->
    <div id="event-section">
        <h3>이벤트 등록 종목</h3>
        <div class="scrollable-content">
            <table>
                <thead>
                    <tr>
                        <th>일자</th>
                        <th>테마</th>
                        <th>그룹명</th>
                        <th>등락률</th>
                        <th>거래대금</th>
                        <th>코멘트</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($event_stocks as $stock): ?>
                    <tr>
                        <td><?= $stock['date'] ?></td>
                        <td><?= $stock['theme'] ?></td>
                        <td><?= $stock['keyword_group_name'] ?></td>
                        <td><?= $stock['close_rate'] ?>%</td>
                        <td><?= number_format($stock['trade_amount']) ?>억</td>
                        <td><?= $stock['stock_comment'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 종목 관련 뉴스 -->
    <div id="news-section">
        <h3>관련 뉴스</h3>
        <div class="scrollable-content">
            <ul>
                <?php foreach ($news_list as $news): ?>
                    <li>
                        <?= $news['news_date'] ?> <a href="<?= $news['link'] ?>" target="_blank"><?= $news['title'] ?></a> (<?= $news['publisher'] ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- 차트 스크립트 -->
<script src='/boot/watchlist/highchart.js?v=1.0.1'></script>

<style>
#dashboard-wrapper {
    display: grid;
    grid-template-columns: 5fr 3fr;
    gap: 10px;
    padding: 20px;
    width: 100%;
}

#chart-section {
    grid-column: 1 / -1;
}

#price-section, #comment-section, #event-section, #news-section {
    background-color: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    margin-bottom: 10px;
}

#price-section {
    background-color: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
    overflow-x: auto; /* 가로 스크롤 활성화 */
}

.small-table th, .small-table td {
    width: 100px !important; 
    white-space: nowrap !important;
}

/* 스크롤 가능한 콘텐츠 */
.scrollable-content {
    max-height: 350px;
    overflow-y: auto;
}

</style>

</body>
</html>
