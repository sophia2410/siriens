if ($action === 'register' || $action === 'update') {
    // 등록 및 수정 로직
    $date = str_replace('-', '', $selected_date);
    $issue = $_POST['issue'] ?? '';
    $first_occurrence = isset($_POST['new_issue']) ? 'Y' : 'N';
    $link = $_POST['link'] ?? '';
    $sector = $_POST['sector'] ?? '';
    $theme = $_POST['theme'] ?? '';
    $hot_theme = isset($_POST['hot_theme']) ? 'Y' : 'N';
    $group_id = handleKeywords($mysqli, $_POST['keyword'] ?? '');

    if ($action === 'register') {
        // 오늘의 market_issues 테이블에 이미 해당 키워드 그룹이 등록되어 있는지 확인
        $stmt = $mysqli->prepare("SELECT issue_id FROM market_issues WHERE date = ? AND keyword_group_id = ?");
        $stmt->bind_param('si', $date, $group_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // 이미 등록된 키워드 그룹이 있으면 해당 issue_id 사용
            $issue_id = $row['issue_id'];
        } else {
            // 등록된 키워드 그룹이 없으면 새로 market_issues에 등록
            $issue_id = insertOrUpdateIssue($mysqli, $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id);
        }
    } elseif ($action === 'update') {
        // 업데이트 로직
        $issue_id = $_POST['issue_id'];
        updateIssue($mysqli, $issue_id, $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id);
        deleteStocks($mysqli, $issue_id); // 기존 종목 삭제
    }

    // 종목 추가 처리
    handleStocks($mysqli, $issue_id, $_POST['stocks'] ?? [], $date);

    // 커밋
    $mysqli->commit();

    // 미사용 키워드 삭제
    try {
        // 저장 프로시저 호출
        $mysqli->query("CALL p_cleanup_unused_keywords();");
    
        // 커밋
        if (!$mysqli->commit()) {
            throw new Exception("Commit failed: " . $mysqli->error);
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        echo "Error: " . $e->getMessage();
    }
}
