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
<form name="form1" method='POST' action='infostock_Stock_script.php' onsubmit="return false">
<input type='button' id='savebtn' class="btn btn-danger btn-sm" onclick='setCode()' value='코드등록'></td>
<?php
	// report 조회
	$query = " SELECT str5 code
					, str6 name
					, max(str7) issue
					, REPLACE(max(str8), '\n','<br><br>') detail
				 FROM rawdata_infostock 
				WHERE str1 in ('Daily_Stock', 'Daily_Kospi', 'Daily_Kosdaq')
				  AND str2 = '$infostock_date'
				  AND (str5 = '' or str5 IS null)
				GROUP BY str5, str6
				ORDER BY id";
	// echo $query;
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered small text-dark'>";
	echo "<tr align=center>";
	echo "<th style='width:200px;'>종목</th>";
	echo "<th style='width:300px;'>이슈</th>";
	echo "</tr>";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		echo "<tr>";
		echo "<td>[".$row['code']."] ".$row['name']."</td>";
		echo "<td>".$row['issue']."</td>";
		echo "</tr>";
	}
	echo "</table>";
	echo "<input type=hidden name=infostock_date value='$infostock_date'>";
	echo "<input type=hidden name=proc_fg>";
?>
</form>

</body>
<script>
function setCode() {
	form = document.form1;
	form.proc_fg.value='setCode';
	form.target = "iframeH";
	form.submit();
}

function procStock() {
	form = document.form1;
	form.proc_fg.value='proc';
	form.target = "iframeH";
	form.submit();
}
</script>
<iframe name="iframeH" src = "infostock_Stock_script.php" width='95%' height=100>
</html>