<?php
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
$trade_date = (isset($_GET['trade_date'])) ? $_GET['trade_date'] : '';
$start_date = (isset($_GET['tracking_index'])) ? substr($_GET['tracking_index'],0, 8) : ''; 

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';
echo "<h4><font color=red><b>★ 질문 : 왜 더 가야하지? 눌림 주고 다시 상승할 재료가 있는가? '사우론의 눈'이 가르킬만한 섹터 & 종목인가?</b></font></h4><br>";
?>

<body>
<form name="form1" method='POST' action='scenario_script.php'>
<?php
	if($trade_date == ''){
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
					WHERE B.code =  '$code'
					AND B.date >= '".$start_date."'
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
					AND A.trade_date = (SELECT MAX(trade_date) FROM mochaten WHERE code = '$code' and trade_date >= '$start_date' AND trade_date <= '$trade_date')" ;

		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);

		$ref_date = $row['trade_date_str'];
		$market_cap = $row['market_cap'];
		$dt_ratio =  $row['dt_ratio'];
		$op_ratio =  $row['op_ratio'];
		$lb_ratio =  $row['lb_ratio'];

		// 차트 이미지 + 종목 정보 출력
		$txat = (isset($comment['nomad'])) ? $comment['nomad'] : '';
		echo "<table border=1 class='table table-sm text-dark'>";
		if($brWidth > 2000) {
			// 차트 -- paxnet
			echo "<tr><td style='width: 1200px;' rowspan=7>";
			echo "<div class='chartBox'><iframe data-v-5032dc6f='' width='1200px' height='560px' scrolling='no' allowtransparency='false' src='https://www.paxnet.co.kr/stock/analysis/chartPopup?abbrSymbol=".$code."'></iframe></div>";
		} else {
			// 차트 -- 네이버이미지
			echo "<tr><td style='width: 700px;' rowspan=7>";
			echo "<h4><b>$name</b><h4>";
			echo "<img id='img_chart_area' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$code.".png?sidcode=1681518352718' width='700' height='289' alt='이미지 차트' onerror='this.src='https://ssl.pstatic.net/imgstock/chart3/world2008/error_700x289.png'>";
		}
			echo "</td><td style='font-weight:bold;background-color:#fae4f1;'>";
			echo " 시총 ".number_format($market_cap)." 억 &nbsp; / &nbsp; 유통비율: ".$dt_ratio." %, 영업이익률: ".$op_ratio." %, 부채율: ".$lb_ratio." %  (기준일 : ".$ref_date.")";
			echo "</td></tr><tr><td class='text-danger' style='font-weight:bold;'>";
			echo "$stock_keyword";
			echo "$stock_comment";
			echo "</td></tr><tr><td>";
			echo "$today_issue";
			echo "</td></tr>";
		echo "</table>";
		
		$query = "SELECT 
						(SELECT close FROM daily_price 
						WHERE date = '$trade_date'
						AND code = '$code'
						) AS close_amt, 
						(SELECT ROUND(AVG(close),0) 
						 FROM 
							(SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -2 WEEK), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 3
							) ncte
						) AS mavg3,
						(SELECT ROUND(AVG(close),0)
						 FROM 
						 (SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -2 WEEK), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 5
							) ncte
						) AS mavg5,
						(SELECT ROUND(AVG(close),0)
						 FROM 
						 (SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -2 WEEK), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 7
							) ncte
						) AS mavg7,
						(SELECT ROUND(AVG(close),0)
						 FROM 
						 (SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -4 WEEK), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 10
							) ncte
						) AS mavg10,
						(SELECT ROUND(AVG(close),0)
						 FROM 
						 (SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -4 WEEK), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 15
							) ncte
						) AS mavg15,
						(SELECT ROUND(AVG(close),0)
						 FROM 
						 (SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -5 WEEK), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 20
							) ncte
						) AS mavg20,
						(SELECT ROUND(AVG(close),0)
						 FROM 
						 (SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -4 MONTH), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 60
							) ncte
						) AS mavg60,
						(SELECT ROUND(AVG(close),0)
						 FROM 
						 (SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -7 MONTH), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 120
							) ncte
						) AS mavg120,
						(SELECT ROUND(AVG(close),0)
						 FROM 
						 (SELECT DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL 1 DAY), '%Y%m%d') date, close FROM daily_price 
								WHERE date = (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND code = '$code'
								UNION ALL
								SELECT date, close FROM daily_price
								WHERE date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
								AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -1 YEAR), '%Y%m%d'))
								AND code = '$code'
								ORDER BY date DESC 
								LIMIT 224
							) ncte
						) AS mavg224";
						
		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);
		$close_amt = "<font color=blue><b>".number_format($row['close_amt'])."</b></font>";
		$mavg3 = "<font color=blue>".number_format($row['mavg3'])."</font>";
		$mavg5 = "<font color=blue>".number_format($row['mavg5'])."</font>";
		$mavg7 = "<font color=blue>".number_format($row['mavg7'])."</font>";
		$mavg10 = "<font color=blue>".number_format($row['mavg10'])."</font>";
		$mavg15 = "<font color=blue>".number_format($row['mavg15'])."</font>";
		$mavg20 = "<font color=blue>".number_format($row['mavg20'])."</font>";
		$mavg60 = "<font color=blue>".number_format($row['mavg60'])."</font>";
		$mavg120 = "<font color=blue>".number_format($row['mavg120'])."</font>";
		$mavg224 = "<font color=blue>".number_format($row['mavg224'])."</font>";

		// 당일 시나리오
		$query = "	SELECT STR_TO_DATE(A.trade_date, '%Y%m%d') trade_date_str
						, A.trade_date
						, A.code
						, A.name
						, A.status
						, CASE WHEN A.close_rate >= 0 THEN CONCAT('<font color=red> ▲',A.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',A.close_rate,'% </font>') END close_rate
						, CASE WHEN A.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',A.tot_trade_amt,'억</b></font>') ELSE  CONCAT(A.tot_trade_amt,'억') END tot_trade_amt
						, A.volume
						, A.market_cap
						, A.stock_keyword
						, A.tracking_yn
						, A.tracking_reason
						, A.buy_pick
						, A.scenario
						, A.buy_band
						, A.buysell_yn
						, A.buysell_review
						, A.remark
						, A.tracking_index
					FROM scenario A
					WHERE A.trade_date = '$trade_date'
					AND A.code = '$code'";

		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);

		$trade_date_str = $row['trade_date_str'];
		$status         = $row['status'];
		$close_rate     = $row['close_rate'];
		$volume         = $row['volume'];
		$tot_trade_amt  = $row['tot_trade_amt'];
		$stock_keyword  = $row['stock_keyword'];
		$tracking_yn    = $row['tracking_yn'];
		$tracking_reason= $row['tracking_reason'];
		$scenario       = $row['scenario'];
		$buy_band       = $row['buy_band'];
		$buysell_review = $row['buysell_review'];
		
		if($row['buy_pick'] == 'Y') {
			$checkY = 'checked';
			$checkN = '';
		} else {
			$checkY = '';
			$checkN = 'checked';
		}
		$buy_pick = "<input type=radio name=buy_pick value='Y' $checkY>Y<input type=radio name=buy_pick value='N' $checkN>N ";

		if($row['tracking_yn'] == 'Y') {
			$checkY = 'checked';
			$checkN = '';
		} else {
			$checkY = '';
			$checkN = 'checked';
		}
		$tracking_yn = "<input type=radio name=tracking_yn value='Y' $checkY>Y<input type=radio name=tracking_yn value='N' $checkN>N ";
		if($row['buysell_yn'] == 'Y') {
			$checkY = 'checked';
			$checkN = '';
		} else {
			$checkY = '';
			$checkN = 'checked';
		}
		$buysell_yn  = "<input type=radio name=buysell_yn  value='Y'>Y<input type=radio name=buysell_yn  value='N' checked>N ";
		
		echo "<table class='table table-sm text-dark' border=0>";
		echo "<tr align=left>";
		echo "<td> $trade_date_str<input type=hidden name=tracking_index value='".$row['tracking_index']."'></td>";
		echo "<td style='width:18%'> $close_rate &nbsp;&nbsp;&nbsp;&nbsp; $tot_trade_amt </td>";
		echo "<td style='width:110px;'> 차트상태 </td>";
		echo "<td><input type=text name=status value='$status'></td>";
		echo "<td style='width:110px;'> 키워드 </td>";
		echo "<td style='width:25%'><input type=text name=stock_keyword value='$stock_keyword' style='width:99%;'></td>";
		echo "<td><input type=button class='btn btn-danger btn-sm' onclick=\"save()\" value='저장'></td>";
		echo "</tr>";
		echo "<tr align=left>";
		echo "<td style='width:110px;'> 트래킹여부 </td>";
		echo "<td>$tracking_yn</td>";
		echo "<td>이유</td>";
		echo "<td colspan=6 style='width:70%;height:40px;'><textarea name='tracking_reason' style='width:99%; height:40px;'>$tracking_reason</textarea></td>";
		echo "</tr>";
		echo "<tr align=left>";
		echo "<td> 이평가</td>";
		echo "<td colspan=8>[종가] $close_amt &nbsp; [3이평] $mavg3 &nbsp; [5이평] $mavg5 &nbsp; [7이평] $mavg7 &nbsp; [10이평] $mavg10 &nbsp; [15이평] $mavg15 &nbsp; [20이평] $mavg20 &nbsp; [60이평] $mavg60 &nbsp; [120이평] $mavg120 &nbsp; [224이평] $mavg224</td>";
		echo "</tr>";
		echo "<tr align=left>";
		echo "<td> 매수밴드 / <br> 시나리오</td>";
		echo "<td colspan=2 style='height:80px;'><textarea name='buy_band' style='width:99%; height:80px;'>$buy_band</textarea></td>";
		echo "<td colspan=6 style='height:80px;'><textarea name='scenario' style='width:99%; height:80px;'>$scenario</textarea></td>";
		echo "</tr>";
		echo "<tr align=left>";
		echo "<td style='width:110px;'> 매수대상 </td>";
		echo "<td>$buy_pick</td>";
		echo "<td rowspan=2> 복기 </td>";
		echo "<td rowspan=2 colspan=6 style='height:40px;'><textarea name='buysell_review' style='width:99%; height:60px;'>$buysell_review</textarea></td>";
		echo "</tr>";
		echo "<tr align=left>";
		echo "<td> 매매여부 </td>";
		echo "<td>$buysell_yn</td>";
		echo "</tr>";
		echo "</table>";

		// 이전 시나리오 보기
		$query = "	SELECT STR_TO_DATE(A.trade_date, '%Y%m%d') trade_date_str
						, A.trade_date
						, A.code
						, A.name
						, A.status
						, CASE WHEN A.close_rate >= 0 THEN CONCAT('<font color=red> ▲',A.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',A.close_rate,'% </font>') END close_rate
						, CASE WHEN A.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',A.tot_trade_amt,'억</b></font>') ELSE  CONCAT(A.tot_trade_amt,'억') END tot_trade_amt
						, A.volume
						, A.market_cap
						, A.stock_keyword
						, CASE WHEN A.tracking_yn = 'Y' THEN '추적' ELSE '' END tracking_yn
						, A.tracking_reason
						, CASE WHEN A.buy_pick = 'Y' THEN '매매대상' ELSE '' END buy_pick
						, A.scenario
						, A.buy_band
						, CASE WHEN A.buysell_yn = 'Y' THEN '매매' ELSE '' END buysell_yn
						, A.buysell_review
						, A.remark
						, A.tracking_index
					FROM scenario A
					INNER JOIN (SELECT tracking_index FROM scenario WHERE trade_date = '$trade_date' AND code = '$code') B
					ON B.tracking_index = A.tracking_index
					ORDER BY A.trade_date DESC";

		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);

		echo "<table class='table table-sm table-bordered small text-dark'>";
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			echo "<tr>";
			echo "<td rowspan=4 style='width:110px;height:12px;' align=center>".$row['trade_date_str']."</td>";
			echo "<td colspan=2>".$row['close_rate']." / ".$row['tot_trade_amt']." &nbsp;&nbsp;&nbsp; ".$row['stock_keyword']." &nbsp;&nbsp;&nbsp; ";
				echo "차트 : ".$row['status']." &nbsp;&nbsp;&nbsp; ".$row['buy_pick']."</td>";
			echo "</tr>";
			echo "<tr>";
			echo "<td>".$row['tracking_yn']."</td>";
			echo "<td>".$row['tracking_reason']."</td>";
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
	}
?>
<input type=hidden name=proc_fg>
<input type=hidden name=trade_date value=<?=$trade_date?>>
<input type=hidden name=code value=<?=$code?>>
</form>
<iframe name="saveFrame" src="scenario_script.php" width=1000 height=70>
</iframe>
</body>
<script>

function save() {
	form = document.form1;
	form.proc_fg.value = 'saveScenario';
	form.target = "saveFrame";
	form.submit();
}

function getData(date) {
	form = document.form1;
	form.proc_fg.value = 'getData';
	form.trade_date.value = date;
	form.target = "saveFrame";
	form.submit();
}

function fnImgPop(url){
 	var img=new Image();
 	img.src=url;
 	// 이미지 사이즈가 구해지지 않아 임의로 사이즈 지정
	// var img_width=img.width;
 	// var img_height=img.height;

 	var img_width=2000;
 	var img_height=820;

	var win_width=img_width+25;
 	var win=img_height+30;
 	var OpenWindow=window.open('','chart', 'width='+img_width+', height='+img_height+', menubars=no, scrollbars=auto');
 	OpenWindow.document.write("<style>body{margin:0px;}</style><img src='"+url+"' width='"+win_width+"'>");
}

function popupNews(link) {
	window.open(link,'popupNews',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1500, height=1000");
}

</script>
</html>