<?php
// futures_replay_trade_api.php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

/** -----------------------------
 *  공통 에러 핸들링: 무조건 JSON
 * ----------------------------- */
set_error_handler(function($severity, $message, $file, $line){
  http_response_code(200);
  if (ob_get_length()) ob_clean();
  echo json_encode(['ok'=>false, 'msg'=>"PHP error: {$message} @{$file}:{$line}"], JSON_UNESCAPED_UNICODE);
  exit;
});

set_exception_handler(function($e){
  http_response_code(200);
  if (ob_get_length()) ob_clean();
  echo json_encode(['ok'=>false, 'msg'=>"EXCEPTION: ".$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
});

register_shutdown_function(function(){
  $err = error_get_last();
  if ($err){
    http_response_code(200);
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok'=>false, 'msg'=>"FATAL: {$err['message']} @{$err['file']}:{$err['line']}"], JSON_UNESCAPED_UNICODE);
    exit;
  }
});

header('Content-Type: application/json; charset=utf-8');

function json_out($arr, $code = 200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

function req($key, $default = null){
  return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function as_int($v, $default = 0){
  if ($v === null || $v === '') return $default;
  return intval($v);
}

function as_float($v, $default = null){
  if ($v === null || $v === '') return $default;
  return floatval($v);
}

/** -----------------------------
 *  설정값
 * ----------------------------- */
// ✅ KOSPI200 선물 기준: 1pt = 250,000원
// $POINT_VALUE = 250000;

// ✅ KOSPI200 미니 선물 기준: 1pt = 50,000원
$POINT_VALUE = 50000;

// ✅ 수수료(요청): 0.003%
// - 기준: (체결가 price * POINT_VALUE * qty) * 0.0003
$FEE_RATE = 0.00003;

$ALLOWED_ACTIONS = ['OPEN_LONG','OPEN_SHORT','CLOSE_PART','CLOSE_ALL'];

$mode = req('mode', '');
if (!$mode) json_out(['ok'=>false,'msg'=>'mode is required'], 400);

/** -----------------------------
 *  상태 계산(포지션/평단/실현손익 points)
 * ----------------------------- */
function calc_state_from_trades($trades){
  $pos = 0;        // signed int: +long, -short
  $avg = null;     // float|null
  $realized = 0.0; // realized points

  foreach ($trades as $t){
    $action = $t['action'];
    $price  = floatval($t['price']);
    $qtyReq = max(1, intval($t['qty']));

    if ($action === 'OPEN_LONG'){
      if ($pos < 0) return [false, "숏 보유 중에는 OPEN_LONG 불가", null];
      $newQty = $qtyReq;

      if ($pos == 0){
        $pos = $newQty;
        $avg = $price;
      } else {
        $avg = (($pos * $avg) + ($newQty * $price)) / ($pos + $newQty);
        $pos += $newQty;
      }
      continue;
    }

    if ($action === 'OPEN_SHORT'){
      if ($pos > 0) return [false, "롱 보유 중에는 OPEN_SHORT 불가", null];
      $newQty = $qtyReq;

      if ($pos == 0){
        $pos = -$newQty;
        $avg = $price;
      } else {
        $absPos = abs($pos);
        $avg = (($absPos * $avg) + ($newQty * $price)) / ($absPos + $newQty);
        $pos -= $newQty; // more negative
      }
      continue;
    }

    if ($action === 'CLOSE_PART' || $action === 'CLOSE_ALL'){
      if ($pos == 0) return [false, "무포지션에서는 청산 불가", null];

      $absPos = abs($pos);
      $closeQty = ($action === 'CLOSE_ALL') ? $absPos : min($absPos, $qtyReq);

      // realized += closeQty * (price - avg) * sign(pos)
      $sign = ($pos > 0) ? 1 : -1;
      $realized += $closeQty * ($price - $avg) * $sign;

      // reduce position
      if ($pos > 0) $pos -= $closeQty;
      else         $pos += $closeQty;

      if ($pos == 0) $avg = null;
      continue;
    }

    return [false, "알 수 없는 action: {$action}", null];
  }

  return [true, null, [
    'pos_qty'    => $pos,
    'avg_price'  => $avg,
    'pnl_points' => round($realized, 4),
  ]];
}

/** -----------------------------
 *  DB helpers
 * ----------------------------- */
function fetch_trades($mysqli, $day_id){
  $stmt = $mysqli->prepare("
    SELECT trade_id, seq, action, bar_dt, price, qty, fee_amount, note, created_at
    FROM futures_sim_trade
    WHERE day_id = ?
    ORDER BY seq ASC
  ");
  $stmt->bind_param('i', $day_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();
  return $rows;
}

function calc_fee_amount($price, $qty, $POINT_VALUE, $FEE_RATE){
  // fee = price(points) * pointValue(KRW/pt) * qty * rate
  $notional = (float)$price * (float)$POINT_VALUE * (int)$qty;
  $fee = (int)round($notional * (float)$FEE_RATE);
  if ($fee < 0) $fee = 0;
  return $fee;
}

function update_day_summary($mysqli, $day_id, $pnl_points, $fee_total, $POINT_VALUE){
  $pnl_amount = (int)round($pnl_points * $POINT_VALUE);
  $pnl_amount_net = $pnl_amount - (int)$fee_total;

  $stmt = $mysqli->prepare("
    UPDATE futures_sim_run_day
    SET pnl_points = ?, pnl_amount = ?, fee_total = ?, pnl_amount_net = ?
    WHERE day_id = ?
  ");
  $stmt->bind_param('diiii', $pnl_points, $pnl_amount, $fee_total, $pnl_amount_net, $day_id);
  $stmt->execute();
  $stmt->close();

  return [$pnl_amount, $pnl_amount_net];
}

function day_payload($mysqli, $day_id, $state, $POINT_VALUE){
  $stmt = $mysqli->prepare("
    SELECT day_id, run_id, trade_date, start_at, pnl_points, pnl_amount, fee_total, pnl_amount_net, day_comment
    FROM futures_sim_run_day
    WHERE day_id = ?
  ");
  $stmt->bind_param('i', $day_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $day = $res->fetch_assoc();
  $stmt->close();

  if (!$day) return null;

  $pos = intval($state['pos_qty']);
  if ($pos > 0) $pos_text = "LONG {$pos}";
  elseif ($pos < 0) $pos_text = "SHORT " . abs($pos);
  else $pos_text = "FLAT";

  $day['pos_qty'] = $pos;
  $day['pos_text'] = $pos_text;
  $day['avg_price'] = $state['avg_price'];

  // 계산값(프론트에서 표시용)
  $day['pnl_points_calc'] = $state['pnl_points'];
  $day['pnl_amount_calc'] = (int)round($state['pnl_points'] * $POINT_VALUE);

  return $day;
}

/** -----------------------------
 *  mode handlers
 * ----------------------------- */

if ($mode === 'run_list'){
  $rows = [];
  $q = $mysqli->query("
    SELECT run_id, run_name, start_date, created_at
    FROM futures_sim_run
    ORDER BY run_id DESC
    LIMIT 200
  ");
  while($r = $q->fetch_assoc()) $rows[] = $r;

  // ✅ runs가 비어도 ok:true 유지
  json_out(['ok'=>true, 'runs'=>$rows]);
}

if ($mode === 'run_create'){
  $name = trim((string)req('run_name',''));

  if ($name === '') json_out(['ok'=>false,'msg'=>'run_name is required'], 400);
  $start_date = date('Y-m-d');

  $stmt = $mysqli->prepare("INSERT INTO futures_sim_run (run_name, start_date) VALUES (?, ?)");
  $stmt->bind_param('ss', $name, $start_date);
  $ok = $stmt->execute();
  $run_id = $stmt->insert_id;
  $stmt->close();

  if (!$ok || $run_id <= 0) json_out(['ok'=>false,'msg'=>'insert failed'], 500);
  json_out(['ok'=>true, 'run_id'=>$run_id]);
}

if ($mode === 'day_get'){
  $run_id = as_int(req('run_id', 0));
  $trade_date = trim((string)req('trade_date',''));
  $start_at = trim((string)req('start_at','09:00:00'));

  if ($run_id <= 0) json_out(['ok'=>false,'msg'=>'run_id is required'], 400);
  if ($trade_date === '') json_out(['ok'=>false,'msg'=>'trade_date is required'], 400);
  if (strlen($start_at) === 5) $start_at .= ':00';

  // ✅ (run_id, trade_date) 유니크로 day 1개 고정
  // ✅ 중복이면 기존 day_id를 LAST_INSERT_ID로 되돌려 받음
  $stmt = $mysqli->prepare("
    INSERT INTO futures_sim_run_day (run_id, trade_date, start_at)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE day_id = LAST_INSERT_ID(day_id)
  ");
  $stmt->bind_param('iss', $run_id, $trade_date, $start_at);
  $ok = $stmt->execute();
  $day_id = $stmt->insert_id;
  $stmt->close();

  if (!$ok || $day_id <= 0) json_out(['ok'=>false,'msg'=>'day_get failed'], 500);

  // trades + state
  $trades = fetch_trades($mysqli, $day_id);
  [$ok2, $msg2, $state] = calc_state_from_trades($trades);
  if (!$ok2) json_out(['ok'=>false,'msg'=>$msg2], 400);

  // fee_total 합산
  $fee_total = 0;
  foreach($trades as $t) $fee_total += intval($t['fee_amount'] ?? 0);

  // day 요약 동기화
  update_day_summary($mysqli, $day_id, $state['pnl_points'], $fee_total, $POINT_VALUE);
  $day = day_payload($mysqli, $day_id, $state, $POINT_VALUE);

  json_out(['ok'=>true, 'day'=>$day, 'trades'=>$trades]);
}

if ($mode === 'trade_add'){
  $day_id = as_int(req('day_id', 0));
  $action = trim((string)req('action',''));
  $bar_dt = trim((string)req('bar_dt',''));
  $price  = as_float(req('price', null), null);
  $qty    = as_int(req('qty', 1), 1);
  $note   = trim((string)req('note',''));

  if ($day_id <= 0) json_out(['ok'=>false,'msg'=>'day_id is required'], 400);
  if (!in_array($action, $GLOBALS['ALLOWED_ACTIONS'], true)) json_out(['ok'=>false,'msg'=>'invalid action'], 400);
  if ($bar_dt === '') json_out(['ok'=>false,'msg'=>'bar_dt is required'], 400);
  if ($price === null || !is_numeric($price)) json_out(['ok'=>false,'msg'=>'price is required'], 400);
  if ($qty <= 0) $qty = 1;

  // 기존 trade 상태
  $existing = fetch_trades($mysqli, $day_id);
  [$okS, $msgS, $stS] = calc_state_from_trades($existing);
  if (!$okS) json_out(['ok'=>false,'msg'=>$msgS], 400);
  $pos_qty = intval($stS['pos_qty'] ?? 0);

  // ✅ CLOSE_ALL이면 qty를 “실제 전량”으로 강제
  if ($action === 'CLOSE_ALL'){
    if ($pos_qty === 0) json_out(['ok'=>false,'msg'=>'무포지션에서는 전량청산 불가'], 400);
    $qty = abs($pos_qty);
  }

  // ✅ CLOSE_PART인데 qty가 과하면 실제 청산 qty로 저장(로그/수수료도 일치시키기)
  if ($action === 'CLOSE_PART'){
    if ($pos_qty === 0) json_out(['ok'=>false,'msg'=>'무포지션에서는 청산 불가'], 400);
    $qty = min(abs($pos_qty), $qty);
  }

  // 시뮬 유효성 검증(저장 전 꼬임 방지)
  $tmp = $existing;
  $tmp[] = ['action'=>$action, 'price'=>$price, 'qty'=>$qty];
  [$okV, $msgV, $stateAfter] = calc_state_from_trades($tmp);
  if (!$okV) json_out(['ok'=>false,'msg'=>$msgV], 400);

  // seq = max+1
  $stmt = $mysqli->prepare("SELECT IFNULL(MAX(seq),0)+1 AS nxt FROM futures_sim_trade WHERE day_id=?");
  $stmt->bind_param('i', $day_id);
  $stmt->execute();
  $stmt->bind_result($nxt);
  $stmt->fetch();
  $stmt->close();
  $seq = intval($nxt);
  if ($seq <= 0) $seq = 1;

  // ✅ 수수료 계산
  $fee_amount = calc_fee_amount($price, $qty, $POINT_VALUE, $FEE_RATE);

  // 저장
  $stmt = $mysqli->prepare("
    INSERT INTO futures_sim_trade (day_id, seq, action, bar_dt, price, qty, fee_amount, note)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->bind_param('iissdiis', $day_id, $seq, $action, $bar_dt, $price, $qty, $fee_amount, $note);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) json_out(['ok'=>false,'msg'=>'trade insert failed'], 500);

  // 저장 후 재계산/업데이트
  $trades = fetch_trades($mysqli, $day_id);
  [$ok2, $msg2, $state] = calc_state_from_trades($trades);
  if (!$ok2) json_out(['ok'=>false,'msg'=>$msg2], 400);

  $fee_total = 0;
  foreach($trades as $t) $fee_total += intval($t['fee_amount'] ?? 0);

  update_day_summary($mysqli, $day_id, $state['pnl_points'], $fee_total, $POINT_VALUE);
  $day = day_payload($mysqli, $day_id, $state, $POINT_VALUE);

  json_out(['ok'=>true, 'day'=>$day, 'trades'=>$trades]);
}

if ($mode === 'trade_undo'){
  $day_id = as_int(req('day_id', 0));
  if ($day_id <= 0) json_out(['ok'=>false,'msg'=>'day_id is required'], 400);

  // 마지막 trade
  $stmt = $mysqli->prepare("
    SELECT trade_id
    FROM futures_sim_trade
    WHERE day_id = ?
    ORDER BY seq DESC
    LIMIT 1
  ");
  $stmt->bind_param('i', $day_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $last = $res->fetch_assoc();
  $stmt->close();

  if (!$last) json_out(['ok'=>false,'msg'=>'삭제할 trade가 없습니다'], 400);

  $trade_id = intval($last['trade_id']);

  $stmt = $mysqli->prepare("DELETE FROM futures_sim_trade WHERE trade_id=?");
  $stmt->bind_param('i', $trade_id);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) json_out(['ok'=>false,'msg'=>'delete failed'], 500);

  // 재계산
  $trades = fetch_trades($mysqli, $day_id);
  [$ok2, $msg2, $state] = calc_state_from_trades($trades);
  if (!$ok2) {
    // 데이터 꼬임이면 일단 0으로 리셋하고 경고
    update_day_summary($mysqli, $day_id, 0.0, 0, $POINT_VALUE);
    json_out(['ok'=>false,'msg'=>"undo 후 상태 계산 실패: {$msg2}"], 400);
  }

  $fee_total = 0;
  foreach($trades as $t) $fee_total += intval($t['fee_amount'] ?? 0);

  update_day_summary($mysqli, $day_id, $state['pnl_points'], $fee_total, $POINT_VALUE);
  $day = day_payload($mysqli, $day_id, $state, $POINT_VALUE);

  json_out(['ok'=>true, 'day'=>$day, 'trades'=>$trades]);
}

if ($mode === 'day_comment_set') {
  $day_id  = intval($_POST['day_id'] ?? 0);
  $comment = $_POST['comment'] ?? '';

  if ($day_id <= 0) {
    echo json_encode(['ok'=>false, 'msg'=>'day_id invalid']); exit;
  }

  // (선택) 길이 제한
  if (mb_strlen($comment) > 5000) {
    echo json_encode(['ok'=>false, 'msg'=>'comment too long']); exit;
  }

  $stmt = $mysqli->prepare("UPDATE futures_sim_run_day SET day_comment=? WHERE day_id=?");
  $stmt->bind_param('si', $comment, $day_id);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) {
    echo json_encode(['ok'=>false, 'msg'=>'update failed']); exit;
  }

  echo json_encode(['ok'=>true]);
  exit;
}


json_out(['ok'=>false,'msg'=>'unknown mode'], 400);
