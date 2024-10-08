<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$key_val   = (isset($_GET['key_val'])  ) ? $_GET['key_val']   : '';
$mainFrame = (isset($_GET['mainFrame'])) ? $_GET['mainFrame'] : '';
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
if (!$mainFrame) {
	require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
}
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

<table style="width:100%;">
	<tr>
		<td style='width:8%'>
			<!-- Page Heading -->
			<div style='border: 1px;'>
				<input type=text id=stock_nm name=stock_nm style='width:70%' placeholder='종목' onkeydown="if(event.keyCode==13) search()">
				<button class="btn btn-danger btn-sm" id=search onclick="search()"> 조 회 </button>
			</div>
		</td>
		<td rowspan=2 style='width:92%'>
			<div style="margin: 0; border: 1; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 70px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 70px); overflow:hidden;" src="viewChart.php">
				</iframe>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<div style="margin: 0; border: 1; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 70px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 70px);" src="aStarWatchlist_L.php">
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
// 종목, 
function search() {
	stock_nm  = document.getElementById('stock_nm').value;
	brWidth = window.innerWidth;
	iframeL.src = "aStarWatchlist_L.php?stock_nm="+stock_nm+"&brWidth="+brWidth;
	return;
}

// 섹터 등 선택 시 오른쪽 프레임에 내역 조회
function viewChart(sector, theme) {
	brWidth = window.innerWidth;
	pgmId = 'aStarWatchlist';

	iframeR.src = "viewChart.php?pgmId="+pgmId+"&sector="+sector+"&theme="+theme+"&brWidth="+brWidth;
	return;
}

// 문서 이미지 선택 시 오른쪽 프레임에 차트 + Xray 체결 보기
function xrayTick(sector, theme) {
	brWidth = window.innerWidth;
	pgmId = 'aStarWatchlist';
	iframeR.src = "../watchlist/xrayTick_StockList.php?pgmId="+pgmId+"&sector="+sector+"&theme="+theme+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
