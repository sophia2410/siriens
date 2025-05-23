<?php
$pageTitle = "선물 백테스트 매매 관리";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

// 기본 전략명: 가장 최근 등록된 set_id의 전략
if (isset($_GET['strategy'])) {
    $strategy = $_GET['strategy'];
} else {
    $strategy_result = $mysqli->query("SELECT strategy_name FROM futures_bt_sets ORDER BY id DESC LIMIT 1");
    $strategy = $strategy_result->fetch_assoc()['strategy_name'] ?? 'RSI14_60min';
}

$selectedSetId = $_GET['set_id'] ?? null;
$editLogId = $_GET['edit_log_id'] ?? null;

// 전략별 세트 조회
$sets_stmt = $mysqli->prepare("SELECT * FROM futures_bt_sets WHERE strategy_name = ? ORDER BY id DESC");
$sets_stmt->bind_param("s", $strategy);
$sets_stmt->execute();
$sets_result = $sets_stmt->get_result();

// 최근 등록일 (해당 전략 기준)
$date_stmt = $mysqli->prepare("
    SELECT MAX(l.date) AS max_date
    FROM futures_bt_logs l
    JOIN futures_bt_sets s ON l.set_id = s.id
    WHERE s.strategy_name = ?
");
$date_stmt->bind_param("s", $strategy);
$date_stmt->execute();
$date_result = $date_stmt->get_result();
$defaultDate = $date_result->fetch_assoc()['max_date'] ?? date('Y-m-d');
$date_stmt->close();

// 선택된 세트의 매매 내역
$logs_stmt = $mysqli->prepare("SELECT * FROM futures_bt_logs WHERE set_id = ? ORDER BY date DESC, id DESC");
$logs_stmt->bind_param("i", $selectedSetId);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();

// 수정할 매매 내역 불러오기
$edit_log = null;
if ($editLogId) {
    $edit_stmt = $mysqli->prepare("SELECT * FROM futures_bt_logs WHERE id = ? LIMIT 1");
    $edit_stmt->bind_param("i", $editLogId);
    $edit_stmt->execute();
    $edit_log = $edit_stmt->get_result()->fetch_assoc();
    $edit_stmt->close();
}

// 다른 전략 불러오기
$strategy_query = $mysqli->query("SELECT DISTINCT strategy_name FROM futures_bt_sets ORDER BY strategy_name");
$strategy_list = [];
while ($row = $strategy_query->fetch_assoc()) {
    $strategy_list[] = $row['strategy_name'];
}
?>

<style>
    .form-section label { font-weight: bold; display: block; margin-top: 8px; }
    .form-section input, .form-section select { width: 100%; padding: 6px; margin-bottom: 10px; }
    .btn { background-color: #2980b9; color: white; border: none; padding: 8px 16px; cursor: pointer; }
    .btn:hover { background-color: #1c5980; }
    .btn-danger { background-color: #c0392b; }
    .btn-danger:hover { background-color: #922b21; }
    table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
</style>

<body style="margin-left:150px">
<?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_nav_menu.php"); ?>
<div style="display: flex; gap: 20px; padding: 20px;">
    <div style="width: 20%;">
        <h2><?= $edit_log ? '매매 수정' : '매매 등록' ?></h2>
        <form method="post" action="futures_bt_process.php" class="form-section">
            <input type="hidden" name="action" value="<?= $edit_log ? 'update_log' : 'register_log' ?>">
            <?php if ($edit_log): ?>
                <input type="hidden" name="id" value="<?= $edit_log['id'] ?>">
            <?php endif; ?>
            <input type="hidden" name="set_id" value="<?= htmlspecialchars($selectedSetId) ?>">

            <label>전략명</label>
            <input type="text" name="strategy_name" value="<?= htmlspecialchars($strategy) ?>" required>

            <label>일자</label>
            <input type="date" name="date" value="<?= htmlspecialchars($edit_log['date'] ?? $defaultDate) ?>" required>

            <label>포지션</label>
            <select name="position">
                <option value="buy" <?= isset($edit_log) && $edit_log['position'] === 'buy' ? 'selected' : '' ?>>매수</option>
                <option value="sell" <?= isset($edit_log) && $edit_log['position'] === 'sell' ? 'selected' : '' ?>>매도</option>
            </select>

            <label>진입가</label>
            <input type="text" name="price" value="<?= $edit_log['price'] ?? '' ?>" required>

            <label>수량</label>
            <input type="number" name="qty" value="<?= $edit_log['qty'] ?? '1' ?>" required>

            <button class="btn" type="submit"><?= $edit_log ? '수정 저장' : '등록' ?></button>
            <?php if ($edit_log): ?>
                <a class="btn btn-danger" href="?strategy=<?= urlencode($strategy) ?>&set_id=<?= $selectedSetId ?>">초기화</a>
            <?php endif; ?>
        </form>

        <hr>

        <form method="get" action="">
            <label>다른 전략 보기:</label>
            <select name="strategy" onchange="this.form.submit()">
                <?php foreach ($strategy_list as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $strategy === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div style="width: 80%;">
        <h2><?= htmlspecialchars($strategy) ?> 전략 세트 목록</h2>
        <div style="max-height: 800px; overflow-y: auto; border: 1px solid #ccc;">
        <table>
            <tr>
                <th>ID</th>
                <th>시작일</th>
                <th>완료일</th>
                <th>상태</th>
                <th>방향</th>
                <th>수량</th>
                <th>수익률</th>
                <th>수익금</th>
                <th>선택</th>
            </tr>
            <?php while ($row = $sets_result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= $row['created_at'] ?></td>
                <td><?= $row['closed_at'] ?? '-' ?></td>
                <td><?= $row['status'] ?></td>
                <td><?= $row['direction'] ?? '-' ?></td>
                <td><?= $row['total_qty'] ?? 0 ?></td>
                <td><?= $row['return_pct'] ?? '-' ?>%</td>
                <td><?= number_format($row['profit_amt'] ?? 0) ?>원</td>
                <td><a class="btn" href="?strategy=<?= urlencode($strategy) ?>&set_id=<?= $row['id'] ?>">보기</a></td>
            </tr>
            <?php endwhile; ?>
        </table>
        </div>

        <?php if ($selectedSetId): ?>
        <h3>세트 #<?= $selectedSetId ?> 매매 내역</h3>
        <table>
            <tr><th>일자</th><th>포지션</th><th>진입가</th><th>수량</th><th>수정</th><th>삭제</th></tr>
            <?php while ($log = $logs_result->fetch_assoc()): ?>
            <tr>
                <td><?= $log['date'] ?></td>
                <td><?= $log['position'] === 'buy' ? '매수' : '매도' ?></td>
                <td><?= $log['price'] ?></td>
                <td><?= $log['qty'] ?></td>
                <td>
                    <a class="btn" href="?strategy=<?= urlencode($strategy) ?>&set_id=<?= $selectedSetId ?>&edit_log_id=<?= $log['id'] ?>">수정</a>
                </td>
                <td>
                    <form method="post" action="futures_bt_process.php" style="display:inline-block;">
                        <input type="hidden" name="action" value="delete_log">
                        <input type="hidden" name="id" value="<?= $log['id'] ?>">
                        <input type="hidden" name="strategy_name" value="<?= htmlspecialchars($strategy) ?>">
                        <input type="hidden" name="set_id" value="<?= $selectedSetId ?>">
                        <button class="btn btn-danger" type="submit">삭제</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
