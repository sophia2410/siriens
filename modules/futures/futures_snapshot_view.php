<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
$date = $_GET['date'] ?? date('Y-m-d');

$sql = "SELECT * FROM futures_snapshot_momentum WHERE date = ? ORDER BY datetime ASC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>선물 스냅샷 모니터링</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      font-family: sans-serif;
      font-size: 14px;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      table-layout: fixed;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 6px;
      text-align: right;
      width: 90px;
      max-width: 90px;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
      font-size: 14px;
    }
    thead th {
      position: sticky;
      z-index: 2;
      background: #f2f2f2;
    }
    thead tr:first-child th {
      top: 0;
    }
    thead tr:nth-child(2) th {
      top: 28px;
    }
    .trend-bar {
      display: inline-block;
      height: 10px;
      border-radius: 2px;
      margin-left: 4px;
      vertical-align: middle;
    }
    .group-start {
      border-left: 2px solid #666;
    }
  </style>
</head>
<body>
  <form method="get">
    날짜: <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button type="submit">조회</button>
  </form>
  <?php
    $sma_data = [
      'labels' => [],
      '1m' => [], '5m' => [], '10m' => [], '15m' => [], '60m' => []
    ];

    foreach ($data as $row) {
      $sma_data['labels'][] = substr($row['time'], 0, 5); // HH:MM 형식
      $sma_data['1m'][] = $row['sma_1min_5'] ?? null;
      $sma_data['5m'][] = $row['sma_5min_5'] ?? null;
      $sma_data['10m'][] = $row['sma_10min_5'] ?? null;
      $sma_data['15m'][] = $row['sma_15min_5'] ?? null;
      $sma_data['60m'][] = $row['sma_60min_5'] ?? null;
    }
  ?>
  <div style="margin: 20px 0;">
    <canvas id="smaChart" width:="100%" height="60"></canvas>
  </div>
  <div style="overflow-y: auto; max-height: 40vh;">
    <table>
      <thead>
        <tr>
          <th colspan="3">시간</th>
          <?php foreach ([1,5,10,15,60,'1day'] as $tf): 
            $colspan = ($tf === 1) ? 3 : 2;
            $label = ($tf === '1day') ? '1DAY' : strtoupper($tf) . 'm';
          ?>
            <th colspan="<?= $colspan ?>"><?= $label ?></th>
          <?php endforeach; ?>
        </tr>
        <tr>
          <th>시각</th>
          <th>가격</th>
          <th>변화</th>
          <?php foreach ([1,5,10,15,60,'1day'] as $tf): 
            $periods = ($tf === 1) ? [5, 20, 120] : [5, 20];
            foreach ($periods as $p): ?>
              <th><?= $p ?></th>
          <?php endforeach; endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        $prevPrice = null;
        $rows = [];
        
        foreach ($data as $row):
          $time = $row['time'];
          $price = $row['price'];
          $diffHtml = '-';
        
          if (!is_null($prevPrice)) {
            $diff = round($price - $prevPrice, 2);
            $absDiff = abs($diff);
            $symbol = '';
            $color = '';
        
            if ($diff > 0.05) {
              $symbol = '▲'; $color = 'red';
            } elseif ($diff > 0) {
              $symbol = '△'; $color = 'red';
            } elseif ($diff < -0.05) {
              $symbol = '▼'; $color = 'blue';
            } elseif ($diff < 0) {
              $symbol = '▽'; $color = 'blue';
            }
        
            if ($symbol !== '') {
              $diffHtml = "<span style='color:$color'>{$symbol} " . number_format($absDiff, 2) . "pt</span>";
            }
          }
          $prevPrice = $price;
        
          // ✨ 출력 내용은 rows에 쌓아둠
          ob_start();
          ?>
          <tr>
            <td><?= $time ?></td>
            <td><?= $price ?></td>
            <td><?= $diffHtml ?></td>
            <?php foreach ([1,5,10,15,60,'1day'] as $tf):
              $periods = ($tf === 1) ? [5, 20, 120] : [5, 20];
              foreach ($periods as $i => $p):
                $pct = $row["dist_pt_{$tf}min_{$p}"] ?? $row["dist_pt_{$tf}_{$p}"];
                $isFirst = ($i === 0);
            ?>
              <td<?= $isFirst ? ' class="group-start"' : '' ?>>
                <?php if (is_null($pct)) {
                  echo '-';
                } else {
                  $maxWidth = 50;
                  $minWidth = 5;
                  $scale = 15;
                  $width = min($maxWidth, abs($pct) * $scale);
                  if ($width > 0 && $width < $minWidth) $width = $minWidth;
                  $color = $pct > 0 ? 'red' : ($pct < 0 ? 'blue' : 'gray');
                  echo number_format($pct, 2) . "pt";
                  echo "<span class='trend-bar' style='width:{$width}px; background:{$color};'></span>";
                } ?>
              </td>
            <?php endforeach; endforeach; ?>
          </tr>
          <?php $rows[] = ob_get_clean(); ?>
          <?php endforeach; ?>
          
        <?php foreach (array_reverse($rows) as $html) echo $html; ?>
      </tbody>
    </table>
  </div>

  <script>
    const smaData = <?= json_encode($sma_data) ?>;

    const ctx = document.getElementById('smaChart').getContext('2d');
    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: smaData.labels,
        // datasets: [
        //   {
        //     label: '1m',
        //     data: smaData['1m'],
        //     borderColor: 'black',
        //     borderWidth: 3,
        //     tension: 0.3,
        //     fill: false,
        //     pointRadius: 0
        //   },
        //   {
        //     label: '5m',
        //     data: smaData['5m'],
        //     borderColor: 'red',
        //     borderWidth: 3,
        //     tension: 0.3,
        //     fill: false,
        //     pointRadius: 0
        //   },
        //   {
        //     label: '10m',
        //     data: smaData['10m'],
        //     borderColor: 'green',
        //     borderWidth: 1,
        //     tension: 0.3,
        //     fill: false,
        //     pointRadius: 0
        //   },
        //   {
        //     label: '15m',
        //     data: smaData['15m'],
        //     borderColor: 'orange',
        //     borderWidth: 2,
        //     tension: 0.3,
        //     fill: false,
        //     pointRadius: 0
        //   },
        //   {
        //     label: '60m',
        //     data: smaData['60m'],
        //     borderColor: 'purple',
        //     borderWidth: 2,
        //     tension: 0.3,
        //     fill: false,
        //     pointRadius: 0
        //   }
        // ]

        datasets: [
          {
            label: '1m',
            data: smaData['1m'],
            borderColor: 'black',
            borderWidth: 3,
            tension: 0.3,
            fill: false,
            pointRadius: 0
          },
          {
            label: '5m',
            data: smaData['5m'],
            borderColor: 'red',
            borderWidth: 3,
            tension: 0.3,
            fill: false,
            pointRadius: 0
          },
          {
            label: '10m',
            data: smaData['10m'],
            borderColor: 'green',
            borderWidth: 1,
            tension: 0.3,
            fill: false,
            pointRadius: 0
          },
          {
            label: '15m',
            data: smaData['15m'],
            borderColor: 'orange',
            borderWidth: 2,
            tension: 0.3,
            fill: false,
            pointRadius: 0
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
          y: {
                title: { display: true, text: '이평 차이 (pt)' },
                ticks: {
                  stepSize: 0.5  // ✅ y축 눈금 간격을 0.5로 고정
                },
                grid: {
                  color: (ctx) => ctx.tick.value === 0 ? '#000' : '#ddd',  // 0이면 진하게
                  lineWidth: (ctx) => ctx.tick.value === 0 ? 2 : 1         // 0이면 굵게
                }
          }
        }
      }
    });
  </script>

</body>
</html>
