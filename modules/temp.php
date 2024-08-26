// 종목 검색
async function Common_SearchStock(event, input, callback = null) {
    if (event.key === 'Enter') { // 엔터 키가 눌렸을 때만 처리
        event.preventDefault(); // 폼 제출 방지
        const query = input.value.trim();
        const codeInput = document.getElementById('stock_code_input'); // 코드 입력 필드
        const sectorInput = codeInput ? codeInput.nextElementSibling : null; // 섹터 입력 필드

        if (query.length > 0) { // 검색어가 비어있지 않은 경우에만 검색 수행
            const stocks = await Common_FetchStocks(query);
            if (stocks.length === 1) {
                input.value = stocks[0].name; // 입력 칸에 종목명 설정
                if (codeInput) codeInput.value = stocks[0].code; // 코드 설정
                if (sectorInput) sectorInput.value = stocks[0].sector; // 섹터 설정 (옵션)
                if (callback) callback(); // 콜백 함수 호출
            } else if (stocks.length > 1) {
                Common_ShowStockPopup(stocks, input, codeInput, sectorInput, callback);
            } else {
                alert("해당 종목명을 찾을 수 없습니다.");
            }
        }
    }
}

function Common_ShowStockPopup(stocks, nameInput, codeInput = null, sectorInput = null, callback = null) {
    const popup = document.createElement('div');
    // 팝업 생성 및 설정...
    stocks.forEach(stock => {
        const btn = document.createElement('button');
        btn.innerText = `${stock.name} (${stock.code})`;
        btn.style.display = 'block';
        btn.style.width = '100%';
        btn.style.margin = '5px 0';
        btn.onclick = () => {
            nameInput.value = stock.name;
            if (codeInput) codeInput.value = stock.code;
            if (sectorInput) sectorInput.value = stock.sector;
            document.body.removeChild(popup); // 선택 시 팝업 닫기
            if (callback) callback(); // 콜백 함수 호출
        };
        popup.appendChild(btn);
    });

    const closeBtn = document.createElement('button');
    closeBtn.innerText = '닫기';
    closeBtn.style.display = 'block';
    closeBtn.style.width = '100%';
    closeBtn.style.margin = '5px 0';
    closeBtn.onclick = () => document.body.removeChild(popup);

    popup.appendChild(closeBtn);
    document.body.appendChild(popup);
}
