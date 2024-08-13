<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$q = $_GET['q'] ?? '';
$results = [];

if ($q !== '') {
    // LIKE 쿼리를 사용하여 부분 문자열 검색 수행
    $stmt = $mysqli->prepare("SELECT code, name FROM stock WHERE name LIKE CONCAT('%', ?, '%') OR code LIKE CONCAT('%', ?, '%')");
    $searchQuery = "%$q%";
    $stmt->bind_param('ss', $searchQuery, $searchQuery);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }

    $stmt->close();
}

echo json_encode($results);
?>
