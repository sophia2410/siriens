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

// 60분봉 첫 봉(당일) 기준 레벨: High/Low/Mid
$lvlHigh = $lvlLow = $lvlMid = null;
$lvlSrcDt = null;

$sqlLvl = "SELECT datetime, high, low FROM futures_60min WHERE date = ? ORDER BY datetime ASC LIMIT 1";
$stmtLvl = $mysqli->prepare($sqlLvl);
$stmtLvl->bind_param('s', $date);
$stmtLvl->execute();
$resLvl = $stmtLvl->get_result();
if ($r = $resLvl->fetch_assoc()) {
  $lvlSrcDt = $r['datetime'];
  $lvlHigh = (float)$r['high'];
  $lvlLow  = (float)$r['low'];
  $lvlMid  = ($lvlHigh + $lvlLow) / 2.0;
}

// 5분봉 초반 12개로 시가/고저(4/12) 계산 (기존 로직 유지)
$sessionOpen   = null;
$sessionHigh4  = null;
$sessionLow4   = null;
$sessionHigh12 = null;
$sessionLow12  = null;

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
  $highs4  = []; $lows4 = []; $highs12 = []; $lows12 = [];
  foreach ($rowsLv as $idx => $r) {
    $h = (float)$r['high'];
    $l = (float)$r['low'];
    $highs12[] = $h; $lows12[] = $l;
    if ($idx < 4) { $highs4[] = $h; $lows4[] = $l; }
  }
  if ($highs4)  $sessionHigh4  = max($highs4);
  if ($lows4)   $sessionLow4   = min($lows4);
  if ($highs12) $sessionHigh12 = max($highs12);
  if ($lows12)  $sessionLow12  = min($lows12);
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
  $sql = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           datetime, open, high, low, close, volume, sma_5, sma_20, sma_120, date
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
  $sql = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           datetime, open, high, low, close, volume, sma_5, sma_20, sma_120, date
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

// 1m: 당일 ~12:00
$toNoon = $date . " 12:00:00";
$sql1 = "
  SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
         datetime, open, high, low, close, volume, sma_5, sma_20, sma_120, date
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
  'lvl_high' => $lvlHigh,
  'lvl_low'  => $lvlLow,
  'lvl_mid'  => $lvlMid,
  'lvl_src_datetime' => $lvlSrcDt,
  'session_open_5m'   => $sessionOpen,
  'session_high4_5m'  => $sessionHigh4,
  'session_low4_5m'   => $sessionLow4,
  'session_high12_5m' => $sessionHigh12,
  'session_low12_5m'  => $sessionLow12,
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
