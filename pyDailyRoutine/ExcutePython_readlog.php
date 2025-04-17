<?php
// 시간대 설정
date_default_timezone_set('Asia/Seoul');

// 로그 파일 경로
$logFile = 'ExcutePython_log.txt';

// 로그 파일 내용 읽기
if (file_exists($logFile)) {
    // 파일을 배열로 읽어 각 줄을 원소로 저장
    // FILE_IGNORE_NEW_LINES: 각 줄 끝의 개행문자를 제거
    // FILE_SKIP_EMPTY_LINES: 빈 줄은 건너뜀
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // 마지막 5줄만 추출
    $last5 = array_slice($lines, -5);
    
    // 출력
    echo "<pre>";
    foreach ($last5 as $line) {
        echo $line . "\n";
    }
    echo "</pre>";
} else {
    echo "No log available.";
}
?>
