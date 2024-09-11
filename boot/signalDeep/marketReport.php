<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$report_date = (isset($_GET['report_date'])) ? $_GET['report_date'] : date('Ymd', time());

// Fetch dates for the select box
$query = "
SELECT date report_date, CONCAT(yyyy, '년 ', mm, '월 ', dd, '일 ') report_date_str
FROM calendar
WHERE date <= NOW()
ORDER BY date DESC
LIMIT 50";

$result = $mysqli->query($query);
$dates = [];
while($row = $result->fetch_assoc()) {
    $dates[] = $row;
}

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

$sector_data = [];
while ($row = $sector_result->fetch_assoc()) {
    $sector_data[$row['sector']][$row['theme']][] = $row;
}

// Fetch recent 5 days themes and stocks
$recent_themes_query = "
    SELECT A.theme, B.code, B.name
    FROM market_sector A
    JOIN market_stock B
    ON B.report_date = A.report_date
    AND B.sector = A.sector
    AND B.theme = A.theme
    WHERE A.report_date BETWEEN DATE_ADD('$report_date', INTERVAL -5 DAY) AND '$report_date'
    GROUP BY A.theme, B.code, B.name";
$recent_themes_result = $mysqli->query($recent_themes_query);
$recent_themes = [];
while($row = $recent_themes_result->fetch_assoc()) {
    $recent_themes[] = $row;
}

// Fetch stocks with more than 20% change
$stocks_20_query = "
    SELECT name, stock_change
    FROM market_stock
    WHERE stock_change > 20
    AND   report_date BETWEEN DATE_ADD('$report_date', INTERVAL -5 DAY) AND '$report_date'
    ORDER BY stock_change DESC";
$stocks_20_result = $mysqli->query($stocks_20_query);
$stocks_20 = [];
while($row = $stocks_20_result->fetch_assoc()) {
    $stocks_20[] = $row;
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
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }
        #wrapper {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100%;
        }
        #left-content, #right-content {
            padding: 20px;
        }
        #left-content {
            width: 80%;
        }
        #right-content {
            width: 20%;
            background: #f8f9fa;
            border-left: 1px solid #ddd;
            overflow-y: auto;
        }
        #controls {
            display: flex;
            align-items: center;
            padding: 10px;
            background: white;
            border-bottom: 1px solid #ccc;
        }
        #controls select, #controls input {
            margin-right: 10px;
        }
        .comment-section, .recent-themes-section, .stocks-20-section {
            margin-bottom: 20px;
        }
        textarea {
            width: 100%;
            height: 100px;
            margin-bottom: 20px;
        }
        .sector-container {
            display: flex;
            flex-wrap: wrap;
        }
        .sector-table {
            border-collapse: collapse;
            flex: 1 1 calc(50% - 40px); /* Adjust width as needed */
            margin: 20px;
            min-width: 300px;
            border: 1px solid black;
        }
        .sector-table th, .sector-table td {
            border: 1px solid black;
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
        .stock-item td {
            padding-left: 20px;
        }
        .right-section-table {
            width: 100%;
            border-collapse: collapse;
        }
        .right-section-table th, .right-section-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .right-section-header {
            background-color: #f2f2f2;
            font-weight: bold;
        }
    </style>

    <script>
        function search() {
            var selectedDate = document.getElementById('report_date').value;
            window.location.href = 'marketReport.php?report_date=' + selectedDate;
        }

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

        function getParameterByName(name) {
            var url = window.location.href;
            name = name.replace(/[\[\]]/g, '\\$&');
            var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
                results = regex.exec(url);
            if (!results) return null;
            if (!results[2]) return '';
            return decodeURIComponent(results[2].replace(/\+/g, ' '));
        }

        window.onload = function() {
            var reportDate = getParameterByName('report_date');
            if (!reportDate) {
                var select = document.getElementById('report_date');
                if (select.options.length > 0) {
                    var initialDate = select.options[0].value;
                    select.value = initialDate;
                    search(); // Automatically trigger search with the first date
                }
            }
        }
    </script>
</head>
<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_nav_menu.php");
?>

<!-- Left Content Wrapper -->
<div id="left-content">

    <!-- Controls -->
    <div id="controls">
        <select id="report_date" onchange="search()">
            <?php foreach($dates as $date): ?>
                <option value="<?= $date['report_date'] ?>" <?= ($date['report_date'] == $report_date) ? 'selected' : '' ?>><?= $date['report_date_str'] ?></option>
            <?php endforeach; ?>
        </select>
        <span id="evening_report_title"></span>
    </div>

    <!-- Main Content -->
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
<!-- End of Left Content Wrapper -->

<!-- Right Content Wrapper -->
<div id="right-content">
    <div class="recent-themes-section">
        <h3>최근 5일간의 테마와 종목</h3>
        <table class="right-section-table">
            <thead>
                <tr class="right-section-header">
                    <th>날짜</th>
                    <th>테마</th>
                    <th>종목</th>
                    <th>변동</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent_themes as $theme): ?>
                    <tr>
                        <td><?= htmlspecialchars($theme['report_date']) ?></td>
                        <td><?= htmlspecialchars($theme['theme']) ?></td>
                        <td><?= htmlspecialchars($theme['stock_name']) ?></td>
                        <td><?= htmlspecialchars($theme['stock_change']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="stocks-20-section">
        <h3>20% 이상 종목</h3>
        <table class="right-section-table">
            <thead>
                <tr class="right-section-header">
                    <th>종목</th>
                    <th>변동</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($stocks_20 as $stock): ?>
                    <tr>
                        <td><?= htmlspecialchars($stock['stock_name']) ?></td>
                        <td><?= htmlspecialchars($stock['stock_change']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- End of Right Content Wrapper -->

</div>
<!-- End of Page Wrapper -->
</body>

<?php
if($_SERVER["HTTP_HOST"] == 'localhost') {
    $PATH = "http://localhost";
} else {
    $PATH = "https://siriens.mycafe24.com";
}
?>

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>

</html>
