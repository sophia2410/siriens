<?php

require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
header('Content-Type: application/json');

// GET 또는 POST 요청으로 종목코드를 받습니다.
$code = isset($_GET['code']) ? $_GET['code'] : (isset($_POST['code']) ? $_POST['code'] : '');
$name = isset($_GET['name']) ? $_GET['name'] : (isset($_POST['name']) ? $_POST['name'] : '');

if (empty($code)) {
    echo json_encode(array("message" => "No stock code provided"));
    exit;
}

// 종목코드의 데이터를 가져오는 SQL 쿼리
$sql = "SELECT mo.date, 
               CASE WHEN mo.open = 0 THEN mo.close ELSE mo.open END AS open, 
               CASE WHEN mo.open = 0 THEN mo.close ELSE mo.high END AS high, 
               CASE WHEN mo.open = 0 THEN mo.close ELSE mo.low END  AS low, 
               mo.close, mo.close_rate, mo.volume, mo.amount, xr.tot_amt AS xray_amount
        FROM daily_price mo
        LEFT OUTER JOIN kiwoom_xray_tick_summary xr
        ON xr.date = mo.date
        AND xr.code = mo.code
        WHERE mo.code = ?
        ORDER BY mo.date ASC";
        
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $code);
$stmt->execute();
$result = $stmt->get_result();

$data = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = [
            strtotime($row['date']) * 1000, // 날짜를 밀리초 타임스탬프로 변환
            (float)$row['open'],
            (float)$row['high'],
            (float)$row['low'],
            (float)$row['close'],
            (float)$row['close_rate'],
            (int)$row['volume'],
            (float)$row['amount'],
            (float)$row['xray_amount']
        ];
    }
} else {
    echo json_encode(array("message" => "No data found"));
    exit;
}

$stmt->close();

// stock_price_zone 데이터를 가져오는 SQL 쿼리
$zone_sql = "SELECT zone_type, start_price, end_price
             FROM stock_price_zone
             WHERE code = ? AND name = ?";
$zone_stmt = $mysqli->prepare($zone_sql);
$zone_stmt->bind_param('ss', $code, $name);
$zone_stmt->execute();
$zone_result = $zone_stmt->get_result();

$zones = array();

if ($zone_result->num_rows > 0) {
    while($zone_row = $zone_result->fetch_assoc()) {
        $zones[] = [
            'zone_type' => $zone_row['zone_type'],
            'start_price' => (float)$zone_row['start_price'],
            'end_price' => $zone_row['end_price'] ? (float)$zone_row['end_price'] : null
        ];
    }
}

$zone_stmt->close();
$mysqli->close();

// 데이터를 JSON으로 반환
echo json_encode(['data' => $data, 'zones' => $zones]);
?>
