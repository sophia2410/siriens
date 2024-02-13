<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
</head>

<?php
$date = (isset($_GET['date']) ) ? $_GET['date'] : '';
$minute = (isset($_GET['minute']) && $_GET['minute'] != '' ) ? $_GET['minute'] : date('Hi');  // 현재 시간을 구함;
$specific_datetime = $date.$minute;

$sort = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'amount_last_min'; // 기본 정렬 기준
$order = "";
switch($sort) {
	case 'name':
		$order = "ORDER BY s.name";
		break;
	case 'rate':
		$order = "ORDER BY rate DESC";
		break;
		case 'amount_acc_day':
			$order = "ORDER BY amount_acc_day DESC, first_minute DESC";
		break;
		case 'amount_last_min':
			$order = "ORDER BY amount_last_min DESC, first_minute DESC";
		break;
}
?>

<body>
<form name="form1" method='POST' action='sirianzEvening_script.php' onsubmit="return false">

<?php
if($specific_datetime == '') {
	echo "<h3>일자를 선택해 주세요!!</h3>";
	$ready = 'N';
}
else {
	function getAmountTdE($amount, $bold='N', $sizeUp='N', $decimals=2) {
		// 백만원 단위를 억단위로 변경
		if($amount == 0) {
			$tdE = "<td>&nbsp;</td>";
		} else {
			$amountInBillion = round($amount/100, $decimals);
	
			// 색상 지정
			if ($amountInBillion >= 10000) {
				$color = '#fde2e4'; // 10000억 이상
			} elseif ($amountInBillion >= 500) {
				$color = '#ffccd5'; // 500억 이상
			} elseif ($amountInBillion >= 100) {
				$color = '#fcb9b2'; // 100억 이상
			} elseif ($amountInBillion >= 50) {
				$color = '#f6dfeb'; // 50억 이상
			} elseif ($amountInBillion >= 30) {
				$color = '#e2ece9'; // 30억 이상
			} elseif ($amountInBillion >= 10) {
				$color = '#bee1e6'; // 10억 이상
			} else {
				$color = '#ffffff'; // 10억 미만
			}
	
			if($sizeUp)
				$h5 = " class='h5'";
			else
				$h5 = "";
	
			if($bold)
				$amountInBillion = "<b>".number_format($amountInBillion, $decimals). " 억</b>";
			else
				$amountInBillion = number_format($amountInBillion, $decimals). " 억";
		
			$tdE = "<td style='background-color:".$color."' $h5>$amountInBillion</td>";
		}

		return $tdE;
	}

	$query = " 
		SELECT
			s.code,
			s.name,
			MIN(minute) AS first_minute,
			MAX(minute) AS last_minute,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime THEN minute_amount ELSE 0 END) AS amount_last_min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 1 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_1min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 2 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_2min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 3 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_3min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 4 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_4min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 5 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_5min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 6 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_6min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 7 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_7min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 8 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_8min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 9 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_9min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') BETWEEN t.specific_datetime - INTERVAL 29 MINUTE AND t.specific_datetime - INTERVAL 10 MINUTE THEN minute_amount ELSE 0 END) AS amount_10to29min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') BETWEEN t.specific_datetime - INTERVAL 59 MINUTE AND t.specific_datetime - INTERVAL 30 MINUTE THEN minute_amount ELSE 0 END) AS amount_30to59min,
			SUM(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') BETWEEN t.specific_datetime - INTERVAL 119 MINUTE AND t.specific_datetime - INTERVAL 60 MINUTE THEN minute_amount ELSE 0 END) AS amount_last_1hr,
			(
			SELECT m2.rate
			FROM kiwoom_realtime_minute m2
			WHERE m2.code = m.code
				AND STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') <= t.specific_datetime
			ORDER BY STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') DESC
			LIMIT 1
			) AS rate, -- 주어진 시간 이전의 가장 최근 rate
			(
			SELECT m2.acc_trade_amount
			FROM kiwoom_realtime_minute m2
			WHERE m2.code = m.code
				AND STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') <= t.specific_datetime
			ORDER BY STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') DESC
			LIMIT 1
			) AS amount_acc_day -- 주어진 시간 이전의 가장 최근 acc_volume
		FROM
			kiwoom_realtime_minute m
		JOIN
			kiwoom_stock s
		ON s.code = m.code
		JOIN 
			(SELECT STR_TO_DATE('$specific_datetime', '%Y%m%d%H%i') specific_datetime) t
		WHERE
			m.date = DATE_FORMAT(t.specific_datetime, '%Y%m%d') AND -- Only considering today's data
			m.minute <= DATE_FORMAT(t.specific_datetime, '%H%i')
		GROUP BY
			s.code,
			s.name
		$order 
		";
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered text-dark'>";
	echo "<tr align=center>";
	echo "<th width=80>종목코드</th>";
	echo "<th width=120 onclick=\"sortTable('name')\">종목명</th>";
	echo "<th width=70  onclick=\"sortTable('rate')\">등락률</th>";
	echo "<th width=100 onclick=\"sortTable('amount_acc_day')\" >당일누적</th>";
	echo "<th width=100 onclick=\"sortTable('amount_last_min')\">".substr($minute,0,2).":".substr($minute,2,2)."</th>";
	echo "<th width=100>1분전</th>";
	echo "<th width=100>2분전</th>";
	echo "<th width=100>3분전</th>";
	echo "<th width=100>4분전</th>";
	echo "<th width=100>5분전</th>";
	echo "<th width=100>6분전</th>";
	echo "<th width=100>7분전</th>";
	echo "<th width=100>8분전</th>";
	echo "<th width=100>9분전</th>";
	echo "<th width=100>10-29분전</th>";
	echo "<th width=100>30-59분전</th>";
	echo "<th width=100>1H-2H사이</th>";
	echo "<th width=80>최초등록</th>";
	echo "<th width=80>최종등록</th>";
	echo "</tr>";

	$i = 0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {

		
		echo "<tr align=right>";
			echo "<td align=center>".$row['code']."</td>";
			echo "<td align=left><b>".$row['name']."</b></td>";
			echo "<td><b>".$row['rate']."%</b></td>";
			$amountTdE = getAmountTdE($row['amount_acc_day'], 'Y', 'Y', 0);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_min'], 'Y', 'Y');
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_1min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_2min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_3min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_4min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_5min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_6min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_7min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_8min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_9min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_10to29min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_30to59min']);
			echo $amountTdE;
			$amountTdE = getAmountTdE($row['amount_last_1hr']);
			echo $amountTdE;
			echo "<td style='$amountTdE;' align=center>".substr($row['first_minute'],0,2).":".substr($row['first_minute'],2,2)."</td>";
			echo "<td style='$amountTdE;' align=center>".substr($row['last_minute'],0,2).":".substr($row['last_minute'],2,2)."</td>";
		echo "</tr>";

		$i++;
	}
	echo "</table>";
}
?>
</form>
<script>
function sortTable(sortBy) {
	window.parent.search(sortBy);
}
</script>
</body>
</html>