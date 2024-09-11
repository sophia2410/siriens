<?php
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_mon = (isset($_GET['report_mon'])   ) ? $_GET['report_mon']    : date('Ym',time());
?>
</head>

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

	$query = " SELECT MIN(A.id) id, A.group_nm org_group_nm, replace(A.group_nm,'관천-','') group_nm, B.sub_group_nm
				 FROM nomad_list A
				LEFT OUTER JOIN (SELECT group_nm, GROUP_CONCAT(sub_group_nm ORDER BY id) sub_group_nm
								   FROM (SELECT MIN(id) id, group_nm, sub_group_nm FROM nomad_list WHERE sub_group_nm != '' GROUP BY group_nm, sub_group_nm) Q 
								  GROUP BY group_nm ) B
				ON B.group_nm = A.group_nm
				GROUP BY A.group_nm
				ORDER BY id";
	$result = $mysqli->query($query);
	
	$pre_date = '';
	$pre_theme = '';
	$input = array();


	$r=0;
	$prd_fg = '';

	echo "<div>";
	echo "<table style='width:100%;' class='table table-bordered table-danger'>";
	echo "<tr style='height:60px'>";

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		//3건씩 한줄에 출력
		if($r > 0 && $r%3 == 0) {
			echo "</tr><tr style='height:60px'>";
			$r = 0;
		}

		echo "<td style='width:20%'><B><a href='./nomadList_Detail.php?group_nm=".$row['org_group_nm']."'>".$row['group_nm']."</a></B><br>".$row['sub_group_nm']."</td>";

		$r++;
	}
	
	//변경 버튼 추가
	if($r > 0 && $r%3 == 0) {
		echo "</tr><tr>";
	}

	echo "</tr></table>";
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
	window.open(link,'popupKeyword',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1000, height=1200");
}
</script>

<?php
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
