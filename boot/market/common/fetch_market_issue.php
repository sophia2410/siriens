<?php
$dateParam = $_GET['date'] ?? date('Y-m-d');
$formattedDate = str_replace('-', '', $dateParam);

// 마켓이슈 불러오기
$issuesQuery = $mysqli->prepare("SELECT mi.*, kg.group_name FROM market_issues mi LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id WHERE mi.date = ? ORDER BY mi.hot_theme DESC, kg.group_name ASC");
$issuesQuery->bind_param('s', $formattedDate);
$issuesQuery->execute();
$issuesResult = $issuesQuery->get_result();

$stocksQuery = $mysqli->prepare("
    SELECT *
    FROM market_issue_stocks
    WHERE date = ? 
    ORDER BY is_leader DESC, close_rate DESC");
$stocksQuery->bind_param('s', $formattedDate); 
$stocksQuery->execute();
$stocksResult = $stocksQuery->get_result();

$stocksData = [];
while ($stock = $stocksResult->fetch_assoc()) {
    $stocksData[$stock['issue_id']][] = $stock;
}
?>
