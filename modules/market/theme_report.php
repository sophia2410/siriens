<?php
$pageTitle = "테마 및 데이터 보기"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php"); // 공통 헤더 사용
?>

<body>
<div id="container">
    <div id="content-area">
        <!-- 조회 조건과 탭을 한 줄에 배치 -->
        <div class="filter-tab-container">
            <div class="filters">
                <label for="queryDate">조회일자:</label>
                <input type="date" id="queryDate" value="<?php echo date('Y-m-d'); ?>" />
                
                <label for="stockCriteria">종목조회기준:</label>
                <select id="stockCriteria">
                    <option value="all">전체</option>
                    <option value="hot">Hot</option>
                </select>
            </div>

            <div class="tab-container">
                <div class="tab" data-url="theme_report_flex.php">플렉스박스 보기</div>
                <div class="tab active" data-url="theme_report_table.php">테이블 보기</div>
            </div>
        </div>

        <div class="tab-content">
            <iframe id="contentFrame" src="theme_report_table.php"></iframe>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab');
        const contentFrame = document.getElementById('contentFrame');
        const queryDate = document.getElementById('queryDate');
        const stockCriteria = document.getElementById('stockCriteria');
        let currentTabUrl = document.querySelector('.tab.active').dataset.url;

        function updateFrame() {
            const url = `${currentTabUrl}?date=${queryDate.value}&criteria=${stockCriteria.value}`;
            contentFrame.src = url;
        }

        // 탭을 클릭할 때마다 iframe URL 업데이트
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // 모든 탭에서 active 클래스 제거
                tabs.forEach(t => t.classList.remove('active'));

                // 클릭된 탭에 active 클래스 추가
                tab.classList.add('active');

                // 클릭된 탭의 URL 저장 및 iframe 업데이트
                currentTabUrl = tab.dataset.url;
                updateFrame();
            });
        });

        // 조회 조건이 변경되었을 때 iframe URL 업데이트
        queryDate.addEventListener('change', updateFrame);
        stockCriteria.addEventListener('change', updateFrame);
    });
</script>

<style>
    /* 조회 조건과 탭을 한 줄에 배치 */
    .filter-tab-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .filters {
        display: flex;
        align-items: center;
        flex-grow: 1;
    }

    .filters label {
        padding: 10px;
        white-space: nowrap;
    }

    .filters input,
    .filters select {
        margin-right: 20px;
        padding: 5px;
    }

    .tab-container {
        display: flex;
        background-color: #e0e0e0;
        border-bottom: 2px solid #444;
        flex-grow: 4;
    }

    .tab {
        padding: 10px 20px;
        cursor: pointer;
        border: 1px solid #ccc;
        border-bottom: none;
        background-color: #e0e0e0;
        transition: background-color 0.3s ease, color 0.3s ease;
        flex: 1;
        text-align: center;
        font-size: 16px;
    }

    .tab:hover {
        background-color: #d0d0d0;
    }

    .tab.active {
        background-color: #fff;
        border-bottom: 2px solid #fff;
        font-weight: bold;
        color: #333;
    }

    iframe {
        width: 100%;
        height: 600px;
        border: none;
    }
</style>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"); // 공통 푸터 사용
?>
</body>
</html>
