<?php
// 시간대 설정
date_default_timezone_set('Asia/Seoul');

// 로그 파일 경로
$logFile = 'ExcutePython_log.txt';

// 로그 파일 내용 읽기
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    echo "<pre>$logContent</pre>";
} else {
    echo "No log available.";
}
?>
