<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php");

$groupId = $_GET['group_id'];
$newKeywords = $_GET['new_keywords'];

if ($groupId && $newKeywords) {
    // 새로운 키워드로 업데이트
    $newKeywordsArray = array_unique(array_filter(array_map('trim', explode('#', $newKeywords))));

    // 그룹 이름 생성
    $newGroupName = '#' . implode(' #', $newKeywordsArray);

    // 동일한 그룹 이름이 이미 존재하는지 확인
    $stmt = $mysqli->prepare("SELECT group_id FROM keyword_groups WHERE group_name = ?");
    $stmt->bind_param('s', $newGroupName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // 동일한 그룹 이름이 존재하면 그 group_id로 기존 market_events 업데이트
        $existingGroupId = $row['group_id'];

        // market_events의 keyword_group_id를 기존 그룹 ID로 업데이트
        $stmt = $mysqli->prepare("UPDATE market_events SET keyword_group_id = ? WHERE keyword_group_id = ?");
        $stmt->bind_param('ii', $existingGroupId, $groupId);
        $stmt->execute();

        // 기존 그룹 매핑 삭제
        $stmt = $mysqli->prepare("DELETE FROM keyword_group_mappings WHERE group_id = ?");
        $stmt->bind_param('i', $groupId);
        $stmt->execute();

        // 원래 있던 keyword_group_id 삭제
        $stmt = $mysqli->prepare("DELETE FROM keyword_groups WHERE group_id = ?");
        $stmt->bind_param('i', $groupId);
        $stmt->execute();

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

        // 완료 후 기존 그룹으로 리다이렉트
        header("Location: keyword_group_list.php?group_id=$existingGroupId&keyword=" . urlencode($newKeywords));
        exit();

    } else {
        // 동일한 그룹 이름이 없으면 현재 그룹에 업데이트 진행
        $stmt = $mysqli->prepare("UPDATE keyword_groups SET group_name = ? WHERE group_id = ?");
        $stmt->bind_param('si', $newGroupName, $groupId);
        $stmt->execute();

        // 기존 키워드 그룹과 연결된 키워드 제거
        $stmt = $mysqli->prepare("DELETE FROM keyword_group_mappings WHERE group_id = ?");
        $stmt->bind_param('i', $groupId);
        $stmt->execute();

        // 새로운 키워드들을 삽입 및 매핑 처리 (필요한 경우)
        foreach ($newKeywordsArray as $keyword) {
            // 키워드가 이미 존재하는지 확인 및 필요 시 삽입
            $stmt = $mysqli->prepare("SELECT keyword_id FROM keyword WHERE keyword = ?");
            $stmt->bind_param('s', $keyword);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $keywordId = $row['keyword_id'];
            } else {
                // 존재하지 않으면 새로운 키워드 삽입
                $stmt = $mysqli->prepare("INSERT INTO keyword (keyword) VALUES (?)");
                $stmt->bind_param('s', $keyword);
                $stmt->execute();
                $keywordId = $stmt->insert_id;
            }

            // 현재 그룹에 새로운 키워드 연결
            $stmt = $mysqli->prepare("INSERT INTO keyword_group_mappings (group_id, keyword_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $groupId, $keywordId);
            $stmt->execute();
        }

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

        // 완료 후 리다이렉트
        header("Location: keyword_group_list.php?group_id=$groupId&keyword=" . urlencode($newKeywords));
        exit();
    }
}
?>
