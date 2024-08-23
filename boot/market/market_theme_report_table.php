<?php
// table_view.php

require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$endDate = date('Ymd', time());
$startDate = date('Ymd', strtotime('-14 days', time()));

// Fetch themes with occurrence count and sorted by the latest date and occurrence count
$theme_query = "
    SELECT 
        mi.theme, 
        mi.date AS theme_date,  
        kg.group_name AS keyword_group_name,
        mis.name AS stock_name, 
        mis.code AS stock_code, 
        mis.close_rate, 
        mis.trade_amount,
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
        mi.date ASC,  -- 종목이 나타난 일자 순으로 정렬
        CASE WHEN mi.theme != '' THEN MAX(mi.date) OVER (PARTITION BY mi.theme) ELSE 0 END DESC,  -- 테마의 최신 날짜 순으로 정렬
        occurrence_count DESC;  -- 발생 건수 순으로 정렬";

$theme_result = $mysqli->query($theme_query);

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
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
            border: 2px solid #444;
        }

        th, td {
            border: 1px solid #888;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .theme-cell {
            font-weight: bold;
            background-color: #e0e0e0;
            vertical-align: middle;
        }

        .border-top-strong {
            border-top: 4px solid #000;
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
            <th colspan=2>테마/일자</th>
            <?php foreach ($dates as $date): ?>
                <th><?= date('m월 d일', strtotime($date)) ?></th>
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
                                        <?= htmlspecialchars($entry['stock_name']) ?>
                                        (<?= htmlspecialchars($entry['close_rate']) ?>, <?= htmlspecialchars($entry['trade_amount']) ?>)
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
