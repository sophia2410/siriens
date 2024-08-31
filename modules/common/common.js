document.addEventListener('DOMContentLoaded', function() {
    // 공통 초기화 작업
    Common_InitializeCommonFeatures();

    // 페이지별 초기화 함수 호출

    // issue_register.php
    if (typeof IssueRegister_Initialize === 'function') {
        IssueRegister_Initialize();
    }
    // issue_register_form.php
    if (typeof IssueRegisterForm_Initialize === 'function') {
        IssueRegisterForm_Initialize();
    }
    // issue_register_popup.php
    if (typeof IssueRegisterPopup_Initialize === 'function') {
        IssueRegisterPopup_Initialize();
    }
    // issue_list.php
    if (typeof IssueList_Initialize === 'function') {
        IssueList_Initialize();
    }

    // stock_issue_list.php
    if (typeof StockIssueList_Initialize === 'function') {
        StockIssueList_Initialize();
    }

    // stock_issue_list.php
    if (typeof ThemeReport_Initialize === 'function') {
        ThemeReport_Initialize();
    }

});

function Common_InitializeCommonFeatures() {
    console.log("공통 초기화 작업 실행");
    // 예: 날짜 선택기 초기화, 공통 이벤트 핸들러 설정 등
}

// 종목 검색
async function Common_SearchStock(event, input, callback = null) {
    if (event.key === 'Enter') { // 엔터 키가 눌렸을 때만 처리
        event.preventDefault(); // 폼 제출 방지
        const query = input.value.trim();
        const codeInput = input.nextElementSibling; // 코드 입력 필드
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

async function Common_FetchStocks(query) {
    try {
        const response = await fetch(`fetch_autocomplete.php?type=stocks&q=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error("Error fetching stocks:", error);
        return [];
    }
}

function Common_ShowStockPopup(stocks, nameInput, codeInput = null, sectorInput = null, callback = null) {
    const popup = document.createElement('div');
    popup.style.position = 'fixed';
    popup.style.top = '50%';
    popup.style.left = '50%';
    popup.style.transform = 'translate(-50%, -50%)';
    popup.style.backgroundColor = '#fff';
    popup.style.padding = '20px';
    popup.style.boxShadow = '0 0 10px rgba(0, 0, 0, 0.5)';
    popup.style.zIndex = 1000;
    popup.style.maxHeight = '400px';
    popup.style.overflowY = 'auto';

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

// 아코디언 스타일
function Common_SetupAccordionToggle(toggleButtonId, contentSelector) {
    const toggleButton = document.getElementById(toggleButtonId);
    const allContents = document.querySelectorAll(contentSelector);
    let isExpanded = true; // 초기 상태는 펼쳐진 상태

    if (!toggleButton) return; // 토글 버튼이 없으면 함수 종료

    toggleButton.addEventListener('click', function() {
        isExpanded = !isExpanded; // 토글 상태 업데이트
        allContents.forEach(function(content) {
            content.style.display = isExpanded ? 'table-row' : 'none';
        });
        toggleButton.textContent = isExpanded ? '모든 항목 접기' : '모든 항목 펼치기'; // 버튼 텍스트 업데이트
    });
}