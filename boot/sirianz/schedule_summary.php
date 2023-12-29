<!DOCTYPE html>
<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");

$schedule_cd = (isset($_GET['schedule_cd'])) ? $_GET['schedule_cd'] : '';
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
<?php
	// 시그널리포트 불러오기
	$query = " SELECT S.schedule_cd
					, S.schedule_nm
					, A.schedule_theme_cd
					, A.schedule_theme_nm
					, C.code
					, C.name
					, IFNULL(D.zeroday_cnt,0) zeroday_cnt
					, F.keyword
					, H.comment
				FROM schedule S
				INNER JOIN schedule_theme A
				ON S.schedule_cd = A.schedule_cd
				INNER JOIN schedule_stock B
				ON B.schedule_theme_cd = A.schedule_theme_cd
				INNER JOIN stock C
				ON B.code = C.code
				LEFT OUTER JOIN (SELECT code, count(*) zeroday_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) D
				ON D.code = C.code
				INNER JOIN (SELECT code, GROUP_CONCAT(keyword ORDER BY id SEPARATOR '#') keyword FROM stock_keyword E WHERE EXISTS (SELECT * FROM schedule_stock Z WHERE Z.schedule_theme_cd LIKE CONCAT ('$schedule_cd', '%') AND Z.code = E.code) GROUP BY code) F
				ON F.code = C.code
				INNER JOIN (SELECT code, GROUP_CONCAT(comment ORDER BY id SEPARATOR '<br>') comment FROM stock_comment G WHERE EXISTS (SELECT * FROM schedule_stock Z WHERE Z.schedule_theme_cd LIKE CONCAT ('$schedule_cd', '%') AND Z.code = G.code) GROUP BY code) H
				ON H.code = C.code
				WHERE S.schedule_cd = '$schedule_cd'
				ORDER BY A.schedule_theme_cd, zeroday_cnt DESC";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {

		if($i == 0) {
			echo "<div><h3>".$row['schedule_nm']."<h3></div>";
			echo "<table width='1000px' cellpadding=5 rowpadding=2 style='font-size:13px;'>";
			echo "<tr style='background-color:#f0f0f0;'>
				  <th>테마</th><th>종목</th><th>키워드/코멘트</th></tr>";
		}

		echo "<tr><td rowspan=2>".$row['schedule_theme_nm']."</td>";

		if($row['zeroday_cnt'] > 0)
			echo "<td rowspan=2><font color='red'><b>".$row['name']."</b>(".$row['zeroday_cnt'].")</font></td>";
		else
			echo "<td rowspan=2>".$row['name']."</td>";

		echo "<td>".$row['keyword']."</td>";
		echo "</tr>";

		echo "<tr><td>".$row['comment']."</td></tr>";

		$i++;
	}

	echo "</table>";
?>

</form>
</body>
<script>
function modifyNews() {
	document.forms[0].proc_fg.value ='NM';
	document.forms[0].submit();
}
function confirmNews() {
	if(confirm('뉴스확인완료 하시겠습니까?')) {
		document.forms[0].proc_fg.value ='NC';
		document.forms[0].submit();
	}
}

</script>
</html>