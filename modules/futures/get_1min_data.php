<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$date = $_GET['date'] ?? '';
$interval = $_GET['interval'] ?? '1m';

// ✅ 분봉별 테이블 매핑
switch ($interval) {
  case '60m':
    $table = 'futures_60min';
    break;
  case '5m':
    $table = 'futures_5min';
    break;
  case '1m':
  default:
    $table = 'futures_1min';
    break;
}

// ✅ 조회 범위: 해당 일자에서 가장 빠른 시간부터 N개 봉만 가져오기
$sql = "
  SELECT datetime, open, high, low, close, volume, sma_5, sma_20
  FROM $table
  WHERE DATE(datetime) = ?
  ORDER BY datetime ASC
  LIMIT 40
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $date);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
