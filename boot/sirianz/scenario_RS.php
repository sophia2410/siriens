<?php
// 관심종목 + 시나리오 = 우측, 종목상세 화면

require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
<link rel="shortcut icon" href="#">
<style>
	.content {
	height: 400px;
	border: 0px solid hsl(0, 0%, 75%);
	}
	.content > img {
	width: auto;
	height: 100%;
	}
	input[type=radio] { margin-left: 5px }
	.scroll_box {
		width: 1800px;
		height: 183px;
		display: flex;
		overflow-x: auto;
	}
	table th, tr, td{
		padding: 0.1rem;
	}
</style>
</head>

<?php
$code = (isset($_GET['code'])) ? $_GET['code'] : '';
$name = (isset($_GET['name'])) ? $_GET['name'] : '';
$watchlist_date = (isset($_GET['watchlist_date'])) ? $_GET['watchlist_date'] : '';
$scenario_date  = (isset($_GET['scenario_date']))  ? $_GET['scenario_date']  : '';
$stock = (isset($_GET['stock'])) ? $_GET['stock'] : '';

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';
echo "<h4><font color=red><b>★ 질문 ★ 눌림 주고 다시 상승할 재료가 있는가? '사우론의 눈'이 가르킬만한 섹터 & 종목인가?</b></font></h4><br>";
?>

<body>
<form name="form1" method='POST' action='scenario_script.php'>
<?php
	if($watchlist_date == ''){
		echo "<h3></h3>";
	} else {

		// 종목 키워드
		$stock_keyword = "";
		$query = "SELECT CONCAT('#',keyword,' ') keyword
					FROM stock_keyword
				   WHERE code='".$code."'
				   ORDER BY id
		" ;
		
		$result = $mysqli->query($query);
		
		while( $row = $result->fetch_array(MYSQLI_BOTH) ){
			$stock_keyword .= $row['keyword'];
		}

		$stock_keyword .= ($stock_keyword != '') ? "<br>" : "";

		// 종목 코멘트
		$stock_comment = "";
		$query = "SELECT CONCAT(CASE WHEN LENGTH(comment) > 30 THEN '<br>' ELSE '' END,'#',comment,' ') comment 
					FROM stock_comment 
				   WHERE code='".$code."'
				   ORDER BY id
		" ;
		
		$result = $mysqli->query($query);
		
		while( $row = $result->fetch_array(MYSQLI_BOTH) ){
			$stock_comment .= $row['comment'];
		}

		// 최근뉴스 가져오기
		$query = "SELECT  date
						, title
						, link
					FROM signals B
					WHERE B.code = '$code'
					AND B.date >= (select DATE_FORMAT(DATE_ADD('$watchlist_date', INTERVAL -5 DAY), '%Y%m%d'))
					ORDER BY date DESC" ;

		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);

		$today_issue = "";
		while( $row = $result->fetch_array(MYSQLI_BOTH) ){
			$today_issue = '('.$row['date'].')'.$row['title'];
		}

		// 종목 시총, 부채율 등 구하기
		$query = "SELECT  STR_TO_DATE(A.trade_date, '%Y%m%d') trade_date_str
						, A.market_cap
						, A.op_ratio
						, A.lb_ratio
						, A.dt_ratio
					FROM mochaten A
					WHERE A.code =  '$code'
					AND A.trade_date = (SELECT MAX(trade_date) FROM mochaten WHERE code = '$code' AND trade_date <= '$watchlist_date')" ;

		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);

		$ref_date   = isset($row['trade_date_str']) ? "(기준일 : ".$row['trade_date_str'].")" : '';
		$market_cap = isset($row['market_cap']) ? "시총 ".number_format($row['market_cap'] )." 억 &nbsp; / &nbsp;" : '';
		$dt_ratio   = isset($row['dt_ratio']  ) ? "유통비율: ".$row['dt_ratio']." %," : '';
		$op_ratio   = isset($row['op_ratio']  ) ? "영업이익률: ".$row['op_ratio']." %," : '';
		$lb_ratio   = isset($row['lb_ratio']  ) ? "부채율: ".$row['lb_ratio']." % " : '';

		// 차트 이미지 + 종목 정보 출력
		$txat = (isset($comment['nomad'])) ? $comment['nomad'] : '';
		echo "<table border=1 class='table table-sm text-dark'>";
			// 차트 -- 네이버이미지
			echo "<tr><td style='width: 700px;' rowspan=7>";
			echo "<h4><b><a href='stock_B.php?code=$code&name=$name' onclick='window.open(this.href, \'stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>$name</b><h4>";
			echo "<img id='img_chart_area' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$code.".png?sidcode=1703839838123' width='700' height='289' alt='이미지 차트' onerror='this.src='https://ssl.pstatic.net/imgstock/chart3/world2008/error_700x289.png'>";
			echo "</td><td style='font-weight:bold;background-color:#fae4f1;'>";
			echo " $market_cap $dt_ratio $op_ratio $lb_ratio $ref_date";
			echo "</td></tr><tr><td class='text-danger' style='font-weight:bold;'>";
			echo "$stock_keyword";
			echo "$stock_comment";
			echo "</td></tr><tr><td>";
			echo "$today_issue";
			echo "</td></tr>";
		echo "</table>";

		$sub_query = "SELECT DATE_FORMAT(DATE_ADD('$watchlist_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
					WHERE date = (select max(date) from daily_price where date <= '$watchlist_date' AND code = '$code' limit 1)
					AND code = '$code'
					UNION ALL
					SELECT date, close FROM daily_price
					WHERE code = '$code'
					AND date <= (select max(date) from daily_price where date <= '$watchlist_date' AND code = '$code' limit 1)
					AND date > (select DATE_FORMAT(DATE_ADD('$watchlist_date', INTERVAL -1 YEAR), '%Y%m%d'))
					ORDER BY date DESC ";

		$query = "SELECT 
						(SELECT close FROM daily_price 
						WHERE date = '$watchlist_date'
						AND code = '$code'
						) AS close_amt, 
						(SELECT ROUND(AVG(close),0) 
						 FROM 
							(	$sub_query
								LIMIT 3
							) ncte
						) AS mavg3,
						(SELECT ROUND(AVG(close),0)
						 FROM 
							(	$sub_query
								LIMIT 5
							) ncte
						) AS mavg5,
						(SELECT ROUND(AVG(close),0)
						 FROM 
							(	$sub_query
								LIMIT 8
							) ncte
						) AS mavg8,
						(SELECT ROUND(AVG(close),0)
						 FROM 
							(	$sub_query
								LIMIT 10
							) ncte
						) AS mavg10,
						(SELECT ROUND(AVG(close),0)
						 FROM 
							(	$sub_query
								LIMIT 15
							) ncte
						) AS mavg15,
						(SELECT ROUND(AVG(close),0)
						 FROM 
							(	$sub_query
								LIMIT 20
							) ncte
						) AS mavg20,
						(SELECT ROUND(AVG(close),0)
						 FROM 
							(	$sub_query
								LIMIT 60
							) ncte
						) AS mavg60,
						(SELECT ROUND(AVG(close),0)
						 FROM 
							(	$sub_query
								LIMIT 120
							) ncte
						) AS mavg120,
						(SELECT ROUND(AVG(close),0)
						 FROM 
							(	$sub_query
								LIMIT 224
							) ncte
						) AS mavg224";
						
		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);
		$close_amt = "<font color=blue><b>".number_format($row['close_amt'])."</b></font>";
		$mavg3 = "<font color=blue><b>".number_format($row['mavg3'])."</b></font>";
		$mavg5 = "<font color=blue><b>".number_format($row['mavg5'])."</b></font>";
		$mavg8 = "<font color=blue><b>".number_format($row['mavg8'])."</b></font>";
		$mavg10 = "<font color=blue>".number_format($row['mavg10'])."</font>";
		$mavg15 = "<font color=blue>".number_format($row['mavg15'])."</font>";
		$mavg20 = "<font color=blue>".number_format($row['mavg20'])."</font>";
		$mavg60 = "<font color=blue>".number_format($row['mavg60'])."</font>";
		$mavg120 = "<font color=blue>".number_format($row['mavg120'])."</font>";
		$mavg224 = "<font color=blue>".number_format($row['mavg224'])."</font>";

		
		// 종목 이슈 + 시나리오
		echo "<table style='width:100%;'>
				<tr valign=top>
				<td width=85%'>";

					$query = "	SELECT STR_TO_DATE(A.watchlist_date, '%Y%m%d') watchlist_date_str
									, A.watchlist_date
									, A.code
									, A.name
									, A.regi_reason
									, CASE WHEN A.close_rate >= 0 THEN CONCAT('<font color=red> ▲',A.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',A.close_rate,'% </font>') END close_rate
									, CASE WHEN A.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',A.tot_trade_amt,'억</b></font>') ELSE  CONCAT(A.tot_trade_amt,'억') END tot_trade_amt
									, A.volume
									, A.market_cap
									, CASE WHEN A.sector IS NOT NULL THEN A.sector ELSE (SELECT sector FROM daily_watchlist WHERE watchlist_date < '$watchlist_date' AND code = A.code ORDER BY watchlist_date DESC LIMIT 1) END sector
									, CASE WHEN A.theme IS NOT NULL THEN A.theme ELSE (SELECT theme FROM daily_watchlist WHERE watchlist_date < '$watchlist_date' AND code = A.code ORDER BY watchlist_date DESC LIMIT 1) END theme
									, CASE WHEN A.issue IS NOT NULL THEN A.issue ELSE (SELECT issue FROM daily_watchlist WHERE watchlist_date < '$watchlist_date' AND code = A.code ORDER BY watchlist_date DESC LIMIT 1) END issue
									, CASE WHEN A.stock_keyword IS NOT NULL THEN A.stock_keyword ELSE (SELECT stock_keyword FROM daily_watchlist WHERE watchlist_date < '$watchlist_date' AND code = A.code ORDER BY watchlist_date DESC LIMIT 1) END stock_keyword
									, A.tracking_yn
									, A.tracking_reason
								FROM daily_watchlist A
								WHERE A.watchlist_date = '$watchlist_date'
								AND A.code = '$code'";

					// echo "<pre>$query</pre>";
					$result = $mysqli->query($query);
					$row = $result->fetch_array(MYSQLI_BOTH);

					$watchlist_date_str = $row['watchlist_date_str'];
					$regi_reason         = $row['regi_reason'];
					$close_rate     = $row['close_rate'];
					$volume         = $row['volume'];
					$tot_trade_amt  = $row['tot_trade_amt'];
					$sector 		= $row['sector'];
					$theme 			= $row['theme'];
					$issue 			= $row['issue'];
					$stock_keyword  = $row['stock_keyword'];
					$tracking_yn    = $row['tracking_yn'];
					$tracking_reason= $row['tracking_reason'];

					if($row['tracking_yn'] == 'Y') {
						$checkY = 'checked';
						$checkN = '';
					} else {
						$checkY = '';
						$checkN = 'checked';
					}
					$tracking_yn = "<input type=radio name=tracking_yn value='Y' $checkY>Y<input type=radio name=tracking_yn value='N' $checkN>N ";
					
					// 종목 상승 이유 등
					echo "<table class='table table-sm table-warning table-text-dark' border=0>";
					echo "<tr align=left>";
					echo "<td class='text-info' colspan=2>[관종일] <b>$watchlist_date_str</b>&nbsp;&nbsp;&nbsp;&nbsp;";
					echo "$close_rate &nbsp;&nbsp; $tot_trade_amt </td>";
					echo "<td> 관종이유 </td>";
					echo "<td><input type=text name=regi_reason value='$regi_reason'></td>";
					echo "<td> 섹터/테마/이슈/종목키워드 </td>";
					echo "<td style='width:35%'>";
					echo "<input type=text name=sector value='$sector' style='width:15%;'>";
					echo "<input type=text name=theme value='$theme' style='width:20%;'>";
					echo "<input type=text name=issue value='$issue' style='width:30%;'>";
					echo "<input type=text name=stock_keyword value='$stock_keyword' style='width:35%;'></td>";
					echo "<td><input type=button class='btn btn-danger btn-sm' onclick=\"save()\" value='저장'></td>";
					echo "</tr>";
					echo "<tr align=left>";
					echo "<td> 트래킹 </td>";
					echo "<td>$tracking_yn</td>";
					echo "<td>이유</td>";
					echo "<td colspan=6 style='width:70%;height:40px;'><textarea name='tracking_reason' style='width:99%; height:40px;'>$tracking_reason</textarea></td>";
					echo "<tr align=left style='background-color:white'>";
					echo "<td colspan=7></td>";
					echo "</tr>";

					// 다음 영업일 시나리오 보기
					$query = "	SELECT STR_TO_DATE(B.date, '%Y%m%d') scenario_date_str
									, A.scenario_date
									, CASE WHEN A.buy_pick = 'Y' THEN '매매대상' ELSE '' END buy_pick
									, A.scenario
									, A.buy_band
									, CASE WHEN A.buysell_yn = 'Y' THEN '매매' ELSE '' END buysell_yn
									, A.buysell_review
									, buysell_category
								FROM calendar B
								LEFT OUTER JOIN daily_watchlist_scenario A
								ON A.scenario_date =  B.date
								AND A.code = '$code'
								WHERE B.date = '$scenario_date'";

					// echo "<pre>$query</pre>";
					$result = $mysqli->query($query);
					$row = $result->fetch_array(MYSQLI_BOTH);

					$scenario_date_str=isset($row['scenario_date_str'])? $row['scenario_date_str']: '';
					$scenario        = isset($row['scenario']        ) ? $row['scenario']         : '';
					$buy_band        = isset($row['buy_band']        ) ? $row['buy_band']         : '';
					$buysell_review  = isset($row['buysell_review']  ) ? $row['buysell_review']   : '';
					$buysell_category= isset($row['buysell_category']) ? $row['buysell_category'] : '';

					if(isset($row['buy_pick']) && $row['buy_pick'] == 'Y') {
						$checkY = 'checked';
						$checkN = '';
					} else {
						$checkY = '';
						$checkN = 'checked';
					}
					$buy_pick = "<input type=radio name=buy_pick value='Y' $checkY>Y<input type=radio name=buy_pick value='N' $checkN>N ";

					if(isset($row['buysell_yn']) && $row['buysell_yn'] == 'Y') {
						$checkY = 'checked';
						$checkN = '';
					} else {
						$checkY = '';
						$checkN = 'checked';
					}
					$buysell_yn  = "<input type=radio name=buysell_yn  value='Y'>Y<input type=radio name=buysell_yn value='N' checked>N ";

					// 시나리오 작성 부분
					echo "<tr align=left>";
					echo "<td class='text-info' colspan=2 style='width:110px;'> [예정일] <b>$scenario_date_str</b></td>";
					echo "<td>(예상이평가)</td>";
					echo "<td colspan=8>[종가] $close_amt &nbsp; [3] $mavg3 &nbsp; [5] $mavg5 &nbsp; [8] $mavg8 &nbsp; [10] $mavg10 &nbsp; [15] $mavg15 &nbsp; [20] $mavg20 &nbsp; [60] $mavg60 &nbsp; [120] $mavg120 &nbsp; [224] $mavg224</td>";
					echo "</tr>";
					echo "<tr align=left>";
					echo "<td> 매매고려</td>";
					echo "<td>$buy_pick</td>";
					echo "<td rowspan=2> 시나리오</td>";
					echo "<td rowspan=2 colspan=4 style='height:80px;'><textarea name='scenario' style='width:99%; height:99%'>$scenario</textarea></td>";
					echo "</tr>";
					echo "<tr align=left>";
					echo "<td> 매수밴드</td>";
					echo "<td style='height:80px;'><textarea name='buy_band' style='width:99%; height:80px;'>$buy_band</textarea></td>";
					echo "</tr>";
					echo "<tr align=left style='background-color:white'>";
					echo "<td colspan=7></td>";
					echo "</tr>";
					echo "<tr align=left>";
					echo "<td> 매매여부 </td>";
					echo "<td>$buysell_yn</td>";
					echo "<td rowspan=2> 복기 </td>";
					echo "<td rowspan=2 colspan=4 style='height:40px;'><textarea name='buysell_review' style='width:99%; height:60px;'>$buysell_review</textarea></td>";
					echo "</tr>";
					echo "<tr align=left>";
					echo "<td> 매매유형 </td>";
					echo "<td><input type=text name='buysell_category' value='$stock_keyword'></td>";
					echo "</tr>";
					echo "</table>";
					
					// 과거 시나리오 보기
					$query = "	SELECT STR_TO_DATE(A.scenario_date, '%Y%m%d') scenario_date_str
									, A.scenario_date
									, A.mavg
									, CASE WHEN A.buy_pick = 'Y' THEN '매매대상' ELSE '' END buy_pick
									, A.scenario
									, A.buy_band
									, CASE WHEN A.buysell_yn = 'Y' THEN '매매' ELSE '' END buysell_yn
									, A.buysell_review
									, buysell_category
								FROM daily_watchlist_scenario A
								WHERE A.code = '$code'
								ORDER BY A.scenario_date DESC";
					$result = $mysqli->query($query);
					$row = $result->fetch_array(MYSQLI_BOTH);

					echo "<table class='table table-sm table-bordered small text-dark' style='font-size:90%'>";
					while($row = $result->fetch_array(MYSQLI_BOTH)) {

						echo "<tr>";
						echo "<td rowspan=3 style='width:110px;height:12px;' align=center>".$row['scenario_date_str']."</td>";
						echo "<td colspan=2>차트 : ".$row['mavg'].$row['buy_pick']."</td>";
						echo "</tr>";
						echo "<tr>";
						echo "<td>".$row['buy_band']."</td>";
						echo "<td>".$row['scenario']."</td>";
						echo "</tr>";
						echo "<tr>";
						echo "<td>".$row['buysell_yn']."</td>";
						echo "<td>".$row['buysell_review']."</td>";
						echo "</tr>";
					}
					echo "</table>";
		echo "	</td>
				<td width='15%'>
				<div style='margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;width=20%; height: calc(100vh - 500px);'>
					<iframe id='iframe' style='width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 500px);' src='scenario_RST.php?watchlist_date=$watchlist_date'>
					</iframe>
				</td>
			</tr>
		</table>";
	}

?>
<input type=hidden name=proc_fg>
<input type=hidden name=watchlist_date value=<?=$watchlist_date?>>
<input type=hidden name=scenario_date  value=<?=$scenario_date?>>
<input type=hidden name=stock value=<?=$stock?>>
<input type=hidden name=code value=<?=$code?>>
</form>
<iframe name="saveFrame" src="scenario_script.php" style='border:0px;' width=1000 height=700>
</iframe>
</body>

<script>
// 종목 시나리오 저장
function save() {
	form = document.form1;
	form.proc_fg.value = 'saveScenario';
	form.target = "saveFrame";
	form.submit();
}

// 관종등록일자 0일차 종목 가져오기
function getWatchlist(date) {
	form = document.form1;
	form.proc_fg.value = 'getWatchlist';
	form.watchlist_date.value = date;
	form.target = "saveFrame";
	form.submit();
}

// 입력 종목 추가하기
function addWatchlist(date, stock) {
	form = document.form1;
	form.proc_fg.value = 'addWatchlist';
	form.watchlist_date.value = date;
	form.stock.value = stock;
	form.target = "saveFrame";
	form.submit();
}

</script>
</html>