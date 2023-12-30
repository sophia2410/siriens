<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$query = "SELECT  T.idx,
				  T.sector,
				  T.theme
			 FROM (
					SELECT  'A' idx,
						sector,
						theme
					FROM sophia_watchlist X
					WHERE watchlist_date >= (select DATE_FORMAT(DATE_ADD(now(), INTERVAL -5 DAY), '%Y%m%d'))
					AND (sector is not null AND sector != '')
					UNION ALL
					SELECT 'B' idx,
						sector,
						theme
					FROM sophia_watchlist Y
					WHERE (sector is not null AND sector != '')
				) T
			GROUP BY T.idx, T.sector, T.theme
			ORDER BY T.idx, T.sector, T.theme";
// echo $query."<br><br>";
$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm small">
<?php
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$bgstyle = ($row['idx'] =='A') ? "background-color:#F6F6F6;" : "";
		echo "<tr style='$bgstyle'>";
		echo "<td>".$row['sector']."</td>";
		echo "<td><a href=\"javascript:setPPG('".$row['sector']."','".$row['theme']."')\">".$row['theme']."</a></td>";
		echo "<td><a href='scenario_PopT.php?sector=".$row['sector']."&theme=".$row['theme']."' onclick='window.open(this.href, \'stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'> ≫  &nbsp;</a></td>";
		echo "</tr>" ;
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function setPPG(sector, theme) {
	ppg = window.parent.document.forms[0];
	ppg.sector.value = sector;
	ppg.theme.value = theme;
}

// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function popTheme(sector, theme) {
}
</script>
</html>
