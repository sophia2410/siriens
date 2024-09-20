<?php
// 관심종목 + 시나리오 = 우측, 테마조회 화면

require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$issue_mon   = (isset($_GET['issue_mon']) )  ? $_GET['issue_mon'] : date('Ym',time());
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">
	
<form id="form" method=post>
<?php

// 조회 월 구하기
$query = "  SELECT CONCAT(yyyy, mm) yyyymm, CONCAT(yyyy,'년',mm,'월') yyyymm_str
			FROM calendar
			WHERE date < now()
			GROUP BY yyyy, mm
			ORDER BY yyyy DESC,mm DESC
			LIMIT 20";
$result = $mysqli->query($query);

$option = "";
echo "<select id='issue_mon' class='select'>";

while($row = $result->fetch_array(MYSQLI_BOTH)) {
	if($issue_mon == $row['yyyymm']) {
		$option .= "<option value='". $row['yyyymm']."' selected>". $row['yyyymm_str']."</option>";
	} else {
		$option .= "<option value='". $row['yyyymm']."'>". $row['yyyymm_str']."</option>";
	}
}
echo $option;
echo "</select> ";
echo "&nbsp;";
echo "<input type=button class='btn btn-danger btn-sm' value='조 회' onclick='search()'>";
// 테마 - 소피아관종 기준 뽑아오기
$query = " SELECT STR_TO_DATE(A.date, '%Y%m%d') issue_date, DAYOFWEEK(A.date) - 2 arr_idx
				, CONCAT('[코스피]', CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',B.close_rate,'% </font>') END,' / ') kospi_index
				, CONCAT('[코스닥]', CASE WHEN C.close_rate >= 0 THEN CONCAT('<font color=red> ▲',C.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',C.close_rate,'% </font>') END) kosdaq_index
				, E.evening_report_title
				, F.today_theme
			FROM calendar A
			LEFT OUTER JOIN market_index B
			on B.date = A.date
			and B.market_fg = 'KOSPI'
			LEFT OUTER JOIN market_index C
			on C.date = A.date
			and C.market_fg = 'KOSDAQ'
			LEFT OUTER JOIN market_report E
			ON E.report_date = A.date
			LEFT OUTER JOIN (SELECT 0day_date
									, GROUP_CONCAT('<tr class=\'font-weight-bold\'><td>', CASE WHEN hot_theme = 'Y' THEN CONCAT('<font color=red>',today_theme,'</font>') ELSE today_theme END ,'</td></tr>' ORDER BY hot_theme DESC, tot_trade_amt DESC SEPARATOR '') today_theme
								FROM (SELECT 0day_date, hot_theme
										, CASE WHEN theme is null OR  theme = '' THEN sector ELSE CONCAT (sector, ' > ' , theme) END today_theme
										, SUM(tot_trade_amt) tot_trade_amt
									  FROM 0day_stocks A
									  WHERE 0day_date BETWEEN CONCAT('$issue_mon','01') AND CONCAT('$issue_mon','31')
									  GROUP BY 0day_date, hot_theme, theme, sector
									  ORDER BY 0day_date, hot_theme DESC, tot_trade_amt DESC) Z
									  GROUP BY 0day_date
							) F
			ON  F.0day_date = A.date
			WHERE A.date BETWEEN CONCAT('$issue_mon','01') AND CONCAT('$issue_mon','31')
			ORDER BY A.date, F.today_theme ";

// echo "<pre>$query</pre>";
$result = $mysqli->query($query);

// 화면 출력 부분
$pre_date = '';
$pre_theme = '';
$input = array();

$i=0;	
$r=1;
while($row = $result->fetch_array(MYSQLI_BOTH)) {
	//일자, 주제, 지수, 테마으로 출력될 수 있도록 조정
	$r1 = ($r*4)-3;
	$r2 = ($r*4)-2;
	$r3 = ($r*4)-1;
	$r4 = $r*4;

	//월요일인 경우 row 증가시키기
	if($row['arr_idx'] == 0) {
		$r++;

		$r1 = ($r*4)-3;
		$r2 = ($r*4)-2;
		$r3 = ($r*4)-1;
		$r4 = $r*4;
	}
		$arr_idx = $row['arr_idx'];

		$input[$r1][$arr_idx]  = "<b><center>".$row['issue_date']."</center></b>";
		$input[$r2][$arr_idx]  = "<b class='small'>".$row['evening_report_title']."</b>";
		$input[$r3][$arr_idx]  = $row['kospi_index']."".$row['kosdaq_index'];
		$input[$r4][$arr_idx]  = "<table style='width:100%;' class='table table-light table-sm table-bordered small text-dark'>";
		// $input[$r4][$arr_idx] .= "<tr style='vertical-align:top;'class='mark font-weight-bold'>".$row['today_theme']."</td></tr>";
		$input[$r4][$arr_idx] .= $row['today_theme'];
		$input[$r4][$arr_idx] .= "</table>";

	$i++;
}
// echo var_dump ($input);

// 주차수만큼 돌리기
for($tr=1; $tr<=$r; $tr++) {
	echo "<div>";
	echo "<table style='width:100%;' class='table table-bordered table-sm text-dark'>";

	// 한 주차의 row 수만큼 돌리기 (4행으로 구성됨)
	$ti = $tr*4-3;
	for($ti; $ti<=$tr*4; $ti++)
	{
		// 일자 row class 지정
		if($ti%4 == 1)	$trStyle = "style='height:30px;background-color: #faf6f5'";
		else $trStyle = "";

		echo "<tr $trStyle align=center>";
		for($td=0; $td<5; $td++) {
			if(!isset($input[$ti][$td])) {
				echo "<td style='width:20%'>&nbsp;</td>";
			} else {
				echo "<td style='width:20%'>".$input[$ti][$td]."</td>";
			}
		}
		echo "</tr>";	
	}
	echo "</table>";
	echo "</div>";
}

?>
</form>

</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<?php
if($_SERVER["HTTP_HOST"] == 'localhost') {
	$PATH = "http://localhost";
} else {
	$PATH = "https://siriens.mycafe24.com";
}
?>

<script>
// 일자 선택후 조회
function search() {
	issue_mon  = document.getElementById('issue_mon').options[document.getElementById("issue_mon").selectedIndex].value;
	document.forms[0].action = "./scenario_RT.php?issue_mon="+issue_mon;
	document.forms[0].submit();
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
