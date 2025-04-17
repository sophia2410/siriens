<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

// 호출한 곳을 구분하는 변수 (폼의 hidden 필드로 전송됨)
// 기본값은 'iframe'
$source = isset($_REQUEST['source']) ? $_REQUEST['source'] : 'iframe';

// 리다이렉트 URL을 저장할 변수
$redirectURL = "";

// ------------------------------
// POST 처리 (등록/수정)
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) POST로 넘어온 값 받기
    $journalId    = isset($_POST['journal_id']) ? $mysqli->real_escape_string($_POST['journal_id']) : '';
    $trade_date   = $mysqli->real_escape_string($_POST['trade_date']);
    $trade_items  = $mysqli->real_escape_string($_POST['trade_items']);
    $trade_method = $mysqli->real_escape_string($_POST['trade_method']);
    $profit_loss  = $mysqli->real_escape_string($_POST['profit_loss']);
    $comment      = $mysqli->real_escape_string($_POST['comment']);

    // 2) 수정 vs 등록 분기
    if (!empty($journalId)) {
        // 수정 로직
        $updateQuery = "
            UPDATE journal_trade
            SET
                trade_date    = '$trade_date',
                trade_items   = '$trade_items',
                trade_method  = '$trade_method',
                profit_loss   = '$profit_loss',
                comment       = COMPRESS('$comment')
            WHERE id = '$journalId'
        ";
        $mysqli->query($updateQuery);
    } else {
        // 등록 로직
        $insertQuery = "
            INSERT INTO journal_trade
                (trade_date, trade_items, trade_method, profit_loss, comment)
            VALUES
                ('$trade_date', '$trade_items', '$trade_method', '$profit_loss', COMPRESS('$comment'))
        ";
        $mysqli->query($insertQuery);
        $journalId = $mysqli->insert_id;
    }

    // ------------------------------
    // GET 처리 (삭제)
    // ------------------------------
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $trade_date = isset($_GET['trade_date']) ? $_GET['trade_date'] : date('Y-m-d');

    if ($_GET['action'] == 'delete' && isset($_GET['journal_id'])) {
        $journalId = $mysqli->real_escape_string($_GET['journal_id']);
        $mysqli->query("DELETE FROM journal_trade WHERE id = '$journalId'");
    }
}

// ------------------------------
// 단일 리다이렉트 함수
// ------------------------------
function redirectBySource($source, $tradeDate) {
    if ($source === 'popup') {
        $redirectURL = "journal_trade_popup.php?trade_date=$tradeDate&mode=popup";
        echo "<script>
            // alert('등록/수정 완료 (팝업)!');
            location.href = '$redirectURL';
        </script>";
    } else if ($source === 'iframe') {
        // iframe 모드: 부모 창을 새로고침하고, iframe은 그대로 유지

        $searchMonth = substr($tradeDate, 0, 7);
        $redirectURL = "journal_trade_register.php?search_month=$searchMonth&trade_date=$tradeDate";

        echo "<script>
            // alert('등록/수정 완료 (iframe)!');
            parent.location.href = '$redirectURL';
        </script>";
    } else {
        // 기본: 메인 페이지로 리다이렉트
        header("Location: blank");
    }
    exit;
}

// ------------------------------
// 최종 리다이렉트 호출
// ------------------------------
redirectBySource($source, $trade_date);
?>
