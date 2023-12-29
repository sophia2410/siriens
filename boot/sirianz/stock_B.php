<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
<link rel="shortcut icon" href="#">
<style>
	.content {
	height: 460px;
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
$mochaten_date = date("Ymd");
$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';
$page_id = (isset($_GET['page_id'])) ? $_GET['page_id'] : 'stock';
$code = (isset($_GET['code'])) ? $_GET['code'] : '';
$name = (isset($_GET['name'])) ? $_GET['name'] : '';
?>

<body>
<form name="form1" method='POST' action='mochaten_script.php'>
<?php
	if($code == '' && $page_id == 'stock'){
		echo "<h3>종목명 입력 후 엔터</h3>";
	}else if($code == '' && $page_id == 'schedule'){
		echo "<h3></h3>";
	} else {
		// 모차십 등록 이력 불러오기
		$query = " SELECT STR_TO_DATE(A.mochaten_date, '%Y%m%d') mochaten_date
						, STR_TO_DATE(A.trade_date   , '%Y%m%d') trade_date
						, C.cnt
						, A.cha_fg
						, A.name
						, CASE WHEN A.cha_fg = 'MCH00' THEN '과열예고' ELSE B.nm END cha_fg_nm
						, A.market_cap
						, A.close_rate
						, A.volume
						, A.tot_trade_amt
						, A.f_trade_amt
						, A.o_trade_amt
						, A.p_trade_amt
						, A.op_ratio
						, A.lb_ratio
						, A.dt_ratio
						, A.create_dtime
					FROM mochaten A
					INNER JOIN comm_cd B
					ON B.cd = A.cha_fg
					INNER JOIN (SELECT mochaten_date, count(*) cnt
								  FROM mochaten
								 WHERE code = '$code'
								 group by mochaten_date) C
					ON C.mochaten_date = A.mochaten_date
					WHERE code = '$code'
					ORDER BY A.mochaten_date desc, cha_fg";
		// echo $query;
		$result = $mysqli->query($query);

		// 종목 모차십이력 표시 - 변수 초기화
		$i=0;
		$pre_cha_date = "";
		$td_date = "";
		$td_cha_fg = "";
		$td_close_rate = "";
		$td_volume = "";
		$td_trade_amt = "";
		$amt_class = "";
		$stock_info = "";
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			// 하루에 여러 항목에 포함된 경우 묶어서 (colspan) 표시되도록 처리

			// 거래대금에 따라 스타일 적용
			if($row['tot_trade_amt'] > 2000)
				$amt_style = "mark text-danger font-weight-bold h6";
			else if($row['tot_trade_amt'] > 1000)
				$amt_style = "text-danger font-weight-bold h6";
			else
				$amt_style = "font-weight-bold";

			if($pre_cha_date != $row['trade_date']){
				$td_date      .= "<th align=center colspan=".$row['cnt']." style='width:100px; height: 30px;'>". $row['trade_date']."</th>";
				$td_close_rate.= "<td align=center colspan=".$row['cnt']." style='width:100px; height: 30pxpx;'>". $row['close_rate']."%</td>";
				$td_volume    .= "<td align=center colspan=".$row['cnt']." style='width:100px; height: 30pxpx;'>". number_format($row['volume'])."K</td>";
				$td_trade_amt .= "<td align=center colspan=".$row['cnt']."  style='width:100px; height: 30pxpx;' class='"."$amt_style"."'>". number_format($row['tot_trade_amt'])."억</td>";
			}
			if($row['cnt'] > 1) $width = round(100/$row['cnt'],0).'px';
			else				$width = '100px';
			$td_cha_fg .= "<td align=center style='width:".$width." overflow:hidden;text-overflow;ellipsis;white-space:nowrap;' >". $row['cha_fg_nm']."</td>";
			$pre_cha_date = $row['trade_date'];

			// 가장 최근 시총 가져오기 
			if($i == 0) {
				$stock_nm   = $row['name'];
				$market_cap = $row['market_cap'];
				$op_ratio   = $row['op_ratio'];
				$lb_ratio   = $row['lb_ratio'];
				$dt_ratio   = $row['dt_ratio'];

				$stock_info = "시총 ".number_format($market_cap)." 억 &nbsp; / &nbsp; 유통비율: ".$dt_ratio." %, 영업이익률: ".$op_ratio." %, 부채율: ".$lb_ratio." %";
			}
			$i++;
		}

		for(; $i<15; $i++){
			$td_date       .= "<td style='width:100px;'>&nbsp;</td>";
			$td_cha_fg     .= "<td style='width:100px;'>&nbsp;</td>";
			$td_close_rate .= "<td style='width:100px;'>&nbsp;</td>";
			$td_volume	   .= "<td style='width:100px;'>&nbsp;</td>";
			$td_trade_amt  .= "<td style='width:100px;'>&nbsp;</td>";
		}

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

		//TODAY ISSUE
		$today_issue = '';

		// infostock 에서 구하기
		$query = "SELECT  CONCAT(CASE WHEN length(C.today_theme_nm) > 1 THEN CONCAT('[',C.today_theme_nm,'] ',C.issue,'<BR><BR>') ELSE '' END) today_theme
								, B.issue today_issue
								, B.detail today_detail
					FROM sirianz_infostock B
					LEFT OUTER JOIN sirianz_infostock_theme C
					ON C.today_theme_cd = B.today_theme_cd
					WHERE B.report_date = (select max(date) from calendar where date < '$mochaten_date')
					AND B.code =  '$code'" ;
		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);
				
		while( $row = $result->fetch_array(MYSQLI_BOTH) ){
			$today_issue = $row['today_theme']."<b>".$row['today_issue']."</b><br>".$row['today_detail']."<br>";
		}

		// infostock에 없을 경우 signal evening에서 가져오기 -> 있어도 가져오기.. 비교
		// if($today_issue == '') {
			$query = "SELECT CONCAT('[',A.signal_grp
								, CASE WHEN length(A.theme) > 1 && A.theme != A.signal_grp THEN CONCAT(A.theme, ']<BR>') ELSE ']<BR>' END) today_theme
								, A.title today_issue
						FROM	rawdata_siri_report A
						WHERE	page_date = (select max(date) from calendar where date < '$mochaten_date')
						AND  page_fg = 'E'
						AND  today_pick = 'Y'
						AND  stock =  '$code'" ;

			// echo "<pre>$query</pre>";
			$result = $mysqli->query($query);

			while( $row = $result->fetch_array(MYSQLI_BOTH) ){
				$today_issue.= $row['today_theme']."<b>".$row['today_issue']."</b>";
			}
		// }

		// 다 없을 경우 최근뉴스 가져오기
		if($today_issue == '') {
			$query = "SELECT  date
							, title
							, link
						FROM signals B
						WHERE B.code =  '$code'
						AND B.date <= (select max(date) from calendar where date < '$mochaten_date')
						ORDER BY date DESC
						LIMIT 1 " ;

			// echo "<pre>$query</pre>";
			$result = $mysqli->query($query);

			while( $row = $result->fetch_array(MYSQLI_BOTH) ){
				$today_issue = '('.$row['date'].')'.$row['title'];
			}
		}

		// 시그널리포트 주식WHY / 주식인포 가져오기 start
		// --- 주식WHY 경로 구하기
		$query = "SELECT cd, nm_sub1, nm_sub2 FROM comm_cd WHERE cd IN ('PT002','PT003','PT004')";
		$result = $mysqli->query($query);
		while( $row = $result->fetch_array(MYSQLI_BOTH) ){
			$svpath = $row['nm_sub1'];
			$lcpath = $row['nm_sub2'];

			// 주식why 주소
			if($row['cd'] == 'PT002') {
				$svfilepath = $svpath.$code.".html";
				$lcfilepath = $lcpath.$code.".html";
			}

			// 주식why 주소
			if($row['cd'] == 'PT003') {
				$yh_svfilepath = $svpath.$code.".html";
				$yh_lcfilepath = $lcpath.$code.".html";
			}

			// 주식why 주소
			if($row['cd'] == 'PT004') {
				$yl_svfilepath = $svpath.$code.".html";
				$yl_lcfilepath = $lcpath.$code.".html";
			}
	
		}
		// --- 파일 존재여부 확인 및 출력을 위한 링크 생성
		$stockwhy ="";
		$yh_stockinfo ="";
		$yl_stockinfo ="";
		if($_SERVER["HTTP_HOST"] == 'localhost') {	// localhost 인 경우 파일 존재 확인
			$file_headers = @get_headers($svfilepath);
			// post 방식으로 넘어온 변수 확인
			// foreach($file_headers as $key=>$val){
			// 	echo "$key =>  $val \n";
			// }
			if($file_headers[0] != 'HTTP/1.1 404 Not Found'){
				$stockwhy = "&nbsp;&nbsp;<a href='".$svfilepath."' class='popup' style='text-decoration:underline; ' onclick='window.open(this.href, 'STOCKWAY', 'width=700px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>[주식WHY]</a>";
			}
			$file_headers = @get_headers($yh_svfilepath);
			if($file_headers[0] != 'HTTP/1.1 404 Not Found'){
				$yh_stockinfo = "&nbsp;&nbsp;<a href='".$yh_svfilepath."' class='popup' style='text-decoration:underline; ' onclick='window.open(this.href, 'STOCKWAY', 'width=700px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>[Sophia]</a>";
			}
			$file_headers = @get_headers($yl_svfilepath);
			if($file_headers[0] != 'HTTP/1.1 404 Not Found'){
				$yl_stockinfo = "&nbsp;&nbsp;<a href='".$yl_svfilepath."' class='popup' style='text-decoration:underline; ' onclick='window.open(this.href, 'STOCKWAY', 'width=700px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>[Sister]</a>";
			}
		} else { // 서버에서 파일 존재 확인
			if(file_exists($lcfilepath)){
				$stockwhy = "&nbsp;&nbsp;<a href='".$lcfilepath."' class='popup' style='text-decoration:underline; ' onclick='window.open(this.href, 'STOCKWAY', 'width=700px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>[주식WHY]</a>";
			}
			
			if(file_exists($yh_lcfilepath)){
				$yh_stockinfo = "&nbsp;&nbsp;<a href='".$yh_lcfilepath."' class='popup' style='text-decoration:underline; ' onclick='window.open(this.href, 'STOCKWAY', 'width=700px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>[Sophia]</a>";
			}

			if(file_exists($yl_lcfilepath)){
				$yl_stockinfo = "&nbsp;&nbsp;<a href='".$yl_lcfilepath."' class='popup' style='text-decoration:underline; ' onclick='window.open(this.href, 'STOCKWAY', 'width=700px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>[Sister]</a>";
			}
		}

		// 시그널리포트 주식WHY / 주식인포 가져오기 end

		// 차트 이미지 + 목민쌤 코멘트 출력
		$txat = (isset($comment['nomad'])) ? $comment['nomad'] : '';
		echo "<table border=1 class='table table-sm text-dark'>";
		if($brWidth > 2000) {
			// 차트 -- paxnet
			echo "<tr><td style='width: 1200px;' rowspan=4>";
			echo "<div class='chartBox'><iframe data-v-5032dc6f='' width='1200px' height='650px' scrolling='no' allowtransparency='false' src='https://www.paxnet.co.kr/stock/analysis/chartPopup?abbrSymbol=".$code."'></iframe></div>";
		} else {
			// 차트 -- 네이버이미지
			echo "<tr><td style='width: 700px;' rowspan=4>";
			echo "<h4><b>$name</b><h4>";
			echo "<img id='img_chart_area' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$code.".png?sidcode=1681518352718' width='700' height='289' alt='이미지 차트' onerror='this.src='https://ssl.pstatic.net/imgstock/chart3/world2008/error_700x289.png'>";
		}
			echo "</td><td style='font-weight:bold;background-color:#fae4f1;'>";
			echo "[$code]&nbsp;"."$stock_info";
			echo "&nbsp;&nbsp;<a href='https://new.infostock.co.kr/stockitem?code=".$code."' class='popup' style='text-decoration:underline; ' onclick='window.open(this.href, 'STOCK', 'width=700px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>[INFOSTOCK]</a>";
			echo "$stockwhy $yh_stockinfo $yl_stockinfo";
			echo "</td></tr><tr><td class='text-danger' style='font-weight:bold;'>";
			echo "$stock_keyword";
			echo "$stock_comment";
			echo "&nbsp;<input type=button class='btn-icon-split bg-info' value='+' onclick=popupStockComment()>";
			echo "</td></tr><tr><td>";
			echo "$today_issue";
			echo "</td></tr>";
		echo "</table>";
		
		if($stock_info != '') { // 모차십 내역이 있는 경우..
			// 종목 모차십 내역 표시
			echo "<div class='scroll_box' style='overflow-x: auto; white-space: nowrap;'>";
			echo "<table class='table table-sm table-bordered small text-dark' style='table-layout: fixed;' >";
			echo "<tr align=center style='background-color:#fdf9f5;'>".$td_date."</tr>";
			echo "<tr align=center>".$td_cha_fg."</tr>";
			echo "<tr align=center>".$td_close_rate."</tr>";
			echo "<tr align=center>".$td_volume."</tr>";
			echo "<tr align=center>".$td_trade_amt."</tr>";
			echo "</table>";
			echo "</div>";
		}

		//재료
        $query = "SELECT 'REPORT' seq
						, STR_TO_DATE(A.report_date, '%Y%m%d') date
						, CONCAT(CASE WHEN Y.keyword_nm is not null THEN concat('[',Y.keyword_nm,'] ') ELSE '' END
								, CASE WHEN A.today_issue is not null THEN concat(' #',A.today_issue) ELSE '' END) title
						, '' content
						, '' publisher
						, '' writer
						, '' link
						, '' keyword
						, '' signal_id
						, close_rate
						, volume
					FROM (	SELECT report_date, code, name
								, CASE WHEN MAX(infostock_issue) = '' THEN MAX(siri_issue) ELSE MAX(infostock_issue) END today_issue
							FROM (	SELECT	report_date, code, name, '' infostock_theme_issue, '' infostock_theme_detail
										, '' infostock_issue, '' infostock_detail
										, CASE  WHEN D.signal_grp is NOT NULL AND D.signal_grp != '' 
												THEN CASE WHEN D.theme is NOT NULL AND D.theme != '' THEN concat(D.signal_grp, '-', D.theme) ELSE D.signal_grp END
												ELSE CASE WHEN D.theme is NOT NULL AND D.theme != '' THEN D.theme ELSE '' END END siri_theme
										, CONCAT(CASE WHEN D.issue is NOT NULL AND D.issue != '' THEN D.issue 
													ELSE CASE WHEN D.stock_CPR != '' THEN D.stock_CPR ELSE '' END END
												) siri_issue
										, stock_connect siri_stock_connect
									FROM sirianz_evening D
									WHERE code =  '$code'
									UNION ALL
									SELECT B.report_date
										, B.code
										, B.name
										, C.issue infostock_theme_issue
										, C.detail infostock_theme_detail
										, B.issue infostock_issue
										, B.detail infostock_detail
										, '' siri_theme
										, '' siri_issue
										, '' siri_stock_connect
									FROM sirianz_infostock B
									LEFT OUTER JOIN sirianz_infostock_theme C
									ON C.today_theme_cd = B.today_theme_cd
									WHERE B.issue != ''
									  AND B.code =  '$code'
								) E
							GROUP BY report_date, code, name
							) A
							LEFT OUTER JOIN (SELECT B.date, B.close_rate, round(B.volume/1000,0) volume
									   FROM daily_price B
									  WHERE B.code =  '$code') Z
					  		ON Z.date = A.report_date
							LEFT OUTER JOIN (SELECT J.report_date
												, J.code
												, GROUP_CONCAT(K.keyword_cd ORDER BY K.sort_no, K.keyword_cd) keyword_cd
												, GROUP_CONCAT(K.keyword_nm ORDER BY K.sort_no, K.keyword_cd) keyword_nm
											FROM sirianz_report_keyword J
											INNER JOIN theme_keyword K
												ON K.keyword_cd = J.keyword_cd
											GROUP BY report_date, code) Y
							ON  Y.report_date = A.report_date
							AND Y.code = A.code
				  UNION ALL
				  SELECT 'NEWS' seq
						, STR_TO_DATE(date, '%Y%m%d') date
						, title
						, content
						, publisher
						, writer
						, link
						, CONCAT(B.grouping
						, CASE WHEN keyword  != '' THEN concat(' #',keyword ) ELSE '' END) keyword 
						, signal_id 
						, '' close_rate
						, '' volume
					 FROM signals B
                    WHERE B.code =  '$code'
					ORDER BY date DESC, seq DESC
        		" ;

		// echo "<pre>$query</pre>";
		$result = $mysqli->query($query);
		
		echo "<table class='table table-sm table-bordered small text-dark'>";
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			if($row['seq'] == "REPORT") {
				echo "<tr class='table-warning'>";
				echo "<td style='width:100px;height:12px;' align=center>".$row['date']."</td>";
				echo "<td style='width:70px;height:12px;' align=right><b>".$row['close_rate']."%</b></td>";
				echo "<td style='width:70px;height:12px;' align=right><b>".number_format($row['volume'])."K</b></td>";
				echo "<td colspan=2>".$row['title']."</td>";
				echo "</tr>";
			} else {
				echo "<tr>";
				echo "<td style='width:100px;height:12px;' align=center>".$row['date']."</td>";
				echo "<td style='height:12px;'>&nbsp</td>";
				echo "<td style='height:12px;'>&nbsp</td>";
				echo "<td style='height:12px;'> <a href=\"javascript:popupNews('".$row['link']."')\">".$row['title']."</a><br><br>".$row['content']."</td>";
				echo "<td style='height:12px;'>".$row['keyword']."</td>";
				echo "</tr>";
			}
		}
		echo "</table>";
	}
?>
<input type=hidden name=regi_id>
<input type=hidden name=mochaten_date value=<?=$mochaten_date?>>
<input type=hidden name=code value=<?=$code?>>
</form>
<iframe name="saveComment" src="mochaten_script.php" width=0 height=0>
</iframe>
</body>
<script>
function saveCmt(regi_id) {
	console.log(regi_id)
	form = document.form1;
	form.regi_id.value = regi_id;
	form.target = "saveComment";
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
function popupStockComment(link) {
	form = document.form1;
	link = '/boot/common/popup/stock_comment.php?code=' + form.code.value ;
	window.open(link,'popupComment',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=800, height=900");
}

</script>
</html>