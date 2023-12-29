<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_date = (isset($_GET['report_date'])   ) ? $_GET['report_date'] : date('Ymd',time());
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
		<td style='width:15%'>
			<!-- Page Heading -->
			<div style='border: 1px;' class="card-header py-3">
				거래일자 :
				<select id="report_date" class="select" style='width:100px;'>
				<?php
					$query = " SELECT date
								FROM calendar
							WHERE date <= now()
								ORDER BY date DESC
								LIMIT 500";

					$result = $mysqli->query($query);

					$option = "";
					$i=0;
					while($row = $result->fetch_array(MYSQLI_BOTH)) {
						// 거래일자가 없는 경우는 제일 1행 선택되도록..
						if($report_date == $row['date']) {
							$option .= "<option value='". $row['date']."' selected>". $row['date']."</option>";
						} else {
							$option .= "<option value='". $row['date']."'>". $row['date']."</option>";
						}
						$i++;
					}
					echo $option;
				?>
				</select>
				</select>
				<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button>
			</div>
		</td>
		<td style='width:85%' rowspan=2 valign=top>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 30px);" src="hotStock_R.php">
				</iframe>
			</div>
		</td>
	</tr>
	<tr>
	<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="hotStock_L.php">
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
// 거래일자 선택후 조회 - 왼쪽 프레임에 종목 리스트업
function search() {
	key_val  = document.getElementById('report_date').options[document.getElementById("report_date").selectedIndex].value;

	brWidth = window.innerWidth;
	iframeL.src = "hotStock_L.php?report_date="+key_val+"&brWidth="+brWidth;
	return;
}

// 종목 선택 시 오른쪽 프레임에 내역 조회
function viewMochaten(date, cd, nm) {
	brWidth = window.innerWidth;
	iframeR.src = "hotStock_R.php?report_date="+date+"&code="+cd+"&name="+nm+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
