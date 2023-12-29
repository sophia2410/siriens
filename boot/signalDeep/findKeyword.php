<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$findDB   = (isset($_GET['findDB']) )  ? $_GET['findDB']   : 'siri';
$searchFg = (isset($_GET['searchFg'])) ? $_GET['searchFg'] : 'table';
$keyword  = (isset($_GET['keyword']))  ? $_GET['keyword']  : '';

$check1 = ($findDB == 'siri') ? 'checked' : '';
$check2 = ($findDB == 'infostock') ? 'checked' : '';

$checka = ($searchFg == 'table') ? 'checked' : '';
$checkb = ($searchFg == 'list')  ? 'checked' : '';
?>
<style>
	.scroll_box {
		width: 95%;
		height: 1200PX;
		display: flex;
		overflow-x: auto;
	}
	table#xdys thead { position: sticky; top: 0; z-index: 1; }
	table#xdys th:first-child,
	table#xdys td:first-child { position: sticky; left: 0; }
</style>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_sirianz.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">
	
<form id="form" method=post>
<?php
echo "[키워드] <input type=text name=keyword value='$keyword' onkeydown='keyDown()' style='width:150px;border: 0.1rem solid;zoom:1.1;background-color: #fdeaea;'> &nbsp; / &nbsp;";
echo "[검색기준] <input type=radio name=findDB   value='siri'  $check1>시그널이브닝  <input type=radio name=findDB  value='infostock' $check2>인포스탁 &nbsp;&nbsp; / &nbsp;";
echo "[조회방법] <input type=radio name=searchFg value='table' $checka>테이블 		<input type=radio name=searchFg value='list'	 $checkb>리스트 &nbsp;&nbsp;";
echo "<input type=button class='btn btn-danger btn-sm' value='조 회' onclick='search()'>";

$orderby = ($searchFg == 'table') ? "A.report_date, A.code DESC" : "A.code, A.report_date"; 
if($keyword != '') {
	if($findDB == 'siri') {
		$query = " SELECT A.report_date, A.code, B.name, A.signal_grp theme, A.issue, A.stock_CPR detail, C.close_rate, D.volume, D.tot_trade_amt
					FROM (SELECT report_date, code, MIN(id) id
							FROM sirianz_evening
							WHERE (signal_grp LIKE CONCAT('%','$keyword','%') OR issue LIKE CONCAT('%','$keyword','%'))
							GROUP BY report_date, code
						  ) A0
					INNER JOIN sirianz_evening A
					ON A.id = A0.id
					INNER JOIN stock B
					ON B.code = A.code
					AND B.last_yn = 'Y'
					INNER JOIN daily_price C
					ON C.date = A.report_date
					AND C.code = A.code
					LEFT OUTER JOIN mochaten D
					ON D.trade_date = A.report_date
					AND D.cha_fg = 'MC000'
					AND D.code = A.code
					ORDER BY $orderby";
	} else if($findDB == 'infostock') {
		$query = " SELECT A.report_date, A.code, B.name, A.theme, A.issue, A.detail, C.close_rate, D.volume, D.tot_trade_amt
					FROM (SELECT V.report_date, V.code, MAX(V.theme) theme, MAX(V.issue) issue, MAX(V.detail) detail
							FROM (	SELECT Z.report_date, X.code, Z.today_theme_nm theme, '' issue, '' detail
										FROM sirianz_infostock_theme Z, sirianz_infostock X
										WHERE Z.today_theme_nm LIKE CONCAT('%','$keyword','%')
										AND X.report_date = Z.report_date
										AND X.today_theme_cd = Z.today_theme_cd
									UNION ALL
									SELECT X.report_date, X.code, '' theme, X.issue, X.detail
										FROM sirianz_infostock X
										WHERE X.issue LIKE CONCAT('%','$keyword','%') 
								  ) V
							GROUP BY V.report_date, V.code
					) A
					INNER JOIN stock B
					ON B.code = A.code
					AND B.last_yn = 'Y'
					INNER JOIN daily_price C
					ON C.date = A.report_date
					AND C.code = A.code
					LEFT OUTER JOIN mochaten D
					ON D.trade_date = A.report_date
					AND D.cha_fg = 'MC000'
					AND D.code = A.code
					ORDER BY $orderby";
	}
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	echo "<div class='scroll_box' style='overflow-x: auto; white-space: nowrap;'>";
	echo "<table id=xdys style='width:100%;' class='table table-sm table-bordered text-dark'>";
	
	// X 일자, Y 종목으로 보기
	if($searchFg == 'table') {
		$arr_date  = array();
		$arr_stock = array();
		$arr_rate  = array();
		$arr_amt   = array();

		$i=0;
		$r=1;
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			//일자/종목 배열에 담기
			$arr_date[$row['report_date']] = 1;
			$arr_stock[$row['code']] = $row['name'];
			$arr_rate[$row['code']][$row['report_date']] = $row['close_rate'];
			$arr_amt[$row['code']][$row['report_date']]  = $row['tot_trade_amt'];
		}
		$header = "<thead><tr><th>종목</th>";
		$x=0;
		foreach($arr_date as $key=>$val){
			$header .= "<th>".substr($key,0,4)."<br>".substr($key,4,4)."</th>";
			$header_date[$x] = $key;
			$x++;
		}
		$header .= "</tr></thead>";

		$y=0;
		foreach($arr_stock as $key=>$val){
			$stock_cd[$y] = $key;
			$y++;
		}

		$body = "<tbody>";
		for($j=0; $j<$y; $j++)
		{
			$YKey = $stock_cd[$j];
			// $body .= "<tr><td>$arr_stock[$YKey]</td>";
			$body .= "<tr><td><a href='../sirianz/stock_B.php?code=".$YKey."&name=".$arr_stock[$YKey]."' onclick='window.open(this.href, 'stock', 'width=1800px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>".$arr_stock[$YKey]."</a></td>";

			$stock_rate = $arr_rate[$YKey];

			for($i=0; $i<$x; $i++) {
				$XKey = $header_date[$i];
				if(isset($stock_rate[$XKey]))
					$body .= "<td>$stock_rate[$XKey]</td>";
				else 
					$body .= "<td>&nbsp</td>";
			}
			$body .= "</tr></tbody>";
		}

		echo $header;
		echo $body;
	}
	// 종목 리스트 뿌리기
	else {
	
		$i=0;
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			echo "<td>".$row['code']."</td>";

			// 거래대금에 따라 스타일 적용
			if($row['tot_trade_amt'] > 2000)
				$amt_style = "mark text-danger font-weight-bold h6";
			else if($row['tot_trade_amt'] > 1000)
				$amt_style = "text-danger font-weight-bold h6";
			else
				$amt_style = "font-weight-bold";

			echo "<td class='$amt_style'>".$row['name']."</td>";
			
			echo "<td>".$row['report_date']."</td>";
			echo "<td>".number_format($row['volume'])."K</td>";
			echo "<td>".number_format($row['tot_trade_amt'])."억</td>";
			echo "<td>".$row['theme']."</td>";
			echo "<td>".$row['issue']."</td>";
			echo "<td>".$row['detail']."</td>";
			echo "</tr>";
		}
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
	$PATH = "https://yunseul0907.cafe24.com";
}
?>
<script>
// 일자 선택후 조회
function search() {
	// key_val  = document.getElementById('report_mon').options[document.getElementById("report_mon").selectedIndex].value;
	findDB  = document.forms[0].findDB.value;
	searchFg = document.forms[0].searchFg.value;
	keyword  = document.forms[0].keyword.value;
	document.forms[0].action = "./findKeyword.php?keyword="+keyword+"&findDB="+findDB+"&findDB="+findDB+"&searchFg="+searchFg;
	document.forms[0].submit();
	return;
}

// 검색조건 엔터키 입력
function keyDown(){
	if (event.key == 'Enter') {
		search();
	}
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
