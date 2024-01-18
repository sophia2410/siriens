<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$watchlist_date = (isset($_GET['watchlist_date'])   ) ? $_GET['watchlist_date'] : date('Ymd',time());
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
		<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="sophiaWatchlist_L.php">
				</iframe>
			</div>
		</td>
		<td style='width:90%' valign=top>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 30px);" src="sophiaWatchlist_R.php">
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
// 섹터 등 선택 시 오른쪽 프레임에 내역 조회
function viewStock(sector, theme, category) {
	brWidth = window.innerWidth;
	iframeR.src = "sophiaWatchlist_R.php?sector="+sector+"&theme="+theme+"&category="+category+"&brWidth="+brWidth;
	return;
}

</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
