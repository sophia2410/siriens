<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

$issueId = $_GET['issue_id'] ?? null;
$source = $_GET['source'] ?? 'market_issues'; // 기본값: market_issues

if ($issueId && in_array($source, ['market_issues', 'signals'])) {
    if ($source === 'market_issues') {
        $stmt = $mysqli->prepare("
            SELECT mi.issue_id, mi.date, mi.issue_title, mi.issue_link, mi.issue_content, mi.issue_comment,
                   GROUP_CONCAT(CONCAT('#', k.keyword) SEPARATOR ' ') AS keywords,
                   NULL AS code, NULL AS name, 'market_issues' AS source
            FROM market_issues mi
            LEFT JOIN keyword_issue_mappings kim ON mi.issue_id = kim.issue_id
            LEFT JOIN keyword k ON kim.keyword_id = k.keyword_id
            WHERE mi.issue_id = ?
            GROUP BY mi.issue_id
        ");
    } elseif ($source === 'signals') {
        $stmt = $mysqli->prepare("
            SELECT s.signal_id AS issue_id, s.news_date AS date, s.title AS issue_title, s.link AS issue_link, NULL AS issue_comment,
                   s.content AS issue_content, s.keyword AS keywords, s.code, s.name, 'signals' AS source
            FROM signals s
            WHERE s.signal_id = ?
        ");
    }

    $stmt->bind_param('i', $issueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $issue = $result->fetch_assoc();

    // JSON 형식으로 반환
    header('Content-Type: application/json');
    echo json_encode($issue);
} else {
    http_response_code(400); // 잘못된 요청
    echo json_encode(['error' => 'Invalid parameters']);
}
?>
