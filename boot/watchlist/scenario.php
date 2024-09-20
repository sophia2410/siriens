<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$scenario_date = (isset($_GET['search_date'])   ) ? $_GET['search_date'] : date('Ymd',time());
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
		<td style='width:25%'>
			<!-- Page Heading -->
			<div style='border: 1px;' class="card-header py-3">
				<select id="search_date" class="select" style='width:120px;'>
				<?php
					$query = " SELECT date
									, CASE WHEN B.scenario_date is null THEN '' ELSE '(Y)' END regi_scenario
								FROM calendar A
								LEFT OUTER JOIN (SELECT scenario_date FROM daily_watchlist_scenario GROUP BY scenario_date) B
								ON B.scenario_date = A.date
								WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
								AND date >= '20231001' -- 시나리오 등록 시작일
								ORDER BY date DESC";

					$result = $mysqli->query($query);

					$option = "";
					$i=0;
					while($row = $result->fetch_array(MYSQLI_BOTH)) {
						// 거래일자가 없는 경우는 제일 1행 선택되도록..
						if($scenario_date == $row['date']) {
							$option .= "<option value='". $row['date']."' selected>".$row['date'].$row['regi_scenario']."</option>";
						} else {
							$option .= "<option value='". $row['date']."'>".$row['date'].$row['regi_scenario']."</option>";
						}
						$i++;
					}
					echo $option;
				?>
				</select>
				<!-- <button class="btn btn-secondary btn-sm" onclick="getData()">GetData</button>
				&nbsp;

				<select id="buy_pick" class="select">
					<option value='' selected>매매?</option>
					<option value='Y'>Y</option>
					<option value='N'>N</option>
				</select> -->
				
				<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button>
				&nbsp;&nbsp;&nbsp;
				<button class="btn btn-info btn-sm" onclick="show()"> 테마보기 </button>
				
			</div>
		</td>
		<td style='width:75%' rowspan=2 valign=top>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 30px);" src="scenario_RS.php">
				</iframe>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="scenario_L.php">
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
// 거래일자 선택후 조회 - 왼쪽 프레임에 종목 리스트업
function search() {
	key_val  = document.getElementById('search_date').options[document.getElementById("search_date").selectedIndex].value;
	// key_val1 = document.getElementById('tracking_yn').options[document.getElementById("tracking_yn").selectedIndex].value;
	// key_val2 = document.getElementById('buy_pick').options[document.getElementById("buy_pick").selectedIndex].value;

	brWidth = window.innerWidth;
	// iframeL.src = "scenario_L.php?search_date="+key_val+"&tracking_yn="+key_val1+"&buy_pick="+key_val2+"&brWidth="+brWidth;
	iframeL.src = "scenario_L.php?search_date="+key_val+"&brWidth="+brWidth;
	return;
}

// 테마보기 하면 오른쪽 프레임에 관종 기준 테마가 달력으로 표시 
function show() {
	iframeR.src = "scenario_RT.php";
	return;
}

// 거래일자 선택후 데이터 가져오기
function getData() {

	key_val  = document.getElementById('search_date').options[document.getElementById("search_date").selectedIndex].value;
	if(confirm(key_val+'데이터를 가져오시겠습니까?')) {
		// 자식프레임에 있는 함수 호출하기
		document.getElementById("iframeR").contentWindow.getData(key_val);
	}
}

// 종목 선택 시 오른쪽 프레임에 내역 조회
function viewStock(wdate, sdate, cd, nm) {
	brWidth = window.innerWidth;
	iframeR.src = "scenario_RS.php?0day_date="+wdate+"&scenario_date="+sdate+"&code="+cd+"&name="+nm+"&brWidth="+brWidth;
	return;
}

// 일자 선택 시 오른쪽 프레임에 내역 조회
function viewDay(date) {
	brWidth = window.innerWidth;
	iframeR.src = "scenario_RD.php?search_fg=scenario&search_date="+date+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
