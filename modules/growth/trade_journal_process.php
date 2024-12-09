
<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stock_code = $mysqli->real_escape_string($_POST['stock_code']);
    $trade_date = $mysqli->real_escape_string($_POST['trade_date']);
    $type = $mysqli->real_escape_string($_POST['type']);
    $status = $mysqli->real_escape_string($_POST['status']);
    $comment = $mysqli->real_escape_string($_POST['comment']);
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    // POST로 전송된 검색 조건
    $searchStockName = isset($_POST['search_stock_name']) ? $mysqli->real_escape_string($_POST['search_stock_name']) : '';
    $searchType = isset($_POST['search_type']) ? $mysqli->real_escape_string($_POST['search_type']) : '';
    $searchStatus = isset($_POST['search_status']) ? $mysqli->real_escape_string($_POST['search_status']) : '';

    // 수정하는 경우
    if (!empty($_POST['journal_id'])) {
        $journalId = $mysqli->real_escape_string($_POST['journal_id']);
        $updateQuery = "UPDATE trade_journal 
                        SET code = '$stock_code', trade_date = '$trade_date', type = '$type', status = '$status', comment = COMPRESS('$comment')
                        WHERE id = '$journalId'";
        $mysqli->query($updateQuery);
    } 
    // 새로운 매매/복기 등록하는 경우
    else {
        $insertQuery = "INSERT INTO trade_journal (code, trade_date, type, status, comment) 
                        VALUES ('$stock_code', '$trade_date', '$type', '$status', COMPRESS('$comment'))";
        $mysqli->query($insertQuery);
    }

    // 처리 완료 후 검색 조건과 함께 리다이렉트
    header("Location: trade_journal_register.php?trade_date=$trade_date&type=$type&stock_name=$searchStockName&search_type=$searchType&search_status=$searchStatus&page=" . $page);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['journal_id'])) {
        $journalId = $mysqli->real_escape_string($_GET['journal_id']);
        $deleteQuery = "DELETE FROM trade_journal WHERE id = '$journalId'";
        $mysqli->query($deleteQuery);

        // GET으로 받은 검색 조건
        $trade_date = isset($_GET['trade_date']) ? $_GET['trade_date'] : date('Y-m-d');
        $type = isset($_GET['type']) ? $_GET['type'] : 'trade';
        $status = isset($_GET['status']) ? $_GET['status'] : 'normal';
        $searchStockName = isset($_GET['stock_name']) ? $mysqli->real_escape_string($_GET['stock_name']) : '';
        $searchType = isset($_GET['search_type']) ? $mysqli->real_escape_string($_GET['search_type']) : '';
        $searchStatus = isset($_GET['search_status']) ? $mysqli->real_escape_string($_GET['search_status']) : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        header("Location: trade_journal_register.php?trade_date=$trade_date&type=$type&stock_name=$searchStockName&search_type=$searchType&search_status=$searchStatus&page=" . $page);
    }
}
?>