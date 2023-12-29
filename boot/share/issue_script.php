<?php
header("Content-type: application/json; charset=utf-8");

/* db.php 클래스 버전 - 유튜브 강좌에서 알려줌
    header("Content-type: application/json; charset=utf-8");
    
    include 'common/db/db.php';

    $limit = 100;
    $columns = "TRADE_DATE, STOCK_CD, STOCK_NM, RATE, TRADE_AMT, TRADE_ISSUE";

    $list = $db->get("D_ISSUE", $limit, $columns);
    //print_r($list);
    //echo json_encode($list, JSON_UNESCAPED_UNICODE);
*/

// mysqli 모듈 사용
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

/* mysqli Procedural style    

$sql = "SELECT TRADE_DATE, STOCK_CD, STOCK_NM, RATE, TRADE_AMT, TRADE_ISSUE
          FROM D_ISSUE
         WHERE STOCK_NM LIKE '%현우%'";

$result = mysqli_query($conn,$sql);

$list = array(); 
if(!empty($result) || $result == true ){
  while( $row = mysqli_fetch_array( $result ) ){
    $list[] = $row;
  };

  //데이터 사용을 종료
  mysqli_free_result($result);
  //mysql커넥트 종료
  mysqli_close($conn);
} else {
  echo ">>> Query Error!!!!";
}
*/

/* mysqli Object-oriented style */

//$arr = join("//", $_GET);

if(isset($_GET[0])){
  foreach($_GET as $key=>$val){
    if(is_array($val)){
      $gv  = $val['name'];
      $$gv = $val['value'];
    }
  }

  // 조회조건에 따른 쿼리문 구성
  $where = "";

  // 종목코드/명이 입력된 경우
  if(trim($stock) != '') {
    $where .= " AND code IN (SELECT code 
                              FROM stock 
                             WHERE (replace(name, ' ', '') LIKE '".preg_replace("/\s+/", "", $stock)."%')
                                or code = '".$stock."')" ;
  }

  // 일자가 입력된 경우
  if(isset($date)){ //종목검색 화면에서 넘어온 경우는 이슈항목이 없음.
    if(trim($date) != '') {
      $where .= " AND date = '".$date."' ";
    }
  }
  // 이슈가 입력된 경우
  if(isset($issue)){ //종목검색 화면에서 넘어온 경우는 이슈항목이 없음.
    if(trim($issue) != '') {
      // 이슈 ',' 로 구분되어 들어온 경우
      $arr = explode(",", $issue);
      foreach ($arr as &$value) {
        $where .= " AND issue LIKE '%".$value."%' ";
      }
    }
  }
  // 검색조건이 입력되지 않았을 경우 최근일 이슈 가져오기
  if($where=="") {
    $where .= " AND date = (SELECT MAX(date) date FROM daily_issue)";
  }

  $query = "SELECT STR_TO_DATE(date, '%Y%m%d') date
                 , code
                 , name
                 , CONCAT(FORMAT(rate, 2), '%') rate
                 , FORMAT(amount, '#,0') amount
                 , issue
              FROM daily_issue
             WHERE market_fg = '0'";
  $query .= $where ;
  $query .= "ORDER BY date DESC, rate*1 DESC " ;
} else {
  $query = "SELECT STR_TO_DATE(date, '%Y%m%d') date
                 , code
                 , name
                 , CONCAT(FORMAT(rate, 2), '%') rate
                 , FORMAT(amount, '#,0') amount
                 , issue
              FROM daily_issue
             WHERE market_fg = '0'
               AND date = (SELECT MAX(date) date FROM daily_issue)
             ORDER BY date DESC, rate*1 DESC
            ";
}

file_put_contents("a.txt",$query); // 값 파일로 찍어보기

/* Select queries return a resultset */
$result = $mysqli->query($query);
//printf("Select returned %d rows.\n", $result->num_rows);

$list = array(); 
while( $row = $result->fetch_array(MYSQLI_BOTH) ){
  $list[] = $row;
}
//echo json_encode($list, JSON_UNESCAPED_UNICODE);
?>{
    "result": true,
    "data": {
      "contents": <?= json_encode($list, JSON_UNESCAPED_UNICODE) ?>
    }
  }