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
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<form id="form">
<!-- Main Content -->

<?php

$query = "SELECT date
          FROM calendar
          WHERE date <= now()
          AND date >= '2024-02-01'
          ORDER BY date DESC";

$result = $mysqli->query($query);

?>
<div class="flex-container">
    <div class="left">
        &nbsp;
        <select id="search_date" class="select">
        <?php
            $query = "SELECT date, 
                             CASE WHEN B.0day_date IS NULL THEN '' ELSE '(Y)' END regi_watchlist
                      FROM calendar A
                      LEFT OUTER JOIN (SELECT 0day_date FROM 0day_stocks GROUP BY 0day_date) B
                      ON B.0day_date = A.date
                      WHERE date <= now()
                      AND date >= '2023-01-01'
                      ORDER BY date DESC
                      LIMIT 350";

            $result = $mysqli->query($query);

            $option = "";
            $i = 0;
            while($row = $result->fetch_array(MYSQLI_BOTH)) {
                $option .= "<option value='". $row['date']."'>".$row['date'].$row['regi_watchlist']."</option>";
                $i++;
            }
            echo $option;
        ?>
        </select>

        <label>
            <input type="radio" name="chartview" id="naverChart" value="NaverChart"> NaverChart
        </label>
        <label>
            <input type="radio" name="chartview" id="highChart" value="HighChart" checked> HighChart
        </label>
        / &nbsp;
        <input type=checkbox id='highchartview'> Highchart 바로보기 &nbsp;
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('IPOstock')">신규주</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','29.5', '0')">상한가</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','20','2000')">20% || 2000억↑</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','0','0')">0일차모음</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('mochaten')">모차십</button>
        <button type="button" class="btn btn-danger btn-sm" id="marketIssuesThemeButton">테마(마켓이슈)</button>
        <button type="button" class="btn btn-danger btn-sm" id="watchlistThemeButton">테마(옵시디언)</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('sophiaWatchlist','1 최근0일차☆','')">0일차(옵시디언)</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('xraytick')">조회일자</button> &nbsp; 
        <input type=text id=buy_cnt style='width:30px' value=6>건/<input type=text id=buy_period style='width:30px' value=10>일내
        <input type=checkbox id='0dayview' checked> 0일차포함 &nbsp;
        <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('buyStreak')">연속매수</button> &nbsp;

        <select id="status_cd" class="select" onchange="xrayTick('chartStatus')">
            <?php echo $status_options; ?>
        </select>

        <!-- 발생빈도를 보려고 한것 같은데.. 일단 보류.. 24.08.31 -->
        <!-- <select id="frequency" class="select" onchange="xrayTick('frequency')">
            <?php echo $status_options; ?>
        </select> -->

    </div>
    <div class="right">
    <button type="button" class="btn btn-info btn-sm" onclick="comment_save()">코멘트 저장</button> 

    <!-- Naver 차트 보기에 기능이 있어 우선 막아둠 24.08.31 -->
    <!-- <button type="button" class="btn btn-info btn-sm" id="excel_down">관종엑셀 다운로드</button> -->
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

$(document).ready(function() {
    // Event listener for the "최근테마(관.종)" button
    $("#watchlistThemeButton").click(function() {
        toggleThemeContainer();
        fetchThemes('watchlist_sophia');
    });

    // Event listener for the "최근테마(마켓이슈)" button
    $("#marketIssuesThemeButton").click(function() {
        toggleThemeContainer();
        fetchThemes('market_issues');
    });
});

// Function to toggle the visibility of the theme container
function toggleThemeContainer() {
    var container = $('#sectorContainer');
    if (container.is(':visible')) {
        container.hide();
    } else {
        container.show();
    }
}

// Function to fetch themes from the specified source
function fetchThemes(source) {
    $.ajax({
        url: '/boot/common/ajax/ajaxGetThemes.php',
        method: 'GET',
        data: { source: source },
        success: function(response) {
            displayThemes(response, source); // Pass the source to the display function
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('Error fetching themes:', textStatus, errorThrown);
        }
    });
}

// Function to display themes as buttons
function displayThemes(themes, source) {
    var container = $('#sectorContainer');
    container.empty(); // Clear existing content

    // Determine the correct parameters for xrayTick based on the source
    var tickType = source === 'market_issues' ? 'marketIssue' : 'sophiaWatchlist';

    themes.forEach(function(theme) {
        var sector = source === 'market_issues' ? theme.type : '2 최근테마';

        var themeButton = $('<button>')
            .addClass('btn btn-sm sector-item')
            .text(theme.name)
            .attr('type', 'button')
            .attr('onclick', `xrayTick('${tickType}', '${sector}', '${theme.name}')`);

        // Apply different styles based on whether the theme is hot or not
        if (source === 'market_issues' && theme.type === 'theme') {
            if (theme.hot_theme === 'Y') {
                themeButton.addClass('btn-danger'); // Red button for hot themes
            } else {
                themeButton.addClass('btn-primary'); // Blue button for regular themes
            }
        } else if (source === 'market_issues' && theme.type === 'sector') {
            themeButton.addClass('btn-secondary'); // Grey button for keywords
        } else if (source === 'watchlist_sophia') {
            themeButton.addClass('btn-primary'); // Blue button for themes in watchlist_sophia
        }

        container.append(themeButton);
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
    } else if(pgmId == 'IPOstock') {
        parm = "";
    } else if(pgmId == 'sophiaWatchlist' || pgmId == 'marketIssue') {
        parm = "&sector=" + key1 + "&theme=" + key2;
    } else if(pgmId == 'xraytick') {
        parm = "";
    } else if(pgmId == 'buyStreak') {
        buy_cnt    = document.getElementById('buy_cnt').value;
        buy_period = document.getElementById('buy_period').value;
        zeroday_view = document.getElementById('0dayview').checked;
        parm = "&buy_cnt=" + buy_cnt + "&buy_period=" + buy_period + "&zeroday_view=" + zeroday_view;
    } else if(pgmId == 'chartStatus'){
        status_cd  = document.getElementById('status_cd').options[document.getElementById("status_cd").selectedIndex].value;
        parm = "&chart_status=" + status_cd;
    } else if(pgmId == 'frequency'){
        frequency  = document.getElementById('frequency').options[document.getElementById("frequency").selectedIndex].value;
        parm = "&frequency=" + frequency;
    }

    // 라디오 버튼의 값에 따라 chartview 변수 설정
    let chartview = '';
    if (document.getElementById('naverChart').checked) {
        chartview = 'NaverChart';
    } else if (document.getElementById('highChart').checked) {
        chartview = 'HighChart';
    }
    // chartview 값에 따라 iframe의 src 설정
    if (chartview === 'NaverChart') {
        iframeB.src = "viewChart.php?pgmId=" + pgmId + "&search_date=" + search_date + parm;
    } else if (highchartview === 'Y') {
        iframeB.src = "xrayTick_HighchartView.php?pgmId=" + pgmId + "&search_date=" + search_date + parm;
    } else {
        iframeB.src = "xrayTick_StockList.php?pgmId=" + pgmId + "&search_date=" + search_date + parm;
    }

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
