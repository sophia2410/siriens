<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_date = (isset($_GET['report_date']) ) ? $_GET['report_date'] : "";
?>
</head>

<body id="page-top">
<form id="form" method=POST action="siriensEvening_script.php" onsubmit="return false">

<!-- Page Wrapper -->
<div id="wrapper">

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

<!-- Page Heading -->
<div style='border: 1px;' class="card-header py-3">

	<label>일자 : <?=$report_date?></label>
	<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button> &nbsp; 
	&nbsp;&nbsp;
	/
	&nbsp;&nbsp;
	[상단]&nbsp;
	<button class="btn btn-danger btn-sm" onclick="themeSave()">  이슈 - 변경저장 </button> &nbsp;&nbsp;&nbsp;
	<button class="btn btn-danger btn-sm" onclick="themePick()">  오늘테마 - 선택저장</button> &nbsp; 
	&nbsp;&nbsp;
	/
	&nbsp;&nbsp;
	[하단]&nbsp;
	<button class="btn btn-danger btn-sm" onclick="stockSave()">  종목 - 변경저장 </button> &nbsp;&nbsp;&nbsp;
	<button class="btn btn-danger btn-sm" onclick="stockDel()" >  종목 - 선택삭제 </button> &nbsp;
</div>

<?php
	echo "<input type=hidden name=report_date value='$report_date'>";
?>
<table style="width:100%;">
	<tr>
	<td>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: 400px;">
			<iframe id="iframeT" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: 100%;" src="siriensEvening_Theme_T.php?report_date=<?=$report_date?>">
			</iframe>
		</div>
	</td>
	</tr>
	<tr>
	<td>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 500px);">
			<iframe id="iframeB" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: 100%;" src="siriensEvening_Theme_B.php">
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
</form>
</body>

<script>
function search() {
	report_date = document.forms[0].report_date.value;
	iframeT.src = "siriensEvening_Theme_T.php?report_date="+report_date;
	return;
}

// 종목 구해오기
function getStockList(theme, issue, keyword){
	report_date = document.forms[0].report_date.value;
	iframeB.src = "siriensEvening_Theme_B.php?report_date="+report_date+"&theme="+theme+"&issue="+issue+"&keyword="+keyword;
	return;
}

// 테마 정보 저장
function themeSave() {
	document.getElementById("iframeT").contentWindow.save();
	return;
}

// 테마 정보 저장
function themePick() {
	document.getElementById("iframeT").contentWindow.pick();
	return;
}

// 종목 정보 저장
function stockSave() {
	document.getElementById("iframeB").contentWindow.save();
	return;
}

// 종목 선택 삭제
function stockDel() {
	document.getElementById("iframeB").contentWindow.del();
	return;
}

// 검색조건 엔터키 입력
function keyDown(){
    form.stock.value = '';
	if (event.key == 'Enter') {
		getStockCode();
	}
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
<iframe id="iframeH" name="iframeH" src = "siriensEvening_script.php" width=100% height=100>
</html>
