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
</style>
</head>

<?php
$mochaten_date = (isset($_GET['mochaten_date'])) ? $_GET['mochaten_date'] : '';
$code = (isset($_GET['code'])) ? $_GET['code'] : '';
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
		$query = " SELECT STR_TO_DATE(A.mochaten_date, '%Y%m%d') mochaten_date
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
					ORDER BY A.mochaten_date desc, cha_fg
					LIMIT 20";
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
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			// 하루에 여러 항목에 포함된 경우 묶어서 (colspan) 표시되도록 처리

			// 거래대금에 따라 스타일 적용
			if($row['tot_trade_amt'] > 2000)
				$amt_style = "mark text-danger font-weight-bold h6";
			else if($row['tot_trade_amt'] > 1000)
				$amt_style = "text-danger font-weight-bold h6";
			else
				$amt_style = "font-weight-bold";

			if($pre_cha_date != $row['mochaten_date']){
				$td_date      .= "<th align=center colspan=".$row['cnt'].">". $row['mochaten_date']."</th>";
				$td_close_rate.= "<td align=center colspan=".$row['cnt'].">". $row['close_rate']."%</td>";
				$td_volume    .= "<td align=center colspan=".$row['cnt'].">". number_format($row['volume'])."K</td>";
				$td_trade_amt .= "<td align=center colspan=".$row['cnt']." class='"."$amt_style"."'>". number_format($row['tot_trade_amt'])."억</td>";
			}
			if($row['cnt'] > 1) $width = '90px';
			else				$width = '120px';
			$td_cha_fg .= "<td align=center style='width:".$width."'>". $row['cha_fg_nm']."</td>";
			$pre_cha_date = $row['mochaten_date'];

			// 가장 마지막 시총 가져오기로 변경 (일자 출력을 <- 에서 -> 방향으로 변경처리) <= 가장 최근 시총 가져오기 
			//if($i == 0) {
				$stock_nm   = $row['name'];
				$market_cap = $row['market_cap'];
				$op_ratio   = $row['op_ratio'];
				$lb_ratio   = $row['lb_ratio'];
				$dt_ratio   = $row['dt_ratio'];
			//}
			$i++;
		}

		for(; $i<20; $i++){
			$td_date       .= "<td>&nbsp;</td>";
			$td_cha_fg     .= "<td style='width:90px;'>&nbsp;</td>";
			$td_close_rate .= "<td>&nbsp;</td>";
			$td_volume	   .= "<td>&nbsp;</td>";
			$td_trade_amt  .= "<td>&nbsp;</td>";
		}
		// style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);"
		echo "<div class='row card-header'>
			<div><h4 class='text-danger'>♡ $stock_nm  (시총 ".number_format($market_cap)." 억) </h4> </div>
			<div style='width:20px;'> </div>
			<div style='margin-top: 0.375rem;'><h6> (유통비율: ".$dt_ratio." %, 영업이익률: ".$op_ratio." %, 부채율: ".$lb_ratio." %)</h6></div>
			</div>";

		
		// 종목 이력 표시
		echo "<table class='table table-sm table-bordered small text-dark'>";
		echo "<tr align=center style='background-color:#fdf9f5'>".$td_date."</tr>";
		echo "<tr align=center>".$td_cha_fg."</tr>";
		echo "<tr align=center>".$td_close_rate."</tr>";
		echo "<tr align=center>".$td_volume."</tr>";
		echo "<tr align=center>".$td_trade_amt."</tr>";
		echo "</table>";

		
		// 종목 차트 이미지 경로 구하기
		$query = "SELECT nm_sub1, nm_sub2 FROM comm_cd WHERE cd = 'PT001'";
		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);
		
		$svpath = $row['nm_sub1'];
		$lcpath = $row['nm_sub2'];
		
		// 종목 차트일자 구하기
		$query = "SELECT REPLACE(MAX(date), '-', '') pre_date FROM calendar WHERE date < '".$_GET['mochaten_date']."'";
		$result = $mysqli->query($query);
		$row = $result->fetch_array(MYSQLI_BOTH);

		//이미지 확장자 bmp png 둘다 사용. bmp 확인 후 없을 경우 png 불러오기
		//서버별로 별도 적용		
		//bmp 용량이 너무 커서 png만 사용해야함
		if($_SERVER["HTTP_HOST"] == 'localhost') {

			$filepath = $svpath.$row['pre_date']."_".$code.".png";

			$file_headers = @get_headers($filepath);
			if($file_headers[0] == 'HTTP/1.1 404 Not Found'){
				$filepath = $svpath.$row['pre_date']."_".$code.".bmp";
			}
		} else {
			$filepath = $lcpath.$row['pre_date']."_".$code.".png";

			if(!file_exists($filepath)) {
				$filepath = $lcpath.$row['pre_date']."_".$code.".bmp";
			}
		}
		//echo $filepath;
		

		// 차트 평가 내역 가져오기
		$query = "SELECT regi_id, chart_comment, chart_grade, chart_pick FROM mochaten_comment WHERE mochaten_date=$mochaten_date AND code='$code'";
		$result = $mysqli->query($query);

		$chart_comment = array();
		$chart_grade = array();
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			$chart_comment[$row['regi_id']] = $row['chart_comment'];
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
		$txat = (isset($chart_comment['nomad'])) ? $chart_comment['nomad'] : '';
		echo "<table border=1 class='table table-sm text-dark'>";
			echo "<tr><td style='width:62%;' rowspan=3>";
			echo "<article class='content'>
					<img src='$filepath' onclick='fnImgPop(this.src)'/>
				</article>";
			echo "</td><td style='font-weight:bold;'>";
			echo "★ 목민쌤 코멘트";
			echo "</td></tr><tr><td>";
			echo "<textarea name='nomad_commnet' style='width:100%; height:370px;'>$txat</textarea>";
			echo "</td></tr><tr><td>";
			echo "차트평가 : <select name='nomad_grade'>$options</select>";
			echo "&nbsp;&nbsp;&nbsp; ";
			echo "<input type=button class='btn btn-secondary' onclick=\"saveCmt('nomad')\" value='목민쌤 저장'>";
			echo "</td></tr>";
		echo "</table>";

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
		$txat_sophia = (isset($chart_comment['sophia'])) ? $chart_comment['sophia'] : '';		
		$txat_sister = (isset($chart_comment['sister'])) ? $chart_comment['sister'] : '';
		echo "<div style='height:5px;'></div>";
		echo "<table class='table table-sm text-dark'>";
		echo "<tr><td><b>☆ 소피아 코멘트</b></td><td><b>☆ 시스터 코멘트</b></td></tr>";
		echo "<tr>";
		echo "<td style='height:180px;'><textarea name='sophia_commnet' style='width:99%; height:180px;'>$txat_sophia</textarea></td>";
		echo "<td style='height:180px;'><textarea name='sister_commnet' style='width:99%; height:180px;'>$txat_sister</textarea></td>";
		echo "</tr>";
		echo "<tr><td valign=center>";
		echo "차트평가 : $rdo_sophia";
		echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ";
		echo "<input type=button class='btn btn-secondary' onclick=\"saveCmt('sophia')\" value='소피아 저장'>";
		echo "</td><td valign=center>";
		echo "차트평가 : $rdo_sister";
		echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ";
		echo "<input type=button class='btn btn-secondary' onclick=\"saveCmt('sister')\" value='시스터 저장'>";
		echo "</td></tr>";
		echo "</table>";


		//재료
        $query = "SELECT 'REPORT' seq
						, STR_TO_DATE(A.report_date, '%Y%m%d') date
						, CONCAT(
						CASE WHEN Z.today_theme_nm is not null THEN concat('[',Z.today_theme_nm,'] ') ELSE '' END
						, CASE WHEN A.issue is not null THEN concat(' #',A.issue) ELSE '' END
						, CASE WHEN A.evening_str is not null THEN concat(' #',A.evening_str) ELSE '' END
						, CASE WHEN Z.issue != '' THEN concat('  @@@ ',Z.issue) ELSE '' END) title
						, '' publisher
						, '' writer
						, '' link
						, '' keyword
						, '' signal_id
					FROM siriens_infostock A
					LEFT OUTER JOIN siriens_infostock_theme Z
					ON Z.today_theme_cd = A.today_theme_cd
				   WHERE A.code =  '$code'
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
					 FROM signals B
                    WHERE B.code =  '$code'
                    ORDER BY seq desc, date DESC
					LIMIT 20
        		" ;
		// echo "$query";
		$result = $mysqli->query($query);
		
		echo "<div style='height:5px;'></div>";
		echo "<table class='table table-sm table-bordered small text-dark'>";
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			if($row['seq'] == "REPORT") {
				echo "<tr class='table-warning'>";
				echo "<td style='width:130px;height:12px;' align=center>".$row['date']."</td>";
				echo "<td colspan=2>".$row['title']."</td>";
				echo "</tr>";
			} else {
				echo "<tr>";
				echo "<td style='width:130px;height:12px;' align=center>".$row['date']."</td>";
				echo "<td style='height:12px;'>".$row['title']."</td>";
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

</script>
</html>