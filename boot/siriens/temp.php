<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

error_log(print_r($_POST, true));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $selected_date = $_POST['report_date'] ?? date('Y-m-d');

    $mysqli->autocommit(FALSE);

    try {
        if ($action === 'register' || $action === 'update') {
            // 등록 및 수정 로직
            $date = str_replace('-', '', $selected_date);
            $issue = $_POST['issue'] ?? '';
            $first_occurrence = isset($_POST['new_issue']) ? 'Y' : 'N';
            $link = $_POST['link'] ?? '';
            $theme = $_POST['theme'] ?? '';
            $hot_theme = isset($_POST['hot_theme']) ? 'Y' : 'N';
            $group_id = handleKeywords($mysqli, $_POST['keyword'] ?? '');

            if ($action === 'register') {
                // 오늘의 market_issues 테이블에 이미 해당 키워드 그룹이 등록되어 있는지 확인
                $stmt = $mysqli->prepare("SELECT issue_id FROM market_issues WHERE date = ? AND keyword_group_id = ? AND issue = ? AND theme = ?");
                $stmt->bind_param('siss', $date, $group_id, $issue, $theme);
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

        } elseif ($action === 'copy') {
            // 복사 로직
            $issue_id = $_POST['issue_id'];
            $new_issue_id = copyIssue($mysqli, $issue_id);
            $mysqli->commit();
        } elseif ($action === 'delete') {
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
        }
        $issue = urlencode($_POST['issue'] ?? '');
        $new_issue = isset($_POST['new_issue']) ? '1' : '0';
        
        header("Location: issue_register.php?date=$selected_date&issue=$issue&new_issue=$new_issue");
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        echo "<script>alert('오류 발생: {$e->getMessage()}');</script>";
        header("Location: issue_register.php?date=$selected_date");
        exit;
    }

    $mysqli->autocommit(TRUE);
}

function handleKeywords($mysqli, $keywords_input) {
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
    $stmt = $mysqli->prepare("UPDATE market_issues SET date = ?, issue = ?, first_occurrence = ?, link = ?, theme = ?, hot_theme = ?, keyword_group_id = ?, status = 'registered' WHERE issue_id = ?");
    $stmt->bind_param('ssssssii', $date, $issue, $first_occurrence, $link, $theme, $hot_theme, $group_id, $issue_id);
    if (!$stmt->execute()) {
        throw new Exception("market_issues 업데이트 실패: " . $stmt->error);
    }
}

function handleStocks($mysqli, $issue_id, $stocks, $date) {
    foreach ($stocks as $stock) {
        $code = $stock['code'] ?? '';
        $name = $stock['name'] ?? '';
        $sector = $stock['sector'] ?? ''; // Added to capture sector input
        $stock_comment = $stock['comment'] ?? '';
        $isLeader = isset($stock['is_leader']) ? '1' : '0'; // Adjusted to string

        if ($code !== '' && $name !== '') {
            // Retrieve close_rate, volume, and amount from v_daily_price
            $priceQuery = "
                SELECT close_rate, volume, amount 
                FROM v_daily_price 
                WHERE code = ? AND date = ?
            ";
            $priceStmt = $mysqli->prepare($priceQuery);
            $priceStmt->bind_param('ss', $code, $date);
            $priceStmt->execute();
            $priceResult = $priceStmt->get_result();

            if ($priceRow = $priceResult->fetch_assoc()) {
                $close_rate = $priceRow['close_rate'] ?? null;
                $volume = $priceRow['volume'] ?? null;
                $trade_amount = $priceRow['amount'] ?? null;
            } else {
                // Set defaults if no data is found
                $close_rate = null;
                $volume = null;
                $trade_amount = null;
            }

            // Insert the data into market_issue_stocks
            $stmt = $mysqli->prepare("
                INSERT INTO market_issue_stocks 
                (issue_id, code, name, sector, close_rate, volume, trade_amount, stock_comment, is_leader, date, create_dtime) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                'issssddsss', 
                $issue_id, 
                $code, 
                $name, 
                $sector, // Save sector in market_issue_stocks
                $close_rate, 
                $volume, 
                $trade_amount, 
                $stock_comment, 
                $isLeader, 
                $date
            );

            if (!$stmt->execute()) {
                throw new Exception("market_issue_stocks 삽입 실패: " . $stmt->error);
            }
        } else {
            error_log("Skipped insertion due to missing code or name: code={$code}, name={$name}");
        }
    }
}

function deleteStocks($mysqli, $issue_id) {
    $stmt = $mysqli->prepare("DELETE FROM market_issue_stocks WHERE issue_id = ?");
    $stmt->bind_param('i', $issue_id);
    if (!$stmt->execute()) {
        throw new Exception("market_issue_stocks 삭제 실패: " . $stmt->error);
    }
}

function copyIssue($mysqli, $issue_id) {
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
        $stmt = $mysqli->prepare("INSERT INTO market_issue_stocks (issue_id, code, name, sector, close_rate, volume, trade_amount, stock_comment, date, create_dtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param(
            'issssddss',
            $new_issue_id,
            $stock['code'],
            $stock['name'],
            $stock['sector'], // Include sector in the copied stock record
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
    deleteStocks($mysqli, $issue_id);

    $stmt = $mysqli->prepare("DELETE FROM market_issues WHERE issue_id = ?");
    $stmt->bind_param('i', $issue_id);
    if (!$stmt->execute()) {
        throw new Exception("이슈 삭제 실패: " . $stmt->error);
    }
}
?>
