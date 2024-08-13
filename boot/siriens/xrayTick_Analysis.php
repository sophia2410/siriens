<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$signal_page = (isset($_POST['stock'])) ? $_POST['stock'] : '';
$signal_page = (isset($_POST['stock_nm'])) ? $_POST['stock_nm'] : '';

// 공통코드 불러오기
$status_query = "SELECT cd, nm FROM comm_cd WHERE l_cd = 'HC000' ORDER BY ord_no";
$status_result = $mysqli->query($status_query);
$status_options = "<option value=''>차트 상태</option>";
$comm_codes = [];
while ($status_row = $status_result->fetch_assoc()) {
    $status_options .= "<option value='". $status_row['cd']."'>".$status_row['nm']."</option>";
    $comm_codes[$status_row['cd']] = $status_row['nm']; // 공통코드 배열에 저장
}
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
          flex: 4; /* 비율 2 */
        }
        .right {
          flex: 1; /* 비율 2 */
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

$query = "SELECT date
          FROM calendar
          WHERE date <= (SELECT DATE_FORMAT(now(), '%Y%m%d'))
          AND date >= '20240201'
          ORDER BY date DESC";

$result = $mysqli->query($query);

?>
<div class="flex-container">
    <div class="left">
        &nbsp;
		<select id="search_date" class="select">
        <?php
            $query = "SELECT date, 
                             CASE WHEN B.watchlist_date IS NULL THEN '' ELSE '(Y)' END regi_watchlist
                      FROM calendar A
                      LEFT OUTER JOIN (SELECT watchlist_date FROM daily_watchlist GROUP BY watchlist_date) B
                      ON B.watchlist_date = A.date
                      WHERE date <= (SELECT DATE_FORMAT(now(), '%Y%m%d'))
                      AND date >= '20230101'
                      ORDER BY date DESC
                      LIMIT 350";

            $result = $mysqli->query($query);

            $option = "";
            $i = 0;
            while($row = $result->fetch_array(MYSQLI_BOTH)) {
                // 관종등록일자가 없는 경우는 제일 1행 선택되도록..
                if ($watchlist_date == $row['date']) {
                    $option .= "<option value='". $row['date']."' selected>".$row['date'].$row['regi_watchlist']."</option>";
                } else {
                    $option .= "<option value='". $row['date']."'>".$row['date'].$row['regi_watchlist']."</option>";
                }
                $i++;
            }
            echo $option;
        ?>
		</select>
		<input type=checkbox id='highchartview' checked> Highchart 바로보기 &nbsp;
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','29.5', '0')">상한가</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','20','2000')">20% || 2000억↑</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','0','0')">0일차모음</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('mochaten')">모차십</button>
        <button type="button" class="btn btn-danger btn-sm" id="themeButton">최근테마(관.종)</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('sophiaWatchlist','2 최근0일차☆','')">최근0일차(관.종)</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('xraytick')">조회일자</button> &nbsp; 
        <input type=text id=buy_cnt style='width:30px' value=6>건/<input type=text id=buy_period style='width:30px' value=10>일내
        <input type=checkbox id='0dayview' checked> 0일차포함 &nbsp;
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('buy_streak')">연속매수</button> &nbsp;

        <select id="status_cd" class="select" onchange="xrayTick('chart_status')">
            <?php echo $status_options; ?>
        </select>">연속매수</button> 

        <select id="frequency" class="select" onchange="xrayTick('frequency')">
            <?php echo $status_options; ?>
        </select>

    </div>
    <div class="right">
    <button type="button" class="btn btn-info btn-sm" onclick="comment_save()">코멘트 저장</button> 
        <button type="button" class="btn btn-info btn-sm" id="excel_down">관종엑셀 다운로드</button>
    </div>
</div>
<div id="sectorContainer" class="sector-list" style="display:none;"></div>
<div>
<table style="width:100%; height:100%" class="table text-dark">
    <tr>
        <td colspan=2>
            <div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 80px);">
                <iframe id="iframeB" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 80px);" src="xrayTick_StockList.php">
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
    if(document.getElementById('highchartview').checked == true) highchartview  = 'Y';
    else highchartview  = 'N';

    if(pgmId == '0dayStocks') {
        parm = "&increase_rate=" + key1 + "&trade_amt=" + key2;
    } else if(pgmId == 'mochaten') {
        parm = "";
    } else if(pgmId == 'sophiaWatchlist') {
        parm = "&sector=" + key1 + "&theme=" + key2;
    } else if(pgmId == 'xraytick') {
        parm = "";
    } else if(pgmId == 'buy_streak') {
        buy_cnt    = document.getElementById('buy_cnt').value;
        buy_period = document.getElementById('buy_period').value;
        zeroday_view = document.getElementById('0dayview').checked;
        parm = "&buy_cnt=" + buy_cnt + "&buy_period=" + buy_period + "&zeroday_view=" + zeroday_view;
    } else if(pgmId == 'chart_status'){
        status_cd  = document.getElementById('status_cd').options[document.getElementById("status_cd").selectedIndex].value;
        parm = "&chart_status=" + status_cd;
    } else if(pgmId == 'frequency'){
        frequency  = document.getElementById('frequency').options[document.getElementById("frequency").selectedIndex].value;
        parm = "&frequency=" + frequency;
    }

    if(highchartview == 'Y')
        iframeB.src = "xrayTick_HighchartView.php?pgmId=" + pgmId + "&search_date=" + search_date + parm;
    else
        iframeB.src = "xrayTick_StockList.php?pgmId=" + pgmId + "&search_date=" + search_date + parm;
    return;
}


// 코멘트 저장
function comment_save() {
    document.getElementById("iframeB").contentWindow.saveComment();
	return;
}

</script>

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
