<?php
$pageTitle = "이슈 등록"; // 페이지 타이틀 설정
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT'] . "/modules/market/event_register_form.php");

// GET 파라미터로 선택된 날짜를 받아옵니다. 없으면 현재 날짜로 설정
$dateParam = $_GET['date'] ?? date('Y-m-d');

// 마켓 이슈 불러오기
$issueQuery = $mysqli->prepare("
    SELECT mi.*, kg.group_name 
    FROM market_issues mi 
    LEFT JOIN keyword_groups kg 
    ON mi.issue_id = kg.group_id 
    WHERE mi.date = ?
    ORDER BY kg.group_name ASC
");
$issueQuery->bind_param('s', $dateParam);
$issueQuery->execute();
$issueResult = $issueQuery->get_result();

?>

<head>
    <style>
        #container {
            display: flex;
            height: 100vh; /* 전체 화면 높이를 활용 */
            flex-direction: row;
        }

        #issue_register_container {
            flex: 1;
            background-color: #f5f5f5;
            padding: 20px;
            overflow: auto;
        }

        #issue_register_container input[type="text"],
        #issue_register_container input[type="url"],
        #issue_register_container textarea {
            width: 100%; /* 모든 입력 필드를 100% 너비로 설정 */
            box-sizing: border-box; /* padding과 border를 포함한 크기 계산 */
        }

        #issue_register_container button {
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #d9534f;
            color: white;
            border: none;
            border-radius: 5px;
        }

        /* 조회 폼을 2 비율로 설정 */
        #issue_list_container {
            flex: 2;
            padding: 20px;
            overflow-y: auto;
        }

        .issue-card {
            padding: 15px;
            background-color: #f8f8f8;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }

        .issue-title {
            font-weight: bold;
            font-size: 1.2em;
        }

        .issue-content {
            margin-top: 10px;
            font-size: 1em;
        }

        .issue-link {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
            display: inline-block;
        }

        /* .issue-link:hover {
            white-space: normal;
        } */

        .issue-keywords {
            color: #333;
            font-weight: bold;
            margin-top: 10px;
        }

        .issue-keywords span {
            background-color: #e0e7ff;
            padding: 5px;
            border-radius: 4px;
            margin-right: 5px;
            display: inline-block;
        }
    </style>
</head>

<body>
    <div id="container">
        <!-- 이슈 등록 폼 -->
        <div id="issue_register_container">
            <h2>이슈 등록 (<?= htmlspecialchars($dateParam); ?>)</h2>
            <!-- 날짜 선택 폼 -->
            <form id="dateForm" action="issue_register.php" method="GET">
                <label for="issue_date">이슈 일자:</label>
                <input type="date" id="issue_date" name="date" value="<?= htmlspecialchars($dateParam); ?>" required onchange="handleDateChange(this.value)">
            </form>

            <!-- 이슈 등록 폼 -->
            <form id="issueForm" action="issue_process.php" method="POST">
                <input type="hidden" id="issue_id" name="issue_id">
                <input type="hidden" id="issue_date_hidden" name="issue_date" value="<?= htmlspecialchars($dateParam); ?>">

                <label for="issue_title">이슈 제목:</label>
                <input type="text" id="issue_title" name="issue_title" required autocomplete="off">

                <label for="issue_link">이슈 링크:</label>
                <input type="url" id="issue_link" name="issue_link" autocomplete="off">

                <label for="issue_content">이슈 내용:</label>
                <textarea id="issue_content" name="issue_content" rows="4"></textarea>

                <label for="issue_keywords">키워드:</label>
                <input type="text" id="issue_keywords" name="issue_keywords" placeholder="#키워드1 #키워드2" autocomplete="off">

                <!-- 수정 모드에서는 등록 버튼 숨김 -->
                <button type="submit" id="submit_button">등록</button>
                <button type="button" id="update_button" style="display:none;" onclick="updateIssue()">수정</button>
                <button type="button" id="delete_button" style="display:none;" onclick="deleteIssue()">삭제</button>
                <button type="button" id="reset_button" style="display:none;" onclick="resetForm()">초기화</button>
            </form>
        </div>

        <!-- 이슈 리스트 -->
        <div id="issue_list_container">
            <?php while ($issue = $issueResult->fetch_assoc()): ?>
                <div class="issue-card" onclick="loadIssueData(<?= $issue['issue_id']; ?>)">
                <div class="issue-title"><?= htmlspecialchars($issue['issue_title'], ENT_QUOTES | ENT_HTML401); ?></div>
                <p>링크: <a href="<?= htmlspecialchars($issue['issue_link'], ENT_QUOTES | ENT_HTML401); ?>" target="_blank" class="issue-link">
                    <?= htmlspecialchars($issue['issue_link'], ENT_QUOTES | ENT_HTML401); ?>
                </a></p>
                <p class="issue-content"><?= nl2br(htmlspecialchars($issue['issue_content'], ENT_QUOTES | ENT_HTML401)); ?></p>
                <p class="issue-keywords">
                    <?php foreach (Utility_GgetIssueKeywords($dateParam, $issue['issue_id']) as $keyword): ?>
                        <span>#<?= htmlspecialchars($keyword['keyword'], ENT_QUOTES | ENT_HTML401); ?></span>
                    <?php endforeach; ?>
                </p>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
<script>
    let isEditMode = false;

    function loadIssueData(issueId) {
        // AJAX 요청으로 데이터를 가져옴
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "fetch_issue.php?issue_id=" + issueId, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                var issue = JSON.parse(xhr.responseText);

                // 폼 필드에 데이터 채우기
                document.getElementById('issue_id').value = issue.issue_id;
                document.getElementById('issue_title').value = issue.issue_title;
                document.getElementById('issue_link').value = issue.issue_link;
                document.getElementById('issue_content').value = issue.issue_content;
                document.getElementById('issue_keywords').value = issue.keywords;
                document.getElementById('issue_date_hidden').value = issue.date;  
                document.getElementById('issue_date').value = issue.date;  

                // 수정, 삭제 버튼 보이기
                document.getElementById('submit_button').style.display = 'none'; // 등록 버튼 숨김
                document.getElementById('update_button').style.display = 'inline-block';
                document.getElementById('delete_button').style.display = 'inline-block';
                document.getElementById('reset_button').style.display = 'inline-block';

                isEditMode = true; // 수정 모드로 전환
            }
        };
        xhr.send();
    }

    function resetForm() {
        // 폼의 모든 입력 필드를 초기화
        document.getElementById('issue_id').value = '';
        document.getElementById('issue_title').value = '';
        document.getElementById('issue_link').value = '';
        document.getElementById('issue_content').value = '';
        document.getElementById('issue_keywords').value = '';
        document.getElementById('issue_date_hidden').value = '';  // 히든 필드 초기화

        // 수정, 삭제 버튼 숨기고 등록 버튼 보이기
        document.getElementById('submit_button').style.display = 'inline-block';
        document.getElementById('update_button').style.display = 'none';
        document.getElementById('delete_button').style.display = 'none';
        document.getElementById('reset_button').style.display = 'none';

        isEditMode = false; // 등록 모드로 전환

        // 화면 리로드
        document.getElementById('dateForm').submit();
    }

    function updateIssue() {
        document.getElementById('issueForm').action = "issue_process.php?action=update";
        document.getElementById('issueForm').submit();
    }

    function deleteIssue() {
        if (confirm('정말로 이 이슈를 삭제하시겠습니까?')) {
            document.getElementById('issueForm').action = "issue_process.php?action=delete";
            document.getElementById('issueForm').submit();
        }
    }

    // 날짜 변경 시 화면 리로드를 방지하거나 허용하는 함수
    function handleDateChange(newDate) {
        if (isEditMode) {
            // 수정 모드에서는 화면 리로드 없이 히든 필드에만 값 반영
            document.getElementById('issue_date_hidden').value = newDate;
        } else {
            // 등록 모드에서는 화면 리로드
            document.getElementById('dateForm').submit();
        }
    }
</script>
</body>
