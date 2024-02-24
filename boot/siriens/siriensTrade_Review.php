<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
	
$trade_date = (isset($_POST['trade_date'])) ? $_POST['trade_date'] : date('Ymd');
$buysell_fg = (isset($_POST['buysell_fg'])) ? $_POST['buysell_fg'] : 'B';

$query = " SELECT DISTINCT trade_date
			 FROM siriens_trade
			ORDER BY trade_date DESC
			LIMIT 30";

$result = $mysqli->query($query);
?>
<head>
<link rel="shortcut icon" href="#">
<style>
	/* .content {
	height: 450px;
	border: 0px solid hsl(0, 0%, 75%);
	} */
	.content > img {
	width: 1000px;
	height: 100%;
	}
	input[type=radio] { margin-left: 5px }
</style>
</head>
<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">
<form name="form1" method='POST' action='mochatenReview_sophia.php'>

<div style='border: 1px;' class="card-header py-3">
	<select id="trade_date" name="trade_date" class="select">
	<option value="">--choose an option--</option>
	<?php
		$option = "";
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			if($trade_date == $row['trade_date']) {
				$option .= "<option value='". $row['trade_date']."' selected>". $row['trade_date']."</option>";
			} else {
				$option .= "<option value='". $row['trade_date']."'>". $row['trade_date']."</option>";
			}
		}
		echo $option;
	?>
	</select>

	<select id="buysell_fg" name="buysell_fg" class="select">
	<option value="">--choose an option--</option>
	<?php
		$option = "";
		if($buysell_fg == "B") {
			$selectB = " selected";
			$selectS = "";
		} else {
			$selectB = "";
			$selectS = " selected";
		}

		$option .= "<option value='B' $selectB>매매</option>";
		$option .= "<option value='S' $selectS>공부</option>";
		echo $option;
	?>
	</select>

	<button class="btn btn-primary btn-sm" onclick="search()"> 조 회 </button>
</div>

<?php
// 당일 차트 이미지 가져오기
// --- 이미지 경로 구하기
$query = "SELECT cd, nm_sub1, nm_sub2 FROM comm_cd WHERE cd = 'PT005'";
$result = $mysqli->query($query);
$row = $result->fetch_array(MYSQLI_BOTH);

if($_SERVER["HTTP_HOST"] == 'localhost') {	// localhost 인 경우 파일 존재 확인
	$filepath = $row['nm_sub1'];
} else { // 서버에서 파일 존재 확인
	$filepath = $row['nm_sub2'];
}

$query = " SELECT A.trade_date
				, A.code
				, B.name
				, A.regi_id
				, A.daily_comment
				, A.good_comment
				, A.bad_comment
				, A.daily_comment
				, A.trade_rate
				, A.buysell_fg
			 FROM siriens_trade A
			 INNER JOIN stock B
			   ON B.code = A.code
			  AND B.last_yn = 'Y'
			WHERE A.trade_date = '$trade_date'
			  AND A.buysell_fg = '$buysell_fg'
			ORDER BY trade_rate desc";
// echo "<pre>$query</pre>";
$result = $mysqli->query($query);
?>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<!-- <div class="card header">
<h3 class="m-0 font-weight-bold text-primary">Today Pick</h3>
</div> -->
	<?php
		$trade_rate = "";

		echo "<table class='table table-sm'  style='width:70%;'>";
		while($row = $result->fetch_array(MYSQLI_BOTH)) {

			if($row['buysell_fg'] == 'B') {
				if( $row['trade_rate'] > 0 )
					$trade_rate = "<font color=red>".$row['trade_rate']." % </font>";
				else
					$trade_rate = "<font color=blue>".$row['trade_rate']." % </font>";
			}

			echo "<tr><td class='text-danger' colspan=2>".$row['name']." $trade_rate </td></tr>" ;

			$imgpath = $filepath.$trade_date."_".$row['code'].".png";
			// echo $imgpath;
			echo "<tr><td colspan=2><article class='content'>
					<img src='$imgpath'></td>
					</article></td>
				  </tr>";
			if($row['daily_comment'] != '')
				echo "<tr><td><b>Comment</b></td><td>".$row['daily_comment']."<br><br></td></tr>" ;
			if($row['good_comment'] != '')
				echo "<tr><td><b>Good</b></td><td>".$row['good_comment']."<br><br></td></tr>" ;
			if($row['bad_comment'] != '')
				echo "<tr><td><b>Bad</b></td><td>".$row['bad_comment']."<br><br></td></tr>" ;
		}
		echo "</table>";
	?>
</table>
</form>

</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<script>
// 모차십 일자 선택후 조회 - 왼쪽 프레임에 종목 리스트업
function search() {
	key_val  = document.getElementById('trade_date').options[document.getElementById("trade_date").selectedIndex].value;
	console.log(key_val);
	
	document.form1.action = "siriensTrade_Review.php";
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
