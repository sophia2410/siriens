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
		$qry = "INSERT IGNORE INTO sirianz_watchlist
				( watchlist_date
				, code
				, name
				, regi_reason
				, close_rate
				, volume
				, tot_trade_amt
				, market_cap
				, tracking_yn
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
					, now()
				FROM mochaten A
				WHERE A.trade_date = '".$watchlist_date."'
				AND A.cha_fg = 'MC000'
				AND (A.tot_trade_amt >= 500 OR (A.close_rate > 29 AND A.tot_trade_amt >= 250))";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		$qry = "UPDATE sirianz_watchlist A
				INNER JOIN daily_price B
				ON  B.date = A.watchlist_date
				AND B.code = A.code
				INNER JOIN (SELECT * FROM sirianz_watchlist WHERE watchlist_date = (SELECT MAX(watchlist_date) FROM sirianz_watchlist WHERE watchlist_date < '$watchlist_date')) C
				ON C.code = A.code
				SET A.close_rate = CASE WHEN A.close_rate IS NULL OR A.close_rate = '' THEN B.close_rate ELSE A.close_rate END
					, A.volume = CASE WHEN A.volume IS NULL OR A.volume = '' THEN round(B.volume/1000,0) ELSE A.volume END
					, A.tot_trade_amt = CASE WHEN A.tot_trade_amt IS NULL OR A.tot_trade_amt = '' THEN round(B.amount/100000000,0) ELSE A.tot_trade_amt END
					, A.issue =  C.issue
					, A.stock_keyword =  C.stock_keyword
					, A.tracking_reason =  C.tracking_reason
				WHERE A.watchlist_date = '$watchlist_date'";

		echo $qry."<br><br>";
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
			$qry = "INSERT IGNORE INTO sirianz_watchlist
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
					LEFT OUTER JOIN (SELECT * FROM sirianz_watchlist WHERE watchlist_date = (SELECT MAX(watchlist_date) FROM sirianz_watchlist WHERE watchlist_date < '$watchlist_date')) C
					ON C.code = A.code
					WHERE A.code = '$code'";

			echo $qry."<br><br>";
			$mysqli->query($qry);
		}
	} else if($_POST['proc_fg'] == 'saveScenario') {
		// 관종리스트 업데이트
		$qry = " UPDATE sirianz_watchlist
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
			$qry = " INSERT INTO sirianz_scenario
						(scenario_date, code, name, buy_pick, buy_band, scenario, buysell_yn, buysell_category, buysell_review, watchlist_date, create_dtime)
					SELECT '".$_POST['scenario_date']."', code, name, '".$_POST['buy_pick']."', '".$_POST['buy_band']."', '".$_POST['scenario']."'
						 , '".$_POST['buysell_yn']."', '".$_POST['buysell_category']."', '".$_POST['buysell_review']."', watchlist_date, now()
					FROM  sirianz_watchlist
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

			$update = "UPDATE sirianz_scenario S
						INNER JOIN (SELECT close_amt, closest, compare FROM ( $u_query ) AS inqry ) AS M
						SET S.mavg = CONCAT(M.compare, M.closest)
						WHERE S.code = '".$_POST['code']."'
						AND S.scenario_date = '".$_POST['scenario_date']."'";

			echo "<pre>$update</pre>";
			$mysqli->query($update);
		} else {
			// 시나리오 업데이트
			$qry = " UPDATE sirianz_scenario
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
		$qry = "DELETE FROM sirianz_scenario WHERE scenario_date = '".$_POST['search_date']."' AND code = '".$_POST['code']."'";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		$qry = "INSERT INTO sirianz_scenario
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

			$qry = "UPDATE sirianz_watchlist
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