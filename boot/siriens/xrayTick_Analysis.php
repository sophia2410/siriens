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
            padding: 5px;
            margin-right: 10px;
            background-color: silver;
            border-radius: 5px;
            cursor: pointer;
        }
        .flex-container {
          display: flex;
        }
        .left {
          flex: 3; /* 비율 3 */
          background-color: #f2f2f2; /* 배경색은 예시로 추가한 것입니다 */
        }
        .right {
          flex: 2; /* 비율 1 */
          background-color: #d9d9d9; /* 배경색은 예시로 추가한 것입니다 */
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
<div class="flex-container">
    <div class="left">
        &nbsp;
		<select id="search_date" class="select">
        <?php
            $query = " SELECT date
                            , CASE WHEN B.watchlist_date is null THEN '' ELSE '(Y)' END regi_watchlist
                        FROM calendar A
                        LEFT OUTER JOIN (SELECT watchlist_date FROM daily_watchlist GROUP BY watchlist_date) B
                        ON B.watchlist_date = A.date
                        WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
                        AND   date >= '20230101'
                        ORDER BY date DESC
                        LIMIT 350";

            $result = $mysqli->query($query);

            $option = "";
            $i=0;
            while($row = $result->fetch_array(MYSQLI_BOTH)) {
                // 관종등록일자가 없는 경우는 제일 1행 선택되도록..
                if($watchlist_date == $row['date']) {
                    $option .= "<option value='". $row['date']."' selected>".$row['date'].$row['regi_watchlist']."</option>";
                } else {
                    $option .= "<option value='". $row['date']."'>".$row['date'].$row['regi_watchlist']."</option>";
                }
                $i++;
            }
            echo $option;
        ?>
		</select>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','29.5', '0')">상한가</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','20','2000')">20% || 2000억↑</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('mochaten')">모차십</button>
        <button type="button" class="btn btn-danger btn-sm" id="themeButton">최근테마(관.종)</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('sophiaWatchlist','2 최근0일차☆','')">최근0일차(관.종)</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('xraytick')">최근일자</button> &nbsp; 
        <input type=text id=buy_cnt style='width:30px' value=3>건/<input type=text id=buy_period style='width:30px' value=4>일내
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('buy_streak')">연속매수</button> 
        </div>
    <div class="right">
        <button type="button" class="btn btn-info btn-sm" id="excel_down">관종엑셀 다운로드</button>
    </div>
</div>
<div id="sectorContainer" class="sector-list" style="display:none;"></div>
<div>
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
</div>
<div id="sectorContainer" class="sector-list"></div>
</form>
</div>
</div>
</body>

<script>
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
    var container = $('#sectorContainer');
    if (container.is(':visible')) {
        container.hide(); // 컨테이너가 보이면 숨깁니다.
    } else {
        container.show(); // 컨테이너가 숨겨져 있으면 보이게 합니다.
        fetchSectors();
    }
});

function fetchSectors() {
    console.log('Fetching sectors...');
    $.ajax({
        url: '/boot/common/ajax/ajaxGetThemes.php', // 섹터 데이터를 가져오는 PHP 파일
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
        var sectorButton = $('<button>')
            .addClass('btn btn-secondary btn-sm sector-item')
            .text(sector.name)
            .attr('type', 'button')
            .attr('onclick', `xrayTick('sophiaWatchlist', '1 최근테마☆', '${sector.name}')`);

        container.append(sectorButton);
    });
}

function selectSector(id, name) {
    console.log('Selected Sector ID: ' + id + ', Name: ' + name);
    // 선택된 섹터에 대한 추가 작업을 여기에서 수행할 수 있습니다.
}

// 순간체결 데이터 조회하기
function xrayTick(pgmId, key1='', key2='') {
	search_date  = document.getElementById('search_date').options[document.getElementById("search_date").selectedIndex].value;
    if(pgmId == '0dayStocks') {
        parm = "&search_date=" + search_date + "&increase_rate=" + key1 + "&trade_amt=" + key2;
    } else if(pgmId == 'mochaten') {
        parm = "&search_date=" + search_date;
    } else if(pgmId == 'sophiaWatchlist') {
        parm = "&sector=" + key1 + "&theme=" + key2;
    } else if(pgmId == 'xraytick') {
        parm = "&search_date=" + search_date;
    } else if(pgmId == 'buy_streak') {
        buy_cnt    = document.getElementById('buy_cnt').value;
        buy_period = document.getElementById('buy_period').value;
        parm = "&search_date=" + kesearch_datey1 + "&buy_cnt=" + buy_cnt + "&buy_period=" + buy_period;
    }

    iframeB.src = "xrayTick_StockList.php?pgmId=" + pgmId + parm;
    return;
}

</script>

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
