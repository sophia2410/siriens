<?php

mb_internal_encoding("UTF-8");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$mysqli->set_charset("utf8");

$query = "SELECT min(sort_theme) AS id, theme AS name FROM watchlist_sophia WHERE sector = '1 최근테마☆' GROUP BY theme ORDER BY sort_theme ";
$result = $mysqli->query($query);

$sectors = [];
while ($row = $result->fetch_assoc()) {
    $sectors[] = $row; // UTF-8 인코딩 설정
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($sectors, JSON_UNESCAPED_UNICODE);
?>