<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
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
// 조회일자
$date = (isset($_GET['date']) ) ? $_GET['date'] : '';

// minute GET 변수 확인 및 현재 시간 구하기
if (isset($_GET['minute']) && $_GET['minute'] != '') {
    $minute = $_GET['minute'];
} else {
    // 현재 시간이 15:30 이후인지 확인
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');
    
    if ($currentHour > 15 || ($currentHour == 15 && $currentMinute > 30)) {
        // 현재 시간이 15:30 이후인 경우, 15:30으로 설정
        $minute = '1530';
    } else {
        // 그렇지 않은 경우, 현재 시간을 'Hi' 포맷으로 사용
        $minute = date('Hi');
    }
}

$specific_datetime = $date.$minute;

// 해당 일자 등록 테이블 찾기 (성능 위해 백업 테이블 이동)
$query = "SELECT 'Y' FROM kiwoom_realtime_minute WHERE date = '$date' LIMIT 1";
$result = $mysqli->query($query);

$tableToUse = '';
if ($result->num_rows > 0) {    // 결과가 있는 
    $tableToUse = 'kiwoom_realtime_minute';
} else {    // 결과가 빈 경우
    $tableToUse = 'kiwoom_realtime_minute_backup';
}

// 정렬
$sort = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'time_lasted'; // 기본 정렬 기준
// echo $sort;
if($sort == 'time_lasted') {
	$order = "ORDER BY time_1515 DESC, time_1500 DESC, time_1445 DESC, time_1430 DESC, time_1415 DESC, time_1400 DESC, time_1345 DESC, time_1330 DESC, time_1315 DESC, time_1300 DESC, time_1245 DESC, time_1230 DESC, time_1215 DESC, time_1200 DESC";
	$order.= ", time_1145 DESC, time_1130 DESC, time_1115 DESC, time_1100 DESC, time_1045 DESC, time_1030 DESC, time_1015 DESC, time_1000 DESC, time_0945 DESC, time_0930 DESC, time_0915 DESC, time_0900 DESC";
} else if($sort == 'name') {
	$order = "ORDER BY name";
} else {
	$order = "ORDER BY ".$sort." DESC";
}

$sector = (isset($_GET['sector']) ) ? $_GET['sector'] : '';
$theme  = (isset($_GET['theme']) ) ? $_GET['theme'] : '';

// 섹터별로 조회한 경우 해당 섹터 종목만 조회되도록..
$where = '';
if($theme != '') {
	echo $sector."&nbsp;".$theme;
	$where = "AND m.code IN (SELECT code FROM watchlist_sophia WHERE sector = '$sector' AND theme = '$theme')";
}
?>

<body>
<form name="form1" method='POST' action='siriensEvening_script.php' onsubmit="return false">

<?php
if($specific_datetime == '') {
	echo "<h3>일자를 선택해 주세요!!</h3>";
	$ready = 'N';
}
else {

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

		// 30분 쿼리. 혹시나 백업용
		// $query = " 
		// SELECT 
		// 	code, name,
		// 	time_0900,time_0930,time_1000,time_1030,time_1100,time_1130,
		// 	time_1200,time_1230,time_1300,time_1330,time_1400,time_1430,time_1500,time_all
		// FROM (
		// 	SELECT
		// 		s.code, s.name,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '0900' AND '0929' THEN acc_trade_amount ELSE NULL END), 0) AS time_0900,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '0930' AND '0959' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0929' THEN acc_trade_amount ELSE 0 END), 0) AS time_0930,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1000' AND '1029' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '0959' THEN acc_trade_amount ELSE 0 END), 0) AS time_1000,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1030' AND '1059' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1029' THEN acc_trade_amount ELSE 0 END), 0) AS time_1030,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1100' AND '1129' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1059' THEN acc_trade_amount ELSE 0 END), 0) AS time_1100,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1130' AND '1159' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1129' THEN acc_trade_amount ELSE 0 END), 0) AS time_1130,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1200' AND '1229' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1159' THEN acc_trade_amount ELSE 0 END), 0) AS time_1200,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1230' AND '1259' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1229' THEN acc_trade_amount ELSE 0 END), 0) AS time_1230,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1300' AND '1329' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1259' THEN acc_trade_amount ELSE 0 END), 0) AS time_1300,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1330' AND '1359' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1329' THEN acc_trade_amount ELSE 0 END), 0) AS time_1330,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1400' AND '1429' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1359' THEN acc_trade_amount ELSE 0 END), 0) AS time_1400,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1430' AND '1459' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1429' THEN acc_trade_amount ELSE 0 END), 0) AS time_1430,
		// 		IFNULL(MAX(CASE WHEN minute BETWEEN '1500' AND '1530' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '1459' THEN acc_trade_amount ELSE 0 END), 0) AS time_1500,
		// 		IFNULL(MAX(CASE WHEN minute <= '1530' THEN acc_trade_amount ELSE NULL END), 0) AS time_all
		// 	FROM
		// 		$tableToUse m
		// 	JOIN
		// 		kiwoom_stock s
		// 	ON s.code = m.code
		// 	JOIN 
		// 		(SELECT STR_TO_DATE('$specific_datetime', '%Y%m%d%H%i') specific_datetime) t
		// 	WHERE
		// 		m.date = DATE_FORMAT(t.specific_datetime, '%Y%m%d') AND -- Only considering today's data
		// 		m.minute <= DATE_FORMAT(t.specific_datetime, '%H%i')
		// 		$where
		// 	GROUP BY
		// 		s.code,
		// 		s.name
		// 	) G
		// 	$order 
		// ";

	$query = " 
		SELECT 
			code, name,
			time_0900,time_0915,time_0930,time_0945,time_1000,time_1015,time_1030,time_1045,time_1100,time_1115,time_1130,time_1145,
			time_1200,time_1215,time_1230,time_1245,time_1300,time_1315,time_1330,time_1345,time_1400,time_1415,time_1430,time_1445,time_1500,time_1515,time_all
		FROM (
			SELECT
				s.code, s.name,
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
				$tableToUse m
			JOIN
				kiwoom_stock s
			ON s.code = m.code
			JOIN 
				(SELECT STR_TO_DATE('$specific_datetime', '%Y%m%d%H%i') specific_datetime) t
			WHERE
				m.date = DATE_FORMAT(t.specific_datetime, '%Y%m%d') AND -- Only considering today's data
				m.minute <= DATE_FORMAT(t.specific_datetime, '%H%i')
				$where
			GROUP BY
				s.code,
				s.name
			) G
			$order 
		";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered text-dark'>";

	// 데이터베이스 쿼리 결과를 배열에 저장
	$allData = [];
	$tradeAmountsByTime = []; // 시간대별 거래대금을 저장할 배열
	$times = ['all', '0900', '0915', '0930', '0945', '1000', '1015', '1030', '1045', '1100', '1115', '1130', '1145', '1200', '1215', '1230', '1245', '1300', '1315', '1330', '1345', '1400', '1415', '1430', '1445', '1500', '1515'];

	while ($row = $result->fetch_array(MYSQLI_BOTH)) {
		// 모든 데이터를 $allData 배열에 저장
		$allData[] = $row;

		// 시간대별 거래대금 배열에 데이터 추가
		foreach ($times as $time) {
			$timeKey = "time_$time";
			$amount = $row[$timeKey] ?? 0;

			if (!isset($tradeAmountsByTime[$timeKey])) {
				$tradeAmountsByTime[$timeKey] = [];
			}
			
			if ($amount > 0) {
				$tradeAmountsByTime[$timeKey][] = [
					'code' => $row['code'],
					'name' => $row['name'],
					'amount' => $amount
				];
			}
		}
	}

	// Top 5 계산
	$top7ByTime = [];
	foreach ($tradeAmountsByTime as $timeKey => $items) {
		usort($items, function($a, $b) {
			return $b['amount'] - $a['amount'];
		});
		$top7ByTime[$timeKey] = array_slice($items, 0, 7);
	}

	// Top 5 출력
	echo "<tr><td colspan=2 align=center class=h2>Top7</td>";
	foreach ($times as $time) {
		$timeKey = "time_$time";
		echo "<td cellpadding=0 cellspacing=0><table class='small table-borderless' cellpadding=0 cellspacing=0>";
		if (isset($top7ByTime[$timeKey])) {
			foreach ($top7ByTime[$timeKey] as $item) {
				echo "<tr><td class='cut-text2' title={$item['name']}><b>{$item['name']}</b></td></tr>"; // 종목명
			}
		}
		echo "</table></td>";
	}
	echo "</tr>";

	echo "<tr align=center class='table-active'>";
	echo "<th width=60>코드</th>";
	echo "<th width=120 onclick=\"sortTable('name')\">종목명</th>";
	echo "<th width=110 onclick=\"sortTable('time_all')\">당일누적</th>";
	echo "<th width=80 onclick=\"sortTable('time_0900')\">09:00</th>";
	echo "<th width=80 onclick=\"sortTable('time_0915')\">09:15</th>";
	echo "<th width=80 onclick=\"sortTable('time_0930')\">09:30</th>";
	echo "<th width=80 onclick=\"sortTable('time_0945')\">09:45</th>";
	echo "<th width=80 onclick=\"sortTable('time_1000')\">10:00</th>";
	echo "<th width=80 onclick=\"sortTable('time_1015')\">10:15</th>";
	echo "<th width=80 onclick=\"sortTable('time_1030')\">10:30</th>";
	echo "<th width=80 onclick=\"sortTable('time_1045')\">10:45</th>";
	echo "<th width=80 onclick=\"sortTable('time_1100')\">11:00</th>";
	echo "<th width=80 onclick=\"sortTable('time_1115')\">11:15</th>";
	echo "<th width=80 onclick=\"sortTable('time_1130')\">11:30</th>";
	echo "<th width=80 onclick=\"sortTable('time_1145')\">11:45</th>";
	echo "<th width=80 onclick=\"sortTable('time_1200')\">12:00</th>";
	echo "<th width=80 onclick=\"sortTable('time_1215')\">12:15</th>";
	echo "<th width=80 onclick=\"sortTable('time_1230')\">12:30</th>";
	echo "<th width=80 onclick=\"sortTable('time_1245')\">12:45</th>";
	echo "<th width=80 onclick=\"sortTable('time_1300')\">13:00</th>";
	echo "<th width=80 onclick=\"sortTable('time_1315')\">13:15</th>";
	echo "<th width=80 onclick=\"sortTable('time_1330')\">13:30</th>";
	echo "<th width=80 onclick=\"sortTable('time_1345')\">13:45</th>";
	echo "<th width=80 onclick=\"sortTable('time_1400')\">14:00</th>";
	echo "<th width=80 onclick=\"sortTable('time_1415')\">14:15</th>";
	echo "<th width=80 onclick=\"sortTable('time_1430')\">14:30</th>";
	echo "<th width=80 onclick=\"sortTable('time_1445')\">14:45</th>";
	echo "<th width=80 onclick=\"sortTable('time_1500')\">15:00</th>";
	echo "<th width=80 onclick=\"sortTable('time_1515')\">15:15</th>";
	echo "</tr>";
		
	// 나머지 데이터 출력
	foreach ($allData as $row) {
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
			echo "<td align=center class='small'>";
			echo "<a href='kiwoomRealtime_AStock.php?code=".$row['code']."&name=".$row['name']."&date=".$date."' onclick='window.open(this.href, \'realtime_stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>";
			echo $row['code']."</a></td>";
			
			echo "<td align=left class='cut-text' title=".$row['name'].">";
			echo "<a href='kiwoomRealtime15Min_AStock.php?code=".$row['code']."&name=".$row['name']."&date=".$date."' onclick='window.open(this.href, \'realtime_stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>";
			echo "<b>".$row['name']."</b></a></td>";
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