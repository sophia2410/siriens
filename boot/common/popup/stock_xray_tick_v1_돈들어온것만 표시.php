<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$query = "SELECT time, change_rate, volume, amount, 
                 ROUND(SUM(amount) OVER (ORDER BY time ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW),1) AS cum_amt 
          FROM (
              SELECT time, round(change_rate*100,1) change_rate, current_price, volume, round((current_price * volume)/100000000,1) amount 
              FROM kiwoom_xray_tick_executions 
              WHERE code = '".$_GET['code']."' 
              AND date = '".$_GET['date']."' 
              ORDER BY time
          ) X";
$result = $mysqli->query($query);

// 데이터를 JavaScript로 전달하기 위한 배열 생성
$times = [];
$amounts = [];
$cum_amts = [];
$change_rates = [];

while ($row = $result->fetch_assoc()) {
    $times[] = $row['time'];
    $amounts[] = $row['amount'];
    $cum_amts[] = $row['cum_amt'];
    $change_rates[] = $row['change_rate'];
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
        
        <!-- 테이블을 그대로 유지 -->
        <?php
        echo "<table class='table table-sm table-bordered text-dark'>";
        echo "<tr align=center><th>시간</th><th>등락률</th><th>거래량</th><th>거래대금</th><th>누계대금</th></tr>";
        
        // 데이터를 다시 가져와 테이블 출력
        $result = $mysqli->query($query);
        while ($row = $result->fetch_assoc()) {
            echo "<tr align=right>";
            echo "<td align=center>".$row['time']."</td>";
            echo "<td>".$row['change_rate']." %</td>";
            echo "<td>".number_format($row['volume'])."</td>";
            echo "<td>".number_format($row['amount'],1)." 억</td>";
            echo "<td>".number_format($row['cum_amt'],1)." 억</td>";
            echo "</tr>";
        }

        echo "<tr align=right>";
        echo "<td align=center>마감</td>";
        echo "<td>".end($change_rates)." %</td>";
        echo "<td>".number_format(count($amounts))." 건</td>";
        echo "<td> </td>";
        echo "<td>".number_format(end($cum_amts),1)." 억</td>";
        echo "</tr>";
        echo "</table>";
        ?>
        
        <script>
        var ctx = document.getElementById('myChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar', // 기본 차트 유형을 막대로 설정
            data: {
                labels: <?php echo json_encode($times); ?>, // X축 데이터
                datasets: [{
                    label: '거래대금 (억)',
                    type: 'bar', // 이 데이터셋의 차트 유형을 막대로 설정
                    data: <?php echo json_encode(array_map(function($value) { return $value; }, $amounts)); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)', // 막대 색상
                    borderColor: 'rgba(54, 162, 235, 1)', // 막대 테두리 색상
                    borderWidth: 1,
                    yAxisID: 'y1'
                }, {
                    label: '누적 대금 (억)',
                    type: 'line', // 이 데이터셋의 차트 유형을 선으로 설정
                    data: <?php echo json_encode($cum_amts); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)', // 선 색상
                    borderColor: 'rgba(255, 99, 132, 1)', // 선 테두리 색상
                    borderWidth: 1,
                    fill: false,
                    yAxisID: 'y2'
                }, {
                    label: '등락률 (%)',
                    type: 'line', // 이 데이터셋의 차트 유형을 선으로 설정
                    data: <?php echo json_encode($change_rates); ?>,
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
                        labels: <?php echo json_encode($times); ?>
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
</body>
</html>
