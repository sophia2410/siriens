<style>
	.issue_register_stock_item {
		margin-bottom: 10px; /* Reduced margin */
	}
	.issue_register_stock_row {
		display: flex;
		align-items: center;
	}
	.issue_register_stock_row input[type="text"] {
		margin-right: 8px; /* Reduced margin */
	}
	.issue_register_stock_row label {
		display: flex;
		align-items: center;
	}
</style>
 
<?php
function render_issue_register_form($dateParam = null, $issueParam = null, $newIssueParam = null, $issue_id = null) {
?>
    <h2>이슈 및 테마 등록</h2>
    <form method="post" action="issue_process.php">
        <input type="hidden" name="action" value="register" id="issue_register_action">
        <input type="hidden" name="issue_id" id="issue_register_issue_id">
        <label for="issue_register_date">날짜:</label>
        <input type="date" id="issue_register_date" name="report_date" value="<?= $dateParam ?>" required>

        <label>
        <input type="checkbox" name="new_issue" id="issue_register_new_issue" <?= (isset($newIssueParam) && $newIssueParam == 1) ? 'checked' : '' ?>> 신규 이슈
        </label>
        <textarea id="issue_register_issue" name="issue" rows="2" placeholder="이슈"><?= isset($issueParam) ? htmlspecialchars($issueParam, ENT_QUOTES) : '' ?></textarea>

        <label for="issue_register_keyword">키워드 (# 으로 구분):</label>
        <input type="text" id="issue_register_keyword" name="keyword" placeholder="#키워드1 #키워드2" required autocomplete="off">
        <label for="issue_register_theme">테마:</label>
        <div style="display: flex; align-items: center;">
            <input type="text" id="issue_register_theme" name="theme" placeholder="테마 입력" autocomplete="off" style="flex: 3.3; margin-right: 10px;">
            <label for="issue_register_hot_theme" style="display: flex; align-items: center; margin: 0; flex: 1;">
                <input type="checkbox" name="hot_theme" id="issue_register_hot_theme" style="margin-right: 5px;"> 핫 테마
        </div>
        <br><br>
        <h2>- 종목 - </h2>
        <div id="issue_register_stocks_container">
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 15px;">
            <div>
                <button type="button" id="issue_register_add_stock_button">종목 추가</button>
                <button type="submit">등록</button>
            </div>
            <div>
                <button type="button" id="issue_register_reset_button">초기화</button>
            </div>
        </div>
    </form>

    <script>
        function IssueRegisterForm_Initialize() {
            console.log("이슈 등록 폼 초기화");
            let issueRegisterStockIndex = 0; // 초기 인덱스 설정

            // 초기 종목을 1개 추가
            IssueRegisterForm_AddStock(issueRegisterStockIndex);

            // 날짜 변경 시 페이지 이동
            document.getElementById('issue_register_date').addEventListener('change', function() {
                // 현재 페이지 URL에서 쿼리 파라미터만 바꾸는 방식
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('date', this.value);  // 'date' 파라미터 수정 또는 추가
                window.location.href = currentUrl.toString();  // 수정된 URL로 리다이렉트
            });

            // 키워드 입력 필드에서 Enter 키를 눌렀을 때 폼이 제출되지 않도록 처리
            document.getElementById('issue_register_keyword').addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault(); // Enter key 눌렀을 때 폼 제출 방지
                }
            });

            // 키워드 자동완성
            $("#issue_register_keyword").on('input', function() {
                let query = $(this).val();
                if (query.length > 0) {
                    $.ajax({
                        url: 'fetch_autocomplete.php',
                        type: 'GET',
                        data: { type: 'keywords', q: query },
                        success: function(data) {
                            let keywordGroups = JSON.parse(data);
                            $("#issue_register_keyword").autocomplete({
                                source: keywordGroups
                            });
                        }
                    });
                }
            });

            // 테마 및 섹터 자동완성
            IssueRegisterForm_SetupAutocomplete('#issue_register_theme', 'theme_sector', 'theme');

            // 종목 추가 버튼 클릭 시 호출
            document.getElementById('issue_register_add_stock_button').addEventListener('click', () => {
                IssueRegisterForm_AddStock(issueRegisterStockIndex++);
                IssueRegisterForm_ReindexStockFields(); // 인덱스 재정렬
            });

            // 초기화 버튼 클릭 시 호출
            document.getElementById('issue_register_reset_button').addEventListener('click', function() {
                // 선택된 일자에 맞춰 화면 리로드
                const selectedDate = document.getElementById('issue_register_date').value;

                // 현재 페이지 URL에서 쿼리 파라미터만 바꾸는 방식
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('date', selectedDate);  // date 파라미터 수정 또는 추가
                window.location.href = currentUrl.toString();  // 수정된 URL로 리다이렉트
            });
            <?php if ($issue_id): ?>
                // issue_id가 있는 경우, 해당 이슈의 데이터를 로드
                IssueRegisterForm_LoadDetails(<?= $issue_id ?>);
            <?php endif; ?>
            console.log("이슈 등록 폼 초기화 종료");
        }

        function IssueRegisterForm_SetupAutocomplete(selector, type, key) {
            $(selector).focus(function() {
                const keywordGroup = $("#issue_register_keyword").val().trim();
                $.ajax({
                    url: 'fetch_autocomplete.php',
                    type: 'GET',
                    data: { type: type, q: keywordGroup },
                    success: function(data) {
                        const suggestions = JSON.parse(data);
                        const items = suggestions.map(item => item[key]).filter(Boolean);
                        $(selector).autocomplete({
                            source: items,
                            minLength: 0  // Ensure that suggestions show even for empty input
                        }).autocomplete("search", ""); // Trigger autocomplete
                    }
                });
            });
        }

        function IssueRegisterForm_AddStock(stockIndex, code = '', name = '', comment = '', isLeader = '', sector = '') {
            const container = document.getElementById('issue_register_stocks_container');
            const newStock = document.createElement('div');
            newStock.className = 'issue_register_stock_item';
            newStock.innerHTML = `
                <div class="issue_register_stock_row">
                    <input type="text" name="stocks[${stockIndex}][name]" value="${name}" onkeydown="Common_SearchStock(event, this)" placeholder="종목명/코드" required style="flex: 2; margin-right: 10px;" autocomplete="off">
                    <input type="text" name="stocks[${stockIndex}][code]" value="${code}" readonly placeholder="코드" style="flex: 1; margin-right: 10px;">
                    <input type="text" name="stocks[${stockIndex}][sector]" value="${sector}" placeholder="섹터" style="flex: 1; margin-right: 10px;" autocomplete="off">
                    <label style="margin-left: 10px;">
                        <input type="checkbox" name="stocks[${stockIndex}][is_leader]" ${isLeader ? 'checked' : ''}> 주도주
                    </label>
                    <button type="button" onclick="IssueRegisterForm_RemoveStock(this)" style="margin-left: 10px;">삭제</button>
                </div>
                <div class="issue_register_stock_comment">
                    <input type="text" name="stocks[${stockIndex}][comment]" value="${comment}" placeholder="코멘트" style="width: 100%; margin-top: 10px;" autocomplete="off" onfocus="IssueRegisterForm_FetchStockComment(this)">
                </div>
            `;
            container.appendChild(newStock);
        }

        function IssueRegisterForm_RemoveStock(button) {
            const stockItem = button.closest('.issue_register_stock_item'); // stock-item div
            stockItem.remove(); // 종목 삭제
            IssueRegisterForm_ReindexStockFields(); // 인덱스 재정렬
        }

        function IssueRegisterForm_ReindexStockFields() {
            const stockItems = document.querySelectorAll('.issue_register_stock_item');
            stockItems.forEach((item, index) => {
                item.querySelector('input[name^="stocks"][name$="[name]"]').name = `stocks[${index}][name]`;
                item.querySelector('input[name^="stocks"][name$="[code]"]').name = `stocks[${index}][code]`;
                item.querySelector('input[name^="stocks"][name$="[comment]"]').name = `stocks[${index}][comment]`;
                item.querySelector('input[name^="stocks"][name$="[is_leader]"]').name = `stocks[${index}][is_leader]`;
            });
        }

        // issue_register_form 에 값 셋팅
        function IssueRegisterForm_LoadDetails(issueId) {
            $.ajax({
                url: 'fetch_issue_details.php',
                type: 'GET',
                data: { issue_id: issueId },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.issueDetails) {
                        $('#issue_register_date').val(data.issueDetails.date);
                        $('#issue_register_keyword').val(data.issueDetails.keyword_group_name);
                        $('#issue_register_issue').val(data.issueDetails.issue);
                        $('#issue_register_theme').val(data.issueDetails.theme);
                        $('#issue_register_hot_theme').prop('checked', data.issueDetails.hot_theme === 'Y');
                        $('#issue_register_new_issue').prop('checked', data.issueDetails.first_occurrence === 'Y');
                        $('#issue_register_action').val('update');
                        $('#issue_register_issue_id').val(data.issueDetails.issue_id);
                        
                        // Check status and update keyword box style
                        if (data.issueDetails.status === 'copied') {
                            $('#issue_register_keyword').css({
                                'background-color': '#ffe0e0', // Light coral or any other color
                                'font-weight': 'bold'          // Make it stand out
                            });
                        } else {
                            $('#issue_register_keyword').css({
                                'background-color': '',
                                'font-weight': ''
                            });
                        }

                        // 관련 종목 정보 셋팅
                        const container = $('#issue_register_stocks_container');
                        container.empty();
                        data.stocks.forEach((stock, index) => {
                            const isLeader = stock['is_leader'] === '1' ? true : false;
                            IssueRegisterForm_AddStock(index, stock.code, stock.name, stock.stock_comment, isLeader, stock.sector);
                        });
                        IssueRegisterForm_ReindexStockFields();
                    }
                }
            });
        }

        window.IssueRegisterForm_FetchStockComment = function(input) {
            const stockRow = input.closest('.issue_register_stock_item').querySelector('input[name^="stocks"][name$="[code]"]');
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
                            minLength: 0 // Ensure that suggestions show even for empty input
                        }).autocomplete("search", ""); // Trigger autocomplete
                    }
                });
            }
        }
    </script>
<?php
}
?>