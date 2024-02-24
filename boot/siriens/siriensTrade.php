<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

	$trade_date  = (isset($_POST['trade_date'])) ? $_POST['trade_date'] : date('Ymd');
	$signal_page = (isset($_POST['stock'])) ? $_POST['stock'] : '';
	$signal_page = (isset($_POST['stock_nm'])) ? $_POST['stock_nm'] : '';

	
$query = " SELECT date
			FROM calendar
			WHERE date < now() 
			ORDER BY date DESC 
			LIMIT 20";

$result = $mysqli->query($query);

?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<form id="form" method=post>
<!-- Main Content -->
<div id="content">
	<div>
		<div>
			<label>일자</label>
			<select id="trade_date" class="select">
				<option value="">--choose date--</option>
				<?php
					$option = "";
					while($row = $result->fetch_array(MYSQLI_BOTH)) {
						if($trade_date == $row['date']) {
							$option .= "<option value='". $row['date']."' selected>". $row['date']."</option>";
						} else {
							$option .= "<option value='". $row['date']."'>". $row['date']."</option>";
						}
					}
					echo $option;
				?>
			</select> &nbsp;&nbsp;
			<label>종목</label>
			<input type=text id=stock    name=stock placeholder='종목코드'>
			<input type=text id=stock_nm name=stock_nm  placeholder='종목명' onkeydown='keyDown()'>

			<input type=button value="조 회" class="" onclick='getList()'> &nbsp;&nbsp;
		</div>
	</div>
<div>
	<table style="width:100%;">
	<tr>
	<td style='width:89%' rowspan=2 valign=top>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframe_L" style="width: 100%; margin: 0; border: 1; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 50px);" src="siriensTradeL.php">
			</iframe>
		</div>
	</td>
	<td style='width:11%' rowspan=2 valign=top>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframe_R" style="width: 100%; margin: 0; border: 1; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 50px);" src="siriensTradeR.php">
			</iframe>
		</div>
	</td>
	</tr>
	</table>
</div>

</form>
</div>
</div>
</body>
<script>
// 종목 구해오기
function getStockCode(){
    val = form.stock_nm.value;
	form.stock.value = '';
    window.open('/boot/common/popup/stock_find.php?find_val='+val,'findStock',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=400, height=500");
}

// 검색조건 엔터키 입력
function keyDown(){
    form.stock.value = '';
	if (event.key == 'Enter') {
		getStockCode();
	}
}

// 종목 페이지 불러오기
function getList(){
	key_date = document.getElementById("trade_date").value;
	key_val  = document.getElementById("stock").value;
	brWidth = window.innerWidth;
	iframe_L.src = "siriensTradeL.php?trade_date="+key_date+"&code="+key_val+"&brWidth="+brWidth;
	iframe_R.src = "siriensTradeR.php?trade_date="+key_date+"&code="+key_val+"&brWidth="+brWidth;
	return;
}

// 모차십 종목 선택 시 오른쪽 프레임에 내역 조회
function viewTrade(date, cd) {
	brWidth = window.innerWidth;
	iframe_L.src = "siriensTradeL.php?trade_date="+date+"&code="+cd+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>