<?php
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
		echo "<input type=text id=minute name=minute style='width:60px' onkeydown=\"if(event.keyCode==13) search()\">";
	?>

	<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button> &nbsp;&nbsp;
</div>
<table style="width:100%;">
	<tr>
	<td>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframe" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="kiwoomRealtime_B.php">
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
function search(sortBy='amount_last_min') {
	date   = document.getElementById('date').value;
	minute   = document.getElementById('minute').value;

	iframe.src = "kiwoomRealtime_B.php?date="+date+"&minute="+minute+"&sortBy="+sortBy;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
