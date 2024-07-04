<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");

$b = debug_backtrace();
foreach($b as $key=>$val){
	echo "$key =>  $val \n";
}
?>
</head>

<body>
<?php
// post 방식으로 넘어온 변수 확인
foreach($_POST as $key=>$val){
	echo "$key =>  $val \n";
}

if(isset($_POST['proc_fg'])) {
	if($_POST['proc_fg'] == 'CS') {// 코멘트 저장

		$tot_cnt = $_POST['tot_cnt'];
		$comment_date = $_POST['today'];

		// 종목수만큼 돌면서 종목 코멘트 등록
		for($i=0; $i<$tot_cnt; $i++){
			$code = 'code'.$i;
			$name = 'name'.$i;
			$comment = 'comment'.$i;
			$pick    = 'pick_yn'.$i;

			$pick_yn = (isset($_POST[$pick])) ? $_POST[$pick] : "N";

			// 변경이나 삭제 반영을 위해 우선 지우고 다시 등록 처리
			$qry = "DELETE FROM kiwoom_xtay_tick_comments WHERE comment_date = '$comment_date' AND code = '{$_POST[$code]}'";
			echo $qry."<br><br>";
			$mysqli->query($qry);

			// 등록 정보가 있는 경우만 저장
			if($pick_yn == 'Y' || $_POST[$comment] != '') {
				$qry = "INSERT INTO kiwoom_xtay_tick_comments (code, name, pick_yn, comment, comment_date) VALUES ('{$_POST[$code]}', '{$_POST[$name]}', '{$pick_yn}', '{$_POST[$comment]}', '{$comment_date}')";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}

		}
	}
}
$mysqli->close();
?>
</body>
</html>