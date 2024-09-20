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
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<form id="form">
<!-- Main Content -->

<?php

$query = " SELECT date
			 FROM calendar
			WHERE date <= now()
			AND   date >= '20240201'
			ORDER BY date DESC";

$result = $mysqli->query($query);

?>
<table style="width:100%; height:100%" class="table table-danger text-dark">
	<tr>
		<td>
			<select id="date" name="date" class="select" style='width:120px;'>
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
			<button type="button" class="btn btn-danger btn-sm" onclick="search()">조 회 </button>&nbsp;&nbsp;
			<button type="button" class="btn btn-info btn-sm" id="excel_down">관종엑셀 다운로드</button>
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
}

$("#excel_down").click(function() {
	$.ajax({
	method: "POST",
	url: "viewChart_runPy.php",
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