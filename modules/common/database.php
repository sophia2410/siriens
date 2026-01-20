<?php
// modules/common/database.php

// host에서 포트 제거
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = strtolower(preg_replace('/:\d+$/', '', $host));
$isLocal = in_array($host, ['localhost','127.0.0.1','::1'], true);

// 서버 환경에 따른 데이터베이스 설정
$dbHost = $isLocal ? 'siriens.mycafe24.com' : 'localhost';

$dbUser = 'siriens';
$dbPassword = 'mariadb1004!';
$dbName = 'siriens';

// mysqli 예외(throw)로 통일
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ✅ connect timeout 적용 (new mysqli 대신)
$mysqli = mysqli_init();
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);  // 5초 내 연결 실패
$mysqli->real_connect($dbHost, $dbUser, $dbPassword, $dbName);
$mysqli->set_charset('utf8mb4');

// Log SQL query and parameters to a file (있어도 되지만, API에서 호출하지 않는 게 안전)
function Database_logQuery($query, $params) {
  $logfile = 'E:/Project/202410/www/query_log.txt';
  if (!is_array($params)) $params = [];
  $formattedParams = array_map(function($param) {
    if (is_scalar($param)) return (string)$param;
    return json_encode($param);
  }, $params);

  $queryWithValues = $query;
  foreach ($formattedParams as $param) {
    $queryWithValues = preg_replace('/\?/', "'" . $param . "'", $queryWithValues, 1);
  }

  $logMessage = date('Y-m-d H:i:s') . " | Query: $queryWithValues | Params: " . implode(', ', $formattedParams) . "\n";
  @file_put_contents($logfile, $logMessage, FILE_APPEND);
}

$naver_client_id = "ctlDiVwoaQ1H3KrkPvSZ";
$naver_client_secret = "K59VBs2cOI";
