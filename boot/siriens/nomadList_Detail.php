<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
</head>

<?php
$group_nm = (isset($_GET['group_nm'])) ? $_GET['group_nm'] : '';
?>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">
	
<form id="form" method=post>
<?php
	$query = " SELECT id, replace(group_nm,'관천-','') group_nm, sub_group_nm, code, name
				FROM nomad_list A
				WHERE group_nm = '$group_nm'
				ORDER BY id";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);
	
	$pre_date = '';
	$pre_theme = '';
	$input = array();


	$i=0;
	$pre_keyword_link = '';

	echo "<div>";
	echo "<table style='width:100%;' class='table table-bordered text-dark'>";
	echo "<tr>";
	echo "<th>그룹</th>";
	echo "<th>서브그룹</th>";
	echo "<th>종목코드</th>";
	echo "<th>종목명</th>";
	echo "</tr>";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		echo "<tr>";
		echo "<td><B>".$row['group_nm']."</B></td>";
		echo "<td><B>".$row['sub_group_nm']."</B></td>";
		echo "<td style='width:10%'><B>".$row['code']."</B></td>";
		echo "<td style='width:50%'><B>".$row['name']."</B></td>";
		echo "</tr>";
		$i++;
	}
	echo "</table>";
	echo "</div>";
?>
</form>

</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<?php
if($_SERVER["HTTP_HOST"] == 'localhost') {
	$PATH = "http://localhost";
} else {
	$PATH = "https://siriens.mycafe24.com";
}
?>

<script>
//키워드 추가/변경
function popupKeyword(link) {
	form = document.form1;
	link = '/boot/common/popup/keyword.php' ;
	window.open(link,'popupKeyword',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=800, height=1200");
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
