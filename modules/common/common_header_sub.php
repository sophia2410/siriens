<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php"); // 공통 DB 함수
require($_SERVER['DOCUMENT_ROOT']."/modules/common/utility.php");  // 공통 유틸리티 함수
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($pageTitle) ? $pageTitle : '기본 타이틀'; ?></title>
	<link rel="stylesheet" href="https://require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400&display=swap"><!-- Google Fonts 링크 -->
    <link rel="stylesheet" href="/modules/common/common.css">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
</head>
<!-- body 태그는 개별 페이지에서 시작 -->
