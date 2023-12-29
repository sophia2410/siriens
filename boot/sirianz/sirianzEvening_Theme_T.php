<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
</head>

<?php
$report_date = (isset($_GET['report_date']) ) ? $_GET['report_date'] : "";
?>

<body>
<form name="form1" method='POST' action='sirianzEvening_script.php' onsubmit="return false">

<?php
	$i=0;
	$r=0;
	$prd_fg = '';

	echo "<div>";
	echo "<table style='width:100%;' class='table table-bordered small table-success table-sm'>";
	echo "<tr align=center>";
	echo "<th width=30> </th>";
	echo "<th width=120>테마</th>";
	echo "<th width=120>이슈</th>";
	echo "<th width=150>키워드</th>";
	echo "<th width=100>종목보기</th>";
	echo "<th width=250>시그널아이디</th>";
	echo "<th width=450>관련뉴스1</th>";
	echo "<th width=450>관련뉴스2</th>";
	echo "<th width=450>관련뉴스3</th>";
	echo "</tr>";

	$query = " 	SELECT A.id
					,  A.theme
					,  A.issue
					,  A.keyword
					,  A.stock_cnt
					,  B.signal_id1
					,  B.signal_id2
					,  B.signal_id3
					,  (SELECT title FROM signals WHERE signal_id = B.signal_id1) title1
					,  (SELECT title FROM signals WHERE signal_id = B.signal_id2) title2
					,  (SELECT title FROM signals WHERE signal_id = B.signal_id3) title3
					,  IFNULL(B.id, 'NoData') theme_tb_id
					,  IFNULL(B.today_theme_yn, 'N') today_theme_yn
				FROM (SELECT report_date
							,theme
							,issue
							,keyword
							,count(*) stock_cnt
							,MIN(id) id
						FROM sirianz_evening
						WHERE report_date = '$report_date'
						AND	  ( issue != '' or keyword != '' )
						GROUP BY report_date, theme, issue, keyword) A
				LEFT OUTER JOIN sirianz_evening_theme B
				ON B.report_date = A.report_date
				AND B.theme = A.theme
				AND B.issue = A.issue
				AND B.keyword = A.keyword
				ORDER BY A.id";
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$checked = $borderStyle = "";
		// 테마 등록 여부에 따라 인풋박스 스타일 변경
		if($row['theme_tb_id'] != 'NoData') $borderStyle = "border: 0.1rem solid #f6c23e";
		if($row['today_theme_yn'] == 'Y') {
			$borderStyle = "border: 0.2rem solid #f77d72;";
			$checked = " checked";
		}
			
		echo "<tr align=center>";
		echo "<td><input type=checkbox class=chk name=check$i value='Y' $checked></td>";
		echo "<td><input type=text name=theme$i value=\"".$row['theme']."\" style='width: 120px; $borderStyle'></td>";
		echo "<td><input type=text name=issue$i value=\"".$row['issue']."\" style='width: 120px; $borderStyle'></td>";
		echo "<td><input type=text name=keyword$i value=\"".$row['keyword']."\" style='width: 150px; $borderStyle'></td>";
		echo "<td align=right onclick=\"parent.getStockList('".$row['theme']."','".$row['issue']."','".$row['keyword']."')\"><b>".$row['stock_cnt']." 종목</b></td>";
		echo "<td><input type=text name=signal_id1_$i value=\"".$row['signal_id1']."\" style='width: 80px;'>";
		echo "<input type=text name=signal_id2_$i value=\"".$row['signal_id2']."\" style='width: 80px;'>";
		echo "<input type=text name=signal_id3_$i value=\"".$row['signal_id3']."\" style='width: 80px;'></td>";
		echo "<td align=left>".$row['title1']."</td>";
		echo "<td align=left>".$row['title2']."</td>";
		echo "<td align=left>".$row['title3']."</td>";
		echo "<input type=hidden name=org_theme$i value=\"".$row['theme']."\">";
		echo "<input type=hidden name=org_issue$i value=\"".$row['issue']."\">";
		echo "<input type=hidden name=org_keyword$i value=\"".$row['keyword']."\">";
		echo "<input type=hidden name=theme_tb_id$i value=\"".$row['theme_tb_id']."\">";
		echo "</tr>";
		$i++;
	}
	echo "</table>";
	echo "</div>";
	echo "<input type=hidden name=report_date value='$report_date'>";
	echo "<input type=hidden name=cnt value='$i'>";
	echo "<input type=hidden name=proc_fg>";
?>
</form>

</body>
<script>
function tgCk(i){
	var ckbx = document.getElementsByClassName('chk');
	ckbx[i].checked=!ckbx[i].checked;
}

function tgAllCk(){
	var ckbx = document.getElementsByClassName('chk');
	for(i=0;i<ckbx.length;i++){
		ckbx[i].checked=document.form1.ckAll.checked;
	}
}

// 키워드 등록
function save() {
	form = document.form1;
	form.proc_fg.value='themeSave';
	form.target = "iframeH";
	form.submit();
}

// 키워드 등록
function pick() {
	form = document.form1;
	form.proc_fg.value='themePick';
	form.target = "iframeH";
	form.submit();
}
</script>
<iframe name="iframeH" src = "sirianzEvening_script.php" width=100% height=100>
</html>