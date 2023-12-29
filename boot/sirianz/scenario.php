<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$trade_date = (isset($_GET['search_date'])   ) ? $_GET['search_date'] : date('Ymd',time());
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_sirianz.php");
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
									, CASE WHEN B.trade_date is null THEN '' ELSE '(Y)' END regi_scenario
								FROM calendar A
								LEFT OUTER JOIN (SELECT trade_date FROM scenario GROUP BY trade_date) B
								ON B.trade_date = A.date
								WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
								AND date >= '20231001' -- 시나리오 등록 시작일
								ORDER BY date DESC";

					$result = $mysqli->query($query);

					$option = "";
					$i=0;
					while($row = $result->fetch_array(MYSQLI_BOTH)) {
						// 거래일자가 없는 경우는 제일 1행 선택되도록..
						if($trade_date == $row['date']) {
							$option .= "<option value='". $row['date']."' selected>".$row['date'].$row['regi_scenario']."</option>";
						} else {
							$option .= "<option value='". $row['date']."'>".$row['date'].$row['regi_scenario']."</option>";
						}
						$i++;
					}
					echo $option;
				?>
				</select>
				<button class="btn btn-danger btn-sm" onclick="getData()">GetData</button>
				&nbsp;
				<!-- <select id="tracking_yn" class="select">
					<option value='Y' selected>추적Y</option>
					<option value='N'>추적N</option>
				</select> -->

				<select id="buy_pick" class="select">
					<option value='' selected>매매?</option>
					<option value='Y'>Y</option>
					<option value='N'>N</option>
				</select>
				
				<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button>
				
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
	key_val2 = document.getElementById('buy_pick').options[document.getElementById("buy_pick").selectedIndex].value;

	brWidth = window.innerWidth;
	// iframeL.src = "scenario_L.php?search_date="+key_val+"&tracking_yn="+key_val1+"&buy_pick="+key_val2+"&brWidth="+brWidth;
	iframeL.src = "scenario_L.php?search_date="+key_val+"&buy_pick="+key_val2+"&brWidth="+brWidth;
	return;
}

// 거래일자 선택후 데이터 가져오기
function getData() {
	key_val  = document.getElementById('search_date').options[document.getElementById("search_date").selectedIndex].value;

	// 자식프레임에 있는 함수 호출하기
	document.getElementById("iframeR").contentWindow.getData(key_val);
}

// 종목 선택 시 오른쪽 프레임에 내역 조회
function viewStock(date, cd, nm, idx) {
	brWidth = window.innerWidth;
	iframeR.src = "scenario_RS.php?trade_date="+date+"&code="+cd+"&name="+nm+"&tracking_index="+idx+"&brWidth="+brWidth;
	return;
}

// 일자 선택 시 오른쪽 프레임에 내역 조회
function viewDay(date) {
	brWidth = window.innerWidth;
	iframeR.src = "scenario_RD.php?trade_date="+date+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
