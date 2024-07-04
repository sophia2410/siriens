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
    </style>
</head>
<body>
    <h1>Python Script Executor</h1>
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
        function executeScript(scriptName) {
            $('#result').text('Executing script...'); // "실행 중" 메시지 표시
            $.ajax({
                url: 'ExcutePyshon_script.php',
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
