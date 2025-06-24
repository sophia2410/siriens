<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php"); // DB 연결

// POST 요청으로 받은 데이터를 처리
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// 전송된 RAW 데이터를 로그로 출력하여 확인 (디버깅 용도)
error_log('Raw POST data: ' . $rawData);

// 전송된 데이터를 파싱
if ($data === null) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON format or no data received']);
    exit;
}

// 섹터 및 섹터 그룹 정보를 처리하기 위해 배열을 구성
$newSectors = [];
$newSectorGroups = [];

// 대괄호가 포함된 키를 처리하여 'new_sectors'와 'new_sector_groups' 데이터를 추출
foreach ($data as $key => $value) {
    // new_sectors[코드] 형식에서 대괄호 안의 코드를 추출
    if (preg_match('/new_sectors\[(.*?)\]/', $key, $matches)) {
        $newSectors[$matches[1]] = $value;
    }

    // new_sector_groups[코드] 형식에서 대괄호 안의 코드를 추출
    if (preg_match('/new_sector_groups\[(.*?)\]/', $key, $matches)) {
        $newSectorGroups[$matches[1]] = $value;
    }
}

// 섹터 정보 업데이트 및 삽입 처리
if (!empty($newSectors) && !empty($newSectorGroups)) {
    foreach ($newSectors as $code => $newSector) {
        // 섹터 그룹도 함께 가져옵니다.
        $newSectorGroup = isset($newSectorGroups[$code]) ? $newSectorGroups[$code] : '';

        // 해당 종목이 이미 stock_sector 테이블에 존재하는지 확인
        $checkQuery = "SELECT COUNT(*) FROM stock_sector WHERE code = ?";
        $stmt = $mysqli->prepare($checkQuery);
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            // 이미 존재하면 UPDATE 수행
            $updateQuery = "UPDATE stock_sector SET sector = ?, sector_group = ? WHERE code = ?";
            $stmt = $mysqli->prepare($updateQuery);
            $stmt->bind_param('sss', $newSector, $newSectorGroup, $code);
            $stmt->execute();
        } else {
            // 존재하지 않으면 INSERT 수행
            $insertQuery = "INSERT INTO stock_sector (code, sector, sector_group) VALUES (?, ?, ?)";
            $stmt = $mysqli->prepare($insertQuery);
            $stmt->bind_param('sss', $code, $newSector, $newSectorGroup);
            $stmt->execute();
        }
    }

    // 성공적으로 업데이트 또는 삽입이 완료되었음을 알림
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data received or incomplete data']);
}
?>
