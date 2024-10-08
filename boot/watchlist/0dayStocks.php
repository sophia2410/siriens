<?php
	$pageTitle = "최근0일차";

	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$0day_date = (isset($_GET['0day_date'])   ) ? $_GET['0day_date'] : date('Y-m-d',time());
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

<table style="width:100%;">
	<tr>
		<td style='width:21%'>
			<!-- Page Heading -->
			<div style='border: 1px;'>
				<select id="0day_date" class="select">
				<?php
					$query = " SELECT date
									, CASE WHEN B.0day_date is null THEN '' ELSE '(Y)' END regi_watchlist
								FROM calendar A
								LEFT OUTER JOIN (SELECT 0day_date FROM 0day_stocks GROUP BY 0day_date) B
								ON B.0day_date = A.date
								WHERE date <= now()
								AND   date >= '2023-01-01'
								ORDER BY date DESC
								LIMIT 150";

					$result = $mysqli->query($query);

					$option = "";
					$i=0;
					while($row = $result->fetch_array(MYSQLI_BOTH)) {
						// 관종등록일자가 없는 경우는 제일 1행 선택되도록..
						if($0day_date == $row['date']) {
							$option .= "<option value='". $row['date']."' selected>".$row['date'].$row['regi_watchlist']."</option>";
						} else {
							$option .= "<option value='". $row['date']."'>".$row['date'].$row['regi_watchlist']."</option>";
						}
						$i++;
					}
					echo $option;
				?>
				</select>
				<select id="increase_rate" class="select">
					<option value='10'>전체</option>
					<option value='29.5'>상한가</option>
					<option value='20'>20%↑</option>
				</select>
				<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button>
				<button class="btn btn-info btn-sm" onclick="showT()"> 테 마 </button>
				<button class="btn btn-info btn-sm" onclick="showC()"> 차 트 </button>
				<button class="btn btn-secondary btn-sm" onclick="getData()">Get</button>
				<!-- 종목추가기능 사용하지 않아 잠시 막아두기 24.06.11 -->
				<!-- || <input type=text id=stock style='width:90px' placeholder='종목코드/명'>
				<button class="btn btn-secondary btn-sm" onclick="addData()">Add</button> -->
				
			</div>
		</td>
		<td style='width:79%' rowspan=2 valign=top>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 30px);" src="scenario_RS.php">
				</iframe>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="0dayStocks_L.php">
				</iframe>
			</div>
		</td>
	</tr>

</table>

</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<script>
// 관종등록일자 선택후 조회 - 왼쪽 프레임에 종목 리스트업
function search() {
	key_val1  = document.getElementById('0day_date').options[document.getElementById("0day_date").selectedIndex].value;
	key_val2  = document.getElementById('increase_rate').options[document.getElementById("increase_rate").selectedIndex].value;
	brWidth = window.innerWidth;
	iframeL.src = "0dayStocks_L.php?0day_date="+key_val1+"&increase_rate="+key_val2+"&brWidth="+brWidth;
	return;
}

// 월별 테마조회
function showT() {
	iframeR.src = "scenario_RT.php";
	return;
}

// 기간 관종 차트 모아보기
function showC() {
	pgmId = 'watchlist';
	iframeR.src = "viewChart.php?pgmId="+pgmId;
	return;
}

// 관종등록일자 선택후 데이터 가져오기
function getData() {

	key_val  = document.getElementById('0day_date').options[document.getElementById("0day_date").selectedIndex].value;
	if(confirm(key_val+'데이터를 가져오시겠습니까?')) {
		// 자식프레임에 있는 함수 호출하기
		document.getElementById("iframeR").contentWindow.getWatchlist(key_val);
	}
}

// 관종등록일자에 입력한 종목 추가
function addData() {
	key_val  = document.getElementById('0day_date').options[document.getElementById("0day_date").selectedIndex].value;
	stock = document.getElementById('stock').value;

	// 자식프레임에 있는 함수 호출하기
	document.getElementById("iframeR").contentWindow.addWatchlist(key_val,stock);
}


// 종목 선택 시 오른쪽 프레임에 내역 조회
function viewStock(wdate, sdate, cd, nm) {
	brWidth = window.innerWidth;
	iframeR.src = "scenario_RS.php?0day_date="+wdate+"&scenario_date="+sdate+"&code="+cd+"&name="+nm+"&brWidth="+brWidth;
	return;
}

// 문서 이미지 선택 시 오른쪽 프레임에 내역 조회 - 리뷰등록
function viewReview(date) {
	brWidth = window.innerWidth;
	iframeR.src = "scenario_RV.php?review_date="+date+"&brWidth="+brWidth;
	return;
}

// 문서 이미지 선택 시 오른쪽 프레임에 차트 + Xray 체결 보기
function xrayTick(search_date, increase_rate) {
	brWidth = window.innerWidth;
	pgmId = '0dayStocks';
	iframeR.src = "../watchlist/xrayTick_StockList.php?pgmId="+pgmId+"&search_date="+search_date+"&increase_rate="+increase_rate+"&brWidth="+brWidth;
	return;
}

// 차트돋보기 선택 시 오른쪽 프레임에 내역 조회 - 차트 보기
function viewChart(search_date, increase_rate) {
	brWidth = window.innerWidth;
	pgmId = 'watchlist';
	iframeR.src = "viewChart.php?pgmId="+pgmId+"&search_date="+search_date+"&increase_rate="+increase_rate+"&brWidth="+brWidth;
	return;
}

</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
