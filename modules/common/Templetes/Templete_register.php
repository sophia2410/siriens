<?php
$pageTitle = "생각 등록 관리";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

// GET 파라미터로 선택된 카테고리를 받아옵니다. 없으면 전체 조회
$categoryParam = $_GET['category'] ?? '';
$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $perPage;

// 카테고리 목록 불러오기
$categoryQuery = "SELECT cd, nm FROM comm_cd WHERE l_cd = 'TH000' ORDER BY cd";
$categories = $mysqli->query($categoryQuery);

?>
<head>
</head>

<body>
    <div id="container"> <!-- 메뉴바 구성을 위해 꼭 사용되어야 할 div -->
    </div>
</body>

/* 기본적인 스타일 - 공통모듈*/
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body, html {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
    font-family: Arial, sans-serif;
    background-color: #f9f9f9;
    height: 100vh;
    color: #858796;
}

/* 컨테이너 설정 */
#container {
    display: flex;
    height: 100vh;
    margin-left: 100px !important;
    flex-direction: row;
    width: calc(100% - 100px);
    padding: 0;
    box-sizing: border-box;
}

/* 테이블 스타일 */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 14px;
}

th, td {
    border: 1px solid #ddd;
    padding: 8px;
}

th {
    background-color: #f2f2f2;
}

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
</body>
</html>