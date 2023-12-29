<!DOCTYPE html>
<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");
?>
</head>
<body>
<form method="post" action="news_script.php">
<?php

	if(isset($_GET['link'])){ 
		$link_date    = $_GET['link_date'];
		$link = str_replace('^amp^', '&', $_GET['link']);

		
	// 뉴스 id 구하기
	$qry1  = " SELECT signal_id
				    , date
					, time
					, title
					, content
					, publisher
					, writer
					, link
					, keyword
					, signal_group
					, signal_fg
					, pick_fg
					, rapport_fg
				 FROM signals_web WHERE link = '$link'";

	echo $qry1;
	file_put_contents("a.txt",$qry1); // 값 파일로 찍어보기
	$result = $mysqli->query($qry1);
	$row = $result->fetch_array(MYSQLI_BOTH);

	// echo $qry1;
	$signal_id    = $row['signal_id'];
	$date         = $row['date'];
	$time         = $row['time'];
	$title        = $row['title'];
	$content      = $row['content'];
	$publisher    = $row['publisher'];
	$name         = $row['writer'];
	$keyword      = $row['keyword'];
	$signal_group = $row['signal_group'];
	$signal_fg    = $row['signal_fg'];
	$pick_fg      = $row['pick_fg'];
	$rapport_fg   = $row['rapport_fg'];


	echo "
	<div class='row' style='margin:4px'>
	<h3><font color=red>!!저장된 뉴스!!</font></h3>
	<input type=hidden name=signal_id value='$signal_id'>
	</div>
	<div class='row' style='margin:4px'>
	<label style='display:inline-block; width:90px;font-weight:bold; font-size:18px;'> [제목] </label>
	<label><textarea name=title style='width:70%; height:20px;' readonly>"."$title"."</textarea></label>
	</div>
	<div class='row' style='margin:4px'>
	<label style='display:inline-block; width:90px;font-weight:bold; font-size:18px;'> [출처] </label>
	<input type=text name=publisher value='$publisher' readonly>
	<label> - </label>
	<input type=text name=name      value='$name'>
	<label> - </label>
	<input type=text name=date      value='$date'>
	<input type=text name=time      value='$time'>
	</div>
	<div class='row' style='margin:4px'>
	<label style='display:inline-block; width:90px;font-weight:bold; font-size:18px;'> [키워드] </label>
	<input type=text name=keyword style='width:300px'  value='$keyword'>
	</div>
	<div class='row' style='margin:4px'>
	<label style='display:inline-block; width:90px;font-weight:bold; font-size:18px;'> [내용] </label>
	</div>
	<div class='row' style='margin:2px; width: 80%;height: 350px;'>
	<textarea name=content style='width:100%; height:100%; font-size:15px;'>"."$content"."</textarea>
	</div>	";


	// 시그널뉴스 연결 종목 구하기
	echo "
	<div class='row' style='margin:4px; padding:4px;'>";	

	$checkedN = $checkedP = $checkedC = '';
	$i=1;
	if($signal_fg != '')	{
		
		$qry2  = " SELECT A.name
					 	, B.link_fg
					 FROM stock A
					INNER JOIN signal_link B
					   ON B.code = A.code
					  AND B.news_fg = 'S'
					  AND B.signal_id = '$signal_id'
					GROUP BY A.name
						, B.link_fg";

		$result = $mysqli->query($qry2);
		echo "$qry2";
		$checkedN = "checked";
		while( $row = $result->fetch_array(MYSQLI_BOTH) ){
			echo "<input type=text name='stock".$i."' style='width:200px' value='".$row['name']."'>";

			if($row['link_fg']== 'P') {
				$checkedP = "checked";
				$checkedC = "";
			} else {
				$checkedP = "";
				$checkedC = "checked";
			}
			$i++;
		}
	} 

	for($j=$i;$j<=15; $j++){
		echo "<input type=text name='stock".$j."' style='width:200px'>";
	}

	echo "</div>
	<div class='row' style='margin:4px; padding:4px;'>
		<input type=checkbox name='signal_fg'  value='Y' $checkedN> 시그널뉴스 (시그널리포트작성)
		<input type=radio name=link_fg value='P' $checkedP>P
		<input type=radio name=link_fg value='C' $checkedC>C
	</div>
	<br>";

	// 픽뉴스 연결 종목 구하기
	echo "
	<div class='row' style='margin:4px; padding:4px;'>";
	
	$checkedN = $checkedP = $checkedC = '';
	$i=1;
	if($pick_fg != '')	{
		$qry2  = " SELECT A.name
					 	, B.link_fg
					 FROM stock A
					INNER JOIN signal_link B
					   ON B.code = A.code
					  AND B.news_fg = 'P'
					  AND B.signal_id = '$signal_id'
					GROUP BY A.name
						, B.link_fg";

		$result = $mysqli->query($qry2);
		// echo "$qry2";
		$checkedN = "checked";
		while( $row = $result->fetch_array(MYSQLI_BOTH) ){
			echo "<input type=text name='p_stock".$i."' style='width:200px' value='".$row['name']."'>";

			if($row['link_fg']== 'P') {
				$checkedP = "checked";
				$checkedC = "";
			} else {
				$checkedP = "";
				$checkedC = "checked";
			}
			$i++;
		}
	} 

	for($j=$i;$j<=15; $j++){
		echo "<input type=text name='p_stock".$j."' style='width:200px'>";
	}

	echo "</div>
	<div class='row' style='margin:4px; padding:4px;'>
		<input type=checkbox name='pick_fg'    value='Y' $checkedN> 픽뉴스
		<input type=radio name=p_link_fg value='P' $checkedP>P
		<input type=radio name=p_link_fg value='C' $checkedC>C
	</div>
	<br>";

	// 관련뉴스 연결 종목 구하기
	echo "
	<div class='row' style='margin:4px; padding:4px;'>";
	
	$checkedN = $checkedP = $checkedC = '';
	$i=1;
	if($rapport_fg != '')	{
		$qry2  = " SELECT A.name
					 	, B.link_fg
					 FROM stock A
					INNER JOIN signal_link B
					   ON B.code = A.code
					  AND B.news_fg = 'R'
					  AND B.signal_id = '$signal_id'
					GROUP BY A.name
						, B.link_fg";

		$result = $mysqli->query($qry2);
		// echo "$qry2";
		$checkedN = "checked";
		while( $row = $result->fetch_array(MYSQLI_BOTH) ){
			echo "<input type=text name='r_stock".$i."' style='width:200px' value='".$row['name']."'>";

			if($row['link_fg']== 'P') {
				$checkedP = "checked";
				$checkedC = "";
			} else {
				$checkedP = "";
				$checkedC = "checked";
			}
			$i++;
		}
	} 

	for($j=$i;$j<=15; $j++){
		echo "<input type=text name='r_stock".$j."' style='width:200px'>";
	}

	echo "</div>
	<div class='row' style='margin:4px; padding:4px;'>
		<input type=checkbox name='rapport_fg' value='Y' $checkedN> 관련뉴스
		<input type=radio name=r_link_fg value='P' $checkedP>P
		<input type=radio name=r_link_fg value='C' $checkedC>C
	</div>
	<br>";

	echo "
	<div class='row' style='margin:4px;'>
	<input type=button style='font:red;' value='저 장' onclick='updateNews()'>
	</div>
	";

	$query = " SELECT cd, nm
				 FROM comm_cd
				WHERE l_cd = 'SG00'";
	$result = $mysqli->query($query);
	$cd = array();
	$nm = array();

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$cd[] = $row['cd'];
		$nm[] = $row['nm'];
	}

	echo "<select name='signal_group'><option value=''>--choose an option--</option>";
	$option = "";
	for($i=0; $i<count($cd); $i++) {
		if($signal_group == $cd[$i]) {
			$option .= "<option value='".$cd[$i]."' selected>".$nm[$i]."</option>";
		} else {
			$option .= "<option value='".$cd[$i]."'>".$nm[$i]."</option>";
		}
	}
	echo $option;
	echo "</select>";

	echo "<input type=text name=link_date    style='width:100px' value='$link_date' readonly>";
	echo "<input type=text name=link         style='width:500px' value='$link' readonly>";
	}
?>
</form>
</body>
<script>
	function updateNews() {
		form = document.forms[0];

		if(form.keyword.value == '') {
			result = confirm('키워드가 입력되지 않았습니다. 계속 진행하시겠습니까?')

			if(!result) return;
		}

		if(form.signal_fg.checked  == false && 
		   form.pick_fg.checked	   == false &&
		   form.rapport_fg.checked == false  	) {
			alert('뉴스구분을 선택해 주세요.');
			return;
		}

		if(form.signal_fg.checked  == true && form.stock1.value == '') {
			alert('종목을 입력해주세요');
			form.stock1.focus();
			return;
		}

		if(form.pick_fg.checked  == true && form.p_stock1.value == '') {
			alert('종목을 입력해주세요');
			form.p_stock1.focus();
			return;
		}

		if(form.rapport_fg.checked  == true && form.r_stock1.value == '') {
			alert('종목을 입력해주세요');
			form.r_stock1.focus();
			return;
		}
		
		form.submit();
	}

</script>
<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
