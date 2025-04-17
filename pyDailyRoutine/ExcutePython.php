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
        .crawl-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px; /* 여백 */
        }
        .crawl-container input {
            flex: 0.4;
            padding: 8px;
        }
        .crawl-container button {
            padding: 8px 16px;
        }

        /* 버튼들을 가로 배치할 컨테이너 */
        .btn-container {
            display: flex;
            flex-wrap: wrap;    /* 화면 좁으면 자동 줄바꿈 */
            gap: 10px;
            margin-bottom: 20px;
        }
        .btn-container button {
            padding: 8px 16px;
            cursor: pointer;
        }

        #result {
            margin-top: 0px;
            white-space: pre-wrap;
            border: 1px solid #ddd;
            padding: 10px;
            height: 880px;
            overflow-y: auto;
        }
        #log {
            margin-top: 10px;
            white-space: pre-wrap;
            border: 1px solid #ddd;
            padding: 10px;
            height: 100px;
            overflow-y: auto;
        }
        h1, h2 {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Python Script Executor</h1>

    <!-- URL 입력 필드 및 크롤링 버튼 -->
    <div class="crawl-container">
        <button onclick="crawlPage()">Crawl Page</button>
        <input type="text" id="urlInput" placeholder="Enter URL" value="">
    </div>

    <div class="btn-container">
        <!-- 원하는 스크립트들을 가로로 배치 -->
        <button onclick="executeScript('pyDailyRoutine/DBUp_XrayTickExe.py')">DBUp_XrayTickExe</button>
        <button onclick="executeScript('pyDailyRoutine/futures_pnl_calculator.py')">Calculator_FuturesPnL</button>
        <!-- <button onclick="executeScript('pyDailyRoutine/DBUp_MochatenList.py')">DBUp_MochatenList</button>
        <button onclick="executeScript('pyDailyRoutine/DBUp_SignalEvening.py')">DBUp_SignalEvening</button>
        <button onclick="executeScript('pyObsidian/Obsidian_ConvertSignalReport.py')">Obsidian_ConvertSignalReport</button>
        <button onclick="executeScript('pyObsidian/WatchList_DBUp.py')">WatchList_DBUp</button>
        <button onclick="executeScript('pyObsidian/Obsidian_DBDownStockInfo.py')">Obsidian_DBDownStockInfo</button> -->
    </div>

    <div id="result">Result will be displayed here...</div>

    <h2>Execution Log (Last 5 lines)</h2>
    <div id="log">Log will be displayed here...</div>

    <script>
        // 크롤링 함수
        function crawlPage() {
            const url = $('#urlInput').val();
            if (url === "") {
                alert("Please enter a URL.");
                return;
            }
            $('#result').text('Crawling page...');
            $.ajax({
                url: 'ExcutePython_script.php',
                type: 'POST',
                data: { url: url },
                success: function(response) {
                    $('#result').html(response);
                    updateLog();
                },
                error: function(xhr, status, error) {
                    $('#result').text('Error: ' + error);
                    console.log('AJAX Error: ', error);
                }
            });
        }

        // 일반 스크립트 실행 함수
        function executeScript(scriptName) {
            $('#result').text('Executing script...');
            $.ajax({
                url: 'ExcutePython_script.php',
                type: 'POST',
                data: { script: scriptName },
                success: function(response) {
                    $('#result').html(response);
                    updateLog();
                },
                error: function(xhr, status, error) {
                    $('#result').text('Error: ' + error);
                }
            });
        }

        // 로그 업데이트
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