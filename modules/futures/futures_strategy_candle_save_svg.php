<?php
// futures_strategy_firstcandle_save_svg.php
header('Content-Type: application/json; charset=utf-8');

try {
  // === 경로 설정 ===
  $ATTACH_DIR = 'D:\\Obsidian\\Trader Sophia\\90 Attachments\\future_charts';
  $MD_DIR     = 'D:\\Obsidian\\Trader Sophia\\☆ Futures\\♧ 전략F - 시가vs5이평 추세확인매매';

  // MD 파일 위치(☆ Futures/♧ 전략-... 폴더)에서 Attachments로의 상대경로 (두 단계 ↑)
  $relToMd = '../../90 Attachments/future_charts/';

  // === 입력 ===
  $raw = file_get_contents('php://input');
  if (!$raw) throw new Exception('empty body');
  $req = json_decode($raw, true);
  if (!is_array($req)) throw new Exception('invalid json');

  $date     = $req['date']     ?? null; // YYYY-MM-DD
  $interval = $req['interval'] ?? null; // '1m' | '5m' | '15m' | '60m'
  $index    = $req['index']    ?? null; // 카드 인덱스(선택)
  $bars     = isset($req['bars']) ? intval($req['bars']) : 0; // 캔들 수(선택)
  $svg      = $req['svg']      ?? null;

  if (!$date || !$interval || !$svg) throw new Exception('missing fields');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new Exception('bad date');

  if (!in_array($interval, ['1m','5m','15m','60m'], true)) throw new Exception('bad interval');

  if (!is_dir($ATTACH_DIR) && !mkdir($ATTACH_DIR, 0775, true)) {
    throw new Exception('cannot create attach dir');
  }

  // === 파일명 규칙 ===
  // 동일 조건은 덮어쓰기: 2025-10-24_1m_c16.svg (bars 없으면 _c 생략)
  $barPart  = $bars > 0 ? "_c{$bars}" : '';
  $filename = sprintf('%s_%s%s.svg', $date, $interval, $barPart);

  // wide 버전 저장 시:
  // $filename = sprintf('%s_%s%s_wide.svg', $date, $interval, $barPart);

  // === SVG 보정 ===
  if (strpos($svg, '<svg') === false) throw new Exception('invalid svg');
  if (strpos($svg, '<?xml') === false) {
    $svg = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $svg;
  }

  $full = rtrim($ATTACH_DIR, '\\/') . DIRECTORY_SEPARATOR . $filename;
  if (file_put_contents($full, $svg) === false) throw new Exception('write failed');

  $relPath = $relToMd . $filename; // MD에서 사용할 상대경로

  echo json_encode(['ok' => true, 'rel' => $relPath, 'abs' => $full]);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
