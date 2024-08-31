<?php
$pageTitle = "이슈 수정/등록"; // 팝업 창의 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header_sub.php");

require($_SERVER['DOCUMENT_ROOT']."/modules/issues/issue_register_form.php");

// GET 파라미터로 넘어온 값 처리
$issueId = $_GET['issue_id'] ?? null;
$dateParam = $_GET['date'] ?? date('Y-m-d');
$issueParam = $_GET['issue'] ?? '';
$newIssueParam = $_GET['new_issue'] ?? '';
?>

<head>
    <!-- 페이지 전용 스타일 -->
    <style>
        #issue_register_container {
            padding: 20px;
            background-color: #f8f8f8;
        }
    </style>
</head>

<body>
    <div id="issue_register_container">
        <?php render_issue_register_form($dateParam, $issueParam, $newIssueParam, $issueId); ?>
    </div>

    <script>
        function closePopupAndReloadParent() {
            if (window.opener) {
                window.opener.location.reload(); // 부모 창 리로드
            }
            window.close(); // 팝업 창 닫기
        }

        function IssueRegisterPopup_Initialize(){
            console.log("이슈 등록 팝업 초기화");
            // 폼 제출 시 이 함수가 호출되도록 처리
            const form = document.querySelector('#issue_register_container form');
            form.addEventListener('submit', function() {
                // 폼이 제출되면 closePopupAndReloadParent 호출
                setTimeout(closePopupAndReloadParent, 100); // 팝업을 바로 닫지 않고 잠시 후에 닫음
            });
        }
    </script>
<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
</body>
</html>
