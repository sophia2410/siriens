<?php
// 조건에 따른 종목 구해오기
function getQuery($pgmId, $search_date, $increase_rate, $trade_amt, $group_label, $theme, $category, $buy_cnt, $buy_period, $zeroday_view, $chart_status, $frequency, $trade_qry, $trade_table, $base_date) {
	
	// 종목 거래대금 및 키워드 구하기
	$stock_info_table = "(SELECT * FROM v_market_event WHERE (date, code) IN (SELECT MAX(date), code FROM market_event_stocks WHERE date <= '$search_date' GROUP BY code))";

	$stock_qry = ", CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str";
	$stock_qry.= ", CASE WHEN B.trade_amount >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.trade_amount,0),'억</b></font>') ELSE  CONCAT(B.trade_amount,'억') END trade_amount_str";
	$stock_qry.= ", B.date AS 0day_date";
	$stock_qry.= ", B.group_label";
	$stock_qry.= ", B.keyword_group_name AS keyword_group";
	$stock_qry.= ", B.theme";
	$stock_qry.= ", B.stock_comment";
	$stock_qry.= ", B.trade_amount";

	// 상승 횟수 구하기
	// 2024.12.04 시.리. 구독종료로 mochaten에 데이터 생성 안됨. 2023.10.04 이후로는 별도 관리하던 0day_stocks 에서 데이터 구하기
	$mochaten_cnt_table = "(SELECT code, count(*) mochaten_cnt
							FROM (SELECT code FROM mochaten WHERE cha_fg = 'MC000' AND mochaten_date <= '2023-09-30'
								  UNION ALL
								  SELECT code FROM 0day_stocks WHERE 0day_date >= '2023-10-01'
								 ) SM
							GROUP BY code)";

	if($pgmId == 'Pick') { // event_register.php
		$filename = $pgmId;
		$file_orderby = "ORDER BY V.group_key, V.code";

		$query = "SELECT RTRIM(CONCAT(A.status)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.status,' ] ')) AS group_key_str
						, B.date
						, A.code 
						, S.name
						, A.type AS uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT status, type, code
						  FROM journal_feature jf
						  WHERE status != 'normal'
						  GROUP BY status, code
						 ) A
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = A.code
					AND S.last_yn = 'Y'
					ORDER BY A.status, A.code";
	} elseif($pgmId == '0dayStocksMarketEvent') { //0dayStocks.php
		$filename = $pgmId."_".$increase_rate;
		$file_orderby = "ORDER BY V.date DESC , V.trade_amount DESC";

		//  마켓 이벤트 특정 상승률, 거래대금 종목 골라오기
		if($increase_rate == '29.5') $sub_where = "AND B.close_rate >= $increase_rate";
		// else $sub_where = "AND ( (B.close_rate >= 29.5 AND B.trade_amount > 100) OR (B.close_rate >= $increase_rate AND B.trade_amount > 500) OR (B.close_rate >= 15 AND B.trade_amount > $trade_amt) )"; // 상한가 100억이상, 20% 500억 이상, 15% 1000억 이상
		else $sub_where = "AND ( (B.close_rate >= 29.5 AND B.trade_amount > 100) OR (B.close_rate >= $increase_rate AND B.trade_amount > $trade_amt) )"; // 상한가 300억이상, 10% 1000억 이상, 타이트하게 변경

		$query = "	SELECT A.date group_key
						, CONCAT(' &nbsp; <font color=gray>', B.date,'</font>') group_key_str
						, A.date
						, B.code
						, S.name
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
						$trade_qry
						, X.group_label_max_date
						, X.group_label_sum
						, R.group_sum
						, M.mochaten_cnt
					FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 20) A
					INNER JOIN $stock_info_table B
					ON A.date = B.date
					$sub_where
					INNER JOIN (	SELECT G.group_label, G.theme, SUM(G.trade_amount) AS group_sum, MAX(G.date) AS group_max_date
									FROM v_market_event G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 20) S
									ON S.date = G.date
									GROUP BY G.group_label, G.theme
								) R
					ON B.group_label = R.group_label
					AND B.theme  = R.theme
					INNER JOIN (	SELECT G.group_label, SUM(G.trade_amount) AS group_label_sum, MAX(G.date) AS group_label_max_date
									FROM v_market_event G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 20) S
									ON S.date = G.date
									GROUP BY G.group_label
								) X
					ON B.group_label = X.group_label
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = B.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = B.code
					JOIN stock S
					ON S.code = B.code
					AND S.last_yn = 'Y'
					ORDER BY B.date DESC, B.trade_amount DESC";
	} elseif($pgmId == 'marketEvent') { // event_register.php
		$filename = $pgmId."_".$theme."_".$group_label;
		$file_orderby = "ORDER BY V.group_cnt DESC, V.is_leader DESC, V.is_watchlist DESC, V.trade_amount DESC";

		$query = "SELECT RTRIM(CONCAT(B.keyword_group_name)) AS group_key
						, COUNT(*) OVER (PARTITION BY B.keyword_group_name) AS group_cnt
						, RTRIM(CONCAT(' [ ' , B.keyword_group_name,' ] ')) AS group_key_str
						, B.date
						, B.code 
						, S.name
						, B.is_leader
						, B.is_watchlist
						, B.group_label AS uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT *
						  FROM v_market_event vmi
						  WHERE (date, code) IN (SELECT MAX(date), code 
						  						 FROM market_event_stocks
												 WHERE date BETWEEN DATE_SUB('{$search_date}', INTERVAL 1 MONTH) AND '$search_date'
						  						 AND group_label LIKE '%$theme%'
						    					 GROUP BY code )
						 ) B
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = B.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = B.code
					JOIN stock S
					ON S.code = B.code
					AND S.last_yn = 'Y'
					ORDER BY group_cnt DESC, B.is_leader DESC, B.is_watchlist DESC, B.trade_amount DESC";
	} elseif($pgmId == 'keyword') { // event_register.php
		$filename = $pgmId."_".$theme."_".$group_label;
		$file_orderby = "ORDER BY V.last_event_date DESC, V.group_key, V.is_leader DESC, V.is_watchlist DESC, V.trade_amount DESC";

		if($trade_amt > 0) $sub_where = "AND trade_amount > $trade_amt";
		else $sub_where = "";

		$query = "SELECT RTRIM(CONCAT(B.keyword_group_name)) AS group_key
						, MAX(B.date) OVER (PARTITION BY B.keyword_group_name) AS last_event_date
						, RTRIM(CONCAT(' [ ' , B.keyword_group_name,' ] ')) AS group_key_str
						, B.date
						, B.code 
						, S.name
						, B.is_leader
						, B.is_watchlist
						, B.group_label AS uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM (  SELECT vmi.*
							FROM 
								v_market_event vmi
							JOIN 
								(SELECT code, MAX(date) AS max_date
								FROM v_market_event
								WHERE keyword_group_name LIKE '%$theme%'
								$sub_where
								GROUP BY code) latest 
							ON 
								vmi.code = latest.code 
								AND vmi.date = latest.max_date
							WHERE 
								vmi.keyword_group_name LIKE '%$theme%'
						 ) B
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = B.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = B.code
					JOIN stock S
					ON S.code = B.code
					AND S.last_yn = 'Y'
					ORDER BY last_event_date DESC, group_key, B.is_leader DESC, B.is_watchlist DESC, B.trade_amount DESC";
	} elseif($pgmId == 'sophiaWatchlist') { // sophiaWatchlist.php
		$filename = $pgmId."_".$group_label."_".$theme;
		$file_orderby = "ORDER BY V.sort_theme, V.sort_stock";

		$query = "SELECT RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme, '  ', A.category)) AS group_key
						, CONCAT('[', A.theme, (CASE WHEN A.category != '' THEN CONCAT('-', A.category) ELSE '' END),']') AS group_key_str
						, B.date
						, A.code 
						, S.name
						, A.sector, A.sort_theme, A.sort_stock, A.news_title, A.news_link
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM watchlist_sophia A
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = A.code
					AND S.last_yn = 'Y'
					WHERE A.sector = '$group_label'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					AND A.category LIKE CASE WHEN '$category' != '' THEN '%".$category."%' ELSE '%' END
					ORDER BY A.sector, A.sort_theme, A.sort_stock";
	} elseif($pgmId == 'aStarWatchlist') { // aStarWatchlist.php
		$filename = $pgmId."_".$group_label."_".$theme;
		$file_orderby = "ORDER BY V.sort_theme, V.stock_idx";

		$query = "SELECT RTRIM(CONCAT(A.group_label, A.theme)) AS group_key
						, RTRIM(CONCAT(' [ ' , A.group_label,' ] ', A.theme)) AS group_key_str
						, B.date
						, A.code 
						, S.name
						, A.group_label, A.sort_theme, A.stock_idx
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM watchlist_astar A
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = A.code
					AND S.last_yn = 'Y'
					WHERE A.group_label = '$group_label'
					AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
					ORDER BY A.group_label, A.sort_theme, A.stock_idx";
	} elseif($pgmId == 'IPOstock') { // 신규주
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.uprsn DESC";

		$query = "	SELECT A.listing_month group_key
						 , CONCAT(' &nbsp; <font color=gray>',DATE_FORMAT(A.listing_date, '%Y-%m'),'</font>') group_key_str
						 , B.date
						 , A.code
						 , S.name
						 , A.listing_date uprsn
						 $stock_qry
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
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = A.code
					AND S.last_yn = 'Y'
					ORDER BY A.listing_date DESC";
	} elseif($pgmId == 'xraytick') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.amount DESC";

		$query = "	SELECT A.date AS group_key
						, CONCAT(' &nbsp; <font color=gray>',A.date,'</font>') AS group_key_str
						, B.date
						, A.code 
						, S.name
						, A.amount
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT
						  	date, code, name, round(tot_amt/100000000,1) amount,  tot_cnt cnt
						  FROM
						  	xraytick_summary
						  WHERE
						  	date BETWEEN DATE_SUB('{$search_date}', INTERVAL 20 DAY) AND '{$search_date}'
						 ) A
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = A.code
					AND S.last_yn = 'Y'
					WHERE A.date = '$search_date'
					ORDER BY A.amount DESC";
	} elseif($pgmId == 'buyStreak') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.group_key DESC, V.trade_amount DESC ";

		if($zeroday_view == 'true')
			$sub_where = "";
		else 
			$sub_where = "WHERE (B.date is Null OR B.date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

		$query = "	SELECT occurrence_days  AS group_key
						, CONCAT(' &nbsp; ', occurrence_days, '회 이상') AS group_key_str
						, B.date
						, A.code 
						, S.name
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM (
							SELECT 
								ks.code, 
								ks.name,
								COUNT(DISTINCT ks.date) AS occurrence_days
							FROM
								xraytick_summary ks
							JOIN (
								SELECT date
								FROM calendar
								WHERE date <= '{$search_date}'
								ORDER BY date DESC
								LIMIT $buy_period
							) rd ON ks.date = rd.date
							WHERE ks.tot_amt >= 1000000000
							GROUP BY
								ks.code, ks.name
							HAVING
								occurrence_days >= $buy_cnt
						) A
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = A.code
					AND S.last_yn = 'Y'
					$sub_where
					ORDER BY A.occurrence_days DESC, B.trade_amount DESC";
	}  elseif($pgmId == 'buyStreakMonthly') { // xraytick 월별 조회 방식 고민 중.. 이건 2개월 이전부터 누계를 보는 방식
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.sector_counts DESC, V.group_key DESC, V.occurrence_days DESC ";

		$searchDay = substr($search_date,-2,2);

		// 이번 달의 회수 조건 설정
		$occurrenceCondition = 15;
		if ($searchDay <= 5) {
			$occurrenceCondition = 12;
		} elseif ($searchDay <= 15) {
			$occurrenceCondition = 15;
		} elseif ($searchDay <= 20) {
			$occurrenceCondition = 17;
		}

		$query = "	SELECT CONCAT(sector,'-', occurrence_days)  AS group_key
						, CONCAT(' &nbsp; [', sector, '] ', occurrence_days, '회') AS group_key_str
						, B.date
						, A.code 
						, S.name
						, A.occurrence_days
						, A.sector_counts
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM (
							SELECT 
								cbm.code, 
								cbm.name, 
								cbm.occurrence_days,
								CASE 
									WHEN ss.sector IS NULL OR ss.sector = '' THEN '기타' 
									ELSE ss.sector 
								END AS sector,
								COUNT(*) OVER (PARTITION BY ss.sector) sector_counts
							FROM (
									SELECT 
										ks.code, 
										ks.name, 
										COUNT(DISTINCT ks.date) AS occurrence_days
									FROM 
										xraytick_summary ks
									WHERE 
										ks.tot_amt >= 1000000000
										AND ks.date BETWEEN DATE_SUB(DATE_FORMAT(STR_TO_DATE('{$search_date}', '%Y-%m-%d'), '%Y-%m-01'), INTERVAL 1 MONTH) 
													AND '{$search_date}'
									GROUP BY 
										ks.code, ks.name
									HAVING 
										COUNT(DISTINCT ks.date) >= $occurrenceCondition
								) cbm
								LEFT JOIN stock_sector ss ON cbm.code = ss.code
							) A
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = A.code
					AND S.last_yn = 'Y'
					ORDER BY A.sector_counts DESC, group_key DESC, B.trade_amount DESC";
	}  elseif($pgmId == 'buyStreakMonthly_bak') { // xraytick 월별 조회 방식 고민 중.. 이건 2개월 전부터 1개월 단위 횟수를 보는 방식
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.group_key DESC, V.trade_amount DESC ";

		$searchDay = substr($search_date,-2,2);

		// 이번 달의 회수 조건 설정
		$occurrenceConditionForCurrentMonth = 10;
		if ($searchDay <= 5) {
			$occurrenceConditionForCurrentMonth = 2;
		} elseif ($searchDay <= 15) {
			$occurrenceConditionForCurrentMonth = 5;
		} elseif ($searchDay <= 20) {
			$occurrenceConditionForCurrentMonth = 7;
		}

		$query = "	SELECT CONCAT(sector,'-', month,'-', occurrence_days)  AS group_key
						, CONCAT(' &nbsp; [', sector, '] ', month, '월 ', occurrence_days, '회 이상') AS group_key_str
						, B.date
						, A.code 
						, S.name
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
						$trade_qry
						, M.mochaten_cnt
					FROM (
							SELECT 
								cbm.month,  
								cbm.code, 
								cbm.name, 
								cbm.occurrence_days,
								CASE 
									WHEN ss.sector IS NULL OR ss.sector = '' THEN '기타' 
									ELSE ss.sector 
								END AS sector
							FROM (
									SELECT 
										DATE_FORMAT(ks.date, '%Y-%m') AS month,  
										ks.code, 
										ks.name, 
										COUNT(DISTINCT ks.date) AS occurrence_days
									FROM 
										xraytick_summary ks
									WHERE 
										ks.tot_amt >= 1000000000
										AND ks.date BETWEEN DATE_SUB(DATE_FORMAT(STR_TO_DATE('{$search_date}', '%Y-%m-%d'), '%Y-%m-01'), INTERVAL 2 MONTH) 
													AND LAST_DAY(DATE_SUB(STR_TO_DATE('{$search_date}', '%Y-%m-%d'), INTERVAL 1 MONTH))
									GROUP BY 
										ks.code, ks.name, DATE_FORMAT(ks.date, '%Y-%m')
									HAVING 
										COUNT(DISTINCT ks.date) >= 10
									UNION ALL
									SELECT 
										DATE_FORMAT(ks.date, '%Y-%m') AS month,  
										ks.code, 
										ks.name, 
										COUNT(DISTINCT ks.date) AS occurrence_days
									FROM 
										xraytick_summary ks
									WHERE 
										ks.tot_amt >= 1000000000
										AND ks.date BETWEEN DATE_FORMAT(STR_TO_DATE('{$search_date}', '%Y-%m-%d'), '%Y-%m-01')
														AND '{$search_date}'
									GROUP BY 
										ks.code, ks.name, DATE_FORMAT(ks.date, '%Y-%m')
									HAVING 
										COUNT(DISTINCT ks.date) >= $occurrenceConditionForCurrentMonth
								) cbm
								LEFT JOIN stock_sector ss ON cbm.code = ss.code
							) A
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = A.code
					AND S.last_yn = 'Y'
					ORDER BY sector, month, group_key_str DESC, B.trade_amount DESC";
	} elseif($pgmId == 'watchlist') {  // 0-Day Stock // watchlist.php

		if($search_date == '') { // 전체 차트 보기 클릭. 한달전 차트까지 섹터, 테마별로 보여주기
			$search_date = date('Y-m-d');
			$orderby = "X.group_label_max_date DESC, X.group_label_sum DESC, R.group_sum DESC, B.trade_amount DESC";
			$group_key = "B.group_label";
			$where = "AND (B.date, B.code) IN (SELECT MAX(date), code FROM v_market_event GROUP BY code)";

			$filename = $pgmId."_monthly_theme";
			$file_orderby = "ORDER BY V.group_label_max_date DESC, V.group_label_sum DESC, V.group_sum DESC, V.trade_amount DESC";
		} else {
			if($search_date == 'today')
				$search_date = date('Y-m-d');
			$orderby = "B.date DESC, B.trade_amount DESC";
			$group_key = "A.date";
			$where = "";

			$filename = $pgmId."_0day_".$search_date;
			$file_orderby = "ORDER BY V.date DESC, V.trade_amount DESC";
		}

		$query = "	SELECT $group_key group_key
						, CONCAT(' &nbsp; <font color=gray>',B.date,'</font>') date_str
						, B.date
						, B.code
						, S.name
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
						$trade_qry
						, X.group_label_max_date
						, X.group_label_sum
						, R.group_sum
						, M.mochaten_cnt
					FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) A
					LEFT OUTER JOIN $stock_info_table B
					ON B.date = A.date
					$where
					INNER JOIN (	SELECT G.group_label, G.theme, SUM(G.trade_amount) AS group_sum, MAX(G.date) AS group_max_date
									FROM v_market_event G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) S
									ON S.date = G.date
									GROUP BY G.group_label, G.theme
								) R
					ON B.group_label = R.group_label
					AND B.theme  = R.theme
					INNER JOIN (	SELECT G.group_label, SUM(G.trade_amount) AS group_label_sum, MAX(G.date) AS group_label_max_date
									FROM v_market_event G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 30) S
									ON S.date = G.date
									GROUP BY G.group_label
								) X
					ON B.group_label = X.group_label
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = B.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = B.code
					JOIN stock S
					ON S.code = B.code
					AND S.last_yn = 'Y'
					ORDER BY $orderby";
	} elseif($pgmId == '0dayStocksMochaten') { //0dayStocks.php
		$filename = $pgmId;
		$file_orderby = "ORDER BY V.group_key_str DESC , V.tot_trade_amt DESC";

		$query = "	SELECT A.date group_key
						, CONCAT(' &nbsp; <font color=gray>', B.0day_date,'</font>') group_key_str
						, A.date
						, B.code
						, S.name
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.sector ELSE B.theme END uprsn
						, CASE WHEN B.close_rate >= 0 THEN CONCAT('<font color=red> ▲',B.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',ABS(B.close_rate),'% </font>') END close_rate_str
						, CASE WHEN B.tot_trade_amt >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(B.tot_trade_amt,0),'억</b></font>') ELSE  CONCAT(B.tot_trade_amt,'억') END trade_amount_str
						, B.0day_date
						, B.sector AS group_label
						, B.keyword_group
						, B.theme
						, B.stock_keyword AS stock_comment
						, X.sector_max_date
						, X.sector_sum
						, R.group_sum
						$trade_qry
						, M.mochaten_cnt
					FROM (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 20) A
					INNER JOIN 0day_stocks B
					ON A.date = B.0day_date AND B.tot_trade_amt >= 400
					INNER JOIN (	SELECT G.sector, G.theme, SUM(G.tot_trade_amt) AS group_sum, MAX(G.0day_date) AS group_max_date
									FROM 0day_stocks G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 20) S
									ON S.date = G.0day_date
									GROUP BY G.sector, G.theme
								) R
					ON B.sector = R.sector
					AND B.theme  = R.theme
					INNER JOIN (	SELECT G.sector, SUM(G.tot_trade_amt) AS sector_sum, MAX(G.0day_date) AS sector_max_date
									FROM 0day_stocks G
									INNER JOIN (SELECT date FROM calendar WHERE date <= '$search_date' ORDER BY date desc LIMIT 20) S
									ON S.date = G.0day_date
									GROUP BY G.sector
								) X
					ON B.sector = X.sector
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = B.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = B.code
					JOIN stock S
					ON S.code = B.code
					AND S.last_yn = 'Y'
					ORDER BY B.0day_date DESC, B.tot_trade_amt DESC";
	} elseif($pgmId == 'mochaten') { // 모차십
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.cha_fg, V.trade_amount DESC";

		$query = "	SELECT CONCAT(Q.cha_fg_nm,' - ',cha_comment) group_key
						 , CONCAT(' &nbsp; <font color=gray>', Q.cha_fg_nm,'</font>',' - ',cha_comment) group_key_str
						 , Q.date
						 , Q.code
						 , Q.name
						, '' stock_comment
						 , CASE WHEN Q.close_rate >= 0 THEN CONCAT('<font color=red> ▲',Q.close_rate,'% </font>') ELSE  CONCAT('<font color=blue> ▼',Q.close_rate,'% </font>') END close_rate_str
						 , CASE WHEN Q.trade_amount >= 1000 THEN CONCAT('<font color=red><b>',FORMAT(Q.trade_amount,0),'억</b></font>') ELSE  CONCAT(Q.trade_amount,'억') END trade_amount_str
						 , Q.trade_amount
						 , Q.uprsn
						 , Q.date 0day_date
						 , Q.stock_keyword
						 , Q.cha_fg
						 $trade_qry
						 , M.mochaten_cnt
					FROM (	SELECT A.cha_fg
								, C.nm cha_fg_nm
								, C.nm_sub1 cha_comment
								, A.trade_date AS date
								, A.code
								, A.name
								, CASE WHEN A.cha_fg = 'MC000' THEN A.close_rate ELSE B.close_rate end close_rate
								, CASE WHEN A.cha_fg = 'MC000' THEN A.tot_trade_amt ELSE B.trade_amount end trade_amount
								, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
								, B.keyword_group_name as stock_keyword
							FROM mochaten A
							LEFT OUTER JOIN (SELECT * FROM v_market_event 
											WHERE (date, code)
											IN 	(SELECT MAX(date), code FROM v_market_event WHERE date <= '$search_date' GROUP BY code)) B
							ON A.code = B.code
							INNER JOIN comm_cd C
							ON C.cd = A.cha_fg
							WHERE A.trade_date = '$search_date'
						) Q
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = Q.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = Q.code
					ORDER BY cha_fg, trade_amount DESC";
	} elseif($pgmId == 'chartStatus') { // xraytick
		$filename = $pgmId."_".$search_date;
		$file_orderby = "ORDER BY V.status_date DESC";

		if($zeroday_view == 'true')
			$sub_where = "";
		else 
			$sub_where = "WHERE (B.date is Null OR B.date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

		$query = "	SELECT status_date  AS group_key
						, CONCAT(' &nbsp; ', status_date, '등록') AS group_key_str
						, B.date
						, A.code 
						, S.name
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
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
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN $trade_table Z
					ON Z.code = A.code
					AND Z.date = '$base_date'
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					JOIN stock S
					ON S.code = B.code
					AND S.last_yn = 'Y'
					$sub_where
					ORDER BY A.status_date DESC";
	} else 	if($pgmId == 'kiwoomapi_opt10029_224') { // 예상체결 // kiwoomOpt10029.php && 224선
		$filename = $pgmId."_예상체결";
		$file_orderby = "ORDER BY V.market_fg, V.trade_rate_str DESC";

		$query = "SELECT RTRIM(CONCAT(' [ 224 - ' , S.market_fg,' ] ')) AS group_key
						, S.market_fg
						, S.code 
						, S.name
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
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
					LEFT OUTER JOIN $stock_info_table B
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
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					WHERE A.date = '$search_date'
					AND S.market_fg IN ('KOSPI','KOSDAQ')
					AND ((A.base_price < MA.avg_close AND A.exp_price >= MA.avg_close) OR 
						A.exp_price BETWEEN MA.avg_close * 0.97 AND MA.avg_close * 1.03)
					ORDER BY group_key, A.rate DESC";
	} elseif($pgmId == 'kiwoomapi_opt10029') { // 예상체결 // kiwoomOpt10029.php
		$filename = $pgmId."_예상체결";
		if($group_label=='(미등록)'){
			$file_orderby  = "ORDER BY V.market_fg, V.rate DESC";
			$query_orderby = "ORDER BY S.market_fg, A.rate DESC";
			$where = "AND W.group_label IS NULL ";
		} else {
			$file_orderby = "ORDER BY ORDER BY V.group_label, V.sort_theme, V.rate DESC";
			$query_orderby = "ORDER BY W.group_label, W.sort_theme, acc_trade_amount DESC, W.sort_stock";
			$where = "AND W.group_label = '$group_label' AND W.theme LIKE '%".$theme."' ";
		}
		$query = "SELECT RTRIM(CASE WHEN W.group_label IS NULL THEN CONCAT(' [ ' , S.market_fg ,' ] ') ELSE CONCAT(' [ ' , W.group_label ,' ] ', W.theme) END) AS group_key
						, S.market_fg
						, S.code 
						, S.name
						, W.group_label, W.sort_theme, W.sort_stock, W.news_title, W.news_link
						, CASE WHEN B.theme is null OR  B.theme = '' THEN B.group_label ELSE B.theme END uprsn
						$stock_qry
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
					LEFT OUTER JOIN $stock_info_table B
					ON B.code = A.code
					LEFT OUTER JOIN watchlist_sophia W
					ON W.code = A.code
					LEFT OUTER JOIN (SELECT * FROM v_market_event WHERE (date, code) IN (SELECT MAX(date), code FROM market_event_stocks  GROUP BY code)) B
					ON B.code = A.code
					LEFT OUTER JOIN $mochaten_cnt_table M
					ON M.code = A.code
					WHERE A.date = '$search_date'
					AND S.market_fg IN ('KOSPI','KOSDAQ')
					$where
					$query_orderby";
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
		, (SELECT market_cap FROM naver_finance_stock NF WHERE NF.date = Z.date AND NF.code = Z.code) AS market_cap";
		$table = "daily_price";
	}

    return [
        'query' => $query,
        'table' => $table,
		'today' => $today,
    ];
}

// 종목정보 출력
function generateStockHtml(
    $data,
    $showOnlyName = false, // 옵션 추가
	$linkFunction = 'commonStockLink',
    $chart_url = 'candle/day/' // 차트 URL 추가
) {
    // 가장 최근 0일차 상승이유
    if(isset($data['0day_date'])) {
        $info_0day = " <b>(".$data['keyword_group'].")</b> ".$data['close_rate_str']." , ".$data['trade_amount_str']."</font>"." &nbsp; ".$data['0day_date'];
    } else {
        $info_0day = "<font class='h5'>&nbsp</font>";
    }

    // 실시간 거래내역
    $realtime_data = "";
    if($data['trade_date'] != '') {
        $realtime_data = "<font class='h5'>".number_format($data['acc_trade_amount'])."억  &nbsp ".$data['trade_rate_str']." </font> &nbsp";
        $realtime_data .= "<font class='text-dark'>".$data['trade_date']."</font> ";
    }

    // - 장중 실시간 데이터를 보는 것이 목적이었으나, 잘 활용하지 않아 일단 빈값처리.
    $realtime_data = "";

    // 모차십 0일차 등록건이 있는 경우 건수 표시
    $mochaten_cnt = '';
    if($data['mochaten_cnt'] > 0) {
        $mochaten_cnt = '<font color=red>(' . $data['mochaten_cnt'] . ')</font>';
    }

    // 옵션에 따라 종목명만 출력
    if ($showOnlyName) {
        return "<div class='col mr-0'>
                    <div class='font-weight-bold text-primary text-uppercase mb-1' style='height:35px; line-height:35px;'>$mochaten_cnt
                        <font class='h4'>
                        ".call_user_func($linkFunction, $data['code'], $data['name'])."
                        </font> &nbsp;".$realtime_data."
                    </div>
                    <div class='font-weight-bold mb-1' style='margin: 0; width:610px;'>
                        $info_0day
                    </div>
                </div>";
    }

    // 전체 HTML 생성
    return "<div class='row no-gutters align-items-center'>
                <div class='col mr-0'>
                    <div class='font-weight-bold text-primary text-uppercase mb-1' style='height:35px; line-height:35px;'>$mochaten_cnt
                        <font class='h4'>
                        ".call_user_func($linkFunction, $data['code'], $data['name'])."
                        </font> &nbsp;".$realtime_data."
                    </div>
                    <div class='font-weight-bold mb-1' style='margin: 0;'>
                        $info_0day
                    </div>
                    <div style='margin: 0;'>
                        <img class='img-fluid' src='https://ssl.pstatic.net/imgfinance/chart/item/".$chart_url.$data['code'].".png?sidcode=1705826920773' width='600'>
                    </div>
                </div>
            </div>";
}

function commonStockLink($code, $name) {
    return "<a href='/modules/market/stock_report_popup.php?code=$code&name=$name&brWidth=2500' 
            onclick='window.open(this.href, \"stock\", \"\");return false;' target='_blank'>
            <b>$name</b>
            </a>";
}

function customStockLink($code, $name) {
    return "<a href=\"javascript:viewHighchart('{$code}','{$name}')\">
            <b>$name</b>
            </a>";
}
?>
