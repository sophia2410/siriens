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
} elseif ($type === 'stocks') {
    // Fetch stocks
    if ($stmt = $mysqli->prepare("SELECT code, name FROM stock WHERE name LIKE CONCAT('%', ?, '%') OR code LIKE CONCAT('%', ?, '%')")) {
        $stmt->bind_param('ss', $query, $query);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
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
}

echo json_encode($results);
?>
