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

		// 종목 키워드
		$stock_keyword = "";
		$query = "SELECT CONCAT('- ',keyword,'<br>') keyword
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
		$query = "SELECT CONCAT(CASE WHEN LENGTH(comment) > 30 THEN '<br>' ELSE '' END,'- ',comment,' ') comment 
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


		echo "Stock Code:: "."$code";
		echo "<br>";
		echo "<br>";
		echo "$stock_keyword";
		echo "$stock_comment";
		echo "<br>";
		echo "---";
		echo "<br>";

		//재료
        $query = "SELECT 'NEWS' seq
						, STR_TO_DATE(date, '%Y%m%d') date
						, REPLACE(REPLACE(title, '[',''),']','_') title
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
		
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			echo "<br>";
			echo "(".$row['date'].") ";
			echo "[".$row['title']."](".$row['link'].")";
			echo "<br>";
			echo "<pre>".$row['content']."</pre>";

			// echo "<a href=\"javascript:popupNews('".$row['link']."')\" target=\"_blank\">".$row['title']."</a>";
			// echo $row['content'];
			echo "<br>";
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