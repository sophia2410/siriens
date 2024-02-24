<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>

<body id="page-top">
  <!-- Main Content -->
  <div id="content">

  <?php
  $query = "SELECT 'stock_comment' idx, B.code, B.name, '' report_date, '' thema, '' thema_detail, '' issue, A.comment, A.remark
              FROM stock_comment A
             RIGHT OUTER JOIN stock B
                ON B.code = A.code
             WHERE B.name like '%".$_GET['find_val']."%'
             UNION ALL
            SELECT 'siriens_evening' idx, C.code, D.name, MAX(C.report_date) report_date, C.thema, C.thema_detail, C.issue, C.stock_connect comment, 'siriens_evening' remark
              FROM siriens_evening C
             INNER JOIN stock D
                ON D.code = C.code
             WHERE D.name like '%".$_GET['find_val']."%'
             GROUP BY  C.code, D.name, C.thema, C.thema_detail, C.issue, C.stock_connect
             ORDER BY name, idx desc";

  // echo $query;
  $result = $mysqli->query($query);

  $i=0;
  $pre_cd = "";
  echo "<table class='table table-sm table-bordered small text-dark'>";
  
  while( $row = $result->fetch_array(MYSQLI_BOTH) ){
    if($pre_cd != $row['code']) { // 새로운 종목코드인 경우 명칭 출력
      echo "<tr><td colspan=3 class='mark'><a href=\"javascript:setStock('".$row['code']."','".$row['name']."','');\" class='text-danger font-weight-bold'>[".$row['code']."]".$row['name']."</a></td></tr>";
    }
    
    if($row['idx'] == 'stock_comment') { // 종목 코멘트 
      echo "<tr><td></td><td><a href=\"javascript:setStock('".$row['code']."','".$row['name']."','".$row['comment']."');\" class='text-dark'>".$row['comment']."</a></td><td>".$row['remark']."</td></tr>";
    } else { // 시리언즈리포트
      echo "<tr><td></td><td><a href=\"javascript:setStock2('".$row['code']."','".$row['name']."','".$row['thema']."','".$row['thema_detail']."','".$row['issue']."','".$row['comment']."');\">";
      echo $row['report_date']." #".$row['thema_detail']." #".$row['issue']." #".$row['comment']."</a></td>";
      echo "<td>".$row['remark']."</td></tr>";
    }

    $pre_cd = $row['code'];
    $i++;
  }
  echo "</table>";
  echo "<input type=hidden name=rowcnt value='".$i."'>";
?>
    </div>
    <!-- End of Page Wrapper -->

<script>
function setStock(code, name, comment){
  opener_doc = opener.document;
	opener_doc.getElementById('code').value        = code;
	opener_doc.getElementById('name').value        = name;
	opener_doc.getElementById('stock_cn').value    = comment;
	opener_doc.getElementById('org_comment').value = comment;
  window.close();
}

function setStock2(code, name, thema, thema_detail, issue, comment){
  opener_doc = opener.document;
	opener_doc.getElementById('code').value        = code;
	opener_doc.getElementById('name').value        = name;
	opener_doc.getElementById('thema').value       = thema;
	opener_doc.getElementById('thema_d').value     = thema_detail;
	opener_doc.getElementById('issue').value       = issue;
	opener_doc.getElementById('stock_cn').value    = comment;
	opener_doc.getElementById('org_comment').value = comment;
  window.close();
}
</script>
</body>

</html>