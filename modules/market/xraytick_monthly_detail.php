<?php
// 12일 중 7일, 3억 이상 매수종목 찾기

$pageTitle = "섹터별 연속 매수 종목 조회";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php"); // 공통 헤더 사용

// 조회할 년-월과 종목명을 받아옵니다.
$reportMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$searchStock = isset($_GET['searchStock']) ? $_GET['searchStock'] : '';

// 해당 월의 첫날과 전월의 마지막 10일을 계산
$startDate =  $reportMonth . '-01';
$previousMonthStartDate = date('Y-m-d', strtotime('-12 days', strtotime($startDate)));

// 해당 월이 현재 달인지 여부를 확인
$currentMonth = date('Y-m');
if ($reportMonth === $currentMonth) {
    $endDate = date('Y-m-d'); // 현재 월인 경우 오늘 날짜를 종료일로 사용
} else {
    $endDate = date('Y-m-t', strtotime($startDate)); // 과거 월은 마지막 날
}

// 종목명 추적을 위한 배열 (전월 데이터 포함)
$appearedStocks = [];

// calendar 테이블에서 해당 월과 전월 마지막 10일을 포함한 일자 목록을 가져옵니다
$calendarQuery = "
    SELECT date 
    FROM calendar 
    WHERE date BETWEEN ? AND ?
    ORDER BY date ASC
";
$calendarStmt = $mysqli->prepare($calendarQuery);
$calendarStmt->bind_param('ss', $previousMonthStartDate, $endDate);
$calendarStmt->execute();
$calendarResult = $calendarStmt->get_result();


// 일자별 종목 데이터를 담을 배열
$sectorDataByDate = [];
$sectorCounts = [];  // 섹터별 종목 수를 저장할 배열

while ($calendarRow = $calendarResult->fetch_assoc()) {
    $currentDate = $calendarRow['date'];

    // 각 일자의 과거 10일간 종목 데이터를 가져오는 쿼리 (종목명 검색 조건 추가)
    $eventQuery = "
        SELECT 
            A.code, 
            A.name, 
            A.occurrence_days,
            IFNULL(ss.sector, '기타') AS sector
        FROM (
            SELECT 
                ks.code, 
                ks.name,
                COUNT(DISTINCT ks.date) AS occurrence_days
            FROM 
                xraytick_summary ks
            JOIN (
                SELECT date
                FROM calendar
                WHERE date <= ?
                ORDER BY date DESC
                LIMIT 15
            ) rd ON ks.date = rd.date
            WHERE ks.tot_amt >= 1000000000
            GROUP BY 
                ks.code, ks.name
            HAVING 
                COUNT(DISTINCT ks.date) >= 10
        ) A
        LEFT JOIN stock_sector ss ON A.code = ss.code
    ";

    // 종목명 검색 조건이 있을 경우 추가
    if (!empty($searchStock)) {
        $eventQuery .= " WHERE A.name LIKE CONCAT('%', ?, '%')";
    }

    $eventStmt = $mysqli->prepare($eventQuery);
    
    // 종목명 검색 여부에 따라 바인딩 파라미터 설정
    if (!empty($searchStock)) {
        $eventStmt->bind_param('ss', $currentDate, $searchStock);
    } else {
        $eventStmt->bind_param('s', $currentDate);
    }
    $eventStmt->execute();
    $eventResult = $eventStmt->get_result();

    // 해당 일자의 종목 데이터를 배열에 저장
    while ($row = $eventResult->fetch_assoc()) {
        $tradeDate = $currentDate;
        $sector = $row['sector'];
        $occurrence_days = $row['occurrence_days'];
        $stockName = $row['name'];
        $stockCode = $row['code'];

        if (!isset($sectorDataByDate[$tradeDate])) {
            $sectorDataByDate[$tradeDate] = [];
        }
        if (!isset($sectorDataByDate[$tradeDate][$sector])) {
            $sectorDataByDate[$tradeDate][$sector] = [];
        }
        if (!isset($sectorDataByDate[$tradeDate][$sector][$occurrence_days])) {
            $sectorDataByDate[$tradeDate][$sector][$occurrence_days] = [];
        }

        // 처음 등장하는 종목에만 스타일 적용 (링크로 변경)
        if (!isset($appearedStocks[$stockName])) {
            $sectorDataByDate[$tradeDate][$sector][$occurrence_days][] = 
                '<a href="javascript:void(0);" class="no-underline" onclick="Common_OpenStockPopup(\'' . $stockCode . '\', \'' . $stockName . '\')">
                    <strong style="color: black;">' . $stockName . '(!)</strong>
                </a>';
            $appearedStocks[$stockName] = true;
        } else {
            $sectorDataByDate[$tradeDate][$sector][$occurrence_days][] = 
                '<a href="javascript:void(0);" class="no-underline" onclick="Common_OpenStockPopup(\'' . $stockCode . '\', \'' . $stockName . '\')">' . $stockName . '</a>';
        }

        // 섹터별 종목 수 계산
        if (!isset($sectorCounts[$sector])) {
            $sectorCounts[$sector] = 0;
        }
        $sectorCounts[$sector]++;
    }
}

// 섹터를 종목 수 기준으로 내림차순 정렬
uasort($sectorCounts, function($a, $b) {
    return $b - $a;
});

$sectors = array_keys($sectorCounts);  // 종목 수에 따라 정렬된 섹터 목록을 추출

// 발생 일수를 기준으로 섹터 내부에서 종목들을 내림차순 정렬
foreach ($sectorDataByDate as $date => &$sectorsData) {
    foreach ($sectorsData as &$occurrences) {
        // 발생 일수를 기준으로 내림차순 정렬
        krsort($occurrences);
    }
}
?>

<body>
<div id="container">
    <div id="content-area">
        <div class="filters">
            <form method="GET" action="xraytick_monthly_detail.php" id="searchForm" style="display: flex; align-items: center;">
                <!-- 조회 월 -->
                <label for="queryMonth">조회 월:</label>
                <input type="month" id="queryMonth" name="month" value="<?php echo $reportMonth; ?>" />

                <!-- 이전, 다음 버튼 -->
                <button type="button" id="prevMonth" class="btn"><< 이전</button>
                <button type="button" id="nextMonth" class="btn">다음 >></button>

                <!-- 종목명 검색 입력창 -->
                <label for="searchStock">종목명 검색:</label>
                <input type="text" id="searchStock" name="searchStock" placeholder="종목명 입력" value="<?php echo $searchStock; ?>">

                <!-- 조회 버튼 (숨김 처리하여 Enter로 자동 제출될 수 있도록 설정) -->
                <button type="submit" style="display: none;">조회</button>
            </form>
        </div>

        <div class="table-container">
            <!-- 테이블로 조회 결과를 출력 -->
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>일자</th>
                        <th>요일</th>
                        <?php foreach ($sectors as $sector) { ?>
                            <th class="sector-header" data-sector="<?php echo $sector; ?>">
                                <?php echo $sector; ?>
                            </th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($sectorDataByDate as $tradeDate => $sectorsData) {
                        // 화면에 해당 월 1일부터의 데이터만 표시
                        if (strtotime($tradeDate) >= strtotime($startDate)) { ?>
                            <tr>
                                <td width=55><?php echo date('m-d', strtotime($tradeDate)); ?></td>
                                <td><?php echo ['일', '월', '화', '수', '목', '금', '토'][date('w', strtotime($tradeDate))]; ?></td>
                                <?php foreach ($sectors as $sector) { ?>
                                    <td>
                                        <?php 
                                        if (isset($sectorsData[$sector])) {
                                            // 발생 일수 그룹별로 표시
                                            foreach ($sectorsData[$sector] as $occurrence_days => $stocks) {
                                                echo '<strong>(' . $occurrence_days . '회) </strong>' . implode(', ', $stocks) . '<br>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } 
                    } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const queryMonthInput = document.getElementById('queryMonth');
    const searchStockInput = document.getElementById('searchStock');
    const prevMonthButton = document.getElementById('prevMonth');
    const nextMonthButton = document.getElementById('nextMonth');
    const searchForm = document.getElementById('searchForm');

    // 조회 월이 변경될 때 자동으로 폼 전송
    queryMonthInput.addEventListener('change', function() {
        searchForm.submit();
    });

    // 이전 버튼 클릭 시, 한 달 전으로 변경하고 종목명 검색 값도 유지
    prevMonthButton.addEventListener('click', function() {
        let currentMonth = new Date(queryMonthInput.value + "-01");
        currentMonth.setMonth(currentMonth.getMonth() - 1);
        queryMonthInput.value = currentMonth.toISOString().slice(0, 7);
        searchForm.submit();
    });

    // 다음 버튼 클릭 시, 한 달 후로 변경하고 종목명 검색 값도 유지
    nextMonthButton.addEventListener('click', function() {
        let currentMonth = new Date(queryMonthInput.value + "-01");
        currentMonth.setMonth(currentMonth.getMonth() + 1);
        queryMonthInput.value = currentMonth.toISOString().slice(0, 7);
        searchForm.submit();
    });

    // 종목명 입력 후 엔터를 누르면 자동으로 조회
    searchStockInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault(); // 기본 동작 막기
            searchForm.submit();    // 폼 전송
        }
    });

    const sectorHeaders = document.querySelectorAll('.sector-header');

    sectorHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sector = this.dataset.sector;
            const currentMonth = document.getElementById('queryMonth').value || new Date().toISOString().slice(0, 7); // 월 값 가져오기

            // 팝업 창으로 섹터 수정 페이지를 열기
            const popupUrl = `sector_edit_popup.php?sector=${encodeURIComponent(sector)}&month=${currentMonth}`;
            const popupOptions = "width=600,height=1400,scrollbars=yes";
            window.open(popupUrl, 'Sector Edit', popupOptions);
        });
    });
});
</script>

<style>
    .filters {
        display: flex;
        align-items: center;
        justify-content: flex-start; /* 요소를 한 줄로 왼쪽에 정렬 */
        gap: 10px; /* 각 요소 간의 간격 */
        margin-bottom: 20px;
        flex-wrap: nowrap; /* 줄바꿈을 방지 */
    }

    .filters label {
        white-space: nowrap; /* 레이블이 줄바꿈되지 않도록 설정 */
        margin-right: 5px; /* 레이블 오른쪽 여백 */
    }

    .filters input[type="month"], 
    .filters input[type="text"] {
        flex-shrink: 0; /* 입력 요소가 너무 작아지지 않도록 설정 */
        width: 150px; /* 적절한 넓이 설정 */
    }

    .filters button {
        flex-shrink: 0; /* 버튼이 좁아지지 않도록 설정 */
        padding: 5px 10px; /* 버튼 내부 여백 설정 */
    }

    .filters input[type="text"] {
        width: 200px; /* 종목명 검색 입력창 넓이를 지정 */
    }

    .btn {
        background-color: #ff6666;
        color: white;
        border: none;
        cursor: pointer;
        font-size: 14px;
    }

    .btn:hover {
        background-color: #ff4d4d;
    }

    .filters .btn {
        margin-left: 5px; /* 버튼 사이에 여백 추가 */
    }

    .stock-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px; /* 최소 폭을 지정하여 좌우 스크롤 가능하게 */
    }

    .stock-table th, .stock-table td {
        border: 1px solid #ddd;
        padding: 8px;
    }

    .stock-table th {
        background-color: #f2f2f2;
        position: sticky; /* 테이블 헤더 고정 */
        top: 0;
        z-index: 1;
    }

    .sector-header {
        cursor: pointer;
        background-color: #e0e0e0;
    }

    .sector-header:hover {
        background-color: #d0d0d0;
    }

    #sector-edit {
        margin-top: 20px;
    }

    #sector-edit-table {
        width: 100%;
        border-collapse: collapse;
    }

    #sector-edit-table th, #sector-edit-table td {
        border: 1px solid #ddd;
        padding: 8px;
    }

    #save-sector-btn {
        margin-top: 10px;
        padding: 10px 20px;
        background-color: #4CAF50;
        color: white;
        border: none;
        cursor: pointer;
    }

    #save-sector-btn:hover {
        background-color: #45a049;
    }
</style>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
</body>
</html>
