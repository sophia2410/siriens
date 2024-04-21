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

<?php

$query = " SELECT date
			 FROM calendar
			WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
			AND   date >= '20240201'
			ORDER BY date DESC";

$result = $mysqli->query($query);

?>
<table style="width:100%; height:100%" class="table table-danger text-dark">
	<tr>
		<td>
			<select id="date" class="select" style='width:120px;'>
			<?php
				$option = "";
				while($row = $result->fetch_array(MYSQLI_BOTH)) {
					if($date == $row['date']) {
						$option .= "<option value='". $row['date']."' selected>". $row['date']."</option>";
					} else {
						$option .= "<option value='". $row['date']."'>". $row['date']."</option>";
					}
				}
				echo $option;
			?>
			</select>
			<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button>
		</td>
	</tr>
	<tr>
		<td>
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
// 종목 조회하기
function search(){
	key_val  = document.getElementById('date').options[document.getElementById("date").selectedIndex].value;
	brWidth = window.innerWidth;
	iframeB.src = "xrayTick_Date_B.php?search_date="+key_val+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>