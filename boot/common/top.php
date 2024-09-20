<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <?php
        if(isset($pageTitle))
            echo "<title>$pageTitle</title>";
        else
            echo "<title>202410 - Sophia Report</title>";

        if($_SERVER["HTTP_HOST"] == 'localhost') {
            $PATH = "http://localhost";
        } else {
            $PATH = "https://siriens.mycafe24.com";
        }

        date_default_timezone_set('Asia/Seoul');
    ?>
    <!-- Custom fonts for this template-->
    <link href="<?=$PATH?>/boot/common/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="<?=$PATH?>/boot/common/css/sb-admin-2.min.css" rel="stylesheet">
    <!link href="<?=$PATH?>/boot/common/css/tui-grid.css" rel="stylesheet">
    <link rel="stylesheet" href="https://uicdn.toast.com/grid/latest/tui-grid.css" />

    <!-- 메뉴바 아이콘 위한 스타일시트 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    
    <!script src="js/tui-grid.js"><!/script>
    <script src="https://uicdn.toast.com/grid/latest/tui-grid.js"></script>
    <script src="<?=$PATH?>/boot/common/js/common.js"></script>
    <style>
    /* modules 의 메뉴바를 쓰기 위해 임의로 컨테이너 margin 설정 */
    #content-wrapper {
        margin-left: 100px !important;
        width: calc(100% - 100px) !important;
    }
    </style>

</head>