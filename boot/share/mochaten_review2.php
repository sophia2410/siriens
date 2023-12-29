<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$mochaten_date = (isset($_GET['mochaten_date'])) ? $_GET['mochaten_date'] : date('Ymd',time());
$mochaten_date = "20230202";

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
	height: 500px;
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
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

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
				, D.stock_info
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
			INNER JOIN (SELECT code, chart_comment, case when chart_grade = '5' then '★★★★★' when chart_grade = '4' then '★★★★' else '' end  chart_grade FROM mochaten_comment WHERE regi_id = 'sophia' AND mochaten_date = '$mochaten_date' AND chart_comment != '') C
			   ON C.code = A.code
			 LEFT OUTER JOIN (SELECT code, CONCAT('#',groupby, ' #',issue,' #',stock_info)  AS stock_info FROM sophia_report  WHERE date = '$trade_date') D
			   ON D.code = A.code
		     LEFT OUTER JOIN (SELECT code, open_rate, high_rate, low_rate, close_rate, round(volume/1000,0) volume FROM daily_price WHERE date = '$trade_date') E
			   ON E.code = A.code
			ORDER BY chart_grade desc, tot_trade_amt";
//// echo $query;
$result = $mysqli->query($query);
?>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<!-- <div class="card header">
<h3 class="m-0 font-weight-bold text-primary">Today Pick</h3>
</div> -->

	<?php
		echo "<table class='table table-sm'  style='width:50%;'>";

		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			//모차십 대상 선정 데이터
			echo "<tr><td class='text-danger' colspan=18>".$row['name']." ( ".$row['sophia_grade']." )</td></tr>" ;
			echo "<tr><td colspan=18>".$row['stock_info']."</td></tr>" ;
			echo "<td width=80>시 총</td><td align=left>".number_format($row['market_cap'])." 억</td>";
			echo "<td width=80>등락율</td><td class='text-danger' align=left>".$row['close_rate']." %</td>" ;
			echo "<td width=80>거래대금</td><td align=left>".number_format($row['tot_trade_amt'])." 억</td>";
			echo "<td width=80>거래량</td><td align=left>".number_format($row['volume'])." K</td>";
			echo "</tr>" ;
			
			//이미지 확장자 bmp png 둘다 사용. bmp 확인 후 없을 경우 png 불러오기
			//서버별로 별도 적용
			//bmp 용량이 너무 커서 png만 사용		
			$filepath1 = $svpath.$trade_date."_".$row['code'].".png";

			//echo $filepath;
			echo "<tr><td colspan=8><article class='content'>
					<img src='$filepath1'/></td>
					<td colspan=10><article class='content'>
					</article></td></tr>";

			echo "<tr><td colspan=18>".$row['chart_comment']."<br><br></td></tr>" ;
		}

		echo "</table>";
	?>
</table>

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
		
		iframeL.src = "mochaten_L.php?mochaten_date="+key_val;
		return;
	}

	// 모차십 종목 선택 시 오른쪽 프레임에 내역 조회
	function viewMochaten(date, cd) {
		iframeR.src = "mochaten_R.php?mochaten_date="+date+"&code="+cd;
		return;
	}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
