<?php
// 관심종목 + 시나리오 = 우측, 일자 뷰화면

require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
<link rel="shortcut icon" href="#">
<style>
	.content {
	height: 400px;
	border: 0px solid hsl(0, 0%, 75%);
	}
	.content > img {
	width: auto;
	height: 100%;
	}
	input[type=radio] { margin-left: 5px }
	.scroll_box {
		width: 1800px;
		height: 183px;
		display: flex;
		overflow-x: auto;
	}
	table th, tr, td{
		padding: 0.1rem;
	}
</style>
</head>

<?php
$search_fg   = (isset($_GET['search_fg'])) ? $_GET['search_fg'] : '';
$search_date = (isset($_GET['search_date'])) ? $_GET['search_date'] : '';

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';
?>

<body>
<form name="form1" method='POST' action='0dayStocks_script.php'>

<?php
if($search_fg == ''){
	echo "<h3></h3>";
} else {
		// 종목 키워드
		$query = "SELECT STR_TO_DATE(A.date, '%Y%m%d') date 
						, B.close kospi_close
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',B.close_rate,'% </font>') END kospi_index
						, C.close kosdaq_close
						, CASE WHEN C.close_rate >= 0 THEN CONCAT('<font color=red> ▲',C.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',C.close_rate,'% </font>') END kosdaq_index
					FROM calendar A
					LEFT OUTER JOIN market_index B
					ON B.date = A.date
					AND B.market_fg = 'KOSPI'
					LEFT OUTER JOIN market_index C
					ON C.date = A.date
					AND C.market_fg = 'KOSDAQ'
					WHERE A.date = '$search_date'";

		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);

		$market_index = $row['date']."&nbsp;";
		$market_index .= " [ 코스피 ] ".number_format($row['kospi_close'])."&nbsp;";
		$market_index .= $row['kospi_index']."&nbsp;";
		$market_index .= " [ 코스닥 ] ".number_format($row['kosdaq_close'])."&nbsp;";
		$market_index .= $row['kosdaq_index'];
		
		// 지수
		echo "<div class='row'>
				<div class='card-body h5 mb-0 font-weight-bold text-gray-800'>
				$market_index 
				</div>
			  </div>";

	// 관심종목 + 시나리오 화면 공통 표시


   echo "<div class='container'>
        <h2>섹터</h2>
        <div id='sectorButtons'>";

		$sectors = ['IP/엔터', 'IT/기술', '가상자산', '가상현실', '로봇', '반도체', '2차전지', '국제', '미래모빌리티', '바이오', '방산', '소비', '신물질', '에너지', '운송', '원자재', '정부정책', '정치', '헷징'];
            foreach ($sectors as $sector) {
                echo "<button type='button' class='btn btn-info btn-sm' onclick='setSector(\"$sector\")'>$sector</button> ";
            }
    echo "</div>

        <h2>키워드</h2>
        <div id='keywordButtons'></div>
    </div>";

	// 오늘의 테마
	echo "<div class='row' style='width:96%;'>";
	echo "<div class='font-weight-bold text-gray-800' style='width:87%; margin:0px; margin-top:10px; margin-bottom:10px;'>▷ 오늘의 테마</div>";
	echo "<div style='float:right;'><input type=button class='btn btn-danger btn-sm' onclick=\"saveTM()\" value='저장'></div>";
	echo "</div>";
	
	$i=0;
	$r=0;
	$prd_fg = '';

	echo "<div>";
	echo "<table style='width:95%;' class='table table-bordered small table-success table-sm'>";
	echo "<tr align=center>";
	echo "<th width=60>Hot?</th>";
	echo "<th width=120>섹터</th>";
	echo "<th width=120>테마</th>";
	echo "<th width=300>이슈</th>";
	echo "<th>COMMENT</th>";
	echo "</tr>";

	$query = " 	SELECT A.sector
					,  A.theme
					,  A.issue
					,  A.hot_theme
					,  A.theme_comment
				FROM daily_watchlist A
				WHERE A.watchlist_date = '$search_date'
				AND A.code != 'DAY'
				GROUP BY A.sector
					,  A.theme
					,  A.issue";
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		
		$checked1 = ($row['hot_theme'] == 'Y') ? " checked" : "";

		echo "<tr align=center>";
		echo "<td><input type=checkbox class=hot_theme name=hot_theme$i value='Y' $checked1></td>";
		echo "<td><input type=text name=sector$i value=\"".$row['sector']."\"></td>";
		echo "<td><input type=text name=theme$i value=\"".$row['theme']."\"></td>";
		echo "<td><input type=text name=issue$i style='width: 300px;' value=\"".$row['issue']."\"></td>";
		echo "<td><input type=text name=theme_comment$i style='width:95%;' value=\"".$row['theme_comment']."\"></td>";
		echo "<input type=hidden name=org_sector$i value=\"".$row['sector']."\">";
		echo "<input type=hidden name=org_theme$i value=\"".$row['theme']."\">";
		echo "<input type=hidden name=org_issue$i value=\"".$row['issue']."\">";
		echo "</tr>";
		$i++;
	}
	echo "</table>";
	echo "</div>";

	$query = "	SELECT STR_TO_DATE(A.date, '%Y%m%d') watchlist_date_str
					, B.code
					, B.name
					, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',B.close_rate,'% </font>') END close_rate
					, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',B.tot_trade_amt,'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt
					, B.volume
					, B.market_cap
					, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
					, B.stock_keyword
					, B.watchlist_date
				FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 6) A
				INNER JOIN daily_watchlist B
				ON A.date = B.watchlist_date
				ORDER BY B.watchlist_date DESC, B.tot_trade_amt DESC";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);
	$j=0;
	$pre_date = '';
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($pre_date != $row['watchlist_date']) {
			if($pre_date != '') {
				echo "</div>";
				$j=0;
			}
			echo "<div class='h5 font-weight-bold text-gray-800' style=' margin:0px; margin-top:10px; margin-bottom:10px;'>▷ ".$row['watchlist_date_str']." 일자 종목차트</div>";
			echo "<div class='row'> ";
		}

		echo "<div class='col-xl-3 col-md-6 mb-4'>
				<div style='margin: 0; margin-left:10px'>
					<div class='row no-gutters align-items-center'>
						<div class='col mr-0'>
							<div class='font-weight-bold text-primary text-uppercase mb-1'>
								".$row['name']." (".$row['uprsn'].") ".$row['close_rate']." / ".$row['tot_trade_amt']."
							</div>
							<div style='margin: 0;'>
								<img class='img-fluid' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$row['code'].".png?sidcode=1681518352718'>
							</div>
						</div>
					</div>
				</div>
				</div>";

		$j++;

		if($j%4 == 0) {
			echo "</div>";
			echo "<div class='row'>";
		}

		$pre_date = $row['watchlist_date'];
	}
	echo "</div>";
}
?>
<input type=hidden name=proc_fg>
<input type=hidden name=search_date value=<?=$search_date?>>
<input type=hidden name=search_fg value=<?=$search_fg?>>
<input type=hidden name=cnt value=<?=$i?>>
<input type=hidden name=code value='DAY'>
</form>
<iframe name="saveFrame" src="scenario_script.php" style='border:0px;' width=1000 height=700>
</iframe>


<script>
	function setSector(sector) {
		$.ajax({
			url: 'fetch_keywords.php',
			type: 'POST',
			data: { sector: sector },
			success: function(response) {
				$('#keywordButtons').html(response);
			}
		});
	}
</script>

</body>
<script>
// 일자 매매복기 저장
function saveDR() {
	form = document.form1;
	form.proc_fg.value = 'saveDayReview';
	form.target = "saveFrame";
	form.submit();
}

// 일자 테마 저장
function saveTM() {
	form = document.form1;
	form.proc_fg.value = 'saveTheme';
	form.target = "saveFrame";
	form.submit();
}

// 거래일자 트레킹 대상 종목 가져오기
function getData(date) {
	form = document.form1;
	form.proc_fg.value = 'getData';
	form.scenario_date.value = date;
	form.target = "saveFrame";
	form.submit();
}
</script>
</html>