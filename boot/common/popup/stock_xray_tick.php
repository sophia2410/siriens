<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>

<body id="page-top">
  <!-- Main Content -->
  <div id="content">

  <?php
  $query = "SELECT time, change_rate, volume, amount,
  					ROUND(SUM(amount) OVER (ORDER BY time ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)/10000,1) AS cum_amt,
					ROUND(SUM(amount) OVER()/10000,1) tot_amt,
					COUNT(*) OVER() tot_cnt
  			FROM (
					SELECT time, round(change_rate*100,1) change_rate, current_price, volume, round((current_price * volume)/10000,0) amount
					FROM kiwoom_xray_tick_executions
					WHERE code = '".$_GET['code']."'
					AND date = '".$_GET['date']."'
					ORDER BY time
				) X
			";

//   echo $query;
  $result = $mysqli->query($query);

  echo "<table class='table table-sm table-bordered text-dark'>";
  echo "<tr align=center><th>시간</th><th>등락률</th><th>거래량</th><th>거래대금</th><th>누계대금</th></tr>";
  
  while( $row = $result->fetch_array(MYSQLI_BOTH) ){
    echo "<tr align=right>";
		echo "<td align=center>".$row['time']."</td>";
		echo "<td>".$row['change_rate']." %</td>";
		echo "<td>".number_format($row['volume'])."</td>";
		echo "<td>".number_format($row['amount'])." 만</td>";
		echo "<td>".number_format($row['cum_amt'],1)." 억</td>";
	echo "</tr>";

	$last_rate = $row['change_rate'];
	$tot_cnt = $row['tot_cnt'];
	$tot_amt = $row['tot_amt'];
  }
  echo "<tr align=right>";
	  echo "<td align=center>마감</td>";
	  echo "<td>".$last_rate." %</td>";
	  echo "<td>".number_format($tot_cnt)." 건</td>";
	  echo "<td> </td>";
	  echo "<td>".number_format($tot_amt,1)." 억</td>";
  echo "</tr>";
  echo "</table>";
?>
    </div>
    <!-- End of Page Wrapper -->

</body>

</html>