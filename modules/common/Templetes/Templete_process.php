<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST로 받은 데이터 처리
    $category = $mysqli->real_escape_string($_POST['category']);

    // 생각을 새로 등록하는 경우
    if (empty($_POST['thought_id'])) {
        $insertQuery = "INSERT INTO thoughts (category_cd, thought_text) VALUES ('$category', '$thought')";
        $mysqli->query($insertQuery);
    } 
}
?>
