<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$signal_page = (isset($_GET['signal_page'])) ? $_GET['signal_page'] : '';
$key_val = explode("/", $signal_page);
$page_date = $key_val[0];
$page_fg = $key_val[1];

?>
<head>
<link rel="shortcut icon" href="#">
<style>
	.content {
	height: 600px;
	border: 0px solid hsl(0, 0%, 75%);
	}
	.content > img {
	width: auto;
	height: 100%;
	}
	input[type=radio] { margin-left: 5px }
</style>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content" style='margin-left:15px;'>

<form name="form1" method='POST' action='getSignalReport_script.php'>
<?php
	echo "<div style='height:15px;'></div>";

	// report 조회
	$query = " SELECT A.id
					, A.page_date  report_date
					, A.date       date
					, DATE_FORMAT(A.date, '%Y-%m-%d') news_date
					, A.signal_grp signal_grp_str
					, A.theme      theme_str
					, A.grouping   grouping_str
					, A.keyword    keyword
					, B.title
					, A.code
					, A.stock name
					, B.content
					, A.link
					, E.close_rate
					, E.open_rate
					, E.high_rate
					, E.low_rate
					, E.volume
					, E.amount
					, A.today_rank
				FROM rawdata_siri_report A
				INNER JOIN signals B
				   ON B.signal_id = A.signal_id
				 LEFT OUTER JOIN (SELECT code, open_rate, high_rate, low_rate, close_rate, round(volume/1000,0) volume, floor(amount / 100000000) amount FROM daily_price WHERE date = '$page_date') E
				   ON E.code = A.code
			    WHERE page_date = '$page_date'
				  AND page_fg = '$page_fg'
				  AND today_pick = 'Y'
				  ORDER BY A.today_rank, E.close_rate desc, E.amount desc";
				  
				// 화면 출력용  -- 옵시디언용으로 변경위해 주석처리 2023.10.19
				//ORDER BY today_rank, signal_grp_str, theme_str, case when E.close_rate is null then 999 else E.close_rate end DESC 
	// echo $query;

	$result = $mysqli->query($query);

	// 옵시디언용 출력 2023.10.19  -- 옵시디언용 주석처리 2023.11.20
	// while($row = $result->fetch_array(MYSQLI_BOTH)) {
	// 	echo "<br>";
	// 	echo "(".$row['date'].") ";
	// 	echo "[".$row['title']."](".$row['link'].")";
	// 	echo "<br>";
	// 	echo "<pre>".$row['content']."</pre>";
	// 	echo "<br>";
	// }

	// 화면 출력용  -- 옵시디언용으로 변경위해 주석처리 2023.10.19	
	// 다시 사용 처리 -- 2023.11.20
	echo "<table class='table table-sm table-borderless small text-dark' style='width:1600px;'>";
	$pre_code = "";
	$pre_theme_str = "";
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if( $pre_theme_str != $row['theme_str'] ) {
			echo "<tr>";
			echo "<td colspan=3 style='height:5px;'>&nbsp</td>";
			echo "</tr>";
			echo "<tr>";
			echo "<td colspan=3 class='h3 font-weight-bold'>[".$row['theme_str']."]</td>";
			echo "</tr>";
		}

		if($row['code'] != '' && $pre_code !=  $row['code']) {
			
			echo "<tr valign=center>";
			echo "<td class='mark text-danger font-weight-bold' style='font-size: 0.9rem; width:300px'>".$row['name']."</td>";
			echo "<td class='mark text-danger font-weight-bold' style='font-size: 0.9rem; width:500px' align=left>(".$row['close_rate']."%) (".number_format($row['amount'])."억 / ".number_format($row['volume'])."K) </td>";
			echo "<td>&nbsp;</td>";
			echo "</tr>";
			// echo "<tr>";
			// echo "<td colspan=3 class='h3 font-weight-bold'>![](https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$row['code'].".png?sidcode=)</td>";
			// echo "</tr>";
		}
		
		if($row['title'] != '') {
			echo "<tr valign=center>";
			echo "<td colspan=3 class='font-weight-bold' style='font-size: 0.9rem;'>(".$row['news_date'].") ".$row['title']."</td>";
			echo "</tr><tr>";
			echo "<td colspan=3>".$row['content']."</td>";
			echo "</tr><tr>";
			echo "<td colspan=3>&nbsp</td>";
			echo "</tr>";
		}

		$pre_theme_str = $row['theme_str'];
		$pre_code = $row['code'];
	}
	echo "</table>";


	// report 조회
	$query = " SELECT A.signal_grp signal_grp_str
					, A.theme      theme_str
					, A.grouping   grouping_str
					, MIN(A.today_rank) today_rank
				FROM rawdata_siri_report A
			   WHERE page_date = '$page_date'
				 AND page_fg = '$page_fg'
				 AND today_pick = 'Y'
			   GROUP BY A.signal_grp, A.theme, A.grouping
			   ORDER BY today_rank, signal_grp_str, theme_str ";
	echo $query;

	$result = $mysqli->query($query);

	echo "<div>";
	echo "<input type=button value='테마저장' onclick='savetheme()'>";
	echo "<input type=button value='닫기' onclick='window.close()'>";
	echo "</div>";
	echo "<table class='table table-sm table-borderless small text-dark' style='width:800px;'>";

	$i = 0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		echo "<tr>";
		echo "<td><input type=hidden name=signal_grp$i value='".$row['signal_grp_str']."'><input type=text name=signal_grp_str$i value='".$row['signal_grp_str']."' style='width:200px'></td>";
		echo "<td><input type=hidden name=theme$i      value='".$row['theme_str']."'>     <input type=text name=theme_str$i      value='".$row['theme_str']."'      style='width:200px'></td>";
		echo "<td><input type=hidden name=grouping$i   value='".$row['grouping_str']."'>  <input type=text name=grouping_str$i   value='".$row['grouping_str']."'   style='width:300px'></td>";
		echo "<td><input type=text name=today_rank$i   value='".$row['today_rank']."'></td>";
		echo "</tr>";

		$i++;
	}
	echo "</table>";
	echo "<input type=hidden name=total_cnt value=$i>";
	echo "<input type=hidden name=proc_fg>";
	echo "<input type=hidden name=page_date value='$page_date'>";
	echo "<input type=hidden name=page_fg   value='$page_fg'>";
?>
</form>


</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<script>
// 일자 선택후 조회
function savetheme() {
	document.forms[0].proc_fg.value = 'UT'; //Update theme
	document.forms[0].submit();
}
</script>
<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
