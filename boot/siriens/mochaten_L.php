<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$mochaten_date = (isset($_GET['mochaten_date'])) ? $_GET['mochaten_date'] : date('Ymd',time());
$browser_width = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : 2000;

$query = " SELECT A.mochaten_date
				, A.cha_fg
				, B.nm cha_fg_nm
				, B.nm_sub1 cha_comment
				, A.code
				, A.name
				, E.close_rate
				, A.market_cap
				, A.tot_trade_amt
				, A.f_trade_amt
				, A.o_trade_amt
				, A.p_trade_amt
				, A.op_ratio
				, A.lb_ratio
				, A.create_dtime
			 FROM mochaten A
			INNER JOIN comm_cd B
			   ON B.cd = A.cha_fg
		     LEFT OUTER JOIN daily_price E
			   ON E.DATE = A.trade_date
			  AND E.code = A.code
			WHERE A.mochaten_date = '$mochaten_date'
			ORDER BY A.cha_fg, A.tot_trade_amt DESC";
$result = $mysqli->query($query);
?>

<body class="body">
<table class="table table-sm small">
<?php
	$pre_cha_fg = "";

	// 해상도 낮은 화면에서 조회될 수 있도록
	if($browser_width >=1500) {
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			if($pre_cha_fg != $row['cha_fg']) {
				// echo "<tr class='table-danger  text-dark' align=center><th colspan=2><b>".$row['cha_fg_nm']."</b></th><td colspan=4 align=left>".$row['cha_comment']."</td></tr>";
				echo "<tr class='table-danger  text-dark' align=left><th colspan=4><b>[".$row['cha_fg_nm']."</b>]".$row['cha_comment']."</th></tr>";
			}
			echo "<tr>";
			echo "<td><a href=\"javascript:callFrameR('".$mochaten_date."','".$row['code']."','".$row['name']."')\">".$row['name']."</a></td>";
			echo "<td class='text-danger' align=right>".$row['close_rate']." %</td>" ;
			echo "<td class='text-danger' align=right>".number_format($row['tot_trade_amt'])." 억</td>" ;
			echo "</tr>" ;
			
			$pre_cha_fg =  $row['cha_fg'];
		}
	} else {
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			if($pre_cha_fg != $row['cha_fg']) {
				echo "<tr class='table-danger  text-dark' align=center><th><b>".$row['cha_fg_nm']."</b></th></tr>";
			}
			echo "<tr><td><a href=\"javascript:callFrameR('".$mochaten_date."','".$row['code']."','".$row['name']."')\">".$row['name']."</a></td>";
			echo "</tr>" ;
			
			$pre_cha_fg =  $row['cha_fg'];
		}
	}
?>
</table>
</body>

<script>
// parent 함수 호출, 오른쪽 프레임 종목정보 표시
function callFrameR(date, cd, nm) {
	window.parent.viewMochaten(date, cd, nm);
}
</script>
</html>
