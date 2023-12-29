<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
</head>

<?php
$infostock_date = (isset($_GET['infostock_date'])) ? $_GET['infostock_date'] : '';

$input_row_id = "<input type='hidden' id='row_id' name='row_id'>";
?>

<body>
<form name="form1" method='POST' action='infostock_Theme_script.php' onsubmit="return false">
<!-- <input type='button' id='savebtn' class="btn btn-danger btn-sm" onclick='saveTheme()' value='저 장'></td>
<input type='button' id='procbtn' class="btn btn-danger btn-sm" onclick='procTheme()' value='반 영'></td> -->
<?php
	// report 조회
	$query = " SELECT min(id) id
					, str3 today_theme
					, str4 theme_nm
					, theme_cd
				 FROM rawdata_infostock 
				WHERE str1 = 'Daily_Theme_Stock'
				  AND str2 = '$infostock_date'
				GROUP BY today_theme, theme_nm, theme_cd
				ORDER BY id
				";
	// echo $query;
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered small text-dark' width=100%>";
	echo "<tr align=center>";
	echo "<th style='width:200px;'>테마그룹</th>";
	echo "<th style='width:300px;'>테마명</th>";
	echo "<th>테마코드</th>";
	echo "</tr>";

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$input_group  = "<input type='text' class='text' id='today_theme' name='today_theme$i' value='".$row['today_theme']."' style='width:250px'>";

		echo "<tr>";
		echo "<td align=center>".$input_group."</td>";
		echo "<td>".$row['theme_nm']."<input type=hidden name='theme_nm$i' value='".$row['theme_nm']."'</td>";
		echo "<td>".$row['theme_cd']."</td>";
		echo "</tr>";
		
		$i++;
	}
	echo "</table>";
	echo "<input type=hidden name=infostock_date value='$infostock_date'>";
	echo "<input type=hidden name=rowcnt value='$i'>";
	echo "<input type=hidden name=proc_fg>";
?>
</form>

</body>
<script>
function saveTheme() {
	form = document.form1;
	form.proc_fg.value='save';
	form.target = "iframeH";
	form.submit();
}

function procTheme() {
	form = document.form1;
	form.proc_fg.value='proc';
	form.target = "iframeH";
	form.submit();
}
</script>
<iframe name="iframeH" src = "infostock_Theme_script.php" width=100% height=200>
</html>