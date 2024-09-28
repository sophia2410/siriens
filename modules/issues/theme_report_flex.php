<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header_sub.php");

$endDate = date('Y-m-d', time());
$startDate = date('Y-m-d', strtotime('-15 days', time()));

// Fetch themes with occurrence count and sorted by the latest date and occurrence count
$theme_query = "
    SELECT 
        mi.group_label, 
        mi.theme, 
        mi.date AS issues_date,  
        kg.group_name AS keyword_group_name,
        SUBSTRING(kg.group_name, INSTR(kg.group_name, ' ') + 1) AS remaining_keywords,
        mis.name AS stock_name, 
        mis.code AS stock_code, 
        mis.close_rate, 
        mis.trade_amount,
		MAX(mis.is_leader) OVER (PARTITION BY mi.theme, kg.group_name, mis.code) AS is_leader, -- leader 여부
		MAX(mis.is_watchlist) OVER (PARTITION BY mi.theme, kg.group_name, mis.code) AS is_watchlist, -- 관심종목 여부
        COUNT(mi.date) OVER (PARTITION BY mi.group_label) AS occurrence_count,  -- 그룹별 발생 건수 계산
        MIN(mi.date) OVER (PARTITION BY kg.group_name) AS keyword_occurrence_date,  -- 키워드그룹별 최초 발생 일
        COUNT(mi.date) OVER (PARTITION BY kg.group_name) AS keyword_occurrence_count  -- 키워드그룹별 종목 건수
    FROM 
        market_issues mi
    JOIN 
        market_issue_stocks mis ON mis.issue_id = mi.issue_id
    LEFT JOIN 
        keyword_groups kg ON mi.keyword_group_id = kg.group_id
    WHERE 
        mi.date BETWEEN '$startDate' AND '$endDate'
    AND 
        (mis.trade_amount > 300)
    ORDER BY 
        CASE WHEN mi.theme != '' THEN MAX(mi.date) OVER (PARTITION BY mi.group_label) ELSE 0 END DESC,  -- 그룹의 최신 날짜 순으로 정렬
        occurrence_count DESC,  -- 발생 건수 순으로 정렬
        mi.group_label,
		keyword_occurrence_date DESC,
		keyword_occurrence_count DESC, -- 키워드그룹별 종목 건수로 정렬
		kg.group_name,
		mi.date,
		mis.close_rate";

// Database_logQuery($theme_query, [$startDate, $endDate]);
$group_result = $mysqli->query($theme_query);

$groups = [];

while ($row = $group_result->fetch_assoc()) {
    $group = $row['group_label'];
    $theme = $row['theme'];
    $issues_date = date('m/d', strtotime($row['issues_date']));
    $remaining_keywords = $row['remaining_keywords'];
    $stock_code = $row['stock_code'];

    // Initialize the theme if not already set
    if (!isset($groups[$group])) {
        $groups[$group] = ['dates' => []];
    }

    // Add the theme date to the list of dates if not already added
    if (!in_array($issues_date, $groups[$group]['dates'])) {
        $groups[$group]['dates'][] = $issues_date;
    }

    // Initialize or update the stock entry
    if (!isset($groups[$group][$remaining_keywords][$stock_code])) {
        $groups[$group][$remaining_keywords][$stock_code] = [
            'stock_name' => $row['stock_name'],
            'is_leader' => $row['is_leader'],
            'is_watchlist' => $row['is_watchlist'],
            'max_close_rate' => 0,
            'max_trade_amount' => 0,
            'symbols' => [],
        ];
    }


    // Define symbols based on close rate
    $symbols = [
        'star' => '★',
        'diamond' => '◆',
        'triangle' => '▲'
    ];

    // 거래대금에 따른 스타일 클래스
    $amountInBillion = $row['trade_amount'];
    $tradeAmountClass = Utility_GetAmountClass($amountInBillion);

    // Add symbols to the stock entry with corresponding class based on close rate
    if ($row['close_rate'] >= 30) {
        $groups[$group][$remaining_keywords][$stock_code]['symbols'][] = '<span class="' . $tradeAmountClass . '">' . $symbols['star'] . '</span>';
    } elseif ($row['close_rate'] >= 20) {
        $groups[$group][$remaining_keywords][$stock_code]['symbols'][] = '<span class="' . $tradeAmountClass . '">' . $symbols['diamond'] . '</span>';
    } elseif ($row['close_rate'] >= 10) {
        $groups[$group][$remaining_keywords][$stock_code]['symbols'][] = '<span class="' . $tradeAmountClass . '">' . $symbols['triangle'] . '</span>';
    }

    // Store the highest close rate
    $groups[$group][$remaining_keywords][$stock_code]['max_close_rate'] = max($groups[$group][$remaining_keywords][$stock_code]['max_close_rate'], $row['close_rate']);
    $groups[$group][$remaining_keywords][$stock_code]['max_trade_amount'] = max($groups[$group][$remaining_keywords][$stock_code]['max_trade_amount'], $row['trade_amount']);
}

// Sort dates in descending order (latest dates first)
foreach ($groups as $group => &$data) {
    rsort($data['dates']); // Sort dates in descending order
}
unset($data); // Clear reference

// Sort stocks by symbol count and individual symbol priority within each keyword group
foreach ($groups as &$group_data) {
    foreach ($group_data as $key => &$keyword_group_mappings) {
        if ($key === 'dates') continue;  // Skip the date entry

        uasort($keyword_group_mappings, function($a, $b) {
            // Ensure symbols is an array
            $a_symbols = isset($a['symbols']) && is_array($a['symbols']) ? $a['symbols'] : [];
            $b_symbols = isset($b['symbols']) && is_array($b['symbols']) ? $b['symbols'] : [];

            // First, sort by the number of symbols (descending)
            $a_symbol_count = count($a_symbols);
            $b_symbol_count = count($b_symbols);
            
            if ($a_symbol_count !== $b_symbol_count) {
                return $b_symbol_count - $a_symbol_count;
            }

            // If the number of symbols is the same, sort by symbol priority
            $symbol_priority = ['★', '◆', '▲']; // Define the priority of symbols
            foreach ($symbol_priority as $symbol) {
                $a_has_symbol = in_array($symbol, $a_symbols);
                $b_has_symbol = in_array($symbol, $b_symbols);
                
                if ($a_has_symbol && !$b_has_symbol) {
                    return -1;
                } elseif (!$a_has_symbol && $b_has_symbol) {
                    return 1;
                }
            }

            // If the symbols are identical, sort by max close rate (descending)
            return $b['max_close_rate'] - $a['max_close_rate'];
            
            return 0;
        });
    }
}
unset($group_data); // Clean up reference
unset($keyword_group_mappings);
unset($stocks);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2주간 테마와 주도주 및 핫한 종목</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        #wrapper {
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .theme-box {
            flex: 1 1 calc(14.28% - 20px); /* 7 columns layout */
            background-color: white;
            margin: 10px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            box-sizing: border-box;
            min-width: 150px;
        }

        .theme-header {
            font-size: 1em;
            margin-bottom: 8px;
            color: #333;
        }

        .keyword-group-header {
            /* font-size: 0.9em; */
            font-size: 1em;
            margin-bottom: 8px;
            color: #007bff;
        }

        .stock-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .stock-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .stock-item span {
            color: #333;
            font-size: 0.9em;
        }

        .stock-item .symbols {
            margin-left: 5px;
            color: #333;
            font-weight: bold;
        }

        /* Responsive layout */
        @media (max-width: 1200px) {
            .theme-box {
                flex: 1 1 calc(20% - 20px); /* 5 columns layout on smaller screens */
            }
        }

        @media (max-width: 768px) {
            .theme-box {
                flex: 1 1 calc(33.33% - 20px); /* 3 columns layout on smaller screens */
            }
        }

        @media (max-width: 480px) {
            .theme-box {
                flex: 1 1 calc(50% - 20px); /* 2 columns layout on very small screens */
            }
        }
    </style>
</head>
<body>

<div id="wrapper">
    <?php foreach ($groups as $group => $keyword_group_mappings): ?>
        <div class="theme-box">
            <div class="theme-header">
                <?= htmlspecialchars($group) ?>
                <small>(<?= implode(', ', $keyword_group_mappings['dates']) ?>)</small>
            </div>
            <?php unset($keyword_group_mappings['dates']); // Remove the dates key to prevent displaying it in stocks ?>
            <?php foreach ($keyword_group_mappings as $remaining_keywords => $stocks): ?>
                <div class="keyword-group-header"><?= htmlspecialchars($remaining_keywords) ?></div>
                <ul class="stock-list">
                    <?php foreach ($stocks as $stock_code => $stock): ?>
                        <li class="stock-item">
                            <span>
                                <?php
                                // 주도주 표시
                                $leaderClass = ($stock['is_leader'] === '1') ? 'leader' : '';
                                // 관심종목 표시
                                $watchListClass = ($stock['is_watchlist'] === '1') ? 'watchlist' : '';
                                ?>
                                <span class="<?= $watchListClass ?> <?= $leaderClass ?>"><?= htmlspecialchars($stock['stock_name']) ?></span>
                                <span class="symbols"><?= implode('', $stock['symbols']) ?></span>
                            </span>
                            <span>
                                <?php
                                // 등락률 따른 스타일 클래스
                                $closeRateClass = Utility_GetCloseRateClass($stock['max_close_rate']);
                                // 금액에 따른 스타일 클래스
                                $amountClass = Utility_GetAmountClass($stock['max_trade_amount']);
                                ?>
                                <span class="<?= $closeRateClass ?>"><?= number_format($stock['max_close_rate'], 2) ?>%</span> (<span class="<?= $amountClass ?>"><?= number_format($stock['max_trade_amount']) ?>억</span>)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
