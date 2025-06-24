<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : null;
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;

    $stock_name = isset($_POST['stock_name']) ? $_POST['stock_name'] : '';
    $stock_code = isset($_POST['stock_code']) ? $_POST['stock_code'] : '';
    $buy_date = isset($_POST['buy_date']) ? $_POST['buy_date'] : '';
    $sell_date = isset($_POST['sell_date']) ? $_POST['sell_date'] : '';
    $profit_loss = isset($_POST['profit_loss']) ? $_POST['profit_loss'] : '수익'; // 기본값을 '수익'으로 설정
    $comment = isset($_POST['comment']) ? $_POST['comment'] : ''; // 코멘트 값 추가

    // 등록 및 수정 시 매수일자 기반으로 yearMonth를 설정
    $yearMonth = !empty($buy_date) ? date('Y-m', strtotime($buy_date)) : date('Y-m');

    // 등록 처리
    if ($action === 'register') {
        if (!empty($stock_name) && !empty($stock_code) && !empty($buy_date) && !empty($sell_date)) {
            $query = "INSERT INTO trade_stocks (name, code, buy_date, sell_date, profit_loss, comment) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('ssssss', $stock_name, $stock_code, $buy_date, $sell_date, $profit_loss, $comment);
            
            if ($stmt->execute()) {
                header("Location: trade_register.php?yearMonth=$yearMonth");
                exit(); // 헤더 전송 후 스크립트 종료
            } else {
                error_log("등록 실패: " . $stmt->error); // 에러 로그 작성
            }
        } else {
            error_log("모든 필드를 입력해주세요.");
        }

    // 수정 처리
    } elseif ($action === 'update' && $id) {
        if (!empty($stock_name) && !empty($stock_code) && !empty($buy_date) && !empty($sell_date)) {
            $query = "UPDATE trade_stocks SET name = ?, code = ?, buy_date = ?, sell_date = ?, profit_loss = ?, comment = ? WHERE id = ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('ssssssi', $stock_name, $stock_code, $buy_date, $sell_date, $profit_loss, $comment, $id);

            if ($stmt->execute()) {
                header("Location: trade_register.php?yearMonth=$yearMonth");
                exit(); // 헤더 전송 후 스크립트 종료
            } else {
                error_log("수정 실패: " . $stmt->error); // 에러 로그 작성
            }
        } else {
            error_log("모든 필드를 입력해주세요.");
        }

    // 삭제 처리
    } elseif ($action === 'delete' && $id) {
        // 삭제 전에 삭제할 데이터의 매수일자 가져와서 yearMonth 설정
        $select_query = "SELECT buy_date FROM trade_stocks WHERE id = ?";
        $stmt = $mysqli->prepare($select_query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $trade_data = $result->fetch_assoc();

        // 삭제할 종목의 매수일자가 존재하면 해당 월을 기반으로 yearMonth 설정
        if ($trade_data && !empty($trade_data['buy_date'])) {
            $yearMonth = date('Y-m', strtotime($trade_data['buy_date']));
        }

        // 삭제 처리
        $query = "DELETE FROM trade_stocks WHERE id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            header("Location: trade_register.php?yearMonth=$yearMonth");
            exit(); // 헤더 전송 후 스크립트 종료
        } else {
            error_log("삭제 실패: " . $stmt->error); // 에러 로그 작성
        }
    }

    // stmt가 정의된 경우에만 close 호출
    if (isset($stmt)) {
        $stmt->close();
    }
}
