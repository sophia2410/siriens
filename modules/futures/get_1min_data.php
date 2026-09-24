<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$date     = $_GET['date']     ?? '';        // 'YYYY-MM-DD'
$interval = $_GET['interval'] ?? '1m';      // '1m' | '5m' | '15m' | '60m'
$limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 40;
$allDay   = isset($_GET['all_day']) && $_GET['all_day'] === '1';
if ($limit <= 0 || $limit > 500) $limit = 40;

// all_day=1 이면 interval과 관계없이 해당 날짜 전체를 반환한다.
// 기존 limit 기반 호출은 그대로 유지하므로 다른 화면에는 영향이 없다.

// ── 분봉별 테이블 매핑
switch ($interval) {
  case '60m': $table = 'futures_60min'; break;
  case '15m': $table = 'futures_15min'; break;
  case '5m' : $table = 'futures_5min';  break;
  case '1m':
  default   : $table = 'futures_1min';  break;
}

// 1) 5분봉 초반 6개(30분) + 12개(60분) 기준 시가/고가/저가 계산 (기존 유지)
$sessionOpen   = null;
$sessionHigh30  = null;
$sessionLow30   = null;
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

  $highs30 = [];
  $lows30  = [];
  $highs60 = [];
  $lows60  = [];

  foreach ($rowsLv as $idx => $r) {
    $h = (float)$r['high'];
    $l = (float)$r['low'];

    $highs60[] = $h;
    $lows60[]  = $l;

    if ($idx < 6) {
      $highs30[] = $h;
      $lows30[]  = $l;
    }
  }

  if ($highs30) $sessionHigh30 = max($highs30);
  if ($lows30)  $sessionLow30  = min($lows30);
  if ($highs60) $sessionHigh60 = max($highs60);
  if ($lows60)  $sessionLow60  = min($lows60);
}

/*
// =====================================================
// 임시 테스트
// session_high30 / session_low30에
// 09:30~09:34를 구성하는 09:30 5분봉 고·저점 저장
// =====================================================

$sessionOpen  = null;

$sessionHigh30 = null;
$sessionLow30  = null;

$sessionHigh60 = null;
$sessionLow60  = null;


// 당일 첫 12개 5분봉 조회
// - 첫 봉 시가 유지
// - 60분 고저 유지
// - 09:30 봉을 30분 기준 고저에 임시 저장
$sqlLv = "
  SELECT datetime, open, high, low
  FROM futures_5min
  WHERE date = ?
  ORDER BY datetime ASC
  LIMIT 12
";

$stmtLv = $mysqli->prepare($sqlLv);
$stmtLv->bind_param('s', $date);
$stmtLv->execute();

$resLv  = $stmtLv->get_result();
$rowsLv = $resLv->fetch_all(MYSQLI_ASSOC);
$stmtLv->close();

if ($rowsLv) {
  // 기존 장 시작 시가 유지
  $sessionOpen = (float)$rowsLv[0]['open'];

  $highs60 = [];
  $lows60  = [];

  foreach ($rowsLv as $r) {
    $high = (float)$r['high'];
    $low  = (float)$r['low'];

    // 기존 첫 12개 봉의 고저 유지
    $highs60[] = $high;
    $lows60[]  = $low;

    // datetime 예: 2026-01-05 09:30:00
    $barTime = substr($r['datetime'], 11, 8);

    // 09:30 5분봉은 09:30~09:34 구간
    if ($barTime === '09:30:00') {
      $sessionHigh30 = $high;
      $sessionLow30  = $low;
    }
  }

  if ($highs60) {
    $sessionHigh60 = max($highs60);
  }

  if ($lows60) {
    $sessionLow60 = min($lows60);
  }
}

*/

// 2) 데이터 조회: "오늘 시작부터" 우선 채우고, 남으면 전일~5거래일 전에서 채우기
$rows = [];

function selectColsByTable($table){
  $cols = "datetime, open, high, low, close, volume, sma_5, sma_20, sma_120, vwap_session";
  return $cols;
}

if ($allDay) {
  // ── 전체일 조회
  // 1m / 5m / 15m / 60m 모두 동일한 방식으로 당일 전체 반환.
  // LIMIT을 사용하지 않으므로 1분봉도 09:24 등에서 잘리지 않는다.
  $cols = selectColsByTable($table);

  $sqlAllDay = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           {$cols}
    FROM {$table}
    WHERE date = ?
    ORDER BY datetime ASC
  ";

  $stmtAllDay = $mysqli->prepare($sqlAllDay);
  $stmtAllDay->bind_param('s', $date);
  $stmtAllDay->execute();
  $resAllDay = $stmtAllDay->get_result();
  $rows = $resAllDay->fetch_all(MYSQLI_ASSOC);
  $stmtAllDay->close();

} elseif ($interval === '1m') {
  // ── 1m: 기존 동작 유지 — 당일 N봉만
  $cols = selectColsByTable($table);

  $sql = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           {$cols}
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
  $stmt->close();

} else {
  // ── 기존 동작 유지:
  // 오늘 데이터를 limit까지 먼저 채우고, 부족하면 과거 거래일 봉을 앞에 붙인다.
  $cols = selectColsByTable($table);

  $sqlToday = "
    SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
           {$cols}
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
  $stmtToday->close();

  $todayCount = count($todayRows);

  if ($todayCount >= $limit) {
    $rows = $todayRows;

  } else {
    $remain = $limit - $todayCount;

    // 직전 5거래일 날짜 구하기
    $sqlPrev5 = "SELECT date FROM calendar WHERE date < ? ORDER BY date DESC LIMIT 5";
    $stmtPrev5 = $mysqli->prepare($sqlPrev5);
    $stmtPrev5->bind_param('s', $date);
    $stmtPrev5->execute();
    $resPrev5 = $stmtPrev5->get_result();

    $prevDates = [];
    while ($r = $resPrev5->fetch_assoc()) {
      $prevDates[] = $r['date'];
    }
    $stmtPrev5->close();

    $fromDate = $date;
    if (count($prevDates) > 0) {
      $fromDate = end($prevDates);
    }

    $sqlPrev = "
      SELECT UNIX_TIMESTAMP(datetime)*1000 AS ts,
             {$cols}
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
    $stmtPrev->close();

    $prevRowsAsc = array_reverse($prevRowsDesc);
    $rows = array_merge($prevRowsAsc, $todayRows);
  }
}

// 3) 공통 필드 주입(기존 유지)
$data = [];
foreach ($rows as $row) {
  $row['session_open']   = $sessionOpen;
  $row['session_high30'] = $sessionHigh30;
  $row['session_low30']  = $sessionLow30;
  $row['session_high60'] = $sessionHigh60;
  $row['session_low60']  = $sessionLow60;
  $data[] = $row;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
