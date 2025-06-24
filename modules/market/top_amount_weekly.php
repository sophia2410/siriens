<?php
$pageTitle = "2주 단위 종목별 등장 횟수 요약";
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php");

$startMonth = isset($_GET['from']) ? $_GET['from'] : date('Y-m', strtotime('-6 months'));
$endMonth = isset($_GET['to']) ? $_GET['to'] : date('Y-m');
$onlyUp = $_GET['only_up'] ?? true;

$startDate = $startMonth . '-01';
$endDate = date('Y-m-t', strtotime($endMonth . '-01'));
$cutoffDate = date('Y-m-d', strtotime('-3 years', strtotime($endDate)));

// 상단 쿼리
$sqlVisible = "
    SELECT
        CONCAT(DATE_FORMAT(dar.date, '%Y-%m'), '-', IF(DAY(dar.date) <= 15, '1', '2')) AS period_key,
        dar.code,
        s.name,
        COUNT(*) AS cnt,
        MIN(dar.date) AS first_date
    FROM daily_amount_rank dar
    LEFT JOIN stock s ON dar.code = s.code AND s.last_yn = 'Y'
    WHERE dar.date BETWEEN ? AND ? AND dar.rank <= 30
";
if ($onlyUp) {
    $sqlVisible .= " AND dar.close_rate > 0";
}
$sqlVisible .= "
    GROUP BY period_key, dar.code
    HAVING cnt >= 5
    ORDER BY period_key ASC, cnt DESC
";

$stmtVisible = $mysqli->prepare($sqlVisible);
$stmtVisible->bind_param("ss", $startDate, $endDate);
$stmtVisible->execute();
$resVisible = $stmtVisible->get_result();

$visiblePeriodMap = [];
$firstAppearance = [];
while ($row = $resVisible->fetch_assoc()) {
    $periodKey = $row['period_key'];
    $visiblePeriodMap[$periodKey][] = $row;
    if (!isset($firstAppearance[$row['code']])) {
        $firstAppearance[$row['code']] = $periodKey;
    }
}

// 하단 쿼리 (최근 3년치만)
$sqlAll = "
    SELECT
        dar.code,
        s.name,
        CONCAT(DATE_FORMAT(dar.date, '%Y-%m'), '-', IF(DAY(dar.date) <= 15, '1', '2')) AS period_key,
        COUNT(*) AS cnt,
        SUM(CASE WHEN dar.close_rate > 0 THEN 1 ELSE 0 END) AS up_count,
        SUM(CASE WHEN dar.close_rate < 0 THEN 1 ELSE 0 END) AS down_count,
        MIN(dar.date) AS first_date
    FROM daily_amount_rank dar
    LEFT JOIN stock s ON dar.code = s.code AND s.last_yn = 'Y'
    WHERE dar.rank <= 30 AND dar.date BETWEEN ? AND ? 
    GROUP BY dar.code, period_key
    HAVING cnt >= 3
    ORDER BY first_date ASC
";

$stmtAll = $mysqli->prepare($sqlAll);
$stmtAll->bind_param("ss", $cutoffDate, $endDate);
$stmtAll->execute();
$resAll = $stmtAll->get_result();

$stockDetailMap = [];
$stockTotalCount = [];
$allPeriods = [];

while ($row = $resAll->fetch_assoc()) {
    $code = $row['code'];
    $period = $row['period_key'];
    $stockDetailMap[$code]['name'] = $row['name'];
    $stockDetailMap[$code]['data'][$period] = [
        'cnt' => $row['cnt'],
        'up' => $row['up_count'],
        'down' => $row['down_count'],
    ];
    $allPeriods[$period] = true;
    $stockTotalCount[$code] = ($stockTotalCount[$code] ?? 0) + $row['cnt'];
}
ksort($allPeriods);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/common.css">
    <style>
        body { margin: 0; padding: 0; }
        h2 { margin: 20px 0 10px 0; }
        form { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        #container { display: flex; flex-direction: column; height: calc(100vh - 80px); }
        #top-panel { flex: 1; overflow: auto; border-bottom: 2px solid #ccc; padding: 0 10px 10px 10px; }
        #bottom-panel { height: 300px; overflow: auto; padding: 10px; }
        .periods-wrapper { display: flex; flex-direction: row; gap: 10px; padding-left: 0; }
        .period-section { min-width: 200px; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 10px; }
        .period-title { font-weight: bold; font-size: 15px; margin-bottom: 8px; }
        .stock-box { padding: 8px 10px; border-radius: 6px; background: #f1f1f1; color: #333; margin-bottom: 6px; cursor: pointer; font-size: 13px; position: relative; }
        .stock-box.first-appearance { border-left: 6px solid #2ecc71; }
        .stock-box[data-code]:hover, .stock-box[data-code].highlight { background-color: #d1ecf1 !important; }
        .appearance-count { position: absolute; top: 4px; right: 6px; font-size: 11px; color: #999; }
        table { border-collapse: collapse; width: 100%; min-width: 600px; }
        table th, table td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; font-size: 13px; }
        table th { background: #eee; }
    </style>
    <script>
        const stockDetailMap = <?= json_encode($stockDetailMap) ?>;
        const allPeriods = <?= json_encode(array_keys($allPeriods)) ?>;

        const groupedPeriods = {};
        allPeriods.forEach(p => {
            const [y, m, half] = p.split(/[-]/);
            const key = `${y.slice(2)}/${m}`;
            groupedPeriods[key] = groupedPeriods[key] || {};
            groupedPeriods[key][half] = p;
        });

        function renderStockDetailTable(code) {
            const detail = stockDetailMap[code];
            if (!detail) return;

            let html = `<h3>${detail.name} (코드: ${code})</h3>`;
            html += '<div style="overflow-x: auto;"><table><thead>';
            html += '<tr><th>기간</th>';
            Object.keys(groupedPeriods).forEach(g => {
                html += `<th>${g}</th>`;
            });
            html += '</tr></thead><tbody>';

            html += '<tr><td>1주</td>';
            Object.values(groupedPeriods).forEach(p => {
                const d = detail.data[p['1']];
                html += `<td>${d ? '✅ ' + d.cnt + '회 (' + d.up + '/' + d.down + ')' : ''}</td>`;
            });
            html += '</tr><tr><td>2주</td>';
            Object.values(groupedPeriods).forEach(p => {
                const d = detail.data[p['2']];
                html += `<td>${d ? '✅ ' + d.cnt + '회 (' + d.up + '/' + d.down + ')' : ''}</td>`;
            });

            html += '</tr></tbody></table></div>';
            document.getElementById('stock-detail').innerHTML = html;
        }

        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.stock-box').forEach(box => {
                const code = box.dataset.code;
                box.addEventListener('click', () => {
                    renderStockDetailTable(code);
                    document.querySelectorAll('.stock-box').forEach(b => b.classList.remove('highlight'));
                    document.querySelectorAll(`.stock-box[data-code="${code}"]`).forEach(b => b.classList.add('highlight'));
                });
            });
        });
    </script>
</head>
<body>
<?php include($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_nav_menu.php"); ?>
<div id="content">
    <h2>2주 단위 종목별 등장 횟수 (Top 30 중, 5회 이상)</h2>
    <form method="get">
        <label>From <input type="month" name="from" value="<?= htmlspecialchars($startMonth) ?>"></label>
        <label>To <input type="month" name="to" value="<?= htmlspecialchars($endMonth) ?>"></label>
        <label><input type="checkbox" name="only_up" value="1" <?= $onlyUp ? 'checked' : '' ?>> 상승 마감만 보기</label>
        <button type="submit">조회</button>
    </form>
    <div id="container">
        <div id="top-panel">
            <div class="periods-wrapper">
                <?php foreach ($visiblePeriodMap as $period => $stocks): ?>
                    <div class="period-section">
                        <div class="period-title">📅 
                            <?php
                            $parts = explode('-', $period);
                            echo "{$parts[0]}년 {$parts[1]}월 {$parts[2]}주차";
                            ?>
                        </div>
                        <?php foreach ($stocks as $info):
                            $isFirst = ($period === $firstAppearance[$info['code']]);
                            $boxClass = $isFirst ? 'stock-box first-appearance' : 'stock-box';
                            $totalCount = $stockTotalCount[$info['code']] ?? '';
                        ?>
                            <div class="<?= $boxClass ?>" data-code="<?= $info['code'] ?>" title="처음 등장: <?= $firstAppearance[$info['code']] ?>">
                                <?= htmlspecialchars($info['name']) ?> : <?= $info['cnt'] ?>회
                                <span class="appearance-count">총 <?= $totalCount ?>회</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div id="bottom-panel">
            <div id="stock-detail">※ 종목을 클릭하면 상세 내역이 이곳에 표시됩니다.</div>
        </div>
    </div>
</div>
</body>
</html>
