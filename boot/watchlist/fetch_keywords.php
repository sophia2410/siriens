<?php

mb_internal_encoding("UTF-8");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$mysqli->set_charset("utf8");

header('Content-Type: application/json; charset=UTF-8'); // JSON 형식으로 응답

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sector'])) {
    $sector = $mysqli->real_escape_string($_POST['sector']);
    $query = "SELECT DISTINCT keyword FROM market_sector WHERE sector = '$sector'";
    $query = "SELECT DISTINCT category FROM watchlist_sophia WHERE trim(theme) = '$sector' and sector in( '5 끼있는친구들1', '6 끼있는친구들2')";
    $result = $mysqli->query($query);

    if (!$result) {
        echo json_encode(["error" => $mysqli->error]);
        exit();
    }

    $keywords = [];
    while ($row = $result->fetch_assoc()) {
        $keywords[] = $row; // UTF-8 인코딩 설정
    }

    echo json_encode($keywords, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Invalid request"]);
}

$mysqli->close();
?>
