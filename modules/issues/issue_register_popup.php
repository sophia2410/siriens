<?php
$pageTitle = "이슈 수정/등록"; // 팝업 창의 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header_popup.php");

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

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
</body>
</html>
