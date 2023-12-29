<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
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
	
거래일자 :
	<select id="report_dt" class="select" style='width:150px;'>
	<?php
		$query = " SELECT date
					FROM calendar
				   WHERE date <= (select min(date) from calendar where date > (select DATE_FORMAT(DATE_ADD(now(), INTERVAL 0 DAY), '%Y%m%d')))
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
	</select>&nbsp;
	<input type=button class='btn btn-danger btn-sm' value='조 회' onclick='search()'>&nbsp;
	<input type=button class='btn btn-danger btn-sm' value='저 장' onclick='save()'>



	<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
		<iframe id="iframe" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="marketReview_B.php">
		</iframe>
	</div>
</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<?php
if($_SERVER["HTTP_HOST"] == 'localhost') {
	$PATH = "http://localhost";
} else {
	$PATH = "https://yunseul0907.cafe24.com";
}
?>

<script>
// 조회
function search() {

	brWidth = window.innerWidth;
	report_date = document.getElementById('report_dt').value;
	iframe.src = "marketReview_B.php?report_date="+report_date+"&brWidth="+brWidth;
	return;
}

// 저장
function save() {
	document.getElementById("iframe").contentWindow.save();
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
