<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

// JSON 반환을 위한 헤더 설정
header('Content-Type: application/json');

// 요청 타입 및 ID 가져오기
$journalId = isset($_GET['journal_id']) ? (int)$_GET['journal_id'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : 'journal';  // 기본값은 'journal'로 설정
$response = [];

if ($type === 'journal' && $journalId) {
    // 특정 매매/복기 데이터를 가져오는 로직
    $query = "SELECT jf.id, jf.code, s.name, jf.journal_date, jf.type, jf.status, UNCOMPRESS(jf.comment) AS comment FROM journal_feature jf JOIN stock s ON jf.code = s.code WHERE jf.id = $journalId AND s.last_yn = 'Y'";
    $result = $mysqli->query($query);

    if ($result && $result->num_rows > 0) {
        $response = $result->fetch_assoc();
        // JSON 형식으로 데이터 반환
        echo json_encode($response);
    } else {
        http_response_code(400); // 잘못된 요청
        echo json_encode(['message' => 'Invalid request']);
    }
}