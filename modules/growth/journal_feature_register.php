<?php
$pageTitle = "Hot 종목 관리";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/tinymce_module.php");

// 기본값 설정
$today = date('Y-m-d'); // 오늘 날짜를 기본값으로 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$tradeDate = isset($_GET['journal_date']) ? $_GET['journal_date'] : $today;
$type = isset($_GET['type']) ? $_GET['type'] : 'hot';
$status= isset($_GET['status']) ? $_GET['status'] : 'normal';

// 검색 조건 처리
$searchStockName = isset($_GET['stock_name']) ? $mysqli->real_escape_string($_GET['stock_name']) : '';
$searchType = isset($_GET['search_type']) ? $mysqli->real_escape_string($_GET['search_type']) : '';
$searchStatus = isset($_GET['search_status']) ? $mysqli->real_escape_string($_GET['search_status']) : '';


// 매매/복기 목록 불러오기 (페이지네이션 적용)
$journalQuery = "
    SELECT jf.id, jf.journal_date, jf.code, s.name, jf.type, jf.status, 
        CASE 
            WHEN jf.status = 'focus' THEN '<span style=\"color: #e03e2d;\"><strong>(F)</strong></span>' 
            WHEN jf.status = 'watch_short' THEN '<span style=\"color: #843fa1;\"><strong>(W-S)</strong></span>' 
            WHEN jf.status = 'watch_long' THEN '<span style=\"color: #169179;\"><strong>(W-L)</strong></span>' 
            ELSE '' 
        END AS status_str,       
        UNCOMPRESS(jf.comment) AS comment, jf.created_at
    FROM journal_feature jf
    JOIN stock s ON jf.code = s.code AND s.last_yn = 'Y'
    WHERE 1=1";

    
// 조건 변수 초기화
$whereConditions = array();

// 종목명 검색 조건 추가
if ($searchStockName) {
    $whereConditions[] = "(s.name LIKE '%$searchStockName%' OR jf.code LIKE '%$searchStockName%')";
}

// 구분 검색 조건 추가
if ($searchType) {
    $whereConditions[] = "jf.type = '$searchType'";
}

// 상태 검색 조건 추가
if ($searchStatus) {
    $whereConditions[] = "jf.status = '$searchStatus'";
}

// 조건이 있는 경우, 쿼리에 추가
if (count($whereConditions) > 0) {
    $journalQuery .= ' AND ' . implode(' AND ', $whereConditions);
}


// 정렬과 페이징 처리
$journalQuery .= " ORDER BY jf.id DESC LIMIT 2 OFFSET " . ($page - 1) * 2;

// 쿼리 실행
$journals = $mysqli->query($journalQuery);

// 전체 저널 개수 구하기 (페이지네이션 처리용)
$countQuery = "
    SELECT COUNT(*) AS total
    FROM journal_feature jf
    JOIN stock s ON jf.code = s.code AND s.last_yn = 'Y'
    WHERE 1=1
";

// 조건이 있는 경우, 쿼리에 추가
if (count($whereConditions) > 0) {
    $countQuery .= ' AND ' . implode(' AND ', $whereConditions);
}

// 쿼리 실행
$countResult = $mysqli->query($countQuery)->fetch_assoc();
$totalJournals = $countResult['total'];
$totalPages = ceil($totalJournals / 2);
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

        #status{
            margin-top: 15px;
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
            padding: 20px;
            margin-bottom: 20px;
            background-color: #f8f8f8;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); /* 더 짙은 그림자 */
            border: 2px solid #ccc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }

        .journal-title {
            font-size: 1.2em;
            font-weight: bold;
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
        <!-- 관심 종목 등록 폼 -->
        <div id="journal_register_container">
            <h2>관심 종목 등록</h2>
            <form id="journalForm" action="journal_feature_process.php" method="POST" onsubmit="return validateForm();">
                <input type="hidden" id="journal_id" name="journal_id">
                <input type="hidden" name="page" value="<?php echo $page; ?>"> 

                <!-- 검색 조건을 hidden 필드로 추가 -->
                <input type="hidden" name="search_stock_name" value="<?php echo htmlspecialchars($searchStockName); ?>">
                <input type="hidden" name="search_type" value="<?php echo htmlspecialchars($searchType); ?>">
                <input type="hidden" name="search_status" value="<?php echo htmlspecialchars($searchStatus); ?>">

                <!-- 종목명/코드 -->
                <div class="form-row">
                    <label for="stock_name">종목명/코드:</label>
                    <input type="text" name="stock_name" id="stock_name" placeholder="종목명/코드" required onkeydown="return Common_SearchStock(event, this, focusOnBuyDate)" autocomplete="off">
                    <input type="text" name="stock_code" id="stock_code" readonly placeholder="코드">

                    <!-- 일자 선택 -->
                    <label for="journal_date">일자:</label>
                    <input type="date" name="journal_date" id="journal_date" value="<?php echo $tradeDate; ?>" required>

                    <!-- 구분 선택 -->
                    <label for="type">구분:</label>
                    <select name="type" id="type" required>
                        <option value="hot" <?php echo ($type === 'hot') ? 'selected' : ''; ?>>Hot</option>
                        <option value="xraytick" <?php echo ($type === 'xraytick') ? 'selected' : ''; ?>>Xraytick 연속</option>
                        <option value="ipo" <?php echo ($type === 'ipo') ? 'selected' : ''; ?>>신규주</option>
                    </select>
                </div>

                <!-- 코멘트 입력 -->
                <label for="comment">코멘트:</label>
                <textarea name="comment" id="comment" rows="4"></textarea>

                <!-- 상태 선택과 버튼 그룹 -->
                <div style="display: flex; align-items: center; margin-top: 10px;">
                    <!-- 상태 선택 -->
                    <label for="status" style="margin-right: 10px;">상태:</label>
                    <select name="status" id="status" required style="margin-right: 10px;">
                        <option value="normal" <?php echo ($status === 'normal') ? 'selected' : ''; ?>>일반</option>
                        <option value="focus" <?php echo ($status === 'focus') ? 'selected' : ''; ?>>집중</option>
                        <option value="watch_short" <?php echo ($status === 'watch_short') ? 'selected' : ''; ?>>지켜보기(단기)</option>
                        <option value="watch_long" <?php echo ($status === 'watch_long') ? 'selected' : ''; ?>>지켜보기(장기)</option>
                    </select>

                    <!-- 등록 버튼 -->
                    <button type="submit" id="register_button" style="display: block; margin-right: 10px;">등록</button>

                    <!-- 수정/삭제 버튼 그룹 -->

                    <div id="edit_buttons" style="display: none;">
                        <button type="button" onclick="updateJournal()">수정</button>
                        <button type="button" class="delete" onclick="deleteJournal()">삭제</button>
                        <button type="button" class="reset" style="float: right;" onclick="resetForm()">초기화</button>
                    </div>
                </div>
            </form>

            <!-- TinyMCE 적용 -->
            <?php loadTinyMCE('#comment', 800); ?>
            <?php loadTinyMCEScripts(); ?>

            <!-- 수정, 삭제 버튼 -->
        </div>

        <!-- 관심종목 리스트 -->
        <div id="journal_list_container">
            <h2>관심종목 목록</h2>

            <!-- 검색 조건 -->
            <div class="form-row">
                <input type="text" id="search_stock_name" name="search_stock_name" value="<?php echo htmlspecialchars($searchStockName); ?>" placeholder="종목명/코드" style="flex: 2; margin-right: 10px;">

                <select id="search_type" name="search_type" style="flex: 1; margin-right: 10px;">
                    <option value="" <?php echo ($searchType === '') ? 'selected' : ''; ?>>전체</option>
                    <option value="hot" <?php echo ($searchType === 'hot') ? 'selected' : ''; ?>>Hot</option>
                    <option value="xraytick" <?php echo ($searchType === 'xraytick') ? 'selected' : ''; ?>>Xraytick 연속</option>
                    <option value="ipo" <?php echo ($searchType === 'ipo') ? 'selected' : ''; ?>>신규주</option>
                </select>

                <select id="search_status" name="search_status" style="flex: 1; margin-right: 10px;">
                    <option value="" <?php echo ($searchStatus === '') ? 'selected' : ''; ?>>전체 상태</option>
                    <option value="normal" <?php echo ($searchStatus === 'normal') ? 'selected' : ''; ?>>일반</option>
                    <option value="focus" <?php echo ($searchStatus === 'focus') ? 'selected' : ''; ?>>집중</option>
                    <option value="watch_short" <?php echo ($searchStatus === 'watch_short') ? 'selected' : ''; ?>>지켜보기(단기)</option>
                    <option value="watch_long" <?php echo ($searchStatus === 'watch_long') ? 'selected' : ''; ?>>지켜보기(장기)</option>
                </select>

                <button onclick="searchJournals()" style="flex: 0.5; margin-right: 10px;">조회</button>
                <button onclick="resetSearch()" style="flex: 0.5;">초기화</button>
            </div>

            <?php while ($row = $journals->fetch_assoc()) { ?>
                <div class="journal-card">
                    <div class="journal-title" onclick="Common_OpenStockPopup('<?= htmlspecialchars($row['code']) ?>', '<?= htmlspecialchars($row['name']) ?>');">
                        <?php echo $row['status_str'] ." ". htmlspecialchars($row['name']).' ('.htmlspecialchars($row['code']).') / '.$row['type'];?>
                    </div>
                    <div onclick="loadJournalData(<?= $row['id']; ?>)">
                        <img class='img-fluid' width=545 src="https://ssl.pstatic.net/imgfinance/chart/item/candle/day/<?= $row['code'] ?>.png?sidcode=1705826920773">
                        <img class='img-fluid' width=545 src="https://ssl.pstatic.net/imgfinance/chart/item/candle/month/<?= $row['code'] ?>.png?sidcode=1705826920773">
                    </div>
                    <div class="journal-content" onclick="loadJournalData(<?= $row['id']; ?>)"><?php echo $row['comment']; ?></div>
                </div>
            <?php } ?>


            <!-- 페이지네이션 -->
            <div class="pagination">
                <?php
                $visiblePages = 5; // 한 번에 표시할 페이지 수
                $startPage = max(1, $page - floor($visiblePages / 2)); // 시작 페이지
                $endPage = min($totalPages, $startPage + $visiblePages - 1); // 마지막 페이지

                // 검색 조건을 URL에 추가
                $queryParams = "&stock_name=" . urlencode($searchStockName) . "&search_type=" . urlencode($searchType) . "&search_status=" . urlencode($searchStatus);

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
        function loadJournalData(journalId) {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "fetch_journal.php?journal_id=" + journalId + "&type=journal" + "&status=normal", true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var journal = JSON.parse(xhr.responseText);
                    
                    if (journal.error) {
                        alert(journal.error);
                        return;
                    }

                    document.getElementById('journal_id').value = journal.id;
                    document.getElementById('stock_name').value = journal.name;
                    document.getElementById('stock_code').value = journal.code;
                    document.getElementById('journal_date').value = journal.journal_date;
                    document.getElementById('type').value = journal.type;
                    document.getElementById('status').value = journal.status;
                    setTinyMCEContent('comment', journal.comment);

                    document.getElementById('edit_buttons').style.display = 'block';
                    document.getElementById('register_button').style.display = 'none';
                }
            };
            xhr.send();
        }
    
        function focusOnBuyDate() {
            const tradeDateInput = document.getElementById('journal_date');
            if (tradeDateInput) {
                tradeDateInput.focus();
            }
        }

        function updateJournal() {
            syncTinyMCEData();
            document.getElementById('journalForm').submit();
        }

        function deleteJournal() {
            if (confirm('정말로 이 종목 정보를 삭제하시겠습니까?')) {
                var journalId = document.getElementById('journal_id').value;
                window.location.href = "journal_feature_process.php?action=delete&journal_id=" + journalId + "&page=<?php echo $page; ?>";
            }
        }

        function resetForm() {
            window.location.href = "?page=<?php echo $page; ?>";
        }

        function searchJournals() {
            const stockName = document.getElementById('search_stock_name').value;
            const type = document.getElementById('search_type').value;
            const status = document.getElementById('search_status').value;
            let query = "?page=1";

            if (stockName) {
                query += "&stock_name=" + encodeURIComponent(stockName);
            }

            if (type) {
                query += "&search_type=" + type;
            }

            if (status) {
                query += "&search_status=" + status;
            }

            window.location.href = query;
        }

        function resetSearch() {
            window.location.href = "?page=1";
        }

    </script>
</body>