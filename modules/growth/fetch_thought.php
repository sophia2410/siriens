<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

// JSON 반환을 위한 헤더 설정
header('Content-Type: application/json');

if (isset($_GET['thought_id'])) {
    $thoughtId = $mysqli->real_escape_string($_GET['thought_id']);

    // 선택한 생각의 정보를 가져오는 쿼리
    $query = "
        SELECT t.id, t.category_cd, t.thought_text, t.create_date, c.nm AS category_name
        FROM thoughts t
        JOIN comm_cd c ON t.category_cd = c.cd
        WHERE t.id = ?
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $thoughtId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $thought = $result->fetch_assoc();
        // JSON 형식으로 데이터 반환
        echo json_encode($thought);
    } else {
        http_response_code(404); // 데이터를 찾을 수 없을 때
        echo json_encode(['message' => 'Thought not found']);
    }
} else {
    http_response_code(400); // 잘못된 요청
    echo json_encode(['message' => 'Invalid request']);
}
?>
