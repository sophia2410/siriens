<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$endDate = date('Ymd', time());
$startDate = date('Ymd', strtotime('-14 days', time()));

// Fetch themes with occurrence count and sorted by the latest date and occurrence count
$theme_query = "
    SELECT 
        CASE WHEN mi.theme = '' THEN mi.sector ELSE mi.theme END AS theme, 
        CASE WHEN mi.theme = '' THEN 'sector' ELSE 'theme' END AS group_type, 
        mi.date AS theme_date,  
        kgm.group_name AS keyword_group_name,
        mis.name AS stock_name, 
        mis.code AS stock_code, 
        mis.close_rate, 
        mis.trade_amount,
		mis.is_leader,
        COUNT(mi.date) OVER (PARTITION BY mi.theme, mi.sector) AS occurrence_count,  -- 테마별 발생 건수 계산
        MIN(mi.date) OVER (PARTITION BY mi.theme, kgm.group_name) AS keyword_occurrence_date,  -- 키워드그룹별 최초 발생 일
        COUNT(mi.date) OVER (PARTITION BY mi.theme, kgm.group_name) AS keyword_occurrence_count  -- 키워드그룹별 종목 건수
    FROM 
        market_issues mi
    JOIN 
        market_issue_stocks mis ON mis.issue_id = mi.issue_id
    LEFT JOIN 
        keyword_groups_master kgm ON mi.keyword_group_id = kgm.group_id
    WHERE 
        mi.date BETWEEN '$startDate' AND '$endDate'
    AND 
        (mis.trade_amount > 300 or mis.close_rate > 10)
    ORDER BY 
	    CASE WHEN mi.theme = '' THEN 'sector' ELSE 'theme' END DESC, -- 테마가 없는경우에는 섹터 기준, 테마 우선 정렬
        CASE WHEN mi.theme != '' THEN MAX(mi.date) OVER (PARTITION BY mi.theme) ELSE 0 END DESC,  -- 테마의 최신 날짜 순으로 정렬
        occurrence_count DESC,  -- 발생 건수 순으로 정렬
		MIN(mi.date) OVER (PARTITION BY mi.theme, kgm.group_name), -- 키워드그룹 발생 날짜 순으로 정렬
		COUNT(mi.date) OVER (PARTITION BY mi.theme, kgm.group_name) DESC, -- 키워드그룹별 종목 건수로 정렬
		kgm.group_name,
		mi.date,
		mis.close_rate";

logQuery($theme_query, [$startDate, $endDate]);
$theme_result = $mysqli->query($theme_query);

$themes = [];

while ($row = $theme_result->fetch_assoc()) {
    $theme = $row['theme'];
    $theme_date = date('m/d', strtotime($row['theme_date']));
    $keyword_group_name = $row['keyword_group_name'];
    $stock_code = $row['stock_code'];

    // Initialize the theme if not already set
    if (!isset($themes[$theme])) {
        $themes[$theme] = ['dates' => []];
    }

    // Add the theme date to the list of dates if not already added
    if (!in_array($theme_date, $themes[$theme]['dates'])) {
        $themes[$theme]['dates'][] = $theme_date;
    }

    // Initialize or update the stock entry
    if (!isset($themes[$theme][$keyword_group_name][$stock_code])) {
        $themes[$theme][$keyword_group_name][$stock_code] = [
            'stock_name' => $row['stock_name'],
            'max_close_rate' => 0,
            'max_trade_amount' => 0,
            'symbols' => [],
        ];
    }

    // Define symbols based on criteria
    $symbols = [
        'star' => $row['close_rate'] >= 30 ? '★' : '',
        'diamond' => $row['close_rate'] >= 20 ? '◆' : '',
        'triangle' => $row['close_rate'] >= 10 ? '▲' : ''
    ];

    // Add symbols to the stock entry
    foreach ($symbols as $symbol) {
        if ($symbol && !in_array($symbol, $themes[$theme][$keyword_group_name][$stock_code]['symbols'])) {
            $themes[$theme][$keyword_group_name][$stock_code]['symbols'][] = $symbol;
        }
    }

    // Store the highest close rate
    $themes[$theme][$keyword_group_name][$stock_code]['max_close_rate'] = max($themes[$theme][$keyword_group_name][$stock_code]['max_close_rate'], $row['close_rate']);
    $themes[$theme][$keyword_group_name][$stock_code]['max_trade_amount'] = max($themes[$theme][$keyword_group_name][$stock_code]['max_trade_amount'], $row['trade_amount']);
}

// Sort dates in descending order (latest dates first)
foreach ($themes as $theme => &$data) {
    rsort($data['dates']); // Sort dates in descending order
}
unset($data); // Clear reference

// Sort stocks by symbol count and individual symbol priority within each keyword group
foreach ($themes as &$theme_data) {
    foreach ($theme_data as $key => &$keyword_groups) {
        if ($key === 'dates') continue;  // Skip the date entry

        uasort($keyword_groups, function($a, $b) {
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
            
            return 0;
        });
    }
}
unset($theme_data); // Clean up reference
unset($keyword_groups);
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
            font-size: 0.9em;
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
    <?php foreach ($themes as $theme => $keyword_groups): ?>
        <div class="theme-box">
            <div class="theme-header">
                <?= htmlspecialchars($theme) ?>
                (<?= implode(', ', $keyword_groups['dates']) ?>)
            </div>
            <?php unset($keyword_groups['dates']); // Remove the dates key to prevent displaying it in stocks ?>
            <?php foreach ($keyword_groups as $keyword_group_name => $stocks): ?>
                <div class="keyword-group-header"><?= htmlspecialchars($keyword_group_name) ?></div>
                <ul class="stock-list">
                    <?php foreach ($stocks as $stock_code => $stock): ?>
                        <li class="stock-item">
                            <span>
                                <?= htmlspecialchars($stock['stock_name']) ?>
                                <span class="symbols"><?= implode('', $stock['symbols']) ?></span>
                            </span>
                            <span>
                                <?= number_format($stock['max_close_rate'], 2) ?>% (<?= number_format($stock['max_trade_amount']) ?>억)
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
