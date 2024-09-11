<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_mon   = (isset($_GET['report_mon']) )  ? $_GET['report_mon']   : date('Ym',time());
$tenbillion   = (isset($_GET['tenbillion']) )  ? $_GET['tenbillion']   : 'Y';
$tenbillionOR = (isset($_GET['tenbillionOR'])) ? $_GET['tenbillionOR'] : '';
$onlyTitle    = (isset($_GET['onlyTitle'])  )  ? $_GET['onlyTitle']    : '';

$checked1 = ($tenbillion   == 'Y') ? ' checked' : '';
$checked2 = ($tenbillionOR == 'Y') ? ' checked' : '';
$checkedTitle = ($onlyTitle == 'Y') ? ' checked' : '';
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
?>

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
echo "<select id='report_mon' class='select'>";

while($row = $result->fetch_array(MYSQLI_BOTH)) {
	if($report_mon == $row['yyyymm']) {
		$option .= "<option value='". $row['yyyymm']."' selected>". $row['yyyymm_str']."</option>";
	} else {
		$option .= "<option value='". $row['yyyymm']."'>". $row['yyyymm_str']."</option>";
	}
}
echo $option;
echo "</select> ";
echo "&nbsp;";
echo "<input type=button class='btn btn-danger btn-sm' value='조 회' onclick='search()'>";

// 마켓인덱스 모아보기
$query = " SELECT STR_TO_DATE(A.date, '%Y%m%d') report_date, DAYOFWEEK(A.date) - 2 arr_idx , B.evening_report_title
				, C.index_cd, C.index_nm, C.index_value, C.index_diff, C.index_close_rate
			FROM calendar A
			LEFT OUTER JOIN siriens_report B
			ON B.report_date = A.date
			LEFT OUTER JOIN infostock_market_index C
			ON C.report_date = A.date
			WHERE A.date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
			ORDER BY A.date, C.index_cd";

// echo "<pre>$query</pre>";
$result = $mysqli->query($query);

$wk = 1;
$pre_date = '';
$mi_value = array();
//$mi_value[날자카운트]['마켓인덱스코드값'][속성] = 값
while($row = $result->fetch_array(MYSQLI_BOTH)) {

	$di = $row['arr_idx'];

	if($pre_date != $row['report_date']) {
		if($pre_date != '' && $di == '0')
			$wk++;	// 금요일이면 다음주차 증가시키기

		$mi_value[$wk][$di]['rd'] = $row['report_date'];
		$mi_value[$wk][$di]['ai'] = $row['arr_idx'];
		$mi_value[$wk][$di]['es'] = $row['evening_report_title'];
	}

	// // 1주차 첫 거래일이 월요일이 아닌 경우 배열 만들어주기
	// if($wk == '') {
	// 	for($i=0; $i<$di; $i++) {
			
	// 		$mi_value[$wk][$i]['rd'] = "";
	// 		$mi_value[$wk][$i]['es'] = "";
	// 		$mi_value[$wk][$i][$row['index_cd']]['va'] = "";
	// 		$mi_value[$wk][$i][$row['index_cd']]['df'] = "";
	// 		$mi_value[$wk][$i][$row['index_cd']]['rt'] = "";
	// 	}
	// }

	// echo "wk=".$wk." \n";
	// echo "di=".$di." \n";
	// // post 방식으로 넘어온 변수 확인
	// foreach($mi_value[$wk][$di] as $key=>$val){
	// 	echo "$key =>  $val \n";
	// }

	$mi_value[$wk][$di][$row['index_cd']]['nm'] = $row['index_nm'];
	$mi_value[$wk][$di][$row['index_cd']]['va'] = $row['index_value'];
	$mi_value[$wk][$di][$row['index_cd']]['df'] = $row['index_diff'];
	$mi_value[$wk][$di][$row['index_cd']]['rt'] = $row['index_close_rate'];

	$pre_date = $row['report_date'];
}

// 마켓인덱스 모아보기
$query = " SELECT lvl, cd, nm, m_cd, nm_sub1, nm_sub2
			FROM comm_cd
			WHERE l_cd = 'A0000'
			AND lvl in ('M','S')
			AND cd > 'A0009'
			ORDER BY cd";

// echo "<pre>$query</pre>";
$result = $mysqli->query($query);

$ri = 0;
while($row = $result->fetch_array(MYSQLI_BOTH)) {
	if($row['lvl'] == 'M'){
		$m_cd_nm = $row['nm'];
	} else {
		$mk_name[$ri]['m_nm'] = $m_cd_nm;
		$mk_name[$ri]['cd'] = $row['cd'];
		$mk_name[$ri]['nm'] = $row['nm'];
		$mk_name[$ri]['st'] = $row['nm_sub1'];
		$mk_name[$ri]['bg'] = $row['nm_sub2'];
		$ri++;
	}
}
echo $ri;
// 화면 출력 부분
echo "<table style='width:98%;' class='table table-bordered table-sm text-dark small'>";

for($twk=1; $twk<$wk; $twk++) { //거래일 주차만큼 돌면서 출력
	//일자출력
	echo "<tr>";
	echo "<td colspan=2 align=center>거래일</td>";

	for($r=0; $r<5; $r++) {
		if(isset($mi_value[$twk][$r])) echo "<td colspan=3 align=center style='width:16%'>".$mi_value[$twk][$r]['rd']."</td>";
		else echo "<td colspan=3 style='width:16%'> </td>";
	}
	echo "<td align=center>거래일</td>";
	echo "</tr>";

	//타이틀 출력
	echo "<tr>";
	echo "<td colspan=2 align=center></td>";

	for($r=0; $r<5; $r++) {
		if(isset($mi_value[$twk][$r])) echo "<td colspan=3 align=center>".$mi_value[$twk][$r]['es']."</td>";
		else echo "<td colspan=3> </td>";
	}
	echo "<td align=center></td>";
	echo "</tr>";

	for($i=0; $i<$ri; $i++) { // 마켓인덱스 row 수만큼 출력
		// 해당 row에서 출력할 마켓인덱스 코드 가져오기
		$midx = $mk_name[$i]['cd'];

		echo "<tr style='".$mk_name[$i]['bg']."'>";
		echo "<td align=center>".$mk_name[$i]['m_nm']."</td>";
		echo "<td>".$mk_name[$i]['nm']."</td>";
		
		for($r=0; $r<5; $r++) {
			if(isset($mi_value[$twk][$r][$midx])) {
				if(strstr($mi_value[$twk][$r][$midx]['df'],'▼')) $fontStyle="class='text-primary ".$mk_name[$i]['st']."'";
				else if(strstr($mi_value[$twk][$r][$midx]['df'],'▲')) $fontStyle="class='text-danger ".$mk_name[$i]['st']."'";
				else $fontStyle="class='".$mk_name[$i]['st']."'";

				echo "<td align=right $fontStyle>".$mi_value[$twk][$r][$midx]['va']."</td>";
				echo "<td align=right $fontStyle>".$mi_value[$twk][$r][$midx]['df']."</td>";
				echo "<td align=right $fontStyle>".$mi_value[$twk][$r][$midx]['rt']."</td>";
			}
			else echo "<td colspan=3> </td>";
		}
		echo "<td>".$mk_name[$i]['nm']."</td>";
		echo "</tr>";
	}
}
echo "</table>";

// while($row = $result->fetch_array(MYSQLI_BOTH)) {
// 	//일자, 주제, 내용으로 출력될 수 있도록 조정
// 	$r1 = ($r*3)-2;
// 	$r2 = ($r*3)-1;
// 	$r3 = $r*3;

// 	if($pre_date != $row['report_date']) {
// 		if($i != 0) {
// 			$input[$r3][$arr_idx] .= "</table>";

// 			// 마켓 인덱스 표시하기

// 			//월요일인 경우 row 증가시키기
// 			if($row['arr_idx'] == 0) {
// 				$r++;

// 				$r1 = ($r*3)-2;
// 				$r2 = ($r*3)-1;
// 				$r3 = $r*3;
// 			}
// 		}

// 		$arr_idx = $row['arr_idx'];
// 		$pre_theme = '';

// 		$input[$r1][$arr_idx]  = "<b><center>".$row['report_date']."</center></b>";
// 		$input[$r2][$arr_idx]  = "<b>".$row['evening_report_title']."</b>";
// 		$input[$r3][$arr_idx]  = "<table style='width:100%' class='table table-light table-sm table-bordered small text-dark'>";
// 		$input[$r3][$arr_idx] .= "<tr style='height:10px'>";
// 		$input[$r3][$arr_idx] .= "<td align=left>".$row['index_nm']." %</td>";
// 		$input[$r3][$arr_idx] .= "<td align=right>".$row['index_value']."</td>";
// 		$input[$r3][$arr_idx] .= "<td align=right>".$row['index_diff']."</td>";
// 		$input[$r3][$arr_idx] .= "<td align=right>".$row['index_close_rate']."</td>";
// 		$input[$r3][$arr_idx] .= "</tr>";
// 	} else {
// 		$input[$r3][$arr_idx] .= "<tr style='height:10px'>";
// 		$input[$r3][$arr_idx] .= "<td align=left>".$row['index_nm']." %</td>";
// 		$input[$r3][$arr_idx] .= "<td align=right>".$row['index_value']."</td>";
// 		$input[$r3][$arr_idx] .= "<td align=right>".$row['index_diff']."</td>";
// 		$input[$r3][$arr_idx] .= "<td align=right>".$row['index_close_rate']."</td>";
// 		$input[$r3][$arr_idx] .= "</tr>";
// 	}

// 	$pre_date = $row['report_date'];
// 	$i++;
// }

// $input[$r3][$arr_idx] .= "</table>";

// for($tr=1; $tr<=$r; $tr++) {
// 	echo "<div>";
// 	echo "<table style='width:100%;' class='table table-bordered table-sm text-dark'>";

// 	$ti = $tr*3 -2;
// 	for($ti; $ti<=$tr*3; $ti++)
// 	{
// 		// 타이틀만 보기인 경우는 종목 내역 출력하지 않기  -------------
// 		if($onlyTitle == 'Y' && $ti%3 == 0) break;
// 		// 타이틀만 보기인 경우는 종목 내역 출력하지 않기  -------------

// 		// 일자 row class 지정
// 		if($ti%3 == 1)	$trStyle = "style='height:30px;background-color: #faf6f5'";
// 		else $trStyle = "";

// 		echo "<tr $trStyle>";
// 		for($td=0; $td<5; $td++) {
// 			if(!isset($input[$ti][$td])) {
// 				echo "<td style='width:20%'>&nbsp;</td>";
// 			} else {
// 				echo "<td style='width:20%'>".$input[$ti][$td]."</td>";
// 			}
// 		}
// 		echo "</tr>";	
// 	}
// 	echo "</table>";
// 	echo "</div>";
// }

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
	report_mon  = document.getElementById('report_mon').options[document.getElementById("report_mon").selectedIndex].value;
	document.forms[0].action = "./marketIndex.php?report_mon="+report_mon;
	document.forms[0].submit();
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
