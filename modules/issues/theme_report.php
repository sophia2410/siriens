<?php
$pageTitle = "테마 및 데이터 보기"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php"); // 공통 헤더 사용
?>

<body>
<div id="container">
    <div id="content-area">
        <div class="tab-container">
            <div class="tab" data-url="theme_report_flex.php">플렉스박스 보기</div>
            <div class="tab active" data-url="theme_report_table.php">테이블 보기</div>
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

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // 모든 탭에서 active 클래스 제거
                tabs.forEach(t => t.classList.remove('active'));

                // 클릭된 탭에 active 클래스 추가
                tab.classList.add('active');

                // 클릭된 탭의 데이터를 iframe에 로드
                const url = tab.dataset.url;
                contentFrame.src = url;
            });
        });
    });
</script>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"); // 공통 푸터 사용
?>
</body>
</html>
