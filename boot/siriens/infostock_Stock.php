<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$infostock_date = (isset($_GET['infostock_date'])   ) ? $_GET['infostock_date']    : date('Ymd',time());
$theme = "";
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
	
<form name="form1" method='POST' onsubmit="return false">
<?php
	$query = " SELECT A.date, B.evening_report_title, IF(C.proc_cnt>0,'Y', 'N') proc_yn
				 FROM calendar A
				 LEFT OUTER JOIN market_report B
				   ON B.report_date = A.date
				 LEFT OUTER JOIN (SELECT report_date, COUNT(*) proc_cnt FROM siriens_infostock WHERE report_date <= DATE_ADD(now(), INTERVAL 0 DAY) GROUP BY report_date ORDER BY report_date desc LIMIT 100) C
				   ON C.report_date = A.date
				WHERE date <= DATE_ADD(now(), INTERVAL 0 DAY)
				ORDER BY date DESC
				LIMIT 100";

	$result = $mysqli->query($query);

	$input_date = "<select id='infostock_date' name='infostock_date' class='select'>";
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($infostock_date == $row['date']) {
			$select = " selected";
		} else {
			$select = "";
		}
		$input_date .= "<option value='". $row['date']."' $select>". $row['date']." - ". $row['proc_yn']." - ".$row['evening_report_title']."</option>";
	}
	$input_date .= "</select> ";
?>

<table style="width:100%;" class="table table-danger text-dark">
	<tr>
		<td colspan=2><?=$input_date?> <input type='button' id='searchbtn' class="btn btn-danger" onclick='searchReport()' value='조 회'>
		&nbsp;&nbsp;&nbsp;<input type='button' id='procbtn' class="btn btn-danger" onclick='proc()' value='반 영'></td>
	</tr>
	<tr>
		<td style='width:70%;'>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 155px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 155px);" src="infostock_Stock_L.php">
				</iframe>
			</div>
		</td>
		<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 155px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 155px);" src="infostock_Stock_R.php">
				</iframe>
			</div>
		</td>
	</tr>
</table>
</form>

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
	$PATH = "https://siriens.mycafe24.com";
}
?>

<script>
// 일자 선택후 조회
function searchReport() {
	key_val  = document.getElementById('infostock_date').options[document.getElementById("infostock_date").selectedIndex].value;
	console.log(key_val);

	iframeL.src = "infostock_Stock_L.php?infostock_date="+key_val;
	iframeR.src = "infostock_Stock_R.php?infostock_date="+key_val;
	return;
}

function proc() {
	if(confirm('반영하시겠습니까?')) {
		document.getElementById("iframeR").contentWindow.procStock();
	}
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
