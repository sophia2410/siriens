<?php

	// 종목정보
	$code = isset($_GET['code']) ? $_GET['code'] : '';
	$name = isset($_GET['name']) ? $_GET['name'] : ''; 

	$pageTitle = "실시간 1Month-$name";
	
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
<head>
<style>
.small-fraction {
    font-size: 0.75em; /* 소수점 이하 값을 작게 표시 */
}
.cut-text {
    max-width: 120px; /* 최대 너비 설정 */
    white-space: nowrap; /* 텍스트를 한 줄로 표시 */
    overflow: hidden; /* 내용이 넘칠 경우 숨김 */
    text-overflow: ellipsis; /* 넘친 내용을 생략 부호로 표시 */
    /* cursor: help; */ /* 마우스 오버 시 커서 모양 변경 */
}
.cut-text2 {
    max-width: 75px; /* 최대 너비 설정 */
    white-space: nowrap; /* 텍스트를 한 줄로 표시 */
    overflow: hidden; /* 내용이 넘칠 경우 숨김 */
    text-overflow: ellipsis; /* 넘친 내용을 생략 부호로 표시 */
    cursor: help; /* 마우스 오버 시 커서 모양 변경 */
}
</style>
</head>

<?php
// 기간정보
$date = (isset($_GET['date']) ) ? $_GET['date'] : '';
?>

<body>
<form name="form1" method='POST' action='siriensEvening_script.php' onsubmit="return false">

<?php
if($date == '') {
	echo "<h3>일자를 선택해 주세요!!</h3>";
	$ready = 'N';
}
else {
	// 종목 / 일자 정보 표시
	echo "<div><h4> ▶ [$code] $name </h4></div>";

	// 함수 :: 거래대금 TD 생성 
	function setAmountTdE($array, $key, $bold='N', $sizeUp='N') {
		
		// 키의 현재 위치 찾기
		$keys = array_keys($array);
		$position = array_search($key, $keys);
	
		// 현재 키의 값
		$amount = isset($array[$key]) ? $array[$key] : 0;

		// 백만원 단위를 억단위로 변경
		if($amount == 0) {
			$tdE = "<td>&nbsp;</td>";
		} else {
			$amountInBillion = round($amount/100, 2);
	
			// 색상 지정
			if ($amountInBillion >= 1000) {
				$color = '#fcb9b2'; // 10000억 이상
			} elseif ($amountInBillion >= 500) {
				$color = '#ffccd5'; // 500억 이상
			} elseif ($amountInBillion >= 250) {
				$color = '#f6dfeb'; // 250억 이상
			} elseif ($amountInBillion >= 100) {
				$color = '#fde2e4'; // 100억 이상
			} elseif ($amountInBillion >= 50) {
				$color = '#bee1e6'; // 50억 이상
			} elseif ($amountInBillion >= 30) {
				$color = '#f0f4f5'; //#e2ece9'; // 30억 이상
			} else {
				$color = '#ffffff'; // 30억 미만
			}
	
			if($sizeUp)
				$h5 = " class='h5'";
			else
				$h5 = "";
	
			$formattedNumber = number_format($amountInBillion, 2);
			$parts = explode('.', $formattedNumber);
			$whole = $parts[0];
			$fraction = $parts[1] ?? '00'; // 소수점 이하가 없는 경우 '00'으로 처리

			$rtAmount = "<span>".$whole.".<span class='small-fraction'>".$fraction."</span></span>";

			if($bold)
				$amountInBillion = "<b>".$rtAmount." 억</b>";
			else
				$amountInBillion =$rtAmount." 억";
		
			$tdE = "<td style='background-color:".$color."'> $amountInBillion </td>";
		}

		return $tdE;
	}
	$query = " 
		SELECT 
			c.date,
			STR_TO_DATE(c.date, '%Y%m%d') dateStr,
			CASE DAYOFWEEK(STR_TO_DATE(c.date, '%Y%m%d'))
				WHEN 1 THEN '일'
				WHEN 2 THEN '월'
				WHEN 3 THEN '화'
				WHEN 4 THEN '수'
				WHEN 5 THEN '목'
				WHEN 6 THEN '금'
				WHEN 7 THEN '토' END AS day,
			(SELECT close_rate FROM daily_price p WHERE p.date = c.date AND p.code = '$code') close_rate,
			time_0900,time_0915,time_0930,time_0945,time_1000,time_1015,time_1030,time_1045,time_1100,time_1115,time_1130,time_1145,
			time_1200,time_1215,time_1230,time_1245,time_1300,time_1315,time_1330,time_1345,time_1400,time_1415,time_1430,time_1445,time_1500,time_1515,time_all
		FROM calendar c
		LEFT OUTER JOIN
		(
			SELECT date,
				IFNULL(MAX(CASE WHEN minute BETWEEN '0900' AND '0914' THEN acc_trade_amount ELSE NULL END), 0) AS time_0900,
				IFNULL(MAX(CASE WHEN minute BETWEEN '0915' AND '0929' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0914' THEN acc_trade_amount ELSE 0 END), 0) AS time_0915,
				IFNULL(MAX(CASE WHEN minute BETWEEN '0930' AND '0944' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0929' THEN acc_trade_amount ELSE 0 END), 0) AS time_0930,
				IFNULL(MAX(CASE WHEN minute BETWEEN '0945' AND '0959' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0944' THEN acc_trade_amount ELSE 0 END), 0) AS time_0945,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1000' AND '1014' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0959' THEN acc_trade_amount ELSE 0 END), 0) AS time_1000,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1015' AND '1029' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1014' THEN acc_trade_amount ELSE 0 END), 0) AS time_1015,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1030' AND '1044' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1029' THEN acc_trade_amount ELSE 0 END), 0) AS time_1030,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1045' AND '1059' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1044' THEN acc_trade_amount ELSE 0 END), 0) AS time_1045,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1100' AND '1114' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1059' THEN acc_trade_amount ELSE 0 END), 0) AS time_1100,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1115' AND '1129' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1114' THEN acc_trade_amount ELSE 0 END), 0) AS time_1115,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1130' AND '1144' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1129' THEN acc_trade_amount ELSE 0 END), 0) AS time_1130,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1145' AND '1159' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1144' THEN acc_trade_amount ELSE 0 END), 0) AS time_1145,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1200' AND '1214' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1159' THEN acc_trade_amount ELSE 0 END), 0) AS time_1200,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1215' AND '1229' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1214' THEN acc_trade_amount ELSE 0 END), 0) AS time_1215,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1230' AND '1244' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1229' THEN acc_trade_amount ELSE 0 END), 0) AS time_1230,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1245' AND '1259' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1244' THEN acc_trade_amount ELSE 0 END), 0) AS time_1245,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1300' AND '1314' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1259' THEN acc_trade_amount ELSE 0 END), 0) AS time_1300,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1315' AND '1329' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1314' THEN acc_trade_amount ELSE 0 END), 0) AS time_1315,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1330' AND '1344' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1329' THEN acc_trade_amount ELSE 0 END), 0) AS time_1330,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1345' AND '1359' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1344' THEN acc_trade_amount ELSE 0 END), 0) AS time_1345,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1400' AND '1414' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1359' THEN acc_trade_amount ELSE 0 END), 0) AS time_1400,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1415' AND '1429' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1414' THEN acc_trade_amount ELSE 0 END), 0) AS time_1415,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1430' AND '1444' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1429' THEN acc_trade_amount ELSE 0 END), 0) AS time_1430,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1445' AND '1459' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1444' THEN acc_trade_amount ELSE 0 END), 0) AS time_1445,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1500' AND '1514' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1459' THEN acc_trade_amount ELSE 0 END), 0) AS time_1500,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1515' AND '1530' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1514' THEN acc_trade_amount ELSE 0 END), 0) AS time_1515,
				IFNULL(MAX(CASE WHEN minute <= '1530' THEN acc_trade_amount ELSE NULL END), 0) AS time_all
			FROM
				kiwoom_realtime_minute m
			WHERE 
				m.date <= '$date' AND
				m.date >= (SELECT DATE_FORMAT(DATE_ADD('$date', INTERVAL -1 MONTH), '%Y%m%d')) AND
				m.code = '$code'
			GROUP BY
				m.date
			UNION ALL
			SELECT date,
				IFNULL(MAX(CASE WHEN minute BETWEEN '0900' AND '0914' THEN acc_trade_amount ELSE NULL END), 0) AS time_0900,
				IFNULL(MAX(CASE WHEN minute BETWEEN '0915' AND '0929' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0914' THEN acc_trade_amount ELSE 0 END), 0) AS time_0915,
				IFNULL(MAX(CASE WHEN minute BETWEEN '0930' AND '0944' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0929' THEN acc_trade_amount ELSE 0 END), 0) AS time_0930,
				IFNULL(MAX(CASE WHEN minute BETWEEN '0945' AND '0959' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0944' THEN acc_trade_amount ELSE 0 END), 0) AS time_0945,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1000' AND '1014' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0959' THEN acc_trade_amount ELSE 0 END), 0) AS time_1000,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1015' AND '1029' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1014' THEN acc_trade_amount ELSE 0 END), 0) AS time_1015,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1030' AND '1044' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1029' THEN acc_trade_amount ELSE 0 END), 0) AS time_1030,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1045' AND '1059' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1044' THEN acc_trade_amount ELSE 0 END), 0) AS time_1045,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1100' AND '1114' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1059' THEN acc_trade_amount ELSE 0 END), 0) AS time_1100,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1115' AND '1129' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1114' THEN acc_trade_amount ELSE 0 END), 0) AS time_1115,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1130' AND '1144' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1129' THEN acc_trade_amount ELSE 0 END), 0) AS time_1130,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1145' AND '1159' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1144' THEN acc_trade_amount ELSE 0 END), 0) AS time_1145,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1200' AND '1214' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1159' THEN acc_trade_amount ELSE 0 END), 0) AS time_1200,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1215' AND '1229' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1214' THEN acc_trade_amount ELSE 0 END), 0) AS time_1215,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1230' AND '1244' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1229' THEN acc_trade_amount ELSE 0 END), 0) AS time_1230,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1245' AND '1259' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1244' THEN acc_trade_amount ELSE 0 END), 0) AS time_1245,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1300' AND '1314' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1259' THEN acc_trade_amount ELSE 0 END), 0) AS time_1300,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1315' AND '1329' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1314' THEN acc_trade_amount ELSE 0 END), 0) AS time_1315,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1330' AND '1344' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1329' THEN acc_trade_amount ELSE 0 END), 0) AS time_1330,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1345' AND '1359' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1344' THEN acc_trade_amount ELSE 0 END), 0) AS time_1345,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1400' AND '1414' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1359' THEN acc_trade_amount ELSE 0 END), 0) AS time_1400,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1415' AND '1429' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1414' THEN acc_trade_amount ELSE 0 END), 0) AS time_1415,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1430' AND '1444' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1429' THEN acc_trade_amount ELSE 0 END), 0) AS time_1430,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1445' AND '1459' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1444' THEN acc_trade_amount ELSE 0 END), 0) AS time_1445,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1500' AND '1514' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1459' THEN acc_trade_amount ELSE 0 END), 0) AS time_1500,
				IFNULL(MAX(CASE WHEN minute BETWEEN '1515' AND '1530' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1514' THEN acc_trade_amount ELSE 0 END), 0) AS time_1515,
				IFNULL(MAX(CASE WHEN minute <= '1530' THEN acc_trade_amount ELSE NULL END), 0) AS time_all
			FROM
				kiwoom_realtime_minute_backup b
			WHERE
				b.date <= '$date' AND
				b.date >= (SELECT DATE_FORMAT(DATE_ADD('$date', INTERVAL -1 MONTH), '%Y%m%d')) AND
				b.code = '$code'
			GROUP BY
				b.date
		) g
		ON
			g.date = c.date
		WHERE 
			c.date <= '$date' AND
			c.date >= (SELECT DATE_FORMAT(DATE_ADD('$date', INTERVAL -1 MONTH), '%Y%m%d'))
		ORDER BY
			c.date DESC
		";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered text-dark'>";
	echo "<tr align=center>";
	echo "<th width=120>일자</th>";
	echo "<th width=60>요일</th>";
	echo "<th width=60>등락률</th>";
	echo "<th width=110>당일누적</th>";
	echo "<th width=80>09:00</th>";
	echo "<th width=80>09:15</th>";
	echo "<th width=80>09:30</th>";
	echo "<th width=80>09:45</th>";
	echo "<th width=80>10:00</th>";
	echo "<th width=80>10:15</th>";
	echo "<th width=80>10:30</th>";
	echo "<th width=80>10:45</th>";
	echo "<th width=80>11:00</th>";
	echo "<th width=80>11:15</th>";
	echo "<th width=80>11:30</th>";
	echo "<th width=80>11:45</th>";
	echo "<th width=80>12:00</th>";
	echo "<th width=80>12:15</th>";
	echo "<th width=80>12:30</th>";
	echo "<th width=80>12:45</th>";
	echo "<th width=80>13:00</th>";
	echo "<th width=80>13:15</th>";
	echo "<th width=80>13:30</th>";
	echo "<th width=80>13:45</th>";
	echo "<th width=80>14:00</th>";
	echo "<th width=80>14:15</th>";
	echo "<th width=80>14:30</th>";
	echo "<th width=80>14:45</th>";
	echo "<th width=80>15:00</th>";
	echo "<th width=80>15:15</th>";
	echo "</tr>";

	// 나머지 데이터 출력
	while ($row = $result->fetch_array(MYSQLI_BOTH)) {
		// TD 생성을 위해 거래대금 배열에 담기
		$amounts = [
			'amount_time_all'   => $row['time_all'],
			'amount_time_0900'  => $row['time_0900'],
			'amount_time_0915'  => $row['time_0915'],
			'amount_time_0930'  => $row['time_0930'],
			'amount_time_0945'  => $row['time_0945'],
			'amount_time_1000'  => $row['time_1000'],
			'amount_time_1015'  => $row['time_1015'],
			'amount_time_1030'  => $row['time_1030'],
			'amount_time_1045'  => $row['time_1045'],
			'amount_time_1100'  => $row['time_1100'],
			'amount_time_1115'  => $row['time_1115'],
			'amount_time_1130'  => $row['time_1130'],
			'amount_time_1145'  => $row['time_1145'],
			'amount_time_1200'  => $row['time_1200'],
			'amount_time_1215'  => $row['time_1215'],
			'amount_time_1230'  => $row['time_1230'],
			'amount_time_1245'  => $row['time_1245'],
			'amount_time_1300'  => $row['time_1300'],
			'amount_time_1315'  => $row['time_1315'],
			'amount_time_1330'  => $row['time_1330'],
			'amount_time_1345'  => $row['time_1345'],
			'amount_time_1400'  => $row['time_1400'],
			'amount_time_1415'  => $row['time_1415'],
			'amount_time_1430'  => $row['time_1430'],
			'amount_time_1445'  => $row['time_1445'],
			'amount_time_1500'  => $row['time_1500'],
			'amount_time_1515'  => $row['time_1515']
		];
		
		echo "<tr align=right>";
			echo "<td align=center>";
			echo "<a href='kiwoomRealtime_AStock.php?code={$code}&name={$name}&date={$row['date']}' onclick='window.open(this.href, \'realtime_stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>";
			echo "<b>".$row['dateStr']."</b></a></td>";
			echo "<td align=center><b>".$row['day']."</b></td>";
			echo "<td><b>".$row['close_rate']."</b>%</td>";
			
			$amountTdE = setAmountTdE($amounts, 'amount_time_all', 'Y', 'Y');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_0900');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_0915');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_0930');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_0945');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1000');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1015');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1030');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1045');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1100');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1115');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1130');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1145');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1200');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1215');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1230');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1245');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1300');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1315');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1330');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1345');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1400');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1415');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1430');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1445');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1500');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_time_1515');
			echo $amountTdE;
		echo "</tr>";
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