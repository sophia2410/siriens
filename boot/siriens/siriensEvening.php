<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_date = (isset($_GET['report_date']) ) ? $_GET['report_date'] : "";
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

<!-- Page Heading -->
<div style='border: 1px;' class="card-header py-3">
	거래일자 :
	<select id="report_date" class="select" style='width:200px;'>
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

	<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button> &nbsp;&nbsp;
	<button class="btn btn-danger btn-sm" onclick="save()"> 저 장 </button> &nbsp;
	<button class="btn btn-danger btn-sm" onclick="del()" > 삭 제 </button> 
	&nbsp;&nbsp;&nbsp;
	/ 
	&nbsp;&nbsp;&nbsp;
	<button class="btn btn-danger btn-sm" onclick="regi_theme()"> 테마관리</button>
<table style="width:100%;">
	<tr>
	<td>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframe" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="siriensEvening_B.php">
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
function search() {
	report_date   = document.getElementById('report_date').value;

	iframe.src = "siriensEvening_B.php?report_date="+report_date;
	return;
}

// 종목 추가하기
function regi_theme() {
	report_date   = document.getElementById('report_date').options[document.getElementById("report_date").selectedIndex].value;
	link = 'siriensEvening_Theme.php?report_date='+report_date ;
	window.open(link,'addTheme',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=2300, height=1200");
}

// 키워드 저장
function save() {
	document.getElementById("iframe").contentWindow.save();
	return;
}

// 키워드 삭제
function del() {
	document.getElementById("iframe").contentWindow.del();
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
