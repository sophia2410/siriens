<?php
// partials/predict_rsi_gap_1min_pattern.php

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
  CASE 
    WHEN ABS(f1min.close - f1min.open) >= 0.8 AND f1min.close > f1min.open THEN '장대양봉'
    WHEN ABS(f1min.close - f1min.open) >= 0.8 AND f1min.close < f1min.open THEN '장대음봉'
    WHEN (f1min.high - GREATEST(f1min.close, f1min.open)) >= 1 THEN '긴윗꼬리'
    WHEN (LEAST(f1min.close, f1min.open) - f1min.low) >= 1 THEN '긴아래꼬리'
  END AS candle_pattern,
  COUNT(*) AS total,
  SUM(CASE WHEN f5min.close > f5min.open THEN 1 ELSE 0 END) AS up_5m,
  ROUND(SUM(CASE WHEN f5min.close > f5min.open THEN 1 ELSE 0 END)/COUNT(*)*100,1) AS up_5m_rate,
  SUM(CASE WHEN curr.close > curr.open THEN 1 ELSE 0 END) AS up_60m,
  ROUND(SUM(CASE WHEN curr.close > curr.open THEN 1 ELSE 0 END)/COUNT(*)*100,1) AS up_60m_rate
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
    ) b WHERE rn = 1
) f5min ON f5min.date = cal.date
WHERE cal.cal_yn = 'Y'
  AND prev.rsi_14 BETWEEN ? AND ?
GROUP BY gap_range, candle_pattern
";

$stmt2 = $mysqli->prepare($statQuery);
$stmt2->bind_param("ii", $rsiStart, $rsiEnd);
$stmt2->execute();
$result = $stmt2->get_result();

$stats = [];
while ($row = $result->fetch_assoc()) {
    $gap = $row['gap_range'];
    $pattern = $row['candle_pattern'];
    $stats[$gap][$pattern] = [
        'total' => $row['total'],
        'up_5m' => $row['up_5m'],
        'up_5m_rate' => $row['up_5m_rate'],
        'up_60m' => $row['up_60m'],
        'up_60m_rate' => $row['up_60m_rate']
    ];
}

$gap_order = ['4초과','3~4','2~3','1~2','0~1','-1~0','-2~-1','-3이하'];
$pattern_order = ['장대양봉','장대음봉','긴윗꼬리','긴아래꼬리'];
?>

<style>
  table.predict-table th, table.predict-table td {
    text-align: center;
    padding: 6px;
  }
  table.predict-table tr.gap-divider td {
    border-top: 3px solid #333;
  }
</style>

<table class="predict-table" border="1" cellpadding="6" cellspacing="0">
  <thead>
    <tr>
      <th rowspan="2">📉 갭 구간</th>
      <th colspan="5">패턴</th>
      <th colspan="3" style="background:#e6f0ff">🔵 5분봉</th>
      <th colspan="3" style="background:#ffe6e6">🔴 60분봉</th>
    </tr>
    <tr>
      <th width=90>타입</th>
      <?php foreach ($pattern_order as $pattern): ?>
        <th width=90><?= $pattern ?></th>
      <?php endforeach; ?>
      <th>사례</th><th>상승</th><th>승률</th>
      <th>사례</th><th>상승</th><th>승률</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($gap_order as $gap): ?>
      <?php
      $first = true;
      if (isset($stats[$gap])) {
        foreach ($pattern_order as $pattern) {
          if (!isset($stats[$gap][$pattern])) continue;
          $row = $stats[$gap][$pattern];
      ?>
        <tr<?= $first ? ' class="gap-divider"' : '' ?><?= $first ? ' style="background:#f8f8f8"' : '' ?> >
          <td><?= $first ? "<b>$gap</b>" : '' ?></td>
          <td><?= $pattern ?></td>
          <?php foreach ($pattern_order as $col): ?>
            <td><?= $col === $pattern ? '⭕' : '' ?></td>
          <?php endforeach; ?>
          <td><?= $row['total'] ?></td>
          <td><?= $row['up_5m'] ?></td>
          <td><?= $row['up_5m_rate'] ?>%</td>
          <td><?= $row['total'] ?></td>
          <td><?= $row['up_60m'] ?></td>
          <td><?= $row['up_60m_rate'] ?>%</td>
        </tr>
      <?php $first = false; }} else { ?>
        <tr class="gap-divider">
          <td><b><?= $gap ?></b></td>
          <td colspan="<?= count($pattern_order) + 7 ?>">해당 없음</td>
        </tr>
      <?php } ?>
    <?php endforeach; ?>
  </tbody>
</table>
