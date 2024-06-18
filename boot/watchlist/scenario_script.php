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
	
	if($_POST['proc_fg'] == 'getWatchlist') {
		$watchlist_date = $_POST['watchlist_date'];

		// 500억 이상 or 상한가+250억 이상 0일차 불러오기
		$qry = "INSERT IGNORE INTO daily_watchlist
				( watchlist_date
				, code
				, name
				, regi_reason
				, close_rate
				, volume
				, tot_trade_amt
				, market_cap
				, tracking_yn
				, tracking_start_date
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
					, now()
				FROM mochaten A
				WHERE A.trade_date = '".$watchlist_date."'
				AND A.cha_fg = 'MC000'
				AND (A.tot_trade_amt >= 500 OR (A.close_rate > 29 AND A.tot_trade_amt >= 150))";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		$qry = "UPDATE daily_watchlist A
				INNER JOIN daily_price B
				ON  B.date = A.watchlist_date
				AND B.code = A.code
				INNER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist WHERE watchlist_date < '$watchlist_date' GROUP BY code)) C
				ON C.code = A.code
				SET A.close_rate = CASE WHEN A.close_rate IS NULL THEN B.close_rate ELSE A.close_rate END
					, A.volume = CASE WHEN A.volume IS NULL THEN round(B.volume/1000,0) ELSE A.volume END
					, A.tot_trade_amt = CASE WHEN A.tot_trade_amt IS NULL THEN round(B.amount/100000000,0) ELSE A.tot_trade_amt END
					, A.sector =  C.sector
					, A.theme =  C.theme
					, A.issue =  C.issue
					, A.stock_keyword =  C.stock_keyword
					, A.tracking_reason =  C.tracking_reason
					, A.tracking_start_date =  CASE WHEN C.tracking_yn = 'Y' THEN C.tracking_start_date ELSE A.watchlist_date END
				WHERE A.watchlist_date = '$watchlist_date'";

		echo "<pre>$qry</pre>"."<br><br>";
		$mysqli->query($qry);
	} else if($_POST['proc_fg'] == 'addWatchlist') {
		$watchlist_date = $_POST['watchlist_date'];
		$pval = $_POST['stock'];

		$qry = "SELECT code
				FROM stock
				WHERE code = '$pval' OR name = '$pval'
				LIMIT 1";
		echo $qry."<br><br>";
		$result = $mysqli->query($qry);
		$row = $result->fetch_array(MYSQLI_BOTH);

		$code = isset($row['code']) ? $row['code'] : '';

		if($code == '') {
			echo '<script>alert("종목 정보를 찾을 수 없습니다."); parent.focus();</script>';
		}
		else {
			$qry = "INSERT IGNORE INTO daily_watchlist
					( watchlist_date
					, code
					, name
					, close_rate
					, volume
					, tot_trade_amt
					, tracking_yn
					, tracking_reason
					, create_dtime
					)
					SELECT '$watchlist_date'
						, A.code
						, A.name
						, CASE WHEN B.close_rate IS NOT NULL THEN B.close_rate ELSE 0 END
						, CASE WHEN B.volume IS NOT NULL THEN round(B.volume/1000,0) ELSE 0 END
						, CASE WHEN B.amount IS NOT NULL THEN round(B.amount/100000000,0) ELSE 0 END
						, 'Y'
						, C.tracking_reason
						, now()
					FROM stock A
					LEFT OUTER JOIN daily_price B
					ON  B.date = '$watchlist_date'
					AND B.code = A.code
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE watchlist_date = (SELECT MAX(watchlist_date) FROM daily_watchlist WHERE watchlist_date < '$watchlist_date')) C
					ON C.code = A.code
					WHERE A.code = '$code'";

			echo $qry."<br><br>";
			$mysqli->query($qry);
		}
	} else if($_POST['proc_fg'] == 'saveScenario') {
		// 관종리스트 업데이트
		$qry = " UPDATE daily_watchlist
					SET sector	  = '".$_POST['sector']."'
					, theme		  = '".$_POST['theme']."'
					, issue		  = '".$_POST['issue']."'
					, stock_keyword	  = '".$_POST['stock_keyword']."'
					, regi_reason	  = '".$_POST['regi_reason']."'
					, tracking_yn	  = '".$_POST['tracking_yn']."'
					, tracking_reason = '".$_POST['tracking_reason']."'
				WHERE watchlist_date = '".$_POST['watchlist_date']."'
				AND code = '".$_POST['code']."'";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		if ($_POST['buy_pick'] == 'Y' || $_POST['buysell_yn'] == 'Y') {

			// 시나리오 업데이트 또는 인서트
			$qry = " INSERT INTO daily_watchlist_scenario
						(scenario_date, code, name, buy_pick, buy_band, scenario, buysell_yn, buysell_category, buysell_review, watchlist_date, create_dtime)
					SELECT '".$_POST['scenario_date']."', code, name, '".$_POST['buy_pick']."', '".$_POST['buy_band']."', '".$_POST['scenario']."'
						 , '".$_POST['buysell_yn']."', '".$_POST['buysell_category']."', '".$_POST['buysell_review']."', watchlist_date, now()
					FROM  daily_watchlist
					WHERE watchlist_date = '".$_POST['watchlist_date']."'
					AND   code = '".$_POST['code']."'
					ON DUPLICATE KEY UPDATE
						buy_pick = '".$_POST['buy_pick']."',
						buy_band = '".$_POST['buy_band']."',
						scenario = '".$_POST['scenario']."',
						buysell_yn = '".$_POST['buysell_yn']."',
						buysell_category = '".$_POST['buysell_category']."',
						buysell_review = '".$_POST['buysell_review']."'";

			// 가까운 이평선 구하기
			$sub_query0= "LEAST(ABS(close_amt - mavg3), ABS(close_amt - mavg5), ABS(close_amt - mavg8), ABS(close_amt - mavg10), ABS(close_amt - mavg15), ABS(close_amt - mavg20), ABS(close_amt - mavg60), ABS(close_amt - mavg120), ABS(close_amt - mavg224))";

			$sub_query = "SELECT date, close FROM daily_price
							WHERE code = '".$_POST['code']."'
							AND date <= (select max(date) from daily_price where date < '".$_POST['scenario_date']."' AND code = '".$_POST['code']."' limit 1)
							AND date > (select DATE_FORMAT(DATE_ADD('".$_POST['scenario_date']."', INTERVAL -1 YEAR), '%Y%m%d'))
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
								WHEN $sub_query0 = ABS(close_amt - mavg3) THEN '3'
								WHEN $sub_query0 = ABS(close_amt - mavg5) THEN '5'
								WHEN $sub_query0 = ABS(close_amt - mavg8) THEN '8'
								WHEN $sub_query0 = ABS(close_amt - mavg10) THEN '10'
								WHEN $sub_query0 = ABS(close_amt - mavg15) THEN '15'
								WHEN $sub_query0 = ABS(close_amt - mavg20) THEN '20'
								WHEN $sub_query0 = ABS(close_amt - mavg60) THEN '60'
								WHEN $sub_query0 = ABS(close_amt - mavg120) THEN '120'
								WHEN $sub_query0 = ABS(close_amt - mavg224) THEN '224'
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
								ELSE '='
							END AS compare
						FROM (
								SELECT 
									(SELECT close FROM daily_price 
									WHERE date = '".$_POST['scenario_date']."'
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
			echo $qry."<br><br>";
			$mysqli->query($qry);

			$update = "UPDATE daily_watchlist_scenario S
						INNER JOIN (SELECT close_amt, closest, compare FROM ( $u_query ) AS inqry ) AS M
						SET S.mavg = CONCAT(M.compare, M.closest)
						WHERE S.code = '".$_POST['code']."'
						AND S.scenario_date = '".$_POST['scenario_date']."'";

			echo "<pre>$update</pre>";
			$mysqli->query($update);
		} else {
			// 시나리오 업데이트
			$qry = " UPDATE daily_watchlist_scenario
						SET buy_pick	  = '".$_POST['buy_pick']."'
						, buy_band		  = '".$_POST['buy_band']."'
						, scenario		  = '".$_POST['scenario']."'
						, buysell_yn	  = '".$_POST['buysell_yn']."'
						, buysell_category= '".$_POST['buysell_category']."'
						, buysell_review  = '".$_POST['buysell_review']."'
					WHERE scenario_date   = '".$_POST['scenario_date']."'
					AND code = '".$_POST['code']."'";
			echo $qry."<br><br>";
			$mysqli->query($qry);
		}

	} else if($_POST['proc_fg'] == 'saveDayReview') {	// 일자별 매매 복기
		$qry = "DELETE FROM daily_watchlist_scenario WHERE scenario_date = '".$_POST['search_date']."' AND code = '".$_POST['code']."'";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		$qry = "INSERT INTO daily_watchlist_scenario
				( scenario_date
				, code
				, buysell_category
				, buysell_review
				, create_dtime
				)
				values 
				('".$_POST['search_date']."'
				,'".$_POST['code']."'
				,'".$_POST['buysell_category']."'
				,'".$_POST['buysell_review']."'
				, now()
				)";

		echo $qry."<br><br>";
		$mysqli->query($qry);

	} else if($_POST['proc_fg'] == 'saveTheme') {
		for($i=0; $i<$_POST['cnt']; $i++){
			// 시그널이브닝 테마,이슈,키워드 변경 정보 업데이트
			$sector		= 'sector'.$i;
			$theme		= 'theme'.$i;
			$issue		= 'issue'.$i;
			$hot_theme	= 'hot_theme'.$i;
			$tobecontinued	= 'tobecontinued'.$i;
			$theme_comment	= 'theme_comment'.$i;
			$org_sector	= 'org_sector'.$i;
			$org_theme	= 'org_theme'.$i;
			$org_issue	= 'org_issue'.$i;

			$hot_theme = isset($_POST[$hot_theme]) ? 'Y' : 'N';
			$tobecontinued = isset($_POST[$tobecontinued]) ? 'Y' : 'N';

			$qry = "UPDATE daily_watchlist
					SET sector		= '".$_POST[$sector]."'
					,	theme		= '".$_POST[$theme]."'
					,	issue		= '".$_POST[$issue]."'
					, 	hot_theme	= '$hot_theme'
					, 	theme_comment= '".$_POST[$theme_comment]."'
					WHERE watchlist_date ='".$_POST['search_date']."'
					AND sector  ='".$_POST[$org_sector]."' 
					AND theme  ='".$_POST[$org_theme]."' 
					AND issue  ='".$_POST[$org_issue]."'";
			echo $qry."<br>";
			$mysqli->query($qry);
		}

	} else if($_POST['proc_fg'] == 'getReviewData') { // 리뷰 대상종목 불러오기

		$review_date = $_POST['review_date'];
		$day_cnt = $_POST['day_cnt'];

		// daily_watchlist 데이터 불러오기
		$qry = "INSERT IGNORE INTO daily_watchlist_review
				( review_date
				, code
				, name
				, watchlist_date
				)
				SELECT '".$_POST['review_date']."'
					, code
					, name
					, max(watchlist_date) watchlist_date
				FROM daily_watchlist
				WHERE tracking_yn = 'Y'
				AND watchlist_date <= '$review_date'
				AND watchlist_date >= (select DATE_FORMAT(DATE_ADD('$review_date', INTERVAL -1*$day_cnt DAY), '%Y%m%d'))
				GROUP BY code, name";

		echo $qry."<br>";
		$mysqli->query($qry);

		// 전일 작성했던 재료 리뷰가 있으면 가져오기, 매수정보의 경우는 매도처리가 된 경우는 가져오지 않기
		$qry = "UPDATE daily_watchlist_review A
				INNER JOIN (SELECT * FROM daily_watchlist_review WHERE review_date = (SELECT MAX(review_date) FROM daily_watchlist_review WHERE review_date < '$review_date')) C
				ON C.code = A.code
				SET   A.issue_status = CASE WHEN (A.issue_status = '' OR A.issue_status IS NULL)  AND C.issue_status!='' THEN C.issue_status ELSE A.issue_status END
					, A.chart_status = CASE WHEN (A.chart_status = '' OR A.chart_status IS NULL)  AND C.chart_status!='' THEN C.chart_status ELSE A.chart_status END
					, A.pick_yn 	 = CASE WHEN A.pick_yn = '' OR A.pick_yn  IS NULL THEN C.pick_yn ELSE A.pick_yn END
					, A.buy_yn 	 	 = CASE WHEN A.buy_yn = ''   OR A.buy_yn   IS NULL THEN CASE WHEN C.sell_yn = 'Y' THEN '' ELSE C.buy_yn   END ELSE A.buy_yn END   -- 전일 매도인 경우는 매수처리 하지 않기
					, A.buy_date 	 = CASE WHEN A.buy_date = '' OR A.buy_date IS NULL THEN CASE WHEN C.sell_yn = 'Y' THEN '' ELSE C.buy_date END ELSE A.buy_date END -- 전일 매도인 경우는 매수일자처리 하지 않기
					, A.buysell_review= CASE WHEN (A.buysell_review = '' OR A.buysell_review IS NULL) AND C.buysell_review!='' THEN CASE WHEN (C.buy_yn = '' or C.sell_yn = 'Y') THEN '' ELSE C.buysell_review END ELSE A.buysell_review END
					, A.feat 	 	  = CASE WHEN A.feat = '' OR A.feat IS NULL THEN C.feat ELSE A.feat END
				WHERE A.review_date   = '$review_date'";

		echo $qry."<br>";
		$mysqli->query($qry);
	} else if($_POST['proc_fg'] == 'saveReview') {
		$review_date = $_POST['review_date'];

		// 리뷰일 시장 코멘트
		$qry = "INSERT INTO daily_watchlist_review
					(review_date, code, name, issue_status, create_dtime)
				SELECT '$review_date', 'DAY', '거래일리뷰', '".$_POST['day_issue_status']."', now()
				FROM  daily_watchlist_review
				ON DUPLICATE KEY UPDATE
				issue_status = '".$_POST['day_issue_status']."'";

		echo $qry."<br>";
		$mysqli->query($qry);

		// 리뷰일 종목 업데이트
		for($i=0; $i<$_POST['cnt']; $i++){
			
			$code = 'code'.$i;
			$mavg = 'mavg'.$i;
			$issue_status = 'issue_status'.$i;
			$chart_status = 'chart_status'.$i;
			// $issue_score  = 'issue_score'.$i;
			// $chart_score  = 'chart_score'.$i;
			$pick_yn	= 'pick_yn'.$i;
			$buy_yn 	= 'buy_yn'.$i;
			$sell_yn 	= 'sell_yn'.$i;
			$win_yn		= 'win_yn'.$i;
			$traking_yn = 'traking_yn'.$i;
			$feat		= 'feat'.$i;
			$buysell_review = 'buysell_review'.$i;
			$win_loss_review = 'win_loss_review'.$i;

			$check_pick	= isset($_POST[$pick_yn]) ? $_POST[$pick_yn] : '';
			$check_win	= isset($_POST[$win_yn])  ? $_POST[$win_yn]  : '';

			//매수매도 처리
			if(isset($_POST[$buy_yn])) {
				$buy_check = "'Y'";
				$buy_date  = "'".$review_date."'";
			} else {
				$buy_check = "A.buy_yn";
				$buy_date  = "A.buy_date";
			}
			
			if(isset($_POST[$sell_yn])) {
				$sell_check = "'Y'";
				$sell_date  = "'".$review_date."'";
			} else {
				$sell_check  = "''";
				$sell_date   = "''";
			}

			$qry = "UPDATE daily_watchlist_review A
					SET mavg			= '".$_POST[$mavg]."'
					,	issue_status	= '".$_POST[$issue_status]."'
					,	chart_status	= '".$_POST[$chart_status]."'
					,	pick_yn			= '$check_pick'
					,	feat			= '".$_POST[$feat]."'
					,	buy_yn			=  $buy_check
					,	buy_date		=  $buy_date
					,	sell_yn			=  $sell_check
					,	sell_date		=  $sell_date
					,	buysell_review	= '".$_POST[$buysell_review]."'
					,	win_yn			= '$check_win'
					,	win_loss_review = '".$_POST[$win_loss_review]."'
					WHERE review_date= '$review_date'
					AND	code = '".$_POST[$code]."'";

			echo $qry."<br><br>";
			$mysqli->query($qry);

			// 트레킹 종료를 선택한 경우
			if(isset($_POST[$traking_yn])) {
				$qry = "UPDATE daily_watchlist
						SET	tracking_yn = 'N'
						,	tracking_end_date = '$review_date'
						WHERE code = '".$_POST[$code]."'
						AND tracking_yn = 'Y'";

				echo $qry."<br>";
				$mysqli->query($qry);
			}
		}
	}

	echo '<script>
			var iframeL = parent.parent.document.getElementById ("iframeL").contentWindow;
			iframeL.location.reload ();

			var iframeR = parent.parent.document.getElementById ("iframeR").contentWindow;
			iframeR.location.reload ();
		</script>';

}

$mysqli->close();
?>
</body>
</html>