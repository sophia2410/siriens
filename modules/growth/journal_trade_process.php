<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trade_date = $mysqli->real_escape_string($_POST['trade_date']);
    $trade_items = $mysqli->real_escape_string($_POST['trade_items']);
    $type = $mysqli->real_escape_string($_POST['type']);

    $comment = $mysqli->real_escape_string($_POST['comment']);
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    // POST로 전송된 검색 조건
    $searchStockName = isset($_POST['search_stock_name']) ? $mysqli->real_escape_string($_POST['search_stock_name']) : '';
    $searchType = isset($_POST['search_type']) ? $mysqli->real_escape_string($_POST['search_type']) : '';

    // 하위 데이터 처리
    $details = isset($_POST['details']) ? $_POST['details'] : [];

    // 수정하는 경우
    if (!empty($_POST['journal_id'])) {
        $journalId = $mysqli->real_escape_string($_POST['journal_id']);

        // 상위 데이터 수정
        $updateQuery = "
            UPDATE journal_trade 
            SET trade_date = '$trade_date', type = '$type', trade_items = '$trade_items', comment = COMPRESS('$comment')
            WHERE id = '$journalId'";
        $mysqli->query($updateQuery);

        // 하위 데이터 처리
        $mysqli->query("DELETE FROM journal_trade_details WHERE journal_id = '$journalId'"); // 기존 세부 항목 삭제
        foreach ($details as $detail) {
            $profit_loss = $mysqli->real_escape_string($detail['profit_loss']);
            $trade_method = $mysqli->real_escape_string($detail['trade_method']);
        
            $detailInsertQuery = "
                INSERT INTO journal_trade_details (journal_id, profit_loss, trade_method) 
                VALUES ('$journalId', '$profit_loss', '$trade_method')";
            $mysqli->query($detailInsertQuery);
        }
    } 
    // 새로운 매매/복기 등록하는 경우
    else {
        // 상위 데이터 추가
        $insertQuery = "
            INSERT INTO journal_trade (trade_date, type, trade_items, comment) 
            VALUES ('$trade_date', '$type', '$trade_items', COMPRESS('$comment'))";
        $mysqli->query($insertQuery);

        $journalId = $mysqli->insert_id; // 새로 생성된 journal_id 가져오기

        // 하위 데이터 추가
        foreach ($details as $detail) {
            $profit_loss = $mysqli->real_escape_string($detail['profit_loss']);
            $trade_method = $mysqli->real_escape_string($detail['trade_method']);

            $detailInsertQuery = "
                INSERT INTO journal_trade_details (journal_id, profit_loss, trade_method) 
                VALUES ('$journalId', '$profit_loss', '$trade_method')";
            $mysqli->query($detailInsertQuery);
        }
    }

    // 처리 완료 후 검색 조건과 함께 리다이렉트
    header("Location: journal_trade_register.php?trade_date=$trade_date&type=$type&trade_method=$trade_method&stock_name=$searchStockName&search_type=$searchType&search_method=$searchMethod&search_result=$searchResult&page=" . $page);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['journal_id'])) {
        $journalId = $mysqli->real_escape_string($_GET['journal_id']);

        // 상위 및 하위 데이터 삭제
        $mysqli->query("DELETE FROM journal_trade_details WHERE journal_id = '$journalId'");
        $mysqli->query("DELETE FROM journal_trade WHERE id = '$journalId'");

        // GET으로 받은 검색 조건
        $trade_date = isset($_GET['trade_date']) ? $_GET['trade_date'] : date('Y-m-d');
        $searchStockName = isset($_GET['stock_name']) ? $mysqli->real_escape_string($_GET['stock_name']) : '';
        $searchType = isset($_GET['search_type']) ? $mysqli->real_escape_string($_GET['search_type']) : '';

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        header("Location: journal_trade_register.php?trade_date=$trade_date&stock_name=$searchStockName&search_type=$searchType&search_method=$searchMethod&search_result=$searchResult&page=" . $page);
    }
}
?>