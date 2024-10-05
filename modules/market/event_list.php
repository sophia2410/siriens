<?php
function render_event_list($mysqli, $criteriaType, $criteriaValue) {
    // 마켓 이벤트 및 종목 데이터를 불러오는 쿼리
    $eventsQuery = null;
    $stocksQuery = null;

    switch ($criteriaType) {
        case 'date':
            // 일자별 조회
            $eventsQuery = $mysqli->prepare("
                SELECT 
                    me.*, 
                    kg.group_name 
                FROM 
                    market_events me 
                LEFT JOIN 
                    keyword_groups kg 
                    ON me.keyword_group_id = kg.group_id 
                WHERE 
                    me.date = ? 
                ORDER BY 
                    CASE 
                        WHEN EXISTS (SELECT 1 FROM market_events me2 WHERE me2.group_label = me.group_label AND me2.hot_theme = '1') THEN 1 
                        ELSE 0 
                    END DESC, 
                    kg.group_name ASC");
            $eventsQuery->bind_param('s', $criteriaValue);
            $stocksQuery = $mysqli->prepare("
                SELECT *
                FROM market_event_stocks
                WHERE event_id IN (SELECT event_id FROM market_events WHERE date = ?)
                ORDER BY is_leader DESC, is_watchlist DESC, close_rate DESC");
            $stocksQuery->bind_param('s', $criteriaValue);
            break;
        
        case 'keyword_group':
            // 키워드 그룹별 조회
            $eventsQuery = $mysqli->prepare("SELECT me.*, kg.group_name FROM market_events me LEFT JOIN keyword_groups kg ON me.keyword_group_id = kg.group_id WHERE kg.group_id = ? ORDER BY me.date DESC");
            $eventsQuery->bind_param('i', $criteriaValue);
            $stocksQuery = $mysqli->prepare("
                SELECT *
                FROM market_event_stocks
                WHERE event_id IN (SELECT event_id FROM market_events WHERE keyword_group_id IN (SELECT group_id FROM keyword_groups WHERE group_id = ?))
                ORDER BY is_leader DESC, is_watchlist DESC, close_rate DESC");
            $stocksQuery->bind_param('i', $criteriaValue);
            break;

        case 'stock':
            // 종목별 조회
            $eventsQuery = $mysqli->prepare("
                SELECT me.*, kg.group_name 
                FROM market_events me 
                LEFT JOIN keyword_groups kg ON me.keyword_group_id = kg.group_id 
                LEFT JOIN market_event_stocks mes ON me.event_id = mes.event_id 
                WHERE mes.code = ? 
                ORDER BY me.hot_theme DESC, kg.group_name ASC
            ");

            $eventsQuery->bind_param('s', $criteriaValue);
            $stocksQuery = $mysqli->prepare("
                SELECT *
                FROM market_event_stocks
                WHERE code = ?
                ORDER BY is_leader DESC, is_watchlist DESC, close_rate DESC");
            $stocksQuery->bind_param('s', $criteriaValue);
            break;

        default:
            throw new Exception("Invalid criteria type");
    }

    $eventsQuery->execute();
    $eventsResult = $eventsQuery->get_result();

    $stocksQuery->execute();
    $stocksResult = $stocksQuery->get_result();

    $stocksData = [];
    while ($stock = $stocksResult->fetch_assoc()) {
        $stocksData[$stock['event_id']][] = $stock;
    }
    // print_r($stocksData);

    // 화면 렌더링
    ?>
    <!-- 키워드 그룹을 한 줄로 모아서 표시 -->
    <div id="event_list_keyword_groups" style="margin-bottom: 15px; padding: 10px; background-color: #f0f0f0; border-radius: 5px;">
        <?php foreach ($eventsResult as $event): ?>
            <span 
                id="event_list_keyword_group_<?= $event['event_id'] ?>"
                style="display: inline-block; margin-right: 10px; padding: 5px 10px; background-color: #ddd; border-radius: 5px; cursor: pointer;" 
                onclick="EventList_ScrollToKeyword('<?= htmlspecialchars($event['group_name']) ?>', <?= $event['event_id'] ?>, '<?= $event['date'] ?>', '<?= $criteriaType ?>');"
            >
                <?= htmlspecialchars($event['group_name']) ?>
            </span>
        <?php endforeach; ?>
    </div>
    
    <button id="event_list_toggle_all" style="margin-top: 5px;">종목 펼치기/접기</button>

    <table id="event_list_table">
        <tbody>
            <?php foreach ($eventsResult as $event): ?>
                <?php 
                    $statusStyle = ($event['status'] === 'copied') ?'background-color:#ffe0e0;' : '';
                    $themeClass  = ($event['hot_theme'] === 'Y') ? 'hot-theme' : '';
                ?>
                <tr id="event_list_row_<?= $event['event_id'] ?>" data-event-id="<?= $event['event_id'] ?>" style='background-color: #e8e8e8; font-weight: bold; <?= $statusStyle ?>'>
                    <td><?= $event['date'] ?></td>
                    <td><?= $event['group_label'] ?></td>
                    <td class="<?= $themeClass; ?>" style="display: table-cell;"><?= htmlspecialchars($event['theme']) ?></td>
                    <td>
                        <a href="#" onclick="EventList_HandleKeywordClick('<?= $event['group_name'] ?>', <?= $event['event_id'] ?>, '<?= $event['date'] ?>', '<?= $criteriaType ?>'); return false;">
                            <?= htmlspecialchars($event['group_name']) ?>
                        </a>
                    </td>
                    <td>
                        <form method="post" action="event_process.php" style="display:inline;">
                            <input type="hidden" name="action" value="copy_event">
                            <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                            <input type="hidden" name="report_date" value="<?= $event['date'] ?>">
                            <button type="submit" class="button button-yellow button-mini">복사</button>
                        </form>
                        |
                        <form method="post" action="event_process.php" style="display:inline;">
                            <input type="hidden" name="action" value="delete_event">
                            <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                            <input type="hidden" name="report_date" value="<?= $event['date'] ?>">
                            <button type="submit" class="button button-yellow button-mini">삭제</button>
                        </form>
                    </td>
                </tr>
                <tr id="event_list_details_<?= $event['event_id'] ?>" class="accordion-content">
                    <td colspan="6">
                        <table id="event_list_stocks_<?= $event['event_id'] ?>">
                            <tbody>
                                <?php if (isset($stocksData[$event['event_id']])): ?>
                                    <?php foreach ($stocksData[$event['event_id']] as $stock): ?>
                                        <?php 
                                            $amountInBillion = $stock['trade_amount'];
                                            $closeRate = $stock['close_rate'];
                                            
                                            // 조건에 따른 스타일 클래스
                                            $closeRateClass = Utility_GetCloseRateClass($closeRate);
                                            $amountClass = Utility_GetAmountClass($amountInBillion);
                                            $stockNameClass = Utility_GetStockNameClass($closeRate);
                                            $leaderClass = ($stock['is_leader'] === '1') ? 'leader' : '';
                                            $watchListClass = ($stock['is_watchlist'] === '1') ? 'watchlist' : '';
                                        ?>
                                        <tr>
                                            <td class="<?= $stockNameClass; ?> <?= $watchListClass ?> <?= $leaderClass ?>" style="width:14%; display: table-cell;"><?= htmlspecialchars($stock['name']) ?></td>
                                            <td style="width:5%; display: table-cell;"><?= htmlspecialchars($stock['code']) ?></td>
                                            <td style="width:9%; display: table-cell; text-align: right;"><small>(H)</small><?= number_format($stock['high_rate'], 2) ?> % </td>
                                            <td class="<?= $closeRateClass; ?>" style="width:7%; display: table-cell;"><?= number_format($stock['close_rate'], 2) ?> %</td>
                                            <td class="<?= $amountClass; ?>" style="width:8%; display: table-cell;"><?= number_format($stock['trade_amount']) ?> 억</td>
                                            <td><?= htmlspecialchars($stock['stock_comment']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">No stocks available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        function EventList_Initialize() {
            console.log("이벤트 리스트 초기화");

            // 종목 펼치기/접기 버튼 설정
            document.getElementById('event_list_toggle_all').addEventListener('click', function() {
                const allContents = document.querySelectorAll('.accordion-content');
                let isExpanded = this.textContent.includes('접기');
                
                allContents.forEach(function(content) {
                    content.style.display = isExpanded ? 'none' : 'table-row';
                });
                
                this.textContent = isExpanded ? '종목 펼치기' : '종목 접기';
            });
        }

        function EventList_ScrollToKeyword(groupName, eventId, eventDate, criteriaType) {
            const targetRow = document.querySelector(`#event_list_row_${eventId}`);
            if (targetRow) {
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (criteriaType === 'date') {
                EventRegisterForm_LoadDetails(eventId);
            }
        }

        function EventList_HandleKeywordClick(groupName, eventId, eventDate, criteriaType) {
            if (criteriaType === 'date') {
                EventRegisterForm_LoadDetails(eventId);
            } else {
                // 팝업으로 event_register_form.php를 띄우고 event_id를 전달
                const popupUrl = `event_register_popup.php?event_id=${eventId}`;
                window.open(popupUrl, '_blank', 'width=800,height=1300');
            }
        }

    </script>
<?php
}
?>