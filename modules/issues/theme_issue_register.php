<?php
$pageTitle = "테마 조회 및 종목 등록/조회"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");

// 기본적으로 최근 3일을 조회하기 위한 날짜 설정
$today = date('Y-m-d');
$threeDaysAgo = date('Y-m-d', strtotime('-3 days'));

// 조회 조건으로 사용될 일자 파라미터 처리
$fromDate = $_GET['from_date'] ?? $threeDaysAgo;
$toDate = $_GET['to_date'] ?? $today;
$searchTheme = $_GET['theme'] ?? '';
$registerDate = $_GET['register_date'] ?? date('Y-m-d');

// 테마와 일자 기반으로 v_market_issue 조회
$themeIssues = [];

if ($searchTheme) {
    // 테마 입력 시 일자와 관계없이 해당 테마 전체 조회
    $query = "
        SELECT * 
        FROM v_market_issue 
        WHERE theme LIKE CONCAT('%', ?, '%')
        ORDER BY date DESC
    ";
    Database_logQuery($query, [$searchTheme]);

    $themeIssuesQuery = $mysqli->prepare($query);
    $themeIssuesQuery->bind_param('s', $searchTheme);
} else {
    // 기본적으로 최근 3일 조회
    $query ="
        SELECT * 
        FROM v_market_issue 
        WHERE date BETWEEN ? AND ?
        ORDER BY date DESC
    ";
    Database_logQuery($query, [$fromDate, $toDate]);
    
    $themeIssuesQuery = $mysqli->prepare($query);
    $themeIssuesQuery->bind_param('ss', $fromDate, $toDate);
}
$themeIssuesQuery->execute();
$themeIssuesResult = $themeIssuesQuery->get_result();

while ($row = $themeIssuesResult->fetch_assoc()) {
    $themeIssues[] = $row;
}

// 등록된 테마 종목 조회 쿼리
$registeredIssues = [];
$registeredIssuesQuery = $mysqli->prepare("
    SELECT * 
    FROM market_issue_stocks
    WHERE date = ? AND registration_source = 'copy'
");
$registeredIssuesQuery->bind_param('s', $registerDate);
$registeredIssuesQuery->execute();
$registeredIssuesResult = $registeredIssuesQuery->get_result();

while ($row = $registeredIssuesResult->fetch_assoc()) {
    $registeredIssues[] = $row;
}
?>

<head>
    <style>
        #container {
            display: flex;
        }

        #theme_panel, #issue_list_panel {
            padding: 20px;
            flex: 1;
            background-color: #f8f8f8;
            overflow-y: auto;
        }

        #theme_panel {
            border-right: 1px solid #ddd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        .checkbox-cell {
            text-align: center;
        }

        .panel-header {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .form-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            position: sticky;
            top: 0; /* 상단에 고정 */
            background-color: #f8f8f8; /* 배경색을 고정 */
            padding: 10px;
            z-index: 10;
            border-bottom: 1px solid #ddd; /* 구분선 */
        }

        .form-inline input[type="text"], .form-inline input[type="date"] {
            flex: 1;
        }

        .form-inline button {
            margin-left: 10px;
        }

        .form-inline input[type="checkbox"] {
            margin-right: 5px;
        }

        /* 등록 일자 선택 및 버튼 한 줄 표시 */
        .register-inline {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .register-inline {
            position: sticky;
            top: 50px; /* 조회 폼 아래에 고정되도록 */
            background-color: #f8f8f8;
            padding: 10px;
            z-index: 9;
            border-bottom: 1px solid #ddd;
        }
    </style>
</head>

<body>
    <div id="container">
        <!-- 테마 조회 및 종목 선택 패널 (좌측) -->
        <div id="theme_panel">
            <h3 class="panel-header">과거 테마 조회 및 종목 등록</h3>

            <form method="GET" action="" class="form-inline">
                <label for="theme">테마 입력:</label>
                <input type="text" id="theme" name="theme" value="<?= htmlspecialchars($searchTheme) ?>" placeholder="테마 입력">

                <label for="from_date">조회 일자:</label>
                <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
                
                <label for="to_date">~</label>
                <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($toDate) ?>">

                <button type="submit">조회</button>
            </form>

            <form method="POST" action="issue_process.php">
                <input type="hidden" name="action" value="copy_stocks">
                <div class="register-inline">
                    <label for="register_date">등록할 일자:</label>
                    <input type="date" id="register_date" name="register_date" required value="<?= $registerDate ?>">
                    <button type="submit" class="register-button">선택 종목 등록</button>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>선택</th>
                            <th>날짜</th>
                            <th>테마</th>
                            <th>키워드</th>
                            <th>종목명</th>
                            <th>섹터</th>
                            <th>종목코멘트</th>
                            <th>등락률</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($themeIssues as $issue): ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="selected_issues[]" value="<?= htmlspecialchars($issue['stock_code']) ?>">
                                </td>
                                <td><?= htmlspecialchars($issue['date']) ?></td>
                                <td><?= htmlspecialchars($issue['theme']) ?></td>
                                <td><?= htmlspecialchars($issue['keyword_group_name']) ?></td>
                                <td><?= htmlspecialchars($issue['name']) ?></td>
                                <td><?= htmlspecialchars($issue['sector']) ?></td>
                                <td><?= htmlspecialchars($issue['stock_comment']) ?></td>
                                <td><?= htmlspecialchars($issue['close_rate']) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- 등록된 테마 종목 조회 패널 (우측) -->
        <div id="issue_list_panel">
            <h3 class="panel-header">선택한 일자에 등록된 테마 종목</h3>
            <form method="GET" action="" class="form-inline">
                <label for="register_date">조회할 일자 선택:</label>
                <input type="date" id="register_date" name="register_date" required value="<?= $registerDate ?>">
                <button type="submit">조회</button>
            </form>

            <?php if (count($registeredIssues) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>종목명</th>
                            <th>코드</th>
                            <th>등락률</th>
                            <th>거래대금</th>
                            <th>등록일자</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registeredIssues as $issue): ?>
                            <tr>
                                <td><?= htmlspecialchars($issue['name']) ?></td>
                                <td><?= htmlspecialchars($issue['code']) ?></td>
                                <td><?= htmlspecialchars($issue['close_rate']) ?>%</td>
                                <td><?= number_format($issue['trade_amount']) ?>억</td>
                                <td><?= htmlspecialchars($issue['date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>선택된 일자에 등록된 종목이 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
