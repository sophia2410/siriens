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
            $sector = $_POST['sector'] ?? '';
            $theme = $_POST['theme'] ?? '';
            $hot_theme = isset($_POST['hot_theme']) ? 'Y' : 'N';
            $group_id = handleKeywords($mysqli, $_POST['keyword'] ?? '');

            if ($action === 'register') {
                $issue_id = insertOrUpdateIssue($mysqli, $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id);
            } elseif ($action === 'update') {
                $issue_id = $_POST['issue_id'];
                updateIssue($mysqli, $issue_id, $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id);
                deleteStocks($mysqli, $issue_id); // 기존 종목 삭제
            }

            handleStocks($mysqli, $issue_id, $_POST['stocks'] ?? [], $date);
            $mysqli->commit();
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
        }

        header("Location: market_issue.php?date=$selected_date");
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        echo "<script>alert('오류 발생: {$e->getMessage()}');</script>";
        header("Location: market_issue.php?date=$selected_date");
        exit;
    }

    $mysqli->autocommit(TRUE);
}

function handleKeywords($mysqli, $keywords_input) {
    $keywords = array_unique(array_filter(array_map('trim', explode('#', $keywords_input))));
    $keyword_ids = [];

    foreach ($keywords as $keyword) {
        $stmt = $mysqli->prepare("SELECT keyword_id FROM keyword_master WHERE keyword = ?");
        $stmt->bind_param('s', $keyword);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $keyword_ids[] = $row['keyword_id'];
        } else {
            $stmt = $mysqli->prepare("INSERT INTO keyword_master (keyword) VALUES (?)");
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
            FROM keyword_groups
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
        $stmt = $mysqli->prepare("INSERT INTO keyword_groups_master (group_name) VALUES (?)");
        $group_name = implode(' ', array_map(fn($kw) => "#{$kw}", $keywords));
        $stmt->bind_param('s', $group_name);
        $stmt->execute();
        $group_id = $mysqli->insert_id;

        foreach ($keyword_ids as $keyword_id) {
            $stmt = $mysqli->prepare("INSERT INTO keyword_groups (group_id, keyword_id, create_dtime) VALUES (?, ?, NOW())");
            $stmt->bind_param('ii', $group_id, $keyword_id);
            $stmt->execute();
        }
    }

    return $group_id;
}

function insertOrUpdateIssue($mysqli, $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id) {
    $stmt = $mysqli->prepare("SELECT issue_id FROM market_issues WHERE date = ? AND keyword_group_id = ? AND issue = ? AND sector = ? AND theme = ?");
    $stmt->bind_param('sisss', $date, $group_id, $issue, $sector, $theme);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['issue_id'];
    } else {
        $stmt = $mysqli->prepare("INSERT INTO market_issues (date, issue, first_occurrence, link, sector, theme, hot_theme, create_dtime, keyword_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param('sssssssi', $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id);
        if (!$stmt->execute()) {
            throw new Exception("market_issues 삽입 실패: " . $stmt->error);
        }
        return $mysqli->insert_id;
    }
}

function updateIssue($mysqli, $issue_id, $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id) {
    $stmt = $mysqli->prepare("UPDATE market_issues SET date = ?, issue = ?, first_occurrence = ?, link = ?, sector = ?, theme = ?, hot_theme = ?, keyword_group_id = ? WHERE issue_id = ?");
    $stmt->bind_param('sssssssii', $date, $issue, $first_occurrence, $link, $sector, $theme, $hot_theme, $group_id, $issue_id);
    if (!$stmt->execute()) {
        throw new Exception("market_issues 업데이트 실패: " . $stmt->error);
    }
}

function handleStocks($mysqli, $issue_id, $stocks, $date) {
    foreach ($stocks as $stock) {
        $code = $stock['code'] ?? '';
        $name = $stock['name'] ?? '';
        $stock_comment = $stock['comment'] ?? '';

        if ($code !== '' && $name !== '') {
            $stmt = $mysqli->prepare("INSERT INTO market_issue_stocks (issue_id, code, name, stock_comment, date, create_dtime) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('issss', $issue_id, $code, $name, $stock_comment, $date);
            if (!$stmt->execute()) {
                throw new Exception("market_issue_stocks 삽입 실패: " . $stmt->error);
            }
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

    $stmt = $mysqli->prepare("INSERT INTO market_issues (date, issue, first_occurrence, link, sector, theme, hot_theme, create_dtime, keyword_group_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param(
        'sssssssi',
        $issueData['date'],
        $issueData['issue'],
        $issueData['first_occurrence'],
        $issueData['link'],
        $issueData['sector'],
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
        $stmt = $mysqli->prepare("INSERT INTO market_issue_stocks (issue_id, code, name, stock_comment, date, create_dtime) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param(
            'issss',
            $new_issue_id,
            $stock['code'],
            $stock['name'],
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
