<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/market/common/functions.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/market/common/fetch_issue_register.php");


// 특정 종목의 내역 조회
$stockName = $_GET['code'] ?? ''; // 여기서 'code' 파라미터는 실제로는 종목명을 의미합니다.
$selectedDate = $_GET['date'] ?? '';
$formattedDate = str_replace('-', '', $selectedDate);

// 종목 내역 조회 (부분 일치 검색)
$stockHistoryQuery = $mysqli->prepare("
    SELECT mis.*, mi.issue, mi.theme, mi.sector, kg.group_name 
    FROM market_issue_stocks mis 
    JOIN market_issues mi ON mis.issue_id = mi.issue_id 
    LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id
    WHERE mis.name LIKE ? 
    ORDER BY mis.date DESC
");
$likeStockName = '%' . $stockName . '%';
$stockHistoryQuery->bind_param('s', $likeStockName);
$stockHistoryQuery->execute();
$stockHistoryResult = $stockHistoryQuery->get_result();

// 특정 일자의 market_issue_stock 조회
$dateQuery = $mysqli->prepare("
    SELECT mis.*, mi.issue, mi.theme, mi.sector, kg.group_name 
    FROM market_issue_stocks mis 
    JOIN market_issues mi ON mis.issue_id = mi.issue_id 
    LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id
    WHERE mis.date = ? 
    ORDER BY mis.issue_id DESC
");
$dateQuery->bind_param('s', $formattedDate);
$dateQuery->execute();
$dateResult = $dateQuery->get_result();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>종목 내역 조회 및 수정</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            height: 100vh;
        }
        #container {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100vh;
        }
        #left-panel, #right-panel {
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        #left-panel {
            flex: 3;
            background-color: #fff;
            border-right: 1px solid #ddd;
        }
        #right-panel {
            flex: 2;
            background-color: #f5f5f5;
        }
        label {
            font-weight: bold;
            margin-top: 10px;
            display: block;
        }
        input[type="text"], input[type="date"], textarea {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            margin-top: 15px;
            padding: 8px 12px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
        }
        button:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
    <script>
        function loadDateDetails(date) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('date', date);
            window.location.search = urlParams.toString();
        }
    </script>
</head>
<body>

<div id="container">
    <!-- 종목 내역 조회 및 수정 -->
    <div id="left-panel">
        <h2>종목 내역 조회 및 수정</h2>
        <form method="get" action="">
            <label for="stockCode">종목명:</label>
            <input type="text" id="stockCode" name="code" placeholder="종목명 입력" value="<?= htmlspecialchars($stockName) ?>" required>
            <button type="submit">조회</button>
        </form>

        <?php if ($stockHistoryResult->num_rows > 0): ?>
            <h3>종목 내역</h3>
            <table>
                <thead>
                    <tr>
                        <th>날짜</th>
                        <th>종목명</th>
                        <th>키워드 그룹</th>
                        <th>이슈</th>
                        <th>테마</th>
                        <th>섹터</th>
                        <th>코멘트</th>
                        <th>주도주 여부</th>
                        <th>수정</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $stockHistoryResult->fetch_assoc()): ?>
                        <tr>
                            <td><a href="#" onclick="loadDateDetails('<?= $row['date'] ?>')"><?= htmlspecialchars($row['date']) ?></a></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['group_name']) ?></td>
                            <td><?= htmlspecialchars($row['issue']) ?></td>
                            <td><?= htmlspecialchars($row['theme']) ?></td>
                            <td><?= htmlspecialchars($row['sector']) ?></td>
                            <td><?= htmlspecialchars($row['stock_comment']) ?></td>
                            <td><?= $row['is_leader'] == '1' ? 'Yes' : 'No' ?></td>
                            <td>
                                <form method="post" action="update_stock.php">
                                    <input type="hidden" name="issue_id" value="<?= $row['issue_id'] ?>">
                                    <input type="hidden" name="date" value="<?= $row['date'] ?>">
                                    <input type="hidden" name="code" value="<?= $row['code'] ?>">
                                    <button type="submit">수정</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>선택한 종목에 대한 내역이 없습니다.</p>
        <?php endif; ?>
    </div>

    <!-- 특정 일자의 종목 내역 조회 -->
    <div id="right-panel">
        <h2>특정 일자 종목 내역 조회</h2>
        <form method="get" action="">
            <label for="reportDate">조회할 날짜:</label>
            <input type="date" id="reportDate" name="date" value="<?= htmlspecialchars($selectedDate) ?>" required>
            <button type="submit">조회</button>
        </form>

        <?php if ($dateResult->num_rows > 0): ?>
			<div id="container">
				<!-- 종목 내역 조회 화면 관련 코드 -->
				<?php include($_SERVER['DOCUMENT_ROOT']."/boot/market/common/display_issue_register.php"); ?>
			</div>
        <?php else: ?>
            <p>선택한 날짜에 대한 내역이 없습니다.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
