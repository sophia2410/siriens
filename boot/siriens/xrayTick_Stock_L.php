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
$code = (isset($_GET['code'])) ? $_GET['code'] : '';
$name = (isset($_GET['name'])) ? $_GET['name'] : '';
?>

<body>
<form name="form1" method='POST' action='siriensEvening_script.php' onsubmit="return false">

<?php
if($code == '') {
	echo "<h3>종목명 입력 후 엔터</h3>";
	$ready = 'N';
}
else {
	echo "<table class='table table-sm text-dark'>";
	// 차트 -- 네이버이미지
		echo "<tr><td style='width: 700px;' colspan=2>";
		echo "<h4><b>$name</b><h4></td></tr>";
		echo "<tr>";
		echo "<td>일봉<br><img id='img_chart_area_day' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$code.".png?sidcode=1681518352718' width='100%' alt='일봉'></td>";
		echo "<td>주봉<br><img id='img_chart_area_week' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/week/".$code.".png?sidcode=1681518352718' width='100%' alt='주봉'></td>";
		echo "</tr>";
	echo "</table>";

		//X-RAY 순간체결 거래량
		$query = "	SELECT cal.date, SUBSTR(STR_TO_DATE(cal.date, '%Y%m%d'),6) date_str, xray.close_rate, xray.close_amt, xray.tot_trade_amt, xray.amount, xray.cnt
					FROM calendar cal
					LEFT OUTER JOIN 
						(
							SELECT xr.code, xr.name, xr.date, dp.close_rate close_rate, dp.close close_amt, round(dp.amount/100000000,0) tot_trade_amt, round(xr.tot_amt/100000000,1) amount, xr.tot_cnt cnt
							FROM kiwoom_xray_tick_summary xr
							LEFT OUTER JOIN daily_price dp
							ON dp.date = xr.date
							AND dp.code = xr.code
							WHERE xr.code = '$code'
						) xray
					ON xray.date = cal.date
					WHERE cal.date >= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL -5 MONTH), '%Y%m%d')))
					AND cal.date <= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL 0 DAY), '%Y%m%d')))
					AND cal.date >= '20240226'
					ORDER BY cal.date desc
					";

		// 데이터베이스 쿼리 실행 및 결과 저장
		$result = $mysqli->query($query);

		// 주차별 데이터 배열 초기화
		$weekly_data = [];

		// 데이터 분류
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			$date = $row['date'];
			$day_of_week = date('D', strtotime($date));  // 요일 추출 (Mon, Tue, ...)
			$week_number = date('W', strtotime($date));  // 주 번호 추출

			if (!isset($weekly_data[$week_number])) {
				$weekly_data[$week_number] = [
					'Mon' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
					'Tue' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
					'Wed' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
					'Thu' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
					'Fri' => ['date' => '', 'close_rate' => '', 'close_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
				];
			}
			// 요일별 데이터 저장
			if (isset($weekly_data[$week_number][$day_of_week])) {
				$weekly_data[$week_number][$day_of_week]['date'] = "<td align=center width=90  style='background-color:#fae4f1;'><b><a href=\"javascript:callFrameR('".$date."')\">". $row['date_str']."</a></b></td>";
				if($row['cnt'] > 0) {
					// 등락률 따라 스타일 적용
					if($row['close_rate'] > 29.5)
						$rate_style = "class='text-danger font-weight-bold'";
					else if($row['close_rate'] > 15)
						$rate_style = "class='text-danger'";
					else
						$rate_style = "";

					// 총 거래대금에 따라 스타일 적용
					if($row['tot_trade_amt'] >= 1000)
						$tot_amt_style = "style='color:#fcb9b2;' class='font-weight-bold'";
					else if($row['tot_trade_amt'] >= 500)
						$tot_amt_style = "style='color:#ffccd5;' class='font-weight-bold'";
					else if($row['tot_trade_amt'] < 9)
						$tot_amt_style = "class='small'";
					else
						$tot_amt_style = "";

					// xray 거래대금에 따라 스타일 적용
					if($row['amount'] >= 500)
						$amt_style = "mark text-danger font-weight-bold h6";
					else if($row['amount'] >= 100)
						$amt_style = "text-danger font-weight-bold h6";
					else if($row['amount'] < 9)
						$amt_style = "small";
					else
						$amt_style = "font-weight-bold";

					$weekly_data[$week_number][$day_of_week]['close_rate'] = "<td align=center $rate_style>". $row['close_rate']."%</td>";
					$weekly_data[$week_number][$day_of_week]['tot_trade_amt'] = "<td align=center $tot_amt_style>". number_format($row['tot_trade_amt'])."</td>";
					$weekly_data[$week_number][$day_of_week]['close_amt'] = "<td align=center>". number_format($row['close_amt'])."</td>";
					$weekly_data[$week_number][$day_of_week]['cnt'] = "<td align=right>". number_format($row['cnt'])."</td>";
					$weekly_data[$week_number][$day_of_week]['amount'] = "<td align=right class='{$amt_style}'>". number_format($row['amount'])."</td>";
				} else {
					$weekly_data[$week_number][$day_of_week]['close_rate'] = "<td align=center>&nbsp;</td>";
					$weekly_data[$week_number][$day_of_week]['tot_trade_amt'] = "<td align=center>&nbsp;</td>";
					$weekly_data[$week_number][$day_of_week]['close_amt'] = "<td align=center>&nbsp;</td>";
					$weekly_data[$week_number][$day_of_week]['cnt'] = "<td align=center>&nbsp;</td>";
					$weekly_data[$week_number][$day_of_week]['amount'] = "<td align=center>&nbsp;</td>";
				}
			}
		}

		// 주차별 데이터 내림차순 정렬 (가장 최근 주가 위로)
		krsort($weekly_data);

		// 주 데이터를 세 개씩 묶어서 처리
		$paired_weeks = array_chunk($weekly_data, 3, true);

		// HTML 출력
		echo "<table class='table table-sm table-bordered text-dark'>";
		foreach ($paired_weeks as $pair) {
			echo "<tr>";  // 날짜 헤더 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['date'] ?? "<th></th>";
				}
			}
			echo "</tr><tr>";  // 종가 비율 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['close_rate'] ?? "<td></td>";
				}
			}
			echo "</tr><tr>";  // 총 거래대금 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['tot_trade_amt'] ?? "<td></td>";
				}
			}
			echo "</tr><tr>";  // 종가
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['close_amt'] ?? "<td></td>";
				}
			}
			// echo "</tr><tr>";  // 거래량 행
			// foreach ($pair as $week => $data) {
			// 	foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
			// 		echo $data[$day]['cnt'] ?? "<td></td>";
			// 	}
			// }
			echo "</tr><tr>";  // 거래대금 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['amount'] ?? "<td></td>";
				}
			}
			echo "</tr>";
		}
		echo "</table>";


	// 일자별 리스트 출력 방식 ----- 향후 변경 고려.

	// // 종목 / 일자 정보 표시
	// echo "<div><h4> ▶ [$code] $name </h4></div>";

	// // 함수 :: 거래대금 TD 생성 
	// function setAmountTdE($array, $key, $bold='N', $sizeUp='N') {
		
	// 	// 키의 현재 위치 찾기
	// 	$keys = array_keys($array);
	// 	$position = array_search($key, $keys);
	
	// 	// 현재 키의 값
	// 	$amount = isset($array[$key]) ? $array[$key] : 0;

	// 	// 백만원 단위를 억단위로 변경
	// 	if($amount == 0) {
	// 		$tdE = "<td>&nbsp;</td>";
	// 	} else {
	// 		$amountInBillion = round($amount/100, 1);
	
	// 		// 색상 지정
	// 		if ($amountInBillion >= 1000) {
	// 			$color = '#fcb9b2'; // 10000억 이상
	// 		} elseif ($amountInBillion >= 500) {
	// 			$color = '#ffccd5'; // 500억 이상
	// 		} elseif ($amountInBillion >= 250) {
	// 			$color = '#f6dfeb'; // 250억 이상
	// 		} elseif ($amountInBillion >= 100) {
	// 			$color = '#fde2e4'; // 100억 이상
	// 		} elseif ($amountInBillion >= 50) {
	// 			$color = '#bee1e6'; // 50억 이상
	// 		} elseif ($amountInBillion >= 30) {
	// 			$color = '#f0f4f5'; //#e2ece9'; // 30억 이상
	// 		} else {
	// 			$color = '#ffffff'; // 30억 미만
	// 		}
	
	// 		if($sizeUp)
	// 			$h5 = " class='h5'";
	// 		else
	// 			$h5 = "";
	
	// 		$formattedNumber = number_format($amountInBillion, 1);
	// 		$parts = explode('.', $formattedNumber);
	// 		$whole = $parts[0];
	// 		$fraction = $parts[1] ?? '00'; // 소수점 이하가 없는 경우 '00'으로 처리

	// 		$rtAmount = "<span>".$whole.".<span class='small-fraction'>".$fraction."</span></span>";

	// 		if($bold)
	// 			$amountInBillion = "<b>".$rtAmount." 억</b>";
	// 		else
	// 			$amountInBillion =$rtAmount." 억";
		
	// 		$tdE = "<td style='background-color:".$color."'> $amountInBillion </td>";
	// 	}

	// 	return $tdE;
	// }
	// $query = " 
	// 	SELECT 
	// 		c.date,
	// 		STR_TO_DATE(c.date, '%Y%m%d') dateStr,
	// 		CASE DAYOFWEEK(STR_TO_DATE(c.date, '%Y%m%d'))
	// 			WHEN 1 THEN '일'
	// 			WHEN 2 THEN '월'
	// 			WHEN 3 THEN '화'
	// 			WHEN 4 THEN '수'
	// 			WHEN 5 THEN '목'
	// 			WHEN 6 THEN '금'
	// 			WHEN 7 THEN '토' END AS day,
	// 		(SELECT close_rate FROM daily_price p WHERE p.date = c.date AND p.code = '$code') close_rate,
	// 		time_0900,time_0930,time_1000,time_1030,time_1100,time_1130,
	// 		time_1200,time_1230,time_1300,time_1330,time_1400,time_1430,time_1500,time_all
	// 	FROM calendar c
	// 	LEFT OUTER JOIN
	// 	(
	// 		SELECT date,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '0900' AND '0929' THEN amount ELSE NULL END), 0) AS time_0900,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '0930' AND '0959' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '0929' THEN amount ELSE 0 END), 0) AS time_0930,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1000' AND '1029' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '0959' THEN amount ELSE 0 END), 0) AS time_1000,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1030' AND '1059' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1029' THEN amount ELSE 0 END), 0) AS time_1030,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1100' AND '1129' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1059' THEN amount ELSE 0 END), 0) AS time_1100,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1130' AND '1159' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1129' THEN amount ELSE 0 END), 0) AS time_1130,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1200' AND '1229' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1159' THEN amount ELSE 0 END), 0) AS time_1200,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1230' AND '1259' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1229' THEN amount ELSE 0 END), 0) AS time_1230,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1300' AND '1329' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1259' THEN amount ELSE 0 END), 0) AS time_1300,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1330' AND '1359' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1329' THEN amount ELSE 0 END), 0) AS time_1330,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1400' AND '1429' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1359' THEN amount ELSE 0 END), 0) AS time_1400,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1430' AND '1459' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1429' THEN amount ELSE 0 END), 0) AS time_1430,
	// 			IFNULL(MAX(CASE WHEN minute BETWEEN '1500' AND '1530' THEN amount ELSE NULL END) - MAX(CASE WHEN minute <= '1459' THEN amount ELSE 0 END), 0) AS time_1500,
	// 			IFNULL(MAX(CASE WHEN minute <= '1530' THEN acc_trade_amount ELSE NULL END), 0) AS time_all
	// 		FROM
	// 		(
	// 			SELECT 
	// 				date, SUBSTR(REPLACE(time, ':',''),1,4) minute, code, name, round(change_rate*100,1) change_rate, current_price, volume, round((current_price * volume)/10000,0) amount
	// 			FROM
	// 				kiwoom_xray_tick_executions
	// 			WHERE 
	// 				date <= '$date' AND
	// 				date >= (SELECT DATE_FORMAT(DATE_ADD('$date', INTERVAL -2 MONTH), '%Y%m%d')) AND
	// 				code = '$code'
	// 		) m
	// 		GROUP BY
	// 			m.date
	// 	) g
	// 	ON
	// 		g.date = c.date
	// 	WHERE 
	// 		c.date <= '$date' AND
	// 		c.date >= (SELECT DATE_FORMAT(DATE_ADD('$date', INTERVAL -1 MONTH), '%Y%m%d'))
	// 	ORDER BY
	// 		c.date DESC
	// 	";

	// // echo "<pre>$query</pre>";
	// $result = $mysqli->query($query);

	// echo "<table class='table table-sm table-bordered text-dark'>";
	// echo "<tr align=center>";
	// echo "<th width=120>일자</th>";
	// echo "<th width=60>요일</th>";
	// echo "<th width=60>등락률</th>";
	// echo "<th width=110>당일누적</th>";
	// echo "<th width=80>09:00</th>";
	// echo "<th width=80>09:30</th>";
	// echo "<th width=80>10:00</th>";
	// echo "<th width=80>10:30</th>";
	// echo "<th width=80>11:00</th>";
	// echo "<th width=80>11:30</th>";
	// echo "<th width=80>12:00</th>";
	// echo "<th width=80>12:30</th>";
	// echo "<th width=80>13:00</th>";
	// echo "<th width=80>13:30</th>";
	// echo "<th width=80>14:00</th>";
	// echo "<th width=80>14:30</th>";
	// echo "<th width=80>15:00</th>";
	// echo "</tr>";

	// // 나머지 데이터 출력
	// while ($row = $result->fetch_array(MYSQLI_BOTH)) {
	// 	// TD 생성을 위해 거래대금 배열에 담기
	// 	$amounts = [
	// 		'amount_time_all'   => $row['time_all'],
	// 		'amount_time_0900'  => $row['time_0900'],
	// 		'amount_time_0930'  => $row['time_0930'],
	// 		'amount_time_1000'  => $row['time_1000'],
	// 		'amount_time_1030'  => $row['time_1030'],
	// 		'amount_time_1100'  => $row['time_1100'],
	// 		'amount_time_1130'  => $row['time_1130'],
	// 		'amount_time_1200'  => $row['time_1200'],
	// 		'amount_time_1230'  => $row['time_1230'],
	// 		'amount_time_1300'  => $row['time_1300'],
	// 		'amount_time_1330'  => $row['time_1330'],
	// 		'amount_time_1400'  => $row['time_1400'],
	// 		'amount_time_1430'  => $row['time_1430'],
	// 		'amount_time_1500'  => $row['time_1500'],
	// 	];
		
	// 	echo "<tr align=right>";
	// 		echo "<td align=center>";
	// 		echo "<a href='kiwoomRealtime_AStock.php?code={$code}&name={$name}&date={$row['date']}' onclick='window.open(this.href, \'realtime_stock\', 'width=2500px,height=850,scrollbars=1,resizable=yes');return false;' target='_blank'>";
	// 		echo "<b>".$row['dateStr']."</b></a></td>";
	// 		echo "<td align=center><b>".$row['day']."</b></td>";
	// 		echo "<td><b>".$row['close_rate']."</b>%</td>";
			
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_all', 'Y', 'Y');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_0900');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_0930');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1000');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1030');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1100');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1130');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1200');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1230');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1300');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1330');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1400');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1430');
	// 		echo $amountTdE;
	// 		$amountTdE = setAmountTdE($amounts, 'amount_time_1500');
	// 		echo $amountTdE;
	// 	echo "</tr>";
	// }

	// echo "</table>";
}
?>
</form>
<script>
function callFrameR(date) {
	window.parent.viewDetail(date);
}
</script>
</body>
</html>