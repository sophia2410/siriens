<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
</head>

<body>
<?php
// post 방식으로 넘어온 변수 확인
foreach($_POST as $key=>$val){
	echo "$key =>  $val \n";
}

if(isset($_POST['page_id'])) {
	
	if($_POST['page_id'] == 'keyword') {					// keyword.php
		$keyword_cnt = $_POST['keyword_cnt'];
		$proc_fg = $_POST['proc_fg'];

		if($proc_fg == 'save') {
			for($i=0; $i<$keyword_cnt; $i++){
				$keyword_cd   = 'keyword_cd'.$i;
				$keyword_nm   = 'keyword_nm_a'.$i;
				$keyword_nm2  = 'keyword_nm_b'.$i;
				$keyword_nm3  = 'keyword_nm_c'.$i;
				$keyword_fg   = 'keyword_fg'.$i;
		
				$qry = "UPDATE theme_keyword
						SET   keyword_nm  = '$_POST[$keyword_nm]'
							, keyword_nm2 = '$_POST[$keyword_nm2]'
							, keyword_nm3 = '$_POST[$keyword_nm3]'
							, keyword_fg  = SUBSTR('$_POST[$keyword_fg]',3,1)
							, sort_no     = SUBSTR('$_POST[$keyword_fg]',1,1)
						WHERE keyword_cd = '$_POST[$keyword_cd]'";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}
			
			if($_POST['keywordNew'] != '') {
				$keyword_val  = $_POST['keywordNew'];
				$keyword_val2 = $_POST['keywordNew2'];
				$keyword_val3 = $_POST['keywordNew3'];
				$keywordFg_val= substr($_POST['keywordFgNew'],2,1);
				$sortNo_val= substr($_POST['keywordFgNew'],0,1);

				$qry = "INSERT INTO theme_keyword (keyword_cd, keyword_nm, keyword_nm2, keyword_nm3, keyword_fg, sort_no, create_dtime) 
						SELECT lpad(MAX(keyword_cd)*1+1, 6,'0'), '$keyword_val', '$keyword_val2', '$keyword_val3', '$keywordFg_val', '$sortNo_val', now()
						  FROM theme_keyword";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}
		} else if($proc_fg == 'del') {
			for($i=0; $i<$keyword_cnt; $i++){
				$k_chk  = 'k_chk'.$i;
				$keyword_cd   = 'keyword_cd'.$i;
		
				if(isset($_POST[$k_chk])) {
					$qry = "DELETE FROM theme_keyword
							WHERE keyword_cd = '$_POST[$keyword_cd]'";
					echo $qry."<br><br>";
					$mysqli->query($qry);
				}
			}
		}

	} else if($_POST['page_id'] == 'stock_common') {		// stock_comment.php
		$code = $_POST['code'];
		$keyword_cnt = $_POST['keyword_cnt'];
		$comment_cnt = $_POST['comment_cnt'];
		$proc_fg = $_POST['proc_fg'];
		
		if($proc_fg == 'save') {
			for($i=0; $i<$keyword_cnt; $i++){
				$k_id     = 'k_id'.$i;
				$keyword  = 'keyword'.$i;
				$k_remark = 'k_remark'.$i;

				$keyword_val = str_replace("'", "\'", $_POST[$keyword]);
				$keyword_val = str_replace('"', '\"', $keyword_val);
		
				$qry = "UPDATE stock_keyword
						SET keyword  = '$keyword_val'
							, remark = '$_POST[$k_remark]'
						WHERE id = '$_POST[$k_id]'";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}

			if($_POST['keywordNew'] != '') {
				$keyword_val = str_replace("'", "\'", $_POST['keywordNew']);
				$keyword_val = str_replace('"', '\"', $keyword_val);

				$qry = "INSERT INTO stock_keyword (code, keyword, remark, create_dtime, last_dtime) values('$code', '$keyword_val', '".$_POST['k_remarkNew']."', now(), now())";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}

			for($i=0; $i<$comment_cnt; $i++){
				$c_id     = 'c_id'.$i;
				$comment  = 'comment'.$i;
				$c_remark = 'c_remark'.$i;

				$comment_val = str_replace("'", "\'", $_POST[$comment]);
				$comment_val = str_replace('"', '\"', $comment_val);
		
				$qry = "UPDATE stock_comment
						SET comment = '$comment_val'
							, remark = '$_POST[$c_remark]'
						WHERE id = '$_POST[$c_id]'";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}

			if($_POST['commentNew'] != '') {
				$comment_val = str_replace("'", "\'", $_POST['commentNew']);
				$comment_val = str_replace('"', '\"', $comment_val);

				$qry = "INSERT INTO stock_comment (code, comment, remark, create_dtime, last_dtime) values('$code', '$comment_val', '".$_POST['c_remarkNew']."', now(), now())";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}

			if($_POST['lowprice1'] != '') {
				$qry = "REPLACE INTO stock_kswingport (code, range_fg, low_price, high_price, create_dtime) values('$code', 'range1', ".$_POST['lowprice1'].", ".$_POST['highprice1'].", now())";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			} else {
				$qry = "DELETE FROM stock_kswingport WHERE range_fg = 'range1'";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}

			if($_POST['lowprice2'] != '') {
				$qry = "REPLACE INTO stock_kswingport (code, range_fg, low_price, high_price, create_dtime) values('$code', 'range2', ".$_POST['lowprice2'].", ".$_POST['highprice2'].", now())";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}else {
				$qry = "DELETE FROM stock_kswingport WHERE range_fg = 'range2'";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}
		} else if($proc_fg == 'del') {
			for($i=0; $i<$keyword_cnt; $i++){
				$k_id   = 'k_id'.$i;
				$k_chk  = 'k_chk'.$i;
		
				if(isset($_POST[$k_chk])) {
					$qry = "DELETE FROM stock_keyword
							WHERE id = '$_POST[$k_id]'";
					echo $qry."<br><br>";
					$mysqli->query($qry);
				}
			}

			for($i=0; $i<$comment_cnt; $i++){
				$c_id   = 'c_id'.$i;
				$c_chk  = 'c_chk'.$i;
		
				if(isset($_POST[$c_chk])) {
					$qry = "DELETE FROM stock_comment
							WHERE id = '$_POST[$c_id]'";
					echo $qry."<br><br>";
					$mysqli->query($qry);
				}
			}

		}
	}
	echo "<input type=hidden id=pageId name=pageId value='".$_POST['page_id']."'>";
}
echo "<input type=hidden id=pageId name=pageId value=''>";

$mysqli->close();
?>
</body>
<script>
if(document.getElementById('pageId').value != '') {
	parent.location.reload();
}
</script>
</html>