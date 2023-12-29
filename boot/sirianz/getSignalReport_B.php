<!DOCTYPE html>
<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");

$signal_page = (isset($_GET['signal_page'])) ? $_GET['signal_page'] : '';

if($_SERVER["HTTP_HOST"] == 'localhost') {
    $table_width = '2000px';
	$link_width = '600px';
} else {
    $table_width = '1500px';
	$link_width = '400px';
}

?>
<style>
  table {
    width: 100%;
    border-top: 1px solid #444444;
    border-collapse: collapse;
  }
  th, td {
    border-bottom: 1px solid #444444;
    padding: 10px;
  }
</style>
</head>

<body>
<form method='POST' action='getSignalReport_script.php'>
<?php
	if($signal_page == ''){
		echo "<h1><font color=red>나는 내가 원하는 것을 이룰 수 있는 충분한 능력이 있다!! </font><br><br><br><br></h1>";
		echo "<h3>[작업순서]<br><br></h3>";
		echo "<h3>1. 시그널리포트 페이지 소스 (HTML) 저장<br><br>2.DBUp_SignalReport.py 실행<br><br>3.현재페이지에서 조회</h3>";
	} else {
		// 검색조건 값
		$key_val = explode("/", $signal_page);

		// 시그널리포트 불러오기
		$qry  = " SELECT  A.id
						, A.page_date
						, A.page_fg
						, A.signal_grp
						, A.signal_id
						, A.theme
						, A.stocks
						, A.code
						, A.stock
						, A.today_pick
						, A.link
						, A.title
						, A.content
						, CASE WHEN A.publisher != '' or A.publisher is not null THEN CONCAT('[',A.publisher,']') ELSE '' END publisher
						, A.writer
						, A.date
						, A.time
						, A.grouping
						, A.keyword
						, A.exists_yn
						, A.del_yn
						, A.save_yn
						, Z.close_rate
						, Z.volume
						, D.confirm_fg
						, E.tot_trade_amt
					FROM  rawdata_siri_report A
					LEFT OUTER JOIN (SELECT C.code, C.name, B.close_rate, round(B.volume/1000,0) volume
					                   FROM daily_price B
									  INNER JOIN stock C
									     ON C.code = B.code
									  WHERE B.DATE = '$key_val[0]'
									    AND C.last_yn = 'Y') Z
					  ON Z.code = A.code
					LEFT OUTER JOIN signals D
					  ON D.signal_id = A.signal_id
					LEFT OUTER JOIN (SELECT code, tot_trade_amt FROM mochaten WHERE mochaten_date = (SELECT REPLACE(MIN(date), '-', '') FROM calendar WHERE date > '$key_val[0]') AND cha_fg = 'MC000' ) E
					  ON E.code = A.code
				   WHERE A.page_date = '$key_val[0]'
				     AND A.page_fg   = '$key_val[1]'
					 AND A.del_yn = 'N'
					ORDER BY A.id ";

		// echo $qry;
		$result = $mysqli->query($qry);

		echo "<br>";
		echo "<table border=1 cellpadding=5 rowpadding=2 style='width: ".$table_width.";font-size:15px;'>";

		$i=0;
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
		    $id          = $row['id'];
			$page_date   = $row['page_date'];
			$page_fg     = $row['page_fg'];
			$signal_grp  = $row['signal_grp'];
			$signal_id   = $row['signal_id'];
			$theme       = $row['theme'];
			$stocks      = $row['stocks'];
			$code        = $row['code'];
			$stock       = $row['stock'];
			$link        = $row['link'];
			$title       = $row['title'];
			$date        = $row['date'];
			$time        = $row['time'];
			$publisher   = $row['publisher'];
			$writer      = $row['writer'];
			$content     = $row['content'];
			$grouping    = $row['grouping'];
			$keyword     = $row['keyword'];
			$exists_yn   = $row['exists_yn'];
			$del_yn      = $row['del_yn'];
			$save_yn     = $row['save_yn'];
			$today_pick  = $row['today_pick'];
			$close_rate  = $row['close_rate'];
			$volume      = $row['volume'];
			$confirm_fg  = $row['confirm_fg'];
			$tot_trade_amt = $row['tot_trade_amt'];

			// 화면 간소화 위해 몇몇 항목 빼고 조정처리, 추가 시 TD 재정리 필요  2023.04.09
			// if($today_pick == 'Y') {
			// 	$checkedP = "checked";
			// 	$checkedC = "";
			// 	$borderColor = "border-color: #f77d72;";
			// } else {
			// 	$checkedP = "";
			// 	$checkedC = "checked";
			// 	$borderColor = "";
			// }

			// if($confirm_fg == '1') {
			// 	$borderColor .= "background-color: #acf0d7;";
			// }

			// echo "
			// <tr style='background-color:#f5d8d6'>
			// 	<td style='width:50px'><input type=checkbox name=id$i value='$id' style='zoom:1.3;')><input type=hidden name=h_id$i value='$id')></td>
			// 	<td style='width:280px'><input type=text name=signal_grp$id  value='$signal_grp'  style='width:100px'><input type=text name=thema$id  value='$thema'  style='width:140px'></td>
			// 	<td style='font-size:13pt'><b>($exists_yn/$confirm_fg)<textarea name=title$id style='width:550px; height:15px; $borderColor'>$title</textarea></b></td>
			// 	<td style='width:100px'>$publisher</td>
			// 	<td style='width:90px'><input type=text name=writer$id value='$writer' style='width:80px'></td>
			// 	<td style='width:180px'><input type=text name=date$id value='$date' style='width:80px'> <input type=text name=time$id value='$time' style='width:75px'></td>
			// 	<td style='width:800px'><a href=\"javascript:popupNews('".$link."')\">$link</a></td>
			// </tr>
			// <tr valign=top>
			// 	<td><input type=radio name=today_pick$id value='Y' $checkedP>Pick
			// 		<input type=radio name=today_pick$id value='N' $checkedC>X</td>
			// 	<td colspan=2>
			// 	    <b>P:</b> <input type=text name=stocks$id value='$stocks' style='width:650px'>
			// 	    <b>C:</b> <input type=text name=stock$id  value='$stock'  style='width:150px'></td>
			// 	<td style='width:100px'><a href=\"javascript:findNews('$code')\">$close_rate %</td>
			// 	<td><input type=text name=grouping$id value='$grouping' style='width:100%'></td>
			// 	<td><input type=text name=keyword$id value='$keyword' style='width:100%'></td>
			// 	<td><textarea name=content$id style='width:780px; height:60px'>$content</textarea></td>
			// </tr>

			if($today_pick == 'Y') {
				$checked = "checked";
				//$borderColor = "border-color: #f77d72;";

				$borderColor = "border: thick double #f77d72;";
			} else {
				$checked = "";
				$borderColor = "";
			}

			if($confirm_fg == '1') {
				$borderColor .= "background-color: #acf0d7;";
			}

			// 1차 간소화 이후, 시그널이브닝에서 뉴스 확인하고, 등록용으로만 활용하도록 2차 정리. 사용해보고 1차로 다시 돌아올지 고민해보자. 2023.04.07
			// echo "
			// <tr style='background-color:#faf0f0'>
			// 	<!--td style='width:50px'><input type=checkbox name=id$i value='$id' style='zoom:1.3;')></td//-->
			// 	<td style='width: 550px;' align=right><b><textarea name=title$id style='width:550px; height:15px; $borderColor'>$title</textarea></b><input type=hidden name=h_id$i value='$id')></td>
			// 	<td style='width: 150px;'><input type=text name=stock$id  value='$stock' style='width:150px;border: 0.1rem solid;zoom:1.1;background-color: #fdeaea;'> <a href=\"javascript:findNews('$code')\"></td>
			// 	<td style='width: 130px;'><b>$close_rate % </b></td>
			// 	<!--td style='width:90px'><input type=text name=writer$id value='$writer' style='width:80px'></td//-->
			// 	<td style='width:200px'><input type=text name=date$id value='$date' style='width:80px'> <!--input type=text name=time$id value='$time' style='width:75px'//--></td>
			// 	<td style='width:600px;overflow:hidden;text-overflow;ellipsis;'>$publisher <a href=\"javascript:popupNews('".$link."')\">$link</a></td>
			// </tr>
			// <tr valign=top>
			// 	<td colspan=2 align=right>
			// 	<input type=text name=signal_grp$id  value='$signal_grp'  style='width:100px'><input type=text name=theme$id  value='$theme'  style='width:140px'>
			// 	</td>
			// 	<td rowspan=2><input type=checkbox name=today_pick$id value='Y' $checked>Pick</td>
			// 	<td rowspan=2><input type=text name=grouping$id value='$grouping' style='width:100%'><br><br>
			// 		<input type=text name=keyword$id value='$keyword' style='width:100%'></td>
			// 	<td rowspan=2><textarea name=content$id style='width:800px; height:70px'>$content</textarea></td>
			// </tr>
			// <tr valign=top>
			// 	<td colspan=2 align=right style='font-size:10pt'>$stocks</td>
			// </tr>
			// ";

			// 2차정리. 화면에서 뉴스제목, 종목연결, 투데이픽여부, 뉴스일자만 등록하는걸로.. 2023.04.07
			// 뉴스링크 카피를 위해 signal_id 보여주기.. 2023.09.23

			if($tot_trade_amt != '') {
				$mochaten0 = "border: thick double #f77d72;";
			} else {
				$mochaten0 = "";
			}
			if($stock != "" and $code == '') $code_info = "<h4><font color=red><b>X</b></font></h4>";
			else $code_info = "";
			echo "
			<tr style='background-color:#faf0f0'>
				<td align=right><b><textarea name=title$id style='width:550px; height:15px; $borderColor'>$title</textarea></b><input type=hidden name=h_id$i value='$id')></td>
				<td><input type=text name=stock$id  value='$stock' style='width:150px;border: 0.1rem solid;zoom:1.1;background-color: #fdeaea;$mochaten0'> $code_info <a href=\"javascript:findNews('$code')\"></td>
				<td style='width:130px'><b>$close_rate % / ".number_format($volume)."K</b></td>
				<!--td style='width:90px'><input type=text name=writer$id value='$writer' style='width:80px'></td//-->
				<td><input type=text name=date$id value='$date' style='width:80px'> <!--input type=text name=time$id value='$time' style='width:75px'//--></td>
				<td style='width:".$link_width.";  overflow:hidden;text-overflow;ellipsis;white-space:nowrap;'>$publisher / $signal_id/ <a href=\"javascript:popupNews('".$link."')\" style='font-size:.5rem;'>".substr($link,0,60)."</a></td>
			</tr>
			<tr valign=top>
				<td colspan=2 style='font-size:10pt' align=right>$stocks</td>
				<td><input type=checkbox name=today_pick$id value='Y' $checked>Pick &nbsp;<input type=checkbox name=id$i value='$id')> 삭제</td>
				<td colspan=2><input type=text name=signal_grp$id  value='$signal_grp'  style='width:100px'><input type=text name=theme$id  value='$theme'  style='width:140px'></td>
			";


			$i++;

		}		
		echo "<input type=hidden name=total_cnt value=$i>";
		echo "<input type=hidden name=proc_fg>";
		echo "<input type=hidden name=page_date value='$key_val[0]'>";
		echo "<input type=hidden name=page_fg   value='$key_val[1]'>";
	}
?>

</form>
</body>
<script>
function delNews() {
	document.forms[0].proc_fg.value = 'D';
	document.forms[0].submit();
}

function saveNews() {
	document.forms[0].proc_fg.value = 'S';
	document.forms[0].submit();
}

function procNews() {
	document.forms[0].proc_fg.value = 'P';
	document.forms[0].submit();
}
function popupNews(link) {
	window.open(link,'popupNews',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1500, height=1000");
	// parent.iframe_news.src = link
}
function findNews(code) {
	window.open("./getSignalReport_sub4.php?code="+code,'findNews',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1500, height=1000");
}


</script>
</html>