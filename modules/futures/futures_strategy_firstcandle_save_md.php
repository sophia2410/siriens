<?php
// /exports/save_md.php
header('Content-Type: application/json; charset=utf-8');

try {
  // ======= 환경설정 =======
  $MD_DIR = 'D:\\Obsidian\\Trader Sophia\\☆ Futures\\♧ 전략-1분봉5,20이평기준';

  // ======= 입력 =======
  $raw = file_get_contents('php://input');
  if (!$raw) throw new Exception('empty body');
  $req = json_decode($raw, true);
  if (!is_array($req)) throw new Exception('invalid json');

  $mdText   = $req['md']         ?? null;
  $rsiFrom  = $req['rsi_from']   ?? null;
  $rsiTo    = $req['rsi_to']     ?? null;
  $gapFrom  = $req['gap_from']   ?? null;
  $gapTo    = $req['gap_to']     ?? null;

  if (!$mdText) throw new Exception('missing md');

  // 파일명: RSI{from}-{to}_GAP{from}-{to}_{YYYYMMDD_HHMM}.md
  $ts = date('Ymd_Hi');
  $rf = ($rsiFrom === null ? 'NA' : $rsiFrom);
  $rt = ($rsiTo   === null ? 'NA' : $rsiTo);
  $gf = ($gapFrom === null ? 'NA' : $gapFrom);
  $gt = ($gapTo   === null ? 'NA' : $gapTo);
  $file = sprintf('RSI%s-%s_GAP%s-%s_%s.md', $rf, $rt, $gf, $gt, $ts);

  if (!is_dir($MD_DIR) && !mkdir($MD_DIR, 0775, true)) {
    throw new Exception('cannot create md dir');
  }

  $full = rtrim($MD_DIR, '\\/') . DIRECTORY_SEPARATOR . $file;
  if (file_put_contents($full, $mdText) === false) throw new Exception('write failed');

  echo json_encode(['ok' => true, 'path' => $full, 'file' => $file]);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
