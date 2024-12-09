
<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trade_date = $mysqli->real_escape_string($_POST['trade_date']);
    $trade_items = $mysqli->real_escape_string($_POST['trade_items']);
    $trade_method = $mysqli->real_escape_string($_POST['trade_method']);
    $type = $mysqli->real_escape_string($_POST['type']);

    // profit_loss 값 유효성 검사 추가
    $validProfitLossValues = ['profit', 'loss'];
    $profit_loss = in_array($_POST['profit_loss'], $validProfitLossValues) ? $_POST['profit_loss'] : 'profit';
    
    $comment = $mysqli->real_escape_string($_POST['comment']);
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    // POST로 전송된 검색 조건
    $searchStockName = isset($_POST['search_stock_name']) ? $mysqli->real_escape_string($_POST['search_stock_name']) : '';
    $searchType = isset($_POST['search_type']) ? $mysqli->real_escape_string($_POST['search_type']) : '';
    $searchMethod = isset($_POST['search_method']) ? $mysqli->real_escape_string($_POST['search_method']) : '';
    $searchResult = isset($_POST['search_result']) ? $mysqli->real_escape_string($_POST['search_result']) : '';

    // 수정하는 경우
    if (!empty($_POST['journal_id'])) {
        $journalId = $mysqli->real_escape_string($_POST['journal_id']);
        $updateQuery = "UPDATE journal_trade 
                        SET trade_date = '$trade_date', type = '$type', trade_method = '$trade_method', profit_loss = '$profit_loss', trade_items = '$trade_items', comment = COMPRESS('$comment')
                        WHERE id = '$journalId'";
        $mysqli->query($updateQuery);
    } 
    // 새로운 매매/복기 등록하는 경우
    else {
        $insertQuery = "INSERT INTO journal_trade (trade_date, type, trade_method, profit_loss, trade_items, comment) 
                        VALUES ('$trade_date', '$type', '$trade_method', '$profit_loss', '$trade_items', COMPRESS('$comment'))";
        $mysqli->query($insertQuery);
    }

    // 처리 완료 후 검색 조건과 함께 리다이렉트
    header("Location: journal_trade_register.php?trade_date=$trade_date&type=$type&trade_method=$trade_method&stock_name=$searchStockName&search_type=$searchType&search_method=$searchMethod&search_result=$searchResult&page=" . $page);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['journal_id'])) {
        $journalId = $mysqli->real_escape_string($_GET['journal_id']);
        $deleteQuery = "DELETE FROM journal_trade WHERE id = '$journalId'";
        $mysqli->query($deleteQuery);

        // GET으로 받은 검색 조건
        $trade_date = isset($_GET['trade_date']) ? $_GET['trade_date'] : date('Y-m-d');
        $searchStockName = isset($_GET['stock_name']) ? $mysqli->real_escape_string($_GET['stock_name']) : '';
        $searchType = isset($_GET['search_type']) ? $mysqli->real_escape_string($_GET['search_type']) : '';
        $searchMethod = isset($_POST['search_method']) ? $mysqli->real_escape_string($_POST['search_method']) : '';
        $searchResult = isset($_GET['search_status']) ? $mysqli->real_escape_string($_GET['search_status']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        header("Location: journal_trade_register.php?trade_date=$trade_date&stock_name=$searchStockName&search_type=$searchType&search_method=$searchMethod&search_result=$searchResult&page=" . $page);
    }
}
?>