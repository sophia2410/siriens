<?php
$pageTitle = "키워드 그룹별 이슈 조회"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT']."/modules/market/event_list.php");

$searchKeyword = $_GET['keyword'] ?? '';
$keywordGroups = [];

// 키워드 그룹 조회 쿼리
$keywordGroupQuery = $mysqli->prepare("
    SELECT group_id, group_name 
    FROM keyword_groups 
    WHERE group_name LIKE CONCAT('%', ?, '%') 
    ORDER BY group_name ASC
");
$keywordGroupQuery->bind_param('s', $searchKeyword);
$keywordGroupQuery->execute();
$keywordGroupsResult = $keywordGroupQuery->get_result();

while ($row = $keywordGroupsResult->fetch_assoc()) {
    $keywordGroups[] = $row;
}

$criteriaType = 'keyword_group';
$criteriaValue = $_GET['group_id'] ?? '';

// 종목리스트
function render_stock_list($mysqli, $groupName = '', $groupId = '') {
    $groupCondition = ($groupId !== '') ? "kg.group_id = ?" : "kg.group_name LIKE CONCAT('%', ?, '%')";

    $stockQuery = $mysqli->prepare("
        SELECT 
            kg.group_id, kg.group_name, 
            MAX(mes.date) AS last_event_date, 
            mes.code AS stock_code, 
            (SELECT name FROM stock s WHERE s.code = mes.code AND last_yn = 'Y') AS stock_name, 
            MAX(mes.close_rate) AS close_rate, 
            MAX(mes.trade_amount) AS trade_amount, 
            SUM(mes.trade_amount) AS total_trade_amount, 
            COUNT(*) AS stock_event_count, 
            COUNT(*) OVER(PARTITION BY mes.code) AS stock_count
        FROM market_events me
        JOIN market_event_stocks mes ON me.event_id = mes.event_id
        JOIN keyword_groups kg ON me.keyword_group_id = kg.group_id
        WHERE $groupCondition
        GROUP BY kg.group_id, kg.group_name, mes.code
        ORDER BY stock_count DESC, total_trade_amount DESC, mes.code, close_rate DESC
    ");

    if ($groupId !== '') {
        $stockQuery->bind_param('i', $groupId);
    } else {
        $stockQuery->bind_param('s', $groupName);
    }

    $stockQuery->execute();
    $stockResults = $stockQuery->get_result();

    if ($stockResults->num_rows > 0) {
        $currentStockCode = null;
        $colorToggle = false; // 색상 토글을 위한 변수

        echo "<table><tr><th>종목명</th><th>키워드 그룹</th><th>최종일자</th><th>최고 등락률</th><th>거래대금</th><th>건수</th></tr>";
        while ($row = $stockResults->fetch_assoc()) {
            // 종목이 바뀔 때마다 색상을 토글
            if ($currentStockCode !== $row['stock_code']) {
                $colorToggle = !$colorToggle;
                $currentStockCode = $row['stock_code'];
                $stockName = $row['stock_name']; // 새로운 종목의 이름 설정
            } else {
                $stockName = ''; // 동일 종목일 경우 종목명 출력 안 함
            }

            // 토글된 색상 적용
            $rowColor = $colorToggle ? '#f2f2f2' : '#ffffff';

            echo "<tr style='background-color: $rowColor;'>";

            // 종목명 출력 (새 종목일 때만 출력)
            echo "<td>{$stockName}</td>";

            // 나머지 열 출력
            echo "<td>{$row['group_name']}</td>
                  <td>{$row['last_event_date']}</td>
                  <td align=right>".number_format($row['close_rate'],2)." %</td>
                  <td align=right>".number_format($row['trade_amount'])." 억</td>
                  <td>{$row['stock_event_count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>등록된 종목이 없습니다.</p>";
    }
}
?>

<head>
    <!-- 페이지 전용 스타일 -->
    <style>
        #keyword_group_panel {
            flex: 2;
            background-color: #f8f8f8;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            padding: 20px;
        }

        #event_list_panel {
            flex: 6;
            background-color: #fff;
            padding: 20px;
            overflow-y: auto;
        }

        #stock_list_panel {
            flex: 3;
            background-color: #eef;
            padding: 20px;
            overflow-y: auto;
        }

        .search-bar {
            display: flex;
            margin-bottom: 20px;
        }

        #keyword_search_input {
            flex: 1;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
        }

        .keyword-group-item {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            transition: background-color 0.3s;
            display: flex;
            justify-content: space-between;
        }

        .keyword-group-item:hover {
            background-color: #e9ecef;
        }

        .keyword-group-item.active {
            background-color: #007bff;
            color: white;
        }

        /* The Modal (background) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgb(0,0,0); /* Fallback color */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            padding-top: 60px;
        }

        /* Modal Content/Box */
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; /* 15% from the top and centered */
            padding: 20px;
            border: 1px solid #888;
            width: 30%; /* Could be more or less, depending on screen size */
        }

        /* The Close Button */
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

    </style>
</head>

<body>
    <div id="container" style="display: flex;">
        <!-- 키워드 그룹 리스트 및 검색 -->
        <div id="keyword_group_panel">
            <h2>키워드 그룹 조회</h2>
            <form method="get" action="keyword_group_list.php">
                <div class="search-bar">
                    <input type="text" id="keyword_search_input" name="keyword" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="키워드 입력" autocomplete="off">
                    <button type="submit">검색</button>
                </div>
            </form>

            <?php if (count($keywordGroups) > 0): ?>
                <h3>키워드 그룹 리스트</h3>
                <?php foreach ($keywordGroups as $group): ?>
                    <div class="keyword-group-item <?= ($criteriaValue == $group['group_id']) ? 'active' : '' ?>">
                        <span onclick="window.location.href='keyword_group_list.php?group_id=<?= $group['group_id'] ?>&keyword=<?= urlencode($searchKeyword) ?>'">
                            <?= htmlspecialchars($group['group_name']) ?>
                        </span>
                        <button type="button" class="button-green" onclick="editKeywordGroup(<?= $group['group_id'] ?>, '<?= htmlspecialchars($group['group_name']) ?>')">수정</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>해당 키워드에 대한 그룹이 없습니다.</p>
            <?php endif; ?>
        </div>

        <div id="keywordGroupModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>키워드 그룹 수정</h2>
                <form id="keywordGroupForm">
                    <input type="hidden" id="modalGroupId">
                    <label for="modalGroupName">키워드 그룹:</label>
                    <input type="text" id="modalGroupName" placeholder="키워드 입력" required>
                    <div id="currentKeywords" style="margin-top: 10px;">
                        <!-- 현재 키워드 표시 영역 -->
                    </div>
                    <button type="submit">수정</button>
                </form>
            </div>
        </div>

        <!-- 이슈 리스트 화면 -->
        <div id="event_list_panel">
            <?php 
            if ($criteriaValue) {
                render_event_list($mysqli, $criteriaType, $criteriaValue);
            } else {
                echo "<p>키워드 그룹을 선택해 주세요.</p>";
            }
            ?>
        </div>

        <!-- 추가된 종목 리스트 패널 -->
        <div id="stock_list_panel">
            <h2>키워드에 속한 종목</h2>
            <div id="stock_list_content">
                <!-- 종목 리스트가 여기에 출력됩니다. -->
                <?php render_stock_list($mysqli, $searchKeyword, $criteriaValue); ?>
            </div>
        </div>

    </div>
    <script>
        // 모달 열기 및 닫기
        function editKeywordGroup(groupId, groupName) {
            document.getElementById('modalGroupId').value = groupId;
            document.getElementById('modalGroupName').value = groupName;

            // 현재 키워드를 표시
            document.getElementById('currentKeywords').innerHTML = `<strong>현재 키워드:</strong> ${groupName}`;

            document.getElementById('keywordGroupModal').style.display = "block";
        }

        // 모달 닫기
        document.querySelector('.close').addEventListener('click', function() {
            document.getElementById('keywordGroupModal').style.display = "none";
        });

        // 모달 외부 클릭 시 닫기
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('keywordGroupModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        });

        // 폼 제출 시 처리
        document.getElementById('keywordGroupForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const groupId = document.getElementById('modalGroupId').value;
            const newKeywords = document.getElementById('modalGroupName').value;

            // 키워드 수정 요청
            window.location.href = `keyword_group_process.php?group_id=${groupId}&new_keywords=${encodeURIComponent(newKeywords)}`;
        });

    </script>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
</body>
</html>
