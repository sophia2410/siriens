<?php
	$pageTitle = "실시간체결-관종목록";
	
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

<!-- Page Heading -->
<div style='border: 1px;' class="card-header py-3">
	거래일시 : 
	<?php
		$query = " SELECT max(date) date
					FROM calendar
				   WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))";

		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);
		echo "<input type=text id=date   name=date   style='width:120px' value='". $row['date']."'>";
		echo "<input type=text id=minute name=minute style='width:60px' onkeydown=\"if(event.keyCode==13) search()\"> &nbsp;&nbsp;&nbsp;";
		echo "<input type=text id=stock_nm name=stock_nm style='width: 120px;' placeholder='종목' onkeydown='if(event.keyCode==13) search()'>";
		echo "<input type=text id=min_amount name=min_amount style='width: 120px;' placeholder='누적최소( )억' onkeydown='if(event.keyCode==13) search()'>";
	?>

	<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button> &nbsp;&nbsp;
</div>
<table style="width:100%;">
	<tr>
	<td style="width:17%;">
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="kiwoomRealtimeWatchList_L.php">
			</iframe>
		</div>
	</td>
	<td style="width:83%;">
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="kiwoomRealtime_B.php">
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
window.onload = function() {
    search(); // 페이지 로드 시 search 함수 호출
}

function search(sortBy='amount_last_min') {
	date   = document.getElementById('date').value;
	minute   = document.getElementById('minute').value;
	stock_nm = document.getElementById('stock_nm').value;
	min_amount = document.getElementById('min_amount').value;

	iframeL.src = "kiwoomRealtimeWatchList_L.php?date="+date+"&minute="+minute+"&stock_nm="+stock_nm+"&min_amount="+min_amount+"&sortBy="+sortBy;
	iframeR.src = "kiwoomRealtime_B.php?date="+date+"&minute="+minute+"&sortBy="+sortBy;
	return;
}

// 섹터 등 선택 시 오른쪽 프레임에 내역 조회
function viewRealtime(sector, theme, sortBy='amount_last_min') {
	date   = document.getElementById('date').value;
	minute   = document.getElementById('minute').value;

	var encodedSector = encodeURIComponent(sector);
	var encodedTheme = encodeURIComponent(theme);

	iframeR.src = "kiwoomRealtime_B.php?date="+date+"&minute="+minute+"&sector="+encodedSector+"&theme="+encodedTheme+"&sortBy="+sortBy;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
