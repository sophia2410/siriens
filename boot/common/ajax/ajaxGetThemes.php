<?php

mb_internal_encoding("UTF-8");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$mysqli->set_charset("utf8");

// Determine the source based on the passed parameter
$source = isset($_GET['source']) ? $_GET['source'] : 'market_issues';

if ($source === 'watchlist_sophia') {
    // Fetch themes from watchlist_sophia
    $query = "SELECT min(sort_theme) AS id, theme AS name FROM watchlist_sophia WHERE sector = '1 최근테마☆' GROUP BY theme ORDER BY sort_theme";
} else {
    // Default to fetching themes from market_issues
    $query = "SELECT DISTINCT 
                CASE 
                    WHEN mi.theme IS NOT NULL AND mi.theme != '' THEN mi.theme
                    ELSE REPLACE(SUBSTRING_INDEX(kg.group_name, ' ', 1), '#', '')
                END AS name,
                MAX(mi.date) AS max_date,
                MAX(mi.hot_theme) AS hot_theme,
                CASE 
                    WHEN mi.theme IS NOT NULL AND mi.theme != '' THEN 'theme'
                    ELSE 'keyword'
                END AS type
            FROM 
                market_issues mi
            LEFT JOIN 
                keyword_groups kg ON mi.keyword_group_id = kg.group_id
            WHERE 
                (mi.theme IS NOT NULL AND mi.theme != '') OR (kg.group_name IS NOT NULL AND kg.group_name != '')
            GROUP BY 
                name, type
            ORDER BY 
                type DESC,
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
