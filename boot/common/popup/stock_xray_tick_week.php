<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// 조회일로부터 일주일 전 날짜 계산
$today = $_GET['date']; // 예시: '20240628'
$date_start = date('Ymd', strtotime('-6 days', strtotime($today))); // 일주일 전부터 조회일까지
$today = date('Ymd', strtotime($today));

// 그래프용 데이터 쿼리 (10분 단위로 그룹화)
$query_chart = "SELECT 
                    date,
                    DATE_FORMAT(STR_TO_DATE(time, '%H:%i:%s'), '%H:%i') as time_interval,
                    FLOOR(MINUTE(STR_TO_DATE(time, '%H:%i:%s')) / 10) as time_group,
                    SUM(volume) as volume,
                    SUM(amount) as amount,
                    ROUND(AVG(change_rate * 100), 2) as change_rate
                FROM (
                    SELECT 
                        date,
                        time,
                        change_rate, 
                        current_price, 
                        volume, 
                        ROUND((current_price * volume) / 100000000, 1) as amount 
                    FROM 
                        kiwoom_xray_tick_executions 
                    WHERE 
                        code = '".$_GET['code']."' 
                        AND STR_TO_DATE(date, '%Y%m%d') BETWEEN STR_TO_DATE('".$date_start."', '%Y%m%d') AND STR_TO_DATE('".$today."', '%Y%m%d')
                    ORDER BY 
                        date, STR_TO_DATE(time, '%H:%i:%s')
                ) X
                GROUP BY 
                    date, time_interval, time_group
                ORDER BY 
                    date, time_interval, time_group";
$result_chart = $mysqli->query($query_chart);

// 데이터를 JavaScript로 전달하기 위한 배열 생성
$dates_chart = [];
$times_chart = [];
$amounts_chart = [];
$change_rates_chart = [];
$volumes_chart = [];

while ($row = $result_chart->fetch_assoc()) {
    $dates_chart[] = $row['date'];
    $times_chart[] = $row['date'] . ' ' . $row['time_interval'] . ':' . ($row['time_group'] * 10);
    $amounts_chart[] = $row['amount'];
    $change_rates_chart[] = $row['change_rate'];
    $volumes_chart[] = $row['volume'];
}

// 날짜별 시간대를 생성
$all_times = [];
for ($d = 6; $d >= 0; $d--) {
    $date = date('Ymd', strtotime('-'.$d.' days', strtotime($today)));
    for ($h = 9; $h <= 15; $h++) {
        for ($m = 0; $m < 60; $m += 10) {
            if ($h === 15 && $m > 20) {
                break;
            }
            $time = sprintf('%s %02d:%02d', $date, $h, $m);
            $all_times[] = $time;
        }
    }
}

// 날짜와 시간으로 데이터 매핑
$fixed_data = [
    'amounts' => array_fill(0, count($all_times), 0),
    'cum_amts' => array_fill(0, count($all_times), 0),
    'change_rates' => array_fill(0, count($all_times), 0),
    'volumes' => array_fill(0, count($all_times), 0),
];

$last_cum_amt = 0;
$last_change_rate = 0;
$current_date = '';

foreach ($all_times as $index => $time) {
    $time_parts = explode(' ', $time);
    $date = $time_parts[0];
    $time_only = $time_parts[1];
    $time_with_group = $date . ' ' . $time_only;

    $data_index = array_search($time_with_group, $times_chart);

    if ($data_index !== false && $dates_chart[$data_index] === $date) {
        $fixed_data['amounts'][$index] = $amounts_chart[$data_index];
        $fixed_data['cum_amts'][$index] = $last_cum_amt + $amounts_chart[$data_index];
        $fixed_data['change_rates'][$index] = $change_rates_chart[$data_index];
        $fixed_data['volumes'][$index] = $volumes_chart[$data_index];

        $last_cum_amt = $fixed_data['cum_amts'][$index];
        $last_change_rate = $fixed_data['change_rates'][$index];
    } else {
        $fixed_data['amounts'][$index] = 0;
        $fixed_data['cum_amts'][$index] = $last_cum_amt;
        $fixed_data['change_rates'][$index] = $last_change_rate;
        $fixed_data['volumes'][$index] = 0;
    }

    if ($current_date !== $date) {
        $last_cum_amt = 0;
        $last_change_rate = 0;
        $current_date = $date;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Combined Chart</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body id="page-top">
    <div id="content">
        <!-- 그래프를 상단에 배치 -->
        <canvas id="myChart" width="1200" height="600"></canvas>
        
        <script>
        var ctx = document.getElementById('myChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar', // 기본 차트 유형을 막대로 설정
            data: {
                labels: <?php echo json_encode($all_times); ?>, // X축 데이터
                datasets: [{
                    label: '거래대금 (억)',
                    type: 'bar',
                    data: <?php echo json_encode($fixed_data['amounts']); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }, {
                    label: '누적 거래대금 (억)',
                    type: 'line',
                    data: <?php echo json_encode($fixed_data['cum_amts']); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    fill: false,
                    yAxisID: 'y2'
                }, {
                    label: '등락률 (%)',
                    type: 'line',
                    data: <?php echo json_encode($fixed_data['change_rates']); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    fill: false,
                    yAxisID: 'y3'
                }]
            },
            options: {
                scales: {
                    x: {
                        type: 'category',
                        labels: <?php echo json_encode($all_times); ?>,
                        ticks: {
                            maxRotation: 90,
                            minRotation: 45,
                            callback: function(value, index, values) {
                                if (typeof value === 'string') {
                                    var parts = value.split(' ');
                                    var date = parts[0];
                                    var time = parts[1];
                                    return time === '09:00' ? date : time;
                                }
                                return value;
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' 억';
                            }
                        }
                    },
                    y2: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' 억';
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    },
                    y3: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' %';
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        </script>
    </div>
</body>
</html>
