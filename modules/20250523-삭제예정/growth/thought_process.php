<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST로 받은 데이터 처리
    $category = $mysqli->real_escape_string($_POST['category']);
    $thought = $mysqli->real_escape_string($_POST['thought']);
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;

    // 생각을 새로 등록하는 경우
    if (empty($_POST['thought_id'])) {
        $insertQuery = "INSERT INTO thoughts (category_cd, thought_text) VALUES ('$category', '$thought')";
        $mysqli->query($insertQuery);
    } 
    // 기존 생각을 수정하는 경우
    else {
        $thoughtId = $mysqli->real_escape_string($_POST['thought_id']);
        $updateQuery = "UPDATE thoughts SET category_cd = '$category', thought_text = '$thought' WHERE id = '$thoughtId'";
        $mysqli->query($updateQuery);
    }

    // 처리 완료 후 선택된 카테고리와 페이지로 리다이렉트
    header("Location: thought_register.php?category=" . urlencode($category) . "&page=" . $page);
} 
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    // GET으로 삭제 요청을 처리하는 경우
    if ($_GET['action'] == 'delete' && isset($_GET['thought_id'])) {
        $thoughtId = $mysqli->real_escape_string($_GET['thought_id']);
        $deleteQuery = "DELETE FROM thoughts WHERE id = '$thoughtId'";
        $mysqli->query($deleteQuery);

        // 삭제 후 선택된 카테고리와 페이지로 리다이렉트
        $category = isset($_GET['category']) ? $_GET['category'] : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        header("Location: thought_register.php?category=" . urlencode($category) . "&page=" . $page);
    }
}
?>
