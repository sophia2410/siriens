<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$keywords = $_GET['keywords'] ?? '';
$excludeDate = $_GET['exclude_date'] ?? null; // Get the exclude_date parameter

// Split the keywords and remove the # symbol
$keywordsArray = array_map(function($keyword) {
    return str_replace('#', '', trim($keyword));
}, explode(' ', $keywords));

$placeholders = implode(',', array_fill(0, count($keywordsArray), '?'));
$types = str_repeat('s', count($keywordsArray));

// Prepare the query to find group IDs for the given keywords
$query = "
    SELECT DISTINCT kgm.group_id, kgm.group_name
    FROM keyword_master km
    INNER JOIN keyword_groups kg ON km.keyword_id = kg.keyword_id
    INNER JOIN keyword_groups_master kgm ON kg.group_id = kgm.group_id
    WHERE km.keyword IN ($placeholders)
";

// Log the query and parameters
logQuery($query, $keywordsArray);

$stmt = $mysqli->prepare($query);
$stmt->bind_param($types, ...$keywordsArray);
$stmt->execute();
$groupResult = $stmt->get_result();

$groupIds = [];
while ($row = $groupResult->fetch_assoc()) {
    $groupIds[] = $row['group_id'];
}

// If no group IDs are found, exit
if (empty($groupIds)) {
    echo '<p>No history found for the provided keywords.</p>';
    exit;
}

// Prepare placeholders and types for the next query
$groupPlaceholders = implode(',', array_fill(0, count($groupIds), '?'));
$groupTypes = str_repeat('i', count($groupIds));
$groupTypes.= 's';
$groupparams = array_merge($groupIds, [$excludeDate]);

// Query to fetch market issues and stocks related to these groups
$query = "
    SELECT 
        kgm.group_name, 
        mis.code, 
        mis.name, 
        mis.date, 
        mis.stock_comment
    FROM 
        keyword_groups_master kgm
    INNER JOIN 
        market_issues mi ON kgm.group_id = mi.keyword_group_id
    INNER JOIN 
        market_issue_stocks mis ON mis.issue_id = mi.issue_id
    WHERE 
        kgm.group_id IN ($groupPlaceholders)
        AND mis.date = (
            SELECT MAX(mis_sub.date)
            FROM market_issue_stocks mis_sub
            INNER JOIN market_issues mi_sub ON mis_sub.issue_id = mi_sub.issue_id
            WHERE mi_sub.keyword_group_id = kgm.group_id
            AND mi_sub.date != ?
        )
    ORDER BY 
        kgm.group_name, mis.date DESC
";

// Log the query and parameters
logQuery($query, $groupPlaceholders);

$stmt = $mysqli->prepare($query);
$stmt->bind_param($groupTypes, ...$groupparams);
$stmt->execute();
$result = $stmt->get_result();

// Generate HTML output
$output = '';

$currentIssue = null;
while ($row = $result->fetch_assoc()) {
    if ($currentIssue !== $row['group_name']) {
        if ($currentIssue !== null) {
            $output .= '</tbody></table>';
        }

        $output .= '<h5>' . htmlspecialchars($row['group_name']) . '</h5>';
        $output .= '<table><thead><tr><th>종목명</th><th>코드</th><th>일자</th></tr></thead><tbody>';
        $currentIssue = $row['group_name'];
    }  // Closing brace added here

    $comment = htmlspecialchars($row['stock_comment']);
    $nameWithTooltip = '<span title="' . $comment . '">' . htmlspecialchars($row['name']) . '</span>';

    $output .= '<tr>';
    $output .= '<td>' . $nameWithTooltip . '</td>';
    $output .= '<td>' . htmlspecialchars($row['code']) . '</td>';
    $output .= '<td>' . htmlspecialchars($row['date']) . '</td>';
    $output .= '</tr>';
}

if ($currentIssue !== null) {
    $output .= '</tbody></table>';
}

echo $output ?: '<p>해당 키워드의 과거 이력이 없습니다.</p>';

$stmt->close();
$mysqli->close();
?>
