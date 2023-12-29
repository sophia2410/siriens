<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");
?>
</head>

<body>
<?php
// post 방식으로 넘어온 변수 확인
foreach($_POST as $key=>$val){
	echo "$key =>  $val \n";
}

// 뉴스정보
$title        = $_POST['title'];
$publisher    = $_POST['publisher'];
$name         = $_POST['name'];
$date         = $_POST['date'];
$time         = $_POST['time'];
$keyword      = $_POST['keyword'];
$content      = $_POST['content'];
$link_date    = $_POST['link_date'];
$link         = $_POST['link'];

// 인자값 중 signal_id 존재여부 확인
// 존재 할 경우 기존 뉴스 업데이트
// 미존재일 경우 크롤링 데이터 등록
if(isset($_POST['signal_id'])){
// signal_id 존재 = 기 등록된 뉴스 수정
	$signal_id = $_POST['signal_id'];

	$content = str_replace("'", "\'", $content);
	$content = str_replace('"', '\"', $content);
	$keyword = str_replace("'", "\'", $keyword);
	$keyword = str_replace('"', '\"', $keyword);

	$query = " UPDATE signals
				SET   date    = '$date'
					, time    = '$time'
					, writer  = '$name'
					, content = '$content'
					, keyword = '$keyword'
					, last_date = now()
				WHERE signal_id = $signal_id";

	echo $query."<br><br>";
	$mysqli->query($query);

	// 쿼리문 파일쓰기
	$txt = file_get_contents("query.txt");
	$txt .= date("Y-m-d H:i",time());
	$txt .= $query;
	file_put_contents("query.txt",$txt); // 값 파일로 찍어보기

} else {
// signal_id 미존재 = 크롤링(or 입력) 된 뉴스 등록

	// 뉴스 저장하기
	$title = str_replace("'", "\'", $title);
	$title = str_replace('"', '\"', $title);

	$content = str_replace("'", "\'", $content);
	$content = str_replace('"', '\"', $content);

	$query = " INSERT IGNORE INTO signals
				( date
				, time
				, title
				, content
				, publisher
				, writer
				, link
				, keyword
				, create_date
				, create_id)
				VALUES
				( '$date'
				, '$time'
				, '$title'
				, '$content'
				, '$publisher'
				, '$name'
				, '$link'
				, '$keyword'
				,  now()
				, 'web')";

	echo "* 뉴스 저장 <br>";
	echo $query."<br><br>";
	$mysqli->query($query);

	// 쿼리문 파일쓰기
	$txt = file_get_contents("query.txt");
	$txt .= date("Y-m-d H:i",time());
	$txt .= $query;
	file_put_contents("query.txt",$txt); // 값 파일로 찍어보기

	// 뉴스 id 구하기
	$qry1   = " SELECT signal_id FROM signals WHERE link = '$link'";

	echo "<br>* 뉴스 ID 구하기 <br>";
	echo $qry1."<br><br>";
	$result = $mysqli->query($qry1);

	// 쿼리문 파일쓰기
	$txt = file_get_contents("query.txt");
	$txt .= date("Y-m-d H:i",time());
	$txt .= $qry1;
	file_put_contents("query.txt",$txt); // 값 파일로 찍어보기

	$row = $result->fetch_array(MYSQLI_BOTH);

	$signal_id = $row['signal_id'];
}


// 뉴스 종목 저장

// 실제 상승으로 이어진 뉴스와 연결 종목 저장
if(isset($_POST['signal_fg'])){
	$signal_fg = $_POST['signal_fg'];
	$link_fg   = $_POST['link_fg'];

	$stock[]   = $_POST['stock1'];
	$stock[]   = $_POST['stock2'];
	$stock[]   = $_POST['stock3'];
	$stock[]   = $_POST['stock4'];
	$stock[]   = $_POST['stock5'];
	$stock[]   = $_POST['stock6'];
	$stock[]   = $_POST['stock7'];
	$stock[]   = $_POST['stock8'];
	$stock[]   = $_POST['stock9'];
	$stock[]   = $_POST['stock10'];
	$stock[]   = $_POST['stock11'];
	$stock[]   = $_POST['stock12'];
	$stock[]   = $_POST['stock13'];
	$stock[]   = $_POST['stock14'];
	$stock[]   = $_POST['stock15'];

	// 종목정보 배열생성 확인
	// foreach($stock as $key=>$val){
	// 	echo "$key =>  $val <br>";
	// }

	for($i=0; $i<count($stock); $i++) {
		if(trim($stock[$i]) != '') {
			$qry2 = "REPLACE INTO temp_signal_link
					( link_date
					, news_fg
					, signal_id
					, code
					, name
					, link_fg )
					VALUES
					( $link_date
					, 'S'
					, $signal_id
					, ''
					, '$stock[$i]'
					, '$link_fg') ";

			echo "<br>* 시그널뉴스 - 종목 연결 저장 (임시테이블) <br>";
			echo $qry2."<br><br>";
			$mysqli->query($qry2);

			// 쿼리문 파일쓰기
			$txt = file_get_contents("query.txt");
			$txt .= date("Y-m-d H:i",time());
			$txt .= $qry2;
			file_put_contents("query.txt",$txt); // 값 파일로 찍어보기
		}
	}
}

// 상승이 반영될 것 같은 뉴스와 연결종목 저장
if(isset($_POST['pick_fg'])){
	$pick_fg   = $_POST['pick_fg'];
	$p_link_fg = $_POST['p_link_fg'];

	$p_stock[]  = $_POST['p_stock1'];
	$p_stock[]  = $_POST['p_stock2'];
	$p_stock[]  = $_POST['p_stock3'];
	$p_stock[]  = $_POST['p_stock4'];
	$p_stock[]  = $_POST['p_stock5'];
	$p_stock[]  = $_POST['p_stock6'];
	$p_stock[]  = $_POST['p_stock7'];
	$p_stock[]  = $_POST['p_stock8'];
	$p_stock[]  = $_POST['p_stock9'];
	$p_stock[]  = $_POST['p_stock10'];
	$p_stock[]  = $_POST['p_stock11'];
	$p_stock[]  = $_POST['p_stock12'];
	$p_stock[]  = $_POST['p_stock13'];
	$p_stock[]  = $_POST['p_stock14'];
	$p_stock[]  = $_POST['p_stock15'];

	for($i=0; $i<count($p_stock); $i++) {
		if(trim($p_stock[$i]) != '') {
			$qry2 = "REPLACE INTO temp_signal_link
					( link_date
					, news_fg
					, signal_id
					, code
					, name
					, link_fg )
					VALUES
					( $link_date
					, 'P'
					, $signal_id
					, ''
					, '$p_stock[$i]'
					, '$p_link_fg') ";

			echo "<br>* 픽뉴스 - 종목 연결 저장 (임시테이블) <br>";
			echo $qry2."<br><br>";
			$mysqli->query($qry2);

			// 쿼리문 파일쓰기
			$txt = file_get_contents("query.txt");
			$txt .= date("Y-m-d H:i",time());
			$txt .= $qry2;
			file_put_contents("query.txt",$txt); // 값 파일로 찍어보기
		}
	}
}

// 관심뉴스와 연결종목 저장
if(isset($_POST['rapport_fg'])){
	$rapport_fg = $_POST['rapport_fg'];
	$r_link_fg = $_POST['r_link_fg'];

	$r_stock[]  = $_POST['r_stock1'];
	$r_stock[]  = $_POST['r_stock2'];
	$r_stock[]  = $_POST['r_stock3'];
	$r_stock[]  = $_POST['r_stock4'];
	$r_stock[]  = $_POST['r_stock5'];
	$r_stock[]  = $_POST['r_stock6'];
	$r_stock[]  = $_POST['r_stock7'];
	$r_stock[]  = $_POST['r_stock8'];
	$r_stock[]  = $_POST['r_stock9'];
	$r_stock[]  = $_POST['r_stock10'];
	$r_stock[]  = $_POST['r_stock11'];
	$r_stock[]  = $_POST['r_stock12'];
	$r_stock[]  = $_POST['r_stock13'];
	$r_stock[]  = $_POST['r_stock14'];
	$r_stock[]  = $_POST['r_stock15'];

	for($i=0; $i<count($r_stock); $i++) {
		if(trim($r_stock[$i]) != '') {
			$qry2 = "REPLACE INTO temp_signal_link
					( link_date
					, news_fg
					, signal_id
					, code
					, name
					, link_fg )
					VALUES
					( $link_date
					, 'R'
					, ''
					, $signal_id
					, '$r_stock[$i]'
					, '$r_link_fg') ";

			echo "<br>* 관련뉴스 - 종목 연결 저장 (임시테이블) <br>";
			echo $qry2."<br><br>";
			$mysqli->query($qry2);

			// 쿼리문 파일쓰기
			$txt = file_get_contents("query.txt");
			$txt .= date("Y-m-d H:i",time());
			$txt .= $qry2;
			file_put_contents("query.txt",$txt); // 값 파일로 찍어보기
		}
	}
}

// 종목코드 업데이트
$qry3 = "UPDATE temp_signal_link A
			SET code
			= (SELECT code
				FROM stock B
				WHERE B.name = A.name)
		  WHERE code = ''";

echo "<br>* 종목코드 업데이트 <br>";
echo $qry3."<br><br>";
$mysqli->query($qry3);

$mysqli->close();
?>
<a href="javascript:window.parent.location='http://localhost/boot/news/news.php?link_date=<?=$link_date?>';"> 등록페이지로</a>
</body>
</html>