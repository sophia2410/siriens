<!DOCTYPE html>
<html lang="ko">
<head>
    <?php
    require($_SERVER['DOCUMENT_ROOT']."/modules/common/futures_nav_menu.php"); // 네비게이션 메뉴
    require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php"); // 공통 유틸리티 함수
    require($_SERVER['DOCUMENT_ROOT']."/modules/common/utility.php"); // 공통 유틸리티 함수

    date_default_timezone_set('Asia/Seoul');
    ?>

    <meta charset="UTF-8">
    <title><?php echo isset($pageTitle) ? $pageTitle : '기본 타이틀'; ?></title>
</head>
<!-- body 태그는 개별 페이지에서 시작 -->
