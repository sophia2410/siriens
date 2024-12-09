<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $code = $_POST['code'] ?? '';
    $comment = $_POST['comment'] ?? '';
    $comment_date = $_POST['comment_date'] ?? '';
    $comm_cd = $_POST['comm_cd'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    if ($action === 'save' && $code && $comment_date) {
        // 코멘트 저장 (INSERT OR UPDATE)
        $saveQuery = "
            INSERT INTO xraytick_review_comments (code, comment_date, comment)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE comment = VALUES(comment)";
        $saveStmt = $mysqli->prepare($saveQuery);
        $saveStmt->bind_param('sss', $code, $comment_date, $comment);
        $saveStmt->execute();
    } elseif ($action === 'delete' && $code && $comment_date) {
        // 코멘트 삭제
        $deleteQuery = "DELETE FROM xraytick_review_comments WHERE code = ? AND comment_date = ?";
        $deleteStmt = $mysqli->prepare($deleteQuery);
        $deleteStmt->bind_param('ss', $code, $comment_date);
        $deleteStmt->execute();
    }

    // 처리 후 다시 기존 조회 조건으로 리디렉션
    header("Location: xraytick_comment_register.php?start_date=$start_date&end_date=$end_date&comm_cd=$comm_cd");
    exit();
}
?>
