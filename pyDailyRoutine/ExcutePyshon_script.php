<?php
// 시간대 설정
date_default_timezone_set('Asia/Seoul');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 최대 실행 시간 설정 (초)
    set_time_limit(300);

    // 요청된 Python 스크립트 파일 이름 가져오기
    $scriptName = $_POST['script'];

    // Python 스크립트 경로
    $scriptPath = "e:/Project/202410/www/$scriptName";

    // Python 실행 경로
    $pythonExec = "C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe";

    // 명령어 생성
    $command = escapeshellcmd("$pythonExec $scriptPath 2>&1");

    // 명령어 실행 및 출력 캡처
    $output = shell_exec($command);

    // 로그 파일에 기록
    $logFile = 'ExcutePython_log.txt';
    $logEntry = date('Y-m-d H:i:s') . " - Executed: $scriptName\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    // 출력 결과 반환
    if (empty(trim($output))) {
        echo "<pre>Script executed successfully with no output.</pre>";
    } else {
        echo "<pre>$output</pre>";
    }
}
?>
