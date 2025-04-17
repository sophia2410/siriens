<?php
$pageTitle = "체결 상세 조회";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header_sub.php");

$date = $_GET['date'] ?? date('Y-m-d');
$code = $_GET['code'] ?? '';
$time = $_GET['time'] ?? '';
$export = $_GET['export'] ?? '';

if (!$code || !$time) {
    echo "<p style='margin-left:100px;'>code 또는 time 파라미터가 누락되었습니다.</p>";
    require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
    exit;
}

$from = str_replace(':', '', date("H:i:s", strtotime("$time -5 minutes")));
$to = str_replace(':', '', date("H:i:s", strtotime("$time +2 minutes")));
$sql = "SELECT time, price, volume FROM futures_realtime_tick WHERE code='$code' AND date='$date' AND time BETWEEN '$from' AND '$to' ORDER BY time ASC";
$result = $mysqli->query($sql);
$ticks = $result->fetch_all(MYSQLI_ASSOC);

if ($export === 'csv') {
    header('Content-Type: text/plain; charset=UTF-8');
    header("Content-Disposition: attachment; filename=tick_detail_{$code}_{$date}_{$time}_detail.txt");
    echo "Time\tPrice\tVolume\n";
    foreach ($ticks as $row) {
        $t = substr($row['time'], 0, 2) . ":" . substr($row['time'], 2, 2) . ":" . substr($row['time'], 4, 2);
        echo "$t\t{$row['price']}\t{$row['volume']}\n";
    }
    exit;
}
?>

<style>
#tick-header {
    font-size: 15px;
    font-weight: bold;
    padding: 10px 0 5px;
}
#tick-download {
    padding: 0 0 10px;
}
</style>

<div id="tick-download"  style="display: flex; flex-wrap: nowrap; align-items: center; gap: 10px;">
    <a href="?code=<?= $code ?>&date=<?= $date ?>&time=<?= $time ?>&export=csv">CSV 다운로드</a>
</div>

<table>
    <thead>
        <tr>
            <th>시간</th>
            <th>가격</th>
            <th>체결량</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($ticks as $row): ?>
        <tr>
            <td><?= substr($row['time'], 0, 2) . ":" . substr($row['time'], 2, 2) . ":" . substr($row['time'], 4, 2) ?></td>
            <td><?= number_format($row['price'], 2) ?></td>
            <td><?= number_format($row['volume']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
