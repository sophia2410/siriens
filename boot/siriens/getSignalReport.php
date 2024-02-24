<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$signal_page = (isset($_POST['signal_page'])) ? $_POST['signal_page'] : '';
// echo "signal_page = ". $signal_page;
$query = " SELECT CONCAT(page_date,'/',page_fg) page_seq, page_title
			 FROM rawdata_siri_report
			WHERE page_title != ''
			ORDER BY page_date desc, page_fg
			limit 300";
$result = $mysqli->query($query);
$cd = array();
$nm = array();
while($row = $result->fetch_array(MYSQLI_BOTH)) {
	$cd[] = $row['page_seq'];
	$nm[] = $row['page_title'];
}
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

<!-- Main Content -->
<div id="content">
	<div>
		<div>
			<select id="signal_page">
				<option value="">--choose an option--</option>
				<?php
					$option = "";
					for($i=0; $i<count($cd); $i++) {
						if($signal_page == $cd[$i]) {
							$option .= "<option value='".$cd[$i]."' selected>".$nm[$i]."</option>";
						} else {
							$option .= "<option value='".$cd[$i]."'>".$nm[$i]."</option>";
						}
					}
					echo $option;
				?>
			</select>
			<input type=button value="조 회" class="" onclick='getData()'> &nbsp;&nbsp;
			<input type=button value="선택삭제" class="" onclick='delData()'>
			<input type=button value="전체저장" class="" onclick='saveData()'>&nbsp;&nbsp;&nbsp;

			<!-- <input type=button class="btn-danger" value="뉴스 및 리포트 반영" class="" onclick='procData()'> -->
			<input type=button class="btn-danger" value="뉴스 반영" class="" onclick='procData()'>&nbsp;&nbsp;&nbsp;&nbsp;
			
			<input type=button value="기등록뉴스 변경사항" class="" onclick='preViewUpSignals()'>&nbsp;
			<input type=button value="빠른 뉴스확인" class="" onclick='quickConfirmNews()'>&nbsp;

			<input type=button value="옵시디언용 보기" class="" onclick='preViewReport()'>
			
		</div>
	</div>
	<!-- 서버에 올리는 건 뉴스프레임 없애기-->
	<div style="margin: 0; border: 1; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
		<iframe id="iframe" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="getSignalReport_B.php">
		</iframe>
	</div> 
	 
<!-- 	iframe 에서 뉴스보기
	<div style="margin: 0; border: 1; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 550px);">
		<iframe id="iframe" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 550px);" src="getSignalReport_B.php">
		</iframe>
	</div>
	<div style="margin: 0; border: 1; font: inherit;vertical-align: baseline; padding: 0;height: 500px;">
		<iframe id="iframe_news" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: 500px;" src="">
		</iframe>
	</div> -->
</body>
<script>
// 검색조건 엔터키 입력
function keyDown(){
	if (event.key === 'Enter') {
		findNews();
	}
}

// 조회조건으로 뉴스 검색
function getData() {
	key_val  = document.getElementById("signal_page").options[document.getElementById("signal_page").selectedIndex].value;
	console.log(key_val);

	iframe.src = "getSignalReport_B.php?signal_page="+key_val;
	return;
}
function delData() {
	document.getElementById("iframe").contentWindow.delNews();
	return;
}
function saveData() {
	document.getElementById("iframe").contentWindow.saveNews();
	return;
}
function procData() {
	if(confirm('등록된 뉴스를 \'signals\' 테이블에 반영하시겠습니까?')) {
		document.getElementById("iframe").contentWindow.procNews();
		return;
	}
}
function preViewUpSignals() {
	key_val  = document.getElementById('signal_page').options[document.getElementById("signal_page").selectedIndex].value;
	preViewPage = 'getSignalReport_sub2.php?signal_page='+key_val;
	console.log('preview');
	console.log(preViewPage);
	window.open(preViewPage,'preView',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=2000, height=1500");
}

function preViewReport() {
	key_val  = document.getElementById('signal_page').options[document.getElementById("signal_page").selectedIndex].value;
	preViewPage = 'getSignalReport_sub3.php?signal_page='+key_val;
	window.open(preViewPage,'preView',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=2000, height=1500");
}

function quickConfirmNews() {
	key_val  = document.getElementById('signal_page').options[document.getElementById("signal_page").selectedIndex].value;
	preViewPage = 'getSignalReport_sub5.php?signal_page='+key_val;
	// preViewPage = 'getSignalReport_sub6.php?signal_page='+key_val;  임시 데이터 처리 용. 이후 삭제 예정 2023.11.05
	brWidth = window.innerWidth;
	brHeight = window.innerHeight;
	window.open(preViewPage,'preView',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=2800, height=2000");
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>