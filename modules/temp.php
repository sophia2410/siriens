<?php
// 시간대 설정
date_default_timezone_set('Asia/Seoul');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 최대 실행 시간 설정 (초)
    set_time_limit(300);

    // Python 실행 경로 설정
    $pythonExec = "C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe";

    // URL 크롤링인지, 일반 스크립트 실행인지 구분
    if (isset($_POST['url'])) {
        // (1) URL이 전송된 경우 (Morning Report 크롤링)
        $url = $_POST['url'];
        $scriptPath = "e:/Project/202410/www/pyDailyRoutine/ExcutePython_MorningRoutine.py";
        $command = escapeshellcmd("$pythonExec $scriptPath $url 2>&1");
        $output = shell_exec($command);

        // 로그 기록
        $logFile = 'ExcutePython_log.txt';
        $logEntry = date('Y-m-d H:i:s') . " - Executed: $scriptPath with URL: $url\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

    } elseif (isset($_POST['script'])) {
        // (2) 일반적인 Python 스크립트 실행
        $scriptName = $_POST['script'];
        $scriptPath = "e:/Project/202410/www/$scriptName";
        $command = escapeshellcmd("$pythonExec $scriptPath 2>&1");
        $output = shell_exec($command);

        // 로그 기록
        $logFile = 'ExcutePython_log.txt';
        $logEntry = date('Y-m-d H:i:s') . " - Executed: $scriptName\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    // (3) 파이썬 스크립트 출력 결과 반환
    //     - 기존에 echo "<pre>$output</pre>"; 로 감싸면 HTML 태그가 문자열 그대로 표시됨.
    //     - HTML 해석을 위해서는 아래처럼 그대로 echo.
    if (empty(trim($output))) {
        echo "<p>Script executed successfully with no output.</p>";
    } else {
        echo $output;  // 여기서 바로 echo하면 파이썬 출력(HTML 등)을 그대로 렌더링 가능
    }
}
?>
