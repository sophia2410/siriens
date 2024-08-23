<style>
    #right-panel {
        /* 스타일 정의 */
        overflow-y: auto;
        padding: 15px;
        background-color: #f8f8f8;
        flex: 6;
    }

    #right-panel h2 {
        font-size: 18px;
        margin-bottom: 15px;
    }

    .custom-button {
        margin: 0;
        padding: 4px 8px;
        font-size: 12px;
        line-height: 1;
        background-color: #007bff;
        color: white;
        border: none;
        cursor: pointer;
        border-radius: 4px;
    }

    .custom-button:hover {
        background-color: #0056b3;
    }

    .accordion-content {
        display: none; /* Initially hidden */
    }

    .accordion-content table {
        width: 100%;
        border-collapse: collapse;
    }

    .accordion-content th, .accordion-content td {
        border: 1px solid #ddd;
        padding: 6px;
    }
</style>

<script>
    function openPopup(url) {
        window.open(url, 'popupWindow', 'width=800,height=600,scrollbars=yes,resizable=yes');
    }

    function toggleAccordion(element) {
        var content = element.nextElementSibling;
        if (content.style.display === "table-row") {
            content.style.display = "none";
        } else {
            content.style.display = "table-row";
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('toggle-all').addEventListener('click', function() {
            var accordionContents = document.querySelectorAll('.accordion-content');
            accordionContents.forEach(function(content) {
                if (content.style.display === "none" || content.style.display === "") {
                    content.style.display = "table-row";
                } else {
                    content.style.display = "none";
                }
            });
        });
    });
</script>

<?php
// 해당 파일이 issue_register.php에서 호출되었는지 확인
$isMarketIssuePage = basename($_SERVER['PHP_SELF']) === 'issue_register.php';
?>

<div id="right-panel">
    <h2>Issue List for <?= htmlspecialchars($dateParam) ?></h2>

    <!-- 키워드 그룹을 한 줄로 모아서 표시 -->
    <div style="margin-bottom: 15px; padding: 10px; background-color: #f0f0f0; border-radius: 5px;">
        <?php foreach ($issuesResult as $issue): ?>
            <span 
                style="display: inline-block; margin-right: 10px; padding: 5px 10px; background-color: #ddd; border-radius: 5px; cursor: pointer;" 
                onclick="<?= $isMarketIssuePage ? "scrollToKeyword('" . htmlspecialchars($issue['group_name']) . "', " . $issue['issue_id'] . ");" : "openPopup('../market_issue_stock.php?issue_id=" . $issue['issue_id'] . "');" ?>"
            >
                <?= htmlspecialchars($issue['group_name']) ?>
            </span>
        <?php endforeach; ?>
    </div>
    
    <button id="toggle-all" style="margin-top: 5px;">종목 펼치기/접기</button>
    <table>
        <tbody>
            <?php foreach ($issuesResult as $issue): ?>
                <tr data-issue-id="<?= $issue['issue_id'] ?>" style='background-color: #e8e8e8; font-weight: bold;';>
                    <?php 
                        $statusStyle = ($issue['status'] === 'copied') ?'background-color:#ffe0e0;' : '';
                        $themeStyle  = ($issue['hot_theme'] === 'Y') ? 'color:red;' : '';
                    ?>
                    <td style="width:9%; display: table-cell; <?= $themeStyle ?> <?= $statusStyle ?>"><?= htmlspecialchars($issue['theme']) ?></td>
                    <td style="<?= $statusStyle ?>">
                        <a href="#" onclick="<?= $isMarketIssuePage ? "loadIssueDetails(" . $issue['issue_id'] . ", " . $formattedDate . "); return false;" : "openPopup('../market_issue_stock.php?issue_id=" . $issue['issue_id'] . "'); return false;" ?>" style=''>
                            <?= htmlspecialchars($issue['group_name']) ?>
                        </a>
                    </td>
                    <td style="<?= $statusStyle ?>"><?= htmlspecialchars($issue['sector']) ?></td>
                    <td style="<?= $statusStyle ?>">
                        <form method="post" action="../issue_process.php" style="display:inline;">
                            <input type="hidden" name="action" value="copy">
                            <input type="hidden" name="issue_id" value="<?= $issue['issue_id'] ?>">
                            <input type="hidden" name="report_date" value="<?= $dateParam ?>">
                            <button type="submit" class='custom-button'>복사</button>
                        </form>
                        |
                        <form method="post" action="../issue_process.php" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="issue_id" value="<?= $issue['issue_id'] ?>">
                            <input type="hidden" name="report_date" value="<?= $dateParam ?>">
                            <button type="submit" class="custom-button">삭제</button>
                        </form>
                    </td>
                    <td style="<?= $statusStyle ?>">
                        <input type="text" id="news_url_<?= htmlspecialchars($issue['theme']) ?>" style="width: 200px;" placeholder="뉴스 URL">
                        <button type="button" class='custom-button' onclick="scrapNews('<?= htmlspecialchars($issue['theme']) ?>')">스크랩</button>
                    </td>
                </tr>
                <?php if($issue['issue'] !== ''):?>
                    <tr>
                        <td colspan=5><?= htmlspecialchars($issue['issue'])?> ( <?=htmlspecialchars($issue['first_occurrence']) ?> )</td>
                    </tr>
                <?php endif; ?>
                <tr class="accordion-content">
                    <td colspan="5">
                        <table>
                            <tbody>
                                <?php if (isset($stocksData[$issue['issue_id']])): ?>
                                    <?php foreach ($stocksData[$issue['issue_id']] as $stock): ?>
                                        <?php 
                                            $amountInBillion = $stock['trade_amount'];
                                            $closeRate = $stock['close_rate'];
                                            $amountStyle  = getAmountStyle($amountInBillion);
                                            $nameStyle  = ($closeRate >= 29.5) ? 'color:red;' : '';
                                            $leaderStyle = ($stock['is_leader'] === '1') ? 'background-color:#fff9c4;' : '';
                                        ?>
                                        <tr>
                                            <td style="width:15%; display: table-cell; font-weight: bold; <?= $nameStyle ?> <?= $leaderStyle ?>"><?= htmlspecialchars($stock['name']) ?></td>
                                            <td style="width:5%; display: table-cell;"><?= htmlspecialchars($stock['code']) ?></td>
                                            <td align=right style="width:8%; display: table-cell; <?= $nameStyle ?>"><?= number_format($stock['close_rate'],2) ?> %</td>
                                            <td align=right style="width:9%; display: table-cell; <?= $amountStyle ?>"><?= number_format($stock['trade_amount']) ?> 억</td>
                                            <td style="display: table-cell;"><?= htmlspecialchars($stock['stock_comment']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3">No stocks available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>