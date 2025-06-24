<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

header('Content-Type: text/csv; charset=EUC-KR');
$bb     = $_GET['bb'] ?? '';
$ema    = $_GET['ema'] ?? '';
$rsi    = $_GET['rsi'] ?? '';
$minute = $_GET['minute'] ?? '60';
$table  = "futures_bb_rsi_features_{$minute}m";

// 쿼리 조건 구성
$where = [
  "bb_level = '" . $mysqli->real_escape_string($bb) . "'",
  "ema_cross = '" . $mysqli->real_escape_string($ema) . "'",
  "rsi_range = '" . $mysqli->real_escape_string($rsi) . "'"
];

if (!empty($_GET['macd_hist'])) {
  $where[] = "macd_hist = '" . $mysqli->real_escape_string($_GET['macd_hist']) . "'";
}
if (isset($_GET['body_pct_min'])) {
  $where[] = "body_pct >= " . floatval($_GET['body_pct_min']);
}
if (isset($_GET['ema_bb_gap_min'])) {
  $where[] = "ema_bb_gap >= " . floatval($_GET['ema_bb_gap_min']);
}
if (!empty($_GET['bb_slope'])) {
  $where[] = "bb_slope = '" . $mysqli->real_escape_string($_GET['bb_slope']) . "'";
}

$whereSql = implode(" AND ", $where);

// 응답 헤더 (파일 이름에 분봉도 반영)
header("Content-Disposition: attachment; filename=gap_cases_{$minute}m_{$bb}_{$ema}_{$rsi}.csv");

// CSV 출력
$output = fopen('php://output', 'w');

// 헤더 출력 (인코딩 포함)
$headers = [
  '날짜', '갭', '시가 방향', '60분 후',
  '밴드폭', '기울기', '밴드 내 위치',
  'EMA 간격', 'EMA vs BB',
  'MACD', 'MACD HIST', 'MACD POSITION', 'MACD VS SIGNAL', 'MACD-HIST 증감',
  'RSI', '캔들', '몸통%', '위꼬리%', '아래꼬리%', '거래량'
];
fputcsv($output, array_map(fn($x) => iconv("UTF-8", "EUC-KR//IGNORE", $x), $headers));

// 데이터 조회 및 출력
$sql = "
SELECT date, gap, gap_dir, next_close_dir,
       bb_width, bb_slope, bb_position, ema_gap, ema_bb_gap,
       macd, macd_hist, macd_position, macd_vs_signal, macd_hist_change,
       rsi_14, candle_type,
       body_pct, upper_tail_pct, lower_tail_pct,
       volume
FROM {$table}
WHERE {$whereSql}
ORDER BY date DESC
LIMIT 500
";
$result = $mysqli->query($sql);

// 출력
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['date'],
        $row['gap'],
        iconv("UTF-8", "EUC-KR//IGNORE", $row['gap_dir']),
        iconv("UTF-8", "EUC-KR//IGNORE", $row['next_close_dir']),
        round($row['bb_width'], 2),
        iconv("UTF-8", "EUC-KR//IGNORE", $row['bb_slope']),
        round($row['bb_position'], 2),
        round($row['ema_gap'], 3),
        round($row['ema_bb_gap'], 3),
        round($row['macd'], 2),
        round($row['macd_hist'], 2),
        iconv("UTF-8", "EUC-KR//IGNORE", $row['macd_position']),
        iconv("UTF-8", "EUC-KR//IGNORE", $row['macd_vs_signal']),
        iconv("UTF-8", "EUC-KR//IGNORE", $row['macd_hist_change']),
        round($row['rsi_14'], 1),
        iconv("UTF-8", "EUC-KR//IGNORE", $row['candle_type']),
        round($row['body_pct'], 2),
        round($row['upper_tail_pct'], 2),
        round($row['lower_tail_pct'], 2),
        number_format($row['volume'])
    ]);
}
fclose($output);
exit;
