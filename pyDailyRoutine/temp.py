<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// Fetching issues and stocks data
$dateParam = $_GET['date'] ?? date('Y-m-d');
$formattedDate = str_replace('-', '', $dateParam);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $date = isset($_POST['report_date']) ? str_replace('-', '', $_POST['report_date']) : date('Ymd');
    $issue = $_POST['issue'] ?? '';
    $first_occurrence = isset($_POST['new_issue']) ? 'Y' : 'N'; // Fixed the checkbox retrieval
    $link = $_POST['link'] ?? '';
    $sector = $_POST['sector'] ?? '';
    $theme = $_POST['theme'] ?? '';
    $hot_theme = isset($_POST['hot_theme']) ? 'Y' : 'N';

    $keywords_input = $_POST['keyword'] ?? '';
    $keywords = array_unique(array_filter(array_map('trim', explode('#', $keywords_input))));

    if (count($keywords) > 0) {
        $keyword_ids = [];

        // 각 키워드에 대한 ID 조회 또는 등록
        foreach ($keywords as $keyword) {
            $stmt = $mysqli->prepare("SELECT keyword_id FROM keyword WHERE keyword = ?");
            $stmt->bind_param('s', $keyword);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $keyword_ids[] = $row['keyword_id'];
            } else {
                $stmt = $mysqli->prepare("INSERT INTO keyword (keyword) VALUES (?)");
                $stmt->bind_param('s', $keyword);
                $stmt->execute();
                $keyword_id = $mysqli->insert_id;
                $keyword_ids[] = $keyword_id;
            }
        }

        // 키워드 ID를 정렬하여 조합이 동일한지 확인
        sort($keyword_ids);
        $keyword_ids_str = implode(',', $keyword_ids);

        // 기존 그룹 ID 조회
        $stmt = $mysqli->prepare("
            SELECT group_id 
            FROM (
                SELECT group_id, GROUP_CONCAT(keyword_id ORDER BY keyword_id ASC) as ids
                FROM keyword_group_mappings
                GROUP BY group_id
            ) AS sub
            WHERE ids = ?
        ");
        $stmt->bind_param('s', $keyword_ids_str);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // 동일한 키워드 조합의 그룹이 있는 경우, 그룹명을 업데이트
            $group_id = $row['group_id'];
            $group_name = implode(' ', array_map(fn($kw) => "#{$kw}", $keywords));
            $stmt = $mysqli->prepare("UPDATE keyword_groups SET group_name = ? WHERE group_id = ?");
            $stmt->bind_param('si', $group_name, $group_id);
            $stmt->execute();
        } else {
            // 동일한 조합이 없는 경우 새 그룹 생성
            $group_name = implode(' ', array_map(fn($kw) => "#{$kw}", $keywords));
            $stmt = $mysqli->prepare("INSERT INTO keyword_groups (group_name) VALUES (?)");
            $stmt->bind_param('s', $group_name);
            $stmt->execute();
            $group_id = $mysqli->insert_id;

            // 키워드와 그룹 연결
            foreach ($keyword_ids as $keyword_id) {
                $stmt = $mysqli->prepare("INSERT INTO keyword_group_mappings (group_id, keyword_id, create_dtime) VALUES (?, ?, NOW())");
                $stmt->bind_param('ii', $group_id, $keyword_id);
                $stmt->execute();
            }
        }
    }

    $stocks = $_POST['stocks'] ?? []; // stocks 데이터를 폼에서 받아옴

    $mysqli->autocommit(FALSE); // 트랜잭션 시작

    try {
        if ($_POST['action'] === 'register') {
            // market_issues 테이블에 동일한 데이터 존재 여부 확인
            $stmt = $mysqli->prepare("SELECT issue_id FROM market_issues WHERE date = ? AND keyword_group_id = ? AND issue = ? AND sector = ? AND theme = ?");
            $stmt->bind_param('sisss', $date, $group_id, $issue, $sector, $theme);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // 이미 동일한 데이터가 존재하는 경우, 기존 ID 사용
                $issue_id = $row['issue_id'];
            } else {
                // 새롭게 데이터 삽입
                $stmt = $mysqli->prepare("INSERT INTO market_issues (date, issue, first_occurrence, link, sector, theme, hot_theme, create_dtime, keyword_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->bind_param('sssssssi', $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id);
                if (!$stmt->execute()) {
                    throw new Exception("market_issues 삽입 실패: " . $stmt->error);
                }
                $issue_id = $mysqli->insert_id;
            }
        } elseif ($_POST['action'] === 'update' && isset($_POST['issue_id'])) {
            // 수정 로직 추가
            $issue_id = $_POST['issue_id'];
            $stmt = $mysqli->prepare("UPDATE market_issues SET date = ?, issue = ?, first_occurrence = ?, link = ?, sector = ?, theme = ?, hot_theme = ?, keyword_group_id = ? WHERE issue_id = ?");
            $stmt->bind_param('sssssssii', $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id, $issue_id);
            if (!$stmt->execute()) {
                throw new Exception("market_issues 업데이트 실패: " . $stmt->error);
            }

            // 기존 종목 데이터 삭제 (필요 시, 업데이트로 대체 가능)
            $stmt = $mysqli->prepare("DELETE FROM market_issue_stocks WHERE issue_id = ?");
            $stmt->bind_param('i', $issue_id);
            if (!$stmt->execute()) {
                throw new Exception("market_issue_stocks 삭제 실패: " . $stmt->error);
            }
        }

        // 각 종목 등록
        foreach ($stocks as $stock) {
            $code = $stock['code'] ?? '';
            $stock_name = $stock['name'] ?? '';
            $stock_comment = $stock['comment'] ?? '';

            error_log("Checking stock: code = $code, name = $stock_name");

            if ($code === '') {
                error_log("Stock code is empty");
            }

            if ($stock_name === '') {
                error_log("Stock name is empty");
            }

            if ($code !== '' && $stock_name !== '') { // code와 name이 유효한지 확인
                error_log(print_r($code, true));
                error_log(print_r($stock_name, true));

                // market_issue_stocks 테이블에 동일한 데이터 존재 여부 확인
                $stmt = $mysqli->prepare("SELECT 1 FROM market_issue_stocks WHERE issue_id = ? AND code = ?");
                $stmt->bind_param('is', $issue_id, $code);
                $stmt->execute();
                $result = $stmt->get_result();

                if (!$result->fetch_assoc()) {
                    // 동일한 데이터가 존재하지 않는 경우에만 삽입
                    $stmt = $mysqli->prepare("INSERT INTO market_issue_stocks (issue_id, code, stock_name, stock_comment, create_dtime) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param('isss', $issue_id, $code, $stock_name, $stock_comment);
                    if (!$stmt->execute()) {
                        throw new Exception("market_issue_stocks 삽입 실패: " . $stmt->error);
                    }
                }
            }
        }

        $mysqli->commit(); // 트랜잭션 커밋
    } catch (Exception $e) {
        $mysqli->rollback(); // 오류 발생 시 롤백
        echo "<script>alert('데이터 등록 실패: {$e->getMessage()}');</script>";
    }

    $mysqli->autocommit(TRUE); // 자동 커밋 모드 복원

    echo "<script>alert('이슈 및 종목이 등록되었습니다.');</script>";
    $selected_date = $_POST['report_date'];
    header("Location: market_issue_register.php?date=$selected_date");
    exit;
}

// 마켓이슈 불러오기
$issuesQuery = $mysqli->prepare("SELECT mi.*, kg.group_name FROM market_issues mi LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id WHERE mi.date = ? ORDER BY mi.date DESC");
$issuesQuery->bind_param('s', $formattedDate);
$issuesQuery->execute();
$issuesResult = $issuesQuery->get_result();

// Preparing to fetch stocks data for each issue
$stocksData = [];
while ($issue = $issuesResult->fetch_assoc()) {
    $issueId = $issue['issue_id'];
    $stocksQuery = $mysqli->prepare("SELECT * FROM market_issue_stocks WHERE issue_id = ?");
    $stocksQuery->bind_param('i', $issueId);
    $stocksQuery->execute();
    $stocksResult = $stocksQuery->get_result();

    while ($stock = $stocksResult->fetch_assoc()) {
        $stocksData[$issueId][] = $stock;
    }
}

// 상승률 높은 종목 불러오기
$query = "SELECT nfs.code, nfs.name, nfs.change_rate, ROUND(nfs.volume * nfs.current_price / 100000000, 2) AS turnover,
          CASE WHEN mis.code IS NOT NULL THEN 1 ELSE 0 END AS registered
          FROM naver_finance_stock nfs
          LEFT JOIN market_issue_stocks mis ON nfs.code = mis.code
          LEFT JOIN market_issues mi ON mis.issue_id = mi.issue_id AND mi.date = ?
          WHERE nfs.date = ? AND
          ((nfs.change_rate > 10 AND nfs.volume * nfs.current_price > 5000000000) OR
           (nfs.change_rate > 29.5 AND nfs.volume * nfs.current_price > 3000000000))";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('ss', $formattedDate, $formattedDate);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>이슈 및 테마 등록 및 조회</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            display: flex;
            height: 100vh;
        }
        #container {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100vh; /* Adjust based on your design */
        }
        #left-panel, #middle-panel, #right-panel {
            flex: 1; /* Adjust the flex value if you want different widths for each panel */
            overflow-y: auto;
            padding: 20px;
        }
        #left-panel {
            background-color: #fff;
            border-right: 1px solid #ddd;
        }
        #middle-panel {
            background-color: #e8e8e8; /* Slightly different background for distinction */
            border-right: 1px solid #ddd;
        }
        #right-panel {
            background-color: #f8f8f8;
        }
        label {
            font-weight: bold;
            margin-top: 10px;
            display: block;
        }
        input[type="text"], input[type="date"], textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        input[type="checkbox"] {
            margin-right: 5px;
        }
        button {
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 4px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .stock-item {
            margin-bottom: 15px;
        }
        .stock-row {
            display: flex;
            align-items: center;
        }
        .stock-row input[type="text"] {
            margin-right: 10px;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 20px !important;
        }
        th, td {
            border: 1px solid #ddd !important;
            padding: 8px !important;
            text-align: left !important;
        }
        th {
            background-color: #f2f2f2 !important;
        }
        #theme-table {
            margin-top: 40px;
        }
        .accordion-content {
            display: ''; /* Initially hidden */
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script>
        function loadIssueDetails(issueId) {
            $.ajax({
                url: 'fetch_issue_details.php',
                type: 'GET',
                data: { issue_id: issueId },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.issueDetails) {
                        // 이슈 상세 정보를 폼에 셋팅
                        $('#report_date').val(data.issueDetails.date);
                        $('#keyword').val(data.issueDetails.keyword_group_name); // Updated to use the group name
                        $('#issue').val(data.issueDetails.issue);
                        $('#theme').val(data.issueDetails.theme);
                        $('#sector').val(data.issueDetails.sector);
                        $('#hot_theme').prop('checked', data.issueDetails.hot_theme === 'Y');
                        $('#new_issue').prop('checked', data.issueDetails.first_occurrence === 'Y');
                        $('#action').val('update');
                        $('#issue_id').val(data.issueDetails.issue_id);
                    }

                    if (data.stocks && data.stocks.length > 0) {
                        // 관련 종목 정보 셋팅
                        const container = $('#stocks-container');
                        container.empty(); // 기존 종목 정보를 초기화
                        data.stocks.forEach((stock, index) => {
                            addStock(index, stock.code, stock.name, stock.comment); // Pass stock data
                        });
                    }
                }
            });
        }

        function addStock(stockIndex, code = '', name = '', comment = '') {
            const container = document.getElementById('stocks-container');
            const newStock = document.createElement('div');
            newStock.className = 'stock-item';
            newStock.innerHTML = `
                <div class="stock-row">
                    <input type="text" name="stocks[${stockIndex}][name]" value="${name}" onkeydown="searchStock(event, this)" placeholder="종목명/코드" required style="flex: 2; margin-right: 10px;">
                    <input type="text" name="stocks[${stockIndex}][code]" value="${code}" readonly placeholder="코드" style="flex: 1; margin-right: 10px;">
                    <button type="button" onclick="removeStock(this)" style="margin-left: 10px;">삭제</button>
                </div>
                <div class="stock-comment">
                    <input type="text" name="stocks[${stockIndex}][comment]" value="${comment}" placeholder="코멘트" style="width: 100%; margin-top: 10px;" autocomplete="off" onfocus="fetchCommentSuggestions(this)">
                </div>
            `;
            container.appendChild(newStock);
            stockIndex++; // 인덱스 증가
        }

        // 종목 삭제 기능
        function removeStock(button) {
            const stockItem = button.parentNode.parentNode; // stock-item div
            stockItem.parentNode.removeChild(stockItem);
        }

        document.addEventListener('DOMContentLoaded', function() {
            let stockIndex = 0; // 초기 인덱스 설정

            // 초기 종목을 1개 추가
            addStock(stockIndex);

            // 종목 추가 버튼 클릭 시 호출
            document.getElementById('add-stock-button').addEventListener('click', () => addStock(stockIndex++));

            // 키워드 자동완성
            $("#keyword").on('input', function() {
                let query = $(this).val();
                if (query.length > 0) {
                    $.ajax({
                        url: 'fetch_data.php',
                        type: 'GET',
                        data: { type: 'keywords', q: query },
                        success: function(data) {
                            let keywordGroups = JSON.parse(data);
                            $("#keyword").autocomplete({
                                source: keywordGroups
                            });
                        }
                    });
                }
            });

            // 테마 및 섹터 자동완성
            $("#theme").focus(function() {
                const keywordGroup = $("#keyword").val().trim();
                $.ajax({
                    url: 'fetch_data.php',
                    type: 'GET',
                    data: { type: 'theme_sector', q: keywordGroup },
                    success: function(data) {
                        const suggestions = JSON.parse(data);
                        const themes = suggestions.map(item => item.theme).filter(Boolean);

                        // Initialize autocomplete with the fetched themes
                        $("#theme").autocomplete({
                            source: themes,
                            minLength: 0  // Ensure that suggestions show even for empty input
                        }).autocomplete("search", ""); // Trigger autocomplete
                    }
                });
            });

            $("#sector").focus(function() {
                const keywordGroup = $("#keyword").val().trim();
                $.ajax({
                    url: 'fetch_data.php',
                    type: 'GET',
                    data: { type: 'theme_sector', q: keywordGroup },
                    success: function(data) {
                        const suggestions = JSON.parse(data);
                        const sectors = suggestions.map(item => item.sector).filter(Boolean);

                        // Initialize autocomplete with the fetched sectors
                        $("#sector").autocomplete({
                            source: sectors,
                            minLength: 0 // Ensure that suggestions show even for empty input
                        }).autocomplete("search", ""); // Trigger autocomplete
                    }
                });
            });

            // Fetch comments for a stock
            window.fetchCommentSuggestions = function(input) {
                const stockRow = input.closest('.stock-item').querySelector('input[name^="stocks"][name$="[code]"]');
                const stockCode = stockRow.value;

                if (stockCode) {
                    $.ajax({
                        url: 'fetch_data.php',
                        type: 'GET',
                        data: { type: 'stock_comments', code: stockCode },
                        success: function(data) {
                            const comments = JSON.parse(data);
                            $(input).autocomplete({
                                source: comments,
                                minLength: 0 // Ensure that suggestions show even for empty input
                            }).autocomplete("search", ""); // Trigger autocomplete
                        }
                    });
                }
            };

            var toggleButton = document.getElementById('toggle-all');
            var allContents = document.querySelectorAll('.accordion-content');
            var isExpanded = false; // 초기 상태는 접혀있는 상태

            toggleButton.addEventListener('click', function() {
                isExpanded = !isExpanded; // 토글 상태 업데이트
                allContents.forEach(function(content) {
                    if (isExpanded) {
                        content.style.display = 'table-row'; // 모든 아코디언 항목 표시
                    } else {
                        content.style.display = 'none'; // 모든 아코디언 항목 숨김
                    }
                });
                toggleButton.textContent = isExpanded ? '모든 종목 접기' : '모든 종목 펼치기'; // 버튼 텍스트 업데이트
            });

            document.getElementById('report_date').addEventListener('change', function() {
                window.location.href = 'market_issue_register.php?date=' + this.value;
            });
        });
        

        async function fetchStocks(query) {
            try {
                const response = await fetch(`fetch_stocks.php?q=${encodeURIComponent(query)}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                console.log("Data received from fetch_stocks.php:", data);
                return data;
            } catch (error) {
                console.error("Error fetching stocks:", error);
                return [];
            }
        }

        function showStockPopup(stocks, nameInput, codeInput) {
            console.log("Showing stock selection popup.");
            const popup = document.createElement('div');
            popup.style.position = 'fixed';
            popup.style.top = '50%';
            popup.style.left = '50%';
            popup.style.transform = 'translate(-50%, -50%)';
            popup.style.backgroundColor = '#fff';
            popup.style.padding = '20px';
            popup.style.boxShadow = '0 0 10px rgba(0, 0, 0, 0.5)';
            popup.style.zIndex = 1000;
            popup.style.maxHeight = '400px';
            popup.style.overflowY = 'auto';

            stocks.forEach(stock => {
                const btn = document.createElement('button');
                btn.innerText = `${stock.name} (${stock.code})`;
                btn.style.display = 'block';
                btn.style.width = '100%';
                btn.style.margin = '5px 0';
                btn.onclick = () => {
                    console.log(`Stock selected: ${stock.name} (${stock.code})`);
                    nameInput.value = stock.name;
                    codeInput.value = stock.code;
                    document.body.removeChild(popup); // 선택 시 팝업 닫기
                };
                popup.appendChild(btn);
            });

            const closeBtn = document.createElement('button');
            closeBtn.innerText = '닫기';
            closeBtn.style.display = 'block';
            closeBtn.style.width = '100%';
            closeBtn.style.margin = '5px 0';
            closeBtn.onclick = () => {
                console.log("Closing popup.");
                document.body.removeChild(popup);
            };
            popup.appendChild(closeBtn);

            document.body.appendChild(popup);
        }

        async function searchStock(event, input) {
            if (event.key === 'Enter') { // 엔터 키가 눌렸을 때만 처리
                event.preventDefault(); // 폼 제출 방지
                console.log("Enter key pressed, initiating search...");
                const query = input.value.trim();
                console.log(`Searching for stocks with query: ${query}`);
                const codeInput = input.nextElementSibling; // 코드 입력 필드
                if (query.length > 0) { // 검색어가 비어있지 않은 경우에만 검색 수행
                    const stocks = await fetchStocks(query);
                    console.log("Stocks fetched:", stocks);
                    if (stocks.length === 1) {
                        input.value = stocks[0].name; // 입력 칸에 종목명 설정
                        codeInput.value = stocks[0].code; // 코드 설정
                    } else if (stocks.length > 1) {
                        console.log("Multiple stocks found, showing popup.");
                        showStockPopup(stocks, input, codeInput);
                    } else {
                        console.log("No stocks found for the query.");
                        alert("해당 종목명을 찾을 수 없습니다.");
                    }
                } else {
                    console.log("Search query is empty.");
                }
            }
        }

        // 뉴스 스크랩 기능
        async function scrapNews(theme) {
            const url = document.getElementById('news_url_' + theme).value;
            try {
                const response = await fetch(`get_title.php?url=${encodeURIComponent(url)}`);
                const data = await response.json();
                if (data.success) {
                    alert(`제목: ${data.title} | URL: ${data.url}`);
                    // 데이터베이스 저장 처리
                    const formData = new FormData();
                    formData.append('title', data.title);
                    formData.append('url', data.url);
                    formData.append('theme', theme);

                    fetch('save_news.php', {
                        method: 'POST',
                        body: formData
                    }).then(response => response.json()).then(result => {
                        if (result.success) {
                            alert('뉴스가 저장되었습니다.');
                            location.reload(); // 새로고침
                        } else {
                            alert('뉴스 저장에 실패했습니다.');
                        }
                    });
                } else {
                    alert('제목을 가져오지 못했습니다.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('뉴스 스크랩 중 오류가 발생했습니다.');
            }
        }
    </script>

</head>
<body>
<div id="container">
    <!-- 등록 화면 -->
    <div id="left-panel">
        <h1>이슈 및 테마 등록</h1>
        <form method="post">
            <input type="hidden" name="action" value="register" id="action">
            <input type="hidden" name="issue_id" id="issue_id">
            <label for="report_date">날짜:</label>
            <input type="date" id="report_date" name="report_date" value="<?= $dateParam ?>" required>

			<label for="keyword">키워드 (# 으로 구분):</label>
			<input type="text" id="keyword" name="keyword" placeholder="#키워드1 #키워드2" required autocomplete="off">

            <label for="issue">이슈:</label>
            <textarea id="issue" name="issue" rows="2" placeholder="이슈"></textarea>
            <label>
                <input type="checkbox" name="new_issue" id="new_issue"> 신규 이슈
            </label>
            <label for="theme">테마:</label>
            <input type="text" id="theme" name="theme" placeholder="테마 입력" autocomplete="off">

            <label>
                <input type="checkbox" name="hot_theme" id="hot_theme"> 핫 테마로 설정
            </label>
            <label for="sector">섹터:</label>
            <input type="text" id="sector" name="sector" placeholder="IT, 금융 등" autocomplete="off">

            <h2>연결된 종목</h2>
            <div id="stocks-container">
            </div>
            <button type="button" id="add-stock-button">종목 추가</button>
            
            <button type="submit">등록</button>
        </form>
    </div>

    <!-- 종목 조회 화면 -->
    <div id="middle-panel">
        <h2>Stock Analysis for <?= htmlspecialchars($_GET['date'] ?? date('Y-m-d')) ?></h2>
        <table>
            <thead>
                <tr>
                    <th>종목</th>
                    <th>등락률</th>
                    <th>거래대금(억)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr style="<?= $row['registered'] ? 'text-decoration: line-through;' : '' ?>">
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['change_rate']) ?>%</td>
                    <td><?= htmlspecialchars($row['turnover']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- 마켓이슈 조회 화면 -->
    <div id="right-panel">
        <h2>Issue List for <?= htmlspecialchars($dateParam) ?></h2>
        <button id="toggle-all">종목 펼치기/접기</button>
        <table>
            <thead>
                <tr>
                    <th>이슈</th>
                    <th>신규</th>
                    <th>키워드</th>
                    <th>테마</th>
                    <th>핫 테마</th>
                    <th>섹터</th>
                    <!-- <th>뉴스 스크랩</th> -->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issuesResult as $issue): ?>
                    <tr>
                        <td><?= htmlspecialchars($issue['issue']) ?></td>
                        <td><?= htmlspecialchars($issue['first_occurrence']) ?></td>
                        <td><a href="#" onclick="loadIssueDetails(<?= $issue['issue_id'] ?>); return false;"><?= htmlspecialchars($issue['group_name']) ?></a></td>
                        <td><?= htmlspecialchars($issue['theme']) ?></td>
                        <td><?= htmlspecialchars($issue['hot_theme']) ?></td>
                        <td><?= htmlspecialchars($issue['sector']) ?></td>
                        <!-- <td>
                            <input type="text" id="news_url_<?= htmlspecialchars($issue['theme']) ?>" placeholder="뉴스 URL">
                            <button type="button" onclick="scrapNews('<?= htmlspecialchars($issue['theme']) ?>')">스크랩</button>
                        </td> -->
                    </tr>
                    <tr class="accordion-content">
                        <td colspan="7">
                            <table>
                                <thead>
                                    <tr>
                                        <th>종목명</th>
                                        <th>종목 코드</th>
                                        <th>키워드</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($stocksData[$issue['issue_id']])): ?>
                                        <?php foreach ($stocksData[$issue['issue_id']] as $stock): ?>
                                            <tr>
                                                <td style="display: table-cell;"><?= htmlspecialchars($stock['stock_name']) ?></td>
                                                <td style="display: table-cell;"><?= htmlspecialchars($stock['code']) ?></td>
                                                <td style="display: table-cell;"><?= htmlspecialchars($stock['stock_comment']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3">No stocks available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
