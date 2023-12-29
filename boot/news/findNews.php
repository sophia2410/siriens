<!DOCTYPE html>
<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../../common/db/connect.php");

//변수 셋팅
$title   = '';
$content = '';
$stock   = '';
$code    = '';
$date    = '';
$where   = "";

// 검색 조건값 넘어온 경우 변수 셋팅
if(isset($_POST['title'])){ 
	if(isset($_POST['title'])  ) $title   = $_POST['title'];
	if(isset($_POST['content'])) $content = $_POST['content'];
	if(isset($_POST['code']))    $code    = $_POST['code'];
	if(isset($_POST['stock']))   $stock   = $_POST['stock'];
	if(isset($_POST['date']))    $date    = $_POST['date'];

	$setArr  = 'Y';
} else{
	$setArr  = 'N';
}
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
	<div>
		<div>
			<label>뉴스타이틀</label>
			<input type=text name="title"   value='<?=$title?>'   onkeydown="keyDown()" style="height:30px; width:150px;">
			<label>뉴스본문</label>
			<input type=text name="content" value='<?=$content?>' onkeydown="keyDown()" style="height:30px; width:150px;">
			<label>종목</label>
			<input type=text name="stock"   value='<?=$stock?>'   onkeydown="keyDown()" style="height:30px; width:150px;">
			<label>리포트일자</label>
			<input type=text name="date"    value='<?=$date?>'    onkeydown="keyDown()" style="height:30px; width:150px;">
			<input type=button value="뉴스검색" class="" onclick='findNews()'>
		</div>
	</div>

<?php
	if($setArr == 'N'){ 
		echo "<h3><font color=red>등록된 뉴스를 검색할 수 있습니다 (하트)</font></h3>";
	} else {
		// 검색조건 설정
		if($title   != '') $where .= " AND title   like '%".$title."%'";
		if($content != '') $where .= " AND content like '%".$content."%'";
		if($code   != '') {
			$where .= " AND code like '$code'";
		}
		if($stock   != '') {
			$where .= " AND EXISTS (SELECT 1 FROM signal_link X INNER JOIN stock Y ON Y.code = X.code AND Y.name like '%".$stock."%'";
			$where .= "              WHERE X.signal_id = A.signal_id )";
		}
		if($date   != '') {
			$where .= " AND EXISTS (SELECT 1 FROM signal_link X WHERE X.link_date = '".$date."' AND X.signal_id = A.signal_id )";
		}

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
					FROM signals A
					WHERE 1=1 ";
		$qry1 .= $where;
		$qry1 .= "ORDER BY STR_TO_DATE(A.date, '%Y%m%d') desc ";

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

			echo "
			<tr style='border: 1px solid #444444;'>
				<td><input type=button value='$signal_id' onclick=\"setNews('".$signal_id."','".$link."')\"></td>
				<td width=70>$date</td>
				<td width=300>$title</td>
				<td>$publisher</td>
				<td>$keyword</td>
				<td>$keyword</td>
				<td width=350>".substr($content, 0,300)."</td>
			</tr>	
			";
		}
			
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