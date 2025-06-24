<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'register_log':
    case 'update_log':
        handle_log_save_or_update($mysqli, $_POST);
        break;

    case 'delete_log':
        handle_log_delete($mysqli, $_POST);
        break;
}

function handle_log_save_or_update($mysqli, $data) {
    $strategy = $data['strategy_name'];
    $date = $data['date'] ?? date('Y-m-d');
    $position = $data['position'] ?? '';
    $price = isset($data['price']) ? floatval($data['price']) : 0;
    $qty = isset($data['qty']) ? intval($data['qty']) : 0;
    $log_id = $data['id'] ?? null;
    $set_id = $data['set_id'] ?? null;

    if ($data['action'] === 'update_log' && $log_id) {
        $stmt = $mysqli->prepare("UPDATE futures_bt_logs SET date = ?, position = ?, price = ?, qty = ? WHERE id = ?");
        $stmt->bind_param("ssdii", $date, $position, $price, $qty, $log_id);
        $stmt->execute();
        $stmt->close();

        $res = $mysqli->query("SELECT set_id FROM futures_bt_logs WHERE id = {$log_id} LIMIT 1");
        $row = $res->fetch_assoc();
        $set_id = $row['set_id'];
    } else {
        $stmt = $mysqli->prepare("SELECT id FROM futures_bt_sets WHERE strategy_name = ? AND status = '진행중' AND created_at <= ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ss", $strategy, $date);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $set_id = $row['id'];
        } else {
            $stmt2 = $mysqli->prepare("INSERT INTO futures_bt_sets (symbol, strategy_name, created_at, status) VALUES ('미니K200', ?, ?, '진행중')");
            $stmt2->bind_param("ss", $strategy, $date);
            $stmt2->execute();
            $set_id = $stmt2->insert_id;
            $stmt2->close();
        }
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO futures_bt_logs (set_id, date, position, price, qty) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issdi", $set_id, $date, $position, $price, $qty);
        $stmt->execute();
        $stmt->close();
    }

    settle_logs($mysqli, $set_id, $date);
    header("Location: futures_bt_ui.php?strategy=" . urlencode($strategy) . "&set_id=" . intval($set_id));
    exit;
}

function handle_log_delete($mysqli, $data) {
    $log_id = intval($data['id']);
    $strategy = $data['strategy_name'];
    $set_id = intval($data['set_id']);

    $mysqli->query("DELETE FROM futures_bt_logs WHERE id = {$log_id} LIMIT 1");

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM futures_bt_logs WHERE set_id = ?");
    $stmt->bind_param("i", $set_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row['cnt'] == 0) {
        $stmt = $mysqli->prepare("DELETE FROM futures_bt_sets WHERE id = ?");
        $stmt->bind_param("i", $set_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // 로그가 남아 있다면 상태 재정산
        settle_logs($mysqli, $set_id, date('Y-m-d'));
    }

    header("Location: futures_bt_ui.php?strategy=" . urlencode($strategy) . "&set_id=" . intval($set_id));
    exit;
}

function settle_logs($mysqli, $set_id, $date) {
    $stmt = $mysqli->prepare("SELECT * FROM futures_bt_logs WHERE set_id = ? ORDER BY date ASC, id ASC");
    $stmt->bind_param("i", $set_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $logs = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $fee = 0.0003; // 수수료 0.03%
    $stack = [];
    $realized_profit = 0;
    $entry_cost_sum = 0;
    $closed_qty = 0;
    $first_position = null;

    foreach ($logs as $log) {
        $p = $log['position'];
        $q = $log['qty'];
        $price = $log['price'];

        if (empty($stack)) $first_position = $p;

        if (empty($stack) || $stack[0]['position'] === $p) {
            for ($i = 0; $i < $q; $i++) {
                $stack[] = ['position' => $p, 'price' => $price];
            }
        } else {
            for ($i = 0; $i < $q; $i++) {
                if (empty($stack)) break;
                $entry = array_shift($stack);

                if ($entry['position'] === 'buy' && $p === 'sell') {
                    // long 진입
                    $entry_cost_sum += $entry['price'] * (1 + $fee) * 50000;

                    $net_buy = $entry['price'] * (1 + $fee);
                    $net_sell = $price * (1 - $fee);
                    $realized_profit += ($net_sell - $net_buy) * 50000;
                } elseif ($entry['position'] === 'sell' && $p === 'buy') {
                    // short 진입 → 청산: 매수, 진입비용 = 커버 매수 비용
                    $entry_cost_sum += $price * (1 + $fee) * 50000;

                    $initial_sell_proceeds = $entry['price'] * (1 - $fee);
                    $cover_buy_cost = $price * (1 + $fee);
                    $realized_profit += ($initial_sell_proceeds - $cover_buy_cost) * 50000;
                }
                $closed_qty++;
            }
        }
    }

    $unmatched_qty = count($stack);
    $direction = ($first_position === 'buy') ? 'long' : 'short';
    $status = ($unmatched_qty === 0) ? '완료' : '진행중';
    $return_pct = ($entry_cost_sum > 0) ? ($realized_profit / $entry_cost_sum) * 100 : 0;

    $stmt = $mysqli->prepare("UPDATE futures_bt_sets SET total_qty = ?, return_pct = ?, profit_amt = ?, closed_at = ?, status = ?, direction = ?, remaining_qty = ? WHERE id = ?");
    $stmt->bind_param("iddsssii", $closed_qty, $return_pct, $realized_profit, $date, $status, $direction, $unmatched_qty, $set_id);
    $stmt->execute();
    $stmt->close();
}