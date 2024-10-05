<?php
// 조건에 따른 종목 구해오기
function getQuery($pgmId, $search_date, $increase_rate, $trade_amt, $sector, $theme, $category, $buy_cnt, $buy_period, $zeroday_view, $chart_status, $frequency, $trade_qry, $trade_table, $base_date) {
	if($pgmId == '0dayStocks') { //0dayStocks.php
		$filename = $pgmId."_".$increase_rate;
		$file_orderby = "ORDER BY V.0day_date DESC , V.tot_trade_amt DESC";

		// 0일차 관종 목록 구해오기
		if($increase_rate == '29.5') $sub_where = "AND B.close_rate >= $increase_rate";
		else $sub_where = "AND ( B.close_rate >= $increase_rate OR B.tot_trade_amt > $trade_amt)";

		$query = "	SELECT A.date group_key
						, CONCAT(' &nbsp; <font color=gray>', B.0day_date,'</font>') group_key_str
						, A.date 0day_date
						, B.code
						, B.name
						, '' stock_comment
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, B.stock_keyword
						, X.sector_max_date
						, X.sector_sum
						, R.group_sum
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) A
					INNER JOIN 0day_stocks B
					ON A.date = B.0day_date
					$sub_where
					INNER JOIN (	SELECT G.sector, G.theme, SUM(G.tot_trade_amt) AS group_sum, MAX(G.0day_date) AS group_max_date
									FROM 0day_stocks G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) S
									ON S.date = G.0day_date
									GROUP BY G.sector, G.theme
								) R
					ON B.sector = R.sector
					AND B.theme  = R.theme
					INNER JOIN (	SELECT G.sector, SUM(G.tot_trade_amt) AS sector_sum, MAX(G.0day_date) AS sector_max_date
									FROM 0day_stocks G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) S
									ON S.date = G.0day_date
									GROUP BY G.sector
								) X
					ON B.sector = X.sector
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = B.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = B.code
					ORDER BY B.0day_date DESC, B.tot_trade_amt DESC";
	} else if($pgmId == 'mochaten') { // 모차십
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.cha_fg, V.tot_trade_amt DESC";

		$query = "	SELECT CONCAT(Q.cha_fg_nm,' - ',cha_comment) group_key
						 , CONCAT(' &nbsp; <font color=gray>', Q.0day_date,'</font>') group_key_str
						 , Q.0day_date
						 , Q.code
						 , Q.name
						, '' stock_comment
						 , CASE WHEN Q.close_rate >= 0 THEN CONCAT('<font color=red> ▲',Q.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',Q.close_rate,'% </font>') END close_rate_str
						 , CASE WHEN Q.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(Q.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(Q.tot_trade_amt,'억') END tot_trade_amt_str
						 , Q.tot_trade_amt
						 , Q.uprsn
						 , Q.stock_keyword
						 , Q.cha_fg
						 $trade_qry
						 , M.mochaten_cnt
					FROM (	SELECT A.cha_fg
								, C.nm cha_fg_nm
								, C.nm_sub1 cha_comment
								, A.code
								, A.name
								, CASE WHEN A.cha_fg = 'MC000' THEN A.close_rate ELSE B.close_rate end close_rate
								, CASE WHEN A.cha_fg = 'MC000' THEN A.tot_trade_amt ELSE B.tot_trade_amt end tot_trade_amt
								, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
								, B.stock_keyword
								, B.0day_date
							FROM mochaten A
							LEFT OUTER JOIN (SELECT * FROM 0day_stocks 
											WHERE (0day_date, code)
											IN 	(SELECT MAX(0day_date), code FROM 0day_stocks WHERE 0day_date <= '$search_date' GROUP BY code)) B
							ON A.code = B.code
							INNER JOIN comm_cd C
							ON C.cd = A.cha_fg
							WHERE A.trade_date = '$search_date'
						) Q
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = Q.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = Q.code
					ORDER BY cha_fg, tot_trade_amt DESC";
	} else if($pgmId == 'marketIssue') { // issue_register.php
		$filename = $pgmId."_".$theme."_".$sector;
		$file_orderby = "ORDER BY V.group_key, V.is_leader, V.is_watchlist DESC";

		$query = "SELECT RTRIM(CONCAT(A.keyword_group_name)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.keyword_group_name,' ] ')) AS group_key_str
						, A.date 0day_date
						, A.code 
						, A.name
						, A.group_label, A.theme, A.is_leader, A.is_watchlist, A.stock_comment
						, A.group_label AS uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT group_label, keyword_group_name, theme, code, name,
								 MAX(date) date, MAX(is_leader) AS is_leader, MAX(is_watchlist) AS is_watchlist, MAX(stock_comment) AS stock_comment
						  FROM v_market_event vmi
						  WHERE date BETWEEN DATE_SUB('{$search_date}', INTERVAL 1 MONTH) AND '$search_date'
						  AND group_label LIKE '%$theme%'
						  GROUP BY group_label, keyword_group_name, theme, code, name
						 ) A
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks WHERE (0day_date, code) IN (SELECT MAX(0day_date), code FROM 0day_stocks  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					ORDER BY A.keyword_group_name, A.is_leader DESC, A.is_watchlist DESC";
	} else if($pgmId == 'sophiaWatchlist') { // sophiaWatchlist.php
		$filename = $pgmId."_".$sector."_".$theme;
		$file_orderby = "ORDER BY V.sort_theme, V.sort_stock";

		$query = "SELECT RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme, '  ', A.category)) AS group_key
						, CONCAT('[', A.theme, (CASE WHEN A.category != '' THEN CONCAT('-', A.category) ELSE '' END),']') AS group_key_str
						, B.0day_date
						, A.code 
						, A.name
						, '' stock_comment
						, A.sector, A.sort_theme, A.sort_stock, A.news_title, A.news_link
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM watchlist_sophia A
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks WHERE (0day_date, code) IN (SELECT MAX(0day_date), code FROM 0day_stocks  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.sector = '$sector'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					AND A.category LIKE CASE WHEN '$category' != '' THEN '%".$category."%' ELSE '%' END
					ORDER BY A.sector, A.sort_theme, A.sort_stock";
	} else if($pgmId == 'aStarWatchlist') { // aStarWatchlist.php
		$filename = $pgmId."_".$sector."_".$theme;
		$file_orderby = "ORDER BY V.sort_theme, V.stock_idx";

		$query = "SELECT RTRIM(CONCAT(A.sector, A.theme)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme)) AS group_key_str
						, B.0day_date
						, A.code 
						, A.name
						, '' stock_comment
						, A.sector, A.sort_theme, A.stock_idx
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM watchlist_astar A
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks WHERE (0day_date, code) IN (SELECT MAX(0day_date), code FROM 0day_stocks  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.sector = '$sector'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					ORDER BY A.sector, A.sort_theme, A.stock_idx";
	} else if($pgmId == 'IPOstock') { // 신규주
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.uprsn DESC";

		$query = "	SELECT A.listing_month group_key
						 , CONCAT(' &nbsp; <font color=gray>',DATE_FORMAT(A.listing_date, '%Y-%m'),'</font>') group_key_str
						 , B.0day_date
						 , A.code
						 , A.name
						, '' stock_comment
						 , CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',B.close_rate,'% </font>') END close_rate_str
						 , CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						 , B.tot_trade_amt
						 , A.listing_date uprsn
						 , B.stock_keyword
						 $trade_qry
						 , M.mochaten_cnt
					FROM (	SELECT listing_date
								, SUBSTR(listing_date,1,7) AS listing_month
								, code
								, name
								, market_fg
							FROM stock_listing
							WHERE listing_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
						) A
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks 
									WHERE (0day_date, code)
									IN 	(SELECT MAX(0day_date), code FROM 0day_stocks GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					ORDER BY A.listing_date DESC";
	} else if($pgmId == 'xraytick') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.amount DESC";

		$query = "	SELECT A.date AS group_key
						, CONCAT(' &nbsp; <font color=gray>',A.date,'</font>') AS group_key_str
						, B.0day_date
						, A.code 
						, A.name
						, A.amount
						, '' stock_comment
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT
						  	date, code, name, round(tot_amt/100000000,1) amount,  tot_cnt cnt
						  FROM
						  	kiwoom_xray_tick_summary
						  WHERE
						  	date BETWEEN DATE_SUB('{$search_date}', INTERVAL 20 DAY) AND '{$search_date}'
						 ) A
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks WHERE (0day_date, code) IN (SELECT MAX(0day_date), code FROM 0day_stocks  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.date = '$search_date'
					ORDER BY A.amount DESC";
	} else if($pgmId == 'buyStreak') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.group_key DESC, V.tot_trade_amt DESC ";

		if($zeroday_view == 'true')
			$sub_where = "";
		else 
			$sub_where = "WHERE (B.0day_date is Null OR B.0day_date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

		$query = "	SELECT occurrence_days  AS group_key
						, CONCAT(' &nbsp; ', occurrence_days, '회 이상') AS group_key_str
						, B.0day_date
						, A.code 
						, A.name
						, '' stock_comment
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM (
							SELECT 
								ks.code, 
								ks.name,
								COUNT(DISTINCT ks.date) AS occurrence_days
							FROM
								kiwoom_xray_tick_summary ks
							JOIN (
								SELECT date
								FROM calendar
								WHERE date <= '{$search_date}'
								ORDER BY date DESC
								LIMIT $buy_period
							) rd ON ks.date = rd.date
							WHERE ks.tot_amt >= 300000000
							GROUP BY
								ks.code, ks.name
							HAVING
								occurrence_days >= $buy_cnt
						) A
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks WHERE (0day_date, code) IN (SELECT MAX(0day_date), code FROM 0day_stocks  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					$sub_where
					ORDER BY A.occurrence_days DESC, B.tot_trade_amt DESC";
	} else if($pgmId == 'chartStatus') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.status_date DESC";

		if($zeroday_view == 'true')
			$sub_where = "";
		else 
			$sub_where = "WHERE (B.0day_date is Null OR B.0day_date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

		$query = "	SELECT status_date  AS group_key
						, CONCAT(' &nbsp; ', status_date, '등록') AS group_key_str
						, B.0day_date
						, A.code 
						, A.name
						, '' stock_comment
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM (
							SELECT code, name, status_date
							FROM chart_status 
							WHERE status_cd = '$chart_status' 
							AND (code, status_date) IN (
								SELECT code, MAX(status_date) 
								FROM chart_status 
								GROUP BY code
							)
						) A
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks WHERE (0day_date, code) IN (SELECT MAX(0day_date), code FROM 0day_stocks  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					$sub_where
					ORDER BY A.status_date DESC";
	} else 	if($pgmId == 'kiwoomapi_opt10029_224') { // 예상체결 // kiwoomOpt10029.php && 224선
		$filename = $pgmId."_예상체결";
		$file_orderby = "ORDER BY V.market_fg, V.trade_rate_str DESC";

		$query = "SELECT RTRIM(CONCAT(' [ 224 - ' , S.market_fg,' ] ')) AS group_key
						, S.market_fg
						, S.code 
						, S.name
						, '' talent_fg
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.0day_date IS NOT NULL THEN CONCAT(' &nbsp; <font color=gray>',B.0day_date,'</font>') ELSE '' END 0day_date_str
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						, CONCAT('*',  DATE_FORMAT(A.date, '%m/%d'),' - (매도)', A.sell_remain,'(매수)', A.buy_remain) trade_date
						, A.exp_price trade_price
						, A.rate
						, CASE WHEN A.rate >= 0 THEN CONCAT('<font color=red> +',A.rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(A.rate),'% </font>') END trade_rate_str
						, ROUND((exp_price * exp_vol)/100000000,2) acc_trade_amount
						, M.mochaten_cnt
						-- , CASE WHEN (A.exp_price < MA.avg_close * 1.05 AND A.exp_price > MA.avg_close * 0.98) OR (A.base_price < MA.avg_close AND A.exp_price >= MA.avg_close) THEN '224 대상' ELSE '224 비대상' END AS condition_result
					FROM kiwoom_opt10029 A
					INNER JOIN kiwoom_stock S
					ON S.code = A.code
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks WHERE (0day_date, code) IN (SELECT MAX(0day_date), code FROM 0day_stocks  GROUP BY code)) B
					ON B.code = A.code
					LEFT OUTER JOIN
					(SELECT 
						code, 
						AVG(close) AS avg_close
					FROM 
						daily_price
					WHERE
						date >=(SELECT min(date)
								FROM (	SELECT date
										FROM calendar
										WHERE date <= '$search_date'
										AND date > (SELECT DATE_ADD(now(), INTERVAL IF(CURTIME() < '09:00:00', -1, 0) DAY) - INTERVAL 2 YEAR )
										ORDER BY date DESC
										LIMIT 224) CL)
					GROUP BY 
						code
					) MA
					ON MA.code = A.code
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.date = '$search_date'
					AND S.market_fg IN ('KOSPI','KOSDAQ')
					AND ((A.base_price < MA.avg_close AND A.exp_price >= MA.avg_close) OR 
						A.exp_price BETWEEN MA.avg_close * 0.97 AND MA.avg_close * 1.03)
					ORDER BY group_key, A.rate DESC";
	} else if($pgmId == 'kiwoomapi_opt10029') { // 예상체결 // kiwoomOpt10029.php
		$filename = $pgmId."_예상체결";
		if($sector=='(미등록)'){
			$file_orderby  = "ORDER BY V.market_fg, V.rate DESC";
			$query_orderby = "ORDER BY S.market_fg, A.rate DESC";
			$where = "AND W.sector IS NULL ";
		} else {
			$file_orderby = "ORDER BY ORDER BY V.sector, V.sort_theme, V.rate DESC";
			$query_orderby = "ORDER BY W.sector, W.sort_theme, acc_trade_amount DESC, W.sort_stock";
			$where = "AND W.sector = '$sector' AND W.theme LIKE '%".$theme."' ";
		}
		$query = "SELECT RTRIM(CASE WHEN W.sector IS NULL THEN CONCAT(' [ ' , S.market_fg ,' ] ') ELSE CONCAT(' [ ' , W.sector ,' ] ', W.theme) END) AS group_key
						, S.market_fg
						, S.code 
						, S.name
						, CASE WHEN talent_fg != '' THEN CONCAT('<font color=violet><b>',talent_fg,'</b></font>') ELSE '' END talent_fg
						, W.sector, W.sort_theme, W.sort_stock, W.news_title, W.news_link
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.0day_date IS NOT NULL THEN CONCAT(' &nbsp; <font color=gray>',B.0day_date,'</font>') ELSE '' END 0day_date_str
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						, CONCAT('*',  DATE_FORMAT(A.date, '%m/%d'),' - ', A.sell_remain) trade_date
						, A.exp_price trade_price
						, A.rate
						, CASE WHEN A.rate >= 0 THEN CONCAT('<font color=red> +',A.rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(A.rate),'% </font>') END trade_rate_str
						, ROUND((exp_price * exp_vol)/100000000,2) acc_trade_amount
						, M.mochaten_cnt
						-- , CASE WHEN (A.exp_price < MA.avg_close * 1.05 AND A.exp_price > MA.avg_close * 0.98) OR (A.base_price < MA.avg_close AND A.exp_price >= MA.avg_close) THEN '224 대상' ELSE '224 비대상' END AS condition_result
					FROM kiwoom_opt10029 A
					INNER JOIN kiwoom_stock S
					ON S.code = A.code
					LEFT OUTER JOIN watchlist_sophia W
					ON W.code = A.code
					LEFT OUTER JOIN (SELECT * FROM 0day_stocks WHERE (0day_date, code) IN (SELECT MAX(0day_date), code FROM 0day_stocks  GROUP BY code)) B
					ON B.code = A.code
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.date = '$search_date'
					AND S.market_fg IN ('KOSPI','KOSDAQ')
					$where
					$query_orderby";
	} else if($pgmId == 'watchlist') {  // 0-Day Stock // watchlist.php

		if($search_date == '') { // 전체 차트 보기 클릭. 한달전 차트까지 섹터, 테마별로 보여주기
			$search_date = date('Y-m-d');
			$orderby = "X.sector_max_date DESC, X.sector_sum DESC, R.group_sum DESC, B.tot_trade_amt DESC";
			$group_key = "B.sector";
			$where = "AND (B.0day_date, B.code) IN (SELECT MAX(0day_date), code FROM 0day_stocks GROUP BY code)";

			$filename = $pgmId."_monthly_theme";
			$file_orderby = "ORDER BY V.sector_max_date DESC, V.sector_sum DESC, V.group_sum DESC, V.tot_trade_amt DESC";
		} else {
			if($search_date == 'today')
				$search_date = date('Y-m-d');
			$orderby = "B.0day_date DESC, B.tot_trade_amt DESC";
			$group_key = "A.date";
			$where = "";

			$filename = $pgmId."_0day_".$search_date;
			$file_orderby = "ORDER BY V.0day_date DESC, V.tot_trade_amt DESC";
		}

		$query = "	SELECT $group_key group_key
						, CONCAT(' &nbsp; <font color=gray>',B.0day_date,'</font>') 0day_date_str
						, B.code
						, B.name
						, '' talent_fg
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, B.stock_keyword
						, B.0day_date
						, X.sector_max_date
						, X.sector_sum
						, R.group_sum
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) A
					INNER JOIN 0day_stocks B
					ON A.date = B.0day_date
					$where
					INNER JOIN (	SELECT G.sector, G.theme, SUM(G.tot_trade_amt) AS group_sum, MAX(G.0day_date) AS group_max_date
									FROM 0day_stocks G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) S
									ON S.date = G.0day_date
									GROUP BY G.sector, G.theme
								) R
					ON B.sector = R.sector
					AND B.theme  = R.theme
					INNER JOIN (	SELECT G.sector, SUM(G.tot_trade_amt) AS sector_sum, MAX(G.0day_date) AS sector_max_date
									FROM 0day_stocks G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) S
									ON S.date = G.0day_date
									GROUP BY G.sector
								) X
					ON B.sector = X.sector
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = B.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = B.code
					ORDER BY $orderby";
	}

    return [
        'query' => $query,
        'filename' => $filename,
        'file_orderby' => $file_orderby,
    ];
}

// 시세 조회 기준일자 구하는 쿼리
function getBaseDateQuery($search_date) {
	// 시세 조회 기준일자 구해오기. 조회일자가 입력되지 않은 경우는 현재일 기준으로 구하기.
	if (DateTime::createFromFormat('Y-m-d', $search_date) !== false) {
		// 유효한 날짜 포맷인 경우
		$input_date_str = "'$search_date'";
	} else {
		// 유효하지 않은 날짜 포맷이거나 입력되지 않은 경우
		$input_date_str = "NOW()";
	}

	// 기준일자를 구하는 쿼리.
	$query = "
		SELECT MAX(date) AS base_date
		FROM calendar 
		WHERE date <= 
			(SELECT DATE_ADD($input_date_str, INTERVAL IF(CURTIME() < '09:00:00', -1, 0) DAY))";

	return $query;
}

// 현재 시세 정보 구하는 쿼리
function getTradeQuery($base_date) {
	date_default_timezone_set('Asia/Seoul');
	$today = date('Y-m-d');  // 현재 일자를 구함
	$now = date('H:i:s');  // 현재 시간을 구함

	if ($today == $base_date && $now >= '09:00:0' && $now <= '19:30:00') {
		// 당일 시세 정보 등록전에는 실시간 등록 정보에서 받아오기..
		$query = "
			, CONCAT('*',  DATE_FORMAT(crawl_time, '%H:%i')) trade_date
			, Z.current_price trade_price
			, CASE WHEN Z.change_rate >= 0 THEN CONCAT('<font color=red> +',Z.change_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.change_rate),'% </font>') END trade_rate_str
			, FLOOR((Z.volume*Z.current_price)/100000000) acc_trade_amount
			, Z.market_cap";
		$table = "naver_finance_stock";
	} else {
		// 당일 시세 정보 가져오기
		$query = "
		, DATE_FORMAT(Z.date, '%m/%d') trade_date
		, Z.close trade_price
		, CASE WHEN Z.close_rate >= 0 THEN CONCAT('<font color=red> +',Z.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> -',ABS(Z.close_rate),'% </font>') END trade_rate_str
		, FLOOR(Z.amount/100000000) acc_trade_amount
		, (SELECT round(market_cap/1000,2) FROM naver_finance_stock NF WHERE NF.date = Z.date AND NF.code = Z.code) AS market_cap";
		$table = "daily_price";
	}

    return [
        'query' => $query,
        'table' => $table,
		'today' => $today,
    ];
}

?>
