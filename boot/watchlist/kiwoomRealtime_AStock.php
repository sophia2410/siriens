<?php

// 종목정보
$code = isset($_GET['code']) ? $_GET['code'] : '';
$name = isset($_GET['name']) ? $_GET['name'] : ''; 

$pageTitle = "실시간거래-$name";

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
$minute = '1530';

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

?>

<body>
<form name="form1" method='POST' action='siriensEvening_script.php' onsubmit="return false">

<?php
if($specific_datetime == '') {
	echo "<h3>일자를 선택해 주세요!!</h3>";
	$ready = 'N';
}
else {

	// date 일자 형식으로 출력
	$originalDate = DateTime::createFromFormat('Ymd', $date);
	$formattedDate = $originalDate->format('Y-m-d');

	// 오늘 최저가 = 3일 이동평균이 되기 위해 종가 예측
	$query="SELECT ROUND((3 * ABS((SELECT low_price FROM kiwoom_realtime WHERE code = {$code} AND date = {$date})) - SUM(close)) / 1, 0) AS predicted_close_today
			FROM (
				SELECT close 
				FROM daily_price 
				WHERE code = {$code} AND date < {$date} 
				ORDER BY date DESC 
				LIMIT 2
			) AS last_two_days;
			";
	
	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);
	$row = $result->fetch_array(MYSQLI_BOTH);

	// 종목 / 일자 정보 화면에 표시
	echo "<div><h4> ▶ [{$code}] {$name} / {$formattedDate} / 예상 종가 : {$row['predicted_close_today']}</h4> </div>";

	// 거래대금 TD 생성
	function setAmountTdE($amount) {
		// 백만원 단위를 억단위로 변경
		if($amount == 0) {
			$tdE = "<td>&nbsp;</td>";
		} else {
			$amountInBillion = round($amount/100, 1);
	
			// 색상 지정
			if ($amountInBillion >= 150) {
				$color = '#fcb9b2'; // 150억 이상
			} elseif ($amountInBillion >= 100) {
				$color = '#ffccd5'; // 100 이상
			} elseif ($amountInBillion >= 80) {
				$color = '#f6dfeb'; // 80 이상
			} elseif ($amountInBillion >= 50) {
				$color = '#fde2e4'; // 50 이상
			} elseif ($amountInBillion >= 20) {
				$color = '#bee1e6'; // 20억 이상
			} elseif ($amountInBillion >= 10) {
				$color = '#f0f4f5'; //#e2ece9'; // 10억 이상
			} else {
				$color = '#ffffff'; // 10억 미만
			}
	
	
			$formattedNumber = number_format($amountInBillion, 1);
			$parts = explode('.', $formattedNumber);
			$whole = $parts[0];
			$fraction = $parts[1] ?? '00'; // 소수점 이하가 없는 경우 '00'으로 처리

			$rtAmount = "<span>".$whole.".<span class='small-fraction'>".$fraction."</span></span>";
			$amountInBillion =$rtAmount." 억";
		
			$tdE = "<td style='background-color:".$color."'><b>$amountInBillion</b></td>";
		}

		return $tdE;
	}

	// 시작 시간과 종료 시간 설정
	$startTime = new DateTime('09:00');
	$endTime = new DateTime('15:30');

	// 결과를 저장할 배열
	$queries = [];

	// 시작 시간부터 종료 시간까지 분단위로 순회
	while ($startTime <= $endTime) {
		// 현재 시간과 1분 전 시간을 문자열로 변환
		$currentTimeStr = $startTime->format('Hi');
		$oneMinuteBefore = (clone $startTime)->sub(new DateInterval('PT1M'))->format('Hi');
		
		// 쿼리 문자열 생성
		$query = "IFNULL(MAX(CASE WHEN minute BETWEEN '0900' AND '$currentTimeStr' THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= '$oneMinuteBefore' THEN acc_trade_amount ELSE 0 END), 0) AS time_$currentTimeStr,";
		
		// 쿼리 배열에 추가
		array_push($queries, $query);
		
		// 다음 시간으로 이동
		$startTime->add(new DateInterval('PT1M'));
	}

	// 모든 쿼리를 하나의 문자열로 결합
	$finalQuery = implode("\n", $queries);

	$query = " 
		SELECT *
		FROM (
			SELECT
				s.code, s.name,
				$finalQuery
				IFNULL(MAX(CASE WHEN minute <= '1530' THEN acc_trade_amount ELSE NULL END), 0) AS time_all
			FROM
				$tableToUse m
			JOIN
				kiwoom_stock s
			ON s.code = m.code
			JOIN 
				(SELECT STR_TO_DATE('$specific_datetime', '%Y%m%d%H%i') specific_datetime) t
			WHERE
				m.code = '$code' AND 
				m.date = DATE_FORMAT(t.specific_datetime, '%Y%m%d') AND -- Only considering today's data
				m.minute <= DATE_FORMAT(t.specific_datetime, '%H%i')
			GROUP BY
				s.code,
				s.name
			) G
		";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);

	echo "<table class='table table-sm table-bordered text-dark'>";
	echo "<tr align=center class='table-active'>";
	echo "<td width=80 rowspan=3 align=center class='h5'><b>시간</b></td>"; // 시간 표시
	echo "<th width=80>00</th>";
	echo "<th width=80>01</th>";
	echo "<th width=80>02</th>";
	echo "<th width=80>03</th>";
	echo "<th width=80>04</th>";
	echo "<th width=80>05</th>";
	echo "<th width=80>06</th>";
	echo "<th width=80>07</th>";
	echo "<th width=80>08</th>";
	echo "<th width=80>09</th>";
	echo "<th width=80>10</th>";
	echo "<th width=80>11</th>";
	echo "<th width=80>12</th>";
	echo "<th width=80>13</th>";
	echo "<th width=80>14</th>";
	echo "<th width=80>15</th>";
	echo "<th width=80>16</th>";
	echo "<th width=80>17</th>";
	echo "<th width=80>18</th>";
	echo "<th width=80>19</th>";
	echo "</tr>";
	echo "<tr align=center class='table-active'>";
	echo "<th width=80>20</th>";
	echo "<th width=80>11</th>";
	echo "<th width=80>22</th>";
	echo "<th width=80>23</th>";
	echo "<th width=80>24</th>";
	echo "<th width=80>25</th>";
	echo "<th width=80>26</th>";
	echo "<th width=80>27</th>";
	echo "<th width=80>28</th>";
	echo "<th width=80>29</th>";
	echo "<th width=80>30</th>";
	echo "<th width=80>31</th>";
	echo "<th width=80>32</th>";
	echo "<th width=80>33</th>";
	echo "<th width=80>34</th>";
	echo "<th width=80>35</th>";
	echo "<th width=80>36</th>";
	echo "<th width=80>37</th>";
	echo "<th width=80>38</th>";
	echo "<th width=80>39</th>";
	echo "</tr>";
	echo "<tr align=center class='table-active'>";
	echo "<th width=80>40</th>";
	echo "<th width=80>41</th>";
	echo "<th width=80>42</th>";
	echo "<th width=80>43</th>";
	echo "<th width=80>44</th>";
	echo "<th width=80>45</th>";
	echo "<th width=80>46</th>";
	echo "<th width=80>47</th>";
	echo "<th width=80>48</th>";
	echo "<th width=80>49</th>";
	echo "<th width=80>50</th>";
	echo "<th width=80>51</th>";
	echo "<th width=80>52</th>";
	echo "<th width=80>53</th>";
	echo "<th width=80>54</th>";
	echo "<th width=80>55</th>";
	echo "<th width=80>56</th>";
	echo "<th width=80>57</th>";
	echo "<th width=80>58</th>";
	echo "<th width=80>59</th>";
	echo "</tr>";

	$hours = ['9시','10시','11시','12시','13시','14시','15시'];

	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		
		echo "<tr align=right>";
		echo "<td rowspan=3 align=center class='h5 table-active'><b>{$hours[0]}</b></td>"; // 시간 표시

		$counter = 1; // 20분 단위로 행을 구분하기 위한 카운터

		// 시작 시간과 종료 시간 설정
		$startTime = new DateTime('09:00');
		$endTime = new DateTime('15:30');

		// 시작 시간부터 종료 시간까지 분 단위로 순회
		while ($startTime <= $endTime) {
			// 현재 시간을 'Hi' 형식으로 변환 (예: '0930')
			$currentTimeStr = $startTime->format('Hi');
		
			// 데이터 배열에서 현재 시간에 해당하는 값을 가져옴
			$colName = "time_".$currentTimeStr;
			$currentValue = isset($row[$colName]) ? $row[$colName] : '';

			// 데이터를 테이블 셀로 출력
			$amountTdE = setAmountTdE($currentValue);
			echo $amountTdE;
			// 15분마다 새로운 행을 시작
			if ($counter % 20 == 0) {
				echo "</tr><tr align=right>"; // 현재 행을 닫고 새로운 행 시작
			}

			// 15분마다 새로운 행을 시작
			if ($counter % 60 == 0) {
				$quotient = intdiv($counter, 60);
				echo "<td rowspan=3 align=center class='h5 table-active'><b>{$hours[$quotient]}</b></td>"; // 시간 표시
			}

			// 시간을 1분 증가시킴
			$startTime->modify('+1 minutes');
			$counter++;
		}

		// 마지막 행 닫기
		if ($counter % 20 == 0) {
			echo "</tr>"; // 테이블의 마지막 행 닫기
		}
	}
	echo "</table>";

	echo "<div class='container-fluid'>";
	echo "<div class='row'>";
		echo "<div class='card-body'>
				<img class='img-fluid' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{$code}.png?sidcode=1705826920773'>
			</div>";
		echo "<div class='card-body'>
				<img class='img-fluid' src='https://ssl.pstatic.net/imgfinance/chart/item/area/day/{$code}.png?sidcode=1705826920773'>
			</div>";
	echo "</div>";
	echo "</div>";
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