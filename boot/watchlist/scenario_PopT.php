<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$sector = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme  = (isset($_GET['theme'])) ? $_GET['theme'] : '';
?>

<body>
<form name="form1" method='POST' action='scenario_pop_script.php'>

<?php

echo "<div style='margin-top:10px;margin-bottom:10px;'>";
echo "<input type=text name=sector value=\"".$sector."\"> ";
echo "<input type=text name=theme  value=\"".$theme."\"> ";
echo "<button class='btn btn-danger btn-sm' onclick='updateOne()'> 일괄변경 </button> / ";
echo "<button class='btn btn-danger btn-sm' onclick='updateEach()'> 종목수정반영 </button>";
echo "</div>";

echo "<table class='table table-sm' border=1>
		<tr align=center>
		<th>관종등록일</th>
		<th>코드</th>
		<th>종목</th>
		<th>Hot</th>
		<th>섹터</th>
		<th>테마</th>
		<th>이슈</th>
		<th>종목키워드</th>
		<th>테마코멘트</th>
		<th>뉴스</th>
		</tr>";

$query = "SELECT STR_TO_DATE(A.watchlist_date, '%Y%m%d') watchlist_date_str,
				A.watchlist_date,
				A.code,
				A.name,
				A.sector,
				A.theme,
				A.issue,
				A.stock_keyword,
				A.hot_theme,
				A.theme_comment,
				(SELECT title FROM rawdata_siri_report Z WHERE Z.page_date = A.watchlist_date AND Z.code = A.code ORDER BY date DESC LIMIT 1) news
		FROM daily_watchlist A
		WHERE sector = '$sector'
		AND theme = '$theme'
		ORDER BY watchlist_date DESC";
// echo $query."<br><br>";
$result = $mysqli->query($query);

$i=0;
while($row = $result->fetch_array(MYSQLI_BOTH)) {

	$checked1 = ($row['hot_theme'] == 'Y') ? " checked" : "";
	
	echo "<tr align=center>";
	echo "<td>".$row['watchlist_date_str']."</td>";
	echo "<td>".$row['code']."</td>";
	echo "<td align=left>".$row['name']."</td>";
	echo "<td><input type=checkbox class=hot_theme name=hot_theme$i value='Y' $checked1></td>";
	echo "<td><input type=text name=sector$i value=\"".$row['sector']."\"></td>";
	echo "<td><input type=text name=theme$i value=\"".$row['theme']."\"></td>";
	echo "<td><input type=text name=issue$i style='width: 300px;' value=\"".$row['issue']."\"></td>";
	echo "<td><input type=text name=stock_keyword$i style='width:300px;' value=\"".$row['stock_keyword']."\">";
	echo "<td><input type=text name=theme_comment$i style='width:300px;' value=\"".$row['theme_comment']."\"></td>";
	echo "<input type=hidden name=watchlist_date$i value=\"".$row['watchlist_date']."\">";
	echo "<input type=hidden name=code$i value=\"".$row['code']."\"></td>";
	echo "<td align=left>".$row['news']."</td>";
	echo "</tr>";
	$i++;
}
?>
</table>
<input type=hidden name=proc_fg>
<input type=hidden name=org_sector value=<?=$sector?>>
<input type=hidden name=org_theme  value=<?=$theme?>>
<input type=hidden name=cnt value=<?=$i?>>
</form>
<iframe name="saveFrame" src="scenario_pop_script.php" style='border:0px;' width=1000 height=700>
</iframe>
</body>


<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function updateOne() {
	if(confirm('입력하신 섹터/테마를 일괄변경하시겠습니까?')) {
		form = document.form1;
		form.proc_fg.value = 'updateThemeOne';
		form.target = "saveFrame";
		form.submit();
	}
}

// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function updateEach(sector, theme) {
	form = document.form1;
	form.proc_fg.value = 'updateThemeEach';
	form.target = "saveFrame";
	form.submit();
}
</script>
</html>
