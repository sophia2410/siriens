<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$type = $_GET['type'] ?? '';
$query = $_GET['q'] ?? '';
$results = [];

if ($type === 'keywords') {
    // Fetch keyword groups
    if ($stmt = $mysqli->prepare("SELECT group_name FROM keyword_groups_master WHERE group_name LIKE CONCAT('%', ?, '%')")) {
        $stmt->bind_param('s', $query);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row['group_name'];
        }
        $stmt->close();
    }
} elseif ($type === 'theme_sector') {
    // Fetch theme and sector based on the exact keyword group
    if ($stmt = $mysqli->prepare("
        SELECT DISTINCT theme, sector 
        FROM market_issues mi
        JOIN keyword_groups_master kgm ON mi.keyword_group_id = kgm.group_id
        WHERE kgm.group_name = ?
    ")) {
        $stmt->bind_param('s', $query);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = ['theme' => $row['theme'], 'sector' => $row['sector']];
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
                SELECT stock_comment FROM market_issue_stocks WHERE code = ? AND stock_comment != ''
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
} elseif ($type === 'stocks') {
    // Fetch stock codes and names
    if ($query !== '') {
        $stmt = $mysqli->prepare("SELECT code, name FROM stock WHERE name LIKE CONCAT(?, '%') OR code LIKE CONCAT('%', ?, '%')");
        $searchQuery = "%$query%";
        $stmt->bind_param('ss', $searchQuery, $searchQuery);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }

        $stmt->close();
    }
}

echo json_encode($results);
?>
