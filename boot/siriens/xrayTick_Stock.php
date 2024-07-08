<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

	$stock = (isset($_GET['stock'])) ? $_GET['stock'] : '';
	$stock_nm = (isset($_GET['stock_nm'])) ? $_GET['stock_nm'] : '';

	$page_fg  = (isset($_GET['page_fg'])) ? $_GET['page_fg'] : '';
	
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
if($page_fg != 'popup')
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<form id="form" method=post>
<!-- Main Content -->

<table style="width:100%; height:100%" class="table table-danger text-dark">
	<tr>
			<label>종목</label>
			<input type=text id=stock    name=stock value='<?=$stock?>' placeholder='종목코드'>
			<input type=text id=stock_nm name=stock_nm value='<?=$stock_nm?>' placeholder='종목명' onkeydown='keyDown()'>

			<input type=button value="조 회" class="" onclick='getList()'> &nbsp;&nbsp;
	</tr>
	<tr>
		<td style='width:70%'>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 80px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 80px);" src="xrayTick_Stock_L.php">
				</iframe>
			</div>
		</td>
		<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 80px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 80px);" src="xrayTick_Stock_R.php">
				</iframe>
			</div>
		</td>
	</tr>
</table>

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

// 종목 조회하기
function getList(){
	code  = document.getElementById("stock").value;
	name  = document.getElementById("stock_nm").value;
	brWidth = window.innerWidth;
	iframeL.src = "xrayTick_Stock_L.php?code="+code+"&name="+name+"&brWidth="+brWidth;
	iframeR.src = "xrayTick_Stock_R.php?code="+code+"&brWidth="+brWidth;
	return;
}

// 일자 선택 시 상세 내역 조회
function viewDetail(date) {
	code  = document.getElementById("stock").value;
	brWidth = window.innerWidth;
	iframeR.src = "xrayTick_Stock_R.php?date="+date+"&code="+code+"&brWidth="+brWidth;
	iframeR.src = "/boot/common/popup/stock_xray_tick.php?date="+date+"&code="+code+"&brWidth="+brWidth;
	return;
}
window.onload = function() {
	var stock = document.getElementById('stock');
	if (stock.value != '') {
		getList();
	}
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>