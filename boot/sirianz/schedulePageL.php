<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$schedule_cd = (isset($_GET['schedule_cd'])) ? $_GET['schedule_cd'] : "";
$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;

$query = " SELECT A.schedule_cd
				, A.schedule_theme_cd
				, A.schedule_theme_nm
				, C.code
				, C.name
				, IFNULL(D.zeroday_cnt,0) zeroday_cnt
			 FROM schedule_theme A
			INNER JOIN schedule_stock B
			   ON B.schedule_theme_cd = A.schedule_theme_cd
			INNER JOIN stock C
			   ON B.code = C.code
			LEFT OUTER JOIN (SELECT code, count(*) zeroday_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) D
			   ON D.code = C.code
			WHERE A.schedule_cd = '$schedule_cd'
			ORDER BY A.schedule_theme_cd, zeroday_cnt DESC";
// echo "<pre>$query</pre>";
$result = $mysqli->query($query);
?>

<body class="body">

<?php

	if($schedule_cd != '') {
		echo "<div>
			<a href='./schedule_summary.php?schedule_cd=$schedule_cd' class='popup' style='text-decoration:underline; ' onclick='window.open(this.href, 'STOCKWAY', 'width=700px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>[모아보기]</a>
		</dif>";
	}
	
	$pre_schedule_theme = "";
	echo "<table class='table table-sm small'>";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($pre_schedule_theme != $row['schedule_theme_cd']) {
			echo "<tr class='table-danger  text-dark' align=center><th><b>".$row['schedule_theme_nm']."</b></th></tr>";
		}
		
		echo "<td><a href=\"javascript:callFrameR('".$row['code']."','".$row['name']."')\">";
		
		if($row['zeroday_cnt'] > 0)
			echo "<font color='red'><b>".$row['name']."</b>(".$row['zeroday_cnt'].")</font>";
		else
			echo $row['name'];
		echo "</a></td>";
		echo "</tr>" ;
		
		$pre_schedule_theme =  $row['schedule_theme_cd'];
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameR(cd,nm) {
	window.parent.viewStock(cd,nm);
}
</script>
</html>
