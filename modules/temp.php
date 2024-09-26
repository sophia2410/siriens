<?php
        } elseif ($action === 'register_by_stock') {
            $issues = $_POST['issues'] ?? [];
            $selectedStocks = $_POST['selected_stocks'] ?? [];

            // 선택된 종목만 처리하도록 필터링
            foreach ($issues as $group_code => $issue_data) {
                // 선택된 종목인지 확인
                if (!in_array($issue_data['code'], $selectedStocks)) {
                    continue; // 선택되지 않은 종목은 처리하지 않음
                }

                $isExisting = $issue_data['is_existing'] ?? '0';
                $issue_id = null;

                // 키워드 그룹 처리 (새로운 키워드 그룹 ID 구함)
                $new_group_id = handleKeywords($mysqli, $issue_data['keyword'] ?? '');

                // 기존 종목 데이터 확인
                if ($isExisting === '1') {
                    $issue_id = $issue_data['issue_id'];

                    $stmt = $mysqli->prepare("SELECT mi.keyword_group_id FROM market_issues mi WHERE mi.issue_id = ?");
                    $stmt->bind_param('i', $issue_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existingIssue = $result->fetch_assoc();
                    $existing_group_id = $existingIssue['keyword_group_id'] ?? null;

                    $isKeywordGroupChanged = ($existing_group_id != $new_group_id);

                    if ($isKeywordGroupChanged) {
                        $stmt = $mysqli->prepare("DELETE FROM market_issue_stocks WHERE issue_id = ? AND code = ?");
                        $stmt->bind_param('is', $issue_id, $issue_data['code']);
                        $stmt->execute();

                        $issue_id = insertOrUpdateIssue($mysqli, $selected_date, '', 'N', '', $issue_data['theme'], 'N', $new_group_id);
                    } else {
                        updateIssue($mysqli, $issue_id, $selected_date, '', 'N', '', $issue_data['theme'], 'N', $existing_group_id);
                    }

                    // 기존 데이터 삭제 후 종목 정보 업데이트
                    $stmt = $mysqli->prepare("DELETE FROM market_issue_stocks WHERE issue_id = ? AND code = ?");
                    $stmt->bind_param('is', $issue_id, $issue_data['code']);
                    $stmt->execute();

                    handleStocks($mysqli, $issue_id, [$issue_data], $selected_date);
                } else {
                    // 신규 등록
                    $issue_id = insertOrUpdateIssue($mysqli, $selected_date, '', 'N', '', $issue_data['theme'], 'N', $new_group_id);
                    handleStocks($mysqli, $issue_id, [$issue_data], $selected_date);
                }
            }

            // 트랜잭션 커밋
            if ($mysqli->commit()) {
                error_log("Transaction committed successfully.");
            } else {
                error_log("Transaction commit failed: " . $mysqli->error);
            }

            // 미사용 키워드 삭제 처리
            try {
                $mysqli->query("CALL p_cleanup_unused_keywords();");
                if (!$mysqli->commit()) {
                    throw new Exception("Commit failed: " . $mysqli->error);
                }
            } catch (Exception $e) {
                $mysqli->rollback();
                echo "Error: " . $e->getMessage();
            }
        }
?>
