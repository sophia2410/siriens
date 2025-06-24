<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php");

$type = $_GET['type'] ?? '';
$query = $_GET['q'] ?? '';
$results = [];

if ($type === 'stocks') {
    // Fetch stock codes and names
    if ($query !== '') {
        $stmt = $mysqli->prepare("
            SELECT s.code, s.name
            FROM stock s
            WHERE s.name LIKE CONCAT(?, '%') OR s.code LIKE CONCAT('%', ?, '%')
        ");
        // logQuery($stmt, [$searchQuery, $searchQuery]);
        $searchQuery = "%$query%";
        $stmt->bind_param('ss', $searchQuery, $searchQuery);
        $stmt->execute();
        $result = $stmt->get_result();
    
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    
        $stmt->close();
    }
} elseif ($type === 'keywords') {
    // Fetch keyword groups
    if ($stmt = $mysqli->prepare("SELECT group_name FROM keyword_groups WHERE group_name LIKE CONCAT('%', ?, '%')")) {
        $stmt->bind_param('s', $query);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row['group_name'];
        }
        $stmt->close();
    }
} elseif ($type === 'theme') {
    // Fetch theme based on the exact keyword group
    if ($stmt = $mysqli->prepare("
        SELECT DISTINCT theme
        FROM market_events me
        JOIN keyword_groups kg ON me.keyword_group_id = kg.group_id
        WHERE kg.group_name = ?
    ")) {
        $stmt->bind_param('s', $query);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = ['theme' => $row['theme']];
        }
        $stmt->close();
    }
} elseif ($type === 'stock_comments') {
    // Fetch stock comments for autocomplete
    $code = $_GET['code'] ?? '';
    if ($code !== '') {
        $stmt = $mysqli->prepare("
            SELECT stock_comment FROM (
                SELECT comment AS stock_comment FROM stock_comment WHERE code = ?
                UNION
                SELECT stock_comment FROM watchlist_sophia WHERE code = ? AND stock_comment != ''
                UNION
                SELECT stock_comment FROM market_event_stocks WHERE code = ? AND stock_comment != ''
            ) AS combined_comments
        ");
        $stmt->bind_param('sss', $code, $code, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row['stock_comment'];
        }
        $stmt->close();
    }
}

echo json_encode($results);
?>
