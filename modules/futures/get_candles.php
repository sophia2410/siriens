<?php
// get_candles.php — AJAX 캔들 데이터 (Highcharts 친화적)
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php"; // $mysqli 제공

$date = $_GET['date'] ?? '';
$tf   = $_GET['tf']   ?? '1m';
if (!$date) {
  header('Content-Type: application/json');
  echo '[]';
  exit;
}

function out(array $rows): void {
  header('Content-Type: application/json');
  echo json_encode(['candles' => $rows]);
  exit;
}

/******************** 1. 일봉 ***************************/
if ($tf === '1day') {
  $sql  = "SELECT UNIX_TIMESTAMP(date)*1000 AS ts, open, high, low, close
           FROM futures_1day
           WHERE date BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?
           ORDER BY date";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('ss', $date, $date);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_NUM);
  out($rows);
}

/******************** 2. 분봉 (1•5•15) ******************/
$unit = ($tf === '15m') ? 15 : (($tf === '5m') ? 5 : 1);
$lookbackMin = 120 * $unit;

$startDT = date('Y-m-d 00:00:00', strtotime($date));
$startDT = date('Y-m-d H:i:s', strtotime($startDT) - $lookbackMin);
$endDT   = date('Y-m-d 23:59:59', strtotime($date));

$sql  = "SELECT datetime, open, high, low, close
         FROM futures_1min
         WHERE datetime BETWEEN ? AND ?
         ORDER BY datetime";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss', $startDT, $endDT);
$stmt->execute();
$result = $stmt->get_result();

$data   = [];
$bucket = [];
$curKey = null;

while ($row = $result->fetch_assoc()) {
  $ts  = strtotime($row['datetime']);

  if ($unit === 1) {
    $data[] = [
      $ts * 1000,
      (float) $row['open'],
      (float) $row['high'],
      (float) $row['low'],
      (float) $row['close']
    ];
    continue;
  }

  // 버킷 키
  $block = floor(date('i', $ts) / $unit);
  $bucketKey = date('Y-m-d-H', $ts) . '-' . $block;

  if ($curKey === null || $bucketKey !== $curKey) {
    if ($bucket) $data[] = $bucket;
    $curKey = $bucketKey;

    $bucketTime = mktime(
      (int)date('H', $ts),
      $block * $unit,
      0,
      (int)date('n', $ts),
      (int)date('j', $ts),
      (int)date('Y', $ts)
    );

    $bucket = [
      $bucketTime * 1000,
      (float) $row['open'],
      (float) $row['high'],
      (float) $row['low'],
      (float) $row['close']
    ];
  } else {
    $bucket[2] = max($bucket[2], (float)$row['high']); // high
    $bucket[3] = min($bucket[3], (float)$row['low']);  // low
    $bucket[4] = (float) $row['close'];                // close
  }
}
if ($bucket) $data[] = $bucket;

// 오늘 구간만 필터링
$todayStart = strtotime("$date 00:00:00") * 1000;
$out = array_values(array_filter($data, fn($r) => $r[0] >= $todayStart));

out($out);
