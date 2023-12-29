<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>

<body id="page-top">
<!-- Main Content -->
<div id="content">

<?php
$code = (isset($_GET['code'])) ? $_GET['code'] : '';
?>
</head>

<body>
<form name="form1" method='POST' action='popup_script.php'>
<div style='position: absolute;  right: 0px;'>
<input type=button class="btn btn-danger btn-sm" onclick="procComment('save')" value='저 장'>
<input type=button class="btn btn-danger btn-sm" onclick="procComment('del')"  value='선택삭제'>
</div>
<br>
<div>
<?php
	// 키워드
	$qry  = " SELECT 	a.id,
						b.code,
						b.name,
						a.keyword,
						a.remark
				FROM stock_keyword a
				INNER JOIN stock b
				ON a.code = b.code
				AND b.last_yn = 'Y'
				WHERE a.code = '$code'
				ORDER BY a.id";

	// echo $qry;
	$result = $mysqli->query($qry);

	echo "<div class='small'><b>▷ 키워드</b></div>";
	echo "<table width='100%' class='table table-warning table-sm small'>";
	echo "<thead><th>선택</th><th>키워드</th><th>비고</th></thead>";

	$i=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$id    = $row['id'];
		$code    = $row['code'];
		$keyword = $row['keyword'];
		$remark  = $row['remark'];

		echo "
		<tr> 
		<td width=50><input type=checkbox name=k_chk$i></td>
		<td width=500 align=left><input type=text name=keyword$i value=\"$keyword\" style='width:240px'><input type=hidden name=k_id$i value='$id'></td>
		<td width=250 align=left><input type=text name=k_remark$i value='$remark' style='width:200px'></td>
		</tr>
		";
		//<textarea name=title$i style='width:700px; height:20px'>$title</textarea>
		$i++;
	}

	// 신규 키워드 입력란 추가
	echo "
	<tr> 
	<td width=50><b>(+)</b></td>
	<td width=500 align=left><input type=text name=keywordNew  style='width:240px'></td>
	<td width=250 align=left><input type=text name=k_remarkNew style='width:200px'></td>
	</tr>
	";

	echo "</table>";

	// 코멘트
	$qry  = " SELECT 	a.id,
						b.code,
						b.name,
						a.comment,
						a.remark
				FROM stock_comment a
				INNER JOIN stock b
				ON a.code = b.code
				AND b.last_yn = 'Y'
				WHERE a.code = '$code'
				ORDER BY a.id";

	// echo $qry;
	$result = $mysqli->query($qry);
	
	echo "<div class='small'><b>▷ 코멘트</b></div>";
	echo "<table width='100%' class='table table-success table-sm small'>";
	echo "<thead>	<th>선택</th><th>코멘트</th><th>비고</th></thead>";

	$j=0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$id    = $row['id'];
		$code    = $row['code'];
		$comment = $row['comment'];
		$remark  = $row['remark'];

		echo "
		<tr> 
		<td width=50><input type=checkbox name=c_chk$j></td>
		<td width=500 align=left><input type=text name=comment$j value=\"$comment\" style='width:490px'><input type=hidden name=c_id$j value='$id'></td>
		<td width=250 align=left><input type=text name=c_remark$j value='$remark' style='width:200px'></td>
		</tr>
		";
		
		$j++;
	}

	// 신규 코멘트 입력란 추가
	echo "
	<tr> 
	<td width=50><b>(+)</b></td>
	<td width=500 align=left><input type=text name=commentNew  style='width:490px'></td>
	<td width=250 align=left><input type=text name=c_remarkNew style='width:200px'></td>
	</tr>
	";
	echo "</table>";
	
	$lowprice1  = '';
	$highprice1 = '';
	$lowprice2  = '';
	$highprice2 = '';

	// 키워드
	$qry  = " SELECT 	a.range_fg,
						a.low_price,
						a.high_price
				FROM stock_kswingport a
				WHERE a.code = '$code'";

	// echo $qry;
	$result = $mysqli->query($qry);

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($row['range_fg'] == 'range1') {
			$lowprice1 = $row['low_price'];
			$highprice1 = $row['high_price'];
		}
		
		if($row['range_fg'] == 'range2') {
			$lowprice2 = $row['low_price'];
			$highprice2 = $row['high_price'];
		}
	}

	echo "<div class='small'><b>▷ K-스윙 포트</b></div>";
	echo "<table width='100%' class='table table-danger table-sm small'>";
	echo "<tr valign=middle>";
	echo "<td><b> 범위1 </b> (적당)</td>";
	echo "<td><b>저가</b></td>";
	echo "<td><input type=text name=lowprice1 value='$lowprice1'></td>";
	echo "<td><b>고가</b></td>";
	echo "<td><input type=text name=highprice1 value='$highprice1'></td>";
	echo "</tr>";
	echo "<tr valign=middle>";
	echo "<td><b> 범위2 </b> (초초바닥)</td>";
	echo "<td><b>저가</b></td>";
	echo "<td><input type=text name=lowprice2 value='$lowprice2'></td>";
	echo "<td><b>고가</b></td>";
	echo "<td><input type=text name=highprice2 value='$highprice2'></td>";
	echo "</tr></table>";
	
	echo "<input type=hidden name=page_id value='stock_common'>";
	echo "<input type=hidden name=code value=$code>";
	echo "<input type=hidden name=keyword_cnt value=$i>";
	echo "<input type=hidden name=comment_cnt value=$j>";
	echo "<input type=hidden name=proc_fg>";
?>
</div>
</form>
</body>
<script>
function procComment(procFg) {
	form = document.form1;
	form.proc_fg.value = procFg;
	form.target = "saveComment";
	form.submit();
}
</script>
<iframe name="saveComment" src="popup_script.php" width=0 height=0>
</html>