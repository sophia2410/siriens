<?php

require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
header('Content-Type: application/json');

// '003160' 종목의 데이터를 가져오는 SQL 쿼리
$sql = "SELECT `date`, `open`, `high`, `low`, `close`, `volume` FROM `market_ohlcv` WHERE `code` = '003160' ORDER BY `date` ASC";
$result = $mysqli->query($sql);

$data = array();

if ($result->num_rows > 0) {
    // 각 행의 데이터를 배열에 추가
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
        $data[] = [
            strtotime($row['date']) * 1000, // 날짜를 밀리초 타임스탬프로 변환
            (float)$row['open'],
            (float)$row['high'],
            (float)$row['low'],
            (float)$row['close'],
            (int)$row['volume']
        ];
    }
} else {
    echo json_encode(array("message" => "No data found"));
    exit;
}

// JSON 형식으로 출력
echo json_encode($data);
?>
