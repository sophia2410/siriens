<?php
// 차트 보기 화면. 각 화면에서 링크 됨. 화면 ID에 따라 분기 처리
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>

<head>
<link rel="shortcut icon" href="#">
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

$search_date  = (isset($_GET['search_date'])  ) ? $_GET['search_date']   : '';
$mochaten_date= (isset($_GET['mochaten_date'])) ? $_GET['mochaten_date'] : '';

$sector   = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme 	  = (isset($_GET['theme']))  ? $_GET['theme'] : '';
$category = (isset($_GET['category'])) ? $_GET['category'] : '';

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';

// 기본은 일차트 조회
$viewFg = (isset($_GET['viewFg'])) ? $_GET['viewFg'] : 'day';

switch ($viewFg) {
	case "day"	   : $chart_url = "candle/day/";	break;
	case "week"    : $chart_url = "candle/week/";	break;
	case "month"   : $chart_url = "candle/month/";	break;
	case "oneday"  : $chart_url = "area/day/";		break;
	case "oneweek" : $chart_url = "area/week/";		break;
	default : $chart_url = "";	break;
}

date_default_timezone_set('Asia/Seoul');

$now = date('H:i:s');  // 현재 시간을 구함
$day_of_week = date('N');  // 현재 요일을 구함 (1: 월요일, 2: 화요일, ..., 7: 일요일)

// $pgmId sophiaWatchlist 만 실행되도록 함. + 테마정보가 있는 경우만 실행되도록 추가 수정. 24.01.24
if ($pgmId == 'sophiaWatchlist' && $theme != '') {
	if ($day_of_week >= 1 && $day_of_week <= 5 && $now >= '09:00:00' && $now <= '15:30:00') {
		// 월요일부터 금요일까지 9시에서 3시 30분 사이에 실행될 로직을 작성

		file_put_contents("E:/Project/202410/www/pyObsidian/vars_viewChart.txt", "$pgmId\n$sector\n$theme\n$category\n$search_date"); 
		$command = "C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe E:/Project/202410/www/pyObsidian/Watchlist_CrawlingPrice.py";
		$output = shell_exec($command);
	}
}
?>

<body>

<!-- 조회 조건을 변경할 수 있는 버튼을 생성합니다. -->
<button onclick="changeCondition('day')">일</button>
<button onclick="changeCondition('week')">주</button>
<button onclick="changeCondition('month')">월</button> &nbsp;
<button onclick="changeCondition('oneday')">1일</button>
<button onclick="changeCondition('oneweek')">1주</button>
<?php
if($pgmId == ''){
	echo "<h3></h3>";
} else {

	if($pgmId == 'sophiaWatchlist') {
		$query = "SELECT CONCAT(' [ ' , A.sector,' ] ', A.theme, '  ',A.category) AS group_key
						, A.code 
						, CONCAT(A.name, CASE WHEN talent_fg != '' THEN CONCAT('<font color=violet><b>',talent_fg,'</b></font>') ELSE '' END) name
						, A.sort_theme, A.sort_stock, A.news_title, A.news_link
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.watchlist_date IS NOT NULL THEN CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(B.watchlist_date, '%Y%m%d'),'</font>') ELSE '' END watchlist_date_str
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt
						, CONCAT('*', Z.crawling_dtime) realtime_date
						, Z.crawling_price realtime_price
						, CASE WHEN Z.crawling_rate >= 0 THEN CONCAT('<font color=red> +',Z.crawling_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.crawling_rate),'% </font>') END realtime_rate
					FROM watchlist_sophia A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN temporary_price Z
					ON Z.code = A.code
					WHERE A.sector = '$sector'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					AND A.category LIKE CASE WHEN '$category' != '' THEN '%".$category."%' ELSE '%' END
					ORDER BY A.sector, A.sort_theme, A.sort_stock";
	} else if($pgmId == 'watchlist') {

		if($search_date == '') { // 전체 차트 보기 클릭. 한달전 차트까지 섹터, 테마별로 보여주기
			$search_date = date('Ymd');
			$orderby = "X.sector_max_date DESC, R.group_max_date DESC, X.sector_sum DESC, R.group_sum DESC, B.tot_trade_amt DESC";
			$group_key = "B.sector";
			$where = "AND (B.watchlist_date, B.code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist GROUP BY code)";
		} else {
			$orderby = "B.watchlist_date DESC, B.tot_trade_amt DESC";
			$group_key = "STR_TO_DATE(A.date, '%Y%m%d')";
			$where = "";
		}

		$query = "	SELECT $group_key group_key
						, CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(B.watchlist_date, '%Y%m%d'),'</font>') watchlist_date_str
						, B.code
						, B.name
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, B.stock_keyword
						, B.watchlist_date
						, STR_TO_DATE(Z.date, '%Y%m%d') realtime_date
						, Z.close realtime_price
						, CASE WHEN Z.close_rate >= 0 THEN CONCAT('<font color=red> +',Z.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.close_rate),'% </font>') END realtime_rate
					FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) A
					INNER JOIN daily_watchlist B
					ON A.date = B.watchlist_date
					$where
					INNER JOIN (	SELECT G.sector, G.theme, SUM(G.tot_trade_amt) AS group_sum, MAX(G.watchlist_date) AS group_max_date
									FROM daily_watchlist G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) S
									ON S.date = G.watchlist_date
									GROUP BY G.sector, G.theme
								) R
					ON B.sector = R.sector
					AND B.theme  = R.theme
					INNER JOIN (	SELECT G.sector, SUM(G.tot_trade_amt) AS sector_sum, MAX(G.watchlist_date) AS sector_max_date
									FROM daily_watchlist G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) S
									ON S.date = G.watchlist_date
									GROUP BY G.sector
								) X
					ON B.sector = X.sector
					LEFT OUTER JOIN daily_price Z
					ON Z.code = B.code
					AND Z.date = (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL 0 DAY), '%Y%m%d')))
					ORDER BY $orderby";
	} else if($pgmId == 'mochaten') {
		$query = "	SELECT CONCAT(Q.cha_fg_nm,' - ',cha_comment) group_key
						 , CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(Q.watchlist_date, '%Y%m%d'),'</font>') watchlist_date_str
						 , Q.cha_fg_nm
						 , Q.code
						 , Q.name
						 , CASE WHEN Q.close_rate >= 0 THEN CONCAT('<font color=red> ▲',Q.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',Q.close_rate,'% </font>') END close_rate
						 , CASE WHEN Q.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(Q.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(Q.tot_trade_amt,'억') END tot_trade_amt
						 , Q.uprsn
						 , Q.stock_keyword
						 , Q.watchlist_date
						 , STR_TO_DATE(Z.date, '%Y%m%d') realtime_date
						 , Z.close realtime_price
						 , CASE WHEN Z.close_rate >= 0 THEN CONCAT('<font color=red> +',Z.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.close_rate),'% </font>') END realtime_rate
					FROM (	SELECT A.cha_fg
								, C.nm cha_fg_nm
								, C.nm_sub1 cha_comment
								, A.code
								, A.name
								, CASE WHEN A.cha_fg = 'MC000' THEN A.close_rate ELSE B.close_rate end close_rate
								, CASE WHEN A.cha_fg = 'MC000' THEN A.tot_trade_amt ELSE B.tot_trade_amt end tot_trade_amt
								, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
								, B.stock_keyword
								, B.watchlist_date
							FROM mochaten A
							LEFT OUTER JOIN (SELECT * FROM daily_watchlist 
											WHERE (watchlist_date, code)
											IN 	(SELECT MAX(watchlist_date), code FROM daily_watchlist WHERE watchlist_date < $mochaten_date GROUP BY code)) B
							ON A.code = B.code
							INNER JOIN comm_cd C
							ON C.cd = A.cha_fg
							WHERE A.mochaten_date = '$mochaten_date'
						) Q
					LEFT OUTER JOIN daily_price Z
					ON Z.code = Q.code
					AND Z.date = (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL 0 DAY), '%Y%m%d')))
					ORDER BY cha_fg, tot_trade_amt DESC";
	}
	// echo "<pre>$query</pre>";

	$result = $mysqli->query($query);
	$j=0;
	$d=0;
	$pre_group = '';
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($pre_group != $row['group_key']) {
			if($pre_group != '') {
				echo "</div>";
				$j=0;
			}
			echo "<div class='h4 font-weight-bold text-gray-800' style='margin:0px; margin-top:10px; margin-bottom:15px;height:40px;'><hr class='table-danger'>▷ ".$row['group_key']."</div>";
			echo "<div class='row' style='margin-left:10px'> ";
		}

		if($row['watchlist_date_str'] != '') {
			$info_0day =" <font class='h5'><b>(".$row['uprsn'].")</b> ".$row['watchlist_date_str']." &nbsp; ".$row['close_rate']." / ".$row['tot_trade_amt']."</font>";
		} else {
			$info_0day = "";
		}
		
		$realtime_data = "";
		if($row['realtime_date'] != '') {
			$realtime_data = "<font class='h5'>".$row['realtime_rate']." </font> &nbsp";
			$realtime_data .= "<font class='text-dark'>".number_format($row['realtime_price'])." &nbsp ".$row['realtime_date']."</font> ";
		}
		
		if($pgmId == 'sophiaWatchlist') {
			$col_st = "col-xl-4";
			$row_div = 3;
		}
		else {
			$col_st = "col-xl-3";
			$row_div = 4;
		}

		// echo "<div class='col-xl-3 col-md-6 mb-4' style='margin: 0; margin-left:10px margin-right:10px'>
		echo "<div class='$col_st col-md-6 mb-4' style='margin: 0;'>
					<div class='row no-gutters align-items-center'>
						<div class='col mr-0'>
							<div class='font-weight-bold text-primary text-uppercase mb-1' style='height:35px; line-height:35px;'>
								<font class='h4'><span class='draggable' id=stock_nm$d draggable='true'><b>[[".$row['name']."]]</b></span><a href='stock_B.php?code=".$row['code']."&name=".$row['name']."&brWidth=2500' onclick='window.open(this.href, \'stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>..</a></font> &nbsp;".$realtime_data."
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
</body>
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

</script>
</html>