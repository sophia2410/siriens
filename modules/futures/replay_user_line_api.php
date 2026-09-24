<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require $_SERVER['DOCUMENT_ROOT'] . '/modules/common/database.php';

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function validDate(string $date): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt !== false && $dt->format('Y-m-d') === $date;
}

function fetchLines(mysqli $mysqli, int $runId, string $tradeDate): array {
    $stmt = $mysqli->prepare(
        'SELECT id, trade_date, price, comment, color, dash_style, enabled
           FROM futures_sim_user_lines
          WHERE run_id = ? AND trade_date = ?
          ORDER BY price DESC, id ASC'
    );
    $stmt->bind_param('is', $runId, $tradeDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'run_id' => $runId,
            'trade_date' => $row['trade_date'],
            'price' => (float)$row['price'],
            'comment' => $row['comment'] ?? '',
            'color' => $row['color'] ?: '#7c3aed',
            'dash_style' => $row['dash_style'] ?: 'Dash',
            'enabled' => (bool)$row['enabled'],
        ];
    }
    $stmt->close();
    return $rows;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['ok' => false, 'msg' => 'POST 요청만 허용됩니다.'], 405);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $runId = (int)($_POST['run_id'] ?? 0);
    $tradeDate = trim((string)($_POST['trade_date'] ?? ''));
    if ($runId <= 0) {
        respond(['ok' => false, 'msg' => '회차 정보가 올바르지 않습니다.'], 422);
    }
    if (!validDate($tradeDate)) {
        respond(['ok' => false, 'msg' => '거래일 형식이 올바르지 않습니다.'], 422);
    }

    if ($action === 'list') {
        respond(['ok' => true, 'lines' => fetchLines($mysqli, $runId, $tradeDate)]);
    }

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $priceRaw = $_POST['price'] ?? null;
        $price = is_numeric($priceRaw) ? (float)$priceRaw : 0.0;
        $comment = trim((string)($_POST['comment'] ?? ''));

        if (!is_finite($price) || $price <= 0) {
            respond(['ok' => false, 'msg' => '올바른 가격을 입력하세요.'], 422);
        }
        if (mb_strlen($comment, 'UTF-8') > 200) {
            respond(['ok' => false, 'msg' => '코멘트는 200자 이내로 입력하세요.'], 422);
        }

        if ($id > 0) {
            $stmt = $mysqli->prepare(
                'UPDATE futures_sim_user_lines
                    SET price = ?, comment = ?
                  WHERE id = ? AND run_id = ? AND trade_date = ?'
            );
            $stmt->bind_param('dsiis', $price, $comment, $id, $runId, $tradeDate);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                $check = $mysqli->prepare(
                    'SELECT id FROM futures_sim_user_lines
                      WHERE id = ? AND run_id = ? AND trade_date = ?'
                );
                $check->bind_param('iis', $id, $runId, $tradeDate);
                $check->execute();
                if (!$check->get_result()->fetch_assoc()) {
                    $check->close();
                    $stmt->close();
                    respond(['ok' => false, 'msg' => '수정할 라인을 찾을 수 없습니다.'], 404);
                }
                $check->close();
            }
            $stmt->close();
        } else {
            $stmt = $mysqli->prepare(
                "INSERT INTO futures_sim_user_lines
                    (run_id, trade_date, price, comment, color, dash_style, enabled)
                 VALUES (?, ?, ?, ?, '#7c3aed', 'Dash', 1)"
            );
            $stmt->bind_param('isds', $runId, $tradeDate, $price, $comment);
            $stmt->execute();
            $stmt->close();
        }

        respond(['ok' => true, 'lines' => fetchLines($mysqli, $runId, $tradeDate)]);
    }

    if ($action === 'replace_reference_bar') {
        $barTime = trim((string)($_POST['bar_time'] ?? ''));
        $highRaw = $_POST['high'] ?? null;
        $lowRaw = $_POST['low'] ?? null;
        $high = is_numeric($highRaw) ? (float)$highRaw : 0.0;
        $low = is_numeric($lowRaw) ? (float)$lowRaw : 0.0;

        if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $barTime)) {
            respond(['ok' => false, 'msg' => '기준봉 시간이 올바르지 않습니다.'], 422);
        }
        if (!is_finite($high) || !is_finite($low) || $high <= 0 || $low <= 0 || $high < $low) {
            respond(['ok' => false, 'msg' => '기준봉 고가와 저가가 올바르지 않습니다.'], 422);
        }

        $highComment = '[기준봉:HIGH] ' . $barTime . ' 고가';
        $lowComment = '[기준봉:LOW] ' . $barTime . ' 저가';

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare(
                "DELETE FROM futures_sim_user_lines
                  WHERE run_id = ? AND trade_date = ?
                    AND (comment LIKE '[기준봉:HIGH] %' OR comment LIKE '[기준봉:LOW] %')"
            );
            $stmt->bind_param('is', $runId, $tradeDate);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare(
                "INSERT INTO futures_sim_user_lines
                    (run_id, trade_date, price, comment, color, dash_style, enabled)
                 VALUES
                    (?, ?, ?, ?, '#ef4444', 'Solid', 1),
                    (?, ?, ?, ?, '#3b82f6', 'Solid', 1)"
            );
            $stmt->bind_param(
                'isdsisds',
                $runId, $tradeDate, $high, $highComment,
                $runId, $tradeDate, $low, $lowComment
            );
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        respond(['ok' => true, 'lines' => fetchLines($mysqli, $runId, $tradeDate)]);
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $enabled = ((int)($_POST['enabled'] ?? 0)) === 1 ? 1 : 0;
        if ($id <= 0) {
            respond(['ok' => false, 'msg' => '라인 ID가 올바르지 않습니다.'], 422);
        }

        $stmt = $mysqli->prepare(
            'UPDATE futures_sim_user_lines
                SET enabled = ?
              WHERE id = ? AND run_id = ? AND trade_date = ?'
        );
        $stmt->bind_param('iiis', $enabled, $id, $runId, $tradeDate);
        $stmt->execute();
        $stmt->close();
        respond(['ok' => true, 'lines' => fetchLines($mysqli, $runId, $tradeDate)]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            respond(['ok' => false, 'msg' => '라인 ID가 올바르지 않습니다.'], 422);
        }

        $stmt = $mysqli->prepare(
            'DELETE FROM futures_sim_user_lines
              WHERE id = ? AND run_id = ? AND trade_date = ?'
        );
        $stmt->bind_param('iis', $id, $runId, $tradeDate);
        $stmt->execute();
        $stmt->close();
        respond(['ok' => true, 'lines' => fetchLines($mysqli, $runId, $tradeDate)]);
    }

    respond(['ok' => false, 'msg' => '지원하지 않는 작업입니다.'], 400);
} catch (Throwable $e) {
    error_log('replay_user_line_api: ' . $e->getMessage());
    respond(['ok' => false, 'msg' => '사용자 라인 처리 중 오류가 발생했습니다.'], 500);
}
