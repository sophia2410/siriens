<?php
header("Content-type: application/json; charset=utf-8");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// 쿼리결과 작업용 변수
$list = array(); 
$arr  = array(); 
$p_code="";
$r=$s=0;

if(isset($_GET[0])){
  foreach($_GET as $key=>$val){
    if(is_array($val)){
      $gv  = $val['name'];
      $$gv = $val['value'];
    }
  }

  $query = " SELECT STR_TO_DATE(a.link_date, '%Y%m%d') link_date
                  , d.code, d.name
			            , CONCAT(FORMAT(b.close_rate, 2), '%') rate
                  , ROUND(b.volume/1000,0) volume
                  , e.keyword
               FROM signal_link a
              INNER JOIN daily_price b 
                 ON b.code = a.code 
                AND b.date = a.link_date
              INNER JOIN stock d 
                 ON d.code = a.code
              INNER JOIN signals_web e 
                 ON e.signal_id = a.signal_id
              WHERE EXISTS (SELECT 1 
                              FROM signal_link c
                             WHERE c.link_fg = 'P' 
                               AND c.code = '".$stock."'
                               AND c.signal_id = a.signal_id)
                AND a.code <> '".$stock."'
                AND d.last_yn = 'Y'
              ORDER BY name, link_date DESC
           " ;

/* Select queries return a resultset */
$result = $mysqli->query($query);
//printf("Select returned %d rows.\n", $result->num_rows);

while( $row = $result->fetch_array(MYSQLI_BOTH) ){
  // 하나의 배열 완성
  if($row['code'] != $p_code)
  { 
    if($p_code != ''){
      $list[] = $arr;
      $arr = array(); 
      $r=0;
    }
    
	$arr['title']     = $row['name'].' ('.$row['rate'].')('.$row['volume'].'K)';
	$arr['link_date'] = $row['link_date'];
	$arr['keyword']   = $row['keyword'];
  } else {
      $arr['_children'][$r]['title']     = $row['name'].' ('.$row['rate'].')('.$row['volume'].'K)';
      $arr['_children'][$r]['link_date'] = $row['link_date'];
	    $arr['_children'][$r]['keyword']   = $row['keyword'];
      $r++;
  }

  $p_code = $row['code'];

//   while( $row = $result->fetch_array(MYSQLI_BOTH) ){
//     $list[] = $row;
//     print($row['name']);
}
$list[] = $arr;
} 
//printf(json_encode($list, JSON_UNESCAPED_UNICODE));
?>{
    "result": true,
    "data": {
      "contents": <?= json_encode($list, JSON_UNESCAPED_UNICODE) ?>
    }
  }