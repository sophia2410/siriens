<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

// comm_cd 테이블에서 조건을 가져옵니다.
$commQuery = "SELECT cd, nm, nm_sub1 FROM comm_cd WHERE l_cd = 'XR000' ORDER BY cd";
$commResult = $mysqli->query($commQuery);

// 날짜 처리 (오늘 날짜를 기준으로 작업)
$today = date('Y-m-d');

// 조건별로 쿼리 실행
while ($commRow = $commResult->fetch_assoc()) {
    $cd = $commRow['cd'];
    $nm = $commRow['nm'];
    $nm_sub1 = $commRow['nm_sub1'];

    // nm_sub1에서 조건 파싱 (거래일 수, 발생 횟수, 거래 금액 추출)
    list($trade_days, $occurrences, $min_amt) = explode(",", $nm);  // 예: 15,10,10 추출
    $tot_amt_condition = $min_amt * 100000000; // 10억 단위 금액 계산

    // 동적으로 쿼리 수정
    $query = "
        SELECT A.code, A.name, A.occurrence_days, IFNULL(ss.sector, '기타') AS sector
        FROM (
            SELECT ks.code, ks.name, COUNT(DISTINCT ks.date) AS occurrence_days
            FROM xraytick_summary ks
            JOIN (
                SELECT date
                FROM calendar
                WHERE date <= ?
                ORDER BY date DESC
                LIMIT ?
            ) rd ON ks.date = rd.date
            WHERE ks.tot_amt >= ?
            GROUP BY ks.code, ks.name
            HAVING COUNT(DISTINCT ks.date) >= ?
        ) A
        LEFT JOIN stock_sector ss ON A.code = ss.code";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('siii', $today, $trade_days, $tot_amt_condition, $occurrences);
    $stmt->execute();
    $result = $stmt->get_result();

    // 추출된 종목을 저장
    while ($row = $result->fetch_assoc()) {
        $insertQuery = "
            INSERT INTO xraytick_extracted_stocks (code, name, sector, occurrence_days, comm_cd, extract_date) 
            VALUES (?, ?, ?, ?, ?, ?)";
        $insertStmt = $mysqli->prepare($insertQuery);
        $insertStmt->bind_param('ssssss', $row['code'], $row['name'], $row['sector'], $row['occurrence_days'], $cd, $today);
        $insertStmt->execute();
    }

    $stmt->close();
}

echo "배치 처리가 완료되었습니다.";
?>
