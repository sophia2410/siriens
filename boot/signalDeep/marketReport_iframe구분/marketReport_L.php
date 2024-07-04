<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: row;
            height: 100vh;
        }
        #content-wrapper {
            margin-left: 230px; /* Same as the sidebar width */
            padding: 20px;
            width: (100% - 230px);
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow-y: auto;
        }
        textarea {
            width: 100%;
            height: 100px;
            margin-bottom: 20px;
        }
        .sector-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .sector-table {
            border-collapse: collapse;
            flex: 1 1 calc(33.333% - 20px); /* Adjust width as needed */
            margin-bottom: 20px;
            min-width: 300px;
            border: 1px solid black;
        }
        .sector-table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .sector-header {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .theme-header {
            background-color: #e6f7ff;
        }
        .stock-item {
            padding-left: 20px;
        }
        .comment-section {
            margin-bottom: 20px;
        }
    </style>
    <script>
        function saveComment() {
            var comment = document.getElementById('market_comment').value;
            var report_date = document.getElementById('report_date').value;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?action=save_comment', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    alert('Comment saved successfully!');
                }
            };
            xhr.send('report_date=' + report_date + '&comment=' + encodeURIComponent(comment));
        }
    </script>
</head>
<body>
    <div id="content-wrapper">
        <?php
            require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

            $report_date = $_GET['report_date'];

            // Fetch market comment
            $comment_query = "SELECT market_comment FROM market_report WHERE report_date = '$report_date'";
            $comment_result = $mysqli->query($comment_query);
            $comment_row = $comment_result->fetch_assoc();
            $market_comment = (isset($comment_row)) ? $comment_row['market_comment'] : '';

            // Fetch sector data
            $sector_query = "
                SELECT A.sector, A.theme, A.issue, A.hot_theme, B.code, B.name, B.stock_keyword, B.stock_change
                FROM market_sector A
                JOIN market_stock B
                ON B.report_date = A.report_date
                AND B.sector = A.sector
                AND B.theme = A.theme
                WHERE A.report_date = '$report_date'";
            $sector_result = $mysqli->query($sector_query);

            // echo "<pre>$sector_query</pre>";

            $sector_data = [];
            while ($row = $sector_result->fetch_assoc()) {
                $sector_data[$row['sector']][$row['theme']][] = $row;
            }
        ?>

        <div class="comment-section">
            <h2>Market Comment</h2>
            <textarea id="market_comment"><?= htmlspecialchars($market_comment) ?></textarea>
            <button onclick="saveComment()">Save Comment</button>
        </div>

        <h2>Issues and Stocks for <?= $report_date ?></h2>

        <div class="sector-container">
            <?php foreach ($sector_data as $sector => $themes): ?>
                <table class="sector-table">
                    <thead>
                        <tr class="sector-header">
                            <th colspan="3"><?= htmlspecialchars($sector) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($themes as $theme => $stocks): ?>
                            <tr class="theme-header">
                                <td colspan="3"><?= htmlspecialchars($theme) ?> - <?= htmlspecialchars($stocks[0]['issue']) ?></td>
                            </tr>
                            <?php foreach ($stocks as $stock): ?>
                                <tr class="stock-item">
                                    <td><?= htmlspecialchars($stock['name']) ?> (<?= htmlspecialchars($stock['code']) ?>)</td>
                                    <td><?= htmlspecialchars($stock['stock_keyword']) ?></td>
                                    <td><?= htmlspecialchars($stock['stock_change']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
