<?php
// flask 실행 체크
$flask_status = exec("tasklist /FI \"IMAGENAME eq python.exe\" /V | findstr app.py");

if ($flask_status) {
    echo json_encode(['status' => 'already running']);
} else {
    pclose(popen("start /B E:/Project/202410/www/modules/futures/api/run_flask.bat", "r"));
    sleep(2);  // 잠깐 대기
    echo json_encode(['status' => 'started']);
}
?>
