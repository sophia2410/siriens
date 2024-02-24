<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_date = (isset($_POST['report_date'])   ) ? $_POST['report_date']    : date('Ymd',time());
?>
<head>
<link rel="shortcut icon" href="#">
<style>
	.content {
	height: 600px;
	border: 0px solid hsl(0, 0%, 75%);
	}
	.content > img {
	width: auto;
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
<div id="content" style='margin-left:15px;'>

<form name="form1" method='POST' action='siriVSinfostock.php'>
<?php
	$query = " SELECT date
					FROM calendar
				WHERE date <= DATE_ADD(now(), INTERVAL 0 DAY)
				ORDER BY date DESC
				LIMIT 20";

	$result = $mysqli->query($query);

	$input_date = "<select id='report_date' name='report_date' class='select'>";
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($report_date == $row['date']) {
			$input_date .= "<option value='". $row['date']."' selected>". $row['date']."</option>";
		} else {
			$input_date .= "<option value='". $row['date']."'>". $row['date']."</option>";
		}
	}
	$input_date .= "</select> ";

	echo $input_date;
	echo "<input type='button' id='searchbtn' class='btn btn-danger' onclick='searchReport()' value='조 회'>";
	echo "<div style='height:15px;'></div>";

	// 모차십종목 차트일자 구하기
	$query = "SELECT REPLACE(MIN(date), '-', '') mochaten_date FROM calendar WHERE date > '$report_date'";
	$result = $mysqli->query($query);
	$row = $result->fetch_array(MYSQLI_BOTH);
	$mochaten_date = $row['mochaten_date'];
	
	// 종목 차트 이미지 경로 구하기
	$query = "SELECT nm_sub1, nm_sub2 FROM comm_cd WHERE cd = 'PT001'";
	$result = $mysqli->query($query);
	$ifpath = $result->fetch_array(MYSQLI_BOTH);

	$svpath = $ifpath['nm_sub1'];
	$lcpath = $ifpath['nm_sub2'];

	// report 조회
	$query = " SELECT min(id)
					, report_date
					, code
					, name
					, max(infostock_theme) infostock_theme
					, max(infostock_issue) infostock_issue
					, max(siri_theme) siri_theme
					, max(siri_issue) siri_issue
					, max(siri_friend_stock) siri_friend_stock
				FROM (
						SELECT id*1000000 id
							 , page_date report_date
							 , code
							 , stock name
							 , '' infostock_theme
							 , '' infostock_issue
							 , CONCAT('[',A.signal_grp , CASE WHEN A.theme is not null or A.theme != '' THEN concat('-',A.theme,']') ELSE ']' END) siri_theme
							 , A.title siri_issue
							 , A.stocks siri_friend_stock
						  FROM rawdata_siri_report A
						 WHERE code IS NOT NULL
						   AND page_date = '$report_date'
						   AND today_pick = 'Y'
						 UNION ALL
						SELECT B.id
							 , B.report_date
							 , B.code
							 , B.name
							 , CONCAT(CASE WHEN C.today_theme_nm is not null THEN concat('[',C.today_theme_nm,'] ') ELSE '' END) infostock_theme
							 , B.issue infostock_issue
							 , '' siri_theme
							 , '' siri_issue
							 , '' siri_friend_stock
						  FROM siriens_infostock B
						  LEFT OUTER JOIN siriens_infostock_theme C
							ON C.today_theme_cd = B.today_theme_cd
						 WHERE B.issue != '' 
						   AND B.report_date = '$report_date'
					 ) Z
				GROUP BY report_date, code, name
				ORDER BY infostock_theme, id*1 ";
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	echo "<table class='table table-sm text-dark''>";

	$pre_code = "";
	$pre_theme = "";
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($row['infostock_issue'] == '') $bgcolor = 'red';
		else if($row['siri_issue'] == '') $bgcolor = 'blue';
		else $bgcolor = '';

		
		echo "<tr valign=center>";
		echo "<td style='background-color:$bgcolor'>".$row['code']."</td>";
		echo "<td>".$row['name']."</td>";
		echo "<td>".$row['infostock_theme']."</td>";
		echo "<td>".$row['infostock_issue']."</td>";
		echo "<td>".$row['siri_theme']."</td>";
		echo "<td>".$row['siri_issue']."</td>";
		echo "<td>".$row['siri_friend_stock']."</td>";
		echo "</tr>";
	}
	echo "</table>";

?>
</form>


</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<script>
// 일자 선택후 조회
function searchReport() {
	document.forms[0].submit();
}
</script>
<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
