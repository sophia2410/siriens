<?php
// partials/predict_rsi_gap_1min_direction.php
// 1분봉 대비 up/down 으로 변경
$statQuery = "
SELECT 
  CASE 
    WHEN (curr.open - prev.close) > 4 THEN '4초과'
    WHEN (curr.open - prev.close) > 3 THEN '3~4'
    WHEN (curr.open - prev.close) > 2 THEN '2~3'
    WHEN (curr.open - prev.close) > 1 THEN '1~2'
    WHEN (curr.open - prev.close) > 0 THEN '0~1'
    WHEN (curr.open - prev.close) > -1 THEN '-1~0'
    WHEN (curr.open - prev.close) > -2 THEN '-2~-1'
    ELSE '-3이하'
  END AS gap_range,
  CASE WHEN f1min.close > f1min.open THEN '양봉' ELSE '음봉' END AS first_1m_type,
  COUNT(*) AS total,
  SUM(CASE WHEN f5min.close > f1min.close THEN 1 ELSE 0 END) AS up_5m,
  ROUND(SUM(CASE WHEN f5min.close > f1min.close THEN 1 ELSE 0 END)/COUNT(*)*100,1) AS up_5m_rate,
  SUM(CASE WHEN curr.close > f1min.close THEN 1 ELSE 0 END) AS up_60m,
  ROUND(SUM(CASE WHEN curr.close > f1min.close THEN 1 ELSE 0 END)/COUNT(*)*100,1) AS up_60m_rate
FROM calendar cal
JOIN (
    SELECT t1.* FROM futures_60min t1
    JOIN (SELECT date, MAX(time) AS maxtime FROM futures_60min GROUP BY date) t2
    ON t1.date = t2.date AND t1.time = t2.maxtime
) prev ON prev.date = (SELECT MAX(date) FROM calendar c2 WHERE c2.date < cal.date)
JOIN futures_60min curr ON curr.date = cal.date AND curr.time = cal.futures_start_time
JOIN (
    SELECT * FROM (
        SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn FROM futures_1min
    ) a WHERE rn = 1
) f1min ON f1min.date = cal.date
JOIN (
    SELECT * FROM (
        SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn FROM futures_5min
    ) a WHERE rn = 1
) f5min ON f5min.date = cal.date
WHERE cal.cal_yn = 'Y'
  AND prev.rsi_14 BETWEEN ? AND ?
GROUP BY gap_range, first_1m_type
";

$stmt2 = $mysqli->prepare($statQuery);
$stmt2->bind_param("ii", $rsiStart, $rsiEnd);
$stmt2->execute();
$result = $stmt2->get_result();

$stats = [];
while ($row = $result->fetch_assoc()) {
    $gap = $row['gap_range'];
    $type = $row['first_1m_type'];
    $stats[$gap][$type] = [
        'total' => $row['total'],
        'up_5m' => $row['up_5m'],
        'up_5m_rate' => $row['up_5m_rate'],
        'up_60m' => $row['up_60m'],
        'up_60m_rate' => $row['up_60m_rate']
    ];
}

$gap_order = ['4초과','3~4','2~3','1~2','0~1','-1~0','-2~-1','-3이하'];
$type_order = ['양봉', '음봉'];
?>

<style>
  table.predict-table th, table.predict-table td {
    text-align: center;
    padding: 6px;
  }
  table.predict-table tr.highlight {
    background-color: #f8f9fa;
  }
</style>

<div style="margin-top:30px">
<table class="predict-table" border="1" cellpadding="6" cellspacing="0">
  <thead>
    <tr>
      <th rowspan="3">📉 갭 구간</th>
      <th colspan="3">건수</th>
      <th colspan="4" style="background:#fff5f5"><span style="color:#cc3333">■</span><b>1분봉 양봉</b></th>
      <th colspan="4" style="background:#f5f5ff"><span style="color:#3366cc">■</span><b>1분봉 음봉</b></th>
    </tr>
    <tr>
      <th rowspan="2" style="background:#eef">총 건수</th>
      <th rowspan="2" style="background:#eef"><span style="color:#cc3333">■</span> 건수</th>
      <th rowspan="2" style="background:#eef"><span style="color:#3366cc">■</span> 건수</th>
      <th colspan="2" style="background:#eef">5분봉 - 1분봉</th>
      <th colspan="2" style="background:#fee">60분봉 - 1분봉</th>
      <th colspan="2" style="background:#eef">5분봉 - 1분봉</th>
      <th colspan="2" style="background:#fee">60분봉 - 1분봉</th>
    </tr>
    <tr>
      <th>상승</th><th>상승률</th>
      <th>상승</th><th>상승률</th>
      <th>상승</th><th>상승률</th>
      <th>상승</th><th>상승률</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($gap_order as $gap): 
      $bull = $stats[$gap]['양봉'] ?? ['total' => 0, 'up_5m' => 0, 'up_5m_rate' => 0, 'up_60m' => 0, 'up_60m_rate' => 0];
      $bear = $stats[$gap]['음봉'] ?? ['total' => 0, 'up_5m' => 0, 'up_5m_rate' => 0, 'up_60m' => 0, 'up_60m_rate' => 0];
    ?>
    <tr>
      <td><b><?= $gap ?></b></td>
      <td width=100><b><?= $bull['total'] + $bear['total'] ?></td>
      <td><h3><?= $bull['total'] ?></h3></td>
      <td><h3><?= $bear['total'] ?></h3></td>

      <td><b><?= $bull['up_5m'] ?> / <?= $bull['total'] ?></td>
      <td><?= $bull['up_5m_rate'] ?>%</td>
      <td><b><?= $bull['up_60m'] ?> / <?= $bull['total'] ?></td>
      <td><?= $bull['up_60m_rate'] ?>%</td>

      <td><b><?= $bear['up_5m'] ?> / <?= $bear['total'] ?></td>
      <td><?= $bear['up_5m_rate'] ?>%</td>
      <td><b><?= $bear['up_60m'] ?> / <?= $bear['total'] ?></td>
      <td><?= $bear['up_60m_rate'] ?>%</td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
