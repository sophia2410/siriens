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
$mochaten_date = (isset($_GET['mochaten_date'])) ? $_GET['mochaten_date'] : '';
$code = (isset($_GET['code'])) ? $_GET['code'] : '';
$name = (isset($_GET['name'])) ? $_GET['name'] : '';

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';
?>

<body>
<form name="form1" method='POST' action='mochaten_script.php'>
<?php
	if($mochaten_date == ''){
		echo "<h3></h3>";
	}else if($code == ''){
		echo "<h3>종목을 선택해주세요!!</h3>";
	} else {
		// 모차십 등록 이력 불러오기
		$query = " SELECT STR_TO_DATE(A.trade_date   , '%Y%m%d') trade_date_str
						, A.mochaten_date
						, A.trade_date
						, C.cnt
						, A.cha_fg
						, D.name
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
						, A.keyword1
						, A.keyword2
						, A.keyword3
						, A.keyevent
						, A.create_dtime
					FROM mochaten A
					INNER JOIN comm_cd B
					ON B.cd = A.cha_fg
					INNER JOIN (SELECT mochaten_date, count(*) cnt
								  FROM mochaten
								 WHERE code = '$code'
								 group by mochaten_date) C
					ON C.mochaten_date = A.mochaten_date
					INNER JOIN stock D
					ON D.code = A.code
					AND D.last_yn = 'Y'
					WHERE A.code = '$code'
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
		$day0_date = "";
		$day0_keyword1 = "";
		$day0_keyword2 = "";
		$day0_keyword3 = "";
		$day0_keyevent = "";
		$day0_mochaten_date = "";
		$temp_market_cap = "";
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			// 하루에 여러 항목에 포함된 경우 묶어서 (colspan) 표시되도록 처리

			// 거래대금에 따라 스타일 적용
			if($row['tot_trade_amt'] > 2000)
				$amt_style = "mark text-danger font-weight-bold h6";
			else if($row['tot_trade_amt'] > 1000)
				$amt_style = "text-danger font-weight-bold h6";
			else
				$amt_style = "font-weight-bold";

			if($pre_cha_date != $row['trade_date_str']){
				$td_date      .= "<th align=center colspan=".$row['cnt']." style='width:100px; height: 30px;'>". $row['trade_date_str']."</th>";
				$td_close_rate.= "<td align=center colspan=".$row['cnt']." style='width:100px; height: 30pxpx;'>". $row['close_rate']."%</td>";
				$td_volume    .= "<td align=center colspan=".$row['cnt']." style='width:100px; height: 30pxpx;'>". number_format($row['volume'])."K</td>";
				$td_trade_amt .= "<td align=center colspan=".$row['cnt']."  style='width:100px; height: 30pxpx;' class='"."$amt_style"."'>". number_format($row['tot_trade_amt'])."억</td>";
				$temp_market_cap .= "<td align=center colspan=".$row['cnt']."  style='width:100px; height: 30pxpx;'>". number_format($row['market_cap'])."억</td>";
			}
			if($row['cnt'] > 1) $width = round(100/$row['cnt'],0).'px';
			else				$width = '100px';
			$td_cha_fg .= "<td align=center style='width:".$width." overflow:hidden;text-overflow;ellipsis;white-space:nowrap;' >". $row['cha_fg_nm']."</td>";
			$pre_cha_date = $row['trade_date_str'];

			// 가장 최근 0일차 키워드 가져오기
			if($day0_date == '' && $row['cha_fg'] == 'MC000') {
				$day0_date = $row['trade_date_str'];
				$day0_mochaten_date = $row['mochaten_date'];
				$day0_keyword1 = $row['keyword1'];
				$day0_keyword2 = $row['keyword2'];
				$day0_keyword3 = $row['keyword3'];
				$day0_keyevent = $row['keyevent'];
			}

			// 가장 최근 시총 가져오기 
			if($i == 0) {
				$stock_nm   = $row['name'];
				$market_cap = $row['market_cap'];
				$op_ratio   = $row['op_ratio'];
				$lb_ratio   = $row['lb_ratio'];
				$dt_ratio   = $row['dt_ratio'];
			}

			$i++;
		}

		for(; $i<15; $i++){
			$td_date       .= "<td style='width:100px;'>&nbsp;</td>";
			$td_cha_fg     .= "<td style='width:100px;'>&nbsp;</td>";
			$td_close_rate .= "<td style='width:100px;'>&nbsp;</td>";
			$td_volume	   .= "<td style='width:100px;'>&nbsp;</td>";
			$td_trade_amt  .= "<td style='width:100px;'>&nbsp;</td>";
			$temp_market_cap .= "<td style='width:100px;'>&nbsp;</td>";
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
					FROM siriens_infostock B
					LEFT OUTER JOIN siriens_infostock_theme C
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

		// 차트 평가 내역 가져오기
		$query = "SELECT regi_id, chart_comment, chart_grade, chart_pick, geo, cha, jae, si FROM mochaten_comment WHERE mochaten_date=$mochaten_date AND code='$code'";
		$result = $mysqli->query($query);

		$comment = array();
		$chart_grade = array();
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			$geo[$row['regi_id']] = $row['geo'];
			$cha[$row['regi_id']]   = $row['cha'];
			$jae[$row['regi_id']] = $row['jae'];
			$si[$row['regi_id']]   = $row['si'];
			$comment[$row['regi_id']] = $row['chart_comment'];
			$chart_grade[$row['regi_id']]   = $row['chart_grade'];
		}

		// 목민쌤 차트 평가등급 공통코드 가져오기
		$query = "SELECT cd, nm FROM comm_cd WHERE l_cd = 'CNM00' order by ord_no*1";
		$result = $mysqli->query($query);
		
		$options = "<option value=''>--choose an option--</option>";
		$nomad_grade = (isset($chart_grade['nomad'])) ? $chart_grade['nomad'] : '';
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			if($nomad_grade == $row['cd']) {
				$options .= "<option value='". $row['cd']."' selected>". $row['nm']."</option>";
			} else {
				$options .= "<option value='". $row['cd']."'>". $row['nm']."</option>";
			}
		}
		
		// 차트 이미지 + 목민쌤 코멘트 출력
		$txat = (isset($comment['nomad'])) ? $comment['nomad'] : '';
		echo "<table border=1 class='table table-sm text-dark'>";
		if($brWidth > 2000) {
			// 차트 -- paxnet
			echo "<tr><td style='width: 1200px;' rowspan=7>";
			echo "<div class='chartBox'><iframe data-v-5032dc6f='' width='1200px' height='650px' scrolling='no' allowtransparency='false' src='https://www.paxnet.co.kr/stock/analysis/chartPopup?abbrSymbol=".$code."'></iframe></div>";
		} else {
			// 차트 -- 네이버이미지
			echo "<tr><td style='width: 700px;' rowspan=7>";
			echo "<h4><b>$stock_nm</b><h4>";
			echo "<img id='img_chart_area' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$code.".png?sidcode=1681518352718' width='700' height='289' alt='이미지 차트' onerror='this.src='https://ssl.pstatic.net/imgstock/chart3/world2008/error_700x289.png'>";
		}
			echo "</td><td style='font-weight:bold;background-color:#fae4f1;'>";
			echo " 시총 ".number_format($market_cap)." 억 &nbsp; / &nbsp; 유통비율: ".$dt_ratio." %, 영업이익률: ".$op_ratio." %, 부채율: ".$lb_ratio." %";
			echo "</td></tr><tr><td class='text-danger' style='font-weight:bold;'>";
			echo "$stock_keyword";
			echo "$stock_comment";
			echo "&nbsp;<input type=button class='btn-icon-split bg-info' value='+' onclick=popupStockComment()>";
			echo "</td></tr><tr><td>";
			echo "$today_issue";
			echo "</td></tr><tr><td style='font-weight:bold;'>";
			echo "<b>".$day0_date."</b> <input type=button class='btn btn-secondary btn-sm' onclick=\"saveKeyword('keyword')\" value='키워드 저장'><br>0일차 키워드 &nbsp;";
			echo "<input type=text name='day0_keyword1' style='width:200px;' value=\"$day0_keyword1\"><input type=hidden name=day0_date value='$day0_mochaten_date'>";
			echo "<input type=text name='day0_keyword2' style='width:200px;' value=\"$day0_keyword2\">";
			echo "<input type=text name='day0_keyword3' style='width:200px;' value=\"$day0_keyword3\"><br>0일차 이벤트 &nbsp;";
			echo "<input type=text name='day0_keyevent' style='width:600px;' value=\"$day0_keyevent\">";
			echo "</td></tr><tr><td style='font-weight:bold;'>";
			echo "★ 목민쌤 코멘트";
			echo "</td></tr><tr><td>";
			echo "<textarea name='nomad_commnet' style='width:100%; height:170px;'>$txat</textarea>";
			echo "</td></tr><tr><td>";
			echo "차트평가 : <select name='nomad_grade'>$options</select>";
			echo "&nbsp;&nbsp;&nbsp; ";
			echo "<input type=button class='btn btn-secondary btn-sm' onclick=\"saveCmt('nomad')\" value='목민쌤 저장'>";
			echo "</td></tr>";
		echo "</table>";
		
		// 종목 모차십 내역 표시
		echo "<div class='scroll_box' style='overflow-x: auto; white-space: nowrap;'>";
		echo "<table class='table table-sm table-bordered small text-dark' style='table-layout: fixed;' >";
		echo "<tr align=center style='background-color:#fdf9f5;'>".$td_date."</tr>";
		echo "<tr align=center>".$td_cha_fg."</tr>";
		echo "<tr align=center>".$td_close_rate."</tr>";
		echo "<tr align=center>".$td_volume."</tr>";
		echo "<tr align=center>".$td_trade_amt."</tr>";
		echo "<tr align=center>".$temp_market_cap."</tr>";
		echo "</table>";
		echo "</div>";

		// 차트 평가등급 공통코드 가져오기 
		$query = "SELECT cd, nm, nm_sub1 FROM comm_cd WHERE l_cd = 'CHG00'";
		$result = $mysqli->query($query);
		
		// 평가등급 표시 라디오버튼 생성
		$rdo_sophia = "";
		$rdo_sister = "";

		$sophia_grade = (isset($chart_grade['sophia'])) ? $chart_grade['sophia'] : '';
		$sister_grade = (isset($chart_grade['sister'])) ? $chart_grade['sister'] : '';
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			if($sophia_grade == $row['nm_sub1']) {
				$rdo_sophia .= "<input type=radio class='radio' name=sophia_grade value='".$row['nm_sub1']."' checked>".$row['nm']."&nbsp;";
			} else {
				$rdo_sophia .= "<input type=radio class='radio' name=sophia_grade value='".$row['nm_sub1']."'>".$row['nm']."&nbsp;";
			}

			if($sister_grade == $row['nm_sub1']) {
				$rdo_sister .= "<input type=radio class='radio' name=sister_grade value='".$row['nm_sub1']."' checked>".$row['nm']."&nbsp;";
			} else {
				$rdo_sister .= "<input type=radio class='radio' name=sister_grade value='".$row['nm_sub1']."'>".$row['nm']."&nbsp;";
			}
		}

		$rdo_sophia .= "<input type=radio name=sophia_grade value=''>점수없음 ";
		$rdo_sister .= "<input type=radio name=sister_grade value=''>점수없음 ";

		//차트 코멘트 등록
		$sophia_geo = (isset($geo['sophia'])) ? $geo['sophia'] : '';
		$sister_geo = (isset($geo['sister'])) ? $geo['sister'] : '';
		$sophia_cha = (isset($cha['sophia'])) ? $cha['sophia'] : '';
		$sister_cha = (isset($cha['sister'])) ? $cha['sister'] : '';
		$sophia_jae = (isset($jae['sophia'])) ? $jae['sophia'] : '';
		$sister_jae = (isset($jae['sister'])) ? $jae['sister'] : '';
		$sophia_si  = (isset($si['sophia'])) ? $si['sophia'] : '';
		$sister_si  = (isset($si['sister'])) ? $si['sister'] : '';
		$sophia_cmt = (isset($comment['sophia'])) ? $comment['sophia'] : '';
		$sister_cmt = (isset($comment['sister'])) ? $comment['sister'] : '';
		echo "<div style='height:5px;'></div>";
		echo "<table class='table table-sm text-dark'>";
		echo "<tr><td colspan=2><b>☆ 시스터</b><td colspan=2><b>☆ 소피아</b></td></td></tr>";
		echo "<tr align=center>";
		echo "<td style='width:60px;'> 거 </td>";
		echo "<td style='height:40px;'><textarea name='sister_geo' style='width:99%; height:40px;'>$sister_geo</textarea></td>";
		echo "<td style='width:60px;'> 거 </td>";
		echo "<td style='height:40px;'><textarea name='sophia_geo' style='width:99%; height:40px;'>$sophia_geo</textarea></td>";
		echo "</tr>";
		echo "<tr align=center>";
		echo "<td> 차 </td>";
		echo "<td style='height:40px;'><textarea name='sister_cha' style='width:99%; height:40px;'>$sister_cha</textarea></td>";
		echo "<td> 차 </td>";
		echo "<td style='height:40px;'><textarea name='sophia_cha' style='width:99%; height:40px;'>$sophia_cha</textarea></td>";
		echo "</tr>";
		echo "<tr align=center>";
		echo "<td> 재 </td>";
		echo "<td style='height:40px;'><textarea name='sister_jae' style='width:99%; height:40px;'>$sister_jae</textarea></td>";
		echo "<td> 재 </td>";
		echo "<td style='height:40px;'><textarea name='sophia_jae' style='width:99%; height:40px;'>$sophia_jae</textarea></td>";
		echo "</tr>";
		echo "<tr align=center>";
		echo "<td> 시 </td>";
		echo "<td style='height:40px;'><textarea name='sister_si' style='width:99%; height:40px;'>$sister_si</textarea></td>";
		echo "<td> 시 </td>";
		echo "<td style='height:40px;'><textarea name='sophia_si' style='width:99%; height:40px;'>$sophia_si</textarea></td>";
		echo "</tr>";
		echo "<tr align=center>";
		echo "<td> 코멘트 </td>";
		echo "<td style='height:60px;'><textarea name='sister_commnet' style='width:99%; height:60px;'>$sister_cmt</textarea></td>";
		echo "<td> 코멘트 </td>";
		echo "<td style='height:60px;'><textarea name='sophia_commnet' style='width:99%; height:60px;'>$sophia_cmt</textarea></td>";
		echo "</tr>";
		echo "<tr><td valign=center colspan=2>";
		echo "차트평가 : $rdo_sister";
		echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ";
		echo "<input type=button class='btn btn-secondary btn-sm' onclick=\"saveCmt('sister')\" value='시스터 저장'>";
		echo "</td><td valign=center colspan=2>";
		echo "차트평가 : $rdo_sophia";
		echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ";
		echo "<input type=button class='btn btn-secondary btn-sm' onclick=\"saveCmt('sophia')\" value='소피아 저장'>";
		echo "</td></tr>";
		echo "</table>";

		//재료
        $query = "SELECT 'REPORT' seq
						, STR_TO_DATE(A.report_date, '%Y%m%d') date
						, CONCAT(CASE WHEN A.today_theme is not null THEN concat('[',A.today_theme,'] ') ELSE '' END
								, CASE WHEN A.today_issue is not null THEN concat(' #',A.today_issue) ELSE '' END) title
						, '' publisher
						, '' writer
						, '' link
						, '' keyword
						, '' signal_id
						, close_rate
						, volume
					FROM (	SELECT report_date, code, name
								, CASE WHEN MAX(infostock_theme) = '' THEN MAX(siri_theme) ELSE MAX(infostock_theme) END today_theme
								, CASE WHEN MAX(infostock_issue) = '' THEN MAX(siri_issue) ELSE MAX(infostock_issue) END today_issue
							FROM (	SELECT	report_date, code, name, '' infostock_theme, '' infostock_theme_issue, '' infostock_theme_detail
										, '' infostock_issue, '' infostock_detail
										, CASE  WHEN D.signal_grp is NOT NULL AND D.signal_grp != '' 
												THEN CASE WHEN D.theme is NOT NULL AND D.theme != '' THEN concat(D.signal_grp, '-', D.theme) ELSE D.signal_grp END
												ELSE CASE WHEN D.theme is NOT NULL AND D.theme != '' THEN D.theme ELSE '' END END siri_theme
										, CONCAT(CASE WHEN D.issue is NOT NULL AND D.issue != '' THEN D.issue 
													ELSE CASE WHEN D.stock_CPR != '' THEN D.stock_CPR ELSE '' END END
												) siri_issue
										, stock_connect siri_stock_connect
									FROM siriens_evening D
									WHERE code =  '$code'
				  UNION ALL
									SELECT B.report_date
										, B.code
										, B.name
										, C.today_theme_nm infostock_theme
										, C.issue infostock_theme_issue
										, C.detail infostock_theme_detail
										, B.issue infostock_issue
										, B.detail infostock_detail
										, '' siri_theme
										, '' siri_issue
										, '' siri_stock_connect
									FROM siriens_infostock B
									LEFT OUTER JOIN siriens_infostock_theme C
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
				  UNION ALL
				  SELECT 'NEWS' seq
						, STR_TO_DATE(date, '%Y%m%d') date
						, title
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
				echo "<td style='height:12px;'> <a href=\"javascript:popupNews('".$row['link']."')\">".$row['title']."</td>";
				echo "<td style='height:12px;'>".$row['keyword']."</td>";
				echo "</tr>";
			}
		}
		echo "</table>";
	}
?>
<input type=hidden name=regi_id>
<input type=hidden name=proc_fg>
<input type=hidden name=mochaten_date value=<?=$mochaten_date?>>
<input type=hidden name=code value=<?=$code?>>
</form>
<iframe name="saveComment" src="mochaten_script.php" width=1000 height=100>
</iframe>
</body>
<script>
function saveKeyword(proc_fg) {
	form = document.form1;
	form.proc_fg.value = proc_fg;
	form.target = "saveComment";
	form.submit();
}

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