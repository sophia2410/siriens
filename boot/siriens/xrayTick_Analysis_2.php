<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$signal_page = (isset($_POST['stock'])) ? $_POST['stock'] : '';
$signal_page = (isset($_POST['stock_nm'])) ? $_POST['stock_nm'] : '';
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <style>
        .sector-list {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            margin-top: 10px;
        }
        .sector-item {
            padding: 10px;
            margin-right: 10px;
            background-color: #f1f1f1;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
            integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="
            crossorigin="anonymous"></script>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
    
<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<form id="form">
<!-- Main Content -->

<?php

$query = " SELECT date
             FROM calendar
            WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
            AND   date >= '20240201'
            ORDER BY date DESC";

$result = $mysqli->query($query);

?>
<div class="container">
    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','29.5')">상한가</button>
    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','20','2000')">20% || 2000억↑</button>
    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('mochaten')">모차십</button>
    <button type="button" class="btn btn-danger btn-sm" id="themeButton">최근테마(관.종)</button>
        <div id="sectorContainer" class="sector-list"></div>
    </div>
    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('sophiaWatchlist')">최근0일차(관.종)</button>
    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick()">최근일자</button> &nbsp; 
    <input type=text name=buy_cnt style='width:30px' value=3>건/<input type=text name=buy_period style='width:30px' value=4>일내
    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick()">연속매수</button>
    <button type="button" class="btn btn-info btn-sm" id="excel_down">관종엑셀 다운로드</button>
</div>
<table style="width:100%; height:100%" class="table text-dark">
    <tr>
        <td colspan=2>
            <div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 80px);">
                <iframe id="iframeB" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 80px);" src="xrayTick_Date_B.php">
                </iframe>
            </div>
        </td>
    </tr>
</table>
<div id="sectorContainer" class="sector-list"></div>
</form>
</div>
</div>
</body>

<script>
function xrayTick(pgmId, key1, key2) {
    var brWidth = window.innerWidth;
    iframeR.src = "xrayTick_StockList.php?pgmId=" + pgmId + "&key1=" + key1 + "&key2=" + key2 + "&brWidth=" + brWidth;
    return;
}

$("#excel_down").click(function() {
    $.ajax({
        method: "POST",
        url: "../watchlist/viewChart_runPy.php",
        data: {downfile: "excel"}
    })
    .done(function(result) {
        alert('다운로드 완료!');
    });
});

$("#themeButton").click(function() {
    fetchSectors();
});

function fetchSectors() {
    console.log('Fetching sectors...');
    $.ajax({
        url: 'ajaxGetThemes.php', // 섹터 데이터를 가져오는 PHP 파일
        method: 'GET',
        success: function(response) {
            console.log('Sectors fetched successfully:', response);
            displaySectors(response);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('Error fetching sectors:', textStatus, errorThrown);
        }
    });
}

function displaySectors(sectors) {
    console.log('Displaying sectors:', sectors);
    var container = $('#sectorContainer');
    container.empty(); // 기존 섹터 목록을 지웁니다.

    sectors.forEach(function(sector) {
        var sectorElement = $('<div>')
            .addClass('sector-item')
            .text(sector.name)
            .data('sector-id', sector.id)
            .click(function() {
                selectSector(sector.id, sector.name);
            });
        
        container.append(sectorElement);
    });
}

function selectSector(id, name) {
    console.log('Selected Sector ID: ' + id + ', Name: ' + name);
    // 선택된 섹터에 대한 추가 작업을 여기에서 수행할 수 있습니다.
}

</script>

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
