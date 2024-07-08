<?php
$code = $_GET['code'];
$name = $_GET['name'];

$query = "SELECT zone_type, start_price, end_price, color, dash_style
          FROM stock_price_zone
          WHERE code = '$code' AND name = '$name'";
$result = $mysqli->query($query);

$zones = [];
while ($row = $result->fetch_assoc()) {
    // start_price를 추가
    $zones[] = [
        'zone_type' => $row['zone_type'],
        'price' => $row['start_price'],
        'color' => $row['color'],
        'dash_style' => $row['dash_style']
    ];
    // end_price가 존재하면 추가
    if (!empty($row['end_price'])) {
        $zones[] = [
            'zone_type' => $row['zone_type'],
            'price' => $row['end_price'],
            'color' => $row['color'],
            'dash_style' => $row['dash_style']
        ];
    }
}

echo json_encode(['data' => $data, 'zones' => $zones]);
?>
