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
<input type=button value="Signals Update" class="" onclick='updateSignals()'>
<?php
	// 검색조건 값
	$key_val = explode("/", $signal_page);

	// 시그널리포트 불러오기
	$qry  = " SELECT a.link,
						a.signal_id,
						a.date      as signals_date,
						a.news_date as signals_news_date,
						a.time      as signals_time,
						a.title     as signals_title,
						a.content   as signals_content,
						a.publisher as signals_publisher,
						a.writer    as signals_writer,
						a.code      as signals_code,
						a.name      as signals_name,
						a.grouping  as signals_grouping,
						a.keyword   as signals_keyword,
						a.remark    as signals_remark,
						b.date,
						b.time,
						b.title,
						b.content,
						b.publisher,
						b.writer,
						b.code,
						b.stock,
						b.grouping,
						b.keyword,
						b.stocks
				FROM signals a
				INNER JOIN rawdata_siri_report b
					ON  b.signal_id = a.signal_id
					AND b.page_date ='$key_val[0]'
					AND b.page_fg   ='$key_val[1]'
					AND (  (b.date     != a.date) 
						OR (b.content  != a.content)
						OR (b.title    != a.title)
						OR (b.code     != a.code)
						OR (b.stock    != a.name)
						OR (a.confirm_fg != 1)
						)";
	// echo $qry;
	$result = $mysqli->query($qry);
//
	echo "$key_val[0]<br>";
	echo "<table cellpadding=5 rowpadding=2 style='font-size:13px;'>";

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$id                = $row['signal_id'];
		$link              = $row['link'];
		$signals_date      = $row['signals_date'];
		$signals_news_date = $row['signals_news_date'];
		$signals_time      = $row['signals_time'];
		$signals_title     = $row['signals_title'];
		$signals_content   = $row['signals_content'];
		$signals_publisher = $row['signals_publisher'];
		$signals_writer    = $row['signals_writer'];
		$signals_code      = $row['signals_code'];
		$signals_name      = $row['signals_name'];
		$signals_grouping  = $row['signals_grouping'];
		$signals_keyword   = $row['signals_keyword'];
		$signals_remark    = $row['signals_remark'];
		$date              = $row['date'];
		$time              = $row['time'];
		$title             = $row['title'];
		$content           = $row['content'];
		$publisher         = $row['publisher'];
		$writer            = $row['writer'];
		$code              = $row['code'];
		$stock             = $row['stock'];
		$grouping          = $row['grouping'];
		$keyword           = $row['keyword'];
		$stocks            = $row['stocks'];

		echo "
		<tr style='background-color:#f0f0f0;'>
			<td colspan=4><h3><a href=\"javascript:popupNews('".$link."')\">$link</a></h3><input type=hidden  name=h_id$i value='$id'></td>
		</tr>
		<tr> 
			<td style='width:120px'><h2>TITLE</h2></td>";
		if($signals_title != $title ) echo "<td><h1 style='color:red'>X</h1></td>";
		else echo "<td>&nbsp;</td>";

		echo "
			<td><textarea name=title$id style='width:900px; height:20px'>$signals_title</textarea></td>
			<td>$title</td>
		</tr>
		<tr> 
			<td style='width:120px'><h2>Content</td>";
		if($signals_content != $content ) echo "<td><h1 style='color:red'>X</h1></td>";
		else echo "<td>&nbsp;</td>";

		echo "
			<td><textarea name=content$id style='width:100%; height:100px'>$signals_content</textarea></td>
			<td>$content</textarea></td>
		</tr>
		<tr>
			<td style='width:120px'><h2>출판사/기자</td>";
		if( ($signals_publisher != $publisher) || ($signals_writer != $writer) ) echo "<td><h1 style='color:red'>X</h1></td>";
		else echo "<td>&nbsp;</td>";

		echo "
			<td>
				<input type=text name=publisher$id value='$signals_publisher' style='width:80px'>
				<input type=text name=writer$id    value='$signals_writer' style='width:80px'>
			</td>
			<td>$publisher / $writer</td>
		</tr>
		<tr> 
			<td style='width:120px'><h2>일자/시간</td>";
		if($signals_date != $date || $signals_date != $signals_news_date  ) echo "<td><h1 style='color:red'>X</h1></td>";
		else echo "<td>&nbsp;</td>";

		echo "
			<td><input type=text name=date$id value='$signals_date' style='width:80px'> <input type=text name=news_date$id value='$signals_news_date' style='width:80px'> <input type=text name=time$id value='$signals_time' style='width:80px'></td>
			<td>$date</td>
		</tr>";
		// <tr> 
		// 	<td style='width:120px'><h2>그룹</td>";
		// if($signals_grouping != $grouping) echo "<td><h1 style='color:red'>X</h1></td>";
		// else echo "<td>&nbsp;</td>";

		// echo "
		// 	<td><input type=text name=grouping$id value='$signals_grouping' style='width:100%'></td>
		// 	<td>$grouping</td>
		// </tr>
		// <tr> 
		// 	<td style='width:120px'><h2>중심테마</td>";
		// if($signals_keyword != $keyword) echo "<td><h1 style='color:red'>X</h1></td>";
		// else echo "<td>&nbsp;</td>";

		// echo "
		// 	<td><input type=text name=keyword$id value='$signals_keyword' style='width:100%'></td>
		// 	<td>$keyword</td>
		// </tr>
		echo "<tr> 
			<td style='width:120px'><h2>종목</td>";
		if( ($signals_code != $code) || ($signals_name != $stock) )  echo "<td><h1 style='color:red'>X</h1></td>";
		else echo "<td>&nbsp;</td>";

		echo "
			<td>
				<input type=text name=code$id value='$signals_code' style='width:80px'>
				<input type=text name=name$id value='$signals_name' style='width:80px'>
			</td>
			<td>$code / $stock</td>
		</tr>
		";

		$i++;
	}
	echo "<input type=hidden name=total_cnt value=$i>";
	echo "<input type=hidden name=proc_fg>";
?>

</form>
</body>
<script>
function updateSignals() {
	document.forms[0].proc_fg.value ='US';
	document.forms[0].submit();
}

function popupNews(link) {
	window.open(link,'popupNews',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1500, height=1000");
}

</script>
</html>