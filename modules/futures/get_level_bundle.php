<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false, 'message'=>'invalid date']);
  exit;
}

// 직전 거래일 1개
$prevDate = null;
$sqlPrev = "SELECT date FROM calendar WHERE date < ? ORDER BY date DESC LIMIT 1";
$stmtPrev = $mysqli->prepare($sqlPrev);
$stmtPrev->bind_param('s', $date);
$stmtPrev->execute();
$resPrev = $stmtPrev->get_result();
if ($r = $resPrev->fetch_assoc()) $prevDate = $r['date'];

// 5분봉 초반 6개로 시가/고저(4/12) 계산 (기존 로직 유지)
$sessionOpen   = null;
$sessionHigh30 = null;
$sessionLow30  = null;
$sessionMid30  = null;
$sessionHigh60 = null;
$sessionLow60  = null;
$sessionMid60  = null;

$sqlLv = "
  SELECT datetime, open, high, low
  FROM futures_5min
  WHERE DATE(datetime) = ?
  ORDER BY datetime ASC
  LIMIT 12
";

$stmtLv = $mysqli->prepare($sqlLv);
$stmtLv->bind_param('s', $date);
$stmtLv->execute();
$resLv  = $stmtLv->get_result();
$rowsLv = $resLv->fetch_all(MYSQLI_ASSOC);

if ($rowsLv) {
  $sessionOpen = (float)$rowsLv[0]['open'];
  $highs30 = []; $lows30 = []; $highs60 = []; $lows60 = [];
  foreach ($rowsLv as $idx => $r) {
    $h = (float)$r['high'];
    $l = (float)$r['low'];
    $highs60[] = $h; $lows60[] = $l;
    if ($idx < 6) { $highs30[] = $h; $lows30[] = $l; }
  }
  if ($highs30) $sessionHigh30 = max($highs30);
  if ($lows30)  $sessionLow30  = min($lows30);
  if ($sessionHigh30 !== null && $sessionLow30 !== null) {
    $sessionMid30 = ($sessionHigh30 + $sessionLow30) / 2.0;
  }

  if ($highs60) $sessionHigh60 = max($highs60);
  if ($lows60)  $sessionLow60  = min($lows60);
  if ($sessionHigh60 !== null && $sessionLow60 !== null) {
    $sessionMid60 = ($sessionHigh60 + $sessionLow60) / 2.0;
  }
}

function selectColsByTable($table){
  // 공통 컬럼
  $cols = "datetime, open, high, low, close, volume, sma_5, sma_20, sma_120, vwap_session";

  // date는 여기서 항상 포함(기존 로직 유지)
  $cols .= ", date";
  return $cols;
}

function getPrevTradingDates($mysqli, $date, $n) {
  $sql = "SELECT date FROM calendar WHERE date < ? ORDER BY date DESC LIMIT ?";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('si', $date, $n);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($r = $res->fetch_assoc()) $out[] = $r['date'];
  return $out; // 최신순(내림차순)
}

function fetchByDateIn($mysqli, $table, $dates) {
  if (!$dates) return [];

  $ph = implode(',', array_fill(0, count($dates), '?'));
  $types = str_repeat('s', count($dates));

  $cols = selectColsByTable($table);

  $sql = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           {$cols}
    FROM {$table}
    WHERE date IN ($ph)
    ORDER BY datetime ASC
  ";

  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param($types, ...$dates);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_all(MYSQLI_ASSOC);
}

function fetchRows($mysqli, $table, $whereSql, $types, $params) {
  $cols = selectColsByTable($table);

  $sql = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           {$cols}
    FROM {$table}
    {$whereSql}
    ORDER BY datetime ASC
  ";

  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_all(MYSQLI_ASSOC);
}

// 60m/15m: 전일+당일 (prevDate 없으면 당일만)
$LOOKBACK_60M = 5; // ✅ 최근 5거래일 + 당일 (필요하면 7로)
$prevDates60 = getPrevTradingDates($mysqli, $date, $LOOKBACK_60M);
$dates60 = array_reverse($prevDates60); // 오래된→최신
$dates60[] = $date;

$data60 = fetchByDateIn($mysqli, 'futures_60min', $dates60);

// 15m: 전일+당일 유지
$prevDates15 = getPrevTradingDates($mysqli, $date, 1);
$dates15 = [];
if (!empty($prevDates15)) $dates15[] = $prevDates15[0];
$dates15[] = $date;

$data15 = fetchByDateIn($mysqli, 'futures_15min', $dates15);

// 5m: 당일 전체
$data5  = fetchRows($mysqli, 'futures_5min', 'WHERE date = ?', 's', [$date]);

// 1m: 당일 ~ 15:45
$toNoon = $date . " 15:45:00";

$cols1 = selectColsByTable('futures_1min');

$sql1 = "
  SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
         {$cols1}
  FROM futures_1min
  WHERE date = ?
    AND datetime <= ?
  ORDER BY datetime ASC
";

$stmt1 = $mysqli->prepare($sql1);
$stmt1->bind_param('ss', $date, $toNoon);
$stmt1->execute();
$res1 = $stmt1->get_result();
$data1 = $res1->fetch_all(MYSQLI_ASSOC);

// 공통 메타 주입(원하면 JS에서 쓰게)
$meta = [
  'date' => $date,
  'prev_date' => $prevDate,
  'session_open'   => $sessionOpen,
  'session_high30' => $sessionHigh30,
  'session_low30'  => $sessionLow30,
  'session_mid30'  => $sessionMid30,
  'session_high60' => $sessionHigh60,
  'session_low60'  => $sessionLow60,
  'session_mid60'  => $sessionMid60,
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok' => true,
  'meta' => $meta,
  'data' => [
    '60m' => $data60,
    '15m' => $data15,
    '5m'  => $data5,
    '1m'  => $data1,
  ]
]);
