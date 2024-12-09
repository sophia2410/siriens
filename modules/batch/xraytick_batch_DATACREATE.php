<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

// 임시 기간 설정 (2024-04-01 ~ 2024-04-30)
$start_date = '2024-08-29';
$end_date = '2024-10-31';

// 에러 확인 함수
function checkError($mysqli, $step = '') {
    if ($mysqli->error) {
        echo "[ERROR at $step]: " . $mysqli->error . "\n";
        return true;
    }
    return false;
}

// 데이터 확인 함수
function debugData($message, $data) {
    echo "[DEBUG] $message: " . print_r($data, true) . "\n";
}

// 1. comm_cd 테이블 데이터를 배열로 가져오기
$commQuery = "SELECT cd, nm, nm_sub1 FROM comm_cd WHERE l_cd = 'XR000' ORDER BY cd";
$commResult = $mysqli->query($commQuery);
if (checkError($mysqli, 'Comm Query')) {
    exit;
}

$commData = [];
while ($commRow = $commResult->fetch_assoc()) {
    $commData[] = $commRow; // 배열에 comm_cd 데이터를 저장
}

// calendar 테이블에서 지정한 기간 동안의 날짜를 가져오는 쿼리
$dateQuery = "SELECT date FROM calendar WHERE date BETWEEN ? AND ?";
$dateStmt = $mysqli->prepare($dateQuery);
if (!$dateStmt) {
    checkError($mysqli, 'Prepare Date Query');
}
$dateStmt->bind_param('ss', $start_date, $end_date);
$dateStmt->execute();
$dateResult = $dateStmt->get_result();
if (checkError($mysqli, 'Execute Date Query')) {
    exit;
}

// 기간 내 각 날짜에 대해 배치 실행
while ($dateRow = $dateResult->fetch_assoc()) {
    $currentDate = $dateRow['date'];
    debugData('Processing Date', $currentDate);

    // comm_cd 배열을 사용하여 실행
    foreach ($commData as $commRow) {
        $cd = $commRow['cd'];
        $nm = $commRow['nm'];
        $nm_sub1 = $commRow['nm_sub1'];

        debugData('Comm_cd Data', $commRow);

        // nm_sub1에서 조건 파싱 (거래일 수, 발생 횟수, 최소 거래 금액 추출)
        list($trade_days, $occurrences, $min_amt) = explode(",", $nm);  // 예: 15,10,10 추출
        $tot_amt_condition = $min_amt * 100000000; // 10억 단위 금액 계산

        debugData('Parsed nm_sub1', [
            'trade_days' => $trade_days,
            'occurrences' => $occurrences,
            'min_amt' => $min_amt,
            'tot_amt_condition' => $tot_amt_condition
        ]);

        // 동적으로 쿼리 실행
        $query = "
            SELECT A.code, A.occurrence_days
            FROM (
                SELECT ks.code, COUNT(DISTINCT ks.date) AS occurrence_days
                FROM xraytick_summary ks
                JOIN (
                    SELECT date
                    FROM calendar
                    WHERE date <= ?
                    ORDER BY date DESC
                    LIMIT ?
                ) rd ON ks.date = rd.date
                WHERE ks.tot_amt >= ?
                GROUP BY ks.code
                HAVING COUNT(DISTINCT ks.date) >= ?
            ) A";

        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            checkError($mysqli, 'Prepare Main Query');
            continue;
        }
        
        $stmt->bind_param('siii', $currentDate, $trade_days, $tot_amt_condition, $occurrences);
        $stmt->execute();
        if (checkError($mysqli, 'Execute Main Query')) {
            continue;
        }

        $result = $stmt->get_result();
        $rowCount = 0;

        debugData('Main Query Result', $result->num_rows);

        // 추출된 종목을 xraytick_extracted_stocks 테이블에 저장 또는 업데이트
        while ($row = $result->fetch_assoc()) {
            $rowCount++;  // 종목별 카운트를 기록

            // 종목명과 섹터 조회
            $stockQuery = "
                SELECT s.name, ss.sector 
                FROM stock s
                LEFT JOIN stock_sector ss ON s.code = ss.code
                WHERE s.code = ?";
            $stockStmt = $mysqli->prepare($stockQuery);
            if (!$stockStmt) {
                checkError($mysqli, 'Prepare Stock Query');
                continue;
            }
            $stockStmt->bind_param('s', $row['code']);
            $stockStmt->execute();
            $stockResult = $stockStmt->get_result();
            if (checkError($mysqli, 'Execute Stock Query')) {
                continue;
            }

            $stockRow = $stockResult->fetch_assoc();
            $stockName = $stockRow['name'] ?? '알 수 없음';
            $stockSector = $stockRow['sector'] ?? '기타';

            debugData('Stock Data', [
                'stockName' => $stockName,
                'stockSector' => $stockSector
            ]);

            // 기존 데이터를 확인하여 stock_count 계산 (등록된 횟수)
            $countQuery = "SELECT COUNT(*) AS count FROM xraytick_extracted_stocks WHERE comm_cd = ? AND extract_date < ? AND code = ?";
            $countStmt = $mysqli->prepare($countQuery);
            if (!$countStmt) {
                checkError($mysqli, 'Prepare Count Query');
                continue;
            }
            $countStmt->bind_param('sss', $cd, $currentDate, $row['code']);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow = $countResult->fetch_assoc();
            $stockCount = $countRow['count'] + 1;  // 기존 카운트에 1 추가

            // INSERT OR UPDATE 처리
            $insertQuery = "
                INSERT INTO xraytick_extracted_stocks (code, occurrence_days, comm_cd, extract_date, stock_count) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE occurrence_days = VALUES(occurrence_days), stock_count = VALUES(stock_count)";
            $insertStmt = $mysqli->prepare($insertQuery);
            
            if (!$insertStmt) {
                checkError($mysqli, 'Prepare Insert Query');
                continue;
            }

            $insertStmt->bind_param('ssssi', $row['code'], $row['occurrence_days'], $cd, $currentDate, $stockCount);
            $insertStmt->execute();
            if (checkError($mysqli, 'Execute Insert Query')) {
                continue;
            }

            echo "[INFO] 종목: {$stockName}, 섹터: {$stockSector}, 날짜: {$currentDate}, 저장 완료 (횟수: {$stockCount}).\n";
        }

        if ($rowCount > 0) {
            echo "[INFO] 날짜: $currentDate, 조건: $cd - $rowCount 종목이 저장되었습니다.\n";
        } else {
            echo "[INFO] 날짜: $currentDate, 조건: $cd - 저장된 종목이 없습니다.\n";
        }

        $stmt->close();
    }

    echo "[INFO] 날짜: $currentDate 의 데이터가 처리되었습니다.\n";
}

$dateStmt->close();

echo "[INFO] 모든 배치 작업이 완료되었습니다.\n";
?>
