<?php
// 서버 환경에 따른 데이터베이스 설정
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // 로컬호스트 환경
    $dbHost = 'siriens.mycafe24.com';
} else {
    // 호스팅 서버 환경
    $dbHost = 'localhost';
}
$dbUser = 'siriens';
$dbPassword = 'hosting1004!';
$dbName = 'siriens';

// 데이터베이스 연결
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
$mysqli->set_charset('utf8mb4');

// 연결 체크
if ($mysqli->connect_error) {
    die('Connect Error: ' . $mysqli->connect_error);
} 

// Log SQL query and parameters to a file
function Database_logQuery($query, $params) {
    $logfile = 'E:/Project/202410/www/query_log.txt';

    // Ensure that $params is an array; if not, convert it to an empty array
    if (!is_array($params)) {
        $params = [];
    }

    // Handle the case where $params is an empty array or contains non-scalar values
    $formattedParams = array_map(function($param) {
        if (is_scalar($param)) {
            return (string)$param;
        } else {
            return json_encode($param); // Convert arrays/objects to JSON strings
        }
    }, $params);

    // Replace placeholders with actual values
    $queryWithValues = $query;
    foreach ($formattedParams as $param) {
        $queryWithValues = preg_replace('/\?/', "'" . $param . "'", $queryWithValues, 1);
    }

    // Prepare the log message
    $logMessage = date('Y-m-d H:i:s') . " | Query: $queryWithValues | Params: " . implode(', ', $formattedParams) . "\n";

    // Write the log message to the file
    file_put_contents($logfile, $logMessage, FILE_APPEND);
}
?>