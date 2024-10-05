<?php
// 금액에 따른 CSS 클래스 반환
function Utility_GetAmountClass($amountInBillion) {
    if ($amountInBillion >= 2000) {
        return 'amount-highest'; // 2000억 이상
    } elseif ($amountInBillion >= 1000) {
        return 'amount-high'; // 1000억 이상
    } elseif ($amountInBillion >= 500) {
        return 'amount-medium-high'; // 500억 이상
    } elseif ($amountInBillion >= 150) {
        return 'amount-medium'; // 150억 이상
    } elseif ($amountInBillion >= 100) {
        return 'amount-low'; // 100억 이상
    } else {
        return 'amount-lowest'; // 100억 미만
    }
}

// 등락률에 따른 등락률 CSS 클래스 반환
function Utility_GetCloseRateClass($closeRate) {
    if ($closeRate >= 29.5) {
        return 'close-rate-high'; // 상한가
    } else {
        return 'close-rate-lowest'; // 100억 미만
    }
}

// 등락률에 따른 종목명 CSS 클래스 반환
function Utility_GetStockNameClass($closeRate) {
    if ($closeRate >= 29.5) {
        return 'stock-name-high';
    } else {
        return 'stock-name-lowest';
    }
}
// 등락률에 따른 종목명 CSS 클래스 반환
function Utility_GetStockNameAmountClass($amountInBillion) {
    if ($amountInBillion >= 2000) {
        return 'stock-name-high'; // 2000억 이상
    } elseif ($amountInBillion >= 1000) {
        return 'stock-name-medium-high'; // 1000억 이상
    } elseif ($amountInBillion >= 500) {
        return 'stock-name-medium'; // 500억 이상
    } elseif ($amountInBillion >= 100) {
        return 'stock-name-low'; // 100억 이상
    } else {
        return 'stock-name-lowest'; // 100억 미만
    }
}

// 등락률에 따른 종목명 CSS 클래스 반환
function Utility_GgetIssueKeywords($reportDate, $issueId) {
    global $mysqli; // 데이터베이스 연결 사용

    $keywordStmt = $mysqli->prepare("
    SELECT 
        k.keyword, 
        CASE 
            WHEN sc.stock_cnt > 0 
            THEN CONCAT('(', sc.stock_cnt, ')')
            ELSE ''
        END AS stock_cnt
    FROM 
        keyword_issue_mappings mik
    JOIN 
        keyword k ON mik.keyword_id = k.keyword_id
    LEFT JOIN (
                SELECT kgm.keyword_id, COUNT(mes.code) AS stock_cnt
                FROM market_events me
                JOIN market_event_stocks mes ON me.event_id = mes.event_id
                JOIN keyword_group_mappings kgm ON me.keyword_group_id = kgm.group_id
                WHERE me.date = ?  -- 특정 일자 조건
                GROUP BY kgm.keyword_id
            ) sc
    ON sc.keyword_id = mik.keyword_id  -- 여기서 sc 서브쿼리의 keyword_id와 mik의 keyword_id를 조인
    WHERE 
        mik.issue_id = ?  -- 특정 issue_id 조건
    ORDER BY 
        k.keyword_id ASC
    ");

    $keywordStmt->bind_param('si', $reportDate, $issueId);
    $keywordStmt->execute();
    $keywordResult = $keywordStmt->get_result();

    $keywords = [];
    while ($keywordRow = $keywordResult->fetch_assoc()) {
        $keywords[] = $keywordRow;
    }

    return $keywords;
}
?>