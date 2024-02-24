<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>

<body id="page-top">
  <!-- Main Content -->
  <div id="content">

  <?php
  $query = "SELECT MAX(A.report_date) report_date, A.thema, B.nm thema_str, A.thema_detail, A.issue
              FROM siriens_evening A
             INNER JOIN comm_cd B
                ON B.cd = A.thema
             WHERE A.thema = '".$_GET['find_val']."'
			 GROUP BY  A.thema, B.nm, A.thema_detail, A.issue
             ORDER BY report_date desc, B.nm";

//   echo $query;
  $result = $mysqli->query($query);

  echo "<table class='table table-sm table-bordered small text-dark'>";
  echo "<tr><th>테마상세</th><th>이슈</th><th>일자</th></tr>";
  
  while( $row = $result->fetch_array(MYSQLI_BOTH) ){
    echo "<tr><td>".$row['thema_detail']."</td>";
	echo "<td><a href=\"javascript:setStock('".$row['thema_detail']."','".$row['issue']."');\">".$row['issue']."</a></td>";
	echo "<td>".$row['report_date']."</td></tr>";
  }
  echo "</table>";
?>
    </div>
    <!-- End of Page Wrapper -->

<script>
function setStock(thema_d, issue){
	opener_doc = opener.document;
	opener_doc.getElementById('thema_d').value = thema_d;
	opener_doc.getElementById('issue').value   = issue;
	window.close();
}
</script>
</body>

</html>