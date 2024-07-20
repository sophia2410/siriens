<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$specific_datetime = isset($_GET['specific_datetime']) ? $_GET['specific_datetime'] : date('YmdHi', time());
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'amount_acc_day';
$date = isset($_GET['date']) ? $_GET['date'] : date('Ymd');  // 초기 날짜를 현재 날짜로 설정

// Fetch dates for the select box
$query = "
SELECT date date, CONCAT(yyyy, '년 ', mm, '월 ', dd, '일 ') date_str
FROM calendar
WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
ORDER BY date DESC
LIMIT 50";

$result = $mysqli->query($query);
$dates = [];
while($row = $result->fetch_assoc()) {
    $dates[] = $row;
}

// 필요한 변수들 정의 및 초기화

// 해당 일자 등록 테이블 찾기 (성능 위해 백업 테이블 이동)
$query = "SELECT 'Y' FROM kiwoom_realtime_minute WHERE date = '$date' LIMIT 1";
$result = $mysqli->query($query);
$tableToUse = '';
if ($result->num_rows > 0) {    // 결과가 있는 경우
    $tableToUse = 'kiwoom_realtime_minute';
} else {    // 결과가 빈 경우
    $tableToUse = 'kiwoom_realtime_minute_backup';
}

$where = '';  // 필요한 조건이 있으면 여기에 추가
$minute = date('Hi');  // 현재 시간 분으로 초기화
$min_amount = 1000;  // 최소 거래 대금 (예시)
$detail_cnt = 10;  // 표시할 상세 항목 개수 (예시)

$query = "SELECT * FROM (
    SELECT 
        w.theme, s.code, s.name,
        first_minute, last_minute, rate,
        RANK() OVER(PARTITION BY w.theme ORDER BY ABS(amount_last_min) DESC, ABS(amount_last_1min) DESC, ABS(amount_last_2min) DESC) rank,
        SUM(amount_last_min  ) OVER(PARTITION BY w.theme) AS last_min_theme,
        SUM(amount_last_1min ) OVER(PARTITION BY w.theme) AS last_1min_theme,
        volume_sign_last_min   * amount_last_min   AS amount_last_min,
        volume_sign_last_1min  * amount_last_1min  AS amount_last_1min,
        CASE WHEN h.code IS NULL AND (volume_sign_last_min * amount_last_min) > 0 THEN '★' ELSE '' END AS first_alert
    FROM (
        SELECT
            m.code,
            MIN(minute) AS first_minute,
            MAX(minute) AS last_minute,
            IFNULL(MAX(CASE WHEN minute = t.last_min THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_min,
            IFNULL(MAX(CASE WHEN minute = t.last_1min THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_1min,
            IFNULL(MAX(CASE WHEN minute = t.last_2min THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_2min,
            IFNULL(MAX(CASE WHEN minute = t.last_min THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_1min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_min,
            IFNULL(MAX(CASE WHEN minute = t.last_1min THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_2min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_1min,
            IFNULL(MAX(CASE WHEN minute = t.last_2min THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_3min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_2min,
            IFNULL(MAX(CASE WHEN minute <= t.last_min THEN acc_trade_amount ELSE NULL END), 0) AS amount_acc_day,
            (
                SELECT m2.rate
                FROM $tableToUse m2
                WHERE m2.code = m.code AND 
                      STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') <= t.specific_datetime
                ORDER BY STR_TO_DATE(CONCAT(m2.date, m2.minute), '%Y%m%d%H%i') DESC
                LIMIT 1
            ) AS rate -- 주어진 시간 이전의 가장 최근 rate
        FROM
            $tableToUse m
        JOIN (
            SELECT
                specific_datetime,
                DATE_FORMAT(sd.specific_datetime, '%H%i') AS last_min,
                DATE_FORMAT(sd.specific_datetime - INTERVAL 1  MINUTE, '%H%i') AS last_1min,
                DATE_FORMAT(sd.specific_datetime - INTERVAL 2  MINUTE, '%H%i') AS last_2min,
                DATE_FORMAT(sd.specific_datetime - INTERVAL 3  MINUTE, '%H%i') AS last_3min
            FROM
                (SELECT STR_TO_DATE('$specific_datetime', '%Y%m%d%H%i') AS specific_datetime) sd
        ) t
        WHERE
            m.date = DATE_FORMAT(t.specific_datetime, '%Y%m%d') AND -- Only considering today's data
            m.minute <= DATE_FORMAT(t.specific_datetime, '%H%i')
            $where
        GROUP BY
            m.code
    ) g
    JOIN
        kiwoom_stock s
    ON
        s.code = g.code
    JOIN
        (SELECT code, theme FROM watchlist_sophia WHERE realtime_yn = 'Y' or sector in( '5 끼있는친구들1', '6 끼있는친구들2') GROUP BY code, theme) w
    ON
        w.code = g.code
    LEFT OUTER JOIN 
        (SELECT DISTINCT code FROM telegram_message_history WHERE date = '$date' AND minute <'$minute') h
    ON h.code = g.code
    WHERE
        (g.amount_acc_day > $min_amount OR g.amount_last_min > 500) AND
        g.amount_last_min + g.amount_last_1min + g.amount_last_2min > 0
) final_query
WHERE rank <= $detail_cnt
";
echo "<pre>$query</pre>";
$result = $mysqli->query($query);

$sector_data = [];
while ($row = $result->fetch_assoc()) {
    $sector_data[$row['theme']][] = $row;
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
    </style>
    <script>
        function search() {
            var selectedDate = document.getElementById('date').value;

            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'kiwoomRealtimeBlock.php?date=' + selectedDate, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.open();
                    document.write(xhr.responseText);
                    document.close();
                }
            };
            xhr.send();
        }

        function saveComment() {
            var comment = document.getElementById('market_comment').value;
            var date = document.getElementById('date').value;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?action=save_comment', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    alert('Comment saved successfully!');
                }
            };
            xhr.send('date=' + date + '&comment=' + encodeURIComponent(comment));
        }

        window.onload = function() {
            var select = document.getElementById('date');
            if (select.options.length > 0) {
                var initialDate = select.options[0].value;
                select.value = initialDate;
                // 무한으로 조회되어 일단 막음 24.07.07
                // search(); // Automatically trigger search with the first date
            }
        }
    </script>
</head>
<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>

<!-- Left Content Wrapper -->
<div id="left-content">

    <!-- Controls -->
    <div id="controls">
        <select id="date">
            <?php foreach($dates as $search_date): ?>
                <option value="<?= $search_date['date'] ?>" <?= ($search_date['date'] == $date) ? 'selected' : '' ?>><?= $search_date['date_str'] ?></option>
            <?php endforeach; ?>
        </select>
        <input type="button" class="btn btn-danger btn-sm" value="조회" onclick="search()">
        <span id="evening_subject"></span>
    </div>

    <h2>Issues and Stocks for <?= $date ?></h2>
    <div class="sector-container">
        <?php foreach ($sector_data as $theme => $stocks): ?>
            <table class="sector-table">
                <thead>
                    <tr class="sector-header">
                        <th colspan="4"><?= htmlspecialchars($theme) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stocks as $stock): ?>
                        <tr class="stock-item">
                            <td><?= htmlspecialchars($stock['name']) ?> (<?= htmlspecialchars($stock['code']) ?>)</td>
                            <td>등락률: <?= htmlspecialchars($stock['rate']) ?></td>
                            <td>거래대금(최근 1분): <?= htmlspecialchars($stock['amount_last_min']) ?></td>
                            <td>거래대금(최근 2분): <?= htmlspecialchars($stock['amount_last_1min']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>
</div>
<!-- End of Left Content Wrapper -->

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
