<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>

<body id="page-top" onload=autoSet()>
<form id="popup">
  <!-- Main Content -->
  <div id="content">

  <?php
  $query = "select code, replace(name, ' ', '') name
              from stock
             where replace(name, ' ', '') like '".preg_replace("/\s+/", "", $_GET['find_val'])."%' 
               and last_yn = 'Y'
           " ;

  $result = $mysqli->query($query);
  //printf("Select returned %d rows.\n", $result->num_rows);

  $i=0;
  $cd=$nm="";
  while( $row = $result->fetch_array(MYSQLI_BOTH) ){
    //echo "<input type=button onclick=setStock('".$row['code']."','".$row['name']."') value='".$row['name']."'>";

    echo "<div class='m-3 font-weight-bold text-primary' id='stock".$i."' name='".$row['code']."'><a href=\"javascript:setStock('".$row['code']."','".$row['name']."');\">".$row['name']."</div>";
    $cd=$row['code'];
    $nm=$row['name'];

    $i++;
  }
  echo "<input type=hidden name=rowcnt value='".$i."'>";
  echo "<input type=hidden name=cd value='".$cd."'>";
  echo "<input type=hidden name=nm value='".$nm."'>";
?>
    </div>
    <!-- End of Page Wrapper -->
</form>

<script>
  function setStock(code, name){
    opener.form.stock.value = code;
    opener.form.stock_nm.value = name;
    opener.getList();
    window.close();
  }

  function autoSet(){
	console.log(popup.rowcnt.value);
	if(popup.rowcnt.value == 1){
		console.log(popup.cd.value);
		console.log(popup.nm.value);
		setStock(popup.cd.value, popup.nm.value);
	}
  }
</script>
</body>

</html>