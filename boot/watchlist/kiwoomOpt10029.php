<?php
	$pageTitle = "예상체결";

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
<table style="width:100%;">
	<tr>
	<td style="width:17%;">
	<div style='border: 1px;'>
		거래일시 : 
		<?php
			$query = " SELECT max(date) date
						FROM calendar
					WHERE date <= now()";

			$result = $mysqli->query($query);
			$row = $result->fetch_array(MYSQLI_BOTH);
			echo "<input type=text id=date   name=date   style='width:120px' value='". $row['date']."' onkeydown='if(event.keyCode==13) search()'>";
		?>

		<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button> &nbsp;&nbsp;
	</div>
	</td>
	<td rowspan=2 style="width:83%;">
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 50px);">
			<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 50px);" src="viewChart.php">
			</iframe>
		</div>
	</td>
	</tr>
	<tr>
	<td style="width:17%;">
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="kiwoomOpt10029_L.php">
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
	var date   = document.getElementById('date').value;

	iframeL.src = "kiwoomOpt10029_L.php?date="+date;
	iframeR.src = "viewChart_B.php";
	return;
}

// 섹터 등 선택 시 오른쪽 프레임에 내역 조회
function viewRealtime(pgmId, sector, theme) {
	var date   = document.getElementById('date').value;
	var encodedSector = encodeURIComponent(sector);
	var encodedTheme = encodeURIComponent(theme);

	var path = "viewChart.php?pgmId="+pgmId+"&search_date="+date+"&sector="+encodedSector+"&theme="+encodedTheme;

	iframeR.src = path;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
