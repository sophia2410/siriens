<?php

mb_internal_encoding("UTF-8");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$mysqli->set_charset("utf8");

// Determine the source based on the passed parameter
$source = isset($_GET['source']) ? $_GET['source'] : 'market_events';

if ($source === 'watchlist_sophia') {
    // Fetch themes from watchlist_sophia
    $query = "SELECT min(sort_theme) AS id, theme AS name FROM watchlist_sophia WHERE sector = '2 최근테마' GROUP BY theme ORDER BY sort_theme";
} else {
    // Default to fetching themes from market_events
    $query = "SELECT 
                mi.group_label AS name,
                MAX(mi.date) AS max_date,
                MAX(mi.hot_theme) AS hot_theme
            FROM 
                market_events mi
            JOIN 
                market_event_stocks mis ON mis.event_id = mi.event_id
            WHERE
                mi.date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
            GROUP BY 
                mi.group_label
            ORDER BY 
                hot_theme DESC,
                max_date DESC,
                name ASC";
}

$result = $mysqli->query($query);

$themes = [];
while ($row = $result->fetch_assoc()) {
    $themes[] = $row;
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($themes, JSON_UNESCAPED_UNICODE);
?>
