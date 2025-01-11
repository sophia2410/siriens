<?php
$pageTitle = "매매/복기 등록 관리";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/tinymce_module.php");

// 기본값 설정
$today = date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$tradeDate = isset($_GET['trade_date']) ? $_GET['trade_date'] : $today;
$type = isset($_GET['type']) ? $_GET['type'] : 'trade';

// 검색 조건 처리
$searchStockName = isset($_GET['stock_name']) ? $mysqli->real_escape_string($_GET['stock_name']) : '';
$searchMethod = isset($_GET['search_method']) ? $mysqli->real_escape_string($_GET['search_method']) : '';
$searchType = isset($_GET['search_type']) ? $mysqli->real_escape_string($_GET['search_type']) : '';
$searchResult = isset($_GET['search_result']) ? $mysqli->real_escape_string($_GET['search_result']) : '';

// 매매/복기 목록 불러오기 (상위 데이터)
$journalQuery = "
    SELECT 
        jt.id AS journal_id, 
        jt.trade_date, 
        jt.type, 
        jt.trade_items, 
        UNCOMPRESS(jt.comment) AS comment, 
        jt.created_at
    FROM journal_trade jt
    WHERE 1=1";

// 검색 조건 추가
$whereConditions = [];
if ($searchStockName) {
    $whereConditions[] = "jt.trade_items LIKE '%#$searchStockName%'";
}
if ($searchType) {
    $whereConditions[] = "jt.type = '$searchType'";
}
if (count($whereConditions) > 0) {
    $journalQuery .= ' AND ' . implode(' AND ', $whereConditions);
}

// 정렬 및 페이징
$journalQuery .= " ORDER BY jt.id DESC LIMIT 1 OFFSET " . ($page - 1) * 1;
$journals = $mysqli->query($journalQuery);

// 전체 저널 개수
$countQuery = "SELECT COUNT(*) AS total FROM journal_trade jt WHERE 1=1";
if (count($whereConditions) > 0) {
    $countQuery .= ' AND ' . implode(' AND ', $whereConditions);
}
$countResult = $mysqli->query($countQuery)->fetch_assoc();
$totalJournals = $countResult['total'];
$totalPages = ceil($totalJournals / 1);

// 세부 항목 데이터 가져오기
$detailsQuery = "
    SELECT 
        jtd.id,
        jtd.journal_id, 
        jtd.profit_loss, 
        jtd.trade_method
    FROM journal_trade_details jtd
    INNER JOIN journal_trade jt ON jtd.journal_id = jt.id";
$detailsResult = $mysqli->query($detailsQuery);

// 세부 항목을 배열로 정리
$details = [];
while ($detail = $detailsResult->fetch_assoc()) {
    $details[$detail['journal_id']][] = $detail;
}
?>

<head>
    <style>
        #journal_register_container {
            flex: 1;
            background-color: #f9f9f9;
            padding: 20px;
            margin-right: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        /* 한 줄로 필드를 배치하기 위한 스타일 */
        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .form-row label {
            margin-right: 10px;
        }

        .form-row input[type="text"],
        .form-row input[type="date"],
        .form-row select {
            flex: 1;
            padding: 10px;
            margin-right: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        /* 마지막 요소에 margin-right를 없앰 */
        .form-row input[type="text"]:last-child,
        .form-row input[type="date"]:last-child,
        .form-row select:last-child {
            margin-right: 0;
        }

        #journal_register_container button {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #d9534f;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        #journal_list_container {
            flex: 1;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .journal-card {
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f8f8f8;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); /* 더 짙은 그림자 */
            border: 2px solid #ccc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            max-height: calc(100vh - 200px); /* 화면 높이를 기준으로 설정 (여백 포함) */
            overflow-y: auto; /* 내부 스크롤 활성화 */
            position: relative; /* 내부 스크롤을 위해 위치 설정 */
        }

        .journal-title {
            font-size: 1.2em;
            font-weight: bold;
        }

        .detail-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .detail-row label {
            margin-right: 10px;
            flex: 0 0 auto; /* 고정 크기 */
        }

        .detail-row select,
        .detail-row input[type="number"] {
            flex: 1; /* 입력 필드가 동일한 비율로 확장 */
            margin-right: 10px; /* 각 필드 간격 */
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        /* 마지막 필드의 오른쪽 간격 제거 */
        .detail-row select:last-child,
        .detail-row input[type="number"]:last-child {
            margin-right: 0;
        }

        #details-container button {
            background-color: #d9534f;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            cursor: pointer;
        }

        .journal-content {
            margin-top: 10px;
        }

        .pagination a {
            padding: 10px 15px;
            margin: 0 5px;
            background-color: #f1f1f1;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background-color: #ddd;
        }

        .pagination a.active {
            background-color: #d9534f;
            color: white;
        }

        .pagination a:first-child, .pagination a:last-child {
            margin: 0 10px;
        }

        #edit_buttons {
            display: none;
        }

        #edit_buttons button {
            padding: 10px 20px;
            background-color: #5bc0de;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        #edit_buttons button.delete {
            background-color: #d9534f;
        }
    </style>
</head>

<body>
    <div id="container">
        <!-- 매매/복기 등록 폼 -->
        <div id="journal_register_container">
            <h2>매매/복기 등록</h2>
            <form id="journalForm" action="journal_trade_process.php" method="POST">
                <input type="hidden" id="journal_id" name="journal_id">
                <input type="hidden" name="page" value="<?php echo $page; ?>"> 

                <!-- 검색 조건을 hidden 필드로 추가 -->
                <input type="hidden" name="search_stock_name" value="<?php echo htmlspecialchars($searchStockName); ?>">
                <input type="hidden" name="search_type" value="<?php echo htmlspecialchars($searchType); ?>">

                <div class="form-row">
                    <!-- 매매일자 -->
                    <label for="trade_date">일자:</label>
                    <input type="date" name="trade_date" id="trade_date" value="<?php echo $tradeDate; ?>" required>

                    <!-- 구분 -->
                    <label for="type">구분:</label>
                    <select name="type" id="type" required>
                        <option value="scenario" <?php echo ($type === 'scenario') ? 'selected' : ''; ?>>시나리오</option>
                        <option value="trade" <?php echo ($type === 'trade') ? 'selected' : ''; ?>>매매</option>
                        <option value="reflection" <?php echo ($type === 'reflection') ? 'selected' : ''; ?>>복기</option>
                    </select>

                    <!-- 매매 종목 -->
                    <label for="trade_items">종목:</label>
                    <input type="text" name="trade_items" id="trade_items" placeholder="#종목명 #종목명2 ">
                </div>

                <!-- 코멘트 -->
                <label for="comment">코멘트:</label>
                <textarea name="comment" id="comment" rows="4"></textarea>

                <hr>

                <!-- 하위 데이터 -->
                <h3>매매 세부 항목</h3>
                <div id="details-container">
                    <div class="detail-row">

                        <label>매매 방식:</label>
                        <select name="details[0][trade_method]">
                            <option value="종가베팅">종가베팅</option>
                            <option value="시간외단일가">시간외단일가</option>
                            <option value="시가베팅">시가베팅</option>
                            <option value="당일매매">당일매매</option>
                            <option value="단기스윙">단기스윙</option>
                        </select>

                        <label>수익/손실:</label>
                        <select name="details[0][profit_loss]">
                            <option value="profit">수익</option>
                            <option value="loss">손실</option>
                        </select>

                    </div>
                </div>
                <button type="button" onclick="addDetailRow()">+ 추가</button>

                <br><br>
                <!-- 버튼 -->
                <button type="submit" id="register_button">등록</button>
                <div id="edit_buttons" style="display: none;">
                    <button type="button" onclick="updateJournal()">수정</button>
                    <button type="button" class="delete" onclick="deleteJournal()">삭제</button>
                    <button type="button" class="reset" onclick="resetForm()">초기화</button>
                </div>
            </form>
            <?php loadTinyMCE('#comment', 700); ?>
            <?php loadTinyMCEScripts(); ?>
        </div>

        <!-- 매매/복기 목록 -->
        <div id="journal_list_container">
            <h2>매매/복기 목록</h2>

            <!-- 검색 조건 -->
            <div class="form-row">
                <input type="text" id="search_stock_name" name="search_stock_name" value="<?php echo htmlspecialchars($searchStockName); ?>" placeholder="종목명/코드">
                <select id="search_type" name="search_type">
                    <option value="" <?php echo ($searchType === '') ? 'selected' : ''; ?>>타입</option>
                    <option value="scenario" <?php echo ($searchType === 'scenario') ? 'selected' : ''; ?>>시나리오</option>
                    <option value="trade" <?php echo ($searchType === 'trade') ? 'selected' : ''; ?>>매매</option>
                    <option value="reflection" <?php echo ($searchType === 'reflection') ? 'selected' : ''; ?>>복기</option>
                </select>
                <button onclick="searchJournals()" style="flex: 0.5; margin-right: 10px;">조회</button>
                <button onclick="resetSearch()" style="flex: 0.5;">초기화</button>
            </div>

            <?php while ($row = $journals->fetch_assoc()) { ?>
                <div class="journal-card">
                    <div class="journal-title">
                        <?php echo $row['trade_date'] . ' / ' . $row['type'] . ' / ' . htmlspecialchars($row['trade_items']); ?>
                        <?php if (isset($details[$row['journal_id']])) { ?>
                            <ul class="detail-list">
                                <?php foreach ($details[$row['journal_id']] as $detail) { ?>
                                    <li class="detail-item">
                                        <?php echo $detail['trade_method']; ?>, 
                                        <?php echo $detail['profit_loss']; ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                    <div class="journal-content" onclick="loadJournalData(<?= $row['journal_id']; ?>)"><?php echo $row['comment']."&nbsp"; ?></div>
                </div>
            <?php } ?>

            <!-- 페이지네이션 -->
            <div class="pagination">
                <?php
                $visiblePages = 5; // 한 번에 표시할 페이지 수
                $startPage = max(1, $page - floor($visiblePages / 1)); // 시작 페이지
                $endPage = min($totalPages, $startPage + $visiblePages - 1); // 마지막 페이지

                // 검색 조건을 URL에 추가
                $queryParams = "&stock_name=" . urlencode($searchStockName) . "&search_type=" . urlencode($searchType) . "&search_method=" . urlencode($searchMethod) . "&search_result=" . urlencode($searchResult);

                // "최초" 버튼
                if ($page > 1) {
                    echo '<a href="?page=1' . $queryParams . '">최초</a>';
                }

                // "이전" 버튼
                if ($page > 1) {
                    echo '<a href="?page=' . ($page - 1) . $queryParams . '">이전</a>';
                }

                // 페이지 번호
                for ($i = $startPage; $i <= $endPage; $i++) {
                    echo '<a href="?page=' . $i . $queryParams . '" class="' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
                }

                // "다음" 버튼
                if ($page < $totalPages) {
                    echo '<a href="?page=' . ($page + 1) . $queryParams . '">다음</a>';
                }

                // "최종" 버튼
                if ($page < $totalPages) {
                    echo '<a href="?page=' . $totalPages . $queryParams . '">최종</a>';
                }
                ?>
            </div>
        </div>
    </div>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>

<script>

    function addDetailRow() {
        const container = document.getElementById('details-container');
        const index = container.children.length;
        const row = document.createElement('div');
        row.className = 'detail-row';
        row.innerHTML = `
            <label>수익/손실:</label>
            <select name="details[${index}][profit_loss]">
                <option value="profit">수익</option>
                <option value="loss">손실</option>
            </select>

            <label>매매 방식:</label>
            <select name="details[${index}][trade_method]">
                <option value="종가베팅">종가베팅</option>
                <option value="시간외단일가">시간외단일가</option>
                <option value="시가베팅">시가베팅</option>
                <option value="당일매매">당일매매</option>
                <option value="단기스윙">단기스윙</option>
            </select>

        `;
        container.appendChild(row);
    }

    function loadJournalData(journalId) {
        fetch(`fetch_journal.php?journal_id=${journalId}&type=trade`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch journal data');
                }
                return response.json();
            })
            .then(data => {
                if (data.journal) {
                    // 상위 데이터 채우기
                    document.getElementById('journal_id').value = data.journal.journal_id;
                    document.getElementById('trade_date').value = data.journal.trade_date;
                    document.getElementById('type').value = data.journal.type;
                    document.getElementById('trade_items').value = data.journal.trade_items;
                    setTinyMCEContent('comment', data.journal.comment);

                    // 하위 데이터 채우기
                    const detailsContainer = document.getElementById('details-container');
                    detailsContainer.innerHTML = ''; // 기존 내용 초기화

                    data.details.forEach((detail, index) => {
                        const detailRow = document.createElement('div');
                        detailRow.className = 'detail-row';
                        detailRow.innerHTML = `
                            <label>수익/손실:</label>
                            <select name="details[${index}][profit_loss]">
                                <option value="profit" ${detail.profit_loss === 'profit' ? 'selected' : ''}>수익</option>
                                <option value="loss" ${detail.profit_loss === 'loss' ? 'selected' : ''}>손실</option>
                            </select>

                            <label>매매 방식:</label>
                            <select name="details[${index}][trade_method]">
                                <option value="종가베팅" ${detail.trade_method === '종가베팅' ? 'selected' : ''}>종가베팅</option>
                                <option value="시간외단일가" ${detail.trade_method === '시간외단일가' ? 'selected' : ''}>시간외단일가</option>
                                <option value="시가베팅" ${detail.trade_method === '시가베팅' ? 'selected' : ''}>시가베팅</option>
                                <option value="당일매매" ${detail.trade_method === '당일매매' ? 'selected' : ''}>당일매매</option>
                                <option value="단기스윙" ${detail.trade_method === '단기스윙' ? 'selected' : ''}>단기스윙</option>
                            </select>
                        `;

                        detailsContainer.appendChild(detailRow);
                    });

                    // 수정 버튼 활성화
                    document.getElementById('edit_buttons').style.display = 'block';
                    document.getElementById('register_button').style.display = 'none';
                } else {
                    alert('매매 데이터를 찾을 수 없습니다.');
                }
            })
            .catch(error => {
                console.error('Error fetching journal data:', error);
                alert('매매 데이터를 가져오는 중 문제가 발생했습니다.');
            });
    }


    function focusOnBuyDate() {
        const tradeDateInput = document.getElementById('trade_date');
        if (tradeDateInput) {
            tradeDateInput.focus();
        }
    }

    function updateJournal() {
        syncTinyMCEData();
        document.getElementById('journalForm').submit();
    }

    function deleteJournal() {
        if (confirm('정말로 이 매매/복기를 삭제하시겠습니까?')) {
            var journalId = document.getElementById('journal_id').value;
            window.location.href = "journal_trade_process.php?action=delete&journal_id=" + journalId + "&page=<?php echo $page; ?>";
        }
    }

    function resetForm() {
        window.location.href = "?page=<?php echo $page; ?>";
    }

    function searchJournals() {
        const stockName = document.getElementById('search_stock_name').value;
        const type = document.getElementById('search_type').value;
        const method = document.getElementById('search_method').value;
        const profit_loss = document.getElementById('search_result').value;
        let query = "?page=1";

        if (stockName) {
            query += "&stock_name=" + encodeURIComponent(stockName);
        }

        if (type) {
            query += "&search_type=" + type;
        }

        if (method) {
            query += "&search_method=" + method;
        }

        if (profit_loss) {
            query += "&search_result=" + profit_loss;
        }

        window.location.href = query;
    }

    function resetSearch() {
        window.location.href = "?page=1";
    }

</script>
</body>