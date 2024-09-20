<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// 조회일자
$date = (isset($_GET['date']) ) ? $_GET['date'] : '';

// minute GET 변수 확인 및 현재 시간 구하기
if (isset($_GET['minute']) && $_GET['minute'] != '') {
    $minute = $_GET['minute'];
} else {
    // 현재 시간이 15:30 이후인지 확인
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');
    
    if ($currentHour > 15 || ($currentHour == 15 && $currentMinute > 30)) {
        // 현재 시간이 15:30 이후인 경우, 15:30으로 설정
        $minute = '1530';
    } else {
        // 그렇지 않은 경우, 현재 시간을 'Hi' 포맷으로 사용
        $minute = date('Hi');
    }
}

$specific_datetime = str_replace('-', '', $date).$minute;

?>

<body class="body">
<table class="table table-sm small">
<!-- 224일선 모아보기 위해 별도 sector 추가 -->
<tr align=right class='table-info'>
<td colspan=3 align=left><a href="javascript:callRealtime('kiwoomapi_opt10029_224','224', '')"><b>224선</b></a></td>
</tr>

<?php
// sector 별 종목 불러오기
// sector가 미등록된 종목은 '(미등록)' 으로 표시되게 함.
$query = "SELECT z.sector, z.theme, z.sort_theme, z.exp_amount, z.cnt, 
				SUM(z.exp_amount) OVER (PARTITION BY z.sector) sector_amount,
				SUM(z.cnt) OVER (PARTITION BY z.sector) sector_cnt
			FROM (
					SELECT IFNULL(w.sector, '(미등록)') sector, CASE WHEN (w.sector LIKE '0%' or w.sector LIKE '1%' or w.sector LIKE '5%') THEN w.theme ELSE '' END theme, min(w.sort_theme) as sort_theme, 
						   ROUND(SUM(exp_price * exp_vol)/100000000,2) exp_amount, count(*) cnt
					FROM kiwoom_opt10029 u
					LEFT OUTER JOIN watchlist_sophia w
					ON u.code = w.code
					WHERE u.date = '$date'
					AND (NOT ( w.sector LIKE '3%' 
					OR w.sector LIKE '4%' 
					OR w.sector LIKE '6%' 
					OR w.sector LIKE '7%' 
					OR w.sector LIKE '8%' 
					OR w.sector LIKE '9%') OR w.sector IS NULL)
					GROUP BY w.sector, w.theme) z
			ORDER BY z.sector, z.sort_theme";

// echo "<pre>$query</pre>";
$result = $mysqli->query($query);



// 출력된 종목명을 저장할 배열
$printed = array();
$pre_sector = "";
$pre_theme  = "";

while($row = $result->fetch_array(MYSQLI_BOTH)) {
	if($pre_sector != $row['sector']) {
		echo "<tr align=right class='table-danger'>";
		echo "<td align=left> <a href=\"javascript:callRealtime('kiwoomapi_opt10029','".$row['sector']."', '')\"><b>".$row['sector']."</b></a></td>" ;
		echo "<td>".$row['sector_cnt']."</td>" ;
		echo "<td>".number_format($row['sector_amount'],2)."</td>" ;
		echo "</tr>";
	}
	if($row['theme'] != '' && $pre_theme != $row['sector'].$row['theme']) {
		echo "<tr align=right>";
		echo "<td align=left> <a href=\"javascript:callRealtime('kiwoomapi_opt10029','".$row['sector']."','".$row['theme']."')\"><b>".$row['theme']."</b></a></td>" ;
		echo "<td>".$row['cnt']."</td>" ;
		echo "<td>".number_format($row['exp_amount'],2)."</td>" ;
		echo "</tr>" ;
	}
	$pre_sector = $row['sector'];
	$pre_theme  = $row['sector'].$row['theme'];
}
?>
</table>

<!-- <?php 
	echo "<pre>$query</pre>";
?> -->
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callRealtime(pgmId, sector, theme) {
	window.parent.viewRealtime(pgmId, sector, theme);
}
</script>
</html>