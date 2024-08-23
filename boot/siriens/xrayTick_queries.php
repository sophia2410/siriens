<?php
function getQuery($pgmId, $search_date, $increase_rate, $trade_amt, $sector, $theme, $buy_cnt, $buy_period, $zeroday_view, $chart_status, $frequency, $trade_qry, $trade_table, $base_date) {
	if($pgmId == '0dayStocks') { //0dayStocks.php
		$filename = $pgmId."_".$increase_rate;
		$file_orderby = "ORDER BY V.watchlist_date DESC , V.tot_trade_amt DESC";

		// 0일차 관종 목록 구해오기
		if($increase_rate == '29.5') $sub_where = "AND B.close_rate >= $increase_rate";
		else $sub_where = "AND ( B.close_rate >= $increase_rate OR B.tot_trade_amt > $trade_amt)";

		$query = "	SELECT STR_TO_DATE(A.date, '%Y%m%d') group_key
						, CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(B.watchlist_date, '%Y%m%d'),'</font>') group_key_str
						, A.date 0day_date
						, B.code
						, B.name
						, '' talent_fg
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, B.stock_keyword
						, B.watchlist_date
						, X.sector_max_date
						, X.sector_sum
						, R.group_sum
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) A
					INNER JOIN daily_watchlist B
					ON A.date = B.watchlist_date
					$sub_where
					INNER JOIN (	SELECT G.sector, G.theme, SUM(G.tot_trade_amt) AS group_sum, MAX(G.watchlist_date) AS group_max_date
									FROM daily_watchlist G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) S
									ON S.date = G.watchlist_date
									GROUP BY G.sector, G.theme
								) R
					ON B.sector = R.sector
					AND B.theme  = R.theme
					INNER JOIN (	SELECT G.sector, SUM(G.tot_trade_amt) AS sector_sum, MAX(G.watchlist_date) AS sector_max_date
									FROM daily_watchlist G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 15) S
									ON S.date = G.watchlist_date
									GROUP BY G.sector
								) X
					ON B.sector = X.sector
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = B.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = B.code
					ORDER BY B.watchlist_date DESC, B.tot_trade_amt DESC";
	} else if($pgmId == 'sophiaWatchlist') { // sophiaWatchlist.php
		$filename = $pgmId."_".$sector."_".$theme;
		$file_orderby = "ORDER BY V.sort_theme, V.sort_stock";

		$query = "SELECT RTRIM(CONCAT(A.sector, A.theme, A.category)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme, '  ',A.category)) AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						, CASE WHEN talent_fg != '' THEN CONCAT('<font color=violet><b>',talent_fg,'</b></font>') ELSE '' END talent_fg
						, A.sector, A.sort_theme, A.sort_stock, A.news_title, A.news_link
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM watchlist_sophia A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.sector = '$sector'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					ORDER BY A.sector, A.sort_theme, A.sort_stock";
	} else if($pgmId == 'market_issue') { // issue_register.php
		$filename = $pgmId."_".$theme."_".$sector;
		$file_orderby = "ORDER BY V.keyword_group_name, V.is_leader DESC";

		$query = "SELECT RTRIM(CONCAT(A.theme)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.theme,' ] ')) AS group_key_str
						, A.date 0day_date
						, A.code 
						, A.name
						, '' talent_fg
						, A.theme, A.keyword_group_name, A.is_leader
						, CASE WHEN A.theme is null OR  A.theme = '' THEN A.keyword_group_name ELSE A.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM v_market_issue A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE 
						CASE 
							WHEN '$sector' = 'theme' THEN A.theme = '$theme'
							WHEN '$sector' = 'keyword' THEN A.keyword_group_name LIKE '%#$theme%' 
							ELSE 1=1
						END
					ORDER BY A.keyword_group_name, A.is_leader DESC";
	} else if($pgmId == 'astarWatchlist') { // astarWatchlist.php
		$filename = $pgmId."_".$sector."_".$theme;
		$file_orderby = "ORDER BY V.sort_theme, V.stock_idx";

		$query = "SELECT RTRIM(CONCAT(A.sector, A.theme)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme)) AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						, '' talent_fg
						, A.sector, A.sort_theme, A.stock_idx
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END tot_trade_amt_str
						, B.tot_trade_amt
						$trade_qry
						, M.mochaten_cnt
					FROM watchlist_astar A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.sector = '$sector'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					ORDER BY A.sector, A.sort_theme, A.stock_idx";
	} else if($pgmId == 'mochaten') { // 모차십
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.cha_fg, V.tot_trade_amt DESC";

		$query = "	SELECT CONCAT(Q.cha_fg_nm,' - ',cha_comment) group_key
						 , CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(Q.watchlist_date, '%Y%m%d'),'</font>') group_key_str
						 , Q.watchlist_date 0day_date
						 , Q.code
						 , Q.name
						 , '' talent_fg
						 , CASE WHEN Q.close_rate >= 0 THEN CONCAT('<font color=red> ▲',Q.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',Q.close_rate,'% </font>') END close_rate_str
						 , CASE WHEN Q.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(Q.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(Q.tot_trade_amt,'억') END tot_trade_amt_str
						 , Q.tot_trade_amt
						 , Q.uprsn
						 , Q.stock_keyword
						 , Q.watchlist_date
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
								, B.watchlist_date
							FROM mochaten A
							LEFT OUTER JOIN (SELECT * FROM daily_watchlist 
											WHERE (watchlist_date, code)
											IN 	(SELECT MAX(watchlist_date), code FROM daily_watchlist WHERE watchlist_date <= $search_date GROUP BY code)) B
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
	} else if($pgmId == 'xraytick') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.amount DESC";

		$query = "	SELECT A.date AS group_key
						, CONCAT(' &nbsp; <font color=gray>',STR_TO_DATE(A.date, '%Y%m%d'),'</font>') AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						, A.amount
						, '' talent_fg
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
						  	date BETWEEN DATE_FORMAT(DATE_SUB('{$search_date}', INTERVAL 20 DAY), '%Y%m%d') AND DATE_FORMAT('{$search_date}', '%Y%m%d')
						 ) A
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					WHERE A.date = '$search_date'
					ORDER BY A.amount DESC";
	} else if($pgmId == 'buy_streak') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.group_key DESC, V.tot_trade_amt DESC ";

		if($zeroday_view == 'true')
			$sub_where = "";
		else 
			$sub_where = "WHERE (B.watchlist_date is Null OR STR_TO_DATE(B.watchlist_date, '%Y%m%d') < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

		$query = "	SELECT occurrence_days  AS group_key
						, CONCAT(' &nbsp; ', occurrence_days, '회 이상') AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						 , '' talent_fg
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
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					$sub_where
					ORDER BY A.occurrence_days DESC, B.tot_trade_amt DESC";
	} else if($pgmId == 'chart_status') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.status_date DESC";

		if($zeroday_view == 'true')
			$sub_where = "";
		else 
			$sub_where = "WHERE (B.watchlist_date is Null OR STR_TO_DATE(B.watchlist_date, '%Y%m%d') < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

		$query = "	SELECT status_date  AS group_key
						, CONCAT(' &nbsp; ', status_date, '등록') AS group_key_str
						, B.watchlist_date 0day_date
						, A.code 
						, A.name
						 , '' talent_fg
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
					LEFT OUTER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist  GROUP BY code)) B
					ON A.code = B.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN (SELECT code, count(*) mochaten_cnt FROM mochaten WHERE cha_fg = 'MC000' GROUP BY code) M
					ON M.code = A.code
					$sub_where
					ORDER BY A.status_date DESC";
	}


    return [
        'query' => $query,
        'filename' => $filename,
        'file_orderby' => $file_orderby,
    ];
}
?>
