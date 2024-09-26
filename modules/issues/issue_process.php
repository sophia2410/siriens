<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

error_log(print_r($_POST, true));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $selected_date = $_POST['report_date'] ?? date('Y-m-d');
    $issue_id = isset($issue_id) ?? '';
    
    // 리다이렉트 URL 설정
    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? "issue_register.php";

    // HTTP_REFERER에 쿼리스트링이 있는지 확인
    $parsedUrl = parse_url($redirectUrl);
    if (empty($parsedUrl['query'])) {
        // 쿼리스트링이 없을 때만 파라미터 추가
        $redirectUrl .= "?date=$selected_date";
    }

    $mysqli->autocommit(FALSE);

    try {
        if ($action === 'register' || $action === 'update') {
            // 등록 및 수정 로직
            $date = $selected_date;
            $issue = $_POST['issue'] ?? '';
            $first_occurrence = isset($_POST['new_issue']) ? 'Y' : 'N';
            $link = $_POST['link'] ?? '';
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
                    $issue_id = insertOrUpdateIssue($mysqli, $date, $issue, $first_occurrence, $link, $theme, $hot_theme, $group_id);
                }
            } elseif ($action === 'update') {
                $issue_id = $_POST['issue_id'];
                updateIssue($mysqli, $issue_id, $date, $issue, $first_occurrence, $link, $theme, $hot_theme, $group_id);
                deleteStocks($mysqli, $issue_id); // 기존 종목 삭제
            }

            handleStocks($mysqli, $issue_id, $_POST['stocks'] ?? [], $date);
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

        } elseif ($action === 'copy_issue') {
            // 복사 로직
            $issue_id = $_POST['issue_id'];
            $new_issue_id = copyIssue($mysqli, $issue_id);
            $mysqli->commit();
        } elseif ($action === 'delete_issue') {
            // 삭제 로직
            $issue_id = $_POST['issue_id'];
            deleteIssue($mysqli, $issue_id);
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
        } elseif ($action === 'register_by_stock') {
            $issues = $_POST['issues'] ?? [];  // 종목별로 등록 또는 수정할 데이터
            $selectedStocks = $_POST['selected_stocks'] ?? [];

            // 선택된 종목만 처리하도록 필터링
            foreach ($issues as $group_code => $issue_data) {
                // 선택된 종목인지 확인
                if (!in_array($issue_data['code'], $selectedStocks)) {
                    continue; // 선택되지 않은 종목은 처리하지 않음
                }
                error_log("selectedStocks code={$issue_data['code']} name={$issue_data['name']}");

                $isExisting = $issue_data['is_existing'] ?? '0';  // 등록 여부 확인
                $issue_id = null;
        
                // 키워드 그룹 처리 (새로운 키워드 그룹 ID 구함)
                $new_group_id = handleKeywords($mysqli, $issue_data['keyword'] ?? '');
                // error_log("Result handleKeywords: keyword={$issue_data['keyword']} new_group_id={$new_group_id}");
        
                // 기존 종목 데이터 확인
                if ($isExisting === '1') {
                    // 기존 데이터 가져오기
                    $issue_id = $issue_data['issue_id'];
                    // error_log("Checking existing issue, issue_id={$issue_id}");
        
                    $stmt = $mysqli->prepare("
                        SELECT mi.keyword_group_id 
                        FROM market_issues mi 
                        WHERE mi.issue_id = ?
                    ");
                    $stmt->bind_param('i', $issue_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existingIssue = $result->fetch_assoc();
                    $existing_group_id = $existingIssue['keyword_group_id'] ?? null;
                    // error_log("Existing group ID: {$existing_group_id}, new group ID: {$new_group_id}");
        
                    // 기존 키워드 그룹과 새로운 그룹이 다른지 확인
                    $isKeywordGroupChanged = ($existing_group_id != $new_group_id);
                    // error_log("Is keyword group changed: " . ($isKeywordGroupChanged ? 'Yes' : 'No'));
        
                    if ($isKeywordGroupChanged) {
                        // 키워드 그룹이 변경된 경우, 기존 키워드 그룹에서 해당 종목만 삭제
                        $stmt = $mysqli->prepare("
                            DELETE FROM market_issue_stocks 
                            WHERE issue_id = ? AND code = ?
                        ");
                        $stmt->bind_param('is', $issue_id, $issue_data['code']);
                        $stmt->execute();
                        // error_log("Deleted stock for issue_id={$issue_id} and code={$issue_data['code']}");
        
                        // 새로운 키워드 그룹으로 이슈 업데이트
                        $issue_id = insertOrUpdateIssue(
                            $mysqli, $selected_date, '', 'N', '', $issue_data['theme'], 'N', $new_group_id
                        );
                        // error_log("Inserted/Updated issue with new keyword group, issue_id={$issue_id}");
                    } else {
                        // 키워드 그룹이 변경되지 않은 경우 기존 이슈 업데이트
                        updateIssue(
                            $mysqli, $issue_id, $selected_date, '', 'N', '', $issue_data['theme'], 'N', $existing_group_id
                        );
                        // error_log("Updated issue with existing keyword group, issue_id={$issue_id}");
                    }

                    // 기존 데이터 삭제 -- issue_register 처리루틴과 동일하게
                    $stmt = $mysqli->prepare("DELETE FROM market_issue_stocks WHERE issue_id = ? AND code = ?");
                    $stmt->bind_param('is', $issue_id, $issue_data['code']);
                    $stmt->execute();
                    // error_log("Delete stock, issue_id={$issue_id}, code={$issue_data['code']}");
        
                    // 종목 정보 업데이트
                    handleStocks($mysqli, $issue_id, [$issue_data], $selected_date);
                    // error_log("Handled stocks for issue_id={$issue_id}");
        
                } else {
                    // 새로 등록해야 하는 경우
                    $issue_id = insertOrUpdateIssue(
                        $mysqli, $selected_date, '', 'N', '', $issue_data['theme'], 'N', $new_group_id
                    );
                    // error_log("Inserted new issue, issue_id={$issue_id}");
        
                    // 종목 처리
                    handleStocks($mysqli, $issue_id, [$issue_data], $selected_date);
                    // error_log("Handled stocks for new issue_id={$issue_id}");
                }
            }
        
            // 트랜잭션 커밋
            if ($mysqli->commit()) {
                error_log("Transaction committed successfully.");
            } else {
                error_log("Transaction commit failed: " . $mysqli->error);
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
        }

        // 등록 처리 후 리다이렉트
        header("Location: $redirectUrl");
        
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        echo "<script>alert('오류 발생: {$e->getMessage()}');</script>";
        header("Location: $redirectUrl");
        exit;
    }

    $mysqli->autocommit(TRUE);
}

function handleKeywords($mysqli, $keywords_input) {
    
    error_log("call handleKeywords: keywords_input={$keywords_input}");
    $keywords = array_unique(array_filter(array_map('trim', explode('#', $keywords_input))));
    $keyword_ids = [];

    foreach ($keywords as $keyword) {
        $stmt = $mysqli->prepare("SELECT keyword_id FROM keyword WHERE keyword = ?");
        $stmt->bind_param('s', $keyword);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $keyword_ids[] = $row['keyword_id'];
        } else {
            $stmt = $mysqli->prepare("INSERT INTO keyword (keyword) VALUES (?)");
            $stmt->bind_param('s', $keyword);
            $stmt->execute();
            $keyword_id = $mysqli->insert_id;
            $keyword_ids[] = $keyword_id;
        }
    }

    sort($keyword_ids);
    $keyword_ids_str = implode(',', $keyword_ids);

    $stmt = $mysqli->prepare("
        SELECT group_id 
        FROM (
            SELECT group_id, GROUP_CONCAT(keyword_id ORDER BY keyword_id ASC) as ids
            FROM keyword_group_mappings
            GROUP BY group_id
        ) AS sub
        WHERE ids = ?
    ");
    $stmt->bind_param('s', $keyword_ids_str);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $group_id = $row['group_id'];
    } else {
        $stmt = $mysqli->prepare("INSERT INTO keyword_groups (group_name) VALUES (?)");
        $group_name = implode(' ', array_map(fn($kw) => "#{$kw}", $keywords));
        $stmt->bind_param('s', $group_name);
        $stmt->execute();
        $group_id = $mysqli->insert_id;

        foreach ($keyword_ids as $keyword_id) {
            $stmt = $mysqli->prepare("INSERT INTO keyword_group_mappings (group_id, keyword_id, create_dtime) VALUES (?, ?, NOW())");
            $stmt->bind_param('ii', $group_id, $keyword_id);
            $stmt->execute();
        }
    }

    return $group_id;
}

function insertOrUpdateIssue($mysqli, $date, $issue, $first_occurrence, $link, $theme, $hot_theme, $group_id) {

    error_log("call insertOrUpdateIssue: date={$date} issue={$issue} first_occurrence={$first_occurrence} link={$link} theme={$theme} hot_theme={$hot_theme} group_id={$group_id}");
    $stmt = $mysqli->prepare("SELECT issue_id FROM market_issues WHERE date = ? AND keyword_group_id = ? AND issue = ? AND theme = ?");
    $stmt->bind_param('siss', $date, $group_id, $issue, $theme);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['issue_id'];
    } else {
        $stmt = $mysqli->prepare("INSERT INTO market_issues (date, issue, first_occurrence, link, theme, hot_theme, keyword_group_id, status, create_dtime) VALUES (?, ?, ?, ?, ?, ?, ?, 'registered', NOW())");
        $stmt->bind_param('ssssssi', $date, $issue, $first_occurrence, $link, $theme, $hot_theme, $group_id);
        if (!$stmt->execute()) {
            throw new Exception("market_issues 삽입 실패: " . $stmt->error);
        }
        return $mysqli->insert_id;
    }
}

function updateIssue($mysqli, $issue_id, $date, $issue, $first_occurrence, $link, $theme, $hot_theme, $group_id) {

    error_log("call updateIssue: issue={$issue} date={$date} first_occurrence={$first_occurrence} link={$link} theme={$theme} hot_theme={$hot_theme} group_id={$group_id}");
    $stmt = $mysqli->prepare("UPDATE market_issues SET date = ?, issue = ?, first_occurrence = ?, link = ?, theme = ?, hot_theme = ?, keyword_group_id = ?, status = 'registered' WHERE issue_id = ?");
    $stmt->bind_param('ssssssii', $date, $issue, $first_occurrence, $link, $theme, $hot_theme, $group_id, $issue_id);
    if (!$stmt->execute()) {
        throw new Exception("market_issues 업데이트 실패: " . $stmt->error);
    }
}

function handleStocks($mysqli, $issue_id, $stocks, $date) {
    foreach ($stocks as $stock) {
        error_log("call handleStocks: Processing stock: issue_id={$issue_id}, code={$stock['code']}, name={$stock['name']}");

        $code = $stock['code'] ?? '';
        $name = $stock['name'] ?? '';
        $sector = $stock['sector'] ?? ''; 
        $stock_comment = $stock['comment'] ?? '';
        $isLeader = isset($stock['is_leader']) ? '1' : '0'; 
        $isWatchlist = isset($stock['is_watchlist']) ? '1' : '0'; 

        // Ensure we have the necessary stock data
        if ($code !== '' && $name !== '') {
            error_log("Stock data is valid: code={$code}, name={$name}, sector={$sector}, comment={$stock_comment}");

            // Retrieve high_rate, close_rate, volume, and amount from v_daily_price
            $priceQuery = "
                SELECT high_rate, close_rate, volume, amount 
                FROM v_daily_price 
                WHERE code = ? AND date = ?
            ";
            $priceStmt = $mysqli->prepare($priceQuery);
            $priceStmt->bind_param('ss', $code, $date);
            $priceStmt->execute();
            $priceResult = $priceStmt->get_result();

            if ($priceRow = $priceResult->fetch_assoc()) {
                $high_rate = $priceRow['high_rate'] ?? null;
                $close_rate = $priceRow['close_rate'] ?? null;
                $volume = $priceRow['volume'] ?? null;
                $trade_amount = $priceRow['amount'] ?? null;
                error_log("Retrieved stock price data: high_rate={$high_rate}, close_rate={$close_rate}, volume={$volume}, amount={$trade_amount}");
            } else {
                // Set defaults if no data is found
                error_log("No price data found for code={$code} on date={$date}, setting default values.");
                $high_rate = null;
                $close_rate = null;
                $volume = null;
                $trade_amount = null;
            }

            // Insert the data into market_issue_stocks
            $stmt = $mysqli->prepare("
                INSERT INTO market_issue_stocks 
                (issue_id, code, name, sector, high_rate, close_rate, volume, trade_amount, stock_comment, is_leader, is_watchlist, date, registration_source, create_dtime) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', NOW())
            ");
            $stmt->bind_param(
                'isssssddssss', 
                $issue_id, 
                $code, 
                $name, 
                $sector,
                $high_rate, 
                $close_rate, 
                $volume, 
                $trade_amount, 
                $stock_comment, 
                $isLeader, 
                $isWatchlist, 
                $date
            );

            // Convert parameters to a string for logging
            $params = sprintf("INSERT INTO market_issue_stocks 
                (issue_id, code, name, sector, high_rate, close_rate, volume, trade_amount, stock_comment, is_leader, is_watchlist, date, registration_source, create_dtime) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'manual', NOW())",
                $issue_id, 
                $code, 
                $name, 
                $sector,
                $high_rate, 
                $close_rate, 
                $volume, 
                $trade_amount, 
                $stock_comment, 
                $isLeader, 
                $isWatchlist, 
                $date
            );

            // Log the parameters
            error_log("Prepared statement parameters: " . $params);

            if (!$stmt->execute()) {
                error_log("Error inserting into market_issue_stocks: " . $stmt->error);
                throw new Exception("market_issue_stocks 삽입 실패: " . $stmt->error);
            } else {
                error_log("Stock successfully inserted: Code - {$code}");
            }
        } else {
            error_log("Skipped insertion due to missing code or name: code={$code}, name={$name}");
        }
    }
}

function deleteStocks($mysqli, $issue_id) {

    error_log("call deleteStocks: issue_id={$issue_id}");
    $stmt = $mysqli->prepare("DELETE FROM market_issue_stocks WHERE issue_id = ?");
    $stmt->bind_param('i', $issue_id);
    if (!$stmt->execute()) {
        throw new Exception("market_issue_stocks 삭제 실패: " . $stmt->error);
    }
}

function copyIssue($mysqli, $issue_id) {

    error_log("call copyIssue: issue_id={$issue_id}");
    $stmt = $mysqli->prepare("SELECT * FROM market_issues WHERE issue_id = ?");
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $issueData = $result->fetch_assoc();

    if (!$issueData) {
        throw new Exception("이슈를 찾을 수 없습니다.");
    }

    $stmt = $mysqli->prepare("INSERT INTO market_issues (date, issue, first_occurrence, link, theme, hot_theme, status, create_dtime, keyword_group_id) VALUES (?, ?, ?, ?, ?, ?, 'copied', NOW(), ?)");
    $stmt->bind_param(
        'ssssssi',
        $issueData['date'],
        $issueData['issue'],
        $issueData['first_occurrence'],
        $issueData['link'],
        $issueData['theme'],
        $issueData['hot_theme'],
        $issueData['keyword_group_id']
    );

    if (!$stmt->execute()) {
        throw new Exception("이슈 복사 실패: " . $stmt->error);
    }

    $new_issue_id = $mysqli->insert_id;

    $stmt = $mysqli->prepare("SELECT * FROM market_issue_stocks WHERE issue_id = ?");
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $stocksResult = $stmt->get_result();

    while ($stock = $stocksResult->fetch_assoc()) {
        $stmt = $mysqli->prepare("INSERT INTO market_issue_stocks (issue_id, code, name, sector, high_rate, close_rate, volume, trade_amount, stock_comment, date, registration_source, create_dtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', NOW())");
        $stmt->bind_param(
            'isssssddss',
            $new_issue_id,
            $stock['code'],
            $stock['name'],
            $stock['sector'],
            $stock['high_rate'],
            $stock['close_rate'],
            $stock['volume'],
            $stock['trade_amount'],
            $stock['stock_comment'],
            $stock['date']
        );

        if (!$stmt->execute()) {
            throw new Exception("종목 복사 실패: " . $stmt->error);
        }
    }

    return $new_issue_id;
}

function deleteIssue($mysqli, $issue_id) {

    error_log("call copyIssue: issue_id={$issue_id}");
    deleteStocks($mysqli, $issue_id);

    $stmt = $mysqli->prepare("DELETE FROM market_issues WHERE issue_id = ?");
    $stmt->bind_param('i', $issue_id);
    if (!$stmt->execute()) {
        throw new Exception("이슈 삭제 실패: " . $stmt->error);
    }
}
?>
