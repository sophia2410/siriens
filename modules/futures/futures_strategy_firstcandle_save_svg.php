<?php
// /exports/save_svg.php
// PHP가 D:\ 경로에 쓸 수 있어야 합니다 (IIS/Apache 계정 권한 확인).

header('Content-Type: application/json; charset=utf-8');

try {
  // ======= 환경설정 =======
  // 이미지 저장 경로(Obsidian Attachments)
  $ATTACH_DIR = 'D:\\Obsidian\\Trader Sophia\\90 Attachments\\future_charts';
  // 마크다운 파일이 저장될 폴더(상대 경로 계산용)
  $MD_DIR     = 'D:\\Obsidian\\Trader Sophia\\☆ Futures\\♧ 전략-1분봉5,20이평기준';

  // 상대경로: MD 파일 기준으로 Attachments 상대경로 계산 (..\90 Attachments\)
  // 윈도우 경로를 / 로 바꿔도 Obsidian이 잘 읽습니다.
  $relToMd = 'future_charts/';

  // ======= 입력 =======
  $raw = file_get_contents('php://input');
  if (!$raw) throw new Exception('empty body');
  $req = json_decode($raw, true);
  if (!is_array($req)) throw new Exception('invalid json');

  $date     = $req['date']     ?? null; // 'YYYY-MM-DD'
  $interval = $req['interval'] ?? null; // '1m' | '5m' | '60m'
  $index    = $req['index']    ?? null; // 카드 인덱스
  $svg      = $req['svg']      ?? null;

  if (!$date || !$interval || !$svg) throw new Exception('missing fields');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new Exception('bad date');
  if (!in_array($interval, ['1m','5m','60m'], true)) throw new Exception('bad interval');

  // 폴더 생성
  if (!is_dir($ATTACH_DIR) && !mkdir($ATTACH_DIR, 0775, true)) {
    throw new Exception('cannot create attach dir');
  }

  // 파일명: 2025-10-17_1m_idx0.svg (중복 방지로 타임스탬프 살짝)
  $idxPart = is_null($index) ? 'x' : intval($index);
  $stamp   = date('His');
  // $filename = sprintf('%s_%s_idx%s_%s.svg', $date, $interval, $idxPart, $stamp);

  // 동일한 일자의 분봉은 덮어쓰기
  $filename = sprintf('%s_%s.svg', $date, $interval);
  $filename = sprintf('%s_%s_wide.svg', $date, $interval);  // ver3 저장 시 사용

  // SVG 보정
  if (strpos($svg, '<svg') === false) throw new Exception('invalid svg');
  if (strpos($svg, '<?xml') === false) {
    $svg = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $svg;
  }

  $full = rtrim($ATTACH_DIR, '\\/') . DIRECTORY_SEPARATOR . $filename;
  if (file_put_contents($full, $svg) === false) throw new Exception('write failed');

  // 마크다운에서 사용할 상대경로 (MD_DIR 기준)
  // (Obsidian은 상대경로 이미지 링크를 권장합니다)
  $relPath = $relToMd . $filename;

  echo json_encode(['ok' => true, 'rel' => $relPath, 'abs' => $full]);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
