<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
?>
</head>
<body>
<form method="post" action="news_script.php">
<?php
	// 변수초기화
	$publisher = "";
	$title     = "";
	$name      = "";
	$date      = "";
	$time      = "";
	$content   = "";
	
	$link_date = "";
	$link      = "";

	// python 호출 성공 버전
	// exec('C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe test.py 2>&1', $arr);
	// exec('C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe E:\Project\202410\python\DBUpdater_News_1page.py $link', $arr);
	// $result = implode("\n", $arr)."\n";
	// echo "Result=".$result;
	if(isset($_GET['link'])){ 
		$link_date    = $_GET['link_date'];
		$link = str_replace('^amp^', '&', $_GET['link']);

		// 크롤링 파이썬 파일 실행
		exec('C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe z_DBUp_News.py '.$link, $arr);

		// 반환된 크롤링 결과 확인하기
		foreach($arr as $key=>$val){
			echo "$key =>  ".iconv("EUC-KR", "UTF-8",$val)."\n"; 
		}
		
		$publisher = iconv("EUC-KR", "UTF-8",$arr[0]);
		$title     = iconv("EUC-KR", "UTF-8",$arr[1]);
		$name      = iconv("EUC-KR", "UTF-8",$arr[2]);
		$date      = $arr[3];
		$time      = iconv("EUC-KR", "UTF-8",$arr[4]);

		//본문 가져오기
		for($i=5; $i<count($arr); $i++) {
			if($arr[$i] != '' && $content != ''){
				$content .= "\n\n";
			}
			$content .= iconv("EUC-KR", "UTF-8",$arr[$i]);
		}
	}

	echo "
	<br>
	<div class='container'>
	<div class='row' style='margin:4px'>
	<label style='display:inline-block; width:90px;font-weight:bold; font-size:18px;'> [제목] </label>
	<label><textarea name=title style='width:70%; height:20px;'>"."$title"."</textarea></label>
	</div>
	<div class='row' style='margin:4px'>
	<label style='display:inline-block; width:90px;font-weight:bold; font-size:18px;'> [출처] </label>
	<input type=text name=publisher value="."$publisher".">
	<label> - </label>
	<input type=text name=name      value="."$name".">
	<label> - </label>
	<input type=text name=date      value="."$date".">
	<input type=text name=time      value="."$time".">
	</div>
	<div class='row' style='margin:4px'>
	<label style='display:inline-block; width:90px;font-weight:bold; font-size:18px;'> [키워드] </label>
	<input type=text name=keyword style='width:300px'>
	</div>
	<div class='row' style='margin:4px'>
	<label style='display:inline-block; width:90px;font-weight:bold; font-size:18px;'> [내용] </label>
	</div>
	<div class='row' style='margin:2px; width: 80%;height: 400px;'>
	<textarea name=content style='width:100%; height:100%; font-size:15px;'>"."$content"."</textarea>
	</div>
	<!--시그널리포트 저장 //-->
	<div class='row' style='margin:4px; padding:4px;'>
		<div class='col-8' style='margin:4px; padding:4px;'>
		<input type=checkbox name='signal_fg'  value='Y'> 시그널뉴스 (시그널리포트작성)
		<input type=radio name=link_fg value='P'>P
		<input type=radio name=link_fg value='C' checked>C
		</div>
	</div>
	<div class='row' style='margin:4px'>
	<input type=text name='stock1' style='width:200px'>
	<input type=text name='stock2' style='width:200px'>
	<input type=text name='stock3' style='width:200px'>
	<input type=text name='stock4' style='width:200px'>
	<input type=text name='stock5' style='width:200px'>
	<br>
	<input type=text name='stock6' style='width:200px'>
	<input type=text name='stock7' style='width:200px'>
	<input type=text name='stock8' style='width:200px'>
	<input type=text name='stock9' style='width:200px'>
	<input type=text name='stock10' style='width:200px'>
	<br>
	<input type=text name='stock11' style='width:200px'>
	<input type=text name='stock12' style='width:200px'>
	<input type=text name='stock13' style='width:200px'>
	<input type=text name='stock14' style='width:200px'>
	<input type=text name='stock15' style='width:200px'>
	</div>
	<!--시그널리포트 저장 //-->
	<div class='row' style='margin:4px; padding:4px;'>
		<div class='col-8' style='margin:4px; padding:4px;'>
		<input type=checkbox name='pick_fg'    value='Y'> 픽뉴스
		<input type=radio name=p_link_fg value='P'>P
		<input type=radio name=p_link_fg value='C' checked>C
		</div>
	</div>
	<div class='row' style='margin:4px'>
	<input type=text name='p_stock1' style='width:200px'>
	<input type=text name='p_stock2' style='width:200px'>
	<input type=text name='p_stock3' style='width:200px'>
	<input type=text name='p_stock4' style='width:200px'>
	<input type=text name='p_stock5' style='width:200px'>
	<br>
	<input type=text name='p_stock6' style='width:200px'>
	<input type=text name='p_stock7' style='width:200px'>
	<input type=text name='p_stock8' style='width:200px'>
	<input type=text name='p_stock9' style='width:200px'>
	<input type=text name='p_stock10' style='width:200px'>
	<br>
	<input type=text name='p_stock11' style='width:200px'>
	<input type=text name='p_stock12' style='width:200px'>
	<input type=text name='p_stock13' style='width:200px'>
	<input type=text name='p_stock14' style='width:200px'>
	<input type=text name='p_stock15' style='width:200px'>
	</div>
	
	<!--시그널리포트 저장 //-->
	<div class='row' style='margin:4px; padding:4px;'>
		<div class='col-8' style='margin:4px; padding:4px;'>
		<input type=checkbox name='rapport_fg' value='Y'> 관련뉴스
		<input type=radio name='r_link_fg' value='P'>P
		<input type=radio name='r_link_fg' value='C' checked>C
		</div>
	</div>
	<div class='row' style='margin:4px'>
	<input type=text name='r_stock1' style='width:200px'>
	<input type=text name='r_stock2' style='width:200px'>
	<input type=text name='r_stock3' style='width:200px'>
	<input type=text name='r_stock4' style='width:200px'>
	<input type=text name='r_stock5' style='width:200px'>
	<br>
	<input type=text name='r_stock6' style='width:200px'>
	<input type=text name='r_stock7' style='width:200px'>
	<input type=text name='r_stock8' style='width:200px'>
	<input type=text name='r_stock9' style='width:200px'>
	<input type=text name='r_stock10' style='width:200px'>
	<br>
	<input type=text name='r_stock11' style='width:200px'>
	<input type=text name='r_stock12' style='width:200px'>
	<input type=text name='r_stock13' style='width:200px'>
	<input type=text name='r_stock14' style='width:200px'>
	<input type=text name='r_stock15' style='width:200px'>
	</div>
	<div class='col-4' style='margin:4px; padding:4px;'>
	<div class='row' style='margin:4px;'>
	<input type=button style='font:red;' value='저 장' onclick='saveNews()'>
	</div>
	</div>
	";

	echo "<input type=text name=link_date    style='width:100px' value='$link_date' readonly>";
	echo "<input type=text name=link         style='width:500px' value='$link' readonly>";

?>
</form>
</body>
<script>
	function saveNews() {
		form = document.forms[0];

		if(form.keyword.value == '') {
			result = confirm('키워드가 입력되지 않았습니다. 계속 진행하시겠습니까?')

			if(!result) return;
		}
		
		console.log(form.signal_fg.checked);
		console.log(form.pick_fg.checked);
		console.log(form.rapport_fg.checked);

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
</html>