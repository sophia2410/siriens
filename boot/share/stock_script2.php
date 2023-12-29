<?php
header("Content-type: application/json; charset=utf-8");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// 쿼리결과 작업용 변수
$list      = array(); 
$arr_date  = array(); 
$arr_close = array(); 
$arr_rate  = array(); 
$arr_volume= array(); 

if(isset($_GET[0])){
  foreach($_GET as $key=>$val){
    if(is_array($val)){
      $gv  = $val['name'];
      $$gv = $val['value'];
    }
  }

  $query = " select concat('D',lpad(@rownum,2,'0')) rnum
                  , a.date
                  , format(close, 0) close
                  , CONCAT(FORMAT(close_rate, 2), '%') rate
                  , FORMAT(ROUND(volume/1000,0),0) volume
                  , @rownum:=@rownum+1
                from daily_price a 
                  , (select DATE_FORMAT(date,'%Y%m%d') date from calendar where date <= now() order by date desc limit 15) b
                  , (select @rownum:=1) c
                where a.code = '".$stock."'
                and b.date = a.date
                order by date
           " ;

$result = $mysqli->query($query);

$arr_rate['subject']  =  '등락률';
$arr_volume['subject']=  '거래량(K)';
$arr_close['subject'] =  '종가';
$arr_date['subject']  =  '일자';

while( $row = $result->fetch_array(MYSQLI_BOTH) ){    
  $arr_rate[$row['rnum']]  =  $row['rate'];
  $arr_volume[$row['rnum']]=  $row['volume'];
  $arr_close[$row['rnum']] =  $row['close'];
	$arr_date[$row['rnum']]  =  $row['date'];
}
$list[] = $arr_rate;
$list[] = $arr_volume;
$list[] = $arr_close;
$list[] = $arr_date;
} 
//printf(json_encode($list, JSON_UNESCAPED_UNICODE));
?>{
    "result": true,
    "data": {
      "contents": <?= json_encode($list, JSON_UNESCAPED_UNICODE) ?>
    }
  }