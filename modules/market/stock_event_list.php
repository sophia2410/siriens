<?php
$pageTitle = "종목별 이슈 조회"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT']."/modules/market/event_register_form.php");

// GET 파라미터로 넘어온 값 처리
$stockName = $_GET['stock_name'] ?? '';
$stockCode = $_GET['stock_code'] ?? '';

// 종목명과 코드가 설정되어 있을 때만 이슈 조회 실행
$eventsResult = [];
if ($stockCode) {
    $eventsQuery = $mysqli->prepare("
        SELECT me.*, kg.group_name, mes.*
        FROM market_events me 
        LEFT JOIN keyword_groups kg ON me.keyword_group_id = kg.group_id 
        LEFT JOIN market_event_stocks mes ON me.event_id = mes.event_id 
        WHERE mes.code = ? 
        ORDER BY me.date DESC, me.hot_theme DESC, kg.group_name ASC
    ");
    $eventsQuery->bind_param('s', $stockCode);
    $eventsQuery->execute();
    $eventsResult = $eventsQuery->get_result();
}
?>

<head>
    <!-- 페이지 전용 스타일 -->
    <style>
        #event_list_panel {
            flex: 2;
            background-color: #f8f8f8;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            padding: 20px;
        }

        #event_details_panel {
            flex: 1;
            background-color: #fff;
            padding: 20px;
            overflow-y: auto;
        }

        .search-bar {
            display: flex;
            margin-bottom: 20px;
        }

        #stock_search_input {
            flex: 1;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
        }
        
        table.event_list_table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table.event_list_table th, table.event_list_table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        table.event_list_table th {
            background-color: #f2f2f2;
        }

        .first_occurrence {
            font-weight: bold;
            color: #e74c3c; /* 빨간색 */
        }

        .hot_theme {
            background-color: #f39c12; /* 주황색 배경 */
            color: white;
            padding: 2px 4px;
            border-radius: 3px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div id="container">
        <!-- 종목 검색 및 이슈 리스트 -->
        <div id="event_list_panel">
            <h2>종목 이슈 조회</h2>
            <form id="stock_search_form" method="get" action="stock_event_list.php">
                <div class="search-bar">
                    <input type="text" id="stock_search_input" name="stock_name" value="<?= htmlspecialchars($stockName) ?>" placeholder="종목명/코드 입력" required autocomplete="off">
                    <input type="hidden" id="stock_code_input" name="stock_code" value="<?= htmlspecialchars($stockCode) ?>">
                    <button type="submit">조회</button>
                </div>
            </form>

            <?php if ($stockCode && $eventsResult->num_rows > 0): ?>
                <h3>이슈 리스트</h3>
                <table class="event_list_table">
                    <thead>
                        <tr>
                            <th>날짜</th>
                            <th>키워드 그룹</th>
                            <th>테마</th>
                            <th>이슈</th>
                            <th>등락률</th>
                            <th>거래대금</th>
                            <th>주도주</th>
                            <th>관심종목</th>
                            <th>종목 코멘트</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($event = $eventsResult->fetch_assoc()): ?>
                            <?php 
                                $themeClass    = ($event['hot_theme'] === 'Y') ? 'hot-theme' : '';
                                $newEventClass = ($event['first_occurrence'] === 'Y') ? 'first-occurrence' : '';

                                // 등락률 따른 스타일 클래스
                                $closeRateClass = Utility_GetCloseRateClass($event['close_rate']);
                                // 금액에 따른 스타일 클래스
                                $amountClass = Utility_GetAmountClass($event['trade_amount']);

                                $isLeader = ($event['is_leader'] === '1') ? 'Y' : '';
                                $leaderClass = ($event['is_leader'] === '1') ? 'leader' : '';

                                $isWatchlist = ($event['is_watchlist'] === '1') ? 'Y' : '';
                                $watchListClass = ($event['is_watchlist'] === '1') ? 'watchlist' : '';
                            ?>
                            <tr onclick="EventRegisterForm_LoadDetails(<?= $event['event_id'] ?>)" style="cursor: pointer;">
                                <td><?= $event['date'] ?></td>
                                <td><?= htmlspecialchars($event['group_name']) ?></td>
                                <td class="<?= $themeClass; ?>"><?= htmlspecialchars($event['theme']) ?></td>
                                <td class="<?= $newEventClass; ?>"><?= htmlspecialchars($event['issue']) ?></td>
                                <td class="<?= $closeRateClass; ?>"><?= number_format($event['close_rate'], 2) ?>%</td>
                                <td class="<?= $amountClass; ?>"><?= number_format($event['trade_amount']) ?>억</td>
                                <td class="<?= $leaderClass; ?>"><?= $isLeader ?></td>
                                <td class="<?= $watchListClass; ?>"><?= $isWatchlist ?></td>
                                <td><?= htmlspecialchars($event['stock_comment']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php elseif ($stockCode): ?>
                <p>해당 종목에 대한 이슈가 없습니다.</p>
            <?php endif; ?>
        </div>

        <!-- 이슈 및 테마 등록 화면 -->
        <div id="event_details_panel">
            <?php render_event_register_form(); ?>
        </div>
    </div>

    <script>
        function StockEventList_Initialize() {
            console.log("상품 이슈 조회 초기화");

            document.getElementById('stock_search_input').addEventListener('input', function() {
                // 종목명 변경 시 코드 초기화
                if (event.key !== 'Enter') {
                    document.getElementById('stock_code_input').value = '';
                }
            });

            document.getElementById('stock_search_input').addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault(); // 폼 제출 방지

                    const stockCode = document.getElementById('stock_code_input').value.trim();
                    
                    if (stockCode === '') {
                        // 종목 코드가 없으면 종목 검색 수행
                        Common_SearchStock(event, this, function() {
                            // 종목 검색 후, 종목 코드가 설정되면 폼 제출
                            document.getElementById('stock_search_form').submit();
                        });
                    } else {
                        // 종목 코드가 이미 있으면 바로 조회
                        console.log(stockCode);
                        document.getElementById('stock_search_form').submit();
                    }
                }
            });
        }
    </script>
<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
</body>
</html>
