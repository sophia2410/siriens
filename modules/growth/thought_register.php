<?php
$pageTitle = "생각 등록 관리";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/tinymce_module.php");

// GET 파라미터로 선택된 카테고리를 받아옵니다. 없으면 전체 조회
$categoryParam = $_GET['category'] ?? '';
$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $perPage;

// 카테고리 목록 불러오기
$categoryQuery = "SELECT cd, nm FROM comm_cd WHERE l_cd = 'TH000' ORDER BY cd";
$categories = $mysqli->query($categoryQuery);

// 카테고리 필터링 및 생각 목록 불러오기
$filterQuery = '';
if ($categoryParam) {
    $filterQuery = "WHERE t.category_cd = '$categoryParam'";
}

$thoughtsQuery = "
    SELECT t.id, c.nm AS category_name, t.thought_text, t.create_date
    FROM thoughts t
    JOIN comm_cd c ON t.category_cd = c.cd
    $filterQuery
    ORDER BY t.create_date DESC
    LIMIT $start, $perPage
";
$thoughts = $mysqli->query($thoughtsQuery);

// 전체 생각 개수 구하기 (페이지네이션 처리용)
$countQuery = "
    SELECT COUNT(*) AS total
    FROM thoughts t
    $filterQuery
";
$countResult = $mysqli->query($countQuery)->fetch_assoc();
$totalThoughts = $countResult['total'];
$totalPages = ceil($totalThoughts / $perPage);
?>

<head>
    <style>
        #thought_register_container {
            flex: 1;
            background-color: #f9f9f9;
            padding: 20px;
            margin-right: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        #thought_register_container input[type="text"],
        #thought_register_container textarea {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        #thought_list_container {
            flex: 1;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column; /* 세로 정렬 */
            height: 99%; /* 전체 높이 설정 */
        }

        .thought-list-wrapper {
            flex-grow: 1; /* 카드 목록이 남은 공간을 차지하도록 설정 */
            overflow-y: auto; /* 세로 스크롤 활성화 */
            margin-bottom: 10px; /* 페이지네이션과 여백 추가 */
        }

        .thought-card {
            padding: 20px;
            margin-bottom: 20px;
            background-color: #f8f8f8;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }

        .thought-title {
            font-size: 1.2em;
            font-weight: bold;
        }

        .thought-content {
            margin-top: 10px;
        }

        /* 페이지네이션 스타일 */
        .pagination {
            text-align: center;
            margin-top: 10px;
            padding: 10px 0; /* 고정 위치를 위한 패딩 */
            background: #fff; /* 페이지네이션 배경 */
            border-top: 1px solid #ddd; /* 구분선 추가 */
            position: sticky; /* 화면 하단에 고정 */
            bottom: 0; /* 하단 고정 위치 */
        }

        .pagination a {
            padding: 10px 15px;
            margin: 0 5px;
            background-color: #f1f1f1;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background-color: #ddd;
        }

        .pagination a.active {
            background-color: #d9534f;
            color: white;
        }

        /* 수정, 삭제 버튼 */
        #edit_buttons {
            display: none;
            margin-top: 20px;
        }

        #edit_buttons button {
            padding: 10px 20px;
            background-color: #5bc0de;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        #edit_buttons button.delete {
            background-color: #d9534f;
        }
    </style>
</head>

<body>
    <div id="container">
        <!-- 생각 등록 폼 -->
        <div id="thought_register_container">
            <h2>생각 등록</h2>
            <form id="thoughtForm" action="thought_process.php" method="POST" onsubmit="return validateForm();">
                <input type="hidden" id="thought_id" name="thought_id">
                <input type="hidden" name="page" value="<?php echo $page; ?>"> <!-- 페이지 정보 추가 -->

                <label for="category">카테고리:</label>
                <select name="category" id="category" onchange="onCategoryChange()" required>
                    <option value="">카테고리 선택</option>
                    <?php while ($row = $categories->fetch_assoc()) { ?>
                        <option value="<?php echo $row['cd']; ?>" <?php echo ($row['cd'] == $categoryParam) ? 'selected' : ''; ?>><?php echo $row['nm']; ?></option>
                    <?php } ?>
                </select>

                <label for="thought">생각 내용:</label>
                <textarea name="thought" id="thought" rows="4"></textarea>

                <button type="submit" id="register_button">등록</button>
            </form>

            <!-- TinyMCE 적용 -->
            <?php loadTinyMCE('#thought',800); ?>
            <?php loadTinyMCEScripts(); ?> <!-- 공통 스크립트 로딩 -->

            <!-- 수정, 삭제 버튼 -->
            <div id="edit_buttons">
                <button type="button" onclick="updateThought()">수정</button>
                <button type="button" class="delete" onclick="deleteThought()">삭제</button>
                <button type="button" class="reset" style="float: right;" onclick="resetForm()">초기화</button>
            </div>
        </div>

        <!-- 생각 리스트 -->
        <div id="thought_list_container">
            <h2>카테고리별 생각 목록</h2>
            <!-- 카드 목록을 스크롤 영역으로 감싸기 -->
            <div class="thought-list-wrapper">
                <?php while ($row = $thoughts->fetch_assoc()) { ?>
                    <div class="thought-card" onclick="loadThoughtData(<?= $row['id']; ?>)">
                        <div class="thought-title"><?php echo $row['category_name']; ?></div>
                        <div class="thought-content"><?php echo $row['thought_text']; ?> (<?php echo $row['create_date']; ?>)</div>
                    </div>
                <?php } ?>
            </div>

            <!-- 페이지네이션 -->
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++) { ?>
                    <a href="?category=<?php echo htmlspecialchars($categoryParam); ?>&page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>

<script>
    // 카테고리 변경 시 동작하는 함수
    function onCategoryChange() {
        var thoughtId = document.getElementById('thought_id').value;

        if (thoughtId === "") {
            // 신규 등록 모드일 때는 페이지를 리로드하여 카테고리 필터 적용
            var category = document.getElementById('category').value;
            location.href = '?category=' + category;
        } else {
            // 수정 모드일 때는 리로드하지 않고 카테고리만 폼에 반영
            // 카테고리 값만 변경된 상태에서 저장할 수 있음
            console.log("수정 모드에서 카테고리만 변경됨.");
        }
    }

    // 수정된 데이터를 TinyMCE에 로드하는 부분
    function loadThoughtData(thoughtId) {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "fetch_thought.php?thought_id=" + thoughtId + "&type=thought", true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                var thought = JSON.parse(xhr.responseText);
                document.getElementById('thought_id').value = thought.id;
                document.getElementById('category').value = thought.category_cd;
                
                // TinyMCE 에디터에 데이터 설정
                setTinyMCEContent('thought', thought.thought_text);

                // 수정 및 삭제 버튼 보이기
                document.getElementById('edit_buttons').style.display = 'block';
                document.getElementById('register_button').style.display = 'none'; // 등록 버튼 숨기기
            }
        };
        xhr.send();
    }

    // 폼 유효성 검사 및 제출 전 TinyMCE 내용 동기화
    function validateForm() {
        return validateTinyMCEForm('thought');
    }

    function updateThought() {
        // TinyMCE 에디터의 값을 동기화
        syncTinyMCEData();
        document.getElementById('thoughtForm').submit();
    }

    function deleteThought() {
        if (confirm('정말로 이 생각을 삭제하시겠습니까?')) {
            var thoughtId = document.getElementById('thought_id').value;
            var category = document.getElementById('category').value;
            var page = new URLSearchParams(window.location.search).get('page') || 1;

            // 삭제 시 카테고리와 페이지 정보를 함께 전달
            window.location.href = "thought_process.php?action=delete&thought_id=" + thoughtId + "&category=" + category + "&page=" + page;
        }
    }

// 등록 모드로 초기화
function resetForm() {
    var category = document.getElementById('category').value;
    var page = new URLSearchParams(window.location.search).get('page') || 1;
    
    // 카테고리와 페이지를 유지하면서 현재 페이지로 리디렉트
    window.location.href = "?category=" + category + "&page=" + page;
}

</script>
</body>
