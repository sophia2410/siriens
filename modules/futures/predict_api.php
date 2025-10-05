<?php
// 로그 함수: 현재 폴더에 predict_debug.log 파일로 기록
function write_debug_log($message) {
    $logFile = __DIR__ . "/predict_debug.log";
    file_put_contents($logFile, date("[Y-m-d H:i:s] ") . $message . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. 입력값 수집
    $open_price = $_POST['open_price'];
    $range_5m   = $_POST['range_5m'];
    $vol_5m     = $_POST['vol_5m'];
    $up_5m      = $_POST['up_5m'];
    $ret_5m     = $_POST['ret_5m'];
    $date       = $_POST['date'];

    // 2. JSON 형태로 구성
    $input = [
        "open_price" => $open_price,
        "range_5m"   => $range_5m,
        "vol_5m"     => $vol_5m,
        "up_5m"      => $up_5m,
        "ret_5m"     => $ret_5m,
        "date"       => $date
    ];

    // 3. Python 경로 및 스크립트 설정
    $pythonExec = "C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe";
    $scriptPath = "E:/Project/202410/www/modules/futures/predict_model.py";

    // 4. JSON 문자열을 Windows cmd용으로 수동 quoting
    $json = json_encode($input);  // {"open_price":"430.25",…}
    $input_json = '"' . str_replace('"', '\"', $json) . '"';
    //   -> "{\"open_price\":\"430.25\", … }"

    // 5. 커맨드 조립 (PythonExec, scriptPath도 각각 따옴표로 감싸주세요)
    $command = "\"{$pythonExec}\" \"{$scriptPath}\" {$input_json}";

    // 6. Python 실행
    write_debug_log("🐍 실행 커맨드: " . $command);
    $output = shell_exec($command);
    write_debug_log("📤 PYTHON OUTPUT: " . $output);

    // 7. 실행 실패 대응
    if (!$output) {
        write_debug_log("❌ Python 실행 실패. 입력값: " . json_encode($input));
        echo json_encode(["error" => "파이썬 실행 오류"]);
        exit;
    }

    // 8. JSON 파싱
    $decoded = json_decode($output, true);
    if ($decoded === null) {
        write_debug_log("❌ JSON 디코딩 실패. 출력 내용: " . $output);
        echo json_encode(["error" => "서버 출력이 JSON 형식이 아닙니다."]);
        exit;
    }

    // 9. 결과 반환
    echo json_encode($decoded);
}
?>
