<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
</head>

<?php
$infostock_date = (isset($_GET['infostock_date'])) ? $_GET['infostock_date'] : '';
?>

<body>
<form name="form1" method='POST' action='infostock_Theme_script.php' onsubmit="return false">
<!-- <input type='button' id='savebtn' class="btn btn-danger btn-sm" onclick='saveThemeGropu()' value='저 장'></td> -->
<?php
	// report 조회
	$query = " SELECT id
					, str3 today_theme
					, str7 issue
					, REPLACE(str8, '\n','<br><br>') detail
				 FROM rawdata_infostock 
				WHERE str1 = 'Daily_Theme_Group'
				  AND str2 = '$infostock_date'";
	// echo $query;
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered small text-dark'>";
	echo "<tr align=center>";
	echo "<th style='width:300px;'>테마</th>";
	echo "<th style='width:300px;'>이슈</th>";
	echo "<th>상세</th>";
	echo "</tr>";
	
	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$input_group  = "<input type='text' class='text' id='today_theme' name='today_theme$i' value='".$row['today_theme']."' style='width:300px'>";
		echo "<tr>";
		echo "<td align=center>".$input_group."<input type=hidden name='id$i' value='".$row['id']."'></td>";
		echo "<td>".$row['issue']."</td>";
		echo "<td>".$row['detail']."</td>";
		echo "</tr>";

		$i++;
	}
	echo "</table>";
	echo "<input type=hidden name=infostock_date value='$infostock_date'>";
	echo "<input type=hidden name=rowcnt value='$i'>";
	echo "<input type=hidden name=proc_fg>";
?>
</form>

<script>
function saveThemeGropu() {
	form = document.form1;
	form.proc_fg.value='saveTM';
	form.target = "iframeH";
	form.submit();
}
</script>
<iframe name="iframeH" src = "infostock_Theme_script.php" width=100% height=200>
</body>
</html>