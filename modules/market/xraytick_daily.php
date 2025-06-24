<?php
$pageTitle = "XrayTick Comment Register";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

// 기간 데이터를 받아오기
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// 선택된 기간에 맞는 일자 목록 가져오기
$dateQuery = "SELECT DISTINCT date FROM calendar WHERE date BETWEEN ? AND ? ORDER BY date ASC";
$dateStmt = $mysqli->prepare($dateQuery);
$dateStmt->bind_param('ss', $start_date, $end_date);
$dateStmt->execute();
$dateResult = $dateStmt->get_result();

// 일자 배열 만들기
$dates = [];
while ($dateRow = $dateResult->fetch_assoc()) {
    $dates[] = $dateRow['date'];
}

// 데이터 조회 (5억 이상 매수건)
$dataQuery = "
    SELECT xs.code, xs.date, ROUND(xs.tot_amt / 100000000, 1) AS xray_amount, s.name, ss.sector, rc.comment, dp.close_rate, ROUND(dp.amount / 100000000, 0) AS trade_amount
    FROM xraytick_summary xs
    LEFT JOIN stock s ON xs.code = s.code AND s.last_yn = 'Y'
    LEFT JOIN stock_sector ss ON xs.code = ss.code
    LEFT JOIN daily_price dp ON xs.code = dp.code AND xs.date = dp.date
    LEFT JOIN xraytick_review_comments rc ON xs.code = rc.code AND xs.date = rc.comment_date
    WHERE xs.date BETWEEN ? AND ?
    AND xs.tot_amt > 1000000000
    ORDER BY ss.sector, s.name, xs.date ASC";
$dataStmt = $mysqli->prepare($dataQuery);
$dataStmt->bind_param('ss', $start_date, $end_date);
$dataStmt->execute();
$dataResult = $dataStmt->get_result();

// 데이터를 배열로 변환
$data = [];
while ($row = $dataResult->fetch_assoc()) {
    $data[$row['sector']][$row['name']][$row['date']] = [
        'code' => $row['code'],
        'comment' => $row['comment'] ?? '',
        'close_rate' => $row['close_rate'] ?? 0.0,
        'xray_amount' => $row['xray_amount'] ?? 0,
        'trade_amount' => $row['trade_amount'] ?? 0
    ];
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>XrayTick Comment Register</title>
    <style>
        /* 스타일 */
        .search-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .search-bar label,
        .search-bar input,
        .search-bar select,
        .search-bar button {
            margin-right: 10px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .search-bar input,
        .search-bar select {
            width: auto;
            max-width: 200px;
        }

        .table-wrapper {
            max-height: 1000px;
            overflow-x: auto;
            overflow-y: auto;
            white-space: nowrap;
            margin-bottom: 20px;
        }
        
        table {
            border-collapse: collapse;
            width: auto;
            table-layout: auto; /* 테이블이 셀 내용에 맞게 크기를 조정 */
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            vertical-align: middle;
        }

        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
            z-index: 2;
        }


        .fixed {
            position: sticky;
            left: 0;
            background-color: #f9f9f9;
            z-index: 1;
        }

        .comment-cell {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .comment-cell:hover {
            cursor: pointer;
        }

        .highlight {
            background-color: yellow;
        }

        .tooltip-text {
            visibility: hidden;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            width: 300px;
            bottom: 100%;
            left: 50%;
            margin-left: -150px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .comment-cell:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        .comment-box-container {
            margin-top: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            position: sticky;
            bottom: 0;
        }

        .form-container {
            margin-top: 20px;
        }
    </style>
    <script>
        function selectCell(code, name, date, comment) {
            // 줄바꿈 처리: 줄바꿈을 <br>로 변경해주거나 적절히 처리
            comment = comment.replace(/\n/g, '\\n'); // JavaScript에서 줄바꿈을 escape 처리
            document.getElementById('comment_code').value = code;
            document.getElementById('comment_name').value = name;
            document.getElementById('comment_date').value = date;
            document.getElementById('comment_input').value = comment.replace(/\\n/g, '\n'); // 줄바꿈을 다시 엔터로 복원
            document.getElementById('selected_comment').innerText = `종목: ${code} (${name}), 일자: ${date}`;
        }
    </script>
</head>
<body>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_nav_menu.php"); ?>
<div id="content">
    <h2>XrayTick Comment Register</h2>

    <form method="GET" action="xraytick_daily.php" class="search-bar">
        <label for="start_date">Start Date:</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>

        <label for="end_date">End Date:</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>

        <button type="submit">Search</button>
    </form>

    <?php if ($dataResult->num_rows > 0 && count($dates) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th class="fixed">Sector</th>
                        <th class="fixed">Stock</th>
                        <?php foreach ($dates as $date): ?>
                            <th><?= htmlspecialchars($date) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $sector => $stocks): ?>
                        <?php foreach ($stocks as $stock => $comments): ?>
                            <tr>
                                <td class="fixed"><?= htmlspecialchars($sector) ?></td>
                                <td class="fixed"><?= htmlspecialchars($stock) ?></td>
                                <?php foreach ($dates as $date): ?>
                                    <?php
                                    $code = $comments[$date]['code'] ?? '';
                                    $close_rate = $comments[$date]['close_rate'] ?? 0.0;
                                    $xray_amount = $comments[$date]['xray_amount'] ?? 0;
                                    $trade_amount = $comments[$date]['trade_amount'] ?? 0;
                                    $comment = $comments[$date]['comment'] ?? '';
                                    ?>
                                    <td 
                                        <?= isset($comments[$date]) ? "onclick=\"selectCell('{$code}', '{$stock}', '{$date}', '" . str_replace(["\r", "\n"], ["\\r", "\\n"], $comment) . "')\"" : 'class="disabled"' ?>
                                        class="comment-cell <?= !empty($comment) ? 'highlight' : '' ?>"
                                        title="<?= htmlspecialchars($comment) ?>">
                                        <?= $close_rate != 0.0 ? number_format($close_rate,2)  : '' ?><br>
                                        <?= $xray_amount > 0 ? number_format($xray_amount) .'/'.  number_format($trade_amount): '' ?><br>
                                        <?= nl2br(htmlspecialchars($comment)) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 코멘트 입력 및 수정 영역 -->
        <div class="comment-box-container">
            <div class="comment-display" id="selected_comment">선택된 종목과 일자: 없음</div>
            <form method="POST" action="xraytick_comment_process.php" class="form-container">
                <input type="hidden" id="comment_code" name="code">
                <input type="hidden" id="comment_name" name="name">
                <input type="hidden" id="comment_date" name="comment_date">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                <textarea id="comment_input" name="comment" class="comment-box"></textarea><br>
                <button type="submit" name="action" value="save">Save</button>
                <button type="submit" name="action" value="delete">Delete</button>
            </form>
        </div>
    <?php else: ?>
        <p>No data found for the selected period and category.</p>
    <?php endif; ?>
</div>
</body>
</html>

<?php
$dataStmt->close();
$mysqli->close();
?>
