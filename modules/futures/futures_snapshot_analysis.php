<?php
// 파일명: futures_snapshot_analysis.php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
$date = $_GET['date'] ?? date('Y-m-d');
$minute = $_GET['minute'] ?? null;

// 이전일 / 다음일 조회
$stmt_prev = $mysqli->prepare("SELECT MAX(date) FROM calendar WHERE date < ?");
$stmt_prev->bind_param('s', $date);
$stmt_prev->execute();
$stmt_prev->bind_result($prev_date);
$stmt_prev->fetch();
$stmt_prev->close();

$stmt_next = $mysqli->prepare("SELECT MIN(date) FROM calendar WHERE date > ?");
$stmt_next->bind_param('s', $date);
$stmt_next->execute();
$stmt_next->bind_result($next_date);
$stmt_next->fetch();
$stmt_next->close();

$sql = "SELECT *
        FROM futures_snapshot_momentum 
        WHERE date = ? ORDER BY datetime ASC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

$labels = [];
$prices = [];
$volumes = [];
$sma1_5 = [];
$dist_pct1_5 = [];
$dist_pt1_5 = [];
$pos1_5 = [];
$sma1_20 = [];
$dist_pct1_20 = [];
$dist_pt1_20 = [];
$pos1_20 = [];
$sma1_120 = [];
$dist_pct1_120 = [];
$dist_pt1_120 = [];
$pos1_120 = [];
$sma5_5 = [];
$dist_pct5_5 = [];
$dist_pt5_5 = [];
$pos5_5 = [];
$sma5_20 = [];
$dist_pct5_20 = [];
$dist_pt5_20 = [];
$pos5_20 = [];
$sma5_120 = [];
$dist_pct5_120 = [];
$dist_pt5_120 = [];
$pos5_120 = [];
$sma10_5 = [];
$dist_pct10_5 = [];
$dist_pt10_5 = [];
$pos10_5 = [];
$sma10_20 = [];
$dist_pct10_20 = [];
$dist_pt10_20 = [];
$pos10_20 = [];
$sma10_120 = [];
$dist_pct10_120 = [];
$dist_pt10_120 = [];
$pos10_120 = [];
$sma15_5 = [];
$dist_pct15_5 = [];
$dist_pt15_5 = [];
$pos15_5 = [];
$sma15_20 = [];
$dist_pct15_20 = [];
$dist_pt15_20 = [];
$pos15_20 = [];
$sma15_120 = [];
$dist_pct15_120 = [];
$dist_pt15_120 = [];
$pos15_120 = [];
$sma60_5 = [];
$dist_pct60_5 = [];
$dist_pt60_5 = [];
$pos60_5 = [];
$sma60_20 = [];
$dist_pct60_20 = [];
$dist_pt60_20 = [];
$pos60_20 = [];
$sma60_120 = [];
$dist_pct60_120 = [];
$dist_pt60_120 = [];
$pos60_120 = [];
$sma_1day_5 = [];
$dist_pct_1day_5 = [];
$dist_pt_1day_5 = [];
$pos_1day_5 = [];
$sma_1day_20 = [];
$dist_pct_1day_20 = [];
$dist_pt_1day_20 = [];
$pos_1day_20 = [];
$sma_1day_120 = [];
$dist_pct_1day_120 = [];
$dist_pt_1day_120 = [];
$pos_1day_120 = [];

if ($minute) {
  $rows = array_filter($rows, function($row) use ($minute) {
    return substr($row['time'], 0, 5) <= $minute;
  });
}

foreach ($rows as $row) {
  $labels[] = substr($row['time'], 0, 5);
  $prices[] = $row['price'];
  $volumes[] = $row['volume'];

  $sma1_5[]           = $row['sma_1min_5'];         
  $dist_pct1_5[]      = $row['dist_pct_1min_5'];    
  $dist_pt1_5[]       = $row['dist_pt_1min_5'];     
  $pos1_5[]           = $row['pos_1min_5'];         
  $sma1_20[]          = $row['sma_1min_20'];        
  $dist_pct1_20[]     = $row['dist_pct_1min_20'];   
  $dist_pt1_20[]      = $row['dist_pt_1min_20'];    
  $pos1_20[]          = $row['pos_1min_20'];        
  $sma1_120[]         = $row['sma_1min_120'];       
  $dist_pct1_120[]    = $row['dist_pct_1min_120'];  
  $dist_pt1_120[]     = $row['dist_pt_1min_120'];   
  $pos1_120[]         = $row['pos_1min_120'];       
  $sma5_5[]           = $row['sma_5min_5'];         
  $dist_pct5_5[]      = $row['dist_pct_5min_5'];    
  $dist_pt5_5[]       = $row['dist_pt_5min_5'];     
  $pos5_5[]           = $row['pos_5min_5'];         
  $sma5_20[]          = $row['sma_5min_20'];        
  $dist_pct5_20[]     = $row['dist_pct_5min_20'];   
  $dist_pt5_20[]      = $row['dist_pt_5min_20'];    
  $pos5_20[]          = $row['pos_5min_20'];        
  $sma5_120[]         = $row['sma_5min_120'];       
  $dist_pct5_120[]    = $row['dist_pct_5min_120'];  
  $dist_pt5_120[]     = $row['dist_pt_5min_120'];   
  $pos5_120[]         = $row['pos_5min_120'];       
  $sma10_5[]          = $row['sma_10min_5'];        
  $dist_pct10_5[]     = $row['dist_pct_10min_5'];   
  $dist_pt10_5[]      = $row['dist_pt_10min_5'];    
  $pos10_5[]          = $row['pos_10min_5'];        
  $sma10_20[]         = $row['sma_10min_20'];       
  $dist_pct10_20[]    = $row['dist_pct_10min_20'];  
  $dist_pt10_20[]     = $row['dist_pt_10min_20'];   
  $pos10_20[]         = $row['pos_10min_20'];       
  $sma10_120[]        = $row['sma_10min_120'];      
  $dist_pct10_120[]   = $row['dist_pct_10min_120']; 
  $dist_pt10_120[]    = $row['dist_pt_10min_120'];  
  $pos10_120[]        = $row['pos_10min_120'];      
  $sma15_5[]          = $row['sma_15min_5'];        
  $dist_pct15_5[]     = $row['dist_pct_15min_5'];   
  $dist_pt15_5[]      = $row['dist_pt_15min_5'];    
  $pos15_5[]          = $row['pos_15min_5'];        
  $sma15_20[]         = $row['sma_15min_20'];       
  $dist_pct15_20[]    = $row['dist_pct_15min_20'];  
  $dist_pt15_20[]     = $row['dist_pt_15min_20'];   
  $pos15_20[]         = $row['pos_15min_20'];       
  $sma15_120[]        = $row['sma_15min_120'];      
  $dist_pct15_120[]   = $row['dist_pct_15min_120']; 
  $dist_pt15_120[]    = $row['dist_pt_15min_120'];  
  $pos15_120[]        = $row['pos_15min_120'];      
  $sma60_5[]          = $row['sma_60min_5'];        
  $dist_pct60_5[]     = $row['dist_pct_60min_5'];   
  $dist_pt60_5[]      = $row['dist_pt_60min_5'];    
  $pos60_5[]          = $row['pos_60min_5'];        
  $sma60_20[]         = $row['sma_60min_20'];       
  $dist_pct60_20[]    = $row['dist_pct_60min_20'];  
  $dist_pt60_20[]     = $row['dist_pt_60min_20'];   
  $pos60_20[]         = $row['pos_60min_20'];       
  $sma60_120[]        = $row['sma_60min_120'];      
  $dist_pct60_120[]   = $row['dist_pct_60min_120']; 
  $dist_pt60_120[]    = $row['dist_pt_60min_120'];  
  $pos60_120[]        = $row['pos_60min_120'];      
  $sma1day_5[]        = $row['sma_1day_5'];         
  $dist_pct_1day_5[]  = $row['dist_pct_1day_5'];    
  $dist_pt_1day_5[]   = $row['dist_pt_1day_5'];     
  $pos1day_5[]        = $row['pos_1day_5'];         
  $sma1day_20[]       = $row['sma_1day_20'];        
  $dist_pct_1day_20[] = $row['dist_pct_1day_20'];   
  $dist_pt_1day_20[]  = $row['dist_pt_1day_20'];    
  $pos1day_20[]       = $row['pos_1day_20'];        
  $sma1day_120[]      = $row['sma_1day_120'];       
  $dist_pct_1day_120[]= $row['dist_pct_1day_120'];  
  $dist_pt_1day_120[] = $row['dist_pt_1day_120'];   
  $pos1day_120[]      = $row['pos_1day_120'];       
}

$chart_data = [
  'labels' => $labels,
  'prices' => $prices,
  'sma1_5' => $sma1_5,
  'sma5_5' => $sma5_5,
  'sma5_20' => $sma5_20,
  'sma10_5' => $sma10_5,
  'sma15_5' => $sma15_5,
  'sma60_5' => $sma60_5,
  'dist_pt1_5' => $dist_pt1_5,
  'dist_pt5_5' => $dist_pt5_5,
  'dist_pt10_5' => $dist_pt10_5,
  'dist_pt15_5' => $dist_pt15_5,
  'dist_pt60_5' => $dist_pt60_5,
  'volume' => $volumes
];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>이평선 상회 분석</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: sans-serif; font-size: 14px; }
    .summary { margin: 20px 0; }
    canvas { margin-bottom: 40px; }
  </style>
</head>
<body>
  <h2>📈 KOSPI 200 선물 이평선 분석 (<?= htmlspecialchars($date) ?>)</h2>
  <form id="dateForm" method="get">
    날짜: <input type="date" name="date" id="dateInput" value="<?= htmlspecialchars($date) ?>">
    시간: <input type="time" name="minute" id="minuteInput" value="<?= htmlspecialchars($minute ?? '') ?>">
    <button type="submit">조회</button>
    &nbsp;
    <button type="button" onclick="goToDate('<?= $prev_date ?>')">◀ 이전</button>
    <button type="button" onclick="goToDate('<?= $next_date ?>')">다음 ▶</button>
  </form>

  <canvas id="lineChart" height="70"></canvas>
  <canvas id="dfChart" height="55"></canvas>

  <script>
    const data = <?= json_encode($chart_data) ?>;

    new Chart(document.getElementById('lineChart'), {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [
          {
            label: '종가',
            data: data.prices,
            borderColor: 'black',
            pointRadius: 0,
            fill: false,
            yAxisID: 'y2',
            tension: 0.2
          },
          {
            label: '5분 5이평',
            data: data.sma5_5,
            borderColor: 'red',
            borderDash: [2, 2],
            pointRadius: 0,
            fill: false,
            yAxisID: 'y2',
            tension: 0.2
          },
          {
            label: '5분 20이평',
            data: data.sma5_20,
            borderColor: 'orange',
            borderDash: [2, 2],
            pointRadius: 0,
            fill: false,
            yAxisID: 'y2',
            tension: 0.2
          },
          {
            label: '15분 5이평',
            data: data.sma15_5,
            borderColor: 'green',
            borderDash: [10, 2],
            borderWidth: 1,
            pointRadius: 0,
            fill: false,
            yAxisID: 'y2',
            tension: 0.2
          },
          {
            label: '60분 5이평',
            data: data.sma60_5,
            borderColor: 'gray',
            borderDash: [2, 2],
            borderWidth: 1,
            pointRadius: 0,
            fill: false,
            yAxisID: 'y2',
            tension: 0.2
          },
          {
            type: 'bar',
            label: '거래량',
            data: data.volume,
            backgroundColor: 'rgba(200, 200, 200, 0.4)',
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        scales: {
          y1: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: '거래량' }
          },
          y2: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: '가격 (pt)' },
            grid: { drawOnChartArea: false }
          }
        },
        plugins: {
          legend: { position: 'top' },
          title: { display: true, text: '종가 + 이평선 + 거래량' },
          tooltip: {
            mode: 'index',     // ✅ x축 기준으로 모든 dataset 툴팁 표시
            intersect: false   // ✅ 포인터가 바를 찍지 않아도 툴팁 활성화
          }
        }
      }
    });

    const ctx = document.getElementById('dfChart').getContext('2d');
    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [
          {
            label: '1m',
            data: data.dist_pt1_5,
            borderColor: 'black',
            borderDash: [2, 2],
            pointRadius: 0,
            fill: false,
            tension: 0.2,
            yAxisID: 'yLeft'
          },
          {
            label: '5m',
            data: data.dist_pt5_5,
            borderColor: 'red',
            borderDash: [2, 2],
            pointRadius: 0,
            fill: false,
            tension: 0.2,
            yAxisID: 'yLeft'
          }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'top' },
          title: { display: true, text: '5이평 (SMA-5) 차트' }
        },
        scales: {
          x: { title: { display: true, text: '시간' } },
          yLeft: {
            type: 'linear',
            position: 'left',
            display: true,
            title: { display: true, text: '이평 차이 (pt)' },
            suggestedMin: -1.5,
            suggestedMax: 1.5,
            ticks: { stepSize: 0.5 },
            grid: {
              color: ctx => ctx.tick.value === 0 ? '#000' : '#ddd',
              lineWidth: ctx => ctx.tick.value === 0 ? 2 : 1
            }
          },
          yRight: {
            type: 'linear',
            position: 'right',
            display: true,
            title: { display: ' ' }, // 필요시 표시
            suggestedMin: -1.5,
            suggestedMax: 1.5,
            ticks: { stepSize: 0.5 },
            grid: { drawOnChartArea: false } // ✅ 오른쪽 눈금만, 라인은 안 그림
          }
        }
      }
    });
  </script>
  <script>
    function goToDate(date) {
      const form = document.getElementById('dateForm');
      const dateInput = document.getElementById('dateInput');
      const minuteInput = document.getElementById('minuteInput');

      dateInput.value = date;
      minuteInput.value = '13:00';  // ✅ 분 초기화
      minuteInput.value = '';  // ✅ 분 초기화
      form.submit();
    }
  </script>

</body>
</html>