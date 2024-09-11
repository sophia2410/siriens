<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$schedule_cd = (isset($_GET['schedule_cd'])   ) ? $_GET['schedule_cd']    : "";

$query = " SELECT schedule_cd, schedule_nm
			 FROM schedule
			ORDER BY schedule_cd DESC
			LIMIT 20";

$result = $mysqli->query($query);
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
	require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

<table style="width:100%;">
	<tr>
		<td style='width:17%'>
			<!-- Page Heading -->
			<div style='border: 1px;' class="card-header py-3">
				<select id="schedule_cd" class="select" style='width:70%;'>
				<option value="">--choose an option--</option>
				<?php
					$option = "";
					while($row = $result->fetch_array(MYSQLI_BOTH)) {
						if($schedule_cd == $row['schedule_cd']) {
							$option .= "<option value='". $row['schedule_cd']."' selected>". $row['schedule_nm']."</option>";
						} else {
							$option .= "<option value='". $row['schedule_cd']."'>". $row['schedule_nm']."</option>";
						}
					}
					echo $option;
				?>
				</select>
				<button class="btn btn-danger btn-sm small" onclick="search()"> 조 회 </button>
			</div>
		</td>
		<td style='width:83%' rowspan=2 valign=top>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 30px);" src="stock_B.php?page_id=schedule">
				</iframe>
			</div>
		</td>
	</tr>
	<tr>
	<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="schedulePageL.php">
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
// 모차십 일자 선택후 조회 - 왼쪽 프레임에 종목 리스트업
function search() {
	key_val  = document.getElementById('schedule_cd').options[document.getElementById("schedule_cd").selectedIndex].value;
	// console.log(key_val);

	brWidth = window.innerWidth;
	iframeL.src = "schedulePageL.php?schedule_cd="+key_val+"&brWidth="+brWidth;
}

// 모차십 종목 선택 시 오른쪽 프레임에 내역 조회
function viewStock(cd,nm) {
	brWidth = window.innerWidth;
	iframeR.src = "stock_B.php?code="+cd+"&name="+nm+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
