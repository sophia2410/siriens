<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['url'])) {
        // URL이 전송된 경우 Python 스크립트 실행 (Morning Report 크롤링)
        $url = $_POST['url'];
        $command = escapeshellcmd("python3 ExcutePython_MorningRoutine.py $url");
        $output = shell_exec($command);  // 스크립트 실행 결과 받기
        echo $output;  // 결과 반환
    } else if (isset($_POST['script'])) {
        // 다른 스크립트 실행 요청
        $script = $_POST['script'];
        $command = escapeshellcmd("python3 $script");
        $output = shell_exec($command);  // 해당 스크립트 실행 결과 받기
        echo $output;  // 결과 반환
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Python Script Executor</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        button {
            display: block;
            margin: 10px 0;
        }
        #result {
            margin-top: 20px;
            white-space: pre-wrap;
            border: 1px solid #ddd;
            padding: 10px;
            height: 300px; /* 결과 표시 영역 크기 증가 */
            overflow-y: auto; /* 스크롤 가능 */
        }
        #log {
            margin-top: 20px;
            white-space: pre-wrap;
            border: 1px solid #ddd;
            padding: 10px;
            height: 200px; /* 로그 표시 영역 크기 */
            overflow-y: auto; /* 스크롤 가능 */
        }
        .crawl-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .crawl-container input {
            flex: 0.4; /* 남은 공간을 차지하게 함 */
            padding: 8px;
        }
        .crawl-container button {
            padding: 8px;
        }
    </style>
</head>
<body>
    <h1>Python Script Executor</h1>

    <!-- URL 입력 필드 및 크롤링 버튼 추가 -->
    <div class="crawl-container">
        <button onclick="crawlPage()">Crawl Page</button>
        <input type="text" id="urlInput" placeholder="Enter URL" value="https://contents.premium.naver.com/nomadand/nomad/contents/240911081950790dg">
    </div>

    <hr>

    <button onclick="executeScript('pyDailyRoutine/DBUp_XrayTickExe.py')">Execute DBUp_XrayTickExe.py</button>
    <button onclick="executeScript('pyDailyRoutine/DBUp_MochatenList.py')">Execute DBUp_MochatenList.py</button>
    <button onclick="executeScript('pyDailyRoutine/DBUp_SignalEvening.py')">Execute DBUp_SignalEvening.py</button>
    <button onclick="executeScript('pyObsidian/Obsidian_ConvertSignalReport.py')">Execute Obsidian_ConvertSignalReport.py</button>
    <button onclick="executeScript('pyObsidian/WatchList_DBUp.py')">Execute WatchList_DBUp.py</button>
    <button onclick="executeScript('pyObsidian/Obsidian_DBDownStockInfo.py')">Execute Obsidian_DBDownStockInfo.py</button>

    <div id="result">Result will be displayed here...</div>
    <h2>Execution Log</h2>
    <div id="log">Log will be displayed here...</div>

    <script>

        // 크롤링 실행 함수
        function crawlPage() {
            const url = $('#urlInput').val();
            if (url === "") {
                alert("Please enter a URL.");
                return;
            }
            $('#result').text('Crawling page...'); // 크롤링 진행 중 메시지 표시
            $.ajax({
                url: 'ExcutePython_script.php',  // URL을 처리할 PHP 파일
                type: 'POST',
                data: { url: url },  // URL 데이터를 전송
                success: function(response) {
                    $('#result').html(response);  // 성공 시 결과 표시
                    updateLog();  // 로그 업데이트
                },
                error: function(xhr, status, error) {
                    $('#result').text('Error: ' + error);  // 에러 시 메시지 표시
                    console.log('AJAX Error: ', error);  // 콘솔에 에러 출력
                }
            });
        }

        function executeScript(scriptName) {
            $('#result').text('Executing script...'); // "실행 중" 메시지 표시
            $.ajax({
                url: 'ExcutePython_script.php',
                type: 'POST',
                data: { script: scriptName },
                success: function(response) {
                    $('#result').html(response);
                    updateLog(); // 실행 후 로그 업데이트
                },
                error: function(xhr, status, error) {
                    $('#result').text('Error: ' + error);
                }
            });
        }

        // 로그 업데이트 함수
        function updateLog() {
            $.ajax({
                url: 'ExcutePython_readlog.php',
                type: 'GET',
                success: function(response) {
                    $('#log').html(response);
                },
                error: function(xhr, status, error) {
                    $('#log').text('Error: ' + error);
                }
            });
        }

        // 페이지 로드 시 로그 업데이트
        $(document).ready(function() {
            updateLog();
        });
    </script>
</body>
</html>
