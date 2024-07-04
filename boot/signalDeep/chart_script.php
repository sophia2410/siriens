<?php

require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
header('Content-Type: application/json');

// '003160' 종목의 데이터를 가져오는 SQL 쿼리
$sql = "SELECT mo.date, mo.open, mo.high, mo.low, mo.close, mo.volume, mo.amount, xr.tot_amt AS xray_amount
        FROM market_ohlcv mo
        LEFT OUTER JOIN kiwoom_xray_tick_summary xr
        ON xr.date = mo.date
        AND xr.code = mo.code
        WHERE mo.code = '003160'
        ORDER BY mo.date ASC";
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
            (int)$row['volume'],
            (float)$row['amount'],
            (float)$row['xray_amount']
        ];
    }
} else {
    echo json_encode(array("message" => "No data found"));
    exit;
}

// JSON 형식으로 출력
echo json_encode($data);
?>
