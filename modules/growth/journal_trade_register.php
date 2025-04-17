<?php
$pageTitle = "매매/복기 등록 관리";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/tinymce_module.php");


// 1) 거래대금을 '조' 단위로 표시 (이미 있는 함수)
function formatAmountToJo($amount) {
    return ($amount >= 1.0e12) ? number_format($amount / 1.0e12, 2) : number_format($amount);
}

// 1-1) 거래대금 막대그래프 표현 (최대값 20조 기준)
function getAmountBarColor($amount) {
    if ($amount >= 10.0e12) {
        return 'background: linear-gradient(to right, #ff3333, #ff9999);'; // 빨강
    } elseif ($amount >= 8.0e12) {
        return 'background: linear-gradient(to right, #ff9900, #ffcc66);'; // 주황
    } elseif ($amount >= 6.0e12) {
        return 'background: linear-gradient(to right, #0099ff, #66ccff);'; // 파랑
    } else {
        return 'background: linear-gradient(to right, #555555, #aaaaaa);'; // 어두운 회색 → 밝은 회색
    }
}

function renderAmountBar($amount) {
    $maxAmount = 15.0e12; // 최대 20조 기준
    $percentage = ($amount / $maxAmount) * 100; // 백분율 계산
    $barWidth = max(3, $percentage); // 최소 3px 보장
    $barColor = getAmountBarColor($amount); // 색상 동적 선택
    $formattedAmount = formatAmountToJo($amount); // '조' 단위 변환

    // 그래프 너비가 20% 이하이면 텍스트를 막대 바깥에 표시
    $textClass = ($percentage > 20) ? 'amount-text-inside' : 'amount-text-outside';

    return "
        <div class='amount-bar-container'>
            <div class='amount-bar' style='width: {$barWidth}%; {$barColor}'>
                <span class='{$textClass}' style='color: #fff;'>{$formattedAmount}</span>
            </div>
        </div>
    ";
}

// 2) 조회할 연월 결정
$searchMonth = isset($_GET['search_month']) ? $_GET['search_month'] : date('Y-m');
list($searchYear, $searchMon) = explode('-', $searchMonth);

// 해당 월의 시작일/마지막일
$monthStartDate = new DateTime("$searchYear-$searchMon-01");
$monthEndDate   = new DateTime("$searchYear-$searchMon-01");
$monthEndDate->modify('last day of this month');

// 이전 달 / 다음 달 계산
$prevMonthObj = clone $monthStartDate;
$prevMonthObj->modify('-1 month');
$prevSearchMonth = $prevMonthObj->format('Y-m');

$nextMonthObj = clone $monthStartDate;
$nextMonthObj->modify('+1 month');
$nextSearchMonth = $nextMonthObj->format('Y-m');

// 3) market_index 조회
$marketIndexQuery = "
    SELECT `date` AS trade_date, market_fg, close, close_rate, amount
    FROM market_index
    WHERE `date` BETWEEN '{$monthStartDate->format('Y-m-d')}' AND '{$monthEndDate->format('Y-m-d')}'
";
$marketIndexResult = $mysqli->query($marketIndexQuery);
$marketData = [];
while ($row = $marketIndexResult->fetch_assoc()) {
    $marketData[$row['trade_date']][$row['market_fg']] = $row;
}

// 4) journal_trade 조회 (매매방식/수익손실 표시용)
$journalTradeQuery = "
    SELECT trade_date, trade_method, profit_loss
    FROM journal_trade
    WHERE trade_date BETWEEN '{$monthStartDate->format('Y-m-d')}' AND '{$monthEndDate->format('Y-m-d')}'
";
$journalTradeResult = $mysqli->query($journalTradeQuery);
$journalTradeData = [];
while ($row = $journalTradeResult->fetch_assoc()) {
    $journalTradeData[$row['trade_date']][] = $row;
}

// 5) 달력용 데이터 (월~금 평일만)
$rows = [];
$currentRow = ['', '', '', '', ''];
$dayObj = clone $monthStartDate;
while ($dayObj <= $monthEndDate) {
    $dow = (int)$dayObj->format('N'); // 1=월 ~ 7=일
    if ($dow <= 5) {
        if ($dow === 1 && !empty(array_filter($currentRow))) {
            $rows[] = $currentRow;
            $currentRow = ['', '', '', '', ''];
        }
        $currentRow[$dow - 1] = $dayObj->format('Y-m-d');
    }
    $dayObj->modify('+1 day');
}
if (!empty(array_filter($currentRow))) {
    $rows[] = $currentRow;
}
while (count($rows) < 5) {
    $rows[] = ['', '', '', '', ''];
}

// trade_date 값이 있으면 사용, 없으면 기본 로직 실행
$tradeDate = isset($_GET['trade_date']) ? $_GET['trade_date'] : null;

// 현재 연월 확인
$currentYearMonth = date('Y-m');

// trade_date 값이 없는 경우 기본 로직 적용
if (!$tradeDate) {
    if ($searchMonth === $currentYearMonth) {
        $tradeDate = date('Y-m-d'); // 이번 달이면 오늘 날짜
    } else {
        // 조회 월의 첫 번째 평일(월~금) 찾기
        $dayObj = clone $monthStartDate;
        while ($dayObj <= $monthEndDate) {
            $dow = (int)$dayObj->format('N'); // 1=월 ~ 7=일
            if ($dow <= 5) { // 월~금이면 설정
                $tradeDate = $dayObj->format('Y-m-d');
                break;
            }
            $dayObj->modify('+1 day');
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        /* 전체 컨테이너 (좌우 1:1 분할) */
        #container {
            display: flex;
            gap: 10px;
            padding: 20px;
            align-items: stretch; /* 자식들을 동일 높이로 */
        }
        /* 좌측: 조회조건 + 달력 */
        #left_container {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        #right_container {
            flex: 1;
            position: relative;
            display: flex;          /* 추가 */
            flex-direction: column; /* 추가 */
        }

        /* iframe을 부모 높이에 맞춤 */
        iframe#editor_frame {
            flex: 1;        /* 추가: 남은 공간을 전부 사용 */
            border: none;
        }
        /* 조회조건 영역 */
        #search_container {
            background: #fff;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        #search_container form {
            display: inline-block; /* 버튼 폼들도 가로로 이어 붙이기 위해 inline-block */
            margin-right: 5px;
        }

        /* 이전/다음 버튼 스타일 */
        .month-nav-btn {
            background: #ff6e6e;
            color: #fff;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        /* 달력 영역 */
        #calendar_container {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }
        .calendar-table th,
        .calendar-table td {
            border: 1px solid #ccc;
            width: 20%;
            vertical-align: top;
        }
        .calendar-table th {
            background: #eee;
            height: 30px;   /* 헤더 높이 낮춤 */
            padding: 4px;
        }
        .calendar-table td {
            height: 150px;  /* 본문 셀 높이 증가 */
            padding: 8px;
        }
        .calendar-cell {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            height: 100%;
            padding: 5px;
        }
        .calendar-cell a {
            font-weight: bold;
            text-decoration: none;
            color: #333;
            margin-bottom: 5px;
        }
        /* 시장 정보: KOSPI와 KOSDAQ 3줄 (각 줄 2컬럼 고정) */
        .market-index {
            width: 100%;
            margin-top: 5px;
            font-size: 0.9em;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .market-row {
            display: flex; /* flex 적용 */
            align-items: center; /* 세로 중앙 정렬 */
            justify-content: space-between; /* 좌우 정렬 */
        }
        .market-row div {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .market-col {
            flex: 1;
            padding: 2px;
        }
        .market-name {
            font-weight: bold;
            color: #555;
        }
        .market-price.positive { color: red; }
        .market-price.negative { color: blue; }
        .trade-divider {
            width: 100%;
            height: 1px;
            background: #bbb;
            margin: 5px 0;
        }
        /* 매매방식 / 수익손실 (달력 셀 하단 추가) */
        .trade-info {
            width: 100%;
            font-size: 14px;
            margin-top: 5px;
            text-align: left;
            color: #333;
        }
        .trade-item {
            margin-bottom: 3px;
        }
        /* 팝업 보기 버튼 */
        #popup_button {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 8px 16px;
            border: none;
            background: #5bc0de;
            color: #fff;
            border-radius: 5px;
            cursor: pointer;
        }
        /* iframe 에디터 */
        iframe {
            width: 100%;
            border: none;
            /* 높이는 JS로 조절 */
        }

        /* 가로 막대 그래프 컨테이너 */
        .amount-bar-container {
            position: relative;
            width: 100%;
            height: 14px;
            background: repeating-linear-gradient(
                to right,
                #ddd 0%,
                #ddd 1%,
                transparent 1%,
                transparent 25%,
                #ddd 25%,
                #ddd 26%,
                transparent 26%,
                transparent 50%,
                #ddd 50%,
                #ddd 51%,
                transparent 51%,
                transparent 75%,
                #ddd 75%,
                #ddd 76%,
                transparent 76%,
                transparent 100%
            );
            border-radius: 7px;
            overflow: visible !important;
        }

        /* 거래대금 표시 바 */
        .amount-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease-in-out;
        }

    </style>
</head>
<body>
    <div id="container">
        <!-- (1) 좌측: 조회조건 + 달력 -->
        <div id="left_container">
            <div id="search_container">
                <form method="GET">
                    <input type="month" id="search_month" name="search_month" value="<?= htmlspecialchars($searchMonth) ?>">
                    <button type="submit" class="month-nav-btn">조회</button>
                </form>
                <!-- 이전달 버튼 -->
                <form method="GET">
                    <input type="hidden" name="search_month" value="<?= $prevSearchMonth ?>">
                    <button type="submit" class="month-nav-btn">&lt;&lt; 이전</button>
                </form>
                <!-- 다음달 버튼 -->
                <form method="GET">
                    <input type="hidden" name="search_month" value="<?= $nextSearchMonth ?>">
                    <button type="submit" class="month-nav-btn">다음 &gt;&gt;</button>
                </form>
            </div>
            <div id="calendar_container">
                <table class="calendar-table">
                    <thead>
                        <tr>
                            <th>월</th>
                            <th>화</th>
                            <th>수</th>
                            <th>목</th>
                            <th>금</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $weekRow): ?>
                        <tr>
                            <?php for ($col = 0; $col < 5; $col++):
                                $cellDate = $weekRow[$col];
                            ?>
                                <td>
                                    <?php if ($cellDate): ?>
                                    <div class="calendar-cell">
                                        <a href="javascript:loadEditor('<?= $cellDate ?>')"><?= $cellDate ?></a>
                                        <?php
                                        // (A) KOSPI / KOSDAQ 데이터
                                        $kospi  = isset($marketData[$cellDate]['KOSPI']) ? $marketData[$cellDate]['KOSPI'] : null;
                                        $kosdaq = isset($marketData[$cellDate]['KOSDAQ']) ? $marketData[$cellDate]['KOSDAQ'] : null;
                                        if ($kospi || $kosdaq):
                                            // KOSPI
                                            $kospiName   = $kospi ? "KOSPI" : "";
                                            $kospiClose  = $kospi ? number_format($kospi['close'], 2) : "";
                                            $kospiRate   = $kospi ? $kospi['close_rate'] : "";
                                            $rateVal     = $kospi ? floatval($kospi['close_rate']) : 0;
                                            $rateClass   = ($rateVal >= 0) ? "positive" : "negative";

                                            // KOSDAQ
                                            $kosdaqName  = $kosdaq ? "KOSDAQ" : "";
                                            $kosdaqClose = $kosdaq ? number_format($kosdaq['close'], 2) : "";
                                            $kosdaqRate  = $kosdaq ? $kosdaq['close_rate'] : "";
                                            $rateVal2    = $kosdaq ? floatval($kosdaq['close_rate']) : 0;
                                            $rateClass2  = ($rateVal2 >= 0) ? "positive" : "negative";
                                        ?>
                                        <div class="market-index">
                                            <!-- 첫 줄: 시장명 -->
                                            <div class="market-row">
                                                <div class="market-col "><span class="market-name"><?= $kospiName ?></span></div>
                                                <div class="market-col"><span class="market-name"><?= $kosdaqName ?></span></div>
                                            </div>
                                            <!-- 두 번째 줄: 지수 -->
                                            <div class="market-row">
                                                <div class="market-col">
                                                    <span class="market-price <?= $rateClass ?>">
                                                        <?= $kospiClose ?>
                                                    </span>
                                                </div>
                                                <div class="market-col">
                                                    <span class="market-price <?= $rateClass2 ?>">
                                                        <?= $kosdaqClose ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- 세 번째 줄: 등락률 -->
                                            <div class="market-row">
                                                <div class="market-col">
                                                    <span class="market-price <?= $rateClass ?>">
                                                        <?= $kospi ? "(".$kospiRate."%)" : "" ?>
                                                    </span>
                                                </div>
                                                <div class="market-col">
                                                    <span class="market-price <?= $rateClass2 ?>">
                                                        <?= $kosdaq ? "(".$kosdaqRate."%)" : "" ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- 네 번째 줄: 거래대금 → 그래프 대체 -->
                                            <div class="market-row">
                                                <div class="market-col"><?= $kospi ? renderAmountBar($kospi['amount']) : '' ?></div>
                                                <div class="market-col"><?= $kosdaq ? renderAmountBar($kosdaq['amount']) : '' ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <!-- (B) 매매방식 / 수익손실 -->
                                        <?php if (isset($journalTradeData[$cellDate])): ?>
                                            <div class="trade-divider"></div> <!-- 중간 바 추가 -->
                                            <div class="trade-info">
                                                <?php foreach ($journalTradeData[$cellDate] as $trade): ?>
                                                    <div class="trade-item">
                                                        <?= htmlspecialchars($trade['trade_method']) ?> / <?= htmlspecialchars($trade['profit_loss']) ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- (2) 우측: 등록폼 (iframe) -->
        <div id="right_container">
            <iframe id="editor_frame" src="journal_trade_popup.php?trade_date=<?= $tradeDate ?>&mode=iframe"></iframe>
        </div>

    <script>
        // 날짜 클릭 시, iframe에 해당 일자 로드
        function loadEditor(date) {
            document.getElementById('editor_frame').src = "journal_trade_popup.php?trade_date=" + encodeURIComponent(date) + "&mode=iframe";
            adjustIframeHeight();
        }
        // 달력 영역과 iframe 높이를 1:1 맞추기
        function adjustIframeHeight() {
            var calHeight = document.getElementById('calendar_container').offsetHeight;
            document.getElementById('editor_frame').style.height = calHeight + "px";
        }
        window.addEventListener('load', adjustIframeHeight);
        window.addEventListener('resize', adjustIframeHeight);
    </script>
</body>
</html>
