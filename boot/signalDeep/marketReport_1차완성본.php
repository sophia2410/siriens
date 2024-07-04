    <?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

    $report_date = (isset($_GET['report_date'])) ? $_GET['report_date'] : date('Ymd', time());

    // Fetch dates for the select box
    $date_query = "SELECT date report_date, CONCAT(yyyy, '년 ', mm, '월 ', dd, '일 ') report_date_str FROM calendar WHERE date <= NOW() ORDER BY date DESC LIMIT 50";
    $date_result = $mysqli->query($date_query);
    $dates = [];
    while ($row = $date_result->fetch_assoc()) {
        $dates[] = $row;
    }

    // Fetch market comment
    $comment_query = "SELECT market_comment FROM market_report WHERE report_date = '$report_date'";
    $comment_result = $mysqli->query($comment_query);
    $comment_row = $comment_result->fetch_assoc();
    $market_comment = isset($comment_row) ? $comment_row['market_comment'] : '';

    // Fetch sector data
    $sector_query = "SELECT A.sector, A.theme, A.issue, A.hot_theme, B.code, B.name, B.stock_keyword, B.stock_change FROM market_sector A JOIN market_stock B ON B.report_date = A.report_date AND B.sector = A.sector AND B.theme = A.theme WHERE A.report_date = '$report_date'";
    $sector_result = $mysqli->query($sector_query);
    $sector_data = [];
    while ($row = $sector_result->fetch_assoc()) {
        $sector_data[$row['sector']][$row['theme']][] = $row;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Market Report</title>
        <style>
            body, html {
                height: 100%;
                margin: 0;
                display: flex;
            }
            #sidebar {
                width: 230px;
                position: fixed;
                height: 100%;
                background-color: #f4f4f4;
            }
            #content-wrapper {
                margin-left: 230px;
                padding: 20px;
                width: calc(100% - 230px);
                display: flex;
                flex-direction: column;
                overflow-y: auto;
            }
            .comment-section, .sector-container {
                margin-top: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                border: 1px solid #ccc;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f4f4f4;
            }
        </style>
        <script>
            function handleDateChange() {
                var selectedDate = document.getElementById('report_date').value;
                location.href = '?report_date=' + selectedDate;
            }

            window.onload = function() {
                // 페이지가 로드될 때 select box의 값이 URL에 이미 적용되어 있는지 확인
                var select = document.getElementById('report_date');
                var urlDate = new URLSearchParams(window.location.search).get('report_date');
                if (!urlDate && select.value) {
                    // URL에 날짜가 없고, select box에 값이 있으면 페이지를 리로드
                    location.href = '?report_date=' + select.value;
                }
            }
        </script>
    </head>
    <body>
        <div id="sidebar">
            <?php require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php"); ?>
        </div>
        <div id="content-wrapper">
            <select id="report_date" onchange="handleDateChange()">
                <?php foreach ($dates as $date): ?>
                    <option value="<?= $date['report_date'] ?>" <?= ($date['report_date'] == $report_date) ? 'selected' : '' ?>><?= $date['report_date_str'] ?></option>
                <?php endforeach; ?>
            </select>
            <div class="comment-section">
                <h2>Market Comment</h2>
                <textarea id="market_comment"><?= htmlspecialchars($market_comment) ?></textarea>
                <button onclick="saveComment()">Save Comment</button>
            </div>
            <h2>Issues and Stocks for <?= htmlspecialchars($report_date) ?></h2>
            <div class="sector-container">
                <?php foreach ($sector_data as $sector => $themes): ?>
                    <table>
                        <thead>
                            <tr>
                                <th colspan="4"><?= htmlspecialchars($sector) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($themes as $theme => $stocks): ?>
                                <tr>
                                    <td colspan="4"><?= htmlspecialchars($theme) ?> - <?= htmlspecialchars($stocks[0]['issue']) ?></td>
                                </tr>
                                <?php foreach ($stocks as $stock): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($stock['name']) ?></td>
                                        <td><?= htmlspecialchars($stock['code']) ?></td>
                                        <td><?= htmlspecialchars($stock['stock_keyword']) ?></td>
                                        <td><?= htmlspecialchars($stock['stock_change']) ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
            function saveComment() {
                var comment = document.getElementById('market_comment').value;
                var report_date = '<?= $report_date ?>';

                fetch('api_save_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'report_date=' + report_date + '&comment=' + encodeURIComponent(comment)
                })
                .then(response => response.text())
                .then(text => alert(text))
                .catch(error => console.error('Error:', error));
            }
        </script>
    </body>
    </html>
