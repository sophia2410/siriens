<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>202410 - Dashboard</title>
    <?php
        date_default_timezone_set('Asia/Seoul');

        if($_SERVER["HTTP_HOST"] == 'localhost') {
            $PATH = "http://localhost";
        } else {
            $PATH = "https://yunseul0907.cafe24.com";
        }
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
    
    <!script src="js/tui-grid.js"><!/script>
    <script src="https://uicdn.toast.com/grid/latest/tui-grid.js"></script>
    <script src="<?=$PATH?>/boot/common/js/common.js"></script>

</head>