<?php
// 시간대 설정
date_default_timezone_set('Asia/Seoul');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 최대 실행 시간 설정 (초)
    set_time_limit(300);

    // Python 실행 경로 설정
    $pythonExec = "C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe";

    // 요청이 URL 크롤링인지 스크립트 실행인지 구분
    if (isset($_POST['url'])) {
        // URL이 전송된 경우 (Morning Report 크롤링 처리)
        $url = $_POST['url'];

        // Python 스크립트 경로 (Morning Report 크롤링 전용)
        $scriptPath = "e:/Project/202410/www/pyDailyRoutine/ExcutePython_MorningRoutine.py";

        // 명령어 생성 (URL을 인자로 전달)
        $command = escapeshellcmd("$pythonExec $scriptPath $url 2>&1");

        // 명령어 실행 및 출력 캡처
        $output = shell_exec($command);

        // 로그 파일에 기록
        $logFile = 'ExcutePython_log.txt';
        $logEntry = date('Y-m-d H:i:s') . " - Executed: $scriptPath with URL: $url\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

    } elseif (isset($_POST['script'])) {
        // 일반적인 Python 스크립트 실행 처리
        $scriptName = $_POST['script'];

        // Python 스크립트 경로
        $scriptPath = "e:/Project/202410/www/$scriptName";

        // 명령어 생성
        $command = escapeshellcmd("$pythonExec $scriptPath 2>&1");

        // 명령어 실행 및 출력 캡처
        $output = shell_exec($command);

        // 로그 파일에 기록
        $logFile = 'ExcutePython_log.txt';
        $logEntry = date('Y-m-d H:i:s') . " - Executed: $scriptName\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    // 출력 결과 반환
    if (empty(trim($output))) {
        echo "<pre>Script executed successfully with no output.</pre>";
    } else {
        echo "<pre>$output</pre>";
    }
}
?>
