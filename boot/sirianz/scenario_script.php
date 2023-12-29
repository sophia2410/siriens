<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
</head>

<body>
<?php
// post 방식으로 넘어온 변수 확인
foreach($_POST as $key=>$val){
	echo "$key =>  $val \n";
}

if(isset($_POST['proc_fg'])) {

	$code = isset($_POST['code']) ? $_POST['code'] : "";
	
	if($_POST['proc_fg'] == 'getData') {

		$trade_date = $_POST['trade_date'];

		// 500억 이상 0일차 불러오기 전 미리 등록된 데이터 있으면 지우기
		$qry = "DELETE FROM scenario
				WHERE trade_date = '".$trade_date."'
				AND code IN (SELECT code
								FROM mochaten
								WHERE trade_date = '".$trade_date."'
								AND cha_fg = 'MC000'
								AND tot_trade_amt >= 500)";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		// 500억 이상 0일차 불러오기
		$qry = "INSERT IGNORE INTO scenario
				( trade_date
				, code
				, name
				, status
				, close_rate
				, volume
				, tot_trade_amt
				, market_cap
				, tracking_yn
				, last_0day
				, tracking_index
				, create_dtime
				)
				SELECT A.trade_date
					, A.code
					, A.name
					, '0일차'
					, A.close_rate
					, A.volume
					, A.tot_trade_amt
					, A.market_cap
					, 'Y'
					, A.trade_date
					, case when B.tracking_index is not null then B.tracking_index else concat(A.trade_date, A.code) end
					, now()
				FROM mochaten A
				LEFT OUTER JOIN (SELECT t1.code, t1.tracking_index
								FROM scenario t1 
								JOIN (
								SELECT code, tracking_index, MAX(trade_date) AS max_date
								FROM scenario
								GROUP BY code, tracking_index
								) t2 
								ON t1.tracking_index = t2.tracking_index AND t1.trade_date = t2.max_date
								WHERE t1.tracking_yn = 'Y') B
				ON B.code = A.code
				WHERE A.trade_date = '".$trade_date."'
				AND A.cha_fg = 'MC000'
				AND A.tot_trade_amt >= 500";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		$qry = "INSERT IGNORE INTO scenario
				( trade_date
				, code
				, name
				, issue
				, stock_keyword
				, buy_band
				, tracking_yn
				, tracking_reason
				, last_0day
				, tracking_index
				, create_dtime
				)
				SELECT '$trade_date'
					, A.code
					, A.name
					, CONCAT(A.issue, '_C')
					, CONCAT(A.stock_keyword, '_C')
					, A.buy_band
					, 'Y'
					, A.tracking_reason
					, A.last_0day
					, A.tracking_index
					, now()
				FROM scenario A
				WHERE A.trade_date = (SELECT MAX(trade_date) FROM scenario WHERE trade_date < '$trade_date')
				AND A.tracking_yn = 'Y'";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		$qry = "UPDATE scenario A
				INNER JOIN daily_price B
				ON  B.date = A.trade_date
				and B.code = A.code
				INNER JOIN (SELECT * FROM scenario WHERE trade_date = (SELECT MAX(trade_date) FROM scenario WHERE trade_date < '$trade_date')) C
				ON C.code = A.code
				SET A.close_rate = CASE WHEN A.close_rate IS NULL OR A.close_rate = '' THEN B.close_rate ELSE A.close_rate END
					, A.volume = CASE WHEN A.volume IS NULL OR A.volume = '' THEN round(B.volume/1000,0) ELSE A.volume END
					, A.tot_trade_amt = CASE WHEN A.tot_trade_amt IS NULL OR A.tot_trade_amt = '' THEN round(B.amount/100000000,0) ELSE A.tot_trade_amt END
					, A.issue =  C.issue
					, A.stock_keyword =  C.stock_keyword
					, A.buy_band =  C.buy_band
					, A.tracking_reason =  C.tracking_reason
					, A.tracking_yn = 'N' /* 1일차 매매 시뮬레이션, 1일차만 가져오도록 함 2023.12.25 */
				WHERE A.trade_date = '$trade_date'
				AND	  (A.status NOT LIKE '0일차%' OR A.status IS NULL)";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		$query = "SELECT A.code
					FROM scenario A
					WHERE A.trade_date = '$trade_date'";
		$result = $mysqli->query($query); 
		
		$ma_to_update = [];
		
		while($row = $result->fetch_assoc()) {
		  $ma_to_update[] = $row['code']; 
		}

		foreach ($ma_to_update as $code) {
	
			// 종목 가까운 이평선 구하기
			$sub_query0= "LEAST(ABS(close_amt - mavg3), ABS(close_amt - mavg5), ABS(close_amt - mavg8), ABS(close_amt - mavg10), ABS(close_amt - mavg15), ABS(close_amt - mavg20), ABS(close_amt - mavg60), ABS(close_amt - mavg120), ABS(close_amt - mavg224))";

			$sub_query = "SELECT date, close FROM daily_price
							WHERE code = '$code'
							AND date <= (select max(date) from daily_price where date <= '$trade_date' AND code = '$code' limit 1)
							AND date > (select DATE_FORMAT(DATE_ADD('$trade_date', INTERVAL -1 YEAR), '%Y%m%d'))
							ORDER BY date DESC ";

			$u_query = "SELECT 
							close_amt,
							mavg3,
							mavg5,
							mavg8,
							mavg10, 
							mavg15, 
							mavg20, 
							mavg60, 
							mavg120,
							mavg224,
							$sub_query0 AS min_diff,
							CASE
								WHEN $sub_query0 = ABS(close_amt - mavg3) THEN '3일선'
								WHEN $sub_query0 = ABS(close_amt - mavg5) THEN '5일선'
								WHEN $sub_query0 = ABS(close_amt - mavg8) THEN '8일선'
								WHEN $sub_query0 = ABS(close_amt - mavg10) THEN '10일선'
								WHEN $sub_query0 = ABS(close_amt - mavg15) THEN '15일선'
								WHEN $sub_query0 = ABS(close_amt - mavg20) THEN '20일선'
								WHEN $sub_query0 = ABS(close_amt - mavg60) THEN '60일선'
								WHEN $sub_query0 = ABS(close_amt - mavg120) THEN '120일선'
								WHEN $sub_query0 = ABS(close_amt - mavg224) THEN '224일선'
							END AS closest,
							CASE
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg3) THEN mavg3 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg3) THEN mavg3 END THEN '▽'
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg5) THEN mavg5 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg5) THEN mavg5 END THEN '▽'
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg8) THEN mavg8 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg8) THEN mavg8 END THEN '▽'
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg10) THEN mavg10 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg10) THEN mavg10 END THEN '▽'
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg15) THEN mavg15 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg15) THEN mavg15 END THEN '▽'
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg20) THEN mavg20 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg20) THEN mavg20 END THEN '▽'
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg60) THEN mavg60 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg60) THEN mavg60 END THEN '▽'
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg120) THEN mavg120 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg120) THEN mavg120 END THEN '▽'
								WHEN close_amt > CASE WHEN $sub_query0 = ABS(close_amt - mavg224) THEN mavg224 END THEN '△'
								WHEN close_amt < CASE WHEN $sub_query0 = ABS(close_amt - mavg224) THEN mavg224 END THEN '▽'
								ELSE 'equal'
							END AS compare
						FROM (
								SELECT 
									(SELECT close FROM daily_price 
									WHERE date = '$trade_date'
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
									) AS mavg224
						) AS mavg";
			// echo "<pre>$u_query</pre>";

			$update = "UPDATE scenario S
						INNER JOIN (SELECT close_amt, closest, compare FROM ( $u_query ) AS inqry ) AS M
						SET S.mavg = CONCAT(M.compare, M.closest)
						,   S.close_amt = M.close_amt
						WHERE S.code = '$code'
						AND S.trade_date = '$trade_date'";
			
			echo "<pre>$update</pre>";
			$mysqli->query($update);
		}

	} else if($_POST['proc_fg'] == 'saveScenario') {
		$qry = " UPDATE scenario
					SET issue	  = '".$_POST['issue']."'
						, stock_keyword	  = '".$_POST['stock_keyword']."'
						, status		  = '".$_POST['status']."'
						, buy_band		  = '".$_POST['buy_band']."'
						, tracking_yn	  = '".$_POST['tracking_yn']."'
						, tracking_reason = '".$_POST['tracking_reason']."'
						, buy_pick		  = '".$_POST['buy_pick']."'
						, scenario		  = '".$_POST['scenario']."'
						, buy_band		  = '".$_POST['buy_band']."'
						, buysell_yn	  = '".$_POST['buysell_yn']."'
						, buysell_review  = '".$_POST['buysell_review']."'
				WHERE trade_date = '".$_POST['trade_date']."'
				AND	  code 		 = '".$_POST['code']."'";

		echo $qry."<br><br>";
		$mysqli->query($qry);
	} else if($_POST['proc_fg'] == 'saveDayReview') {
		$qry = "DELETE FROM scenario WHERE trade_date = '".$_POST['trade_date']."' AND code = '".$_POST['code']."'";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		$qry = "INSERT INTO scenario
				( trade_date
				, code
				, buysell_reason
				, buysell_review
				, create_dtime
				)
				values 
				('".$_POST['trade_date']."'
				,'".$_POST['code']."'
				,'".$_POST['buysell_reason']."'
				,'".$_POST['buysell_review']."'
				, now()
				)";

		echo $qry."<br><br>";
		$mysqli->query($qry);
	}
}

$mysqli->close();
?>
</body>
<script>
	var iframeL = parent.document.getElementById ('iframeL').contentWindow;
	alert(iframeL);
	iframeL.location.reload ();
</script>
</html>