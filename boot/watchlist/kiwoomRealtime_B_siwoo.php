<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
<style>
.minus-fraction {
    font-size: 0.75em; /* 소수점 이하 값을 작게 표시 */
    color: blue; /* 소수점 이하 값을 작게 표시 */
}
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
    max-width: 88px; /* 최대 너비 설정 */
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
    // 현재 시간이 15:30 이후인지
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

// 조회 최소거래대금 기준 (기본 20억)
$min_amount = (isset($_GET['min_amount']) && $_GET['min_amount'] != '') ? $_GET['min_amount'] * 100 : 2000;

// 해당 일자 등록 테이블 찾기 (성능 위해 백업 테이블 이동)
$query = "SELECT 'Y' FROM kiwoom_realtime_minute WHERE date = '$date' LIMIT 1";
$result = $mysqli->query($query);

$tableToUse = '';
if ($result->num_rows > 0) {    // 결과가 있는 
    $tableToUse = 'kiwoom_realtime_minute_siwoo';
} else {    // 결과가 빈 경우
    $tableToUse = 'kiwoom_realtime_minute_backup';
}

$sort = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'amount_last_min'; // 기본 정렬 기준
$order = "";
switch($sort) {
	case 'name':
		$order = "ORDER BY name";
		break;
	case 'rate':
		$order = "ORDER BY rate DESC";
		break;
		case 'amount_acc_day':
			$order = "ORDER BY amount_acc_day DESC, first_minute DESC";
		break;
		case 'amount_last_min':
			$order = "ORDER BY abs(amount_last_min) DESC, amount_acc_day DESC";
		break;
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
<form name="form1" method='POST' onsubmit="return false">

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
			$amountInBillion = round($amount/100, 1);

			// 색상 지정
			if ($amountInBillion >= 1000) {
				$color = '#fcb9b2'; // 10000억 이상
			} elseif ($amountInBillion >= 500) {
				$color = '#ffccd5'; // 500억 이상
			} elseif ($amountInBillion >= 100) {
				$color = '#f6dfeb'; // 100억 이상
			} elseif ($amountInBillion >= 50) {
				$color = '#fde2e4'; // 50억 이상
			} elseif ($amountInBillion >= 30) {
				$color = '#bee1e6'; // 30억 이상
			} elseif ($amountInBillion >= 10) {
				$color = '#e2ece9'; // 10억 이상
			} elseif ($amountInBillion >= 0) {
				$color = '#f0f4f5'; // 0억 이상
			} else {
				$color = '#ffffff'; // 10억 미만
			}
	
			if($sizeUp)
				$h5 = " class='h5'";
			else
				$h5 = "";
	
			$formattedNumber = number_format($amountInBillion, 1);
			$parts = explode('.', $formattedNumber);
			$whole = $parts[0];
			$fraction = $parts[1] ?? '00'; // 소수점 이하가 없는 경우 '00'으로 처리

			// 매도 거래량이 많은 경우 금액을 '-'로 가져옴. '-' 금액은 작게 보이게 처리
			if($amountInBillion > 0)
				$rtAmount = "<span>".$whole.".<span class='small-fraction'>".$fraction."</span></span>";
			else
				$rtAmount = "<span class='minus-fraction'>".$whole.".<span class='minus-fraction'>".$fraction."</span></span>";

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
			code, name, first_minute, last_minute,
			volume_sign_last_min   * amount_last_min   AS amount_last_min,
			volume_sign_last_1min  * amount_last_1min  AS amount_last_1min,
			volume_sign_last_2min  * amount_last_2min  AS amount_last_2min,
			volume_sign_last_3min  * amount_last_3min  AS amount_last_3min,
			volume_sign_last_4min  * amount_last_4min  AS amount_last_4min,
			volume_sign_last_5min  * amount_last_5min  AS amount_last_5min,
			volume_sign_last_6min  * amount_last_6min  AS amount_last_6min,
			volume_sign_last_7min  * amount_last_7min  AS amount_last_7min,
			volume_sign_last_8min  * amount_last_8min  AS amount_last_8min,
			volume_sign_last_9min  * amount_last_9min  AS amount_last_9min,
			volume_sign_last_10min * amount_last_10min AS amount_last_10min,
			volume_sign_last_11min * amount_last_11min AS amount_last_11min,
			volume_sign_last_12min * amount_last_12min AS amount_last_12min,
			amount_acc_day,
			rate
		FROM (
			SELECT
				s.code, s.name,
				MIN(minute) AS first_minute,
				MAX(minute) AS last_minute,
				IFNULL(MAX(CASE WHEN minute = t.last_min   THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_min,
				IFNULL(MAX(CASE WHEN minute = t.last_1min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_1min,
				IFNULL(MAX(CASE WHEN minute = t.last_2min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_2min,
				IFNULL(MAX(CASE WHEN minute = t.last_3min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_3min,
				IFNULL(MAX(CASE WHEN minute = t.last_4min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_4min,
				IFNULL(MAX(CASE WHEN minute = t.last_5min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_5min,
				IFNULL(MAX(CASE WHEN minute = t.last_6min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_6min,
				IFNULL(MAX(CASE WHEN minute = t.last_7min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_7min,
				IFNULL(MAX(CASE WHEN minute = t.last_8min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_8min,
				IFNULL(MAX(CASE WHEN minute = t.last_9min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_9min,
				IFNULL(MAX(CASE WHEN minute = t.last_10min THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_10min,
				IFNULL(MAX(CASE WHEN minute = t.last_11min THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_11min,
				IFNULL(MAX(CASE WHEN minute = t.last_12min THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_12min,
				IFNULL(MAX(CASE WHEN minute = t.last_min   THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_1min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_min,
				IFNULL(MAX(CASE WHEN minute = t.last_1min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_2min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_1min,
				IFNULL(MAX(CASE WHEN minute = t.last_2min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_3min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_2min,
				IFNULL(MAX(CASE WHEN minute = t.last_3min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_4min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_3min,
				IFNULL(MAX(CASE WHEN minute = t.last_4min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_5min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_4min,
				IFNULL(MAX(CASE WHEN minute = t.last_5min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_6min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_5min,
				IFNULL(MAX(CASE WHEN minute = t.last_6min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_7min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_6min,
				IFNULL(MAX(CASE WHEN minute = t.last_7min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_8min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_7min,
				IFNULL(MAX(CASE WHEN minute = t.last_8min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_9min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_8min,
				IFNULL(MAX(CASE WHEN minute = t.last_9min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_10min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_9min,
				IFNULL(MAX(CASE WHEN minute = t.last_10min THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_11min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_10min,
				IFNULL(MAX(CASE WHEN minute = t.last_11min THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_12min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_11min,
				IFNULL(MAX(CASE WHEN minute = t.last_12min THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_13min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_12min,
				IFNULL(MAX(CASE WHEN minute <= t.last_min THEN acc_trade_amount ELSE NULL END), 0) AS amount_acc_day,
				(
				SELECT m2.rate
				FROM $tableToUse m2
				WHERE m2.code = m.code
					AND STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') <= t.specific_datetime
				ORDER BY STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') DESC
				LIMIT 1
				) AS rate -- 주어진 시간 이전의 가장 최근 rate
			FROM
				$tableToUse m
			JOIN
				kiwoom_stock s
			ON s.code = m.code
			JOIN (
				SELECT
					specific_datetime,
					DATE_FORMAT(sd.specific_datetime, '%H%i') AS last_min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 1  MINUTE, '%H%i') AS last_1min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 2  MINUTE, '%H%i') AS last_2min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 3  MINUTE, '%H%i') AS last_3min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 4  MINUTE, '%H%i') AS last_4min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 5  MINUTE, '%H%i') AS last_5min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 6  MINUTE, '%H%i') AS last_6min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 7  MINUTE, '%H%i') AS last_7min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 8  MINUTE, '%H%i') AS last_8min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 9  MINUTE, '%H%i') AS last_9min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 10 MINUTE, '%H%i') AS last_10min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 11 MINUTE, '%H%i') AS last_11min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 12 MINUTE, '%H%i') AS last_12min,
					DATE_FORMAT(sd.specific_datetime - INTERVAL 13 MINUTE, '%H%i') AS last_13min
				FROM
					(SELECT STR_TO_DATE('$specific_datetime', '%Y%m%d%H%i') AS specific_datetime) sd
				) t
			WHERE
				m.date = DATE_FORMAT(t.specific_datetime, '%Y%m%d') AND -- Only considering today's data
				m.minute <= DATE_FORMAT(t.specific_datetime, '%H%i')
				$where
			GROUP BY
				s.code,
				s.name
			) G
			WHERE G.amount_acc_day > $min_amount
			$order 
		";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);
	
	echo "<table class='table table-sm table-bordered text-dark'>";

	// 데이터베이스 쿼리 결과를 배열에 저장
	$allData = [];
	$tradeAmountsByTime = []; // 시간대별 거래대금을 저장할 배열
	$times = ['acc_day', 'last_min', 'last_1min', 'last_2min', 'last_3min', 'last_4min', 'last_5min', 'last_6min', 'last_7min', 'last_8min', 'last_9min', 'last_10min', 'last_11min', 'last_12min'];

	while ($row = $result->fetch_array(MYSQLI_BOTH)) {
		// 모든 데이터를 $allData 배열에 저장
		$allData[] = $row;

		// 시간대별 거래대금 배열에 데이터 추가
		foreach ($times as $time) {
			$timeKey = "amount_$time";
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

	// Top 7 계산
	$topByTime = [];
	foreach ($tradeAmountsByTime as $timeKey => $items) {
		usort($items, function($a, $b) {
			return $b['amount'] - $a['amount'];
		});
		$topByTime[$timeKey] = array_slice($items, 0, 7); // 7위까지
	}

	// Top7 신규 종목 색상 표시
	// 처음 등장하는 종목에만 스타일을 적용하기 위한 배열 초기화
	$firstAppearance = [];

	// $times 배열 순서대로 처리 (현재, 1분 전, 2분 전, ... , 12분 전)
	echo "<tr><td colspan=3 align=center class=h2>Top7</td>";
	foreach ($times as $time) {
		$timeKey = "amount_$time";
		echo "<td cellpadding=0 cellspacing=0><table class='small table-borderless' cellpadding=0 cellspacing=0>";
		
		if (isset($topByTime[$timeKey])) {
			foreach ($topByTime[$timeKey] as $item) {
				$link_name = "<a href='kiwoomRealtime_AStock.php?code={$item['code']}&name={$item['name']}&date={$date}' target='_blank' style='text-decoration: none; color: inherit;'>";
				$link_name.= "<b>{$item['name']}</b></a>";

				// 종목 코드가 첫 등장인지 확인
				if (!array_key_exists($item['code'], $firstAppearance)) {
					// 첫 등장 종목에 스타일 적용
					echo "<tr><td class='cut-text2' title={$item['name']} style='color: red;'><b>{$link_name}</b></td></tr>";
					// 첫 등장 기록
					$firstAppearance[$item['code']] = true;
				} else {
					// 이전에 등장한 종목
					echo "<tr><td class='cut-text2' title={$item['name']}><b>{$link_name}</b></td></tr>";
				}
			}
		}
		echo "</table></td>";
	}
	echo "</tr>";

	echo "<tr align=center>";
	echo "<th width=60>코드</th>";
	echo "<th width=120 onclick=\"sortTable('name')\">종목명</th>";
	echo "<th width=70  onclick=\"sortTable('rate')\">등락률</th>";
	echo "<th width=110 onclick=\"sortTable('amount_acc_day')\" >당일누적</th>";
	echo "<th width=90  onclick=\"sortTable('amount_last_min')\">".substr($minute,0,2).":".substr($minute,2,2)."</th>";
	echo "<th width=90>1분전</th>";
	echo "<th width=90>2분전</th>";
	echo "<th width=90>3분전</th>";
	echo "<th width=90>4분전</th>";
	echo "<th width=90>5분전</th>";
	echo "<th width=90>6분전</th>";
	echo "<th width=90>7분전</th>";
	echo "<th width=90>8분전</th>";
	echo "<th width=90>9분전</th>";
	echo "<th width=90>10분전</th>";
	echo "<th width=90>11분전</th>";
	echo "<th width=90>12분전</th>";
	echo "</tr>";
		
	// 나머지 데이터 출력
	foreach ($allData as $row) {
		// TD 생성을 위해 거래대금 배열에 담기
		$amounts = [
			'amount_acc_day'   => $row['amount_acc_day'],
			'amount_last_min'  => $row['amount_last_min'],
			'amount_last_1min' => $row['amount_last_1min'],
			'amount_last_2min' => $row['amount_last_2min'],
			'amount_last_3min' => $row['amount_last_3min'],
			'amount_last_4min' => $row['amount_last_4min'],
			'amount_last_5min' => $row['amount_last_5min'],
			'amount_last_6min' => $row['amount_last_6min'],
			'amount_last_7min' => $row['amount_last_7min'],
			'amount_last_8min' => $row['amount_last_8min'],
			'amount_last_9min' => $row['amount_last_9min'],
			'amount_last_10min' => $row['amount_last_10min'],
			'amount_last_11min' => $row['amount_last_11min'],
			'amount_last_12min' => $row['amount_last_12min']
		];

		if($row['amount_last_min'] > 0) {
			$bgcolor = '#f0f4f5'; // 0억 이상
		} else {
			$bgcolor = '#ffffff'; // 10억 미만
		}

		echo "<tr align=right>";
			echo "<td align=center class='small'>";
			echo "<a href='kiwoomRealtime15Min_AStock.php?code={$row['code']}&name={$row['name']}&date={$date}' target='_blank'>";
			echo $row['code']."</a></td>";
			echo "<td align=left class='cut-text' title=".$row['name']." style='background-color:".$bgcolor."'>";
			echo "<a href='kiwoomRealtime_AStock.php?code={$row['code']}&name={$row['name']}&date={$date}' target='_blank'>";
			echo "<b>".$row['name']."</b></a></td>";
			echo "<td><b>".$row['rate']."%</b></td>";
			$amountTdE = setAmountTdE($amounts, 'amount_acc_day', 'Y', 'Y');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_min', 'Y', 'Y');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_1min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_2min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_3min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_4min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_5min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_6min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_7min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_8min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_9min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_10min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_11min');
			echo $amountTdE;
			$amountTdE = setAmountTdE($amounts, 'amount_last_12min');
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