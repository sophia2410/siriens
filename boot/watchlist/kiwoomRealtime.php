<?php
	$pageTitle = "실시간거래-최근12분";

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
		echo "<input type=text id=date   name=date   style='width:120px' onkeydown=\"if(event.keyCode==13) search()\" value='". $row['date']."'>";
		echo "<input type=text id=minute name=minute style='width:60px'  onkeydown=\"if(event.keyCode==13) search()\">";
	?>

	<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button> &nbsp;&nbsp;
	* [코드] 클릭 : 종목 기간별 15분단위 거래대금 조회, [종목명] 클릭 : 조회일 1분 거래대금 조회
</div>
<table style="width:100%;">
	<tr>
	<td>
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

	iframeR.src = "kiwoomRealtime_B.php?date="+date+"&minute="+minute+"&sortBy="+sortBy;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
