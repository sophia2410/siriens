<?php
	// 화면에서 일자 등 정보 클릭 시 해당 일자 그래프를 바로 보는 여러가지 버전을 시도했으나 속도 이슈로 중단. 현재는 팝업이 최선. // 24.06.28
	
	// 종목정보
	$code = isset($_GET['code']) ? $_GET['code'] : '';
	$name = isset($_GET['name']) ? $_GET['name'] : ''; 

	$pageTitle = "실시간 1Month-$name";
	
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
	require("xrayTick_queries.php");
?>

<?php
$pgmId = (isset($_GET['pgmId'])) ? $_GET['pgmId'] : '';

$search_date   = (isset($_GET['search_date'])  ) ? $_GET['search_date']   : date('Y-m-d');
$increase_rate = (isset($_GET['increase_rate'])) ? $_GET['increase_rate'] : 10;
$trade_amt     = (isset($_GET['trade_amt'])    ) ? $_GET['trade_amt'] : 0;

$sector   = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme 	  = (isset($_GET['theme']))  ? $_GET['theme']  : '';
$category = '';

$buy_cnt 	= (isset($_GET['buy_cnt']))     ? $_GET['buy_cnt']    : '';
$buy_period = (isset($_GET['buy_period']))  ? $_GET['buy_period'] : '';
$zeroday_view = (isset($_GET['zeroday_view']))  ? $_GET['zeroday_view'] : '';

$chart_status = (isset($_GET['chart_status']))  ? $_GET['chart_status'] : '';
$frequency = (isset($_GET['frequency']))  ? $_GET['frequency'] : '';

// 시세 정보 불러오기 위한 처리 (실시간 시세를 구해올 수 없어 장중엔 naver 크롤링 데이터 보기)
$query_basedate = getBaseDateQuery($search_date);
$result_basedate = $mysqli->query($query_basedate);
$row = $result_basedate->fetch_array(MYSQLI_BOTH);
$base_date = $row['base_date'];

$result_trade = getTradeQuery($base_date);
$trade_qry = $result_trade['query'];
$trade_table = $result_trade['table'];
?>

<style>
    .flex-view {
        display: flex;
        height: 100vh;
        width: 100vw;
    }
    .left {
        flex: 26;
        overflow-y: auto;
        border-right: 1px solid #ccc;
        height: 100%;
    }
    .middle {
        flex: 58;
        overflow-y: auto;
        border-right: 1px solid #ccc;
        height: 100%;
    }
    .right {
        flex: 17;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .iframe-container {
        flex: 1;
        width: 100%;
        height: 100%;
    }
    iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
</style>

<body>
<?php
if($pgmId == '') {
	echo "<h3>원하는 검색 버튼 선택</h3>";
	$ready = 'N';
} else {
	$result = getQuery($pgmId, $search_date, $increase_rate, $trade_amt, $sector, $theme, $category, $buy_cnt, $buy_period, $zeroday_view, $chart_status, $frequency, $trade_qry, $trade_table, $base_date);
	$query = $result['query'];
	$filename = $result['filename'];
	$file_orderby = $result['file_orderby'];
	// echo "<pre>$query</pre>";
	
	// 특징주 등록을 위한 엑셀파일용 쿼리문, 파이썬 프로그램에서 사용.
	$text =  "xraytick_" . $filename . "\n" . $file_orderby . "\n" . $query;
	file_put_contents('E:/Project/202410/www/pyObsidian/vars_downExcel.txt', $text);
	// echo "<pre>$query</pre>";

	// 특징주 등록을 위한 엑셀파일용 쿼리문, 파이썬 프로그램에서 사용.
	$text =  "xraytick_".$filename. "\n" .$file_orderby. "\n" .$query;
	file_put_contents('E:/Project/202410/www/pyObsidian/vars_downExcel.txt', $text);

	$result1 = $mysqli->query($query);
	$watchlist_data = [];
	
	while ($row = $result1->fetch_assoc()) {
		$watchlist_data[] = $row;
	}
	
	$result1->free();
?>
	
<div class="flex-view">
    <div class="left">
		<table class='table table-sm table-bordered text-dark'>
		<?php
			$pre_group = '';
			foreach ($watchlist_data as $row) {
				// 그룹별 분리표시
				if($pre_group != $row['group_key']) {
					if($pre_group != '') {
						echo "</tr>";
					}
					echo "<tr><td class='table-danger'><b>▷ ".$row['group_key_str']."</b></td></tr>";
				}

				echo "<tr><td>";

					// 전체 HTML 출력
					echo generateStockHtml($row, false, 'customStockLink');

				echo "</td></tr><tr>";

				$pre_group  = $row['group_key'];
				$code = $row['code'];  // 현재 행의 코드 사용
				$zeroday_date = $row['date'];  // 현재 행의 0day일자 사용

				// 종목 이슈 데이터 구해오기 // 0일차 이슈 구해오기
				$today_issue = '';
				$query3 = "SELECT CONCAT('[',A.signal_grp
								, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
								, A.title today_issue
						FROM	signal_evening A
						WHERE	page_date = (select max(date) from calendar where date <= '$zeroday_date')
						AND  page_fg = 'E'
						AND  code =  '$code'" ;

				// 0일차가 아니어도 시그널이브닝에 이슈가 들어오는 경우가 있어, 수정해봄. 더 적합한 데이터로 변경 예정 24.06.29
				$query3 = "SELECT CONCAT(A.page_date
								, ' [',A.signal_grp
								, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
								, A.title today_issue
						FROM	signal_evening A
						WHERE	page_date = (select max(page_date) from signal_evening where page_date <= '$search_date' and code = '$code')
						AND  page_fg = 'E'
						AND  code =  '$code'" ;

				// echo "<pre>$query3</pre>";
				$result3 = $mysqli->query($query3);

				while($row = $result3->fetch_array(MYSQLI_BOTH)) {
					$today_issue = $row['today_theme']." <b>".$row['today_issue']."</b>";
				}

				echo "<td width=60%>$today_issue</td></tr><tr><td>";

				
				echo "</td></tr>";
			}
		?>
        </table>
    </div>
    <div class="middle">
        <div class="iframe-container">
            <iframe id="iframeL" src=""></iframe>
        </div>
    </div>
    <div class="right">
        <div class="iframe-container">
            <iframe id="iframeR" src=""></iframe>
        </div>
    </div>
</div>
<?php
}
?>

<script>
// 종목 선택 시 오른쪽 프레임에 내역 조회
function viewHighchart(code, name) {
	brWidth = window.innerWidth;
	iframeL.src = "xrayTick_Stock_L.php?code="+code+"&name="+name+"&brWidth="+brWidth;
	iframeR.src = "";
	return;
}

// 일자 선택 시 상세 내역 조회
function viewDetail(date, code) {
	brWidth = window.innerWidth;
	iframeR.src = "/boot/common/popup/stock_xray_tick.php?date="+date+"&code="+code+"&brWidth="+brWidth;
	return;
}

function toggleImage(imgId, code) {
    var img = document.getElementById(imgId);
    var candleURL = 'https://ssl.pstatic.net/imgfinance/chart/item/candle/day/' + code + '.png?sidcode=1705826920773';
    var areaURL = 'https://ssl.pstatic.net/imgfinance/chart/item/area/day/' + code + '.png?sidcode=1705826920773';

    if (img.src === candleURL) {
        img.src = areaURL;
    } else {
        img.src = candleURL;
    }
}
</script>
</body>
</html>