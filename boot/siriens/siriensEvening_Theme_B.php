<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
</head>

<?php
$report_date = (isset($_GET['report_date']) ) ? $_GET['report_date'] : "";
$theme = (isset($_GET['theme']) ) ? $_GET['theme'] : '';
$issue = (isset($_GET['issue']) ) ? $_GET['issue'] : '';
$keyword = (isset($_GET['keyword']) ) ? $_GET['keyword'] : '';
?>

<body>
<form name="form1" method='POST' action='siriensEvening_script.php' onsubmit="return false">

<?php
	$query = " SELECT A.id
					, A.report_date
					, A.signal_grp
					, A.theme
					, A.issue
					, A.keyword
					, A.code
					, A.name
					, E.close_rate
					, ROUND(E.volume/1000) volume
					, E.close_rate
					, F.volume Fvolume
					, IFNULL(F.tot_trade_amt,ROUND(E.amount/100000000,0)) tot_trade_amt
					-- , ROUND(E.amount/100000000,0) tot_trade_amt
					, A.stock_keyword
					, A.stock_CPR
					, C.evening_issue
					, D.infostock_issue
					FROM siriens_evening A
					LEFT OUTER JOIN (SELECT signal_id, CONCAT('(',date,') ',title) evening_issue FROM signals ) C
					ON  C.signal_id = A.signal_id
					LEFT OUTER JOIN (SELECT id siriens_infostock_id
										, report_date
										, code
										, issue infostock_issue
									FROM siriens_infostock
									where issue IS NOT NULL) D
					ON  D.report_date = A.report_date
					AND D.code = A.code
					LEFT OUTER JOIN daily_price E
					ON  E.date = A.report_date
					AND E.code = A.code
					LEFT OUTER JOIN (select trade_date, code, MAX(volume) volume, MAX(tot_trade_amt) tot_trade_amt FROM mochaten group by trade_date, code ) F
					ON F.trade_date = A.report_date
					AND F.code = A.code
					WHERE A.report_date = '$report_date'
					AND A.issue = '$issue'
					AND A.keyword = '$keyword'
					ORDER BY A.id ";
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	echo "<table class='table table-sm small table-bordered text-dark'>";
	echo "<tr align=center>";
	echo "<th width=30><input type=checkbox name=ckAll onclick='tgAllCk()'></th>";
	echo "<th width=120>그룹</th>";
	echo "<th width=80>이슈</th>";
	echo "<th width=80>키워드</th>";
	echo "<th width=150>종목</th>";
	echo "<th width=80>등락률</th>";	
	echo "<th style='minWidth:60px'>거래량</th>";
	echo "<th style='minWidth:60px'>거래대금</th>";
	echo "<th width=140>종목 키워드</th>";
	echo "<th>키움 관리종목 메모</th>";
	echo "<th>시리 이브닝 뉴스</th>";
	echo "<th>인포스탁 코멘트</th>";
	echo "</tr>";

	$i = 0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {

	// 상한가, 천만주, 천억 종목의 경우 별도 표시
	if($row['close_rate'] > 29 || $row['volume'] > 10000 || $row['tot_trade_amt'] > 1000) {
	$stock_style = "text-danger font-weight-bold";
	} else {
	$stock_style = "text-secondary font-weight-bold";
	}

	echo "<tr>";
	echo "<td align=center><input type=checkbox class=chk name=check$i value='Y'></td>";
	echo "<td><input type=text name=signal_grp$i value=\"".$row['signal_grp']."\" style='width: 120px;'></td>";
	echo "<td><input type=text name=issue$i value=\"".$row['issue']."\" style='width: 100px;'></td>";
	echo "<td><input type=text name=keyword$i value=\"".$row['keyword']."\" style='width: 100px;'></td>";
	echo "<td><a href='./stock_B.php?code=".$row['code']."&name=".$row['name']."' class='"."$stock_style"."' title=".$row['code']." onclick='window.open(this.href, 'stock', 'width=1800px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>".$row['name']."</a></td>";
	echo "<td align=right class='"."$stock_style"."' >".$row['close_rate']."</td>";
	echo "<td align=right class='"."$stock_style"."' >".number_format($row['volume'])."</td>";
	echo "<td align=right class='"."$stock_style"."' >".number_format($row['tot_trade_amt'])."</td>";
	echo "<td><input type=text name=stock_keyword$i value=\"".$row['stock_keyword']."\" style='width: 120px;'></td>";
	echo "<td>".$row['stock_CPR'];
	echo "<input type=hidden name=id$i   value=\"".$row['id']."\">";
	echo "<input type=hidden name=code$i value=\"".$row['code']."\"></td>";
	echo "<td>".$row['evening_issue']."</td>";
	echo "<td>".$row['infostock_issue']."</td>";
	echo "</tr>";

	$i++;
}
echo "</table>";
echo "<input type=hidden name=report_date value='$report_date'>";
echo "<input type=hidden name=cnt value='$i'>";
echo "<input type=hidden name=proc_fg>";
?>
</form>

</body>
<script>
function tgCk(i){
	var ckbx = document.getElementsByClassName('chk');
	ckbx[i].checked=!ckbx[i].checked;
}

function tgAllCk(){
	var ckbx = document.getElementsByClassName('chk');
	for(i=0;i<ckbx.length;i++){
		ckbx[i].checked=document.form1.ckAll.checked;
	}
}

// 키워드 등록
function save() {
	form = document.form1;
	form.proc_fg.value='stockSave';
	form.target = "iframeH";
	form.submit();

	comAllUnCk();
}
// 키워드 삭제
function del() {
	form = document.form1;
	form.proc_fg.value='stockDel';
	form.target = "iframeH";
	form.submit();

	comAllUnCk();
}
</script>
<iframe name="iframeH" src = "siriensEvening_script.php" width=0 height=0>
</html>