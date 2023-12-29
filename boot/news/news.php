<!DOCTYPE html>
<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
putenv("LANG=ko_KR.UTF-8");
setlocale(LC_ALL, 'ko_KR.utf8');
require("../common/db/connect.php");

$link_date    = (isset($_GET['link_date'])) ? $_GET['link_date'] : date('Ymd',time());

$query = " SELECT cd, nm
			 FROM comm_cd
			WHERE l_cd = 'SG00'";
$result = $mysqli->query($query);
$cd = array();
$nm = array();
while($row = $result->fetch_array(MYSQLI_BOTH)) {
	$cd[] = $row['cd'];
	$nm[] = $row['nm'];
}
?>
</head>
<body>
	<div class="news">
		<div>
			<label>뉴스연결일</label>
			<input type=text id="link_date" value=<?=$link_date?> style="height:30px; width:100px;">
			<label>뉴스링크</label>
			<input type=text id="url" style="font-family: 'Nanum Gothic'; font-size:13px;box-sizing: border-box;display: inline-flex;position: relative; height:30px; width:500px;">
			<button class="" onclick="checkLink()">뉴스가져오기</button>
			&nbsp;&nbsp;&nbsp;
			<button class="" onclick="findNews()" >등록뉴스찾기</button>
		</div>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframe1" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="execCrawling.php">
			</iframe>
		</div>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0; width:900px; height:100px;">
			<iframe id="iframe2" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; width:900px; height:100px;">
			</iframe>
		</div>
	</div>
</body>
<script>

	var agent = navigator.userAgent.toLowerCase();
	console.log(agent);
	if( agent.indexOf("chrome") != -1) {
	console.log("Chrome 브라우저입니다.");
	}
	// 입력된 뉴스링크 이미 저장되었는지 확인
	function checkLink() {
		date = document.getElementById('link_date').value;
		url  = document.getElementById('url').value;

		if(url == '') {
			alert('뉴스링크 입력');
			return;
		} else if(date == '') {
			alert('뉴스연결일 입력');
			return;
		}

		// get 방식 전송으로 주소 잘림 방지
		url = url.replace(/[&]/g, '^amp^');
		console.log(url);

		// 기 등록된 뉴스인지 확인
		iframe2.src = "checkNews.php?link="+url; 
	}
	
	// 링크 체크 후 등록-불러오기, 신규-크롤링 처리
	function getNews(signal_id)
	{
		date = document.getElementById('link_date').value;

		// get 방식 전송으로 주소 잘림 방지
		url = document.getElementById('url').value;
		url = url.replace(/[&]/g, '^amp^'); //str.replace(/[찾을문자열]/g, '새로운 문자열');   // 대소문자 구분


		if(signal_id != '') { // 기등록된 뉴스
			console.log('등록된 뉴스@@@');
			iframe1.src = "viewNews.php?link_date="+date+"&link="+url;
			return;
		} else {  // 등록되지 않은 뉴스
			console.log('가져와야지!!!!@@@');
			iframe1.src = "execCrawling.php?link_date="+date+"&link="+url;
			return;
		}
	}

	//기존 등록된 뉴스 찾기
	function findNews(){
		window.open('findNews.php','findStock',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1200, height=800");
	}

</script>
</html>
