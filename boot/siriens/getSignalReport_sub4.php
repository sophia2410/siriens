<!DOCTYPE html>
<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");

// 검색 조건값 넘어온 경우 변수 셋팅
$code = (isset($_GET['code'])) ? $_GET['code'] : '';
?>
<style>
  table {
    width: 100%;
    border-top: 1px solid #444444;
    border-collapse: collapse;
  }
  th, td {
    border-bottom: 1px solid #444444;
    padding: 10px;
  }
</style>
</head>

<body>
<form method='POST' action='findNews.php'>
<?php
	// 검색조건 설정
	// 뉴스검색 기본 쿼리문		
	$qry1  = " SELECT A.signal_id
					, STR_TO_DATE(A.date, '%Y%m%d') date
					, A.time
					, A.title
					, A.content
					, A.publisher
					, A.writer
					, A.link
					, A.keyword
					, A.grouping
					, A.confirm_fg
				FROM signals A
				WHERE code like '$code' 
				ORDER BY A.confirm_fg, STR_TO_DATE(A.date, '%Y%m%d') desc ";

	//echo $qry1;
	$result = $mysqli->query($qry1);

	echo "<br>";
	echo "<table cellpadding=5 rowpadding=2 style='font-size:12px;'>";
	
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		// echo $qry1;
		$signal_id  = $row['signal_id'];
		$date       = $row['date'];
		$time       = $row['time'];
		$title      = $row['title'];
		$content    = $row['content'];
		$publisher  = $row['publisher'];
		$name       = $row['writer'];
		$grouping   = $row['grouping'];
		$keyword    = $row['keyword'];
		$link       = $row['link'];

		$bgcolor = ($row['confirm_fg'] == '1') ? "#f5d8d6" : '';

		echo "
		<tr style='border: 1px solid #444444; background-color:$bgcolor'>
			<td><input type=button value='$signal_id' onclick=\"setNews('".$signal_id."','".$link."')\"></td>
			<td width=70>$date</td>
			<td width=300 style='font:red;'>$title</td>
			<td>$publisher</td>
			<td>$keyword</td>
			<td>$keyword</td>
			<td width=350>".substr($content, 0,300)."</td>
		</tr>	
		";
	}
?>
</form>
</body>
<script>
// 검색조건 엔터키 입력
function keyDown(){
	if (event.key === 'Enter') {
		findNews();
	}
}

// 조회조건으로 뉴스 검색
function findNews() {
	form = document.forms[0];
	console.log("==="+form.title.value+"===");
	console.log(form.content.value);
	console.log(form.stock.value);

	if(   form.title.value   == ""
		&& form.content.value == ""
		&& form.stock.value   == "" 
		&& form.date.value    == "" ) {
		alert('검색조건을 입력해주세요');
		return;
	}
	form.submit();
}

// 선택한 뉴스 셋팅
function setNews(id, url) {
	opener.document.getElementById('url').value = url;
	opener.getNews(id);
}

</script>
</html>