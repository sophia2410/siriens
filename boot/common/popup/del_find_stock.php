<?php
  require("../top.php");
	require("../db/connect.php");
?>

<body id="page-top">
<form onload=test()>
  <!-- Main Content -->
  <div id="content">

  <?php
  $query = "select code, replace(name, ' ', '') name
              from stock
             where replace(name, ' ', '') like '".preg_replace("/\s+/", "", $_GET['find_val'])."%'
           " ;

  $result = $mysqli->query($query);
  //printf("Select returned %d rows.\n", $result->num_rows);

  $i=0;
  $cd=$nm="";
  while( $row = $result->fetch_array(MYSQLI_BOTH) ){
    //echo "<input type=button onclick=setStock('".$row['code']."','".$row['name']."') value='".$row['name']."'>";

    echo "<div class='card' id='stock".$i."' name='".$row['code']."'><a href=\"javascript:setStock('".$row['code']."','".$row['name']."');\">".$row['name']."</div>";
    $cd=$row['code'];
    $nm=$row['name'];

    $i++;
  }
  echo "<input type=hidden name=rowcnt value='".$i."'>";
?>
    </div>
    <!-- End of Page Wrapper -->

<script>
  function test(){
    alert(form.rowcnt.value);
  }
  function setStock(code, name){
    opener.form.stock.value = code;
    opener.form.stock_nm.value = name;
    opener.getList();
    window.close();
  }
</script>
</form>
</body>

</html>