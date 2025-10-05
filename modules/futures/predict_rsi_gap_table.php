<?php
$pageTitle = "RSI 기반 다음날 갭/5분봉별 예측";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

$dateParam = $_GET['date'] ?? date('Y-m-d');

// 이전일 / 다음일 구하기
$stmt_prev = $mysqli->prepare("SELECT MAX(date) FROM calendar WHERE date < ?");
$stmt_prev->bind_param('s', $dateParam);
$stmt_prev->execute();
$stmt_prev->bind_result($prev_date);
$stmt_prev->fetch();
$stmt_prev->close();

$stmt_next = $mysqli->prepare("SELECT MIN(date) FROM calendar WHERE date > ?");
$stmt_next->bind_param('s', $dateParam);
$stmt_next->execute();
$stmt_next->bind_result($next_date);
$stmt_next->fetch();
$stmt_next->close();


// 0. 선물 일봉 테이블에서 전략정보 구해오기
$sql = "
    SELECT
        f1.*,
        ROUND((f1.open - f2.close) / f2.close * 100, 2) AS open_change_pct,
        ROUND((f1.close - f2.close) / f2.close * 100, 2) AS close_change_pct,
        f1.open - f2.close AS open_change_pt,
        f1.close - f2.close AS close_change_pt,
        fa.gap_percent,
        fa.gap_type,
        fa.pattern_0845_0859,
        fa.tick_range_0845_0859,
        fa.vol_0845_0859,
        fa.candle_0900_dir,
        fa.diff_0900_pt,
        fa.tick_range_0900,
        fa.match_last5_and_0900,
        fa.match_last5_and_0901,
        fa.is_morning_breakout,
        fa.entry_direction_a,
        fa.open_0845_0859,
        fa.close_0845_0859,
        fa.diff_0845_0859_pt,
        fa.created_at
    FROM futures_1day f1
    LEFT JOIN futures_1day f2
        ON f2.date = (
            SELECT MAX(date)
            FROM futures_1day
            WHERE date < f1.date
        )
    LEFT JOIN futures_analysis fa ON f1.date = fa.date
    WHERE f1.date = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $dateParam);
$stmt->execute();
$result = $stmt->get_result();
$strategy = $result->fetch_assoc();

// 상승/하락에 따른 색상 설정
function colorize($value) {
  if (!isset($value)) return '-';

    // 숫자가 아니면 그대로 반환
    if (!is_numeric($value)) return $value;

    // 소수점 자릿수 계산 (정수형도 커버)
    $formatted = (floor($value) != $value)
        ? number_format($value, 2)  // 소수점 있으면 2자리 고정
        : number_format($value);    // 정수면 그냥


  if ($value > 0) {
      return "<span style='color:red;'>$formatted</span>";
  } elseif ($value < 0) {
      return "<span style='color:blue;'>$formatted</span>";
  } else {
      return $formatted;
  }
}

// 1. 선택일자 RSI 그룹 조회
$rsiGroupQuery = "
  SELECT FLOOR(rsi_14 / 10) * 10 AS rsi_group, rsi_14
  FROM futures_60min
  WHERE date = ?
    AND time = (SELECT MAX(time) FROM futures_60min WHERE date = ?)
";
$stmt = $mysqli->prepare($rsiGroupQuery);
$stmt->bind_param("ss", $dateParam, $dateParam);
$stmt->execute();
$rsiGroupResult = $stmt->get_result()->fetch_assoc();
$rsiGroup = $rsiGroupResult['rsi_group'] ?? null;
$rsi14 = $rsiGroupResult['rsi_14'] ?? null;

if ($rsiGroup === null) {
    echo "<p>해당 일자의 RSI 데이터를 찾을 수 없습니다.</p>";
}

// 2. RSI 그룹 기반 과거 통계 조회
$statQuery = "
SELECT 
  CASE 
    WHEN (curr.open - prev.close) > 4 THEN '4초과'
    WHEN (curr.open - prev.close) > 3 AND (curr.open - prev.close) <= 4 THEN '3~4'
    WHEN (curr.open - prev.close) > 2 AND (curr.open - prev.close) <= 3 THEN '2~3'
    WHEN (curr.open - prev.close) > 1 AND (curr.open - prev.close) <= 2 THEN '1~2'
    WHEN (curr.open - prev.close) > 0 AND (curr.open - prev.close) <= 1 THEN '0~1'
    WHEN (curr.open - prev.close) > -1 AND (curr.open - prev.close) <= 0 THEN '-1~0'
    WHEN (curr.open - prev.close) > -2 AND (curr.open - prev.close) <= -1 THEN '-2~-1'
    WHEN (curr.open - prev.close) <= -3 THEN '-3이하'
  END AS gap_range,
  CASE WHEN f5_1.close > f5_1.open THEN '양봉' ELSE '음봉' END AS first_5m_type,
  COUNT(*) AS total,
  SUM(CASE WHEN curr.close > f5_1.close THEN 1 ELSE 0 END) AS up,
  ROUND(SUM(CASE WHEN curr.close > f5_1.close THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS up_rate,
  SUM(CASE WHEN curr.close <= f5_1.close THEN 1 ELSE 0 END) AS down,
  ROUND(SUM(CASE WHEN curr.close <= f5_1.close THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS down_rate
FROM calendar cal
JOIN (
    SELECT t1.* FROM futures_60min t1
    JOIN (SELECT date, MAX(time) AS maxtime FROM futures_60min GROUP BY date) t2
    ON t1.date = t2.date AND t1.time = t2.maxtime
) prev ON prev.date = (SELECT MAX(date) FROM calendar c2 WHERE c2.date < cal.date)
JOIN futures_60min curr ON curr.date = cal.date AND curr.time = cal.futures_start_time
JOIN (
    SELECT * FROM (
        SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn FROM futures_5min
    ) a WHERE rn = 1
) f5_1 ON f5_1.date = cal.date
WHERE cal.cal_yn = 'Y'
  AND prev.rsi_14 BETWEEN ? AND ?
GROUP BY gap_range, first_5m_type
";

$rsiStart = $rsiGroup;
$rsiEnd = $rsiGroup + 9;

$stmt2 = $mysqli->prepare($statQuery);
$stmt2->bind_param("ii", $rsiStart, $rsiEnd);
$stmt2->execute();
$result = $stmt2->get_result();

$stats = [];
while ($row = $result->fetch_assoc()) {
    $gap = $row['gap_range'];
    $type = $row['first_5m_type'];
    $stats[$gap][$type] = [
        'total' => $row['total'],
        'up' => $row['up'],
        'up_rate' => $row['up_rate'],
        'down' => $row['down'],
        'down_rate' => $row['down_rate']
    ];
}

$gap_order = ['4초과','3~4','2~3','1~2','0~1','-1~0','-2~-1','-3이하'];
?>


<h2>📊 RSI 기반 갭/5분봉 조합별 60분 수익 통계</h2>

<form method="get" style="margin-bottom: 20px;">
  <label>📅 날짜 선택: <input type="date" name="date" value="<?= htmlspecialchars($dateParam) ?>"></label>
  <button type="submit">조회</button>
  <?php if (!empty($prev_date)): ?>
    <a href="?date=<?= $prev_date ?>" style="margin-left: 20px;">◀ 이전일</a>
  <?php endif; ?>
  <?php if (!empty($next_date)): ?>
    <a href="?date=<?= $next_date ?>" style="margin-left: 10px;">다음일 ▶</a>
  <?php endif; ?>
</form>
<!-- <div>
    🟡 시가: <?= $strategy['open'] ?>pt , <?= colorize($strategy['open_change_pct'] ?? null) ?>% , <?= colorize($strategy['open_change_pt'] ?? null) ?> pt | 종가: <?= $strategy['close'] ?>pt, <?= colorize($strategy['close_change_pct'] ?? null) ?>%, <?= colorize($strategy['close_change_pt'] ?? null) ?> pt&nbsp;&nbsp;&nbsp;
    🟢 순매수 현황 (천만원):
    외국인: <b><?= colorize($strategy['net_foreign'] ?? null) ?>(<?= colorize($strategy['cum_net_foreign'] ?? null) ?>)</b> |
    기관: <b><?= colorize($strategy['net_institution'] ?? null) ?>(<?= colorize($strategy['cum_net_institution'] ?? null) ?>)</b> |
    개인: <b><?= colorize($strategy['net_individual'] ?? null) ?>(<?= colorize($strategy['cum_net_individual'] ?? null) ?>)</b> |
    거래량: <?= number_format($strategy['volume']) ?? '-' ?>
</div> -->

<hr>

<p><strong>선택일자:</strong> <?= htmlspecialchars($dateParam) ?> / <strong><?= $rsi14 ?> (RSI 구간:</strong> <?= $rsiGroup ?>~<?= $rsiGroup + 9 ?>)</p>

<style>
  table.predictable-table th, table.predictable-table td {
    text-align: center;
    padding: 6px;
  }
  table.predictable-table tr.highlight {
    background-color: #f8f9fa;
  }
</style>

<table class="predictable-table" border="1" cellpadding="6" cellspacing="0">
  <thead>
    <tr>
      <th rowspan="2" colspan=2>📉 갭 구간</th>
      <th colspan="5" style="background:#eef">🟩 5분봉 양봉</th>
      <th colspan="5" style="background:#fee">🟥 5분봉 음봉</th>
    </tr>
    <tr>
      <th>사례</th><th>상승</th><th>승률</th><th>하락</th><th>하락률</th>
      <th>사례</th><th>상승</th><th>승률</th><th>하락</th><th>하락률</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($gap_order as $gap): 
      $up = $stats[$gap]['양봉'] ?? ['total' => 0, 'up' => 0, 'up_rate' => 0, 'down' => 0, 'down_rate' => 0];
      $dn = $stats[$gap]['음봉'] ?? ['total' => 0, 'up' => 0, 'up_rate' => 0, 'down' => 0, 'down_rate' => 0];
    ?>
    <tr class="<?= ($gap === '-3이하' || $gap === '4초과') ? 'highlight' : '' ?>">
      <td><b><?= $gap ?></td><td><b><?=  $up['total'] + $dn['total'] ?></td>
      <td><?= $up['total'] ?></td><td><b><?= $up['up'] ?></td><td><?= $up['up_rate'] ?>%</td><td><b><?= $up['down'] ?></td><td><?= $up['down_rate'] ?>%</td>
      <td><?= $dn['total'] ?></td><td><b><?= $dn['up'] ?></td><td><?= $dn['up_rate'] ?>%</td><td><b><?= $dn['down'] ?></td><td><?= $dn['down_rate'] ?>%</td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<div style="margin-top: 40px;">
  <h2>📈 차트 분석 (볼린저밴드, RSI 포함)</h2>
  <iframe src="./futures_chart_BB.php?date=<?= urlencode($dateParam) ?>"
          width="100%" height="1000" frameborder="0"></iframe>
</div>
<?php require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"); ?>
