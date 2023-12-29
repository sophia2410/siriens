<?php
header("Content-type: application/json; charset=utf-8");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// 쿼리결과 작업용 변수
$list = array(); 
$arr  = array(); 
$color = array('blue');
$p_grp ="";
$p_idx = 0;
$p_code="";
$r=$s=0;

if(isset($_GET[0])){
  foreach($_GET as $key=>$val){
    if(is_array($val)){
      $gv  = $val['name'];
      $$gv = $val['value'];
    }
  }

  $query = "SELECT a.fg
                 , a.code
                 , a.name
                 , a.rate
                 , a.volume
                 , a.signal_id
                 , a.title
                 , a.publisher
                 , a.writer
                 , a.content
                 , a.link
                 , a.keyword
                 , a.rate_1
                 , a.grp
                 , a.srt
              FROM (
                      SELECT a.*
                              /*tree에서 하나로 묶기 위한 그룹코드 만들기*/
                            , CASE WHEN fg='P' then @signal_id:=signal_id
                                WHEN (fg='C' AND @code=code) then @signal_id := @signal_id
                                ELSE  @signal_id := signal_id end grp
                              /*리포트 정렬을 위한 그룹 대표 상승률 구하기*/
                            , CASE WHEN @srt = '' then @srt :=rate_1
                                WHEN  @code = code then @srt :=@srt
                                ELSE  @srt :=rate_1 end srt
                            , @rate_1 := rate_1, @fg:=fg, @code:=code
                        FROM (
                                SELECT 'C' fg, b.code, b.name, c.close_rate rate, format(round(c.volume/1000,0),0) volume, d.signal_id
                                     ,  CONCAT('(',STR_TO_DATE(d.date, '%Y%m%d'),') ',d.title) title
                                     ,  d.publisher, d.writer, d.cONtent, d.link, d.keyword, c.close_rate rate_1
                                  FROM  20230117_signal_link a
                                 INNER  JOIN stock b
                                    ON  b.code = a.code
                                   AND  b.last_yn = 'Y'
                                 INNER  JOIN daily_price c
                                    ON  c.date = a.link_date
                                   AND  c.code = a.code
                                  INNER  JOIN 20230120_signals_web d
                                     ON  d.signal_id = a.signal_id
                                  WHERE  b.last_yn='Y'
                                    AND  a.link_date = '$date'
                                    AND  a.link_fg='C'
                                   UNION ALL
                                  SELECT 'P' fg, b.code, b.name, c.close_rate rate, format(round(c.volume/1000,0),0) volume, d.signal_id                                 
                                       ,  CONCAT('(',STR_TO_DATE(d.date, '%Y%m%d'),') ',d.title) title
                                       ,  d.publisher, d.writer, d.cONtent, d.link, d.keyword, e.rate rate_1
                                    FROM  20230117_signal_link a
                                   INNER  JOIN stock b
                                      ON  b.code = a.code
                                     AND  b.last_yn = 'Y'
                                   INNER  JOIN daily_price c
                                      ON  c.date = a.link_date
                                     AND  c.code = a.code
                                   INNER  JOIN 20230120_signals_web d
                                      ON  d.signal_id = a.signal_id
                                   INNER  JOIN (
                                                SELECT a.signal_id
                                                    , max(b.close_rate) rate
                                                  FROM 20230117_signal_link a
                                                  JOIN daily_price b
                                                    ON a.code = b.code
                                                  AND b.date = a.link_date
                                                WHERE a.link_date = '$date'
                                                  AND a.link_fg = 'P'
                                                GROUP BY a.signal_id
                                              ) e
                                      ON  e.signal_id = a.signal_id
                                   WHERE  b.last_yn   = 'Y'
                                     AND  a.link_date = '$date'
                                     AND  a.link_fg   = 'P'
                                ) a
                       CROSS JOIN (SELECT @srt := '', @grp := 0, @rate_1 := '', @fg:=0, @code:='', @signal_id:='') B
                       ORDER BY code, fg desc
                    ) a
                ORDER BY srt*1 desc, rate desc, code, fg desc
            " ;


          //  select signal_id, code, rate
          //  FROM (
          //          SELECT A.*
          //               , CASE @signal_id WHEN signal_id THEN
          //                      IF(@rate > rate, @RANK := @RANK + 1, @RANK)
          //                 ELSE
          //                      @RANK := 1
          //                 END AS rnk
          //               , @signal_id := signal_id
          //               , @code := code
          //               , @rate := rate
          //          FROM (
          //                 SELECT a.signal_id
          //                      , a.code
          //                      , b.close_rate rate
          //                   FROM 20230117_signal_link a
          //                   JOIN daily_price b
          //                     ON a.code = b.code
          //                    and b.date = a.link_date
          //                  where a.link_date = ".$date."
          //                    and a.link_fg = 'P'
          //                ) A
          //          CROSS JOIN (SELECT @RANK := 0, @signal_id:=0, @code:='', @rate := 0) B
          //          ORDER BY signal_id, code, rate DESC
          //        ) a
          // where rnk= '1'

file_put_contents("a.txt",$query); // 값 파일로 찍어보기

$result = $mysqli->query($query);

while( $row = $result->fetch_array(MYSQLI_BOTH) ){

  $content = str_replace('"', '', $row['content']);

  // 하나의 배열 완성
  if($row['grp'] != $p_grp) { // 그룹코드가 달라지면..
  
    if($p_grp != ''){ // roof 시작
      $list[] = $arr;
      $arr = array(); 
      $r=$s=0;
    }

    if($row['fg'] == 'P'){ // 부모기사
      if($p_grp != $row['grp']){
        $arr['title']     = $row['title'];
        $arr['publisher'] = $row['publisher'];
        $arr['writer']    = $row['writer'];
        $arr['keyword']   = $row['keyword'];
        $arr['content']   = $content;
        $arr['link']      = $row['link'];
        $arr['_attributes']['expanded'] = "true";
      }
      $arr['_children'][$r]['title'] = $row['name'].' ('.$row['rate'].'%)('.$row['volume'].'K)';
      $arr['_children'][$r]['_attributes']['expanded'] = "true";

      $p_idx = 1;
    } else if($row['fg'] == 'C'){ // 단독기사
      $arr['title'] =  $row['name'].' ('.$row['rate'].'%)('.$row['volume'].'K)';
      $arr['_attributes']['expanded']    = "true";
      $arr['_children'][$r]['title']     = $row['title'];
      $arr['_children'][$r]['publisher'] = $row['publisher'];
      $arr['_children'][$r]['writer']    = $row['writer'];
      $arr['_children'][$r]['keyword']   = $row['keyword'];
      $arr['_children'][$r]['content']   = $content;
      $arr['_children'][$r]['link']      = $row['link'];
      $arr['_children'][$r]['_attributes']['expanded'] = "true";

      $p_idx = 2;
    }
  } else {
    if($row['fg'] == 'P'){
      $r++;$s=0;
      $arr['_children'][$r]['title'] = $row['name'].' ('.$row['rate'].'%)('.$row['volume'].'K)';
      $arr['_children'][$r]['_attributes']['expanded'] = "true";

      $p_idx = 3;
    } else if($row['fg'] == 'C'){

      if($p_idx != 2) {
        if($p_code != $row['code']) $r++;
        $arr['_children'][$r]['_children'][$s]['title']     = $row['title'];
        $arr['_children'][$r]['_children'][$s]['publisher'] = $row['publisher'];
        $arr['_children'][$r]['_children'][$s]['writer']    = $row['writer'];
        $arr['_children'][$r]['_children'][$s]['keyword']   = $row['keyword'];
        $arr['_children'][$r]['_children'][$s]['content']   = $content;
        $arr['_children'][$r]['_children'][$s]['link']      = $row['link'];
      } else {
        $r++;
        $arr['_children'][$r]['title']     = $row['title'];
        $arr['_children'][$r]['publisher'] = $row['publisher'];
        $arr['_children'][$r]['writer']    = $row['writer'];
        $arr['_children'][$r]['keyword']   = $row['keyword'];
        $arr['_children'][$r]['content']   = $content;
        $arr['_children'][$r]['link']      = $row['link'];
        $arr['_children'][$r]['_attributes']['expanded'] = "true";
      }

      $p_idx = 4;
      $s++;
    }
  }
  
  $p_grp = $row['grp'];
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