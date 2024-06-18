<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

	$signal_page = (isset($_POST['stock'])) ? $_POST['stock'] : '';
	$signal_page = (isset($_POST['stock_nm'])) ? $_POST['stock_nm'] : '';
?>
<script 
  src="https://code.jquery.com/jquery-3.6.0.min.js"
  integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" 
  crossorigin="anonymous">
</script>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
<style>
	.sector-list {
		display: flex;
		flex-wrap: nowrap;
		overflow-x: auto;
		margin-top: 10px;
	}
	.sector-item {
		padding: 10px;
		margin-right: 10px;
		background-color: #f1f1f1;
		border-radius: 5px;
		cursor: pointer;
	}
</style>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<form id="form">
<!-- Main Content -->

<?php

$query = " SELECT date
			 FROM calendar
			WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
			AND   date >= '20240201'
			ORDER BY date DESC";

$result = $mysqli->query($query);

?>
<table style="width:100%; height:100%" class="table text-dark">
	<tr>
		<td>
			<button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','29.5')">상한가</button>
		    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('0dayStocks','20','2000')">20% || 2000억↑</button>
		    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('mochaten')">모차십</button>
		    <button type="button" class="btn btn-danger btn-sm" id="themeButton">최근테마(관.종)</button>
		    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick('sophiaWatchlist')">최근0일차(관.종)</button>
		    <button type="button" class="btn btn-danger btn-sm" onclick="xrayTick()">최근일자</button> &nbsp; 
		    <input type=text name=buy_cnt style='width:30px' value=3>건/<input type=text name=buy_period style='width:30px' value=4>일내
			<button type="button" class="btn btn-danger btn-sm" onclick="xrayTick()">연속매수</button>
		</td>
		<td>
			<button type="button" class="btn btn-info btn-sm" id="excel_down">관종엑셀 다운로드</button>
		</td>
	</tr>
	<tr>
		<td colspan=2>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 80px);">
				<iframe id="iframeB" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 80px);" src="xrayTick_Date_B.php">
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
// Xray 체결 보기
function xrayTick(pgmId, key1, key2) {
	brWidth = window.innerWidth;
	iframeR.src = "xrayTick_StockList.php?pgmId=sophiaWatchlist&sector="+sector+"&theme="+theme+"&brWidth="+brWidth;
	return;
}

$("#excel_down").click(function() {
	$.ajax({
	method: "POST",
	url: "../watchlist/viewChart_runPy.php",
	data: {downfile: "excel"}
	})
	.done(function(result) {
      alert('다운로드 완료!');
	});
});
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>