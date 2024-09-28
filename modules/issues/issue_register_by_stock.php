<?php
$pageTitle = "테마 종목 조회 및 저장"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");

// 기본적으로 조회 일자를 설정
$today = date('Y-m-d');
$reportDate = $_GET['report_date'] ?? $today;

// v_daily_price 기준으로 종목 조회
$stocks = [];
$query = "
    SELECT code, name, close_rate, volume, amount, source,
           CASE WHEN amount > 1000 THEN '1' ELSE '0' END is_leader,
           CASE WHEN close_rate > 15 THEN '1' ELSE '0' END is_watchlist
    FROM v_daily_price
    WHERE date = ?
    AND ((amount > 30 AND close_rate > 7) OR (amount > 300 AND close_rate > 5))
    ORDER BY close_rate DESC
";
// Database_logQuery($query, [$reportDate]);
$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $reportDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stocks[] = $row;
}

// 등록된 이슈 내역 조회
$issues = [];
foreach ($stocks as $stock) {
    $code = $stock['code'];

    // 기본적으로 v_daily_price에서 가져온 데이터를 먼저 넣어둠
    $issues[$code] = [
        'code' => $stock['code'],
        'name' => $stock['name'],
        'close_rate' => $stock['close_rate'],
        'volume' => $stock['volume'],
        'trade_amount' => $stock['amount'],
        'issue_id' => '',
        'keyword_group_name' => '',
        'theme' => '',
        'sector' => '',
        'stock_comment' => '',
        'date' => 'N/A',
        'group_str' => '기타'
    ];

    // print_r($issues[$code]);

    $issueQuery = "
        SELECT 
            mi.issue_id AS issue_id,
            mi.date AS date,
            mi.issue AS issue,
            mi.first_occurrence AS first_occurrence,
            mi.group_label AS group_label,
            mi.keyword_group_id AS keyword_group_id,
            kgm.group_name AS keyword_group_name,
            mi.theme AS theme,
            mi.hot_theme AS hot_theme,
            mis.code AS code,
            mis.name AS name,
            mis.sector AS sector,
            mis.stock_comment AS stock_comment,
            mis.is_leader AS is_leader,
            mis.is_watchlist AS is_watchlist,
            CASE WHEN theme != '' THEN theme WHEN sector != '' THEN sector ELSE '기타' END AS group_str
        FROM 
            market_issues mi
        JOIN 
            market_issue_stocks mis ON mi.issue_id = mis.issue_id
        LEFT JOIN 
            keyword_groups kgm ON mi.keyword_group_id = kgm.group_id
        WHERE mis.code = ?
        AND mis.date <= ?
        ORDER BY mi.date DESC
        LIMIT 1
    ";

    // Database_logQuery($issueQuery, [$code]);
    $issueStmt = $mysqli->prepare($issueQuery);
    $issueStmt->bind_param('ss', $code, $reportDate);
    $issueStmt->execute();
    $issueResult = $issueStmt->get_result();
    
    // 이슈 데이터가 있으면 덮어쓰기
    if ($issue = $issueResult->fetch_assoc()) {
        // v_daily_price에서 가져온 데이터에 이슈 정보만 덮어쓰기
        $issues[$code] = array_merge($issues[$code], [
            'issue_id' => $issue['issue_id'] ?? '',
            'keyword_group_name' => $issue['keyword_group_name'] ?? '',
            'theme' => $issue['theme'] ?? '',
            'sector' => $issue['sector'] ?? '',
            'stock_comment' => $issue['stock_comment'] ?? '',
            'date' => $issue['date'] ?? 'N/A',
            'group_str' => $issue['group_str'] ?? '개별주'
        ]);
    }

    // print_r($issues[$code]);
    // print_r("<hr>");
}

// 테마별로 그룹화된 데이터를 저장할 배열
$themes = [];

// 테마별로 그룹화
foreach ($issues as $issue) {
    $group_str = $issue['group_str'];

    // 테마가 없을 경우 '기타'로 처리
    if (!isset($themes[$group_str])) {
        $themes[$group_str] = [];
    }

    // 각 필드에 데이터를 할당하여 이슈 배열을 정리
    $themes[$group_str][] = [
        'date' => $issue['date'],
        'code' => $issue['code'],
        'name' => $issue['name'],
        'close_rate' => $issue['close_rate'],
        'volume' => $issue['volume'],
        'trade_amount' => $issue['trade_amount'],
        'issue_id' => $issue['issue_id'],
        'keyword_group_name' => $issue['keyword_group_name'],
        'theme' => $issue['theme'],
        'sector' => $issue['sector'],
        'stock_comment' => $issue['stock_comment']
    ];
}

// 각 테마 그룹 내에서 keyword_group_name을 기준으로 정렬
foreach ($themes as &$themeIssues) {
    usort($themeIssues, function($a, $b) {
        return strcmp($a['keyword_group_name'], $b['keyword_group_name']);
    });
}
unset($themeIssues); // 참조 해제

// 테마별 종목 수 기준으로 내림차순 정렬
uasort($themes, function($a, $b) {
    return count($b) - count($a); // 종목 수 기준 내림차순 정렬
});

// print_r($themes); // 정렬된 배열 출력
?>

<head>
    <style>
        /* 컨테이너와 메인 콘텐츠의 전체 화면 너비 사용 */
        #main_content {
            width: 100%;
            padding: 20px;
            box-sizing: border-box;
        }

        /* 버튼 및 조회 폼의 좌우 정렬 */
        .form-inline {
            display: flex;
            justify-content: space-between; /* 좌우 정렬 */
            align-items: center;
            width: 100%;
            margin-bottom: 10px;
        }

        .form-inline div {
            display: flex;
            align-items: center;
        }

        .submit-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
        }

        .submit-button:hover {
            background-color: #0056b3;
        }

        .checkbox-cell {
            text-align: center;
        }
    </style>
</head>

<body>
<div id="container">
    <div id="main_content">
        <!-- 조회 폼 -->
        <form method="GET" class="form-inline" id="reportForm">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <!-- 좌측: 조회 일자, 조회 버튼 -->
                <div style="flex: 1;">
                    <label for="report_date">조회 일자: </label>
                    <input type="date" id="report_date" name="report_date" 
                        style="width: 150px;"
                        value="<?= htmlspecialchars($reportDate) ?>" 
                        onchange="document.getElementById('reportForm').submit();">
                    <!-- 날짜가 변경될 때 form이 자동으로 제출되도록 설정 -->
                </div>
                <!-- 우측: 선택 종목 일괄 저장 버튼 -->
                <div style="flex: 1; text-align: right;">
                    <button type="button" class="submit-button" onclick="saveSelectedStocks()">선택 종목 일괄 저장</button>
                </div>
            </div>
        </form>

        <!-- 종목 테이블 -->
        <form method="POST" action="issue_process.php" id="saveForm" autocomplete="off">
            <input type="hidden" name="action" value="register_by_stock">
            <input type="hidden" name="report_date" value="<?= htmlspecialchars($reportDate) ?>">
            <table>
                <thead>
                    <tr>
                        <th width=100>선택</th>
                        <th width=100>코드</th>
                        <th width=200>종목명</th>
                        <th width=100>등락률</th>
                        <th width=100>거래대금</th>
                        <th width=250>키워드</th>
                        <th width=150>테마</th>
                        <th width=150>섹터</th>
                        <th>종목코멘트</th>
                        <th>등록일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($themes as $theme => $themeIssues): ?>
                        <!-- 테마별로 출력 -->
                        <tr style="background-color: #f4f4f4;">
                            <td colspan="10"><strong>테마: <?= htmlspecialchars($theme) ?> (<?= count($themeIssues) ?> 종목)</strong></td>
                        </tr>
                        <?php foreach ($themeIssues as $issue): ?>
                            <?php
                                $is_existing = $reportDate == $issue['date'] ? 'Y' : null; // 조회일 등록여부 체크

                                $stockNameClass = Utility_GetStockNameAmountClass($issue['trade_amount']);
                                $closeRateClass = Utility_GetCloseRateClass($issue['close_rate']);
                                $amountClass = Utility_GetAmountClass($issue['trade_amount']);
                            ?>
                            <tr <?= $is_existing ? 'class="highlighted-row"' : '' ?> class="issue_register_stock_item">
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="selected_stocks[]" value="<?= htmlspecialchars($issue['code']) ?>">
                                    <input type="hidden" name="issues[<?= htmlspecialchars($issue['code']) ?>][is_existing]" value="<?= $is_existing ? '1' : '0' ?>">
                                    <input type="hidden" name="issues[<?= htmlspecialchars($issue['code']) ?>][issue_id]" value="<?= $is_existing ? htmlspecialchars($issue['issue_id']) : '' ?>">
                                </td>
                                <td>
                                    <input type="text" name="issues[<?= htmlspecialchars($issue['code']) ?>][code]" value="<?= htmlspecialchars($issue['code']) ?>" readonly>
                                    <input type="hidden" name="issues[<?= htmlspecialchars($issue['code']) ?>][name]" value="<?= htmlspecialchars($issue['name']) ?>">
                                </td>
                                <td class="<?= $stockNameClass; ?>"><?= htmlspecialchars($issue['name']) ?></td>
                                <td class="<?= $closeRateClass; ?>" style="display: table-cell;"><?= number_format($issue['close_rate'], 2) ?> %</td>
                                <td class="<?= $amountClass; ?>" style="width:9%; display: table-cell;"><?= number_format($issue['trade_amount']) ?> 억</td>
                                <td>
                                    <input type="text" name="issues[<?= htmlspecialchars($issue['code']) ?>][keyword]" placeholder="키워드 입력" 
                                    value="<?= htmlspecialchars($issue['keyword_group_name']) ?>"
                                    data-original="<?= htmlspecialchars($issue['keyword_group_name']) ?>" 
                                    oninput="checkForChanges(this)" autocomplete="off">
                                </td>
                                <td>
                                    <input type="text" name="issues[<?= htmlspecialchars($issue['code']) ?>][theme]" placeholder="테마 입력" 
                                    value="<?= htmlspecialchars($issue['theme']) ?>"
                                    data-original="<?= htmlspecialchars($issue['theme']) ?>" 
                                    oninput="checkForChanges(this)" autocomplete="off">
                                </td>
                                <td>
                                    <input type="text" name="issues[<?= htmlspecialchars($issue['code']) ?>][sector]" placeholder="섹터 입력" 
                                    value="<?= htmlspecialchars($issue['sector']) ?>" 
                                    data-original="<?= htmlspecialchars($issue['sector']) ?>" 
                                    oninput="checkForChanges(this)" autocomplete="off">
                                </td>
                                <td>
                                    <input type="text" name="issues[<?= htmlspecialchars($issue['code']) ?>][comment]" placeholder="종목 코멘트 입력" 
                                    onfocus="fetchStockComment(this)" 
                                    value="<?= htmlspecialchars($issue['stock_comment']) ?>" 
                                    data-original="<?= htmlspecialchars($issue['stock_comment']) ?>" 
                                    oninput="checkForChanges(this)" autocomplete="off">
                                </td>
                                <td><?= htmlspecialchars($issue['date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- 선택 종목 일괄 저장 버튼 -->
            <div style="text-align: right; margin-top: 10px;">
                <button type="button" class="submit-button" onclick="saveSelectedStocks()">선택 종목 일괄 저장</button>
            </div>
        </form>
    </div>
</div>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
<script>
    function IssueRegisterByStock_Initialize() {
		console.log("이슈 등록 초기화");

        function saveSelectedStocks() {
            // 선택된 종목이 있는지 확인
            const selectedStocks = document.querySelectorAll('input[name="selected_stocks[]"]:checked');

            if (selectedStocks.length === 0) {
                alert("저장할 종목을 선택해 주세요.");
                return; // 선택된 종목이 없으면 함수 종료
            }

            let hasEmptyKeyword = false; // 키워드 미입력 체크용 플래그

            // 선택된 종목의 키워드 필드를 체크
            selectedStocks.forEach(function(stockCheckbox) {
                const stockCode = stockCheckbox.value;  // 종목 코드 가져오기
                const keywordInput = document.querySelector(`input[name="issues[${stockCode}][keyword]"]`);
                
                if (!keywordInput || keywordInput.value.trim() === '') {
                    hasEmptyKeyword = true;
                    // 키워드가 비어 있으면 경고 표시 (빨간 테두리)
                    keywordInput.style.border = '2px solid red';
                } else {
                    // 정상 입력된 경우 원래 상태로 복구
                    keywordInput.style.border = '';
                }
            });

            // 키워드가 입력되지 않은 종목이 있는 경우 경고 메시지 출력
            if (hasEmptyKeyword) {
                alert("모든 종목의 키워드를 입력해 주세요.");
                return;  // 키워드가 입력되지 않으면 폼 제출하지 않음
            }

            // 사용자에게 확인 메시지
            if (confirm("선택한 종목을 저장하시겠습니까?")) {

                // 선택된 종목이 있으면 폼을 제출
                const saveForm = document.getElementById('saveForm');
                if (saveForm) {
                    saveForm.submit();  // 폼 제출
                } else {
                    console.error("폼을 찾을 수 없습니다.");
                }
            }
        }

        // 버튼에 이벤트 연결
        const submitButton = document.querySelector('.submit-button');
        if (submitButton) {
            submitButton.addEventListener('click', saveSelectedStocks);
        } else {
            console.error("저장 버튼을 찾을 수 없습니다.");
        }



        // 키워드 자동완성 적용
        $('input[name*="[keyword]"]').on('input', function() {
            let $this = $(this); // 현재 입력 중인 필드
            let query = $this.val();

            if (query.length > 0) {
                $.ajax({
                    url: 'fetch_autocomplete.php',
                    type: 'GET',
                    data: { type: 'keywords', q: query },
                    success: function(data) {
                        let keywordGroups = JSON.parse(data);

                        // 현재 입력 필드에 자동완성 적용
                        $this.autocomplete({
                            source: keywordGroups
                        });
                    }
                });
            }
        });

        // 코멘트 자동완성 처리
        window.fetchStockComment = function(input) {
            // input으로부터 해당 종목의 코드를 찾음
            const stockRow = input.closest('.issue_register_stock_item').querySelector('input[name^="issues"][name$="[code]"]');
            const stockCode = stockRow.value;

            if (stockCode) {
                $.ajax({
                    url: 'fetch_autocomplete.php',  // 자동완성 데이터를 가져오는 URL
                    type: 'GET',
                    data: { type: 'stock_comments', code: stockCode },
                    success: function(data) {
                        // 응답 데이터를 JSON으로 변환
                        const comments = JSON.parse(data);

                        // 자동완성 기능 적용
                        $(input).autocomplete({
                            source: comments,  // 가져온 코멘트를 자동완성 데이터로 사용
                            minLength: 0       // 빈 입력에도 자동완성 목록을 표시
                        }).autocomplete("search", ""); // 자동완성 트리거
                    }
                });
            }
        };
        
    }

    function checkForChanges(input) {
        const row = input.closest('tr');
        const checkbox = row.querySelector('input[type="checkbox"]');  // 해당 행의 체크박스

        // 입력 항목들을 모두 가져옴
        const keywordInput = row.querySelector('input[name*="[keyword]"]');
        const themeInput = row.querySelector('input[name*="[theme]"]');
        const sectorInput = row.querySelector('input[name*="[sector]"]');
        const commentInput = row.querySelector('input[name*="[comment]"]');

        // 각각의 입력 항목의 기존값과 현재값을 비교
        const keywordChanged = keywordInput.getAttribute('data-original') !== keywordInput.value;
        const themeChanged = themeInput.getAttribute('data-original') !== themeInput.value;
        const sectorChanged = sectorInput.getAttribute('data-original') !== sectorInput.value;
        const commentChanged = commentInput.getAttribute('data-original') !== commentInput.value;

        // 하나라도 변경되었으면 체크박스를 선택, 모두 원래 값이면 해제
        if (keywordChanged || themeChanged || sectorChanged || commentChanged) {
            checkbox.checked = true;  // 변경이 있으면 체크박스 선택
        } else {
            checkbox.checked = false;  // 모든 값이 원래 값이면 체크 해제
        }
    }
</script>

</body>
</html>
