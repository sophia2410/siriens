<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

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
                        code = '".$_GET['code']."' 
                        AND date = '".$_GET['date']."' 
                    ORDER BY 
                        STR_TO_DATE(time, '%H:%i:%s')
                ) X
                GROUP BY 
                    time_interval
                ORDER BY 
                    time_interval";
$result_chart = $mysqli->query($query_chart);

// 데이터를 JavaScript로 전달하기 위한 배열 생성
$times_chart = [];
$amounts_chart = [];
$cum_amts_chart = [];
$change_rates_chart = [];
$volumes_chart = [];

while ($row = $result_chart->fetch_assoc()) {
    $times_chart[] = $row['time_interval'];
    $amounts_chart[] = $row['amount'];
    $cum_amts_chart[] = $row['cum_amt'];
    $change_rates_chart[] = $row['change_rate'];
    $volumes_chart[] = $row['volume'];
}

// 모든 시간대를 9시에서 15:20분까지 생성
$all_times = [];
for ($h = 9; $h <= 15; $h++) {
    for ($m = 0; $m < 60; $m++) {
        if ($h === 15 && $m > 20) {
            break;
        }
        $time = sprintf('%02d:%02d', $h, $m);
        $all_times[] = $time;
    }
}

// 고정된 시간 범위에 데이터 매핑
$fixed_amounts_chart = [];
$fixed_cum_amts_chart = [];
$fixed_change_rates_chart = [];
$fixed_volumes_chart = [];
$last_cum_amt = 0;
$last_change_rate = 0;

foreach ($all_times as $time) {
    $index = array_search($time, $times_chart);
    if ($index !== false) {
        $fixed_amounts_chart[] = $amounts_chart[$index];
        $fixed_cum_amts_chart[] = $cum_amts_chart[$index];
        $fixed_change_rates_chart[] = $change_rates_chart[$index];
        $fixed_volumes_chart[] = $volumes_chart[$index];
        $last_cum_amt = $cum_amts_chart[$index];
        $last_change_rate = $change_rates_chart[$index];
    } else {
        $fixed_amounts_chart[] = 0;
        $fixed_cum_amts_chart[] = $last_cum_amt;
        $fixed_change_rates_chart[] = $last_change_rate;
        $fixed_volumes_chart[] = 0;
    }
}

// 테이블용 초 단위 데이터 쿼리
$query_table = "SELECT time, change_rate, volume, amount, 
                 ROUND(SUM(amount) OVER (ORDER BY time ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW),1) AS cum_amt 
          FROM (
              SELECT time, round(change_rate*100,1) change_rate, current_price, volume, round((current_price * volume)/100000000,1) amount 
              FROM kiwoom_xray_tick_executions 
              WHERE code = '".$_GET['code']."' 
              AND date = '".$_GET['date']."' 
              ORDER BY time
          ) X";
$result_table = $mysqli->query($query_table);

$times_table = [];
$amounts_table = [];
$cum_amts_table = [];
$change_rates_table = [];
$volumes_table = [];

while ($row = $result_table->fetch_assoc()) {
    $times_table[] = $row['time'];
    $amounts_table[] = $row['amount'];
    $cum_amts_table[] = $row['cum_amt'];
    $change_rates_table[] = $row['change_rate'];
    $volumes_table[] = $row['volume'];
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
        <canvas id="myChart" width="600" height="250"></canvas>
        
        <script>
        var ctx = document.getElementById('myChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar', // 기본 차트 유형을 막대로 설정
            data: {
                labels: <?php echo json_encode($all_times); ?>, // X축 데이터
                datasets: [{
                    label: '거래대금 (억)',
                    type: 'bar', // 이 데이터셋의 차트 유형을 막대로 설정
                    data: <?php echo json_encode($fixed_amounts_chart); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)', // 막대 색상
                    borderColor: 'rgba(54, 162, 235, 1)', // 막대 테두리 색상
                    borderWidth: 1,
                    yAxisID: 'y1'
                }, {
                    label: '누적 대금 (억)',
                    type: 'line', // 이 데이터셋의 차트 유형을 선으로 설정
                    data: <?php echo json_encode($fixed_cum_amts_chart); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)', // 선 색상
                    borderColor: 'rgba(255, 99, 132, 1)', // 선 테두리 색상
                    borderWidth: 1,
                    fill: false,
                    yAxisID: 'y2'
                }, {
                    label: '등락률 (%)',
                    type: 'line', // 이 데이터셋의 차트 유형을 선으로 설정
                    data: <?php echo json_encode($fixed_change_rates_chart); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)', // 선 색상
                    borderColor: 'rgba(75, 192, 192, 1)', // 선 테두리 색상
                    borderWidth: 1,
                    fill: false,
                    yAxisID: 'y3'
                }]
            },
            options: {
                scales: {
                    x: {
                        type: 'category',
                        labels: <?php echo json_encode($all_times); ?>
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
                        beginAtZero: false,
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
                        beginAtZero: false,
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
    
    <!-- 테이블을 그대로 유지 -->
    <div>
        <?php
        echo "<table class='table table-sm table-bordered text-dark'>";
        echo "<tr align=center><th>시간</th><th>등락률</th><th>거래량</th><th>거래대금</th><th>누계대금</th></tr>";
        
        // 데이터를 다시 가져와 테이블 출력
        for ($i = 0; $i < count($times_table); $i++) {
            echo "<tr align=right>";
            echo "<td align=center>".$times_table[$i]."</td>";
            echo "<td>".$change_rates_table[$i]." %</td>";
            echo "<td>".number_format($volumes_table[$i])."</td>";
            echo "<td>".number_format($amounts_table[$i],1)." 억</td>";
            echo "<td>".number_format($cum_amts_table[$i],1)." 억</td>";
            echo "</tr>";
        }

        echo "<tr align=right>";
        echo "<td align=center>마감</td>";
        echo "<td>".end($change_rates_table)." %</td>";
        echo "<td>".number_format(count($amounts_table))." 건</td>";
        echo "<td> </td>";
        echo "<td>".number_format(end($cum_amts_table),1)." 억</td>";
        echo "</tr>";
        echo "</table>";
        ?>
    </div>
</body>
</html>
