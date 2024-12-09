<?php
$pageTitle = "매매 등록 관리";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

// 사용자가 선택한 년도와 월 (기본적으로 현재 년월)
$selectedYearMonth = isset($_GET['yearMonth']) ? $_GET['yearMonth'] : date('Y-m');
$selectedYear = substr($selectedYearMonth, 0, 4);
$selectedMonth = substr($selectedYearMonth, 5, 2);

// 수정 모드일 경우, 수정할 종목의 데이터를 불러옴
$editMode = isset($_GET['id']);
if ($editMode) {
    $trade_id = $_GET['id'];
    $edit_query = "SELECT * FROM trade_stocks WHERE id = ?";
    $stmt = $mysqli->prepare($edit_query);
    $stmt->bind_param("i", $trade_id);
    $stmt->execute();
    $trade_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// 매수일자와 매도일자의 기본값 설정 (선택한 년월의 첫째 날과 마지막 날)
$defaultBuyDate = $editMode ? $trade_data['buy_date'] : "$selectedYear-$selectedMonth-01";
$defaultSellDate = $editMode ? $trade_data['sell_date'] : date('Y-m-t', strtotime($defaultBuyDate));

// 등록된 종목 가져오기
$trade_query = "SELECT * FROM trade_stocks WHERE DATE_FORMAT(buy_date, '%Y-%m') = '$selectedYearMonth' OR DATE_FORMAT(sell_date, '%Y-%m') = '$selectedYearMonth'";
$trade_result = $mysqli->query($trade_query);
?>

<head>
    <style>
        /* container 내부에 content-wrapper 추가 */
        #left-content, #right-content {
            padding: 10px;
        }
        
        #left-content {
            width: 40%;
        }

        #right-content {
            width: 60%;
        }

        #content-wrapper {
            width: 100%;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        /* 폼 섹션 */
        #form-section input,
        #form-section select,
        #form-section textarea,
        #form-section button {
            padding: 10px;
            margin-bottom: 10px;
        }

        #form-section select {
            width: 100%;
        }

        #form-section textarea {
            width: 100%;
            height: 40px; /* 코멘트 입력 필드의 높이를 줄여서 컴팩트하게 */
            resize: none;
        }

        #form-section button {
            background-color: #e74c3c;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
            padding: 10px 20px;
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
        <div id="left-content">
            <!-- 월 선택 기능 -->
            <div id="content-wrapper">
                <form method="GET" action="">
                    <input type="month" id="yearMonth" name="yearMonth" value="<?= $selectedYearMonth ?>">
                    <button type="submit">조회</button>
                </form>
            </div>

            <!-- 종목 매매 등록 폼 -->
            <div id="content-wrapper">
                <h2>종목 매매 등록</h2>
                <form id="tradeForm" method="post" action="trade_process.php">
                    <div style="display: flex; flex-direction: column;">
                        <!-- action 값을 hidden으로 전달하여 등록/수정 구분 -->
                        <input type="hidden" name="action" value="<?= $editMode ? 'update' : 'register' ?>">
                        
                        <!-- 수정 모드일 경우 id 값을 hidden으로 전달 -->
                        <?php if ($editMode): ?>
                            <input type="hidden" name="id" value="<?= $trade_id ?>">
                        <?php endif; ?>

                        <!-- 첫 번째 줄: 종목명, 코드, 매수일자, 매도일자 -->
                        <div style="display: flex; flex-direction: row; justify-content: space-between;">
                            <input type="text" name="stock_name" value="<?= $editMode ? htmlspecialchars($trade_data['name']) : '' ?>" placeholder="종목명/코드" required style="flex: 2; margin-bottom: 10px;" class="input-border-bold input-background-light input-text-bold" onkeydown="Common_SearchStock(event, this, focusOnBuyDate)" autocomplete="off">
                            <input type="text" name="stock_code" readonly value="<?= $editMode ? htmlspecialchars($trade_data['code']) : '' ?>" placeholder="코드" style="flex: 2; margin-bottom: 10px;">
                            <input type="date" name="buy_date" value="<?= $defaultBuyDate ?>" placeholder="매수일자" style="flex: 1;  margin-bottom: 10px; margin-right: 10px;">
                            <input type="date" name="sell_date" value="<?= $defaultSellDate ?>" placeholder="매도일자" style="flex: 1; margin-bottom: 10px;">
                            <select name="profit_loss" style="flex: 1;  margin-bottom: 10px; margin-right: 10px;">
                                <option value="수익" <?= $editMode && $trade_data['profit_loss'] === '수익' ? 'selected' : '' ?>>수익</option>
                                <option value="손실" <?= $editMode && $trade_data['profit_loss'] === '손실' ? 'selected' : '' ?>>손실</option>
                            </select>
                        </div>

                        <!-- 두 번째 줄: 수익/손실 구분, 코멘트, 등록 버튼 -->
                        <div style="display: flex; flex-direction: row; justify-content: space-between; margin-top: 10px;">
                            <textarea name="comment" placeholder="코멘트를 입력하세요" style="flex: 5; margin-right: 10px;"><?= $editMode ? htmlspecialchars($trade_data['comment']) : '' ?></textarea>
                            <button type="submit" style="flex: 1;">등록</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 달력 조회 -->
            <div id="content-wrapper">
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
                            $sql = "SELECT * FROM trade_stocks WHERE '$date' BETWEEN buy_date AND sell_date";
                            $result = $mysqli->query($sql);
                            while ($row = $result->fetch_assoc()) {
                                echo "{$row['name']}<br>";
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

        <!-- 우측: 종목 매매내역 조회 -->
        <div id="right-content">
            <div id="content-wrapper">
                <h2><?= $selectedYearMonth ?> 종목 매매 내역</h2>
                <div id="trade-list">
                    <table>
                        <tr>
                            <th>종목명</th>
                            <th>종목코드</th>
                            <th>매수일자</th>
                            <th>매도일자</th>
                            <th>수익/손실</th>
                            <th>코멘트</th>
                            <th>수정</th>
                            <th>삭제</th>
                        </tr>
                        <?php
                        while ($row = $trade_result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>{$row['name']}</td>";
                            echo "<td>{$row['code']}</td>";
                            echo "<td>{$row['buy_date']}</td>";
                            echo "<td>{$row['sell_date']}</td>";
                            echo "<td>{$row['profit_loss']}</td>";
                            echo "<td>{$row['comment']}</td>";
                            echo "<td><a href='?yearMonth=$selectedYearMonth&id={$row['id']}'>수정</a></td>";
                            echo "<td><form method='post' action='trade_process.php'>
                                    <input type='hidden' name='id' value='{$row['id']}'>
                                    <button type='submit' name='action' value='delete'>삭제</button>
                                </form></td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
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
