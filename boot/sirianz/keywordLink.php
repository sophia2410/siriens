<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
</head>

<?php
$keyword_cd = (isset($_GET['keyword_cd'])) ? $_GET['keyword_cd'] : '';
?>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_sirianz.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">
	
<form id="form" method=post>
<?php
	$query = " SELECT A.keyword_link, B.code, B.name
					, C.keyword_nm keyword_nm1
					, D.keyword_nm keyword_nm2
					, E.keyword_nm keyword_nm3
					, F.keyword_nm keyword_nm4
					, G.keyword_nm keyword_nm5
				FROM (SELECT  keyword_cd1
							, keyword_cd2
							, keyword_cd3
							, keyword_cd4
							, keyword_cd5
							, CONCAT(keyword_cd1, IFNULL(keyword_cd2,''), IFNULL(keyword_cd3,''), IFNULL(keyword_cd4,''), IFNULL(keyword_cd5,'')) keyword_link
							, code
						FROM  _theme_keyword_link
						WHERE (keyword_cd1 = '$keyword_cd'
							or keyword_cd2 = '$keyword_cd'
							or keyword_cd3 = '$keyword_cd'
							or keyword_cd4 = '$keyword_cd'
							or keyword_cd5 = '$keyword_cd')) A
				INNER JOIN stock B
				ON B.code = A.code
				AND B.last_yn = 'Y'
				INNER JOIN theme_keyword C
				ON C.keyword_cd = A.keyword_cd1
				LEFT OUTER JOIN theme_keyword D
				ON D.keyword_cd = A.keyword_cd2
				LEFT OUTER JOIN theme_keyword E
				ON E.keyword_cd = A.keyword_cd3
				LEFT OUTER JOIN theme_keyword F
				ON F.keyword_cd = A.keyword_cd4
				LEFT OUTER JOIN theme_keyword G
				ON G.keyword_cd = A.keyword_cd5
				ORDER BY A.keyword_link";

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

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($pre_keyword_link != $row['keyword_link'])
		{
			echo "<tr class='table-danger  text-dark' align=center><th colspan=3><b>".$row['keyword_nm1']." ".$row['keyword_nm2']." ".$row['keyword_nm3']." ".$row['keyword_nm4']." ".$row['keyword_nm5']."</b></th></tr>";
		}
		echo "<tr><td style='width:10%'><B>".$row['name']."</B></td></tr>";

		$pre_keyword_link = $row['keyword_link'];
		$i++;
	}
	
	//변경 버튼 추가
	if($i > 0 && $i%10 == 0) {
		echo "</tr><tr>";
		$r++;
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
	$PATH = "https://yunseul0907.cafe24.com";
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
