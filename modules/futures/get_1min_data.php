<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$date     = $_GET['date']     ?? '';        // 'YYYY-MM-DD'
$interval = $_GET['interval'] ?? '1m';      // '1m' | '5m' | '15m' | '60m'
$limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 40;
if ($limit <= 0 || $limit > 500) $limit = 40;

// ── 분봉별 테이블 매핑
switch ($interval) {
  case '60m': $table = 'futures_60min'; break;
  case '15m': $table = 'futures_15min'; break;
  case '5m' : $table = 'futures_5min';  break;
  case '1m':
  default   : $table = 'futures_1min';  break;
}

// 1) 5분봉 초반 4개(20분) + 12개(60분) 기준 시가/고가/저가 계산 (기존 유지)
$sessionOpen   = null;
$sessionHigh20  = null;
$sessionLow20   = null;
$sessionHigh60 = null;
$sessionLow60  = null;

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

  $highs20 = [];
  $lows20  = [];
  $highs60 = [];
  $lows60  = [];

  foreach ($rowsLv as $idx => $r) {
    $h = (float)$r['high'];
    $l = (float)$r['low'];

    $highs60[] = $h;
    $lows60[]  = $l;

    if ($idx < 4) {
      $highs20[] = $h;
      $lows20[]  = $l;
    }
  }

  if ($highs20) $sessionHigh20 = max($highs20);
  if ($lows20)  $sessionLow20  = min($lows20);
  if ($highs60) $sessionHigh60 = max($highs60);
  if ($lows60)  $sessionLow60  = min($lows60);
}

// 2) 데이터 조회: "오늘 시작부터" 우선 채우고, 남으면 전일~5거래일 전에서 채우기
$rows = [];

if ($interval === '1m') {
  // ── 1m: (기존 유지) 당일 N봉만
  $sql = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           datetime, open, high, low, close, volume, sma_5, sma_20, sma_120
    FROM {$table}
    WHERE date = ?
    ORDER BY datetime ASC
    LIMIT ?
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('si', $date, $limit);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res->fetch_all(MYSQLI_ASSOC);

} else {
  // ── 2-1) 오늘 데이터(시작부터 ASC)로 limit 먼저 채우기
  $sqlToday = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           datetime, open, high, low, close, volume, sma_5, sma_20, sma_120
    FROM {$table}
    WHERE date = ?
    ORDER BY datetime ASC
    LIMIT ?
  ";
  $stmtToday = $mysqli->prepare($sqlToday);
  $stmtToday->bind_param('si', $date, $limit);
  $stmtToday->execute();
  $resToday = $stmtToday->get_result();
  $todayRows = $resToday->fetch_all(MYSQLI_ASSOC);

  $todayCount = count($todayRows);

  // 오늘 데이터가 limit 이상이면(=오늘 시작부터 limit개 확보), 그대로 끝
  if ($todayCount >= $limit) {
    $rows = $todayRows;
  } else {
    // ── 2-2) 남은 개수만큼 전일~최대 5거래일 전에서 "최근 봉"을 가져와 앞에 붙이기
    $remain = $limit - $todayCount;

    // 직전 5거래일 날짜 구하기 (calendar에 거래일만 있다고 가정)
    $sqlPrev5 = "SELECT date FROM calendar WHERE date < ? ORDER BY date DESC LIMIT 5";
    $stmtPrev5 = $mysqli->prepare($sqlPrev5);
    $stmtPrev5->bind_param('s', $date);
    $stmtPrev5->execute();
    $resPrev5 = $stmtPrev5->get_result();

    $prevDates = [];
    while ($r = $resPrev5->fetch_assoc()) {
      $prevDates[] = $r['date'];
    }

    // 5거래일 전 날짜(없으면 fallback: 당일)
    $fromDate = $date;
    if (count($prevDates) > 0) {
      $fromDate = end($prevDates); // 가장 오래된 날짜(=최대 5거래일 전)
    }

    // 전일~5거래일 전 범위에서 "오늘 직전" 최근 봉들
    // (DESC로 뽑은 뒤, 최종은 ASC가 되어야 하므로 나중에 reverse)
    $sqlPrev = "
      SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
             datetime, open, high, low, close, volume, sma_5, sma_20, sma_120
      FROM {$table}
      WHERE date >= ?
        AND date < ?
      ORDER BY datetime DESC
      LIMIT ?
    ";
    $stmtPrev = $mysqli->prepare($sqlPrev);
    $stmtPrev->bind_param('ssi', $fromDate, $date, $remain);
    $stmtPrev->execute();
    $resPrev = $stmtPrev->get_result();
    $prevRowsDesc = $resPrev->fetch_all(MYSQLI_ASSOC);

    // prevRowsDesc는 DESC이므로 ASC로 뒤집어서 "앞쪽에" 붙임
    $prevRowsAsc = array_reverse($prevRowsDesc);

    // 최종: (이전봉들) + (오늘 시작부터)
    $rows = array_merge($prevRowsAsc, $todayRows);
  }
}

// 3) 공통 필드 주입(기존 유지)
$data = [];
foreach ($rows as $row) {
  $row['session_open']   = $sessionOpen;
  $row['session_high20'] = $sessionHigh20;
  $row['session_low20']  = $sessionLow20;
  $row['session_high60'] = $sessionHigh60;
  $row['session_low60']  = $sessionLow60;
  $data[] = $row;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
