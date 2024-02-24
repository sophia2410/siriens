<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

	$report_date = (isset($_GET['report_date']) ) ? $_GET['report_date'] : "";
	$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;
?>
</head>

<body>
<form id="form" method=post action='marketReview_script.php' onsubmit="return false">
<?php

if($report_date == '') {
	echo "<h3>일자를 선택해주세요!!</h3>";
} else {

	$query = "SELECT '1' regi_fg FROM siriens_market_review WHERE report_date = '$report_date' LIMIT 1";
	$result = $mysqli->query($query);

	$row = $result->fetch_array(MYSQLI_BOTH);
	
	// 당일 마켓리뷰 등록되지 않은 경우 등록해주기
	if(!isset($row['regi_fg'])) {
		$query = "INSERT INTO siriens_market_review (report_date, content_cd, content_nm)
				  SELECT '$report_date', cd, nm FROM comm_cd WHERE l_cd = 'B0000'";
		// echo "<pre>$query</pre>";
		$mysqli->query($query);

		$query = "UPDATE siriens_market_review A
					INNER JOIN (SELECT content_cd, content_str 
								FROM siriens_market_review Z
								WHERE report_date = (select max(date) from calendar where date < '$report_date')
								AND content_cd IN ('B0090', 'B0100')) B
					ON B.content_cd = A.content_cd
					SET A.content_str = B.content_str
					WHERE A.report_date = '$report_date'";
		// echo "<pre>$query</pre>";
		$mysqli->query($query);
	}

	// 마켓리뷰 보기
	$query = " SELECT STR_TO_DATE(A.report_date, '%Y%m%d') report_date
					, A.content_cd, A.content_nm, A.content_str
					, IFNULL(STR_TO_DATE(B.report_date, '%Y%m%d'),' ') pre_report_date
					, IFNULL(B.content_str,' ') pre_content_str
					, C.nm_sub1, IFNULL(C.nm_sub2,'70') height_px, C.lvl, C.l_cd, C.m_cd
				FROM siriens_market_review A
				LEFT OUTER JOIN siriens_market_review B
				ON B.content_cd = A.content_cd
				AND B.report_date = (select max(date) from calendar where date < '$report_date')
				LEFT JOIN comm_cd C
				ON C.cd = A.content_cd
				WHERE A.report_date = '$report_date'
				ORDER BY A.content_cd";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);


	echo "<table class='table table-sm table-bordered text-dark'>";
	echo "<tr align=center class='font-weight-bold h6'>";

	$i = 0;
	$j = 0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {

		// 일자 출력
		if($j==0) {
			echo "<th style='width:50%'>".$row['report_date']."</th>";
			if($browser_width > 2000)
				echo "<th style='width:50%'>".$row['pre_report_date']."</th>";
			echo "</tr>";
		}

		// 레벨에 따라 스타일 적용 (레벨 구분을 중분류 코드가 있는지 여부로 판단)
		if($row['m_cd'] == '')
			$text_style = "mark text-danger font-weight-bold h6";
		else
			$text_style = "font-weight-bold";

		echo "<tr>";
		echo "<td class='"."$text_style"."' colspan=2>".$row['content_nm']."</td>";
		echo "</tr>";

		// lvl = 'E' => 입력 컨텐츠
		if($row['lvl'] == 'E') {
			echo "<tr align=left>";
			echo "<td>";
			echo "<textarea name='content_$i' style='width:95%; height:".$row['height_px']."px;'>".$row['content_str']."</textarea>";
			echo "<input type=hidden name='idx_$i' value=".$row['content_cd'].">";
			echo "</td>";
			if($browser_width > 2000)
				echo "<td><pre>".$row['pre_content_str']."</pre></td>";
			echo "</tr>";

			$i++;
		}
		$j++;
	}
	echo "</table>";
	echo "<input type=hidden name=report_date value='$report_date'>";
	echo "<input type=hidden name=cnt value='$i'>";
	echo "<input type=hidden name=proc_fg>";

}

?>
</form>

<script>
// 등록
function save() {
	form = document.forms[0];
	form.proc_fg.value='save';
	form.target = "iframeH";
	form.submit();
}
</script>
<iframe name="iframeH" src="marketReview_script.php" width=100% height=200>
<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
