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
<form>
<?php
	// report 조회
	$query = " SELECT str5 code
					, str6 name
					, max(str7) issue
					, REPLACE(max(str8), '\n','<br><br>') detail
				 FROM rawdata_infostock 
				WHERE str1 in ('Daily_Stock', 'Daily_Kospi', 'Daily_Kosdaq')
				  AND str2 = '$infostock_date'
				  AND str5 != ''
				GROUP BY str5, str6
				ORDER BY id";
	// echo $query;
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered small text-dark'>";
	echo "<tr align=center>";
	echo "<th style='width:200px;'>종목</th>";
	echo "<th style='width:300px;'>이슈</th>";
	echo "<th>상세</th>";
	echo "</tr>";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		echo "<tr>";
		echo "<td>[".$row['code']."] ".$row['name']."</td>";
		echo "<td>".$row['issue']."</td>";
		echo "<td>".$row['detail']."</td>";
		echo "</tr>";
	}
	echo "</table>";
?>
</form>

</body>
</html>