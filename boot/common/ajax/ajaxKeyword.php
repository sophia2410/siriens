<?php
header("Content-type: application/json; charset=utf-8");

// mysqli 모듈 사용
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");


$subqry = "";
if(isset($_POST['fg'])) {
  if($_POST['fg'] == 'A') $subqry = "";
  else $subqry = "WHERE keyword_fg = '".$_POST['fg']."'";
}

$query = "SELECT keyword_cd, keyword_nm, keyword_fg
          FROM theme_keyword A  $subqry
          ORDER BY A.keyword_fg, A.keyword_nm" ;
  
// file_put_contents("a.txt",$query); // 값 파일로 찍어보기

/* Select queries return a resultset */
$result = $mysqli->query($query);

$list = array(); 
while( $row = $result->fetch_array(MYSQLI_BOTH) ){
  $list[] = $row;
}
// echo json_encode($list, JSON_UNESCAPED_UNICODE);
?>
<?= json_encode($list, JSON_UNESCAPED_UNICODE) ?>