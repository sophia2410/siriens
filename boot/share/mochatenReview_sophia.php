<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$mochaten_date = (isset($_POST['mochaten_date'])) ? $_POST['mochaten_date'] : '';

$query = " SELECT DISTINCT mochaten_date
			 FROM mochaten
			ORDER BY mochaten_date DESC
			LIMIT 30";

$result = $mysqli->query($query);
?>
<head>
<link rel="shortcut icon" href="#">
<style>
	.content {
	height: 450px;
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
	
<?php
if(isset($_GET['mainF'])) {
	require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
} else {
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_share.php");
}
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">
<form name="form1" method='POST' action='mochatenReview_sophia.php'>

<div style='border: 1px;' class="card-header py-3">
	<select id="mochaten_date" name="mochaten_date" class="select">
	<option value="">--choose an option--</option>
	<?php
		$option = "";
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			if($mochaten_date == $row['mochaten_date']) {
				$option .= "<option value='". $row['mochaten_date']."' selected>". $row['mochaten_date']."</option>";
			} else {
				$option .= "<option value='". $row['mochaten_date']."'>". $row['mochaten_date']."</option>";
			}
		}
		echo $option;
	?>
	</select>
	<button class="btn btn-primary btn-sm" onclick="searchMochaten()"> 조 회 </button>
</div>

<?php

// 모차십종목 차트일자 구하기
$query = "SELECT REPLACE(MAX(date), '-', '') pre_date FROM calendar WHERE date < '$mochaten_date'";
$result = $mysqli->query($query);
$row = $result->fetch_array(MYSQLI_BOTH);
$trade_date = $row['pre_date'];

// 종목 차트 이미지 경로 구하기
$query = "SELECT nm_sub1, nm_sub2 FROM comm_cd WHERE cd = 'PT001'";
$result = $mysqli->query($query);
$ifpath = $result->fetch_array(MYSQLI_BOTH);

$svpath = $ifpath['nm_sub1'];
$lcpath = $ifpath['nm_sub2'];
		

$query = " SELECT A.mochaten_date
				, A.code
				, A.name
				, C.chart_grade sophia_grade
				, C.chart_comment
				, D.stock_connect
				, E.close_rate AS today_close_rate
				, E.open_rate  AS today_open_rate
				, E.high_rate  AS today_high_rate
				, E.low_rate   AS today_low_rate
				, E.volume     AS today_volume
				, A.market_cap
				, A.close_rate
				, A.volume
				, A.tot_trade_amt
				, A.f_trade_amt
				, A.o_trade_amt
				, A.p_trade_amt
				, A.op_ratio
				, A.lb_ratio
			 FROM (SELECT mochaten_date
						, code
						, name
						, market_cap
						, close_rate
						, volume
						, tot_trade_amt
						, f_trade_amt
						, o_trade_amt
						, p_trade_amt
						, op_ratio
						, lb_ratio
					 FROM mochaten
					WHERE mochaten_date = '$mochaten_date'
					GROUP BY mochaten_date
							, code
							, name
							, market_cap
							, close_rate
							, volume
							, tot_trade_amt
							, f_trade_amt
							, o_trade_amt
							, p_trade_amt
							, op_ratio
							, lb_ratio ) A
			 LEFT OUTER JOIN (SELECT code, chart_comment, case when chart_grade = '5' then '(★★★★★)' when chart_grade = '4' then '(★★★★)' when chart_grade = '3' then '(★★★)' when chart_grade = '2' then '(★★)' when chart_grade = '1' then '(★)' else '' end  chart_grade FROM mochaten_comment WHERE regi_id = 'sophia' AND mochaten_date = '$mochaten_date') C
			   ON C.code = A.code
			 LEFT OUTER JOIN (SELECT code, CONCAT('#',Y.nm, ' #',Z.issue,' #',Z.stock_connect)  AS stock_connect FROM siriens_evening Z INNER JOIN comm_cd Y ON Y.cd = Z.theme WHERE report_date = '$trade_date') D
			   ON D.code = A.code
		     LEFT OUTER JOIN (SELECT code, open_rate, high_rate, low_rate, close_rate, round(volume/1000,0) volume FROM daily_price WHERE date = '$mochaten_date') E
			   ON E.code = A.code
			ORDER BY chart_grade desc, tot_trade_amt desc";
// echo $query;
$result = $mysqli->query($query);
?>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<!-- <div class="card header">
<h3 class="m-0 font-weight-bold text-primary">Today Pick</h3>
</div> -->

	<?php

		echo "<table class='table table-sm'  style='width:70%;'>";

		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			//모차십 대상 선정 데이터
			echo "<tr><td class='text-danger' colspan=18>".$row['name']." ".$row['sophia_grade']."</td></tr>" ;
			echo "<tr><td colspan=18>".$row['stock_connect']."</td></tr>" ;
			echo "<td width=80>시 총</td><td align=left>".number_format($row['market_cap'])." 억</td>";
			echo "<td width=80>등락율</td><td class='text-danger' align=left>".$row['close_rate']." %</td>" ;
			echo "<td width=80>거래대금</td><td align=left>".number_format($row['tot_trade_amt'])." 억</td>";
			echo "<td width=80>거래량</td><td align=left>".number_format($row['volume'])." K</td>";
			//결과 데이터
			echo "<td width=80>시가</td><td align=left>".$row['today_open_rate']." %</td>";
			echo "<td width=80>고가</td><td align=left>".$row['today_high_rate']." %</td>";
			echo "<td width=80>저가</td><td align=left>".$row['today_low_rate']." %</td>";
			echo "<td width=80>종가</td><td class='text-danger' align=left>".$row['today_close_rate']." %</td>" ;
			echo "<td width=80>거래량</td><td align=left>".number_format($row['today_volume'])." K</td>" ;
			echo "</tr>" ;
			
			//이미지 확장자 bmp png 둘다 사용. bmp 확인 후 없을 경우 png 불러오기
			//서버별로 별도 적용
			//bmp 용량이 너무 커서 png만 사용		
			$filepath1 = $svpath.$trade_date."_".$row['code'].".png";
			$filepath2 = $svpath.$mochaten_date."_".$row['code'].".png";

			//echo $filepath;
			echo "<tr><td colspan=8><article class='content'>
					<img src='$filepath1'/></td>
					<td colspan=10><article class='content'>
					<img src='$filepath2'/></td>
					</article></td></tr>";

			echo "<tr><td colspan=18>".$row['chart_comment']."<br><br></td></tr>" ;
		}

		echo "</table>";
	?>
</table>
</form>

</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<script>
// 모차십 일자 선택후 조회 - 왼쪽 프레임에 종목 리스트업
function searchMochaten() {
	key_val  = document.getElementById('mochaten_date').options[document.getElementById("mochaten_date").selectedIndex].value;
	console.log(key_val);
	
	document.form1.action = "mochatenReview_sophia.php?mainF=siriens";
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
