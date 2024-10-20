<?php
// DB 연결
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stock_name = $_POST['stock_name'];
    $stock_code = $_POST['stock_code'];
    $buy_date = $_POST['buy_date'];
    $sell_date = $_POST['sell_date'];

    $sql = "INSERT INTO stock_trades (stock_name, stock_code, buy_date, sell_date) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssss", $stock_name, $stock_code, $buy_date, $sell_date);

    if ($stmt->execute()) {
        // 매수일자 기준으로 년월을 구하여 리디렉션
        $yearMonth = date('Y-m', strtotime($buy_date));
        header("Location: trade_register.php?message=등록 완료&yearMonth=$yearMonth");
        exit;
    } else {
        echo "등록 실패: " . $mysqli->error;
    }
}
?>
