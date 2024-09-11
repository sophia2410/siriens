<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php");

$keywords = $_GET['keywords'] ?? '';
$excludeDate = $_GET['exclude_date'] ? str_replace('-', '', $_GET['exclude_date']) :  null; // Get the exclude_date parameter

// Ensure no output other than JSON when needed
header('Content-Type: application/json');

// Split the keywords and remove the # symbol
$keywordsArray = array_map(function($keyword) {
    return str_replace('#', '', trim($keyword));
}, explode(' ', $keywords));

$placeholders = implode(',', array_fill(0, count($keywordsArray), '?'));
$types = str_repeat('s', count($keywordsArray));

// Prepare the query to find group IDs for the given keywords
$query = "
    SELECT DISTINCT kg.group_id, kg.group_name
    FROM keyword k
    INNER JOIN keyword_group_mappings kgm ON k.keyword_id = kgm.keyword_id
    INNER JOIN keyword_groups kg ON kgm.group_id = kg.group_id
    WHERE k.keyword IN ($placeholders)
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param($types, ...$keywordsArray);
$stmt->execute();
$groupResult = $stmt->get_result();

$groupIds = [];
while ($row = $groupResult->fetch_assoc()) {
    $groupIds[] = $row['group_id'];
}

// If no group IDs are found, return an empty JSON array
if (empty($groupIds)) {
    echo json_encode(['html' => '<p>No history found for the provided keywords.</p>', 'data' => []]);
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
        kg.group_name, 
        mis.code, 
        mis.name, 
        mis.date, 
        mis.stock_comment
    FROM 
        keyword_groups kg
    INNER JOIN 
        market_issues mi ON kg.group_id = mi.keyword_group_id
    INNER JOIN 
        market_issue_stocks mis ON mis.issue_id = mi.issue_id
    WHERE 
        kg.group_id IN ($groupPlaceholders)
        AND mis.date = (
            SELECT MAX(mis_sub.date)
            FROM market_issue_stocks mis_sub
            INNER JOIN market_issues mi_sub ON mis_sub.issue_id = mi_sub.issue_id
            WHERE mi_sub.keyword_group_id = kg.group_id
            AND mi_sub.date != ?
        )
    ORDER BY 
        kg.group_name, mis.date DESC
";

// Log the query and parameters
// logQuery($query, $groupparams);

$stmt = $mysqli->prepare($query);
$stmt->bind_param($groupTypes, ...$groupparams);
$stmt->execute();
$result = $stmt->get_result();


// Initialize variables
$output = '';
$currentIssue = null;

// Process each row in the result
while ($row = $result->fetch_assoc()) {
    // Generate HTML for each group and its stocks
    if ($currentIssue !== $row['group_name']) {
        if ($currentIssue !== null) {
            $output .= '</tbody></table>';
        }

        $output .= '<h5>' . htmlspecialchars($row['group_name']) . '</h5>';
        $output .= '<table><thead><tr><th>종목명</th><th>코드</th><th>일자</th></tr></thead><tbody>';
        $currentIssue = $row['group_name'];
    }

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


// Query to fetch the most recent theme and sector for the identified exact group ID
$themeQuery = "
    SELECT mi.theme
    FROM market_issues mi
    JOIN keyword_groups kg
    ON mi.keyword_group_id = kg.group_id
    WHERE kg.group_name = ?
    ORDER BY mi.date DESC
    LIMIT 1
";

$themeStmt = $mysqli->prepare($themeQuery);
$themeStmt->bind_param('s', $keywords);
$themeStmt->execute();
$themeResult = $themeStmt->get_result();

$data = [];

if ($themeRow = $themeResult->fetch_assoc()) {
    $data = [
        'theme' => $themeRow['theme']
    ];
}

// Return both HTML and data for JSON response
echo json_encode(['html' => $output, 'data' => $data]);

$stmt->close();
$mysqli->close();
?>
