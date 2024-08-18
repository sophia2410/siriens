<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// Fetching issues and stocks data
$dateParam = $_GET['date'] ?? date('Y-m-d');
$formattedDate = str_replace('-', '', $dateParam);

// 마켓이슈 불러오기
$issuesQuery = $mysqli->prepare("SELECT mi.*, kgm.group_name FROM market_issues mi LEFT JOIN keyword_groups_master kgm ON mi.keyword_group_id = kgm.group_id WHERE mi.date = ? ORDER BY mi.hot_theme DESC, kgm.group_name ASC");
$issuesQuery->bind_param('s', $formattedDate);
$issuesQuery->execute();
$issuesResult = $issuesQuery->get_result();

$stocksQuery = $mysqli->prepare("
    SELECT mis.*, vdp.close_rate, vdp.amount AS trade_amount
    FROM market_issue_stocks mis 
    JOIN v_daily_price vdp ON vdp.code = mis.code AND vdp.date = mis.date 
    WHERE mis.date = ? 
    ORDER BY mis.is_leader DESC, vdp.close_rate DESC");
$stocksQuery->bind_param('s', $formattedDate); 
$stocksQuery->execute();
$stocksResult = $stocksQuery->get_result();

$stocksData = [];
while ($stock = $stocksResult->fetch_assoc()) {
    $stocksData[$stock['issue_id']][] = $stock;
}

// 상승률 높은 종목 불러오기
$query = "SELECT 
              vdp.code, 
              vdp.name, 
              vdp.close_rate, 
              vdp.amount AS trade_amount,
              CASE WHEN mis.code IS NOT NULL THEN 1 ELSE 0 END AS registered,
              (
                  SELECT 
                      kgm.group_name 
                  FROM 
                      keyword_groups_master kgm
                  INNER JOIN 
                      market_issues mi ON mi.keyword_group_id = kgm.group_id
                  INNER JOIN 
                      market_issue_stocks mis_sub ON mis_sub.issue_id = mi.issue_id
                  WHERE 
                      mis_sub.code = vdp.code
                  AND mi.date != vdp.date
                  ORDER BY 
                      mi.date DESC
                  LIMIT 1
              ) AS recent_keyword_group
          FROM 
              v_daily_price vdp
          LEFT JOIN 
              market_issue_stocks mis ON vdp.code = mis.code AND vdp.date = mis.date
          WHERE 
              vdp.date = ? 
              AND 
              ((vdp.close_rate > 10 AND vdp.amount > 50) OR
              (vdp.close_rate > 29.5 AND vdp.amount > 30))
          ORDER BY 
              vdp.close_rate DESC";

// error_log(print_r($binded_query, true));
$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $formattedDate);
$stmt->execute();
$result = $stmt->get_result();

// 거래금액에 따른 색상 표시
function getAmountStyle($amountInBillion) {
    if ($amountInBillion >= 2000) {
        return'color:#ff0000; font-weight: bold;'; // 2000억 이상
    } elseif ($amountInBillion >= 1000) {
        return'color:#ff7f00; font-weight: bold;'; // 1000억 이상
    } elseif ($amountInBillion >= 500) {
        return'color:#ffa500; font-weight: bold;'; // 500억 이상
    } elseif ($amountInBillion >= 150) {
        return'color:#fcb9b2;'; // 150억 이상
    } elseif ($amountInBillion >= 100) {
        return'color:#ffccd5;'; // 100 이상
    } else {
        return''; // 10억 미만
    }
}

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
            height: 100vh;
        }
        #container {
            display: flex;
            flex-direction: row;
            border-right: 1px solid #ddd;
            width: 100%;
            height: 100vh; /* Adjust based on your design */
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-size: 14px; /* Reduced from default to make everything smaller */
        }
        #left-panel, #middle-panel, #right-panel {
            overflow-y: auto;
            padding: 15px; /* Reduced padding from 20px to 15px */
        }
        #left-panel {
            flex: 3;
            background-color: #fff;
            border-right: 1px solid #ddd;
        }
        #history-panel {
            flex: 2;
            background-color: #f5f5f5;
            border-right: 1px solid #ddd;
            padding: 15px; /* Reduced padding */
            overflow-y: auto;
        }
        #history-content {
            margin-top: 15px; /* Reduced margin from 20px to 15px */
        }
        #middle-panel {
            flex: 2;
            background-color: #e8e8e8; /* Slightly different background for distinction */
            border-right: 1px solid #ddd;
        }
        #right-panel {
            flex: 6;
            background-color: #f8f8f8;
        }
        label {
            font-weight: bold;
            margin-top: 8px; /* Reduced margin from 10px to 8px */
            display: block;
            font-size: 13px; /* Reduced font size for labels */
        }
        input[type="text"], input[type="date"], textarea {
            width: 100%;
            padding: 6px; /* Reduced padding from 8px to 6px */
            margin-top: 4px; /* Reduced margin */
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px; /* Reduced font size */
        }
        input[type="checkbox"] {
            margin-right: 5px;
        }
        button {
            margin-top: 15px; /* Reduced margin from 20px to 15px */
            padding: 8px 12px; /* Reduced padding */
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px; /* Reduced font size */
            border-radius: 4px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .custom-button {
            margin: 0;
            padding: 4px 8px; /* Further reduced padding */
            font-size: 12px; /* Reduced font size */
            line-height: 1; /* Maintain reduced height */
        }
        .custom-button:hover {
            background-color: #0056b3;
        }
        .stock-item {
            margin-bottom: 10px; /* Reduced margin */
        }
        .stock-row {
            display: flex;
            align-items: center;
        }
        .stock-row input[type="text"] {
            margin-right: 8px; /* Reduced margin */
        }
        .stock-row label {
            display: flex;
            align-items: center;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 3px !important;
            font-size: 13px; /* Reduced font size for table content */
        }
        th, td {
            border: 1px solid #ddd !important;
            padding: 6px !important; /* Reduced padding from 8px to 6px */
        }
        th {
            background-color: #f2f2f2 !important;
            font-size: 13px; /* Reduced font size */
        }
        #theme-table {
            margin-top: 30px; /* Reduced margin */
        }
        .accordion-content {
            display: ''; /* Initially hidden */
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let stockIndex = 0; // 초기 인덱스 설정

            // 초기 종목을 1개 추가
            addStock(stockIndex);

            // 초기화 버튼 클릭 시 호출
            document.getElementById('reset-button').addEventListener('click', function() {
                // 선택된 일자에 맞춰 화면 리로드
                const selectedDate = document.getElementById('report_date').value;
                window.location.href = 'market_issue.php?date=' + selectedDate;
            });

            // 종목 추가 버튼 클릭 시 호출
            document.getElementById('add-stock-button').addEventListener('click', () => {
                addStock(stockIndex++);
                reindexStockFields(); // 인덱스 재정렬
            });

            // 키워드 자동완성
            $("#keyword").on('input', function() {
                let query = $(this).val();
                if (query.length > 0) {
                    $.ajax({
                        url: 'fetch_autocomplete.php',
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
            setupAutocomplete('#theme', 'theme_sector', 'theme');
            setupAutocomplete('#sector', 'theme_sector', 'sector');

            // 종목 펼치기/접기 버튼 설정
            setupAccordionToggle();

            // 날짜 변경 시 페이지 이동
            document.getElementById('report_date').addEventListener('change', function() {
                window.location.href = 'market_issue.php?date=' + this.value;
            });

            // 키워드 포커스 아웃 시 과거 이력 가져오기document.getElementById('keyword').addEventListener('blur', function() {
            document.getElementById('keyword').addEventListener('blur', function() {
                const keywordInput = this.value.trim();
                const reportDate = document.getElementById('report_date').value; // Get the value of the report date input

                if (keywordInput.startsWith('#')) { // Check if input starts with #
                    $.ajax({
                        url: 'fetch_history.php',
                        type: 'GET',
                        data: {
                            keywords: keywordInput,
                            exclude_date: reportDate // Pass the report date as the exclude_date parameter
                        },
                        success: function(response) {
                            // Inject the fetched history into the history panel
                            $('#history-content').html(response);
                        },
                        error: function() {
                            $('#history-content').html('<p>과거 이력을 가져오지 못했습니다.</p>');
                        }
                    });
                }
            });
        });

        function setupAutocomplete(selector, type, key) {
            $(selector).focus(function() {
                const keywordGroup = $("#keyword").val().trim();
                $.ajax({
                    url: 'fetch_autocomplete.php',
                    type: 'GET',
                    data: { type: type, q: keywordGroup },
                    success: function(data) {
                        const suggestions = JSON.parse(data);
                        const items = suggestions.map(item => item[key]).filter(Boolean);
                        $(selector).autocomplete({
                            source: items,
                            minLength: 0  // Ensure that suggestions show even for empty input
                        }).autocomplete("search", ""); // Trigger autocomplete
                    }
                });
            });
        }

        function setupAccordionToggle() {
            const toggleButton = document.getElementById('toggle-all');
            const allContents = document.querySelectorAll('.accordion-content');
            let isExpanded = true; // 초기 상태는 펼쳐진 상태

            toggleButton.addEventListener('click', function() {
                isExpanded = !isExpanded; // 토글 상태 업데이트
                allContents.forEach(function(content) {
                    content.style.display = isExpanded ? 'table-row' : 'none';
                });
                toggleButton.textContent = isExpanded ? '모든 종목 접기' : '모든 종목 펼치기'; // 버튼 텍스트 업데이트
            });
        }

        function loadIssueDetails(issueId, formattedDate) {
            $.ajax({
                url: 'fetch_issue_details.php',
                type: 'GET',
                data: { 
                    issue_id: issueId,
                    date: formattedDate // Pass the date parameter
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.issueDetails) {
                        // 이슈 상세 정보를 폼에 셋팅
                        $('#report_date').val(data.issueDetails.date);
                        $('#keyword').val(data.issueDetails.keyword_group_name); // 그룹명으로 설정
                        $('#issue').val(data.issueDetails.issue);
                        $('#theme').val(data.issueDetails.theme);
                        $('#sector').val(data.issueDetails.sector);
                        $('#hot_theme').prop('checked', data.issueDetails.hot_theme === 'Y');
                        $('#new_issue').prop('checked', data.issueDetails.first_occurrence === 'Y');
                        $('#action').val('update');
                        $('#issue_id').val(data.issueDetails.issue_id);

                        // Check status and update keyword box style
                        if (data.issueDetails.status === 'copied') {
                            $('#keyword').css({
                                'background-color': '#ffe0e0', // Light coral or any other color
                                'font-weight': 'bold'          // Make it stand out
                            });
                        } else {
                            $('#keyword').css({
                                'background-color': '',
                                'font-weight': ''
                            });
                        }
                    }

                    if (data.stocks && data.stocks.length > 0) {
                        // 관련 종목 정보 셋팅
                        const container = $('#stocks-container');
                        container.empty(); // 기존 종목 정보를 초기화
                        data.stocks.forEach((stock, index) => {
                            const isLeader = stock['is_leader'] === '1' ? true : false;
                            addStock(index, stock.code, stock.name, stock.stock_comment, isLeader); // Pass stock data
                        });
                        reindexStockFields(); // 인덱스 재정렬
                    }
                }
            });
        }

        function addStock(stockIndex, code = '', name = '', comment = '', isLeader = '') {
            const container = document.getElementById('stocks-container');
            const newStock = document.createElement('div');
            newStock.className = 'stock-item';
            newStock.innerHTML = `
                <div class="stock-row">
                    <input type="text" name="stocks[${stockIndex}][name]" value="${name}" onkeydown="searchStock(event, this)" placeholder="종목명/코드" required style="flex: 2; margin-right: 10px;">
                    <input type="text" name="stocks[${stockIndex}][code]" value="${code}" readonly placeholder="코드" style="flex: 1; margin-right: 10px;">
                    <label style="margin-left: 10px;">
                        <input type="checkbox" name="stocks[${stockIndex}][is_leader]" ${isLeader ? 'checked' : ''}> 주도주
                    </label>
                    <button type="button" onclick="removeStock(this)" style="margin-left: 10px;">삭제</button>
                </div>
                <div class="stock-comment">
                    <input type="text" name="stocks[${stockIndex}][comment]" value="${comment}" placeholder="코멘트" style="width: 100%; margin-top: 10px;" autocomplete="off" onfocus="fetchCommentSuggestions(this)">
                </div>
            `;
            container.appendChild(newStock);
        }

        function removeStock(button) {
            const stockItem = button.closest('.stock-item'); // stock-item div
            stockItem.remove(); // 종목 삭제
            reindexStockFields(); // 인덱스 재정렬
        }

        function reindexStockFields() {
            const stockItems = document.querySelectorAll('.stock-item');
            stockItems.forEach((item, index) => {
                item.querySelector('input[name^="stocks"][name$="[name]"]').name = `stocks[${index}][name]`;
                item.querySelector('input[name^="stocks"][name$="[code]"]').name = `stocks[${index}][code]`;
                item.querySelector('input[name^="stocks"][name$="[comment]"]').name = `stocks[${index}][comment]`;
                item.querySelector('input[name^="stocks"][name$="[is_leader]"]').name = `stocks[${index}][is_leader]`;
            });
        }

        async function searchStock(event, input) {
            if (event.key === 'Enter') { // 엔터 키가 눌렸을 때만 처리
                event.preventDefault(); // 폼 제출 방지
                const query = input.value.trim();
                const codeInput = input.nextElementSibling; // 코드 입력 필드
                if (query.length > 0) { // 검색어가 비어있지 않은 경우에만 검색 수행
                    const stocks = await fetchStocks(query);
                    if (stocks.length === 1) {
                        input.value = stocks[0].name; // 입력 칸에 종목명 설정
                        codeInput.value = stocks[0].code; // 코드 설정
                    } else if (stocks.length > 1) {
                        showStockPopup(stocks, input, codeInput);
                    } else {
                        alert("해당 종목명을 찾을 수 없습니다.");
                    }
                }
            }
        }

        async function fetchStocks(query) {
            try {
                const response = await fetch(`fetch_autocomplete.php?type=stocks&q=${encodeURIComponent(query)}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error("Error fetching stocks:", error);
                return [];
            }
        }

        function showStockPopup(stocks, nameInput, codeInput) {
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
            closeBtn.onclick = () => document.body.removeChild(popup);

            popup.appendChild(closeBtn);
            document.body.appendChild(popup);
        }

        window.fetchCommentSuggestions = function(input) {
            const stockRow = input.closest('.stock-item').querySelector('input[name^="stocks"][name$="[code]"]');
            const stockCode = stockRow.value;

            if (stockCode) {
                $.ajax({
                    url: 'fetch_autocomplete.php',
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
        }

        async function scrapNews(theme) {
            const url = document.getElementById('news_url_' + theme).value;
            try {
                const response = await fetch(`get_title.php?url=${encodeURIComponent(url)}`);
                const data = await response.json();
                if (data.success) {
                    alert(`제목: ${data.title} | URL: ${data.url}`);
                    const formData = new FormData();
                    formData.append('title', data.title);
                    formData.append('url', data.url);
                    formData.append('theme', theme);

                    const result = await fetch('save_news.php', {
                        method: 'POST',
                        body: formData
                    }).then(response => response.json());

                    if (result.success) {
                        alert('뉴스가 저장되었습니다.');
                        location.reload(); // 새로고침
                    } else {
                        alert('뉴스 저장에 실패했습니다.');
                    }
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
    
<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>
    <!-- 등록 화면 -->
    <div id="left-panel">
        <h2>이슈 및 테마 등록</h2>
        <form method="post" action="process_issue.php">
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
            <div style="display: flex; justify-content: space-between; margin-top: 15px;">
                <!-- 좌측에 위치할 버튼들 -->
                <div>
                    <button type="button" id="add-stock-button">종목 추가</button>
                    <button type="submit">등록</button>
                </div>
                <!-- 우측에 위치할 초기화 버튼 -->
                <div>
                    <button type="button" id="reset-button">초기화</button>
                </div>
            </div>
        </form>
    </div>

    <!-- 종목 조회 화면 -->
    <div id="middle-panel">
        <h2>Stock for <?= htmlspecialchars($_GET['date'] ?? date('Y-m-d')) ?></h2>
        <table>
            <thead>
                <tr>
                    <th>종목</th>
                    <th>등락률</th>
                    <th>거래대금</th>
                    <th>최근 키워드</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>

                <?php 
                    $amountInBillion = $row['trade_amount'];
                    $closeRate = $row['close_rate'];
                    $amountStyle  = getAmountStyle($amountInBillion);
                    $nameStyle  = ($closeRate >= 29.5) ? 'color:red;' : '';
                ?>
                <tr style="<?= $row['registered'] ? 'text-decoration: line-through;' : '' ?>">
                    <td style="<?= $nameStyle ?>"><?= htmlspecialchars($row['name']) ?></td>
                    <td align=right style="<?= $nameStyle ?>"><?= htmlspecialchars($row['close_rate']) ?>%</td>
                    <td align=right style="<?= $amountStyle ?>"><?= number_format($row['trade_amount']) ?>억</td>
                    <td style="<?= $nameStyle ?>"><?= htmlspecialchars($row['recent_keyword_group']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- 과거 이력 조회 화면 -->
    <div id="history-panel">
        <h2>Keyword History</h2>
        <div id="history-content">
            <!-- This is where the historical data will be injected via AJAX -->
        </div>
    </div>

    <!-- 마켓이슈 조회 화면 -->
    <div id="right-panel">
        <h2>Issue List for <?= htmlspecialchars($dateParam) ?></h2>

        <!-- 키워드 그룹을 한 줄로 모아서 표시 -->
        <div style="margin-bottom: 15px; padding: 10px; background-color: #f0f0f0; border-radius: 5px;">
            <?php foreach ($issuesResult as $issue): ?>
                <span style="display: inline-block; margin-right: 10px; padding: 5px 10px; background-color: #ddd; border-radius: 5px;">
                    <?= htmlspecialchars($issue['group_name']) ?>
                </span>
            <?php endforeach; ?>
        </div>
        
        <button id="toggle-all">종목 펼치기/접기</button>
        <table>
            <!-- <thead>
                <tr>
                    <th style='width:20%;'>키워드</th>
                    <th style='width:10%;'>테마</th>
                    <th style='width:10%;'>섹터</th>
                    <th>복사</th>
                    <th>뉴스 스크랩</th>
                </tr>
            </thead> -->
            <tbody>
                <?php foreach ($issuesResult as $issue): ?>
                    <tr style='background-color: #e8e8e8; font-weight: bold;';>
                        <?php 
                            $statusStyle = ($issue['status'] === 'copied') ?'background-color:#ffe0e0;' : '';
                            $themeStyle  = ($issue['hot_theme'] === 'Y') ? 'color:red;' : '';
                        ?>
                        <td style="width:9%; display: table-cell; <?= $themeStyle ?> <?= $statusStyle ?>"><?= htmlspecialchars($issue['theme']) ?></td>
                        <td style="<?= $statusStyle ?>"><a href="#" onclick="loadIssueDetails(<?= $issue['issue_id'] ?>, <?= $formattedDate ?>); return false;" style=''><?= htmlspecialchars($issue['group_name']) ?></a></td>
                        <td style="<?= $statusStyle ?>"><?= htmlspecialchars($issue['sector']) ?></td>
                        <td style="<?= $statusStyle ?>">
                            <form method="post" action="process_issue.php" style="display:inline;">
                                <input type="hidden" name="action" value="copy">
                                <input type="hidden" name="issue_id" value="<?= $issue['issue_id'] ?>">
                                <input type="hidden" name="report_date" value="<?= $dateParam ?>">
                                <button type="submit" class='custom-button'>복사</button>
                            </form>
                            |
                            <form method="post" action="process_issue.php" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="issue_id" value="<?= $issue['issue_id'] ?>">
                                <input type="hidden" name="report_date" value="<?= $dateParam ?>">
                                <button type="submit" class="custom-button">삭제</button>
                            </form>
                        </td>
                        <td style="<?= $statusStyle ?>">
                            <input type="text" id="news_url_<?= htmlspecialchars($issue['theme']) ?>" style="width: 200px;" placeholder="뉴스 URL">
                            <button type="button" class='custom-button' onclick="scrapNews('<?= htmlspecialchars($issue['theme']) ?>')">스크랩</button>
                        </td>
                    </tr>
                    <?php if($issue['issue'] !== ''):?>
                        <tr>
                            <td colspan=5><?= htmlspecialchars($issue['issue'])?> ( <?=htmlspecialchars($issue['first_occurrence']) ?> )</td>
                        </tr>
                    <?php endif; ?>
                    <tr class="accordion-content">
                        <td colspan="5">
                            <table>
                                <tbody>
                                    <?php if (isset($stocksData[$issue['issue_id']])): ?>
                                        <?php foreach ($stocksData[$issue['issue_id']] as $stock): ?>
                                            <?php 
                                                $amountInBillion = $stock['trade_amount'];
                                                $closeRate = $stock['close_rate'];
                                                $amountStyle  = getAmountStyle($amountInBillion);
                                                $nameStyle  = ($closeRate >= 29.5) ? 'color:red;' : '';
                                                $leaderStyle = ($stock['is_leader'] === '1') ? 'background-color:#fff9c4;' : '';
                                            ?>
                                            <tr>
                                                <td style="width:15%; display: table-cell; font-weight: bold; <?= $nameStyle ?> <?= $leaderStyle ?>"><?= htmlspecialchars($stock['name']) ?></td>
                                                <td style="width:5%; display: table-cell;"><?= htmlspecialchars($stock['code']) ?></td>
                                                <td align=right style="width:8%; display: table-cell; <?= $nameStyle ?>"><?= number_format($stock['close_rate'],2) ?> %</td>
                                                <td align=right style="width:9%; display: table-cell; <?= $amountStyle ?>"><?= number_format($stock['trade_amount']) ?> 억</td>
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
