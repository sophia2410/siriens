<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// 받은 데이터 처리
$date = $_POST['date'] ?? date('Ymd');
$type = $_POST['type'];

// 결과를 저장할 변수 초기화
$output = '';

if ($type == 'market') {
    // Market Data Fetching Logic
    $query = "SELECT * FROM market_report WHERE report_date = '$date'";
    $result = $mysqli->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // HTML 구조에 맞추어 데이터 출력
            $output .= "<div><strong>{$row['report_date']}</strong>: {$row['data']}</div>";
        }
    } else {
        $output = "<div>No market data found for this date.</div>";
    }
} elseif ($type == 'theme') {
    // Theme Data Fetching Logic
    $query = "SELECT * FROM theme_data WHERE report_date = '$date' ORDER BY popularity DESC LIMIT 5";
    $result = $mysqli->query($query);
    if ($result->num_rows > 0) {
        $output .= "<ul>";
        while ($row = $result->fetch_assoc()) {
            $output .= "<li>{$row['theme_name']} - {$row['description']}</li>";
        }
        $output .= "</ul>";
    } else {
        $output = "<div>No theme data found for this date.</div>";
    }
}

// 결과 반환
echo $output;
?>
