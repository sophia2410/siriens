<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php");
require($_SERVER['DOCUMENT_ROOT']."/modules/common/utility.php");

$issue_id = $_GET['issue_id'] ?? '';
$date = '';

if ($issue_id) {
    $response = ['issueDetails' => [], 'stocks' => []];

    // 이슈 상세 정보 조회
    $issueQuery = $mysqli->prepare("
        SELECT mi.*, kg.group_name AS keyword_group_name 
        FROM market_issues mi 
        LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id 
        WHERE mi.issue_id = ?
    ");
    $issueQuery->bind_param('i', $issue_id);
    $issueQuery->execute();
    $issueResult = $issueQuery->get_result()->fetch_assoc();

    // 날짜 형식을 Y-m-d로 변환
    if ($issueResult) {
        $date = $issueResult['date'];
    }

    $response['issueDetails'] = $issueResult;
    $issueQuery->close();

    // 관련 종목 정보 조회
    $stocksQuery = $mysqli->prepare("
        SELECT mis.*, vdp.close_rate
        FROM market_issue_stocks mis 
        JOIN v_daily_price vdp ON vdp.code = mis.code AND vdp.date = ?
        WHERE mis.issue_id = ? 
        ORDER BY mis.is_leader DESC, vdp.close_rate DESC");
    $stocksQuery->bind_param('si', $date, $issue_id); // Bind date and issue_id
    $stocksQuery->execute();
    $result = $stocksQuery->get_result();
    while ($stock = $result->fetch_assoc()) {
        $response['stocks'][] = $stock;
    }
    $stocksQuery->close();

    echo json_encode($response);
}
?>
