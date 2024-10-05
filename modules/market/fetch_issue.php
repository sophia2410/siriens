<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

$issueId = $_GET['issue_id'] ?? null;

if ($issueId) {
    $stmt = $mysqli->prepare("
        SELECT mi.issue_id, mi.date, mi.issue_title, mi.issue_link, mi.issue_content, 
            GROUP_CONCAT(CONCAT('#', k.keyword) SEPARATOR ' ') AS keywords
        FROM market_issues mi
        LEFT JOIN keyword_issue_mappings kim ON mi.issue_id = kim.issue_id
        LEFT JOIN keyword k ON kim.keyword_id = k.keyword_id
        WHERE mi.issue_id = ?
        GROUP BY mi.issue_id
    ");

    $stmt->bind_param('i', $issueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $issue = $result->fetch_assoc();

    // JSON 형식으로 결과 반환
    header('Content-Type: application/json');
    echo json_encode($issue);
}
?>
