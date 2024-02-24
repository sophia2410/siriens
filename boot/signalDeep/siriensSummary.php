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
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
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
echo "<input type=checkbox id='tenbillion' value='Y' $checked1> 1000억 ↑ / ";
echo "&nbsp;";
echo "<input type=checkbox id='tenbillionOR' value='Y' $checked2> 1000억 ↑ or 1000만주 ↑ ";
echo "&nbsp;&nbsp;&nbsp;";
echo "<input type=checkbox id='onlyTitle' value='Y' $checkedTitle> 타이틀만 보기 ";
echo "&nbsp;";
echo "<input type=button class='btn btn-danger btn-sm' value='조 회' onclick='search()'>";

// 시그널이브닝 타이틀 모아보기
if($onlyTitle == 'Y')
{
	$query = " SELECT STR_TO_DATE(A.date, '%Y%m%d') report_date, DAYOFWEEK(A.date) - 2 arr_idx , B.evening_subject
					, '' today_theme_cd, '' today_theme_nm, '' today_theme_rank, '' code, '' name, 0 close_rate, 0 volume, 0 tot_trade_amt
				FROM calendar A
				LEFT OUTER JOIN siriens_report B
				  ON B.report_date = A.date
				WHERE A.date >= (SELECT PERIOD_ADD('$report_mon', -3)*100+1)
				  AND A.date <= CONCAT('$report_mon','31')
				ORDER BY A.date";
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);
} 
// 주도주 달력으로 보기
else { 
	$sub_query = ($tenbillion   == 'Y') ? " AND C.tot_trade_amt >= 1000" : "";
	$sub_query = ($tenbillionOR == 'Y') ? " AND ( C.volume >= 10000 OR C.tot_trade_amt >= 1000) " : $sub_query;

	// 테마 - 인포스탁 기준으로 뽑아오기
	// $query = " SELECT STR_TO_DATE(A.date, '%Y%m%d') report_date, DAYOFWEEK(A.date) - 2 arr_idx , B.evening_subject, D.today_theme_cd, D.today_theme_nm, D.today_theme_rank, C.code, C.name, C.close_rate, C.volume, C.tot_trade_amt
	// 			FROM calendar A
	// 			LEFT OUTER JOIN siriens_report B
	// 			ON B.report_date = A.date
	// 			INNER JOIN (SELECT M.code, M.name, CH.trade_date, CH.close_rate, CH.volume, CH.tot_trade_amt
	// 						FROM   stock M, mochaten CH
	// 						WHERE  CH.trade_date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
	// 						AND	CH.cha_fg = 'MC000'
	// 						AND	CH.code = M.code
	// 						AND M.last_yn = 'Y'
	// 						) C
	// 			ON C.trade_date = A.date
	// 			LEFT OUTER JOIN (SELECT Z.report_date, IFNULL(Z.today_theme_cd,'-') today_theme_cd, IFNULL(Z.today_theme_rank,99) today_theme_rank, IFNULL(Z.today_theme_nm,'-') today_theme_nm, X.code
	// 								FROM siriens_infostock_theme Z, siriens_infostock X
	// 								WHERE Z.report_date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
	// 								AND X.report_date = Z.report_date
	// 								AND X.today_theme_cd = Z.today_theme_cd
	// 								AND EXISTS (SELECT * FROM mochaten W WHERE W.trade_date = X.report_date AND W.code = X.code)) D
	// 			ON D.report_date = C.trade_date
	// 			AND D.CODE = C.code
	// 			WHERE A.date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
	// 			$sub_query
	// 			ORDER BY A.date, D.today_theme_rank, D.today_theme_nm, C.close_rate DESC";

	// 테마 - 키워드 기준으로 뽑아오기
	// $query = " SELECT STR_TO_DATE(A.date, '%Y%m%d') report_date, DAYOFWEEK(A.date) - 2 arr_idx , B.evening_subject, D.today_theme_cd, D.today_theme_nm, D.today_theme_rank, C.code, C.name, C.close_rate, C.volume, C.tot_trade_amt, C.stock_keyword
	// 			FROM calendar A
	// 			LEFT OUTER JOIN siriens_report B
	// 			ON B.report_date = A.date
	// 			INNER JOIN (SELECT M.code, M.name, CH.trade_date, CH.close_rate, CH.volume, CH.tot_trade_amt, CH.stock_keyword
	// 						FROM   stock M, mochaten CH
	// 						WHERE  CH.trade_date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
	// 						AND	CH.cha_fg = 'MC000'
	// 						AND	CH.code = M.code
	// 						AND M.last_yn = 'Y'
	// 						) C
	// 			ON C.trade_date = A.date
	// 			LEFT OUTER JOIN (SELECT report_date
	// 									, code
	// 									, GROUP_CONCAT(G.keyword_cd ORDER BY G.sort_no, G.keyword_cd) today_theme_cd
	// 									, GROUP_CONCAT(G.keyword_nm ORDER BY G.sort_no, G.keyword_cd) today_theme_nm
	// 									, 0 today_theme_rank
	// 								FROM siriens_report_keyword F
	// 								INNER JOIN theme_keyword G
	// 								ON F.keyword_cd = G.keyword_cd 
	// 								AND G.keyword_fg IN ('G','C')
	// 								GROUP BY report_date, code) D
	// 			ON  D.report_date = C.trade_date
	// 			AND D.code = C.code
	// 			WHERE A.date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
	// 			$sub_query
	// 			ORDER BY A.date, D.today_theme_rank, D.today_theme_nm, C.stock_keyword, C.close_rate DESC";

	// 테마 - 시리언즈이브닝 기준 뽑아오기
	$query = " SELECT STR_TO_DATE(A.date, '%Y%m%d') report_date, DAYOFWEEK(A.date) - 2 arr_idx , B.evening_subject, D.today_theme_cd, D.today_theme_nm, D.today_theme_rank, C.code, C.name, C.close_rate, C.volume, C.tot_trade_amt, C.stock_keyword
				FROM calendar A
				LEFT OUTER JOIN siriens_report B
				ON B.report_date = A.date
				INNER JOIN (SELECT M.code, M.name, CH.trade_date, CH.close_rate, CH.volume, CH.tot_trade_amt, CH.stock_keyword
							FROM   stock M, mochaten CH
							WHERE  CH.trade_date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
							AND	CH.cha_fg = 'MC000'
							AND	CH.code = M.code
							AND M.last_yn = 'Y'
							) C
				ON C.trade_date = A.date
				LEFT OUTER JOIN (SELECT report_date
										, code
										, GROUP_CONCAT(G.keyword_cd ORDER BY G.sort_no, G.keyword_cd) today_theme_cd
										, GROUP_CONCAT(G.keyword_nm ORDER BY G.sort_no, G.keyword_cd) today_theme_nm
										, 0 today_theme_rank
									FROM siriens_report_keyword F
									INNER JOIN theme_keyword G
									ON F.keyword_cd = G.keyword_cd 
									AND G.keyword_fg IN ('G','C')
									GROUP BY report_date, code) D
				ON  D.report_date = C.trade_date
				AND D.code = C.code
				WHERE A.date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
				$sub_query
				ORDER BY A.date, D.today_theme_rank, D.today_theme_nm, C.stock_keyword, C.close_rate DESC";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);
}

// 화면 출력 부분
$pre_date = '';
$pre_theme = '';
$input = array();

$i=0;
$r=1;
while($row = $result->fetch_array(MYSQLI_BOTH)) {
	//일자, 주제, 내용으로 출력될 수 있도록 조정
	$r1 = ($r*3)-2;
	$r2 = ($r*3)-1;
	$r3 = $r*3;

	// 상한가, 천만주, 천억 종목의 경우 별도 표시
	if($row['close_rate'] > 29 || $row['volume'] > 10000 || $row['tot_trade_amt'] > 1000) {
		$stock_style = "text-danger font-weight-bold";
	} else {
		$stock_style = "text-secondary font-weight-bold";
	}

	if($pre_date != $row['report_date']) {
		if($i != 0) {
			$input[$r3][$arr_idx] .= "</table>";

			//월요일인 경우 row 증가시키기
			if($row['arr_idx'] == 0) {
				$r++;

				$r1 = ($r*3)-2;
				$r2 = ($r*3)-1;
				$r3 = $r*3;
			}
		}

		$arr_idx = $row['arr_idx'];
		$pre_theme = '';

		$input[$r1][$arr_idx]  = "<b><center>".$row['report_date']."</center></b>";
		$input[$r2][$arr_idx]  = "<b>".$row['evening_subject']."</b>";
		$input[$r3][$arr_idx]  = "<table style='width:100%' class='table table-light table-sm table-bordered small text-dark'>";
		$input[$r3][$arr_idx] .= "<tr class='mark font-weight-bold'><td colspan=4>".$row['today_theme_nm']."</td></tr>";
		// $input[$r3][$arr_idx] .= "<tr class='mark'><td colspan=4>".$row['issue']."</td></tr>";
		$input[$r3][$arr_idx] .= "<tr style='height:10px'><td title='".$row['stock_keyword']."'>";
		$input[$r3][$arr_idx] .= "<a href='../siriens/stock_B.php?code=".$row['code']."&name=".$row['name']."' class='"."$stock_style"."' onclick='window.open(this.href, 'stock', 'width=1800px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>".$row['name']."</a></td>";
		$input[$r3][$arr_idx] .= "<td align=right>".$row['close_rate']." %</td>";
		$input[$r3][$arr_idx] .= "<td align=right>". number_format($row['volume'])."K</td><td align=right>". number_format($row['tot_trade_amt'])."억</td>";
		$input[$r3][$arr_idx] .= "</tr>";

		$pre_theme = $row['today_theme_cd'];
	} else {
		if($pre_theme != $row['today_theme_cd']) {
			$input[$r3][$arr_idx] .= "<tr class='mark font-weight-bold'><td colspan=4>".$row['today_theme_nm']."</td></tr>";
			// $input[$r3][$arr_idx] .= "<tr class='mark'><td colspan=4>".$row['issue']."</td></tr>";
		}
		
		$input[$r3][$arr_idx] .= "<tr style='height:10px'><td title='".$row['stock_keyword']."'>";
		$input[$r3][$arr_idx] .= "<a href='../siriens/stock_B.php?code=".$row['code']."&name=".$row['name']."' class='"."$stock_style"."' onclick='window.open(this.href, 'stock', 'width=1800px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>".$row['name']."</a></td>";
		$input[$r3][$arr_idx] .= "<td align=right>".$row['close_rate']." %</td>";
		$input[$r3][$arr_idx] .= "<td align=right>". number_format($row['volume'])."K</td><td align=right>". number_format($row['tot_trade_amt'])."억</td>";
		$input[$r3][$arr_idx] .= "</tr>";
		$pre_theme = $row['today_theme_cd'];
	}

	$pre_date = $row['report_date'];
	$i++;
}

$input[$r3][$arr_idx] .= "</table>";

for($tr=1; $tr<=$r; $tr++) {
	echo "<div>";
	echo "<table style='width:100%;' class='table table-bordered table-sm text-dark'>";

	$ti = $tr*3 -2;
	for($ti; $ti<=$tr*3; $ti++)
	{
		// 타이틀만 보기인 경우는 종목 내역 출력하지 않기  -------------
		if($onlyTitle == 'Y' && $ti%3 == 0) break;
		// 타이틀만 보기인 경우는 종목 내역 출력하지 않기  -------------

		// 일자 row class 지정
		if($ti%3 == 1)	$trStyle = "style='height:30px;background-color: #faf6f5'";
		else $trStyle = "";

		echo "<tr $trStyle>";
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
	report_mon  = document.getElementById('report_mon').options[document.getElementById("report_mon").selectedIndex].value;
	tenbillion = '';
	if(document.getElementById('tenbillion').checked   == true) tenbillion = 'Y';
	tenbillionOR = '';
	if(document.getElementById('tenbillionOR').checked == true) tenbillionOR = 'Y';
	onlyTitle = '';
	if(document.getElementById('onlyTitle').checked    == true) onlyTitle  = 'Y';
	document.forms[0].action = "./siriensSummary.php?report_mon="+report_mon+"&tenbillion="+tenbillion+"&tenbillionOR="+tenbillionOR+"&onlyTitle="+onlyTitle;
	document.forms[0].submit();
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
