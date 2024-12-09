<?php
$pageTitle = "섹터 그룹별 월간 거래 종목 통계 조회";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

// 조회할 년도와 오늘 날짜로부터 현재 월을 가져옵니다.
$reportYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$currentMonth = date('Y-m'); // 이번 달 (예: "2024-10")
$searchDay = date('d'); // 오늘 날짜
$searchStock = isset($_GET['searchStock']) ? $_GET['searchStock'] : '';

// 해당 연도의 시작과 종료일 계산
$startDate = $reportYear . '-01-01';
$endDate = $reportYear . '-12-31';

// 종목별 등장 횟수를 추적할 배열
$appearedStocks = []; 

// 이번 달의 회수 조건 설정
$occurrenceConditionForCurrentMonth = 10;
if ($searchDay <= 5) {
    $occurrenceConditionForCurrentMonth = 2;
} elseif ($searchDay <= 15) {
    $occurrenceConditionForCurrentMonth = 5;
} elseif ($searchDay <= 20) {
    $occurrenceConditionForCurrentMonth = 7;
}

// 지난 달까지의 데이터를 조회하는 쿼리 (10회 이상 발생한 종목)
$previousMonthsQuery = "
    SELECT 
        DATE_FORMAT(ks.date, '%Y-%m') AS month,  
        ks.code, 
        ks.name, 
        COUNT(DISTINCT ks.date) AS occurrence_days,
        CASE 
            WHEN ss.sector IS NULL OR ss.sector = '' THEN '기타' 
            ELSE ss.sector 
        END AS sector,
        CASE 
            WHEN ss.sector_group IS NULL OR ss.sector_group = '' THEN '기타' 
            ELSE ss.sector_group 
        END AS sector_group
    FROM 
        xraytick_summary ks
    LEFT JOIN stock_sector ss ON ks.code = ss.code
    WHERE 
        ks.tot_amt >= 1000000000
        AND ks.date BETWEEN ? AND ?
        AND DATE_FORMAT(ks.date, '%Y-%m') != ?
        AND ks.name LIKE CONCAT('%', ?, '%')
    GROUP BY 
        ks.code, ks.name, DATE_FORMAT(ks.date, '%Y-%m'), ss.sector, ss.sector_group
    HAVING 
        COUNT(DISTINCT ks.date) >= 10
    ORDER BY 
        month ASC, sector_group, sector, occurrence_days DESC
";

// 이번 달의 데이터를 조회하는 쿼리 (유연한 조건 적용)
$currentMonthQuery = "
    SELECT 
        DATE_FORMAT(ks.date, '%Y-%m') AS month,  
        ks.code, 
        ks.name, 
        COUNT(DISTINCT ks.date) AS occurrence_days,
        CASE 
            WHEN ss.sector IS NULL OR ss.sector = '' THEN '기타' 
            ELSE ss.sector 
        END AS sector,
        CASE 
            WHEN ss.sector_group IS NULL OR ss.sector_group = '' THEN '기타' 
            ELSE ss.sector_group 
        END AS sector_group
    FROM 
        xraytick_summary ks
    LEFT JOIN stock_sector ss ON ks.code = ss.code
    WHERE 
        ks.tot_amt >= 1000000000
        AND ks.date BETWEEN ? AND ?
        AND DATE_FORMAT(ks.date, '%Y-%m') = ?
        AND ks.name LIKE CONCAT('%', ?, '%')
    GROUP BY 
        ks.code, ks.name, DATE_FORMAT(ks.date, '%Y-%m'), ss.sector, ss.sector_group
    HAVING 
        COUNT(DISTINCT ks.date) >= ?
    ORDER BY 
        month ASC, sector_group, sector, occurrence_days DESC
";

// 지난 달까지의 데이터 실행
$eventStmt1 = $mysqli->prepare($previousMonthsQuery);
$eventStmt1->bind_param('ssss', $startDate, $endDate, $currentMonth, $searchStock);
$eventStmt1->execute();
$previousMonthsResult = $eventStmt1->get_result();

// 이번 달의 데이터 실행
$eventStmt2 = $mysqli->prepare($currentMonthQuery);
$eventStmt2->bind_param('ssssi', $startDate, $endDate, $currentMonth, $searchStock, $occurrenceConditionForCurrentMonth);
$eventStmt2->execute();
$currentMonthResult = $eventStmt2->get_result();

// 두 결과를 합침
$sectorGroupData = [];
$sectorGroupsList = [];

// 지난 달까지의 데이터를 배열에 저장
while ($row = $previousMonthsResult->fetch_assoc()) {
    if (!in_array($row['sector_group'], $sectorGroupsList)) {
        $sectorGroupsList[] = $row['sector_group'];
    }

    // 종목별 등장 횟수를 계산
    if (!isset($appearedStocks[$row['code']])) {
        $appearedStocks[$row['code']] = 1;
    } else {
        $appearedStocks[$row['code']]++;
    }

    // 각 월별, 섹터 그룹별 데이터를 저장
    $sectorGroupData[$row['month']][$row['sector_group']][] = [
        'sector' => $row['sector'],
        'occurrence_days' => $row['occurrence_days'],
        'stockName' => $row['name'],
        'stockCode' => $row['code'],
        'appearance_count' => $appearedStocks[$row['code']]
    ];
}

// 이번 달 데이터를 배열에 저장
while ($row = $currentMonthResult->fetch_assoc()) {
    if (!in_array($row['sector_group'], $sectorGroupsList)) {
        $sectorGroupsList[] = $row['sector_group'];
    }

    // 종목별 등장 횟수를 계산
    if (!isset($appearedStocks[$row['code']])) {
        $appearedStocks[$row['code']] = 1;
    } else {
        $appearedStocks[$row['code']]++;
    }

    // 각 월별, 섹터 그룹별 데이터를 저장
    $sectorGroupData[$row['month']][$row['sector_group']][] = [
        'sector' => $row['sector'],
        'occurrence_days' => $row['occurrence_days'],
        'stockName' => $row['name'],
        'stockCode' => $row['code'],
        'appearance_count' => $appearedStocks[$row['code']]
    ];
}
?>


<body>
<div id="container">
    <div id="content-area">
        <div class="filters">
            <form method="GET" action="xraytick_monthly.php" id="searchForm" style="display: flex; align-items: center;">
                <!-- 조회 연도 -->
                <label for="queryYear">조회 연도:</label>
                <input type="number" id="queryYear" name="year" value="<?php echo $reportYear; ?>" />

                <!-- 종목명 검색 입력창 -->
                <label for="searchStock">종목명 검색:</label>
                <input type="text" id="searchStock" name="searchStock" placeholder="종목명 입력" value="<?php echo $searchStock; ?>" style="width: 200px;">

                <!-- 조회 버튼은 숨김 -->
                <button type="submit" style="display: none;">조회</button>
            </form>
        </div>

        <div class="table-container">
            <!-- 테이블로 조회 결과를 출력 -->
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>월</th>
                        <?php foreach ($sectorGroupsList as $sector_group) { ?>
                            <!-- 섹터 그룹 클릭 시 수정 팝업 -->
                            <th>
                                <a href="javascript:void(0);" class="sector-group-header" data-sector-group="<?php echo $sector_group; ?>">
                                    <?php echo $sector_group; ?>
                                </a>
                            </th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectorGroupData as $month => $sectorGroups) { ?>
                        <tr>
                            <td><?php echo $month; ?></td>  <!-- 월별 데이터가 출력되는 부분 -->
                            <?php foreach ($sectorGroupsList as $sector_group) { ?>
                                <td data-sector-group="<?php echo $sector_group; ?>"> <!-- 여기에 data-sector-group 추가 -->
                                    <?php if (isset($sectorGroups[$sector_group])) { 
                                        $currentSector = '';
                                        foreach ($sectorGroups[$sector_group] as $sectorData) {
                                            // 섹터를 처음 출력할 때만 표시
                                            if ($currentSector !== $sectorData['sector']) {
                                                echo "<strong class='sector-title'>" . $sectorData['sector'] . "</strong><br>";
                                                $currentSector = $sectorData['sector'];
                                            }

                                            // 종목에 등장 순서 추가
                                            $appearanceCount = $sectorData['appearance_count'];

                                            // 종목이 처음 나왔는지 확인 (첫 번째 등장만 강조)
                                            $isFirstAppearance = ($appearanceCount === 1);
                                            ?>
                                            <strong>(<?php echo $sectorData['occurrence_days']; ?>회)</strong>
                                            <a href="javascript:void(0);" class="no-underline <?php echo $isFirstAppearance ? 'highlight' : ''; ?>" 
                                            onclick="Common_OpenStockPopup('<?php echo $sectorData['stockCode']; ?>', '<?php echo $sectorData['stockName']; ?>')">
                                                <?php echo $sectorData['stockName'] . "-" . $appearanceCount; ?>
                                            </a><br>
                                            <?php 
                                        } ?>
                                    <?php } else { ?>
                                        -  <!-- 데이터가 없을 때 표시 -->
                                    <?php } ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>

            </table>
        </div>
    </div>
</div>
<<br>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const queryYearInput = document.getElementById('queryYear');
    const searchStockInput = document.getElementById('searchStock');
    const searchForm = document.getElementById('searchForm');

    // 조회 연도 또는 조회 월이 변경될 때 자동으로 폼 전송
    queryYearInput.addEventListener('change', function() {
        searchForm.submit();
    });

    // 종목명 입력 후 엔터를 누르면 자동으로 조회
    searchStockInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault(); // 기본 동작 막기
            searchForm.submit();    // 폼 전송
        }
    });

    // 섹터 그룹 헤더 클릭 시 팝업 열기
    document.querySelectorAll('.sector-group-header').forEach(header => {
        header.addEventListener('click', function() {
            const sectorGroup = this.dataset.sectorGroup;
            const currentYear = document.getElementById('queryYear').value || new Date().getFullYear();
            const popupUrl = `sector_edit_popup.php?sector_group=${encodeURIComponent(sectorGroup)}&year=${currentYear}`;
            const popupOptions = "width=600,height=1400,scrollbars=yes";
            window.open(popupUrl, 'Sector Group Edit', popupOptions);
        });
    });
});
</script>

<style>
    .filters {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 10px;
        margin-bottom: 20px;
    }

    .filters label {
        white-space: nowrap;
        margin-right: 5px;
    }

    .filters input[type="number"], 
    .filters input[type="text"] {
        width: 150px;
    }

    .filters input[type="text"] {
        width: 200px;
    }

    .stock-table {
        width: 100%;
        border-collapse: collapse;
    }

    .stock-table th, .stock-table td {
        border: 1px solid #ddd;
        padding: 12px;
        text-align: left;
        vertical-align: top;
        word-wrap: break-word;
    }

    .stock-table th {
        background-color: #f2f2f2;
        position: sticky;
        top: 0;
        z-index: 1;
        text-align: center;
        font-size: 14px;
        font-weight: bold;
    }

    .stock-table td {
        padding: 12px;
        font-size: 13px;
        line-height: 1.5;
    }

    .stock-table td a {
        margin-right: 5px;
    }

    .stock-table tr {
        border-bottom: 1px solid #ddd;
    }

    .stock-table td:hover {
        background-color: #f0f0f0;
    }

    /* 각 섹터 그룹별로 배경색을 다르게 설정 */
    td[data-sector-group="바이오"] {
        background-color: #e6f7ff;
    }

    td[data-sector-group="반도체"] {
        background-color: #f0f5ff;
    }

    td[data-sector-group="2차전지/자동차"] {
        background-color: #fffbe6;
    }

    /* 처음 나온 종목 강조 */
    .highlight {
        color: red;
        font-weight: bold;
    }

    /* 섹터명 표시 스타일 */
    .sector-title {
        font-weight: bold;
        font-size: 14px;
        color: black;
        margin-bottom: 5px;
    }

    /* 섹터 그룹 클릭 스타일 */
    .sector-group-header {
        cursor: pointer;
        font-weight: bold;
        color: blue;
    }

    .sector-group-header:hover {
        text-decoration: underline;
    }
</style>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
</body>
</html>
