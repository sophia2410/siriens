<?php
$pageTitle = "📈 Predict: RSI 기반 1분봉 방향/특성별 예측";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

$dateParam = $_GET['date'] ?? date('Y-m-d');
$viewType = $_GET['view'] ?? 'direction'; // 'direction' 또는 'pattern'

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

// RSI 그룹 조회
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

$rsiStart = $rsiGroup;
$rsiEnd = $rsiGroup + 9;

$gap_order = ['4초과','3~4','2~3','1~2','0~1','-1~0','-2~-1','-3이하'];
?>

<h2>📊 RSI 기반 갭/1분봉 방향·특성별 예측 통계</h2>

<form method="get" style="margin-bottom: 20px;">
  <input type="hidden" name="view" value="<?= htmlspecialchars($viewType) ?>">
  <label>📅 날짜 선택: <input type="date" name="date" value="<?= htmlspecialchars($dateParam) ?>"></label>
  <button type="submit">조회</button>
  <?php if (!empty($prev_date)): ?>
    <a href="?date=<?= $prev_date ?>&view=<?= $viewType ?>" style="margin-left: 20px;">◀ 이전일</a>
  <?php endif; ?>
  <?php if (!empty($next_date)): ?>
    <a href="?date=<?= $next_date ?>&view=<?= $viewType ?>" style="margin-left: 10px;">다음일 ▶</a>
  <?php endif; ?>
</form>

<p><strong>선택일자:</strong> <?= htmlspecialchars($dateParam) ?> / <strong>RSI:</strong> <?= $rsi14 ?> (<?= $rsiGroup ?>~<?= $rsiGroup + 9 ?>)</p>
<br>
<nav style="margin-bottom: 20px;">
  <a href="?date=<?= $dateParam ?>&view=direction" <?= $viewType === 'direction' ? 'style="font-weight:bold; text-decoration:underline;"' : '' ?>>🟢 1분봉 양/음 기준</a> |
  <a href="?date=<?= $dateParam ?>&view=pattern" <?= $viewType === 'pattern' ? 'style="font-weight:bold; text-decoration:underline;"' : '' ?>>🟠 1분봉 특성 기준</a>
</nav>

<?php

if ($viewType === 'direction') {
    include "./predict_rsi_gap_1min_direction.php";
} elseif ($viewType === 'pattern') {
    include "./predict_rsi_gap_1min_pattern.php";
}
?>

<div style="margin-top: 40px;">
  <h2>📈 차트 분석 (볼린저밴드, RSI 포함)</h2>
  <iframe src="./futures_chart_BB.php?date=<?= urlencode($dateParam) ?>"
          width="100%" height="1000" frameborder="0"></iframe>
</div>

<?php require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"); ?>
