<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_mon   = (isset($_GET['report_mon']) )  ? $_GET['report_mon']   : date('Ym',time());
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
echo "<input type=button class='btn btn-danger btn-sm' value='조 회' onclick='search()'>";

	$query = " SELECT A.report_date
					, A.code
					, B.name
					, A.close_rate
					, ROUND(A.volume/1000) volume
					, ROUND(A.tot_trade_amt/10000) tot_trade_amt
					, L.last_keyword
					, E.eveninng_group
					, E.eveninng_issue
					, I.infostock_theme
					, I.infostock_issue
					FROM (SELECT REPLACE(Z.date,'-','') report_date
								, Z.code
								, Z.amount tot_trade_amt
								, Z.volume
								, Z.close_rate
						   FROM daily_price Z
						  INNER JOIN (SELECT code, MAX(volume) volume, count(*) cnt
									FROM daily_price
									GROUP BY code ) Y
							ON Y.code   = Z.code
							AND Y.volume = Z.volume
							AND Y.cnt > 20		-- 상장 20일 이후 조회
							AND Y.volume > 500000	-- 거래량 50만 이상 조회
							WHERE Z.date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')) A 
					INNER JOIN stock B
					   ON B.code = A.code
					  AND B.last_yn = 'Y'
					LEFT OUTER JOIN (SELECT J.code
										  , GROUP_CONCAT(K.keyword_nm ORDER BY K.keyword_fg, K.keyword_cd) last_keyword
									   FROM siriens_report_keyword J
									  INNER JOIN theme_keyword K
										 ON K.keyword_cd = J.keyword_cd
									  INNER JOIN (SELECT MAX(trade_date) report_date, code FROM mochaten WHERE trade_date <= (select concat(substr(yyyymmdd,1,4), substr(yyyymmdd,6,2), substr(yyyymmdd,9,2)) from (select DATE_ADD(now(), INTERVAL -1 DAY) yyyymmdd) X) AND cha_fg = 'MC000' GROUP BY code) I
										 ON I.report_date = J.report_date
										AND I.code = J.code
									  GROUP BY J.report_date, J.code) L
					   ON L.code = A.code
					LEFT OUTER JOIN (SELECT Z.report_date
										, Z.code
										, Y.today_theme_nm  infostock_theme
										, Z.issue			infostock_issue
									FROM siriens_infostock Z
									LEFT OUTER JOIN siriens_infostock_theme Y
									ON Z.today_theme_cd = Y.today_theme_cd
									WHERE Z.report_date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')
									AND Z.issue IS NOT NULL ) I
					   ON I.report_date = A.report_date
					  AND I.code = A.code
					LEFT OUTER JOIN (SELECT Z.report_date
										, Z.code
										, Z.signal_grp eveninng_group
										, Z.issue	   eveninng_issue
									FROM siriens_evening  Z
									INNER JOIN (SELECT report_date, code, max(id) id FROM siriens_evening WHERE report_date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31') GROUP BY report_date, code) Y
									   ON Y.id = Z.id
									WHERE Z.report_date BETWEEN CONCAT('$report_mon','01') AND CONCAT('$report_mon','31')) E
					   ON E.report_date = A.report_date
					  AND E.code = A.code
					ORDER BY A.report_date, A.close_rate DESC";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered small text-dark'>";
	echo "<tr align=center>";
	echo "<th width=50><input type=checkbox name=ckAll onclick='comTgAllCk(this)'></th>";
	echo "<th width=250>종목</th>";
	echo "<th width=80>거래일자</th>";	
	echo "<th width=80>등락률</th>";	
	echo "<th style='minWidth:80px'>거래량</th>";
	echo "<th style='minWidth:80px'>거래대금</th>";
	echo "<th>최근이슈</th>";
	echo "<th>이브닝그룹</th>";
	echo "<th>이브닝이슈</th>";
	echo "<th>인포테마</th>";
	echo "<th>인포이슈</th>";
	echo "</tr>";
	
	$i = 0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {

		// 상한가, 천만주, 천억 종목의 경우 별도 표시
		if($row['close_rate'] > 29 || $row['volume'] > 10000 || $row['tot_trade_amt'] > 1000) {
			$stock_style = "text-danger font-weight-bold";
		} else {
			$stock_style = "text-secondary font-weight-bold";
		}

		echo "<tr>";
		echo "<td align=center><input type=checkbox class=chk name=check$i value='Y'></td>";
		echo "<td><a href='../siriens/stock_B.php?code=".$row['code']."&name=".$row['name']."' class='"."$stock_style"."' onclick='window.open(this.href, 'stock', 'width=1800px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>".$row['code']." - ".$row['name']."</a><input type=hidden name=code$i value=\"".$row['code']."\"></td>";
		echo "<td align=right>".$row['report_date']."</td>";
		echo "<td align=right>".$row['close_rate']."</td>";
		echo "<td align=right>".number_format($row['volume'])."</td>";
		echo "<td align=right>".number_format($row['tot_trade_amt'])."</td>";
		echo "<td onclick='tgCk($i)'>".$row['last_keyword']."</td>";
		echo "<td onclick='tgCk($i)'>".$row['eveninng_group']."</td>";
		echo "<td>".$row['eveninng_issue']."</td>";
		echo "<td onclick='tgCk($i)'>".$row['infostock_theme']."</td>";
		echo "<td>".$row['infostock_issue']."</td>";
		echo "</tr>";

		$i++;
	}
	echo "</table>";
?>
</form>

</body>
<script>
// 일자 선택후 조회
function search() {
	report_mon  = document.getElementById('report_mon').options[document.getElementById("report_mon").selectedIndex].value;
	document.forms[0].action = "./findMaxVolume.php?report_mon="+report_mon;
	document.forms[0].submit();
	return;
}
</script>
</html>