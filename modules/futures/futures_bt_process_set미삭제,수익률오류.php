<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
$action = $_POST['action'] ?? '';

if ($action === 'register_log' || $action === 'update_log') {
    $strategy = $_POST['strategy_name'];
    $date = $_POST['date'] ?? date('Y-m-d');
    $position = $_POST['position'] ?? '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $qty = isset($_POST['qty']) ? intval($_POST['qty']) : 0;
    $log_id = $_POST['id'] ?? null;
    $set_id = $_POST['set_id'] ?? null;

    if ($action === 'update_log' && $log_id) {
        $update = "UPDATE futures_bt_logs SET date = ?, position = ?, price = ?, qty = ? WHERE id = ?";
        $stmt = $mysqli->prepare($update);
        $stmt->bind_param("ssdii", $date, $position, $price, $qty, $log_id);
        $stmt->execute();
        $stmt->close();

        $set_result = $mysqli->query("SELECT set_id FROM futures_bt_logs WHERE id = {$log_id} LIMIT 1");
        $row = $set_result->fetch_assoc();
        $set_id = $row['set_id'];
    } else {
        $find_sql = "SELECT id FROM futures_bt_sets WHERE strategy_name = ? AND status = '진행중' AND created_at <= ? ORDER BY id DESC LIMIT 1";
        $stmt = $mysqli->prepare($find_sql);
        $stmt->bind_param("ss", $strategy, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $set_id = $row['id'];
        } else {
            $insert_set = "INSERT INTO futures_bt_sets (symbol, strategy_name, created_at, status) VALUES ('미니K200', ?, ?, '진행중')";
            $stmt2 = $mysqli->prepare($insert_set);
            $stmt2->bind_param("ss", $strategy, $date);
            $stmt2->execute();
            $set_id = $stmt2->insert_id;
            $stmt2->close();
        }
        $stmt->close();

        $insert_log = "INSERT INTO futures_bt_logs (set_id, date, position, price, qty) VALUES (?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insert_log);
        $stmt->bind_param("issdi", $set_id, $date, $position, $price, $qty);
        $stmt->execute();
        $stmt->close();
    }

    // 정산 로직
    $log_query = "SELECT * FROM futures_bt_logs WHERE set_id = ? ORDER BY date ASC, id ASC";
    $stmt = $mysqli->prepare($log_query);
    $stmt->bind_param("i", $set_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $fee = 0.0003;
    $stack = [];
    $realized_profit = 0;
    $closed_qty = 0;
    $entry_cost_sum = 0;
    $first_position = $logs[0]['position'] ?? null;

    foreach ($logs as $log) {
        $p = $log['position'];
        $q = $log['qty'];
        $price = $log['price'];

        if (empty($stack) || $stack[0]['position'] === $p) {
            for ($i = 0; $i < $q; $i++) {
                $stack[] = ['position' => $p, 'price' => $price];
                if ($p === $first_position) {
                    $entry_cost_sum += $price * 50000;
                }
            }
        } else {
            for ($i = 0; $i < $q; $i++) {
                if (empty($stack)) break;
                $entry = array_shift($stack);
                if ($entry['position'] === 'buy' && $p === 'sell') {
                    $net_buy = $entry['price'] * (1 + $fee);
                    $net_sell = $price * (1 - $fee);
                    $realized_profit += ($net_sell - $net_buy) * 50000;
                } elseif ($entry['position'] === 'sell' && $p === 'buy') {
                    $net_sell = $entry['price'] * (1 + $fee);
                    $net_buy = $price * (1 - $fee);
                    $realized_profit += ($net_sell - $net_buy) * 50000;
                }
                $closed_qty++;
            }
        }
    }

    $unmatched_qty = count($stack);
    $direction = ($first_position === 'buy') ? 'long' : 'short';

    if ($closed_qty > 0 && $entry_cost_sum > 0) {
        $return_pct = ($realized_profit / $entry_cost_sum) * 100;
        $status = ($unmatched_qty === 0) ? '완료' : '진행중';

        $update = "UPDATE futures_bt_sets SET total_qty = ?, return_pct = ?, profit_amt = ?, closed_at = ?, status = ?, direction = ?, remaining_qty = ? WHERE id = ?";
        $stmt = $mysqli->prepare($update);
        $stmt->bind_param("idssssii", $closed_qty, $return_pct, $realized_profit, $date, $status, $direction, $unmatched_qty, $set_id);
        $stmt->execute();
        $stmt->close();

    } else {
        // 매매 미완성 상태
        $status = '진행중';
        $update = "UPDATE futures_bt_sets SET status = ?, direction = ?, remaining_qty = ? WHERE id = ?";
        $stmt = $mysqli->prepare($update);
        $stmt->bind_param("ssii", $status, $direction, $unmatched_qty, $set_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: futures_bt_ui.php?strategy=" . urlencode($strategy) . "&set_id=" . intval($set_id));
    exit;

} elseif ($action === 'delete_log') {
    $log_id = intval($_POST['id']);
    $strategy = $_POST['strategy_name'];
    $set_id = $_POST['set_id'];

    $mysqli->query("DELETE FROM futures_bt_logs WHERE id = {$log_id} LIMIT 1");
    header("Location: futures_bt_ui.php?strategy=" . urlencode($strategy) . "&set_id=" . intval($set_id));
    exit;
}
