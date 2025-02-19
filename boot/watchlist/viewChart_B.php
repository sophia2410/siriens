<?php
// 차트 보기 화면. 각 화면에서 링크 됨. 화면 ID에 따라 분기 처리
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
require("xrayTick_queries.php");


// xraytick 조회 위해 market 모듈 참조하기.. 25.02.18
require($_SERVER['DOCUMENT_ROOT']."/modules/common/utility.php");  // 공통 유틸리티 함수


//var_dump($_SERVER);
?>

<head>
	
<!-- xraytick 조회 위해 market 모듈 참조하기.. 25.02.18 -->
<link rel="stylesheet" href="/modules/common/common.css">

<style>
	html {
		overflow: auto; 
		height: 100%;
	}

	body {
		overflow: auto;
		overflow-x:hidden;
		height: 100%;
	}
	table th, tr, td{
		padding: 0.1rem;
	}

	.recent-price-section {
		background-color: #fff;
		padding: 10px;
		border: 1px solid #ddd;
		margin-top: 10px;
		overflow-x: auto;
	}

	.small-table th, .small-table td {
		width: 90px;
		text-align: center;
		white-space: nowrap;
		font-size: 14px;
	}

	/* 가로 스크롤 가능하도록 설정 */
	.scrollable-x-content {
		overflow-x: auto;
		white-space: nowrap;
		max-width: 100%;
	}
</style>
</head>

<?php
$pgmId = (isset($_GET['pgmId'])) ? $_GET['pgmId'] : '';

$search_date   = (isset($_GET['search_date'])  ) ? $_GET['search_date'] : '';
$increase_rate = (isset($_GET['increase_rate'])) ? $_GET['increase_rate'] : 10;
$trade_amt     = (isset($_GET['trade_amt'])    ) ? $_GET['trade_amt'] : 0;

$sector   = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme 	  = (isset($_GET['theme']))  ? $_GET['theme']  : '';
$category = (isset($_GET['category'])) ? $_GET['category'] : '';

$buy_cnt 	= (isset($_GET['buy_cnt']))     ? $_GET['buy_cnt']    : '';
$buy_period = (isset($_GET['buy_period']))  ? $_GET['buy_period'] : '';
$zeroday_view = (isset($_GET['zeroday_view']))  ? $_GET['zeroday_view'] : '';

$chart_status = (isset($_GET['chart_status']))  ? $_GET['chart_status'] : '';
$frequency = (isset($_GET['frequency']))  ? $_GET['frequency'] : '';

$plus_xray = (isset($_GET['plus_xray'])) ? $_GET['plus_xray'] : 'N';

$show4 = (isset($_GET['show4'])) ? $_GET['show4'] : '';

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';

// 차트 이미지 경로 지정. 기본은 일차트 조회
$viewFg = (isset($_GET['viewFg'])) ? $_GET['viewFg'] : 'day';

switch ($viewFg) {
	case "day"	   : $chart_url = "candle/day/";	break;
	case "week"    : $chart_url = "candle/week/";	break;
	case "month"   : $chart_url = "candle/month/";	break;
	case "oneday"  : $chart_url = "area/day/";		break;
	case "oneweek" : $chart_url = "area/week/";		break;
	default : $chart_url = "";	break;
}

// 시세 정보 불러오기 위한 처리 (실시간 시세를 구해올 수 없어 장중엔 naver 크롤링 데이터 보기)
$query_basedate = getBaseDateQuery($search_date);
$result_basedate = $mysqli->query($query_basedate);
$row = $result_basedate->fetch_array(MYSQLI_BOTH);
$base_date = $row['base_date'];

$result_trade = getTradeQuery($base_date);
$trade_qry = $result_trade['query'];
$trade_table = $result_trade['table'];

if($show4 == 'Y') {
	$col_st = "col-xl-3";
	$row_div = 4;
} else {
	$col_st = "col-xl-4";
	$row_div = 3;
}

$col_st = "col-xl-3";
$row_div = 4;
?>

<body>
<?php
if($pgmId == ''){
	echo "<h3></h3>";
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

	$j=0;
	$pre_group = '';
	foreach ($watchlist_data as $row) {
		// 그룹별 분리표시
		if($pre_group != $row['group_key']) {
			if($pre_group != '') {
				echo "</div>";
				$j=0;
			}
			echo "<div id='" . $row['group_key'] . "' class='h4 font-weight-bold text-gray-800' style='margin:0px; margin-top:10px; margin-bottom:15px; width:100%; height:40px;'><hr class='table-danger'>▷ ".$row['group_key']."</div>";
			echo "<div class='row' style='margin-left:10px'> ";
		}

		echo "<div class='col-xl-3 col-md-6 mb-4' style='margin: 0;'>";

		// 전체 HTML 출력
		echo generateStockHtml($row, false, 'commonStockLink', $chart_url);

		if($plus_xray == 'Y') {

			// 최근 등락률 및 거래대금 추가 25.02.18
			echo '<div class="recent-price-section">
					<div class="scrollable-x-content">
						<table class="small-table" border=1>
							<thead>
								<tr>';

			// 종목 코드 가져오기
			$code = htmlspecialchars($row['code']);
			// 등록일 기준 최근 12 거래일 데이터 조회
			$query = "
				SELECT DATE_FORMAT(dp.date, '%m-%d') AS mm_dd, dp.close_rate, 
					dp.high_rate, dp.low_rate, ROUND(dp.amount / 100000000, 0) AS trade_amount
				FROM daily_price dp
				JOIN (SELECT date FROM calendar WHERE date <= '$search_date' 
					ORDER BY date DESC LIMIT 8) cal
				ON dp.date = cal.date
				WHERE dp.code = '{$row['code']}'
				ORDER BY dp.date DESC";

			$result = $mysqli->query($query);
			$recent_changes = $result->fetch_all(MYSQLI_ASSOC);

			foreach ($recent_changes as $change) {
				echo "<th>{$change['mm_dd']}</th>";
			}

			echo '</tr></thead><tbody><tr>';

			foreach ($recent_changes as $change) {
				echo "<td class=\"" . Utility_GetCloseRateClass($change['close_rate']) . "\">
					{$change['close_rate']}%</td>";
			}

			echo '</tr><tr>';

			foreach ($recent_changes as $change) {
				echo "<td style='font-size:11px'>{$change['high_rate']} / {$change['low_rate']}</td>";
			}

			echo '</tr><tr>';

			foreach ($recent_changes as $change) {
				echo "<td style='text-align: right;' class=\"" . Utility_GetAmountClass($change['trade_amount']) . "\">
					" . number_format($change['trade_amount']) . "억</td>";
			}

			echo '</tr></tbody></table></div></div>';
		}

		echo "</div>";

		$j++;

		if($j%$row_div == 0) {
			echo "</div>";
			echo "<div class='row' style='margin-left:10px'>";
		}

		$pre_group  = $row['group_key'];
	}
	echo "</div>";
}
?>
<script type="text/javascript">
// 버튼을 클릭할 때마다 호출할 함수를 정의합니다.
function changeCondition(newCondition) {
    // 현재 페이지의 URL을 가져옵니다.
    var url = window.location.href;

    // URL에 조회 조건이 있으면 제거합니다.
    if (url.indexOf("&viewFg=") != -1) {
        url = url.substring(0, url.indexOf("&viewFg="));
    }
    // URL에 새로운 조회 조건을 추가합니다.
    url = url + "&viewFg=" + newCondition;
	// 자기 페이지를 새로운 URL로 리로드합니다.
    window.location.href = url;
}

// 종목명 드래그앤드랍
let spans = document.querySelectorAll('.draggable'); 

spans.forEach(span => {
  span.ondragstart = function(e) {
    e.dataTransfer.setData('text', e.target.innerText);
  }; 
});
</script>
<script>

$("#excel_down").click(function() {
	$.ajax({
	method: "POST",
	url: "viewChart_runPy.php",
	data: {downfile: "excel"}
	})
	.done(function(result) {
      alert('다운로드 완료!');
	});
});

$("#mdfile_down").click(function() {
	$.ajax({
	method: "POST",
	url: "viewChart_runPy.php",
	data: {downfile: "markdown"}
	})
	.done(function(result) {
      alert('생성 완료!');
	});
});

// 관종엑셀 다운로드 파일만 있을 때 로직
// $("#download-btn").click(function() {
//   $.ajax({
//     url: "viewChart_runPy.php",
//     success: function(result) {
//       alert('다운로드 완료!');
//     }
//   });
// });
</script>

</body>
</html>