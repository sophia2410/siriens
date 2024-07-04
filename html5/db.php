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
?>
