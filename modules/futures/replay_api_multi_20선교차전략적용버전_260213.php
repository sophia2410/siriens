<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
header('Content-Type: application/json; charset=utf-8');

// ✅ JSON 응답에 Notice/Warning HTML이 섞이면 프론트 JSON.parse가 깨짐
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$date = $_GET['date'] ?? '';
$start_time = $_GET['start'] ?? '08:45:00';
$end_time   = $_GET['end']   ?? '15:00:00';

$max_prev_1m  = isset($_GET['max_prev_1m'])  ? (int)$_GET['max_prev_1m']  : 500;
$max_prev_5m  = isset($_GET['max_prev_5m'])  ? (int)$_GET['max_prev_5m']  : 300;
$max_prev_15m = isset($_GET['max_prev_15m']) ? (int)$_GET['max_prev_15m'] : 200;
$max_prev_60m = isset($_GET['max_prev_60m']) ? (int)$_GET['max_prev_60m'] : 120;

$init_max_1m  = isset($_GET['init_max_1m'])  ? (int)$_GET['init_max_1m']  : 200;
$init_max_5m  = isset($_GET['init_max_5m'])  ? (int)$_GET['init_max_5m']  : 83;
$init_max_15m = isset($_GET['init_max_15m']) ? (int)$_GET['init_max_15m'] : 29;
$init_max_60m = isset($_GET['init_max_60m']) ? (int)$_GET['init_max_60m'] : 29;

$hi_lo_n = isset($_GET['hi_lo_n']) ? (int)$_GET['hi_lo_n'] : 20;
$hi_lo_n2 = isset($_GET['hi_lo_n2']) ? (int)$_GET['hi_lo_n2'] : 60;


if (!$date) { echo json_encode(['ok'=>false,'msg'=>'date required']); exit; }

function normTime($t, $fallback){
  $t = trim((string)$t);
  if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
  return $fallback;
}

$start_time = normTime($start_time, '08:45:00');
$end_time   = normTime($end_time,   '15:00:00');
$init_time  = normTime($_GET['init_time'] ?? $start_time, $start_time);

function getPrevTradingDateFromCal($mysqli, $date, $n = 1){
  $n = max(1, (int)$n);

  // date보다 이전의 거래일을 최신순으로 n개 뽑아서, n번째(=가장 오래된 것)를 start로 사용
  $sql = "
    SELECT date
    FROM calendar
    WHERE date < ?
    ORDER BY date DESC
    LIMIT ?
  ";

  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('si', $date, $n);
  $stmt->execute();

  $r = $stmt->get_result();
  $dates = [];
  while ($row = $r->fetch_assoc()) $dates[] = $row['date'];

  // DESC로 n개 가져왔으니, n번째 이전 거래일은 인덱스 n-1
  return $dates[$n-1] ?? null;
}

function fetchTail($mysqli, $table, $prevDate, $date, $limit){
  if (!$prevDate) return [];
  $sql = "
    SELECT datetime, open, high, low, close, volume, sma_5, sma_20, sma_120
    FROM {$table}
    WHERE date >= ?
    AND   date < ?
    ORDER BY datetime DESC
    LIMIT ?
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('ssi', $prevDate, $date, $limit);
  $stmt->execute();
  $r = $stmt->get_result();
  $rows = [];
  while ($row = $r->fetch_assoc()) $rows[] = $row;
  return array_reverse($rows);
}

function fetchRange($mysqli, $table, $date, $start_time, $end_time){
  $sql = "
    SELECT datetime, open, high, low, close, volume, sma_5, sma_20, sma_120
    FROM {$table}
    WHERE date = ?
      AND time >= ?
      AND time <= ?
    ORDER BY datetime ASC
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('sss', $date, $start_time, $end_time);
  $stmt->execute();
  $r = $stmt->get_result();
  $rows = [];
  while ($row = $r->fetch_assoc()) $rows[] = $row;
  return $rows;
}

function fetchRangeLT($mysqli, $table, $date, $start_time, $lt_time){
  $sql = "
    SELECT datetime, open, high, low, close, volume, sma_5, sma_20, sma_120
    FROM {$table}
    WHERE date = ?
      AND time >= ?
      AND time < ?
    ORDER BY datetime ASC
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('sss', $date, $start_time, $lt_time);
  $stmt->execute();
  $r = $stmt->get_result();
  $rows = [];
  while ($row = $r->fetch_assoc()) $rows[] = $row;
  return $rows;
}

function fetchRangeAfter($mysqli, $table, $date, $gt_time, $end_time){
  $sql = "
    SELECT datetime, open, high, low, close, volume, sma_5, sma_20, sma_120
    FROM {$table}
    WHERE date = ?
      AND time > ?
      AND time <= ?
    ORDER BY datetime ASC
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('sss', $date, $gt_time, $end_time);
  $stmt->execute();
  $r = $stmt->get_result();
  $rows = [];
  while ($row = $r->fetch_assoc()) $rows[] = $row;
  return $rows;
}

// ===== [BOXES] candle-20선 교차 확정 이벤트 계산 =====
function dtAddMinutesStr($dtStr, $min){
  $dt = new DateTime($dtStr);
  $dt->modify(($min >= 0 ? '+' : '') . (int)$min . ' minutes');
  return $dt->format('Y-m-d H:i:s');
}

function fetch5mRowsForBoxes($mysqli, $date, $start_time, $end_time){
  $sql = "
    SELECT datetime, open, high, low, close, sma_20
    FROM futures_5min
    WHERE date = ?
      AND time >= ?
      AND time <= ?
      AND sma_20 IS NOT NULL
    ORDER BY datetime ASC
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('sss', $date, $start_time, $end_time);
  $stmt->execute();
  $r = $stmt->get_result();
  $rows = [];
  while ($row = $r->fetch_assoc()) $rows[] = $row;
  return $rows;
}

// ✅ (조건1) 장시작봉 교차 포함: 당일 첫 봉(08:45)의 prev는 "당일 시작 직전 가장 최근 5분봉 1개"
function fetchPrev5mRowForBoxes($mysqli, $date, $start_time){
  $sql = "
    SELECT datetime, high, low, close, sma_20
    FROM futures_5min
    WHERE (date < ? OR (date = ? AND time < ?))
      AND sma_20 IS NOT NULL
    ORDER BY datetime DESC
    LIMIT 1
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('sss', $date, $date, $start_time);
  $stmt->execute();
  $r = $stmt->get_result();
  $row = $r->fetch_assoc();
  return $row ?: null;
}

function calcBoxes5mSma20Cross($mysqli, $date, $start_time, $end_time){
  $rows = fetch5mRowsForBoxes($mysqli, $date, $start_time, $end_time);
  if (!$rows || !count($rows)) return [];

  $prev = fetchPrev5mRowForBoxes($mysqli, $date, $start_time);
  $prev_rel = null;
  if ($prev){
    $prev_rel = (float)$prev['close'] - (float)$prev['sma_20'];
  }

  $boxes = [];
  $n = count($rows);

  for ($i=0; $i<$n; $i++){
    $r = $rows[$i];

    $sma  = (float)$r['sma_20'];
    $open = (float)$r['open'];
    $high = (float)$r['high'];
    $low  = (float)$r['low'];
    $close= (float)$r['close'];

    $rel = $close - $sma;

    if ($prev_rel === null){
      $prev_rel = $rel;
      continue;
    }

    // ----------------------------
    // (A) CROSS 판정(기존)
    // ----------------------------
    $dir = null;
    $kind = null; // 'CROSS' | 'REJECT'
    $base_is_event = 0;

    if ($prev_rel <= 0 && $rel > 0){
      $dir = 'UP';
      $kind = 'CROSS';
      // 기존 조건2(윅 기준)
      $up_wick   = $high - $sma;
      $down_wick = $sma - $low;
      $base_is_event = ($up_wick > $down_wick) ? 1 : 0;

    } else if ($prev_rel >= 0 && $rel < 0){
      $dir = 'DOWN';
      $kind = 'CROSS';
      // 기존 조건2(윅 기준)
      $down_wick = $sma - $low;
      $up_wick   = $high - $sma;
      $base_is_event = ($down_wick > $up_wick) ? 1 : 0;
    }

    // ----------------------------
    // (B) REJECT 판정(추가)
    //   - 같은 봉에서 CROSS가 잡히면 REJECT는 만들지 않음(중복 방지)
    // ----------------------------
    if (!$dir){
      $touch = ($low <= $sma && $high >= $sma);

      $rejectSupport = ($touch && $open > $sma && $close > $sma);
      $rejectResist  = ($touch && $open < $sma && $close < $sma);

      if ($rejectSupport){
        $dir = 'UP';
        $kind = 'REJECT';

        // ✅ 몸통 기준: 종가쪽이 더 큰 경우 해당 봉이 기준봉
        $up_body   = $close - $sma; // >0
        $down_body = $open  - $sma; // >0
        $base_is_event = ($up_body > $down_body) ? 1 : 0;

      } else if ($rejectResist){
        $dir = 'DOWN';
        $kind = 'REJECT';

        // ✅ 몸통 기준: 종가쪽이 더 큰 경우 해당 봉이 기준봉
        $down_body = $sma - $close; // >0
        $up_body   = $sma - $open;  // >0
        $base_is_event = ($down_body > $up_body) ? 1 : 0;
      }
    }

    // 이벤트가 아니면 다음 봉으로 기준봉 이동(기존과 동일)
    if ($dir){
      $event_dt = $r['datetime']; // cross_dt 역할(프론트 호환 위해 이름 유지 가능)
      $cross_dt = $event_dt;

      $baseRow = null;
      $base_dt = null;

      if ($base_is_event === 1){
        $baseRow = $r;
        $base_dt = $event_dt;
      } else {
        if ($i + 1 < $n){
          $baseRow = $rows[$i + 1];
          $base_dt = $baseRow['datetime'];
        }
      }

      if ($baseRow){
        $base_sma   = (float)$baseRow['sma_20'];
        $base_close = (float)$baseRow['close'];
        $base_rel   = $base_close - $base_sma;

        // 확정 조건(기존)
        $confirmed = ($dir === 'UP') ? ($base_rel > 0) : ($base_rel < 0);
        if ($confirmed){
          $confirm_dt = dtAddMinutesStr($base_dt, 5);
          $trigger_dt = dtAddMinutesStr($confirm_dt, -1);

          $boxes[] = [
            'seq' => 0,
            'dir' => $dir,

            // 기존 필드 유지
            'cross_dt' => $cross_dt,
            'base_dt' => $base_dt,
            'confirm_dt' => $confirm_dt,
            'trigger_dt' => $trigger_dt,
            'base_is_event' => (int)$base_is_event,

            'base_high' => (float)$baseRow['high'],
            'base_low'  => (float)$baseRow['low'],

            // (옵션/검증용)
            'base_close' => (float)$base_close,
            'base_sma20' => (float)$base_sma,
            'base_rel'   => (float)$base_rel,

            'cross_sma20' => (float)$sma,
            'cross_high'  => (float)$high,
            'cross_low'   => (float)$low,

            // ✅ 추가(프론트는 무시해도 됨)
            'kind' => $kind,
          ];
        }
      }
    }

    $prev_rel = $rel;
  }

  // seq: cross_dt 오름차순
  usort($boxes, function($a,$b){
    return strcmp($a['cross_dt'], $b['cross_dt']);
  });
  $seq = 1;
  foreach ($boxes as &$b){ $b['seq'] = $seq++; }
  unset($b);

  return $boxes;
}

//--- 1분
function fetch1mRowsForBoxes($mysqli, $date, $start_time, $end_time){
  $sql = "
    SELECT datetime, open, high, low, close, sma_20
    FROM futures_1min
    WHERE date = ?
      AND time >= ?
      AND time <= ?
      AND sma_20 IS NOT NULL
    ORDER BY datetime ASC
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('sss', $date, $start_time, $end_time);
  $stmt->execute();
  $r = $stmt->get_result();
  $rows = [];
  while ($row = $r->fetch_assoc()) $rows[] = $row;
  return $rows;
}

// ✅ (조건1) 장시작봉 교차 포함: 시작 직전 가장 최근 1분봉 1개를 prev로
function fetchPrev1mRowForBoxes($mysqli, $date, $start_time){
  $sql = "
    SELECT datetime, high, low, open, close, sma_20
    FROM futures_1min
    WHERE (date < ? OR (date = ? AND time < ?))
      AND sma_20 IS NOT NULL
    ORDER BY datetime DESC
    LIMIT 1
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('sss', $date, $date, $start_time);
  $stmt->execute();
  $r = $stmt->get_result();
  $row = $r->fetch_assoc();
  return $row ?: null;
}


function calcBoxes1mSma20CrossReject($mysqli, $date, $start_time, $end_time){
  $rows = fetch1mRowsForBoxes($mysqli, $date, $start_time, $end_time);
  if (!$rows || !count($rows)) return [];

  $prev = fetchPrev1mRowForBoxes($mysqli, $date, $start_time);
  $prev_rel = null;
  if ($prev){
    $prev_rel = (float)$prev['close'] - (float)$prev['sma_20'];
  }

  $boxes = [];
  $n = count($rows);

  for ($i=0; $i<$n; $i++){
    $r = $rows[$i];

    $sma   = (float)$r['sma_20'];
    $open  = (float)$r['open'];
    $high  = (float)$r['high'];
    $low   = (float)$r['low'];
    $close = (float)$r['close'];

    $rel = $close - $sma;

    if ($prev_rel === null){
      $prev_rel = $rel;
      continue;
    }

    $dir = null;
    $kind = null; // 'CROSS' | 'REJECT'
    $base_is_event = 0;

    // ----------------------------
    // (A) CROSS (1분봉 rel 부호변화)
    // ----------------------------
    if ($prev_rel <= 0 && $rel > 0){
      $dir = 'UP';
      $kind = 'CROSS';
      // 기존 조건2(윅 기준)
      $up_wick   = $high - $sma;
      $down_wick = $sma - $low;
      $base_is_event = ($up_wick > $down_wick) ? 1 : 0;

    } else if ($prev_rel >= 0 && $rel < 0){
      $dir = 'DOWN';
      $kind = 'CROSS';
      // 기존 조건2(윅 기준)
      $down_wick = $sma - $low;
      $up_wick   = $high - $sma;
      $base_is_event = ($down_wick > $up_wick) ? 1 : 0;
    }

    // ----------------------------
    // (B) REJECT (1분봉 touch+몸통)
    //   - 같은 봉에서 CROSS가 잡히면 REJECT는 생략(중복 방지)
    // ----------------------------
    if (!$dir){
      $touch = ($low <= $sma && $high >= $sma);

      $rejectSupport = ($touch && $open > $sma && $close > $sma);
      $rejectResist  = ($touch && $open < $sma && $close < $sma);

      if ($rejectSupport){
        $dir = 'UP';
        $kind = 'REJECT';
        // ✅ 몸통 기준: 종가쪽이 더 크면 해당 봉이 기준봉
        $up_body   = $close - $sma;
        $down_body = $open  - $sma;
        $base_is_event = ($up_body > $down_body) ? 1 : 0;

      } else if ($rejectResist){
        $dir = 'DOWN';
        $kind = 'REJECT';
        // ✅ 몸통 기준: 종가쪽이 더 크면 해당 봉이 기준봉
        $down_body = $sma - $close;
        $up_body   = $sma - $open;
        $base_is_event = ($down_body > $up_body) ? 1 : 0;
      }
    }

    if ($dir){
      $cross_dt = $r['datetime'];

      $baseRow = null;
      $base_dt = null;

      if ($base_is_event === 1){
        $baseRow = $r;
        $base_dt = $cross_dt;
      } else {
        if ($i + 1 < $n){
          $baseRow = $rows[$i + 1];      // 다음 1분봉
          $base_dt = $baseRow['datetime'];
        }
      }

      if ($baseRow){
        // 확정 조건
        $base_sma   = (float)$baseRow['sma_20'];
        $base_close = (float)$baseRow['close'];
        $base_rel   = $base_close - $base_sma;

        $confirmed = ($dir === 'UP') ? ($base_rel > 0) : ($base_rel < 0);
        if ($confirmed){
          // ✅ 1분봉 기준: confirm = base + 1분
          $confirm_dt = dtAddMinutesStr($base_dt, 1);
          $trigger_dt = dtAddMinutesStr($confirm_dt, -1); // = base_dt와 같아짐

          $boxes[] = [
            'seq' => 0,
            'dir' => $dir,
            'cross_dt' => $cross_dt,
            'base_dt' => $base_dt,
            'confirm_dt' => $confirm_dt,
            'trigger_dt' => $trigger_dt,
            'base_is_event' => (int)$base_is_event,

            'base_high' => (float)$baseRow['high'],
            'base_low'  => (float)$baseRow['low'],

            // (옵션/검증용)
            'base_close' => (float)$base_close,
            'base_sma20' => (float)$base_sma,
            'base_rel'   => (float)$base_rel,

            'cross_sma20' => (float)$sma,
            'cross_high'  => (float)$high,
            'cross_low'   => (float)$low,

            'kind' => $kind,
          ];
        }
      }
    }

    $prev_rel = $rel;
  }

  usort($boxes, function($a,$b){
    return strcmp($a['cross_dt'], $b['cross_dt']);
  });
  $seq = 1;
  foreach ($boxes as &$b){ $b['seq'] = $seq++; }
  unset($b);

  return $boxes;
}

// ✅ (중요) start_time(08:45)을 앵커로 버킷 바닥 잡기
function floorTimeStrAnchored($date, $time, $bucketMin, $anchorTime){
  $dt = new DateTime("$date $time");
  $a  = new DateTime("$date $anchorTime");

  $min = ((int)$dt->format('H'))*60 + (int)$dt->format('i');
  $anc = ((int)$a->format('H'))*60 + (int)$a->format('i');

  $delta = $min - $anc;
  // anchor 이전이면 그냥 anchor로(실제론 거의 안 타지만 안전장치)
  if ($delta < 0) $delta = 0;

  $floDelta = intdiv($delta, $bucketMin) * $bucketMin;
  $floMin = $anc + $floDelta;

  $H = intdiv($floMin, 60);
  $i = $floMin % 60;
  return sprintf('%02d:%02d:00', $H, $i);
}

function buildInitSeries($prevTail, $todayPart, $maxCandles){
  $merged = array_merge($prevTail, $todayPart);
  if ($maxCandles > 0 && count($merged) > $maxCandles){
    $merged = array_slice($merged, -$maxCandles);
  }
  return $merged;
}

function calcPartialFrom1m($rows){
  if (!$rows || !count($rows)) return null;

  $first = $rows[0];
  $last  = $rows[count($rows)-1];

  $o = (float)$first['open'];
  $h = (float)$first['high'];
  $l = (float)$first['low'];
  $c = (float)$last['close'];
  $v = 0.0;

  foreach ($rows as $r){
    $h = max($h, (float)$r['high']);
    $l = min($l, (float)$r['low']);
    $v += (float)($r['volume'] ?? 0);
  }

  return ['open'=>$o,'high'=>$h,'low'=>$l,'close'=>$c,'volume'=>$v];
}

function fetchFirstN_1m($mysqli, $date, $start_time, $n){
  $sql = "
    SELECT datetime, open, high, low
    FROM futures_1min
    WHERE date = ?
      AND time >= ?
    ORDER BY datetime ASC
    LIMIT ?
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param('ssi', $date, $start_time, $n);
  $stmt->execute();
  $r = $stmt->get_result();
  $rows = [];
  while ($row = $r->fetch_assoc()) $rows[] = $row;
  return $rows;
}

// prev date
$prev1  = getPrevTradingDateFromCal($mysqli, $date, 1);
$prev5  = getPrevTradingDateFromCal($mysqli, $date, 1);
$prev15 = getPrevTradingDateFromCal($mysqli, $date, 1);
$prev60 = getPrevTradingDateFromCal($mysqli, $date, 6);

// prev tail
$prevTail1  = fetchTail($mysqli,'futures_1min',  $prev1,  $date, $max_prev_1m);
$prevTail5  = fetchTail($mysqli,'futures_5min',  $prev5,  $date, $max_prev_5m);
$prevTail15 = fetchTail($mysqli,'futures_15min', $prev15, $date, $max_prev_15m);
$prevTail60 = fetchTail($mysqli,'futures_60min', $prev60, $date, $max_prev_60m);

// 당일 전체 확정봉(맵용)
$today5_all  = fetchRange($mysqli,'futures_5min',  $date, $start_time, $end_time);
$today15_all = fetchRange($mysqli,'futures_15min', $date, $start_time, $end_time);
$today60_all = fetchRange($mysqli,'futures_60min', $date, $start_time, $end_time);

// 1분봉: init까지 + future
$today1_upto_init = fetchRange($mysqli,'futures_1min', $date, $start_time, $init_time);
  // // 1분봉 리플레이 시 차트 12시까지만 보게 하기 위해 임시 코드 26.01.04
  // if ($init_time <= "12:00:00")
  //   $today1_upto_init = fetchRange($mysqli,'futures_1min', $date, $start_time, $init_time);
  // else
  //   $today1_upto_init = fetchRange($mysqli,'futures_1min', $date, $start_time, "12:00:00");

$today1_future    = fetchRangeAfter($mysqli,'futures_1min', $date, $init_time, $end_time);

// 버킷 시작 시각(앵커=장 시작시간)
$bucket5_time  = floorTimeStrAnchored($date, $init_time, 5,  $start_time);
$bucket15_time = floorTimeStrAnchored($date, $init_time, 15, $start_time);
$bucket60_time = floorTimeStrAnchored($date, $init_time, 60, $start_time);

// 완료된 확정봉(현재 버킷 이전까지만)
$today5_done  = fetchRangeLT($mysqli,'futures_5min',  $date, $start_time, $bucket5_time);
$today15_done = fetchRangeLT($mysqli,'futures_15min', $date, $start_time, $bucket15_time);
$today60_done = fetchRangeLT($mysqli,'futures_60min', $date, $start_time, $bucket60_time);

// partial 계산용 1분봉(버킷 시작~init_time)
$rows1_for_p5  = fetchRange($mysqli,'futures_1min', $date, $bucket5_time,  $init_time);
$rows1_for_p15 = fetchRange($mysqli,'futures_1min', $date, $bucket15_time, $init_time);
$rows1_for_p60 = fetchRange($mysqli,'futures_1min', $date, $bucket60_time, $init_time);

$p5  = calcPartialFrom1m($rows1_for_p5);
$p15 = calcPartialFrom1m($rows1_for_p15);
$p60 = calcPartialFrom1m($rows1_for_p60);

// partial row/state
$partial5_row = [];
$partial15_row = [];
$partial60_row = [];

$partial5_state = null;
$partial15_state = null;
$partial60_state = null;

if ($p5){
  $partial5_row = [[
    'datetime' => $date.' '.$bucket5_time,
    'open' => $p5['open'], 'high'=>$p5['high'], 'low'=>$p5['low'], 'close'=>$p5['close'],
    'volume'=>$p5['volume'],
    'sma_5'=>null,'sma_20'=>null,'sma_120'=>null
  ]];
  $partial5_state = [
    'bucketTs' => (new DateTime($date.' '.$bucket5_time))->getTimestamp()*1000,
    'o'=>$p5['open'], 'h'=>$p5['high'], 'l'=>$p5['low'], 'c'=>$p5['close'], 'v'=>$p5['volume']
  ];
}

if ($p15){
  $partial15_row = [[
    'datetime' => $date.' '.$bucket15_time,
    'open' => $p15['open'], 'high'=>$p15['high'], 'low'=>$p15['low'], 'close'=>$p15['close'],
    'volume'=>$p15['volume'],
    'sma_5'=>null,'sma_20'=>null,'sma_120'=>null
  ]];
  $partial15_state = [
    'bucketTs' => (new DateTime($date.' '.$bucket15_time))->getTimestamp()*1000,
    'o'=>$p15['open'], 'h'=>$p15['high'], 'l'=>$p15['low'], 'c'=>$p15['close'], 'v'=>$p15['volume']
  ];
}

if ($p60){
  $partial60_row = [[
    'datetime' => $date.' '.$bucket60_time,
    'open' => $p60['open'], 'high'=>$p60['high'], 'low'=>$p60['low'], 'close'=>$p60['close'],
    'volume'=>$p60['volume'],
    'sma_5'=>null,'sma_20'=>null,'sma_120'=>null
  ]];
  $partial60_state = [
    'bucketTs' => (new DateTime($date.' '.$bucket60_time))->getTimestamp()*1000,
    'o'=>$p60['open'], 'h'=>$p60['high'], 'l'=>$p60['low'], 'c'=>$p60['close'], 'v'=>$p60['volume']
  ];
}

// init 시리즈(최대 캔들로 컷)
$init1  = buildInitSeries($prevTail1,  $today1_upto_init, $init_max_1m);
$init5  = buildInitSeries($prevTail5,  array_merge($today5_done,  $partial5_row),  $init_max_5m);
$init15 = buildInitSeries($prevTail15, array_merge($today15_done, $partial15_row), $init_max_15m);
$init60 = buildInitSeries($prevTail60, array_merge($today60_done, $partial60_row), $init_max_60m);

// 고저/시가(1분 N개)
$hilo_rows = fetchFirstN_1m($mysqli, $date, $start_time, $hi_lo_n);
$hilo = null;
if ($hilo_rows && count($hilo_rows)){
  $open = (float)$hilo_rows[0]['open'];
  $high = (float)$hilo_rows[0]['high'];
  $low  = (float)$hilo_rows[0]['low'];
  foreach ($hilo_rows as $r){
    $high = max($high, (float)$r['high']);
    $low  = min($low,  (float)$r['low']);
  }
  $start_hhmm = substr($start_time,0,5);
  $end_hhmm = substr(substr($hilo_rows[count($hilo_rows)-1]['datetime'],11,5),0,5);

  $hilo = [
    'open'=>$open, 'high'=>$high, 'low'=>$low,
    'n'=>$hi_lo_n,
    'start_hhmm'=>$start_hhmm,
    'end_hhmm'=>$end_hhmm
  ];
}

$hilo_rows2 = fetchFirstN_1m($mysqli, $date, $start_time, $hi_lo_n2);

$hilo2 = null;
if ($hilo_rows2 && count($hilo_rows2)){
  $open = (float)$hilo_rows2[0]['open'];
  $high = (float)$hilo_rows2[0]['high'];
  $low  = (float)$hilo_rows2[0]['low'];

  foreach ($hilo_rows2 as $r){
    $high = max($high, (float)$r['high']);
    $low  = min($low,  (float)$r['low']);
  }

  // ✅ high/low 중간값(mid)
  $mid = round(($high + $low) / 2, 2);

  $start_hhmm = substr($start_time,0,5);
  $end_hhmm = substr(substr($hilo_rows2[count($hilo_rows2)-1]['datetime'],11,5),0,5);

  $hilo2 = [
    'open'=>$open,
    'high'=>$high,
    'low'=>$low,
    'mid'=>$mid,          // ✅ 추가
    'n'=>$hi_lo_n2,
    'start_hhmm'=>$start_hhmm,
    'end_hhmm'=>$end_hhmm
  ];
}

// nowTs: init1 마지막 캔들의 시간으로 (없으면 init_time)
$nowTs = null;
if ($init1 && count($init1)){
  $lastDt = $init1[count($init1)-1]['datetime'];
  $nowTs = (new DateTime($lastDt))->getTimestamp()*1000;
} else {
  $nowTs = (new DateTime($date.' '.$init_time))->getTimestamp()*1000;
}

// $boxes = calcBoxes5mSma20Cross($mysqli, $date, $start_time, $end_time);
$boxes = calcBoxes1mSma20CrossReject($mysqli, $date, $start_time, $end_time);

$out = [
  'ok'=>true,
  'date'=>$date,
  'init_time'=>$init_time,
  'nowTs'=>$nowTs,
  'hilo_main'=>$hilo,
  'hilo_aux'=>$hilo2,
  'boxes' => $boxes,
  'm1'=>[
    'init'=>$init1,
    'future'=>$today1_future,
  ],
  'm5'=>[
    'init'=>$init5,
    'today'=>$today5_all,
    'partial'=>$partial5_state
  ],
  'm15'=>[
    'init'=>$init15,
    'today'=>$today15_all,
    'partial'=>$partial15_state
  ],
  'm60'=>[
    'init'=>$init60,
    'today'=>$today60_all,
    'partial'=>$partial60_state
  ]
];

echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>
