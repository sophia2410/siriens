<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$mochaten_date = (isset($_GET['mochaten_date'])   ) ? $_GET['mochaten_date']    : date('Ymd',time());
$signal_group  = (isset($_GET['signal_group'])) ? $_GET['signal_group'] : "";

$query = " SELECT DISTINCT mochaten_date
			 FROM mochaten
			ORDER BY mochaten_date DESC
			LIMIT 20";

$result = $mysqli->query($query);
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
if(isset($_GET['mainF'])) {
	require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
} else {
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_share.php");
}
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

<table style="width:100%;">
	<tr>
		<td style='width:20%'>
			<!-- Page Heading -->
			<div style='border: 1px;' class="card-header py-3">
				<select id="mochaten_date" class="select" style='width:60%;'>
				<option value="">--choose an option--</option>
				<?php
					$option = "";
					while($row = $result->fetch_array(MYSQLI_BOTH)) {
						if($mochaten_date == $row['mochaten_date']) {
							$option .= "<option value='". $row['mochaten_date']."' selected>". $row['mochaten_date']."</option>";
						} else {
							$option .= "<option value='". $row['mochaten_date']."'>". $row['mochaten_date']."</option>";
						}
					}
					echo $option;
				?>
				</select>
				<button class="btn btn-primary btn-sm" onclick="searchMochaten()"> 조 회 </button>
			</div>
		</td>
		<td style='width:80%' rowspan=2 valign=top>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 30px);" src="mochaten_R.php">
				</iframe>
			</div>
		</td>
	</tr>
	<tr>
	<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="mochaten_L.php">
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
function searchMochaten(code) {
	key_val  = document.getElementById('mochaten_date').options[document.getElementById("mochaten_date").selectedIndex].value;
	// console.log(key_val);

	brWidth = window.innerWidth;
	if(code == ''){
		iframeL.src = "mochaten_L.php?mochaten_date="+key_val+"&brWidth="+brWidth;
	} else {
		iframeL.src = "mochaten_L.php?mochaten_date="+key_val+"&brWidth="+brWidth+"&#"+code;
	}
	return;
}

// 모차십 종목 선택 시 오른쪽 프레임에 내역 조회
function viewMochaten(date, cd) {
	iframeR.src = "mochaten_R.php?mochaten_date="+date+"&code="+cd;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
