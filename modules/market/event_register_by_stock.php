<?php
$pageTitle = "이벤트 종목 조회 및 저장"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");

// 기본적으로 조회 일자를 설정
$today = date('Y-m-d');
$reportDate = $_GET['report_date'] ?? $today;
$dataSource = $_GET['data_source'] ?? 'rate'; // 조회 기준 선택 (등락률 또는 엑셀 업로드)

// 엑셀 파일 처리 버튼이 눌렸을 경우, Python 스크립트 실행 후 엑셀 업로드 기준으로 설정
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['process_excel'])) {
    $reportDate = $_GET['report_date'];

    // Python 스크립트를 호출하여 엑셀 파일 처리
	$command = escapeshellcmd("C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe E:/Project/202410/www/modules/market/event_upload_excel.py" . escapeshellarg($reportDate));

    $command = "C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe E:/Project/202410/www/modules/market/event_upload_excel.py ". escapeshellarg($reportDate);
	$output = shell_exec($command . " 2>&1");

    // Python 스크립트 처리 결과 확인
    if (strpos($output, 'Error') !== false || empty($output)) {
        echo "<script>alert('엑셀 파일 처리 중 오류가 발생했습니다. 파일을 확인하세요.'); window.history.back();</script>";
    } else {
        echo "<script>alert('엑셀 파일이 성공적으로 처리되었습니다.'); window.location.href = 'event_register_by_stock.php?report_date=$reportDate&data_source=excel';</script>";
    }
    
    exit;
}

// 종목 조회 데이터 초기화
$stocks = [];

// v_daily_price 기준으로 종목 조회 또는 엑셀 업로드된 종목 조회
if ($dataSource === 'rate') {
    // 등락률 기준 데이터 조회
    $query = "
        SELECT code, MAX(name) AS name, MAX(close_rate) AS close_rate,  MAX(volume) AS volume, MAX(amount) AS amount 
        FROM (
                SELECT vdp.code, vdp.name, vdp.close_rate, vdp.volume, vdp.amount
                FROM v_daily_price vdp
                WHERE vdp.date = ?
                AND ((vdp.amount > 30 AND vdp.close_rate > 7) OR (vdp.amount > 300 AND vdp.close_rate > 5))
                UNION ALL
                SELECT mes.code, mes.name, mes.close_rate, mes.volume, mes.trade_amount AS amount
                FROM market_event_stocks mes
                WHERE mes.date = ?
            ) ua
        GROUP BY code
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ss', $reportDate, $reportDate);
} else {
    // 엑셀 업로드된 데이터 조회
    $query = "
        SELECT us.code, us.name, dp.close_rate, dp.volume, dp.amount,
               CASE WHEN dp.amount > 1000 THEN '1' ELSE '0' END is_leader,
               CASE WHEN dp.close_rate > 15 THEN '1' ELSE '0' END is_watchlist
        FROM market_event_upload us
        JOIN v_daily_price dp ON us.code = dp.code AND dp.date = ? 
        WHERE us.date = ? 
        ORDER BY us.id
    ";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ss', $reportDate, $reportDate);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stocks[] = $row;
}

// 등록된 이슈 내역 조회 및 매핑
$events = [];
foreach ($stocks as $stock) {
    $code = $stock['code'];

    // 기본적으로 v_daily_price에서 가져온 데이터를 먼저 넣어둠
    $default_group_str = ($dataSource == 'rate') ? '기타' : 'excel';
    $events[$code] = [
        'code' => $stock['code'],
        'name' => $stock['name'],
        'close_rate' => $stock['close_rate'],
        'volume' => $stock['volume'],
        'trade_amount' => $stock['amount'],
        'event_id' => '',
        'keyword_group_name' => '',
        'theme' => '',
        'hot_theme' => '',
        'is_leader' => '',
        'is_watchlist' => '',
        'stock_comment' => '',
        'date' => 'N/A',
        'group_str' => $default_group_str
    ];

    $eventQuery = "
        SELECT 
            me.event_id AS event_id,
            me.date AS date,
            me.group_label AS group_label,
            me.keyword_group_id AS keyword_group_id,
            kgm.group_name AS keyword_group_name,
            me.theme AS theme,
            CASE WHEN me.date = ? THEN me.hot_theme ELSE 'N' END AS hot_theme,
            mes.code AS code,
            mes.name AS name,
            mes.stock_comment AS stock_comment,
            CASE WHEN me.date = ? THEN mes.is_leader ELSE '0' END AS is_leader,
            CASE WHEN me.date = ? THEN mes.is_watchlist ELSE '0' END AS is_watchlist,
            CASE 
                WHEN ? = 'rate' THEN me.group_label
                ELSE 'excel'
            END AS group_str
        FROM 
            market_events me
        JOIN 
            market_event_stocks mes ON me.event_id = mes.event_id
        LEFT JOIN 
            keyword_groups kgm ON me.keyword_group_id = kgm.group_id
        WHERE mes.code = ?
        AND mes.date <= ?
        ORDER BY me.date DESC
        LIMIT 1
    ";
    $eventStmt = $mysqli->prepare($eventQuery);
    $eventStmt->bind_param('ssssss', $reportDate, $reportDate, $reportDate, $dataSource, $code, $reportDate);
    $eventStmt->execute();
    $eventResult = $eventStmt->get_result();
    
    if ($event = $eventResult->fetch_assoc()) {
        $events[$code] = array_merge($events[$code], [
            'event_id' => $event['event_id'] ?? '',
            'keyword_group_name' => $event['keyword_group_name'] ?? '',
            'theme' => $event['theme'] ?? '',
            'hot_theme' => $event['hot_theme'] ?? 'N',
            'is_leader' => $event['is_leader'] ?? '0',
            'is_watchlist' => $event['is_watchlist'] ?? '0',
            'stock_comment' => $event['stock_comment'] ?? '',
            'date' => $event['date'] ?? 'N/A',
            'group_str' => $event['group_str'] ?? '개별주'
        ]);
    }
}

// 테마별로 그룹화 및 정렬
$groups = [];

foreach ($events as $event) {
    if (is_array($event)) {
        // 테마별로 그룹화하여 $groups에 담음
        $group_str = $event['group_str'];
        if (!isset($groups[$group_str])) {
            $groups[$group_str] = [];
        }
        $groups[$group_str][] = [
            'date' => $event['date'],
            'code' => $event['code'],
            'name' => $event['name'],
            'close_rate' => $event['close_rate'],
            'volume' => $event['volume'],
            'trade_amount' => $event['trade_amount'],
            'event_id' => $event['event_id'],
            'keyword_group_name' => $event['keyword_group_name'],
            'theme' => $event['theme'],
            'hot_theme' => $event['hot_theme'],
            'is_leader' => $event['is_leader'],
            'is_watchlist' => $event['is_watchlist'],
            'stock_comment' => $event['stock_comment']
        ];
    } else {
        // 배열이 아닌 경우 경고 표시 (문제 파악을 위해서)
        echo "Warning: Invalid event format. Event is not an array.";
    }
}

// 'rate' 기준의 경우 keyword_group_name을 기준으로 정렬
if ($dataSource === 'rate') {
    // 각 테마 그룹 내에서 keyword_group_name을 기준으로 정렬
    foreach ($groups as &$groupEvents) {
        usort($groupEvents, function($a, $b) {
            // 1. keyword_group_name으로 먼저 정렬 (오름차순)
            $result = strcmp($a['keyword_group_name'], $b['keyword_group_name']);
            if ($result !== 0) {
                return $result; // keyword_group_name이 다르면 그대로 반환
            }

            // 2. is_leader로 정렬 (내림차순)
            if ($a['is_leader'] !== $b['is_leader']) {
                return $b['is_leader'] - $a['is_leader'];
            }

            // 3. is_watchlist로 정렬 (내림차순)
            if ($a['is_watchlist'] !== $b['is_watchlist']) {
                return $b['is_watchlist'] - $a['is_watchlist'];
            }

            // 4. trade_amount로 정렬 (내림차순)
            return $b['trade_amount'] <=> $a['trade_amount'];
        });
    }
    unset($groupEvents);

    // 테마별 종목 수 기준으로 내림차순 정렬
    uasort($groups, function($a, $b) {
        return count($b) - count($a);
    });
}

// 'excel' 기준일 때는 정렬을 생략하고 원래 순서대로 표시
else if ($dataSource === 'excel') {
    // 엑셀 업로드된 데이터는 정렬 없이 출력
    // 별도의 처리 불필요: $groups는 이미 위에서 생성됨
}
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

        .checkbox-cell {
            text-align: center;
        }

        /* 라디오 버튼 스타일 */
        .radio-group {
            margin-left: 20px;
        }

        /* 파일 업로드와 조회 일자를 같은 라인에 배치 */
        .inline-form {
            display: flex;
            align-items: center;
            justify-content: space-between; /* 좌우 정렬 */
            width: 100%;
        }

        .inline-form label, .inline-form input {
            margin-right: 10px;
        }
    </style>
</head>

<body>
<div id="container">
    <div id="main_content">
        <!-- 조회 폼 -->
        <form method="GET" class="form-inline" id="reportForm">
            <div class="inline-form" style="flex: 1;">
                <div>
                    <label for="report_date">조회 일자: </label>
                    <input type="date" id="report_date" name="report_date" 
                        style="width: 150px;"
                        value="<?= htmlspecialchars($reportDate) ?>" 
                        onchange="document.getElementById('reportForm').submit();"> <!-- 조회일 변경 시 자동 제출 -->

                        <div class="radio-group">
                            <label><input type="radio" name="data_source" value="rate" <?= ($dataSource == 'rate') ? 'checked' : '' ?> onchange="document.getElementById('reportForm').submit();"> 등락률 기준</label>
                            <label><input type="radio" name="data_source" value="excel" <?= ($dataSource == 'excel') ? 'checked' : '' ?> onchange="document.getElementById('reportForm').submit();"> 엑셀 업로드 데이터</label>
                        </div>

                    <button type="submit" class="button-green" name="process_excel">엑셀 데이터 처리</button>
                </div>

                <!-- 우측: 선택 종목 일괄 저장 버튼 -->
                <div style="text-align: right;">
                    <button type="button" id="saveButton" onclick="saveSelectedStocks()">선택 종목 저장</button>
                </div>
            </div>
        </form>

        <!-- 종목 테이블 -->
        <form method="POST" action="event_process.php" id="saveForm" autocomplete="off">
            <input type="hidden" name="action" value="register_by_stock">
            <input type="hidden" name="report_date" value="<?= htmlspecialchars($reportDate) ?>">
            <table>
                <thead>
                    <tr>
                        <th width=60>선택</th>
                        <th width=250>키워드</th>
                        <th width=150>테마</th>
                        <th width=70>핫테마</th>
                        <th width=100>코드</th>
                        <th width=200>종목명</th>
                        <th width=70>주도주</th>
                        <th width=70>관.종</th>
                        <th width=80>등락률</th>
                        <th width=90>거래대금</th>
                        <th>종목코멘트</th>
                        <th width=100>등록일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group => $groupEvents): ?>
                        <!-- 테마별로 출력 -->
                        <tr style="background-color: #f4f4f4;">
                            <td colspan="13"><strong>그룹: <?= htmlspecialchars($group) ?> (<?= count($groupEvents) ?> 종목)</strong></td>
                        </tr>
                        <?php foreach ($groupEvents as $event): ?>
                            <?php
                                $is_existing = $reportDate == $event['date'] ? 'Y' : null; // 조회일 등록여부 체크

                                $stockNameClass = Utility_GetStockNameAmountClass($event['trade_amount']);
                                $closeRateClass = Utility_GetCloseRateClass($event['close_rate']);
                                $amountClass = Utility_GetAmountClass($event['trade_amount']);

                                // 체크박스 상태 설정
                                $hotThemeChecked = $event['hot_theme'] === 'Y' ? 'checked' : '';
                                $isLeaderChecked = $event['is_leader'] === '1' ? 'checked' : '';
                                $isWatchlistChecked = $event['is_watchlist'] === '1' ? 'checked' : '';
                            ?>
                            <tr <?= $is_existing ? 'class="highlighted-row"' : '' ?> class="event_register_stock_item">
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="selected_stocks[]" value="<?= htmlspecialchars($event['code']) ?>">
                                    <input type="hidden" name="events[<?= htmlspecialchars($event['code']) ?>][is_existing]" value="<?= $is_existing ? '1' : '0' ?>">
                                    <input type="hidden" name="events[<?= htmlspecialchars($event['code']) ?>][event_id]" value="<?= $is_existing ? htmlspecialchars($event['event_id']) : '' ?>">
                                </td>
                                <td>
                                    <input type="text" name="events[<?= htmlspecialchars($event['code']) ?>][keyword]" placeholder="키워드 입력" 
                                    class="input-text-bold"
                                    value="<?= htmlspecialchars($event['keyword_group_name']) ?>"
                                    data-original="<?= htmlspecialchars($event['keyword_group_name']) ?>" 
                                    oninput="checkForChanges(this)" autocomplete="off">
                                </td>
                                <td>
                                    <input type="text" name="events[<?= htmlspecialchars($event['code']) ?>][theme]" placeholder="테마 입력" 
                                    value="<?= htmlspecialchars($event['theme']) ?>"
                                    data-original="<?= htmlspecialchars($event['theme']) ?>" 
                                    oninput="checkForChanges(this)" autocomplete="off">
                                </td>
                                <td class="checkbox-cell">
                                    <label>
                                        <input type="checkbox" name="events[<?= htmlspecialchars($event['code']) ?>][hot_theme]" data-original="<?= $event['hot_theme'] === 'Y' ? 'Y' : 'N' ?>" <?= $hotThemeChecked ?> oninput="checkForChanges(this)" >
                                        Hot
                                    </label>
                                </td>
                                <td>
                                    <input type="text" name="events[<?= htmlspecialchars($event['code']) ?>][code]" value="<?= htmlspecialchars($event['code']) ?>" style='width:70px;' readonly>
                                    <input type="hidden" name="events[<?= htmlspecialchars($event['code']) ?>][name]" value="<?= htmlspecialchars($event['name']) ?>">
                                </td>
                                <td class="<?= $stockNameClass; ?>"><?= htmlspecialchars($event['name']) ?></td>
                                <td class="checkbox-cell">
                                    <label>
                                        <input type="checkbox" name="events[<?= htmlspecialchars($event['code']) ?>][is_leader]" data-original="<?= $event['is_leader'] === '1' ? '1' : '0' ?>" <?= $isLeaderChecked ?> oninput="checkForChanges(this)" >
                                        ldr
                                    </label>
                                </td>
                                <td class="checkbox-cell">
                                    <label>
                                        <input type="checkbox" name="events[<?= htmlspecialchars($event['code']) ?>][is_watchlist]" data-original="<?= $event['is_watchlist'] === '1' ? '1' : '0' ?>" <?= $isWatchlistChecked ?> oninput="checkForChanges(this)" >
                                        W.L
                                    </label>
                                </td>
                                <td class="<?= $closeRateClass; ?>"><?= number_format($event['close_rate'], 2) ?> %</td>
                                <td class="<?= $amountClass; ?>"><?= number_format($event['trade_amount']) ?> 억</td>
                                <td>
                                    <input type="text" name="events[<?= htmlspecialchars($event['code']) ?>][comment]" placeholder="종목 코멘트 입력" 
                                    onfocus="fetchStockComment(this)" 
                                    value="<?= htmlspecialchars($event['stock_comment']) ?>" 
                                    data-original="<?= htmlspecialchars($event['stock_comment']) ?>" 
                                    oninput="checkForChanges(this)" autocomplete="off">
                                </td>
                                <td><?= htmlspecialchars($event['date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
</div>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>

<script>
// JavaScript 관련 기능 추가
function EventRegisterByStock_Initialize() {
    console.log("이슈 등록 초기화");

    $('input[name*="[keyword]"]').on('input', function() {
        let $this = $(this);
        let query = $this.val();

        if (query.length > 0) {
            $.ajax({
                url: 'fetch_autocomplete.php',
                type: 'GET',
                data: { type: 'keywords', q: query },
                success: function(data) {
                    let keywordGroups = JSON.parse(data);
                    $this.autocomplete({
                        source: keywordGroups
                    });
                }
            });
        }
    });

    window.fetchStockComment = function(input) {
        const stockRow = input.closest('.event_register_stock_item').querySelector('input[name^="events"][name$="[code]"]');
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
                        minLength: 0
                    }).autocomplete("search", "");
                }
            });
        }
    };
}

function checkForChanges(input) {
    const row = input.closest('tr');
    const checkbox = row.querySelector('input[type="checkbox"]');

    const keywordInput = row.querySelector('input[name*="[keyword]"]');
    const themeInput = row.querySelector('input[name*="[theme]"]');
    const commentInput = row.querySelector('input[name*="[comment]"]');

    const leaderCheckbox = row.querySelector('input[name*="[is_leader]"]');
    const watchlistCheckbox = row.querySelector('input[name*="[is_watchlist]"]');
    const hotThemeCheckbox = row.querySelector('input[name*="[hot_theme]"]');

    // 입력 필드의 변경 여부 확인
    const keywordChanged = keywordInput.getAttribute('data-original') !== keywordInput.value;
    const themeChanged = themeInput.getAttribute('data-original') !== themeInput.value;
    const commentChanged = commentInput.getAttribute('data-original') !== commentInput.value;

    // 체크박스의 변경 여부 확인
    const leaderChanged = leaderCheckbox.getAttribute('data-original') !== (leaderCheckbox.checked ? '1' : '0');
    const watchlistChanged = watchlistCheckbox.getAttribute('data-original') !== (watchlistCheckbox.checked ? '1' : '0');
    const hotThemeChanged = hotThemeCheckbox.getAttribute('data-original') !== (hotThemeCheckbox.checked ? 'Y' : 'N');

    // 변경된 것이 하나라도 있으면 체크박스를 선택 상태로
    if (keywordChanged || themeChanged || commentChanged || leaderChanged || watchlistChanged || hotThemeChanged) {
        checkbox.checked = true;
    } else {
        checkbox.checked = false;
    }
}

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
            const stockCode = stockCheckbox.value;
            const keywordInput = document.querySelector(`input[name="events[${stockCode}][keyword]"]`);

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
            return;
        }

        // 사용자에게 확인 메시지
        if (confirm("선택한 종목을 저장하시겠습니까?")) {
            const saveForm = document.getElementById('saveForm');
            if (saveForm) {
                saveForm.submit(); // 폼 제출
            } else {
                console.error("폼을 찾을 수 없습니다.");
            }
        }
    }


// 엑셀 데이터 처리 후 종목 저장 버튼을 활성화하는 로직
if ("<?= $dataSource ?>" === "excel") {
    document.getElementById("saveButton").disabled = false;
}
</script>

</body>
</html>

