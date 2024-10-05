<?php
// table_view.php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header_sub.php");

// $_GET['date']에서 받은 값 처리 (기본값은 오늘 날짜)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$criteria = isset($_GET['criteria']) ? $_GET['criteria'] : 'all';

// 조회기준일자 구히가
$query = "
    SELECT MIN(c.date) AS start_date, MAX(c.date) AS end_date
    FROM (
        SELECT date
        FROM calendar
        WHERE date <= '$date'
        ORDER BY date DESC
        LIMIT 8
    ) AS c;
";

$result = $mysqli->query($query);
$row = $result->fetch_assoc();
$startDate = $row['start_date'];
$endDate = $row['end_date'];

// 조회기준에 따른 조건문
if ($criteria == 'all') {
    $whereCriteria = "(mes.trade_amount > 500 OR mes.close_rate > 10)";
} else {
    $whereCriteria = "(mes.trade_amount > 1000 OR mes.is_leader = '1' OR mes.is_watchlist = '1')";
}

// 과거일자 순으로 종목 등장 순서 카운팅
$past_order_query = "
    SELECT 
        me.group_label, 
        me.date AS event_date, 
        mes.code AS stock_code
    FROM 
        market_events me
    JOIN 
        market_event_stocks mes ON mes.event_id = me.event_id
    WHERE 
        me.date BETWEEN '$startDate' AND '$endDate'
    AND 
        $whereCriteria
    ORDER BY 
        me.date ASC;  -- 과거일자 순으로 정렬
";

$past_order_result = $mysqli->query($past_order_query);

// 종목 등장 순서를 기록할 배열을 날짜별로 나눔
$stock_appearance_order = [];
$stock_appearance_by_date = [];

while ($row = $past_order_result->fetch_assoc()) {
    $event_date = $row['event_date'];
    $stock_code = $row['stock_code'];

    // 날짜별로 종목 등장 순서를 기록
    if (!isset($stock_appearance_order[$stock_code])) {
        $stock_appearance_order[$stock_code] = 1;
    } else {
        $stock_appearance_order[$stock_code]++;
    }
    $stock_appearance_by_date[$stock_code][$event_date] = "<font color=blue>".$stock_appearance_order[$stock_code]."</font>";
}


// Fetch groups with occurrence count and sorted by the latest date and occurrence count
$theme_query = "
    SELECT 
        me.group_label, 
        me.date AS event_date, 
        me.theme, 
        me.hot_theme, 
        kg.group_name AS keyword_group_name,
        mes.name AS stock_name, 
        mes.code AS stock_code, 
        mes.close_rate, 
        mes.trade_amount,
        mes.is_leader,
        mes.is_watchlist,
        MAX(me.date) OVER (PARTITION BY me.group_label) AS last_occurrence_date,  -- 최신 날짜
        SUM(mes.trade_amount) OVER (PARTITION BY me.group_label, me.date) AS daily_trade_amount,  -- 일별 거래량 합계
        COUNT(me.date) OVER (PARTITION BY me.group_label, me.date) AS occurrence_count  -- 발생 건수 계산
    FROM 
        market_events me
    JOIN 
        market_event_stocks mes ON mes.event_id = me.event_id
    LEFT JOIN 
        keyword_groups kg ON me.keyword_group_id = kg.group_id
    WHERE 
        me.date BETWEEN '$startDate' AND '$endDate'
    AND 
        $whereCriteria
    ORDER BY 
        me.date DESC, -- 날짜를 우선 정렬, 최신 날짜가 먼저 오도록
        daily_trade_amount DESC,  -- 일별 거래량 기준으로 강한 테마(섹터) 정렬
        occurrence_count DESC,  -- 발생 건수 순으로 정렬
        group_label,
        is_leader DESC,
        is_watchlist DESC,
        close_rate DESC;  -- 등락률 높은 순으로 정렬";

$theme_result = $mysqli->query($theme_query);

$index_query = "
    SELECT 
        market_fg, 
        date, 
        close_rate, 
        ROUND(amount / 1000000000000, 2) AS amount_in_trillion  -- 거래대금을 천억 단위로 변환
    FROM 
        market_index 
    WHERE 
        date BETWEEN '$startDate' AND '$endDate'
    AND 
        market_fg IN ('KOSPI', 'KOSDAQ')
    ORDER BY 
        date ASC, market_fg ASC;
";
// echo "$index_query";
$index_result = $mysqli->query($index_query);

$indices = [];
while ($row = $index_result->fetch_assoc()) {
    // Convert the date from YYYYMMDD to YYYY-MM-DD for consistency
    $indices[$row['date']][$row['market_fg']] = [
        'close_rate' => number_format($row['close_rate'], 2) . "%",
        'amount' => number_format($row['amount_in_trillion'], 2) . "조",
    ];
}

$groups = [];
$dates = [];

// 데이터 정렬 및 그룹화
while ($row = $theme_result->fetch_assoc()) {
    $group_label = $row['group_label'];
    $theme = $row['theme'];
    $event_date = date('Y-m-d', strtotime($row['event_date']));
    $keyword_group_name = $row['keyword_group_name'];
    $stock_code = $row['stock_code'];
    $stock_name = $row['stock_name'];
    $is_leader = $row['is_leader'];
    $is_watchlist = $row['is_watchlist'];

    // Initialize the theme if not already set
    if (!isset($groups[$group_label])) {
        $groups[$group_label] = [];
    }

    // Initialize or update the stock entry
    if (!isset($groups[$group_label][$keyword_group_name][$event_date])) {
        $groups[$group_label][$keyword_group_name][$event_date] = [];
    }

    $groups[$group_label][$keyword_group_name][$event_date][] = [
        'theme' => $theme,
        'stock_name' => $stock_name . $stock_appearance_by_date[$stock_code][$event_date],  // Append the appearance order to the stock name
        'close_rate' => number_format($row['close_rate'], 2) . "%",
        'trade_amount' => number_format($row['trade_amount']) . "억",
        'close_rate_css' => $row['close_rate'],
        'trade_amount_css' => $row['trade_amount'],
        'is_leader' => $is_leader,
        'is_watchlist' => $is_watchlist,
    ];

    // 날짜 저장
    if (!in_array($event_date, $dates)) {
        $dates[] = $event_date;
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>테마 및 데이터 보기</title>
    <style>
        /* Fix the header */
        thead th {
            position: sticky;
            top: 0;
            background-color: white; /* Ensures the header has a background */
            z-index: 2; /* Ensures the header stays above other content */
            border-top: 3px solid #000;
            box-shadow: 0 2px 5px -2px rgba(0, 0, 0, 0.15); /* 부드러운 그림자 추가 */
        }

        .theme-cell {
            font-weight: bold;
            background-color: #e0e0e0;
            vertical-align: middle;
        }

        .border-top-strong {
            border-top: 3px solid #000;
        }

        .group-cell {
            background-color: #f7f7f7;
        }
    </style>
</head>
<body>

<table>
    <thead>
        <tr>
            <th colspan=2 class="theme-header">테마/일자</th>
            <?php foreach ($dates as $date): ?>
                <th>
                    <?= date('m월 d일', strtotime($date)) ?>

                    <div style="font-size: 0.9em;">
                        <div style="margin-bottom: 5px;">
                            <div style="font-weight: bold; color: <?= ($indices[$date]['KOSPI']['close_rate'][0] === '-') ? 'blue' : 'red'; ?>">
                                KOSPI <?= $indices[$date]['KOSPI']['close_rate'] ?>
                            </div>
                            <div style="font-size: 0.8em; color: #666;">
                                <?= $indices[$date]['KOSPI']['amount'] ?>
                            </div>
                        </div>
                        <div style="border-top: 1px solid #eee; padding-top: 5px;">
                            <div style="font-weight: bold; color: <?= ($indices[$date]['KOSDAQ']['close_rate'][0] === '-') ? 'blue' : 'red'; ?>">
                                KOSDAQ <?= $indices[$date]['KOSDAQ']['close_rate'] ?>
                            </div>
                            <div style="font-size: 0.8em; color: #666;">
                                <?= $indices[$date]['KOSDAQ']['amount'] ?>
                            </div>
                        </div>
                    </div>
                    
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($groups as $group => $groups): ?>
            <?php $row_count = count($groups); ?>
            <tr class="border-top-strong">
                <td class="theme-cell" rowspan="<?= $row_count ?>">
                    <?= htmlspecialchars($group) ?>
                </td>
                <?php $first = true; ?>
                <?php foreach ($groups as $keyword_group => $data): ?>
                    <?php if (!$first) echo '<tr>'; ?>
                    <td class="group-cell"><?= htmlspecialchars($keyword_group) ?></td>
                    <?php foreach ($dates as $date): ?>
                        <td>
                            <?php if (isset($data[$date])): ?>
                                <?php foreach ($data[$date] as $entry): ?>
                                    <div>
                                        <?php
                                        // 주도주 표시
                                        $leaderClass = ($entry['is_leader'] === '1') ? 'leader' : '';
                                        // 관심종목 표시
                                        $watchListClass = ($entry['is_watchlist'] === '1') ? 'watchlist' : '';
                                        // 등락률 따른 스타일 클래스
                                        $closeRateClass = Utility_GetCloseRateClass($entry['close_rate_css']);
                                        // 금액에 따른 스타일 클래스
                                        $amountClass = Utility_GetAmountClass($entry['trade_amount_css']);
                                        ?>
                                        <span class="<?= $watchListClass ?> <?= $leaderClass ?>"><?= $entry['stock_name'] ?></span>
                                        (<span class="<?= $closeRateClass ?>"><?= htmlspecialchars($entry['close_rate']) ?></span>, <span class="<?= $amountClass ?>"><?= htmlspecialchars($entry['trade_amount']) ?></span>)
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <?php if (!$first) echo '</tr>'; ?>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
