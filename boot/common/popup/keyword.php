<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>

<body id="page-top">
<!-- Main Content -->
<div id="content">
</head>

<body>
<form name="form1" method='POST' action='popup_script.php'>
<div id="row">
<div style='position: absolute;  left: 0px;'>
<label>G(Group) / C(Country,Company) / T(Theme) / K(Keyword) / D(Detil)</label>
</div>
<div style='position: absolute;  right: 0px;'>
<input type=button class="btn btn-danger btn-sm" onclick="procKeyword('save')" value='저 장'>
<input type=button class="btn btn-danger btn-sm" onclick="procKeyword('del')"  value='선택삭제'>
</div>
</div>
<br>
<div>
<?php
	// 키워드
	$qry  = " SELECT A.keyword_cd
					, A.keyword_nm
					, A.keyword_nm2
					, A.keyword_nm3
					, A.keyword_fg
					, A.sort_no
					, B.keyword_cnt
				FROM theme_keyword A
				LEFT OUTER JOIN (SELECT keyword_cd, count(*) keyword_cnt FROM sirianz_report_keyword GROUP BY keyword_cd) B
				ON B.keyword_cd = A.keyword_cd
				ORDER BY A.sort_no, A.keyword_fg, A.keyword_nm";

	// echo $qry;
	$result = $mysqli->query($qry);

	echo "<table width='95%' class='table table-warning table-sm small'>";
	echo "<thead><th>선택</th><th>구분</th><th>키워드</th><th>동의어1</th><th>동의어2</th><th>사용횟수</th><th></th></thead>";

	// 신규 키워드 입력란 추가
	echo "
	<tr style='background-color:#FFFFFF;'> 
	<td width=50><b>(+)</b></td>
	<td width=150>
		<input type=radio name=keywordFgNew value='1-G'>G
		<input type=radio name=keywordFgNew value='2-C'>C
		<input type=radio name=keywordFgNew value='3-T'>T
		<input type=radio name=keywordFgNew value='4-K' checked>K
		<input type=radio name=keywordFgNew value='5-D'>D
		</td>
	<td width=100>신규</td>
	<td width=190 align=left><input type=text name=keywordNew  style='width:180px'></td>
	<td width=190 align=left><input type=text name=keywordNew2 style='width:180px'></td>
	<td width=190 align=left><input type=text name=keywordNew3 style='width:180px'></td>
	<td width=80 align=left>&nbsp</td>
	<td>&nbsp</td>
	</tr>
	";

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$keyword_cd  = $row['keyword_cd'];
		$keyword_nm  = $row['keyword_nm'];
		$keyword_nm2 = $row['keyword_nm2'];
		$keyword_nm3 = $row['keyword_nm3'];
		$keyword_fg  = $row['keyword_fg'];
		$keyword_cnt = $row['keyword_cnt'];

		$checked = array("G"=>"","C"=>"","T"=>"","K"=>"","D"=>"");

		$checked[$keyword_fg] = ' checked';

		echo "
		<tr> 
		<td width=50><input type=checkbox name=k_chk$i></td>
		<td width=150>
			<input type=radio name=keyword_fg$i value='1-G' $checked[G]>G
			<input type=radio name=keyword_fg$i value='2-C' $checked[C]>C
			<input type=radio name=keyword_fg$i value='3-T' $checked[T]>T
			<input type=radio name=keyword_fg$i value='4-K' $checked[K]>K
			<input type=radio name=keyword_fg$i value='5-D' $checked[D]>D
		</td>
		<td width=100 >$keyword_cd</td>
		<td width=190 align=left><input type=text name=keyword_nm_a$i value=\"$keyword_nm\" style='width:180px'><input type=hidden name=keyword_cd$i value='$keyword_cd'></td>
		<td width=190 align=left><input type=text name=keyword_nm_b$i value='$keyword_nm2' style='width:180px'></td>
		<td width=190 align=left><input type=text name=keyword_nm_c$i value='$keyword_nm3' style='width:180px'></td>
		<td width=80 align=right>".number_format($keyword_cnt)."</td>
		<td>&nbsp</td>
		</tr>
		";
		$i++;
	}

	echo "</table>";
	
	echo "<input type=hidden name=page_id value='keyword'>";
	echo "<input type=hidden name=keyword_cnt value=$i>";
	echo "<input type=hidden name=proc_fg>";
?>
</div>
</form>
</body>
<script>
function procKeyword(procFg) {
	form = document.form1;
	form.proc_fg.value = procFg;
	form.target = "saveKeyword";
	form.submit();
}
</script>
<iframe name="saveKeyword" src="popup_script.php" width=500 height=200>
</html>