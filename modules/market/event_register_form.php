<style>
	.event_register_stock_item {
		margin-bottom: 10px; /* Reduced margin */
	}
	.event_register_stock_row {
		display: flex;
		align-items: center;
	}
	.event_register_stock_row input[type="text"] {
		margin-right: 8px; /* Reduced margin */
	}
	.event_register_stock_row label {
		display: flex;
		align-items: center;
	}
</style>
 
<?php
function render_event_register_form($dateParam = null, $event_id = null) {
?>
    <h2>이벤트 및 테마 등록</h2>
    <form method="post" action="event_process.php">
        <input type="hidden" name="action" value="register" id="event_register_action">
        <input type="hidden" name="event_id" id="event_register_event_id">
        <label for="event_register_date">날짜:</label>
        <input type="date" id="event_register_date" name="report_date" value="<?= $dateParam ?>" required>

        <label>
        <input type="checkbox" name="new_issue" id="event_register_new_issue"> 신규 이벤트
        </label>
        <textarea id="event_register_issue" name="issue" rows="2" placeholder="이벤트"></textarea>

        <label for="event_register_keyword">키워드 (# 으로 구분):</label>
        <input type="text" id="event_register_keyword" name="keyword" placeholder="#키워드1 #키워드2" required autocomplete="off">
        <label for="event_register_theme">테마:</label>
        <div style="display: flex; align-items: center;">
            <input type="text" id="event_register_theme" name="theme" placeholder="테마 입력" autocomplete="off" style="flex: 3.3; margin-right: 10px;">
            <label for="event_register_hot_theme" style="display: flex; align-items: center; margin: 0; flex: 1;">
                <input type="checkbox" name="hot_theme" id="event_register_hot_theme" style="margin-right: 5px;"> 핫 테마
        </div>
        <br><br>
        <h2>- 종목 - </h2>
        <div id="event_register_stocks_container">
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 15px;">
            <div>
                <button type="button" id="event_register_add_stock_button">종목 추가</button>
                <button type="submit">등록</button>
            </div>
            <div>
                <button type="button" id="event_register_reset_button">초기화</button>
            </div>
        </div>
    </form>

    <script>
        function EventRegisterForm_Initialize() {
            console.log("이벤트 등록 폼 초기화");
            let eventRegisterStockIndex = 0; // 초기 인덱스 설정

            // 초기 종목을 1개 추가
            EventRegisterForm_AddStock(eventRegisterStockIndex);

            // 날짜 변경 시 페이지 이동
            document.getElementById('event_register_date').addEventListener('change', function() {
                // 현재 페이지 URL에서 쿼리 파라미터만 바꾸는 방식
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('date', this.value);  // 'date' 파라미터 수정 또는 추가
                window.location.href = currentUrl.toString();  // 수정된 URL로 리다이렉트
            });

            // 키워드 입력 필드에서 Enter 키를 눌렀을 때 폼이 제출되지 않도록 처리
            document.getElementById('event_register_keyword').addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault(); // Enter key 눌렀을 때 폼 제출 방지
                }
            });

            // 키워드 자동완성
            $("#event_register_keyword").on('input', function() {
                let query = $(this).val();
                if (query.length > 0) {
                    $.ajax({
                        url: 'fetch_autocomplete.php',
                        type: 'GET',
                        data: { type: 'keywords', q: query },
                        success: function(data) {
                            let keywordGroups = JSON.parse(data);
                            $("#event_register_keyword").autocomplete({
                                source: keywordGroups
                            });
                        }
                    });
                }
            });

            // 테마 및 섹터 자동완성
            EventRegisterForm_SetupAutocomplete('#event_register_theme', 'theme', 'theme');

            // 종목 추가 버튼 클릭 시 호출
            document.getElementById('event_register_add_stock_button').addEventListener('click', () => {
                EventRegisterForm_AddStock(eventRegisterStockIndex++);
                EventRegisterForm_ReindexStockFields(); // 인덱스 재정렬
            });

            // 초기화 버튼 클릭 시 호출
            document.getElementById('event_register_reset_button').addEventListener('click', function() {
                // 선택된 일자에 맞춰 화면 리로드
                const selectedDate = document.getElementById('event_register_date').value;

                // 현재 페이지 URL에서 쿼리 파라미터만 바꾸는 방식
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('date', selectedDate);  // date 파라미터 수정 또는 추가
                window.location.href = currentUrl.toString();  // 수정된 URL로 리다이렉트
            });
            <?php if ($event_id): ?>
                // event_id가 있는 경우, 해당 이벤트의 데이터를 로드
                EventRegisterForm_LoadDetails(<?= $event_id ?>);
            <?php endif; ?>
            console.log("이벤트 등록 폼 초기화 종료");
        }

        function EventRegisterForm_SetupAutocomplete(selector, type, key) {
            $(selector).focus(function() {
                const keywordGroup = $("#event_register_keyword").val().trim();
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

        function EventRegisterForm_AddStock(stockIndex, code = '', name = '', comment = '', isLeader = '', isWatchlist = '') {
            const container = document.getElementById('event_register_stocks_container');
            const newStock = document.createElement('div');
            newStock.className = 'event_register_stock_item';
            newStock.innerHTML = `
                <div class="event_register_stock_row">
                    <input type="text" name="stocks[${stockIndex}][name]" value="${name}" onkeydown="Common_SearchStock(event, this)" placeholder="종목명/코드" required style="flex: 2; margin-right: 10px;" class="input-border-bold input-background-light input-text-bold" autocomplete="off">
                    <input type="text" name="stocks[${stockIndex}][code]" value="${code}" readonly placeholder="코드" style="flex: 1; margin-right: 10px;">
                    <label style="margin-left: 10px;">
                        <input type="checkbox" name="stocks[${stockIndex}][is_leader]" ${isLeader ? 'checked' : ''}> 주도주
                    </label>
                    <label style="margin-left: 10px;">
                        <input type="checkbox" name="stocks[${stockIndex}][is_watchlist]" ${isWatchlist ? 'checked' : ''}> 관심
                    </label>
                    <button type="button" onclick="EventRegisterForm_RemoveStock(this)" style="margin-left: 10px;">삭제</button>
                </div>
                <div class="event_register_stock_comment">
                    <input type="text" name="stocks[${stockIndex}][comment]" value="${comment}" placeholder="코멘트" style="width: 100%; margin-top: 10px;" autocomplete="off" onfocus="EventRegisterForm_FetchStockComment(this)">
                </div>
            `;
            container.appendChild(newStock);
        }

        function EventRegisterForm_RemoveStock(button) {
            const stockItem = button.closest('.event_register_stock_item'); // stock-item div
            stockItem.remove(); // 종목 삭제
            EventRegisterForm_ReindexStockFields(); // 인덱스 재정렬
        }

        function EventRegisterForm_ReindexStockFields() {
            const stockItems = document.querySelectorAll('.event_register_stock_item');
            stockItems.forEach((item, index) => {
                item.querySelector('input[name^="stocks"][name$="[name]"]').name = `stocks[${index}][name]`;
                item.querySelector('input[name^="stocks"][name$="[code]"]').name = `stocks[${index}][code]`;
                item.querySelector('input[name^="stocks"][name$="[comment]"]').name = `stocks[${index}][comment]`;
                item.querySelector('input[name^="stocks"][name$="[is_leader]"]').name = `stocks[${index}][is_leader]`;
                item.querySelector('input[name^="stocks"][name$="[is_watchlist]"]').name = `stocks[${index}][is_watchlist]`;
            });
        }

        // event_register_form 에 값 셋팅
        function EventRegisterForm_LoadDetails(eventId) {
            $.ajax({
                url: 'fetch_event_details.php',
                type: 'GET',
                data: { event_id: eventId },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.eventDetails) {
                        $('#event_register_date').val(data.eventDetails.date);
                        $('#event_register_keyword').val(data.eventDetails.keyword_group_name);
                        $('#event_register_issue').val(data.eventDetails.issue);
                        $('#event_register_theme').val(data.eventDetails.theme);
                        $('#event_register_hot_theme').prop('checked', data.eventDetails.hot_theme === 'Y');
                        $('#event_register_new_event').prop('checked', data.eventDetails.first_occurrence === 'Y');
                        $('#event_register_action').val('update');
                        $('#event_register_event_id').val(data.eventDetails.event_id);
                        
                        // Check status and update keyword box style
                        if (data.eventDetails.status === 'copied') {
                            $('#event_register_keyword').css({
                                'background-color': '#ffe0e0', // Light coral or any other color
                                'font-weight': 'bold'          // Make it stand out
                            });
                        } else {
                            $('#event_register_keyword').css({
                                'background-color': '',
                                'font-weight': ''
                            });
                        }

                        // 관련 종목 정보 셋팅
                        const container = $('#event_register_stocks_container');
                        container.empty();
                        data.stocks.forEach((stock, index) => {
                            const isLeader = stock['is_leader'] === '1' ? true : false;
                            const isWatchList = stock['is_watchlist'] === '1' ? true : false;
                            EventRegisterForm_AddStock(index, stock.code, stock.name, stock.stock_comment, isLeader, isWatchList);
                        });
                        EventRegisterForm_ReindexStockFields();
                    }
                }
            });
        }

        window.EventRegisterForm_FetchStockComment = function(input) {
            const stockRow = input.closest('.event_register_stock_item').querySelector('input[name^="stocks"][name$="[code]"]');
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