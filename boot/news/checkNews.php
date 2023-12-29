<!DOCTYPE html>
<html>
<head></head>
<body onload=rtnValue()>
<form id="iframe">
<?php
require("../common/db/connect.php");

  $link = str_replace('^amp^', '&', $_GET['link']);

  $query = "select signal_id from signals
             where link = '".$link."'" ;
  echo $query;
  $result = $mysqli->query($query);
  //printf("Select returned %d rows.\n", $result->num_rows);

  $i=0;
  while( $row = $result->fetch_array(MYSQLI_BOTH) ){
	echo "<input type=text id=signal_id value='".$row['signal_id']."'>";
	$i++;
  }

  if($i==0){
	echo "<input type=text id=signal_id value=''>";
  }
?>
</form>
</body>

<script>
  function rtnValue(){
	signal_id = document.getElementById('signal_id').value;
	console.log(signal_id);
	parent.getNews(signal_id);
  }
</script>

</html>