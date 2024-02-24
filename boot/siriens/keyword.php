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
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">
	
<form id="form" method=post>
<?php

	$query = " SELECT keyword_cd, keyword_nm, keyword_fg
				 FROM theme_keyword A
				ORDER BY A.sort_no, A.keyword_fg, A.keyword_nm";
	$result = $mysqli->query($query);
	
	$pre_date = '';
	$pre_theme = '';
	$input = array();


	$i=0;
	$r=0;
	$t=0;
	$prd_fg = '';

	echo "<div>";
	echo "<table style='width:100%;' class='table table-bordered table-danger table-sm'>";
	echo "<tr>";

	$tb_css = array('table-success','table-secondary', 'table-secondary', 'table-secondary');

	while($row = $result->fetch_array(MYSQLI_BOTH)) {

		// keyword fg 달라지면 테이블 변경
		if($prd_fg != '' && $prd_fg != $row['keyword_fg']) {
			echo "</tr></table>";
			echo "<table style='width:100%;' class='table ".$tb_css[$t]." table-bordered text-dark table-sm'>";
			echo "<tr>";
			$t++;
			$r = 0;
		}
		//10건씩 한줄에 출력
		else if($r > 0 && $r%10 == 0) {
			echo "</tr><tr>";
			$r = 0;
		}

		echo "<td style='width:10%'><B><a href='./keywordLink.php?keyword_cd=".$row['keyword_cd']."'>".$row['keyword_nm']."</a></B></td>";

		$prd_fg = $row['keyword_fg'];
		$i++;
		$r++;
	}
	
	//변경 버튼 추가
	if($i > 0 && $i%10 == 0) {
		echo "</tr><tr>";
	}
	echo "<td style='width:10%'><input type=button class='btn-icon-split bg-info' value=' 추가/변경 ' onclick=popupKeyword()></td>";

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
