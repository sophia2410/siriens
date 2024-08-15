document.addEventListener('DOMContentLoaded', function() {
    let stockIndex = 0; // 초기 인덱스 설정

    // 초기 종목을 1개 추가
    addStock(stockIndex);

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
    setupAutocomplete('#theme', 'theme_sector', 'theme');
    setupAutocomplete('#sector', 'theme_sector', 'sector');

    // 종목 펼치기/접기 버튼 설정
    setupAccordionToggle();

    // 날짜 변경 시 페이지 이동
    document.getElementById('report_date').addEventListener('change', function() {
        window.location.href = 'market_issue.php?date=' + this.value;
    });
});

function setupAutocomplete(selector, type, key) {
    $(selector).focus(function() {
        const keywordGroup = $("#keyword").val().trim();
        $.ajax({
            url: 'fetch_data.php',
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
    let isExpanded = false; // 초기 상태는 접혀있는 상태

    toggleButton.addEventListener('click', function() {
        isExpanded = !isExpanded; // 토글 상태 업데이트
        allContents.forEach(function(content) {
            content.style.display = isExpanded ? 'table-row' : 'none';
        });
        toggleButton.textContent = isExpanded ? '모든 종목 접기' : '모든 종목 펼치기'; // 버튼 텍스트 업데이트
    });
}

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
                $('#keyword').val(data.issueDetails.keyword_group_name); // 그룹명으로 설정
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
                    addStock(index, stock.code, stock.name, stock.stock_comment); // Pass stock data
                });
                reindexStockFields(); // 인덱스 재정렬
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
        const response = await fetch(`fetch_stocks.php?q=${encodeURIComponent(query)}`);
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




if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) { // 등록처리
    error_log(print_r($_POST, true));
    
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
            $stmt = $mysqli->prepare("SELECT keyword_id FROM keyword_master WHERE keyword = ?");
            $stmt->bind_param('s', $keyword);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $keyword_ids[] = $row['keyword_id'];
            } else {
                $stmt = $mysqli->prepare("INSERT INTO keyword_master (keyword) VALUES (?)");
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
                FROM keyword_groups
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
            $stmt = $mysqli->prepare("UPDATE keyword_groups_master SET group_name = ? WHERE group_id = ?");
            $stmt->bind_param('si', $group_name, $group_id);
            $stmt->execute();
        } else {
            // 동일한 조합이 없는 경우 새 그룹 생성
            $group_name = implode(' ', array_map(fn($kw) => "#{$kw}", $keywords));
            $stmt = $mysqli->prepare("INSERT INTO keyword_groups_master (group_name) VALUES (?)");
            $stmt->bind_param('s', $group_name);
            $stmt->execute();
            $group_id = $mysqli->insert_id;

            // 키워드와 그룹 연결
            foreach ($keyword_ids as $keyword_id) {
                $stmt = $mysqli->prepare("INSERT INTO keyword_groups (group_id, keyword_id, create_dtime) VALUES (?, ?, NOW())");
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
            $name = $stock['name'] ?? '';
            $stock_comment = $stock['comment'] ?? '';

            error_log("Checking stock: code = $code, name = $name");

            if ($code === '') {
                error_log("Stock code is empty");
            }

            if ($name === '') {
                error_log("Stock name is empty");
            }

            if ($code !== '' && $name !== '') { // code와 name이 유효한지 확인
                error_log(print_r($code, true));
                error_log(print_r($name, true));

                // market_issue_stocks 테이블에 동일한 데이터 존재 여부 확인
                $stmt = $mysqli->prepare("SELECT 1 FROM market_issue_stocks WHERE issue_id = ? AND code = ?");
                $stmt->bind_param('is', $issue_id, $code);
                $stmt->execute();
                $result = $stmt->get_result();

                if (!$result->fetch_assoc()) {
                    // 동일한 데이터가 존재하지 않는 경우에만 삽입
                    $stmt = $mysqli->prepare("INSERT INTO market_issue_stocks (issue_id, code, name, stock_comment, date, create_dtime) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param('issss', $issue_id, $code, $name, $stock_comment, $date);

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
    header("Location: market_issue.php?date=$selected_date");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_id'])) { // 키워드복사처리
    $issue_id = $_POST['issue_id'];

    $mysqli->autocommit(FALSE);

    try {
        // 기존 이슈 데이터 가져오기
        $stmt = $mysqli->prepare("SELECT * FROM market_issues WHERE issue_id = ?");
        $stmt->bind_param('i', $issue_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $issueData = $result->fetch_assoc();

        if (!$issueData) {
            throw new Exception("이슈를 찾을 수 없습니다.");
        }

        // 새로운 이슈로 삽입
        $stmt = $mysqli->prepare("INSERT INTO market_issues (date, issue, first_occurrence, link, sector, theme, hot_theme, create_dtime, keyword_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param(
            'sssssssi',
            $issueData['date'],
            $issueData['issue'],
            $issueData['first_occurrence'],
            $issueData['link'],
            $issueData['sector'],
            $issueData['theme'],
            $issueData['hot_theme'],
            $issueData['keyword_group_id']
        );

        if (!$stmt->execute()) {
            throw new Exception("이슈 복사 실패: " . $stmt->error);
        }

        $new_issue_id = $mysqli->insert_id; // 새로 삽입된 이슈 ID

        // 기존 관련 종목 가져오기
        $stmt = $mysqli->prepare("SELECT * FROM market_issue_stocks WHERE issue_id = ?");
        $stmt->bind_param('i', $issue_id);
        $stmt->execute();
        $stocksResult = $stmt->get_result();

        // 새 이슈에 관련 종목 복사
        while ($stock = $stocksResult->fetch_assoc()) {
            $stmt = $mysqli->prepare("INSERT INTO market_issue_stocks (issue_id, code, name, stock_comment, date, create_dtime) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param(
                'issss',
                $new_issue_id,
                $stock['code'],
                $stock['name'],
                $stock['stock_comment'],
                $stock['date']
            );

            if (!$stmt->execute()) {
                throw new Exception("종목 복사 실패: " . $stmt->error);
            }
        }

        $mysqli->commit(); // 트랜잭션 커밋

        echo json_encode(['success' => true, 'message' => '이슈가 성공적으로 복사되었습니다.']);

    } catch (Exception $e) {
        $mysqli->rollback(); // 오류 발생 시 롤백
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $mysqli->autocommit(TRUE); // 자동 커밋 모드 복원
}