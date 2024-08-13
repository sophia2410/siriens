<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $url = $_POST['url'];
    $theme = $_POST['theme'];

    // 뉴스 저장 쿼리 (news 테이블이 있어야 합니다.)
    $stmt = $mysqli->prepare("INSERT INTO news (title, url, theme, create_dtime) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('sss', $title, $url, $theme);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>
