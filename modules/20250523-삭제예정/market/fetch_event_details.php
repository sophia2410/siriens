<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php");
require($_SERVER['DOCUMENT_ROOT']."/modules/common/utility.php");

$event_id = $_GET['event_id'] ?? '';
$date = '';

if ($event_id) {
    $response = ['eventDetails' => [], 'stocks' => []];

    // 이슈 상세 정보 조회
    $eventQuery = $mysqli->prepare("
        SELECT me.*, kg.group_name AS keyword_group_name 
        FROM market_events me 
        LEFT JOIN keyword_groups kg ON me.keyword_group_id = kg.group_id 
        WHERE me.event_id = ?
    ");
    $eventQuery->bind_param('i', $event_id);
    $eventQuery->execute();
    $eventResult = $eventQuery->get_result()->fetch_assoc();

    // 날짜 형식을 Y-m-d로 변환
    if ($eventResult) {
        $date = $eventResult['date'];
    }

    $response['eventDetails'] = $eventResult;
    $eventQuery->close();

    // 관련 종목 정보 조회
    $stocksQuery = $mysqli->prepare("
        SELECT mes.*, vdp.close_rate
        FROM market_event_stocks mes 
        JOIN v_daily_price vdp ON vdp.code = mes.code AND vdp.date = ?
        WHERE mes.event_id = ? 
        ORDER BY mes.is_leader DESC, vdp.close_rate DESC");
    $stocksQuery->bind_param('si', $date, $event_id); // Bind date and event_id
    $stocksQuery->execute();
    $result = $stocksQuery->get_result();
    while ($stock = $result->fetch_assoc()) {
        $response['stocks'][] = $stock;
    }
    $stocksQuery->close();

    echo json_encode($response);
}
?>
