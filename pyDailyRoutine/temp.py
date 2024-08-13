<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// Determine the type of data to fetch based on the `type` parameter
$type = $_GET['type'] ?? '';
$query = $_GET['q'] ?? '';
$response = [];

switch ($type) {
    case 'stocks':
        // Fetch stocks based on a query
        if ($query !== '') {
            $stmt = $mysqli->prepare("SELECT code, name FROM stock WHERE name LIKE CONCAT('%', ?, '%') OR code LIKE CONCAT('%', ?, '%')");
            $stmt->bind_param('ss', $query, $query);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $response[] = $row;
            }

            $stmt->close();
        }
        break;

    case 'keywords':
        // Fetch keyword groups based on a query
        if ($query !== '') {
            $stmt = $mysqli->prepare("SELECT group_name FROM keyword_groups_master WHERE group_name LIKE CONCAT('%', ?, '%')");
            $stmt->bind_param('s', $query);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $response[] = $row['group_name'];
            }

            $stmt->close();
        }
        break;

    case 'stock_comments':
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
                $response[] = $row['stock_comment'];
            }

            $stmt->close();
        }
        break;

    case 'themes_sectors':
        // Fetch themes and sectors based on keywords
        if ($query !== '') {
            $keyword_ids = explode(',', $query); // Assuming $query is a comma-separated list of keyword IDs
            $types = str_repeat('i', count($keyword_ids)); // Prepare the binding types for the keyword IDs

            $stmt = $mysqli->prepare("
                SELECT DISTINCT theme, sector FROM market_issues
                WHERE keyword_group_id IN (
                    SELECT group_id FROM keyword_groups
                    WHERE keyword_id IN (" . implode(',', array_fill(0, count($keyword_ids), '?')) . ")
                    GROUP BY group_id
                    HAVING COUNT(DISTINCT keyword_id) = ?
                )
            ");
            $stmt->bind_param($types . 'i', ...$keyword_ids, count($keyword_ids));
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $response[] = ['theme' => $row['theme'], 'sector' => $row['sector']];
            }

            $stmt->close();
        }
        break;

    default:
        $response['error'] = 'Invalid type specified';
        break;
}

echo json_encode($response);
?>
