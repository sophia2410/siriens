<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

	$signal_page = (isset($_POST['stock'])) ? $_POST['stock'] : '';
	$signal_page = (isset($_POST['stock_nm'])) ? $_POST['stock_nm'] : '';
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
			<label>종목</label>
			<input type=text id=stock    name=stock placeholder='종목코드'>
			<input type=text id=stock_nm name=stock_nm  placeholder='종목명' onkeydown='keyDown()'>

			<input type=button value="조 회" class="" onclick='getList()'> &nbsp;&nbsp;
		</div>
	</div>
	<div style="margin: 0; border: 1; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 150px);">
		<iframe id="iframe_B" style="width: 100%; margin: 0; border: 1; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 150px);" src="stock_B.php">
		</iframe>
	</div>
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

// 종목 조회하기
function getList(){
	key_val  = document.getElementById("stock").value;
	brWidth = window.innerWidth;
	iframe_B.src = "opsidian_StockInfo_B.php?code="+key_val+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>