<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

// 파라미터: 시작일, 종료일, 분봉 선택
$start_date = $_GET['start'] ?? date('Y-m-d');
$end_date = $_GET['end'] ?? $start_date;
$minute_type = $_GET['minute'] ?? '5min';


// 테이블명과 컬럼 접두어 결정
$table_name = "futures_{$minute_type}";
$prefix = $minute_type === '1day' ? '1day' : "{$minute_type}";

// INTERVAL 설정
$interval_min_map = [
    '1min' => 0,
    '5min' => 4,
    '10min' => 9,
    '15min' => 14,
    '60min' => 59,
    '1day' => 0, // 별도로 처리 가능
];

$interval_min = $interval_min_map[$minute_type] ?? 0;
$interval_sql = "INTERVAL {$interval_min} MINUTE";

// 쿼리 실행

$is_1day = ($minute_type === '1day');

if ($is_1day) {
    $sql = "
    SELECT
        f.date,
        f.close              AS original_close,
        f.sma_5              AS original_sma_5,
        f.sma_20             AS original_sma_20,
        f.sma_120            AS original_sma_120,
        s.price              AS snapshot_price,
        s.sma_1day_5         AS snapshot_sma_5,
        s.sma_1day_20        AS snapshot_sma_20,
        s.sma_1day_120       AS snapshot_sma_120,
        CASE WHEN TRUNCATE(f.close, 2) = TRUNCATE(s.price, 2) THEN '✅' ELSE '❌' END AS close_match,
        CASE WHEN TRUNCATE(f.sma_5, 2) = TRUNCATE(s.sma_1day_5, 2) THEN '✅' ELSE '❌' END AS sma5_match,
        CASE WHEN TRUNCATE(f.sma_20, 2) = TRUNCATE(s.sma_1day_20, 2) THEN '✅' ELSE '❌' END AS sma20_match,
        CASE WHEN TRUNCATE(f.sma_120, 2) = TRUNCATE(s.sma_1day_120, 2) THEN '✅' ELSE '❌' END AS sma120_match
    FROM futures_1day f
    JOIN futures_snapshot_momentum s 
        ON f.date = s.date AND s.time = '15:45:00'
    WHERE f.date BETWEEN ? AND ?
    ORDER BY f.date
    ";
} else {
    // 기존 분봉용 쿼리
    $interval_sql = "INTERVAL {$interval_min} MINUTE";
    $sql = "
    SELECT
        f.date,
        f.time AS f_time,
        DATE_ADD(f.datetime, $interval_sql) AS expected_snapshot_time,
        s.time AS snapshot_time,

        f.close              AS original_close,
        f.sma_5              AS original_sma_5,
        f.sma_20             AS original_sma_20,
        f.sma_120            AS original_sma_120,

        s.price              AS snapshot_price,
        s.sma_{$prefix}_5    AS snapshot_sma_5,
        s.sma_{$prefix}_20   AS snapshot_sma_20,
        s.sma_{$prefix}_120  AS snapshot_sma_120,

        CASE WHEN TRUNCATE(f.close, 2) = TRUNCATE(s.price, 2) THEN '✅' ELSE '❌' END AS close_match,
        CASE WHEN TRUNCATE(f.sma_5, 2) = TRUNCATE(s.sma_{$prefix}_5, 2) THEN '✅' ELSE '❌' END AS sma5_match,
        CASE WHEN TRUNCATE(f.sma_20, 2) = TRUNCATE(s.sma_{$prefix}_20, 2) THEN '✅' ELSE '❌' END AS sma20_match,
        CASE WHEN TRUNCATE(f.sma_120, 2) = TRUNCATE(s.sma_{$prefix}_120, 2) THEN '✅' ELSE '❌' END AS sma120_match

    FROM {$table_name} f
    JOIN futures_snapshot_momentum s 
        ON s.datetime = DATE_ADD(f.datetime, $interval_sql)
    WHERE f.date BETWEEN ? AND ?
    ORDER BY f.datetime
    ";
}

// echo "<pre>$sql</pre>";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>📊 이평선 비교 결과</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 4px 8px; border: 1px solid #ccc; text-align: center; }
        th { background-color: #f5f5f5; }
        .fail { color: red; }
        .pass { color: green; }
    </style>
</head>
<body>
    <h2>📈 <?= htmlspecialchars($minute_type) ?> 이평선 비교 (<?= $start_date ?> ~ <?= $end_date ?>)</h2>

    <form method="get" style="margin-bottom: 20px;">
        시작일: <input type="date" name="start" value="<?= $start_date ?>">
        종료일: <input type="date" name="end" value="<?= $end_date ?>">
        분봉: 
        <select name="minute">
            <?php foreach (['1min','5min','10min','15min','60min','1day'] as $min): ?>
                <option value="<?= $min ?>" <?= $minute_type === $min ? 'selected' : '' ?>><?= $min ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">조회</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>일자</th><th>시간</th><th>스냅샷 시간</th>
                <th>Close</th><th>SMA5</th><th>SMA20</th><th>SMA120</th>
                <th>Snapshot Price</th><th>Snapshot SMA5</th><th>SMA20</th><th>SMA120</th>
                <th>종가</th><th>SMA5</th><th>SMA20</th><th>SMA120</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= $row['date'] ?></td>

                <?php if (!$is_1day): ?>
                    <td><?= $row['f_time'] ?></td>
                    <td><?= $row['snapshot_time'] ?></td>
                <?php else: ?>
                    <td colspan="2" style="text-align:center;">-</td>
                <?php endif; ?>

                <td><?= $row['original_close'] ?></td>
                <td><?= $row['original_sma_5'] ?></td>
                <td><?= $row['original_sma_20'] ?></td>
                <td><?= $row['original_sma_120'] ?></td>

                <td><?= $row['snapshot_price'] ?></td>
                <td><?= $row['snapshot_sma_5'] ?></td>
                <td><?= $row['snapshot_sma_20'] ?></td>
                <td><?= $row['snapshot_sma_120'] ?></td>

                <td class="<?= $row['close_match'] === '✅' ? 'pass' : 'fail' ?>"><?= $row['close_match'] ?></td>
                <td class="<?= $row['sma5_match'] === '✅' ? 'pass' : 'fail' ?>"><?= $row['sma5_match'] ?></td>
                <td class="<?= $row['sma20_match'] === '✅' ? 'pass' : 'fail' ?>"><?= $row['sma20_match'] ?></td>
                <td class="<?= $row['sma120_match'] === '✅' ? 'pass' : 'fail' ?>"><?= $row['sma120_match'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
