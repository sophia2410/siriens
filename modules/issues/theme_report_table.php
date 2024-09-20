<?php
// table_view.php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header_sub.php");

$endDate = date('Y-m-d', time());
$startDate = date('Y-m-d', strtotime('-20 days', time()));

// Fetch themes with occurrence count and sorted by the latest date and occurrence count
$theme_query = "
    SELECT 
        CASE WHEN mi.theme != '' THEN mi.theme WHEN mis.sector != '' THEN mis.sector ELSE '미분류' END AS theme, -- 기존 theme로 분류해서 돌아가는 로직 활용하기 위해, theme 컬럼명 활용
        CASE WHEN mi.theme != '' THEN 'theme' ELSE 'sector' END AS group_type, 
        mi.date AS theme_date, 
        mi.hot_theme, 
        kg.group_name AS keyword_group_name,
        mis.name AS stock_name, 
        mis.code AS stock_code, 
        mis.close_rate, 
        mis.trade_amount,
        mis.is_leader,
        mis.is_watchlist,
        COUNT(mi.date) OVER (PARTITION BY mi.theme) AS occurrence_count  -- 테마별 발생 건수 계산
    FROM 
        market_issues mi
    JOIN 
        market_issue_stocks mis ON mis.issue_id = mi.issue_id
    LEFT JOIN 
        keyword_groups kg ON mi.keyword_group_id = kg.group_id
    WHERE 
        mi.date BETWEEN '$startDate' AND '$endDate'
    AND 
        (mis.trade_amount > 500 or mis.close_rate > 10)
    ORDER BY 
        CASE WHEN mi.theme != '' THEN 'theme' ELSE 'sector' END DESC, -- 테마 우선 정렬
        CASE WHEN mi.theme != '' THEN MAX(mi.date) OVER (PARTITION BY mi.theme) ELSE 0 END DESC,  -- 테마의 최신 날짜 순으로 정렬
        occurrence_count DESC,  -- 발생 건수 순으로 정렬
        mi.date ASC,  -- 종목이 나타난 일자 순으로 정렬
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
$index_result = $mysqli->query($index_query);

$indices = [];
while ($row = $index_result->fetch_assoc()) {
    // Convert the date from YYYYMMDD to YYYY-MM-DD for consistency
    $indices[$row['date']][$row['market_fg']] = [
        'close_rate' => number_format($row['close_rate'], 2) . "%",
        'amount' => number_format($row['amount_in_trillion'], 2) . "조",
    ];
}

$themes = [];
$dates = [];
$stock_appearance_order = [];  // 종목이 날짜별로 등장하는 순서를 추적

// 데이터 정렬 및 그룹화
while ($row = $theme_result->fetch_assoc()) {
    $theme = $row['theme'];
    $theme_date = date('Y-m-d', strtotime($row['theme_date']));
    $keyword_group_name = $row['keyword_group_name'];
    $stock_code = $row['stock_code'];
    $stock_name = $row['stock_name'];
    $is_leader = $row['is_leader'];
    $is_watchlist = $row['is_watchlist'];

    // Initialize the theme if not already set
    if (!isset($themes[$theme])) {
        $themes[$theme] = [];
    }

    // Track the appearance order of the stock
    if (!isset($stock_appearance_order[$stock_code])) {
        $stock_appearance_order[$stock_code] = 1;
    } else {
        $stock_appearance_order[$stock_code]++;
    }

    // Initialize or update the stock entry
    if (!isset($themes[$theme][$keyword_group_name][$theme_date])) {
        $themes[$theme][$keyword_group_name][$theme_date] = [];
    }

    $themes[$theme][$keyword_group_name][$theme_date][] = [
        'stock_name' => $stock_name . $stock_appearance_order[$stock_code],  // Append the appearance order to the stock name
        'close_rate' => number_format($row['close_rate'], 2) . "%",
        'trade_amount' => number_format($row['trade_amount']) . "억",
        'close_rate_css' => $row['close_rate'],
        'trade_amount_css' => $row['trade_amount'],
        'is_leader' => $is_leader,
        'is_watchlist' => $is_watchlist,
    ];

    // 날짜 저장
    if (!in_array($theme_date, $dates)) {
        $dates[] = $theme_date;
    }
}

// 날짜를 최신순으로 정렬
rsort($dates);
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
        <?php foreach ($themes as $theme => $groups): ?>
            <?php $row_count = count($groups); ?>
            <tr class="border-top-strong">
                <td class="theme-cell" rowspan="<?= $row_count ?>">
                    <?= htmlspecialchars($theme) ?>
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
                                        <span class="<?= $watchListClass ?> <?= $leaderClass ?>"><?= htmlspecialchars($entry['stock_name']) ?></span>
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
