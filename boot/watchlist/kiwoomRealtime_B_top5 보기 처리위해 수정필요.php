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
			$order = "ORDER BY amount_one_min DESC, amount_acc_day DESC";
		break;
}
		
$sector = (isset($_GET['sector']) ) ? $_GET['sector'] : '';
$theme  = (isset($_GET['theme']) ) ? $_GET['theme'] : '';

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

	// 특정 키에 대한 값과 그 이후의 0이 아닌 값을 찾음
	function findValueAndNextNonZero($array, $key) {
		// 키의 현재 위치 찾기
		$keys = array_keys($array);
		$position = array_search($key, $keys);
	
		// 현재 키의 값
		$currentValue = isset($array[$key]) ? $array[$key] : 0;
	
		// 다음 0이 아닌 값 찾기
		$nextNonZeroValue = 0;
		for ($i = $position + 1; $i < count($array); $i++) {
			if ($array[$keys[$i]] != 0) {
				$nextNonZeroValue = $array[$keys[$i]];
				break;
			}
		}

		return [
			'currentValue' => $currentValue,
			'nextNonZeroValue' => $nextNonZeroValue
		];
	}

	// 거래대금 TD 생성
	function setAmountTdE($array, $key, $bold='N', $sizeUp='N') {
		
		$result = findValueAndNextNonZero($array, $key);

		$amount = $result['currentValue'];

		// 백만원 단위를 억단위로 변경
		if($amount == 0) {
			$tdE = "<td>&nbsp;</td>";
		} else {
			if($key != 'amount_acc_day')
				$amount = $result['currentValue'] - $result['nextNonZeroValue'];

			$amountInBillion = round($amount/100, 2);
	
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
				$color = '#f0f4f5'; //#e2ece9'; // 10억 이상
			} else {
				$color = '#ffffff'; // 10억 미만
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
				
			// if($bold)
			// 	$amountInBillion = "<b>".number_format($amountInBillion, 2). " 억</b>";
			// else
			// 	$amountInBillion = number_format($amountInBillion, 2). " 억";
		
			$tdE = "<td style='background-color:".$color."'> $amountInBillion </td>";
		}

		return $tdE;
	}


	echo "<table class='table table-sm table-bordered text-dark'>";
	echo "<tr align=center>";
	echo "<th width=60>코드</th>";
	echo "<th width=120 onclick=\"sortTable('name')\">종목명</th>";
	echo "<th width=70  onclick=\"sortTable('rate')\">등락률</th>";
	echo "<th width=110 onclick=\"sortTable('amount_acc_day')\" >당일누적</th>";
	echo "<th width=80 onclick=\"sortTable('amount_last_min')\">".substr($minute,0,2).":".substr($minute,2,2)."</th>";
	echo "<th width=80>1분전</th>";
	echo "<th width=80>2분전</th>";
	echo "<th width=80>3분전</th>";
	echo "<th width=80>4분전</th>";
	echo "<th width=80>5분전</th>";
	echo "<th width=80>6분전</th>";
	echo "<th width=80>7분전</th>";
	echo "<th width=80>8분전</th>";
	echo "<th width=80>9분전</th>";
	echo "<th width=80>10분전</th>";
	echo "<th width=80>11분전</th>";
	echo "<th width=80>12분전</th>";
	echo "</tr>";

	$query = " 
		SELECT 
			code, name, first_minute, last_minute,
			CASE WHEN (amount_last_min - amount_acc_pre_time) > 0 THEN (amount_last_min - amount_acc_pre_time) ELSE 0 END AS amount_one_min,
			amount_last_min, amount_last_1min, amount_last_2min, amount_last_3min, amount_last_4min, amount_last_5min, amount_last_6min, amount_last_7min, amount_last_8min, amount_last_9min,
			amount_last_10min,amount_last_11min,amount_last_12min,amount_before_13min, rate, amount_acc_day, amount_acc_pre_time
		FROM (
			SELECT
				s.code,
				s.name,
				MIN(minute) AS first_minute,
				MAX(minute) AS last_minute,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime THEN acc_trade_amount ELSE 0 END) AS amount_last_min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 1 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_1min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 2 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_2min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 3 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_3min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 4 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_4min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 5 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_5min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 6 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_6min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 7 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_7min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 8 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_8min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 9 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_9min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 10 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_10min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 11 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_11min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') = t.specific_datetime - INTERVAL 12 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_last_12min,
				MAX(CASE WHEN STR_TO_DATE(CONCAT(date, minute), '%Y%m%d%H%i') <= t.specific_datetime - INTERVAL 13 MINUTE THEN acc_trade_amount ELSE 0 END) AS amount_before_13min,
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
				) AS amount_acc_day, -- 주어진 시간의 가장 최근 acc_volume
				(
				SELECT m2.acc_trade_amount
				FROM kiwoom_realtime_minute m2
				WHERE m2.code = m.code
					AND STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') < t.specific_datetime
				ORDER BY STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') DESC
				LIMIT 1
				) AS amount_acc_pre_time -- 주어진 시간 이전의 가장 최근 acc_volume
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
				$where
			GROUP BY
				s.code,
				s.name
			) G
			WHERE G.amount_acc_day > 3000
			$order 
		";
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	// 데이터베이스 쿼리 결과를 배열에 저장
	$allData = [];
	$tradeAmountsByTime = []; // 시간대별 거래대금을 저장할 배열

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


	$top5ByTime = []; // 최종적으로 각 시간대별 Top 5를 저장할 배열
	$times = ['amount_acc_day','amount_last_min','amount_last_1min','amount_last_2min','amount_last_2min','amount_last_2min','amount_last_2min','amount_last_2min','amount_last_2min','amount_last_2min','amount_last_2min','amount_last_2min','amount_last_2min'];

	// $tradeAmountsByTime 배열을 사용하여 각 시간대별 Top 5 추출
	foreach ($times as $time) {
		// 해당 시간대의 키를 생성 (예: 'time_0900')
		$timeKey = "time_$time";
	
		// 해당 시간대의 데이터가 존재하는지 확인
		if (isset($tradeAmountsByTime[$timeKey])) {
			// 해당 시간대의 모든 데이터를 거래대금(amount) 기준으로 내림차순 정렬
			usort($tradeAmountsByTime[$timeKey], function ($a, $b) {
				return $b['amount'] - $a['amount'];
			});
	
			// 정렬된 배열에서 상위 5개만 선택하여 최종 배열에 저장
			$top5ByTime[$timeKey] = array_slice($tradeAmountsByTime[$timeKey], 0, 5);
		}
	}


	echo "<tr>";echo "<td colspan=3>Top5</td>";
	foreach ($top5ByTime as $timeKey => $top5) {
		echo "<td><table class='small'>";
		foreach ($top5 as $item) {
			echo "<tr>";
			echo "<td>{$item['name']}</td>"; // 종목명
			echo "</tr>";
		}
		echo "</table></td>";
	}
	echo "</tr></table>";

	$i = 0;
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		// 예시 데이터
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
			'amount_last_12min' => $row['amount_last_12min'],
			'amount_before_15min' => $row['amount_before_13min']
		];
		
		echo "<tr align=right>";
			echo "<td align=center class='small'>".$row['code']."</td>";
			echo "<td align=left class='cut-text' title=".$row['name']."><b>".$row['name']."</b></td>";
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

		$i++;
	}
	echo "</table>";

	if($theme != '') {

		$param = "pgmId=sophiaWatchlist&sector=".$sector."&theme=".$theme;
		echo "<table style='width:100%'><tr>";
		echo "<td>
				<div style='margin: 0; border: 1; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 70px);'>
					<iframe id='iframeR' scrolling='no' style='width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 70px); overflow:hidden;' src='viewChart_B.php?".$param."'>
					</iframe>
				</div>
			</td>";
		
		echo "<table><tr>";

	}
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