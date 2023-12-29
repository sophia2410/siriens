<!DOCTYPE html>
<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");

$signal_page = (isset($_GET['signal_page'])) ? $_GET['signal_page'] : '';
$key_val = explode("/", $signal_page);
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
<form method='POST' action='getSignalReport_script.php'>
<input type=button value="변경사항저장" class="" onclick='modifyNews()'> &nbsp;
<input type=button value="확인완료처리" class="" onclick='confirmNews()'>
<?php
	// 검색조건 값
	$key_val = explode("/", $signal_page);

	// 시그널리포트 불러오기
	$qry  = " SELECT 	b.id,
						b.date,
						b.time,
						b.title,
						b.content,
						b.publisher,
						b.writer,
						b.link,
						b.code,
						b.stock,
						b.grouping,
						b.keyword,
						b.stocks,
						b.confirm_fg
				FROM rawdata_siri_report b
				WHERE b.page_date ='$key_val[0]'
				AND b.page_fg   ='$key_val[1]'
				AND b.confirm_fg is null
				ORDER BY id";
	// echo $qry;
	$result = $mysqli->query($qry);

	echo "$key_val[0]<br>";
	echo "<table width='1000px' cellpadding=5 rowpadding=2 style='font-size:13px;'>";
	echo "<tr style='background-color:#f0f0f0;'>
		  <th>일자</th><th>타이틀</th><th>종목</th></tr>";

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$id                = $row['id'];
		$date              = $row['date'];
		$title             = $row['title'];
		$content           = $row['content'];
		$publisher         = $row['publisher'];
		$writer            = $row['writer'];
		$link              = $row['link'];
		$code              = $row['code'];
		$stock             = $row['stock'];
		$grouping          = $row['grouping'];
		$keyword           = $row['keyword'];
		$stocks            = $row['stocks'];

		echo "
		<tr> 
		<td><input type=text name=date$i value='$date' style='width:80px'><input type=hidden name=id$i value='$id' style='width:80px'></td>
		<td>$title</td>
		<td onclick='showNews(\"$link\", this)'>$link</td>
		<td><input type=text name=stock$i value='$stock' style='width:150px'></td>
		</tr>
		";
		//<textarea name=title$i style='width:700px; height:20px'>$title</textarea>
		$i++;
	}
	echo "<input type=hidden name=total_cnt value=$i>";
	echo "<input type=hidden name=proc_fg>";
?>
<iframe id="newsFrame" style="display:none;width:100%;height:600px;"></iframe>
</form>
</body>
<script>
function modifyNews() {
	document.forms[0].proc_fg.value ='NM';
	document.forms[0].submit();
}
function confirmNews() {
	if(confirm('뉴스확인완료 하시겠습니까?')) {
		document.forms[0].proc_fg.value ='NC';
		document.forms[0].submit();
	}
}
function showNews(url, element) {
  var iframe = document.getElementById("newsFrame");

  if (url != "") {
    iframe.src = url;
    iframe.style.display = "block";

    // 모든 행의 배경색을 초기화합니다.
    var rows = document.getElementsByTagName("tr");
    for (var i = 0; i < rows.length; i++) {
      rows[i].style.backgroundColor = "";
    }

    // 클릭한 행의 배경색을 변경합니다.
    element.parentNode.style.backgroundColor = "yellow";
  } else {
    iframe.style.display = "none";
  }
}
</script>
</html>