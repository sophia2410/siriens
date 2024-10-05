<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issueId = $_POST['issue_id'] ?? null;
    $issueTitle = $_POST['issue_title'];
    $issueLink = $_POST['issue_link'] ?? '';
    $issueContent = $_POST['issue_content'] ?? '';
    $issueDate = $_POST['issue_date'] ?? '';  // issue_date_hidden에서 전달받은 값
    $keywords_input = htmlspecialchars($_POST['issue_keywords'], ENT_QUOTES | ENT_HTML401);  // 특수 문자 처리 추가


    // action 값이 존재하는지 확인
    $action = isset($_GET['action']) ? $_GET['action'] : 'insert';

    if (empty($issueDate)) {
        // 만약 날짜 값이 없으면 오류를 출력
        die('Error: 날짜 값이 비어있습니다.');
    }

    $mysqli->autocommit(FALSE);

    try {
        if ($action === 'update' && $issueId) {
            // 이슈 수정 처리
            $issueStmt = $mysqli->prepare("UPDATE market_issues SET issue_title = ?, issue_link = ?, issue_content = ?, date = ? WHERE issue_id = ?");
            $issueStmt->bind_param('ssssi', $issueTitle, $issueLink, $issueContent, $issueDate, $issueId);
            $issueStmt->execute();

            // 기존 키워드 삭제
            $deleteKeywordStmt = $mysqli->prepare("DELETE FROM keyword_issue_mappings WHERE issue_id = ?");
            $deleteKeywordStmt->bind_param('i', $issueId);
            $deleteKeywordStmt->execute();
            
            // 새 키워드 처리 (등록과 동일하게)
            handleKeywords($keywords_input, $issueId);

        } elseif ($action === 'delete' && $issueId) {
            // 기존 키워드 삭제
            $deleteKeywordStmt = $mysqli->prepare("DELETE FROM keyword_issue_mappings WHERE issue_id = ?");
            $deleteKeywordStmt->bind_param('i', $issueId);
            $deleteKeywordStmt->execute();
            
            // 이슈 삭제 처리
            $issueStmt = $mysqli->prepare("DELETE FROM market_issues WHERE issue_id = ?");
            $issueStmt->bind_param('i', $issueId);
            $issueStmt->execute();

        } else {
            // 이슈 등록 처리
            $issueStmt = $mysqli->prepare("INSERT INTO market_issues (issue_title, issue_link, issue_content, date) VALUES (?, ?, ?, ?)");
            $issueStmt->bind_param('ssss', $issueTitle, $issueLink, $issueContent, $issueDate);
            $issueStmt->execute();
            $issueId = $mysqli->insert_id;

            // 키워드 처리
            handleKeywords($keywords_input, $issueId);
        }
        $mysqli->commit();

        // 미사용 키워드 삭제
        try {
            $mysqli->query("CALL p_cleanup_unused_keywords();");
        
            // 커밋
            if (!$mysqli->commit()) {
                throw new Exception("Commit failed: " . $mysqli->error);
            }
        } catch (Exception $e) {
            $mysqli->rollback();
            echo "Error: " . $e->getMessage();
        }

        header("Location: issue_register.php?date=$issueDate");
        exit;

    } catch (Exception $e) {
        $mysqli->rollback();
        echo "Error: " . $e->getMessage();
    }

    $mysqli->autocommit(TRUE);
}

function handleKeywords($keywords_input, $issueId) {
    global $mysqli;

    $keywords = array_filter(array_map('trim', explode('#', $keywords_input)));

    foreach ($keywords as $keyword) {
        if (!empty($keyword)) {
            $keywordStmt = $mysqli->prepare("SELECT keyword_id FROM keyword WHERE keyword = ?");
            $keywordStmt->bind_param('s', $keyword);
            $keywordStmt->execute();
            $keywordResult = $keywordStmt->get_result();

            if ($keywordResult->num_rows == 0) {
                $insertKeywordStmt = $mysqli->prepare("INSERT INTO keyword (keyword) VALUES (?)");
                $insertKeywordStmt->bind_param('s', $keyword);
                $insertKeywordStmt->execute();
                $keyword_id = $insertKeywordStmt->insert_id;
            } else {
                $row = $keywordResult->fetch_assoc();
                $keyword_id = $row['keyword_id'];
            }

            $mappingStmt = $mysqli->prepare("INSERT INTO keyword_issue_mappings (issue_id, keyword_id) VALUES (?, ?)");
            $mappingStmt->bind_param('ii', $issueId, $keyword_id);
            $mappingStmt->execute();
        }
    }
}
