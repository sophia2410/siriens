<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

// SQL 실행
$query = "SELECT market_overview FROM market_report WHERE market_overview LIKE '%<table%'";
$result = $mysqli->query($query);

while ($row = $result->fetch_assoc()) {
    $html = $row['market_overview'];

    // <table> 태그 포함된 내용만 추출
    preg_match_all('/<table.*?>.*?<\/table>/is', $html, $matches);

    // 테이블만 출력
    if (!empty($matches[0])) {
        foreach ($matches[0] as $table) {
            echo $table . "<br><br>";
        }
    } else {
        echo "테이블 없음";
    }
}

$mysqli->close();
?>
