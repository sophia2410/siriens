<?php
$pageTitle = "매매 등록 관리";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

// 사용자가 선택한 년도와 월 (기본적으로 현재 년월)
$selectedYearMonth = isset($_GET['yearMonth']) ? $_GET['yearMonth'] : date('Y-m');
$selectedYear = substr($selectedYearMonth, 0, 4);
$selectedMonth = substr($selectedYearMonth, 5, 2);

// 매수일자와 매도일자의 기본값 설정 (선택한 년월의 첫째 날과 마지막 날)
$defaultBuyDate = "$selectedYear-$selectedMonth-01";
$defaultSellDate = date('Y-m-t', strtotime($defaultBuyDate)); // 선택한 월의 마지막 날
?>

<head>
    <style>
        /* container 내부에 content-wrapper 추가 */
        #container {
            display: flex;
            height: 100vh;
            margin-left: 100px !important;
            flex-direction: column; /* column으로 변경하여 상하 레이아웃 구현 */
            width: calc(100% - 100px);
            padding: 20px;
            box-sizing: border-box;
            justify-content: flex-start; /* 상단에 고정 */
        }

        #content-wrapper {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        /* 폼 섹션 */
        #form-section form {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        #form-section input[type="text"],
        #form-section input[type="date"] {
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            font-size: 16px;
            width: 100%;
        }

        #form-section button {
            background-color: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        #form-section button:hover {
            background-color: #c0392b;
        }

        /* 달력 스타일 */
        #calendar-section table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        #calendar-section td {
            width: 14.28%;
            height: 100px;
            padding: 15px;
            text-align: center;
            vertical-align: top;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }

        #calendar-section td:hover {
            background-color: #f1c40f;
            cursor: pointer;
        }

        #calendar-section td strong {
            display: block;
            font-size: 18px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        #calendar-section td div {
            font-size: 14px;
            color: #7f8c8d;
            background-color: #ecf0f1;
            padding: 5px;
            border-radius: 4px;
        }

        /* 반응형 처리 */
        @media screen and (max-width: 768px) {
            #form-section form {
                flex-direction: column;
                gap: 15px;
            }

            #calendar-section td {
                height: 80px;
                padding: 10px;
            }
        }

    </style>
</head>
<body>
    <div id="container">
        <!-- 종목 매매 등록 폼 -->
        <div id="content-wrapper">
            <h2>종목 매매 등록</h2>
            <form id="tradeForm" method="post" action="trade_process.php">
                <div style="display: flex; flex-direction: row;">
                    <!-- 종목명 입력 필드 -->
                    <input type="text" name="stock_name" placeholder="종목명/코드" required style="flex: 2; margin-right: 10px;" class="input-border-bold input-background-light input-text-bold" onkeydown="Common_SearchStock(event, this, focusOnBuyDate)" autocomplete="off">
                    
                    <!-- 종목 코드 입력 필드 -->
                    <input type="text" name="stock_code" readonly placeholder="코드" style="flex: 1; margin-right: 10px;">
                    
                    <!-- 매수일자 입력 필드 (기본값: 선택한 월의 첫째 날) -->
                    <input type="date" name="buy_date" value="<?= $defaultBuyDate ?>" placeholder="매수일자" style="flex: 1; margin-right: 10px;">
                    
                    <!-- 매도일자 입력 필드 (기본값: 선택한 월의 마지막 날) -->
                    <input type="date" name="sell_date" value="<?= $defaultSellDate ?>" placeholder="매도일자" style="flex: 1; margin-right: 10px;">
                    
                    <button type="submit" style="flex: 1;">등록</button>
                </div>
            </form>
        </div>

        <!-- 월 선택 기능 -->
        <div id="content-wrapper">
            <h2>조회할 년월 선택</h2>
            <form method="GET" action="">
                <label for="yearMonth">년월:</label>
                <input type="month" id="yearMonth" name="yearMonth" value="<?= $selectedYearMonth ?>">
                <button type="submit">조회</button>
            </form>
        </div>

        <!-- 달력 조회 -->
        <div id="content-wrapper">
            <h2>달력 조회</h2>
            <div id="calendar">
            <?php
            // 선택된 년도와 월에 맞는 달력 생성
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
            $first_day_of_month = date('w', strtotime("$selectedYear-$selectedMonth-01")); // 첫 번째 날의 요일 (0 = 일요일, 1 = 월요일)

            // 요일 헤더 출력 (월, 화, 수, 목, 금)
            echo "<table style='width: 100%;'>";
            echo "<tr><th>월</th><th>화</th><th>수</th><th>목</th><th>금</th></tr>";
            echo "<tr>";

            // 첫 번째 주의 월요일까지 빈 셀 추가 (월요일=1, 화요일=2, ..., 일요일=0)
            if ($first_day_of_month > 1) {
                for ($i = 1; $i < $first_day_of_month; $i++) {
                    echo "<td style='vertical-align: top;'></td>"; // 첫 번째 요일 전까지 빈 셀 추가
                }
            }

            // 날짜 출력
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
                $day_of_week = date('w', strtotime($date)); // 해당 날짜의 요일 (0 = 일요일, 1 = 월요일)

                // 월요일(1) ~ 금요일(5)까지만 출력
                if ($day_of_week >= 1 && $day_of_week <= 5) {
                    echo "<td style='vertical-align: top;'>";
                    echo "<strong>$day</strong><br>";

                    // market_index 테이블에서 KOSPI, KOSDAQ 지수 가져오기
                    $kospi_query = "SELECT close, close_rate FROM market_index WHERE market_fg = 'KOSPI' AND date = '$date'";
                    $kosdaq_query = "SELECT close, close_rate FROM market_index WHERE market_fg = 'KOSDAQ' AND date = '$date'";
                    $kospi_result = $mysqli->query($kospi_query);
                    $kosdaq_result = $mysqli->query($kosdaq_query);

                    $kospi_data = $kospi_result->fetch_assoc();
                    $kosdaq_data = $kosdaq_result->fetch_assoc();

                    // 지수가 있을 때만 종목과 지수를 출력
                    if ($kospi_data || $kosdaq_data) {
                        // KOSPI 지수 출력 (등락률에 따른 색상 적용)
                        if ($kospi_data) {
                            $color = ($kospi_data['close_rate'] >= 0) ? 'red' : 'blue';
                            echo "<div style='display: flex; justify-content: space-between;'>
                                    <span>K O S P I</span>
                                    <span style='color: $color;'>{$kospi_data['close']} ({$kospi_data['close_rate']}%)</span>
                                </div>";
                        }

                        // KOSDAQ 지수 출력 (등락률에 따른 색상 적용)
                        if ($kosdaq_data) {
                            $color = ($kosdaq_data['close_rate'] >= 0) ? 'red' : 'blue';
                            echo "<div style='display: flex; justify-content: space-between;'>
                                    <span>KOSDAQ</span>
                                    <span style='color: $color;'>{$kosdaq_data['close']} ({$kosdaq_data['close_rate']}%)</span>
                                </div><br>";
                        }

                        // 등록된 종목 출력
                        $sql = "SELECT * FROM stock_trades WHERE '$date' BETWEEN buy_date AND sell_date";
                        $result = $mysqli->query($sql);
                        while ($row = $result->fetch_assoc()) {
                            echo "{$row['stock_name']}<br>";
                        }
                    } else {
                        // 지수가 없는 경우에는 아무것도 출력하지 않음 (종목도 보이지 않게)
                        echo "휴장일<br>";
                    }

                    echo "</td>";

                    // 금요일(5)에서 행을 종료하고 새로운 행 시작
                    if ($day_of_week == 5) {
                        echo "</tr><tr>";
                    }
                }
            }
            echo "</tr>";
            echo "</table>";
            ?>
            </div>
        </div>
    </div>


    <?php require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"); ?>

    <script>
        // 매수일자 필드로 포커스 이동하는 콜백 함수
        function focusOnBuyDate() {
            const buyDateInput = document.querySelector("input[name='buy_date']");
            if (buyDateInput) {
                buyDateInput.focus(); // 매수일자 필드로 포커스 이동
            }
        }
    </script>
</body>
</html>
