<?php
// 에러 로그 설정
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/php-error.log'); // 에러 로그 파일 경로 지정
error_reporting(E_ALL);

require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php"); // 필요한 db 연결만 유지

// 전체 시간대를 설정
$startTime = strtotime('09:00');
$endTime = strtotime('15:20');
// $interval = 60 * 5; // 5분 간격
$interval = 60; // 5분 간격

$times = [];
for ($time = $startTime; $time <= $endTime; $time += $interval) {
    $times[] = date('H:i', $time);
}

// 그래프용 시분 단위 데이터 쿼리
$query_chart = "SELECT 
                    time_interval,
                    SUM(volume) as volume,
                    SUM(amount) as amount,
                    SUM(SUM(amount)) OVER (ORDER BY time_interval ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS cum_amt,
                    ROUND(AVG(change_rate * 100), 2) as change_rate
                FROM (
                    SELECT 
                        DATE_FORMAT(STR_TO_DATE(time, '%H:%i:%s'), '%H:%i') as time_interval, 
                        change_rate, 
                        current_price, 
                        volume, 
                        ROUND((current_price * volume) / 100000000, 1) as amount 
                    FROM 
                        kiwoom_xray_tick_executions 
                    WHERE 
                        code = ? 
                        AND date = ? 
                    ORDER BY 
                        STR_TO_DATE(time, '%H:%i:%s')
                ) X
                GROUP BY 
                    time_interval
                ORDER BY 
                    time_interval";
                    
$stmt = $mysqli->prepare($query_chart);
$stmt->bind_param('ss', $_GET['code'], $_GET['date']);
$stmt->execute();
$result_chart = $stmt->get_result();

if (!$result_chart) {
    error_log("Database query failed: " . $mysqli->error);
    http_response_code(500);
    echo json_encode(["error" => "Database query failed"]);
    exit;
}

$data = [
    'times' => $times,
    'amounts' => array_fill(0, count($times), 0),
    'cum_amts' => array_fill(0, count($times), 0),
    'change_rates' => array_fill(0, count($times), 0),
    'volumes' => array_fill(0, count($times), 0)
];

$cumulativeAmount = 0;
$cumulativeRate = 0;

while ($row = $result_chart->fetch_assoc()) {
    $index = array_search($row['time_interval'], $times);
    if ($index !== false) {
        $data['amounts'][$index] = $row['amount'];
        $cumulativeAmount += $row['amount'];
        $data['cum_amts'][$index] = $cumulativeAmount;
        $cumulativeRate = $row['change_rate'];
        $data['change_rates'][$index] = $cumulativeRate;
        $data['volumes'][$index] = $row['volume'];
    }
}

// 이전 값으로 선 그래프 데이터 채우기
for ($i = 1; $i < count($data['times']); $i++) {
    if ($data['cum_amts'][$i] == 0) {
        $data['cum_amts'][$i] = $data['cum_amts'][$i - 1];
        $data['change_rates'][$i] = $data['change_rates'][$i - 1];
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>
