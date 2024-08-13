<script>
    $(document).ready(function() {
        let stockIndex = 0; // 초기 인덱스 설정

        // Function to add a new stock entry
        function addStock() {
            const container = $('#stocks-container');
            const newStock = $(`
                <div class="stock-item">
                    <div class="stock-row">
                        <input type="text" name="stocks[${stockIndex}][name]" onkeydown="searchStock(event, this)" placeholder="종목명/코드" required style="flex: 2; margin-right: 10px;">
                        <input type="text" name="stocks[${stockIndex}][code]" readonly placeholder="코드" style="flex: 1; margin-right: 10px;">
                        <button type="button" onclick="removeStock(this)" style="margin-left: 10px;">삭제</button>
                    </div>
                    <div class="stock-keywords">
                        <input type="text" name="stocks[${stockIndex}][keywords]" placeholder="#키워드1 #키워드2" style="width: 100%; margin-top: 10px;">
                    </div>
                </div>
            `);
            container.append(newStock);
            stockIndex++; // 인덱스 증가
        }

        // Function to remove a stock entry
        window.removeStock = function(button) {
            $(button).closest('.stock-item').remove();
        }

        // Add initial stock entry
        addStock();

        // Add stock button click handler
        $('#add-stock-button').on('click', addStock);

        // Keywords autocomplete
        $('#keyword').on('input', function() {
            let query = $(this).val();
            if (query.length > 0) {
                $.ajax({
                    url: 'fetch_data.php',
                    type: 'GET',
                    data: { type: 'keywords', q: query },
                    success: function(data) {
                        let keywordGroups = JSON.parse(data);
                        $('#keyword').autocomplete({
                            source: keywordGroups
                        });
                    }
                });
            }
        });

        // Toggle button for accordion content
        var toggleButton = $('#toggle-all');
        var allContents = $('.accordion-content');
        var isExpanded = false; // 초기 상태는 접혀있는 상태

        toggleButton.on('click', function() {
            isExpanded = !isExpanded; // 토글 상태 업데이트
            allContents.each(function() {
                if (isExpanded) {
                    $(this).show(); // 모든 아코디언 항목 표시
                } else {
                    $(this).hide(); // 모든 아코디언 항목 숨김
                }
            });
            toggleButton.text(isExpanded ? '모든 종목 접기' : '모든 종목 펼치기'); // 버튼 텍스트 업데이트
        });

        // Date change event handler for reloading the page
        $('#report_date').on('change', function() {
            window.location.href = 'market_issue_register.php?date=' + this.value;
        });
    });

    // Fetch stocks via AJAX
    async function fetchStocks(query) {
        try {
            const response = await fetch(`fetch_stocks.php?q=${encodeURIComponent(query)}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            console.log("Data received from fetch_stocks.php:", data);
            return data;
        } catch (error) {
            console.error("Error fetching stocks:", error);
            return [];
        }
    }

    // Show stock selection popup
    function showStockPopup(stocks, nameInput, codeInput) {
        const popup = $('<div>', {
            css: {
                position: 'fixed',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)',
                backgroundColor: '#fff',
                padding: '20px',
                boxShadow: '0 0 10px rgba(0, 0, 0, 0.5)',
                zIndex: 1000,
                maxHeight: '400px',
                overflowY: 'auto'
            }
        });

        stocks.forEach(stock => {
            const btn = $('<button>', {
                text: `${stock.name} (${stock.code})`,
                css: {
                    display: 'block',
                    width: '100%',
                    margin: '5px 0'
                },
                click: function() {
                    nameInput.value = stock.name;
                    codeInput.value = stock.code;
                    popup.remove(); // 선택 시 팝업 닫기
                }
            });
            popup.append(btn);
        });

        const closeBtn = $('<button>', {
            text: '닫기',
            css: {
                display: 'block',
                width: '100%',
                margin: '5px 0'
            },
            click: function() {
                popup.remove();
            }
        });
        popup.append(closeBtn);

        $('body').append(popup);
    }

    // Search stock function
    async function searchStock(event, input) {
        if (event.key === 'Enter') { // 엔터 키가 눌렸을 때만 처리
            event.preventDefault(); // 폼 제출 방지
            const query = input.value.trim();
            const codeInput = input.nextElementSibling; // 코드 입력 필드
            if (query.length > 0) { // 검색어가 비어있지 않은 경우에만 검색 수행
                const stocks = await fetchStocks(query);
                if (stocks.length === 1) {
                    input.value = stocks[0].name; // 입력 칸에 종목명 설정
                    codeInput.value = stocks[0].code; // 코드 설정
                } else if (stocks.length > 1) {
                    showStockPopup(stocks, input, codeInput);
                } else {
                    alert("해당 종목명을 찾을 수 없습니다.");
                }
            }
        }
    }

    // 뉴스 스크랩 기능
    async function scrapNews(theme) {
        const url = document.getElementById('news_url_' + theme).value;
        try {
            const response = await fetch(`get_title.php?url=${encodeURIComponent(url)}`);
            const data = await response.json();
            if (data.success) {
                alert(`제목: ${data.title} | URL: ${data.url}`);
                // 데이터베이스 저장 처리
                const formData = new FormData();
                formData.append('title', data.title);
                formData.append('url', data.url);
                formData.append('theme', theme);

                fetch('save_news.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json()).then(result => {
                    if (result.success) {
                        alert('뉴스가 저장되었습니다.');
                        location.reload(); // 새로고침
                    } else {
                        alert('뉴스 저장에 실패했습니다.');
                    }
                });
            } else {
                alert('제목을 가져오지 못했습니다.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('뉴스 스크랩 중 오류가 발생했습니다.');
        }
    }
</script>
