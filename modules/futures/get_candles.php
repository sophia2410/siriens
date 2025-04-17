<?php
// get_candles.php — AJAX 캔들 데이터 (Highcharts 친화적)
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php"); // $mysqli 제공

$date = $_GET['date'] ?? '';
$tf   = $_GET['tf']   ?? '1m';
if (!$date) {
  header('Content-Type: application/json');
  echo '[]';
  exit;
}

function out(array $rows): void {
  header('Content-Type: application/json');
  echo json_encode($rows);
  exit;
}

/******************** 1. 일봉 ***************************/
if ($tf === '1day') {
  $sql  = "SELECT UNIX_TIMESTAMP(date)*1000   AS ts,
                  CAST(open  AS DECIMAL(10,2)) AS o,
                  CAST(high  AS DECIMAL(10,2)) AS h,
                  CAST(low   AS DECIMAL(10,2)) AS l,
                  CAST(close AS DECIMAL(10,2)) AS c
           FROM   futures_1day
           WHERE  date BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?
           ORDER  BY date";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('ss', $date, $date);
  $stmt->execute();
  $res  = $stmt->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) {
    // 숫자 배열 [ts,o,h,l,c]
    $rows[] = [
      (int)   $r['ts'],
      (float) $r['o'],
      (float) $r['h'],
      (float) $r['l'],
      (float) $r['c']
    ];
  }
  out($rows);
}

/******************** 2. 분봉 (1•5•15) ******************/
$unit = ($tf === '15m') ? 15 : (($tf === '5m') ? 5 : 1);

$sql  = "SELECT datetime, open, high, low, close
         FROM   futures_1min
         WHERE  date = ?
         ORDER  BY datetime";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $date);
$stmt->execute();
$result = $stmt->get_result();

$data     = [];
$bucket   = [];
$curKey   = null;

while ($row = $result->fetch_assoc()) {
  $ts  = strtotime($row['datetime']);
  $min = (int) date('i', $ts);
  $hr  = (int) date('H', $ts);
  $day = date('Y-m-d', $ts);

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

  $bucketKey = sprintf('%s-%02d-%02d', $day, $hr, floor($min / $unit));

  if ($curKey === null || $bucketKey !== $curKey) {
    if ($bucket) $data[] = $bucket;           // 이전 버킷 push
    $curKey = $bucketKey;

    $bucketTime = mktime(
      $hr,
      floor($min / $unit) * $unit,
      0,
      (int) date('n', $ts),
      (int) date('j', $ts),
      (int) date('Y', $ts)
    );

    $bucket = [
      $bucketTime * 1000,             // ts
      (float) $row['open'],           // o
      (float) $row['high'],           // h
      (float) $row['low'],            // l
      (float) $row['close']           // c (update later)
    ];
  } else {
    // update OHLC
    $bucket[2] = max($bucket[2], (float)$row['high']);
    $bucket[3] = min($bucket[3], (float)$row['low']);
    $bucket[4] = (float)$row['close'];
  }
}
if ($bucket) $data[] = $bucket;

out($data);
