<?php
// 차트 보기 화면. 각 화면에서 링크 됨. 화면 ID에 따라 분기 처리
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
require("xrayTick_queries.php");

//var_dump($_SERVER);
?>

<head>
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
	$d=0;
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

		// 가장 최근 0일차 상승이유
		if(isset($row['0day_date'])) {
			$info_0day =" <b>(".$row['uprsn'].")</b> ".$row['close_rate_str']." / ".$row['tot_trade_amt_str']."</font>"." &nbsp; ".$row['0day_date'];
		} else {
			$info_0day = "<font class='h5'>&nbsp</font>";
		}
		
		// 종목 거래내역 // 장중 - 실시간데이터, 이외 - 마감데이터, 예상체결 - 예상체결데이터
		$realtime_data = "";
		if($row['trade_date'] != '') {
			$realtime_data = "<font class='h5'>".number_format($row['acc_trade_amount'])."억  &nbsp ".$row['trade_rate_str']." </font> &nbsp";
			$realtime_data .= "<font class='text-dark'>".number_format($row['trade_price'])."&nbsp ".number_format($row['market_cap'],2)."&nbsp ".$row['trade_date']."</font> ";
		}
		
		// 모차십 0일차 등록건이 있는 경우 건수 표시되게 함.
		$mochaten_cnt = '';
		if($row['mochaten_cnt'] > 0) {
			$mochaten_cnt = '<font color=red>('.$row['mochaten_cnt'].')</font>';
		}

		// echo "<div class='col-xl-3 col-md-6 mb-4' style='margin: 0; margin-left:10px margin-right:10px'>
		echo "<div class='$col_st col-md-6 mb-4' style='margin: 0;'>
					<div class='row no-gutters align-items-center'>
						<div class='col mr-0'>
							<div class='font-weight-bold text-primary text-uppercase mb-1' style='height:35px; line-height:35px;'>$mochaten_cnt
								<font class='h4'><span class='draggable' id=stock_nm$d draggable='true'><b>[[".$row['name']."]]</b></span><a href='../siriens/stock_B.php?code=".$row['code']."&name=".$row['name']."&brWidth=2500' onclick='window.open(this.href, \'stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>..</a></font> &nbsp;".$realtime_data."
							</div>
							<div class='font-weight-bold mb-1 style='margin: 0;'>
								$info_0day
							</div>
							<div style='margin: 0;'>
								<img class='img-fluid' src='https://ssl.pstatic.net/imgfinance/chart/item/".$chart_url.$row['code'].".png?sidcode=1705826920773'>
							</div>
						</div>
					</div>
			</div>";
				
		$j++;
		$d++;

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