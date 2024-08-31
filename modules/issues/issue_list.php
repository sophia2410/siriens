<?php
function render_issue_list($mysqli, $criteriaType, $criteriaValue) {
    // 마켓 이슈 및 종목 데이터를 불러오는 쿼리
    $issuesQuery = null;
    $stocksQuery = null;

    switch ($criteriaType) {
        case 'date':
            // 일자별 조회
            $issuesQuery = $mysqli->prepare("SELECT mi.*, kg.group_name FROM market_issues mi LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id WHERE mi.date = ? ORDER BY mi.hot_theme DESC, kg.group_name ASC");
            $issuesQuery->bind_param('s', $criteriaValue);
            $stocksQuery = $mysqli->prepare("
                SELECT *
                FROM market_issue_stocks
                WHERE issue_id IN (SELECT issue_id FROM market_issues WHERE date = ?)
                ORDER BY is_leader DESC, is_watchlist DESC, close_rate DESC");
            $stocksQuery->bind_param('s', $criteriaValue);
            break;
        
        case 'keyword_group':
            // 키워드 그룹별 조회
            $issuesQuery = $mysqli->prepare("SELECT mi.*, kg.group_name FROM market_issues mi LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id WHERE kg.group_id = ? ORDER BY mi.date DESC");
            $issuesQuery->bind_param('i', $criteriaValue);
            $stocksQuery = $mysqli->prepare("
                SELECT *
                FROM market_issue_stocks
                WHERE issue_id IN (SELECT issue_id FROM market_issues WHERE keyword_group_id IN (SELECT group_id FROM keyword_groups WHERE group_id = ?))
                ORDER BY is_leader DESC, is_watchlist DESC, close_rate DESC");
            $stocksQuery->bind_param('i', $criteriaValue);
            break;

        case 'stock':
            // 종목별 조회
            $issuesQuery = $mysqli->prepare("
                SELECT mi.*, kg.group_name 
                FROM market_issues mi 
                LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id 
                LEFT JOIN market_issue_stocks mis ON mi.issue_id = mis.issue_id 
                WHERE mis.code = ? 
                ORDER BY mi.hot_theme DESC, kg.group_name ASC
            ");
            $issuesQuery->bind_param('s', $criteriaValue);
            $stocksQuery = $mysqli->prepare("
                SELECT *
                FROM market_issue_stocks
                WHERE code = ?
                ORDER BY is_leader DESC, is_watchlist DESC, close_rate DESC");
            $stocksQuery->bind_param('s', $criteriaValue);
            break;

        default:
            throw new Exception("Invalid criteria type");
    }

    $issuesQuery->execute();
    $issuesResult = $issuesQuery->get_result();

    $stocksQuery->execute();
    $stocksResult = $stocksQuery->get_result();

    $stocksData = [];
    while ($stock = $stocksResult->fetch_assoc()) {
        $stocksData[$stock['issue_id']][] = $stock;
    }

    // 화면 렌더링
    ?>
    <h2>Issue List</h2>

    <!-- 키워드 그룹을 한 줄로 모아서 표시 -->
    <div id="issue_list_keyword_groups" style="margin-bottom: 15px; padding: 10px; background-color: #f0f0f0; border-radius: 5px;">
        <?php foreach ($issuesResult as $issue): ?>
            <span 
                id="issue_list_keyword_group_<?= $issue['issue_id'] ?>"
                style="display: inline-block; margin-right: 10px; padding: 5px 10px; background-color: #ddd; border-radius: 5px; cursor: pointer;" 
                onclick="IssueList_ScrollToKeyword('<?= htmlspecialchars($issue['group_name']) ?>', <?= $issue['issue_id'] ?>, '<?= $issue['date'] ?>', '<?= $criteriaType ?>');"
            >
                <?= htmlspecialchars($issue['group_name']) ?>
            </span>
        <?php endforeach; ?>
    </div>
    
    <button id="issue_list_toggle_all" style="margin-top: 5px;">종목 펼치기/접기</button>

    <table id="issue_list_table">
        <tbody>
            <?php foreach ($issuesResult as $issue): ?>
                <?php 
                    $statusStyle = ($issue['status'] === 'copied') ?'background-color:#ffe0e0;' : '';
                    $themeClass  = ($issue['hot_theme'] === 'Y') ? 'hot-theme' : '';
                ?>
                <tr id="issue_list_row_<?= $issue['issue_id'] ?>" data-issue-id="<?= $issue['issue_id'] ?>" style='background-color: #e8e8e8; font-weight: bold; <?= $statusStyle ?>'>
                    <td><?= Utility_FormatDate($issue['date']) ?></td>
                    <td class="<?= $themeClass; ?>" style="width:9%; display: table-cell;"><?= htmlspecialchars($issue['theme']) ?></td>
                    <td style>
                        <a href="#" onclick="IssueList_HandleKeywordClick('<?= $issue['group_name'] ?>', <?= $issue['issue_id'] ?>, '<?= $issue['date'] ?>', '<?= $criteriaType ?>'); return false;">
                            <?= htmlspecialchars($issue['group_name']) ?>
                        </a>
                    </td>
                    <td>
                        <form method="post" action="issue_process.php" style="display:inline;">
                            <input type="hidden" name="action" value="copy">
                            <input type="hidden" name="issue_id" value="<?= $issue['issue_id'] ?>">
                            <input type="hidden" name="report_date" value="<?= $issue['date'] ?>">
                            <button type="submit" class="button button-yellow button-mini">복사</button>
                        </form>
                        |
                        <form method="post" action="issue_process.php" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="issue_id" value="<?= $issue['issue_id'] ?>">
                            <input type="hidden" name="report_date" value="<?= $issue['date'] ?>">
                            <button type="submit" class="button button-yellow button-mini">삭제</button>
                        </form>
                    </td>
                    <td>
                        <input type="text" id="issue_list_news_url_<?= htmlspecialchars($issue['theme']) ?>" style="width: 200px;" placeholder="뉴스 URL">
                        <button type="button" class="button button-yellow button-mini" onclick="IssueList_ScrapNews('<?= htmlspecialchars($issue['theme']) ?>')">스크랩</button>
                    </td>
                </tr>
                <tr id="issue_list_details_<?= $issue['issue_id'] ?>" class="accordion-content">
                    <td colspan="6">
                        <table id="issue_list_stocks_<?= $issue['issue_id'] ?>">
                            <tbody>
                                <?php if (isset($stocksData[$issue['issue_id']])): ?>
                                    <?php foreach ($stocksData[$issue['issue_id']] as $stock): ?>
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
                                            <td class="<?= $stockNameClass; ?> <?= $watchListClass ?> <?= $leaderClass ?>" style="width:15%; display: table-cell;"><?= htmlspecialchars($stock['name']) ?></td>
                                            <td style="width:5%; display: table-cell;"><?= htmlspecialchars($stock['code']) ?></td>
                                            <td class="<?= $closeRateClass; ?>" style="width:8%; display: table-cell;"><?= number_format($stock['close_rate'], 2) ?> %</td>
                                            <td class="<?= $amountClass; ?>" style="width:9%; display: table-cell;"><?= number_format($stock['trade_amount']) ?> 억</td>
                                            <td><?= htmlspecialchars($stock['sector']) ?></td>
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
        function IssueList_Initialize() {
            console.log("이슈 리스트 초기화");

            // 종목 펼치기/접기 버튼 설정
            document.getElementById('issue_list_toggle_all').addEventListener('click', function() {
                const allContents = document.querySelectorAll('.accordion-content');
                let isExpanded = this.textContent.includes('접기');
                
                allContents.forEach(function(content) {
                    content.style.display = isExpanded ? 'none' : 'table-row';
                });
                
                this.textContent = isExpanded ? '종목 펼치기' : '종목 접기';
            });
        }

        function IssueList_ScrollToKeyword(groupName, issueId, issueDate, criteriaType) {
            const targetRow = document.querySelector(`#issue_list_row_${issueId}`);
            if (targetRow) {
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (criteriaType === 'date') {
                IssueRegisterForm_LoadDetails(issueId);
            }
        }

        function IssueList_HandleKeywordClick(groupName, issueId, issueDate, criteriaType) {
            if (criteriaType === 'date') {
                IssueRegisterForm_LoadDetails(issueId);
            } else {
                // 팝업으로 issue_register_form.php를 띄우고 issue_id를 전달
                const popupUrl = `issue_register_popup.php?issue_id=${issueId}`;
                window.open(popupUrl, '_blank', 'width=800,height=1300');
            }
        }

        function IssueList_ScrapNews(theme) {
            const url = document.getElementById(`issue_list_news_url_${theme}`).value;
            fetch(`get_title.php?url=${encodeURIComponent(url)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`제목: ${data.title} | URL: ${data.url}`);
                        const formData = new FormData();
                        formData.append('title', data.title);
                        formData.append('url', data.url);
                        formData.append('theme', theme);

                        return fetch('save_news.php', {
                            method: 'POST',
                            body: formData
                        });
                    } else {
                        throw new Error('제목을 가져오지 못했습니다.');
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('뉴스가 저장되었습니다.');
                        location.reload();
                    } else {
                        throw new Error('뉴스 저장에 실패했습니다.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('뉴스 스크랩 중 오류가 발생했습니다.');
                });
        }

    </script>
<?php
}
?>