<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issueId = $_POST['issue_id'];
    $stockCode = $_POST['code'];
    $stockName = $_POST['name'];
    $stockComment = $_POST['stock_comment'];
    $isLeader = isset($_POST['is_leader']) ? '1' : '0';

    $stmt = $mysqli->prepare("UPDATE market_issue_stocks SET name = ?, stock_comment = ?, is_leader = ? WHERE issue_id = ? AND code = ?");
    $stmt->bind_param('sssis', $stockName, $stockComment, $isLeader, $issueId, $stockCode);
    $stmt->execute();

    header("Location: market_issue_new.php?code=$stockCode"); // 수정 후 원래 페이지로 리다이렉트
    exit;
} else {
    $issueId = $_GET['issue_id'];
    $stockCode = $_GET['code'];

    $stmt = $mysqli->prepare("SELECT * FROM market_issue_stocks WHERE issue_id = ? AND code = ?");
    $stmt->bind_param('is', $issueId, $stockCode);
    $stmt->execute();
    $stockData = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>종목 내역 수정</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            height: 100vh;
        }
        form {
            margin: 50px;
            padding: 20px;
            background-color: #fff;
            border-radius: 4px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        label {
            font-weight: bold;
            margin-top: 10px;
            display: block;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            margin-top: 15px;
            padding: 8px 12px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

<form method="post" action="">
    <input type="hidden" name="issue_id" value="<?= htmlspecialchars($issueId) ?>">
    <input type="hidden" name="code" value="<?= htmlspecialchars($stockCode) ?>">

    <label for="name">종목명:</label>
    <input type="text" id="name" name="name" value="<?= htmlspecialchars($stockData['name']) ?>" required>

    <label for="stock_comment">코멘트:</label>
    <textarea id="stock_comment" name="stock_comment" rows="3"><?= htmlspecialchars($stockData['stock_comment']) ?></textarea>

    <label>
        <input type="checkbox" name="is_leader" <?= $stockData['is_leader'] == '1' ? 'checked' : '' ?>> 주도주 여부
    </label>

    <button type="submit">저장</button>
</form>

</body>
</html>
