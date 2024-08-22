<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>테마 및 데이터 보기</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        /* 탭 스타일 */
        .tab-container {
            display: flex;
            background-color: #ddd;
            border-bottom: 2px solid #444;
        }

        .tab {
            padding: 5px 5px;
            cursor: pointer;
            border: 1px solid #444;
            border-bottom: none;
            background-color: #ddd;
        }

        .tab.active {
            background-color: #fff;
            border-bottom: 2px solid #fff;
        }

        /* 컨텐츠 영역 */
        .content {
            padding: 5px;
            background-color: #fff;
        }

        iframe {
            width: 100%;
            height: 95vh;
            border: none;
        }
    </style>
</head>
<body>

<div class="tab-container">
    <div class="tab active" data-url="market_theme_report_flex.php">플렉스박스 보기</div>
    <div class="tab" data-url="market_theme_report_table.php">테이블 보기</div>
</div>

<div class="content">
    <iframe id="contentFrame" src="market_theme_report_flex.php"></iframe>
</div>

<script>
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
</script>

</body>
</html>
