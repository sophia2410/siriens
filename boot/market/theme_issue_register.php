<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// 데이터 등록
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $report_date = str_replace('-', '', $_POST['report_date']); // 날짜 형식 변환
    $sector = $_POST['sector'];
    $theme = $_POST['theme'];
    $hot_theme = isset($_POST['hot_theme']) ? 'Y' : 'N';
    $issue = $_POST['issue'];
    $keyword = $_POST['keyword'];
    $stocks = $_POST['stocks'];

    // market_sector 테이블에 데이터 삽입
    $stmt = $mysqli->prepare("INSERT INTO market_sector (report_date, sector, theme, hot_theme, issue, keyword, create_dtime) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('ssssss', $report_date, $sector, $theme, $hot_theme, $issue, $keyword);
    $stmt->execute();
    $stmt->close();

    // 각 종목에 대해 mochaten 테이블에 데이터 삽입
    foreach ($stocks as $stock) {
        $code = $stock['code'];
        $name = $stock['name'];
        $stock_keyword = $stock['keywords'];

        $stmt = $mysqli->prepare("INSERT INTO mochaten (mochaten_date, cha_fg, code, name, stock_keyword, trade_date, create_dtime) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('ssssss', $report_date, $hot_theme, $code, $name, $stock_keyword, $report_date);
        $stmt->execute();
        $stmt->close();
    }

    echo "<script>alert('이슈 및 종목이 등록되었습니다.');</script>";
}

// 등록된 데이터 조회 (해당 일자만)
if (isset($_GET['report_date'])) {
    $report_date = str_replace('-', '', $_GET['report_date']);
    $sector_query = "SELECT * FROM market_sector WHERE report_date = '$report_date' ORDER BY create_dtime DESC";
    $sector_result = $mysqli->query($sector_query);
    $sectors = $sector_result->fetch_all(MYSQLI_ASSOC);
    $sector_result->close();

    $stock_query = "SELECT * FROM mochaten WHERE mochaten_date = '$report_date' ORDER BY create_dtime DESC";
    $stock_result = $mysqli->query($stock_query);
    $stocks = $stock_result->fetch_all(MYSQLI_ASSOC);
    $stock_result->close();
} else {
    $sectors = [];
    $stocks = [];
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
            display: flex;
            height: 100vh;
        }
        #container {
            display: flex;
            flex-direction: row;
            width: 100%;
        }
        #left-panel, #right-panel {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        #left-panel {
            background-color: #fff;
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
        #theme-table {
            margin-top: 40px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.stock-item input[name="stocks[][search]"]').forEach(input => {
                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault(); // 폼 제출 방지
                        searchStock(this); // 종목 검색 함수 호출
                    }
                });
            });
        });

        let stockData = [];

        async function fetchStocks(query) {
            const response = await fetch(`fetch_stocks.php?q=${encodeURIComponent(query)}`);
            stockData = await response.json();
            return stockData;
        }

        function showStockPopup(stocks) {
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
                    document.querySelector('.stock-item:last-child input[name="stocks[][name]"]').value = stock.name;
                    document.querySelector('.stock-item:last-child input[name="stocks[][code]"]').value = stock.code;
                    document.querySelector('.stock-item:last-child input[name="stocks[][keywords]"]').value = stock.keywords;
                    document.body.removeChild(popup);
                };
                popup.appendChild(btn);
            });

            const closeBtn = document.createElement('button');
            closeBtn.innerText = '닫기';
            closeBtn.style.display = 'block';
            closeBtn.style.width = '100%';
            closeBtn.style.margin = '5px 0';
            closeBtn.onclick = () => {
                document.body.removeChild(popup);
            };
            popup.appendChild(closeBtn);

            document.body.appendChild(popup);
        }

        async function searchStock(input) {
            const query = input.value;
            if (query.length > 2) {
                const stocks = await fetchStocks(query);
                if (stocks.length === 1) {
                    input.closest('.stock-item').querySelector('input[name="stocks[][name]"]').value = stocks[0].name;
                    input.closest('.stock-item').querySelector('input[name="stocks[][code]"]').value = stocks[0].code;
                    input.closest('.stock-item').querySelector('input[name="stocks[][keywords]"]').value = stocks[0].keywords;
                } else if (stocks.length > 1) {
                    showStockPopup(stocks);
                }
            }
        }

        // 종목 추가 기능
        function addStock() {
            const container = document.getElementById('stocks-container');
            const newStock = document.createElement('div');
            newStock.className = 'stock-item';
            newStock.innerHTML = `
                <label>종목명/코드:</label>
                <input type="text" name="stocks[][search]" onkeydown="checkEnter(event)" oninput="searchStock(this)" required>
                <label>종목명:</label>
                <input type="text" name="stocks[][name]" readonly>
                <label>종목 코드:</label>
                <input type="text" name="stocks[][code]" readonly>
                <label>키워드 (#으로 구분):</label>
                <input type="text" name="stocks[][keywords]" placeholder="#키워드1 #키워드2">
                <button type="button" onclick="removeStock(this)">삭제</button>
            `;
            container.appendChild(newStock);
        }

        function checkEnter(event) {
            if (event.key === 'Enter') {
                event.preventDefault(); // 엔터키를 눌렀을 때 기본 폼 제출 방지
            }
        }

        // 종목 삭제 기능
        function removeStock(button) {
            const stockItem = button.parentNode;
            stockItem.parentNode.removeChild(stockItem);
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
            <input type="hidden" name="action" value="register">
            <label for="report_date">날짜:</label>
            <input type="date" id="report_date" name="report_date" value="<?= date('Y-m-d') ?>" required>

            <label for="sector">섹터:</label>
            <input type="text" id="sector" name="sector" placeholder="IT, 금융 등" required>

            <label for="theme">테마:</label>
            <input type="text" id="theme" name="theme" placeholder="테마 입력" required>

            <label>
                <input type="checkbox" name="hot_theme"> 핫 테마로 설정
            </label>

            <label for="issue">이슈 (#으로 구분):</label>
            <textarea id="issue" name="issue" rows="4" placeholder="#이슈1 #이슈2"></textarea>

            <label for="keyword">테마 키워드 (#으로 구분):</label>
            <input type="text" id="keyword" name="keyword" placeholder="#키워드1 #키워드2">

            <h2>연결된 종목</h2>
            <div id="stocks-container">
                <div class="stock-item">
                    <label>종목명/코드:</label>
                    <input type="text" name="stocks[][search]" onkeydown="checkEnter(event)" oninput="searchStock(this)" required>
                    <label>종목명:</label>
                    <input type="text" name="stocks[][name]" readonly>
                    <label>종목 코드:</label>
                    <input type="text" name="stocks[][code]" readonly>
                    <label>키워드 (#으로 구분):</label>
                    <input type="text" name="stocks[][keywords]" placeholder="#키워드1 #키워드2">
                    <button type="button" onclick="removeStock(this)">삭제</button>
                </div>
            </div>
            <button type="button" onclick="addStock()">종목 추가</button>
            
            <button type="submit">등록</button>
        </form>
    </div>

    <!-- 조회 화면 -->
    <div id="right-panel">
        <h2>등록된 내역 (<?= isset($_GET['report_date']) ? htmlspecialchars($_GET['report_date']) : date('Y-m-d') ?>)</h2>
        <h3>테마 및 이슈</h3>
        <table>
            <thead>
                <tr>
                    <th>날짜</th>
                    <th>섹터</th>
                    <th>테마</th>
                    <th>핫 테마</th>
                    <th>이슈</th>
                    <th>키워드</th>
                    <th>뉴스 스크랩</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sectors as $sector): ?>
                    <tr>
                        <td><?= htmlspecialchars($sector['report_date']) ?></td>
                        <td><?= htmlspecialchars($sector['sector']) ?></td>
                        <td><?= htmlspecialchars($sector['theme']) ?></td>
                        <td><?= htmlspecialchars($sector['hot_theme']) ?></td>
                        <td><?= htmlspecialchars($sector['issue']) ?></td>
                        <td><?= htmlspecialchars($sector['keyword']) ?></td>
                        <td>
                            <input type="text" id="news_url_<?= htmlspecialchars($sector['theme']) ?>" placeholder="뉴스 URL">
                            <button type="button" onclick="scrapNews('<?= htmlspecialchars($sector['theme']) ?>')">스크랩</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>종목</h3>
        <table>
            <thead>
                <tr>
                    <th>날짜</th>
                    <th>종목 코드</th>
                    <th>종목명</th>
                    <th>테마</th>
                    <th>키워드</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stocks as $stock): ?>
                    <tr>
                        <td><?= htmlspecialchars($stock['mochaten_date']) ?></td>
                        <td><?= htmlspecialchars($stock['code']) ?></td>
                        <td><?= htmlspecialchars($stock['name']) ?></td>
                        <td><?= htmlspecialchars($stock['stock_keyword']) ?></td>
                        <td><?= htmlspecialchars($stock['stock_keyword']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
