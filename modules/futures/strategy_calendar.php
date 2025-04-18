<?php
// strategy_calendar.php — 전략 A 백테스트 달력 + 차트 대시보드
$pageTitle = "전략 성과 달력";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header.php";

/* ------------------------------------------------------------------
 * 1. 년도·월 파라미터 처리
 * ------------------------------------------------------------------*/
$year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year  = max(2020, min(2030, $year));
$month = max(1,    min(12,   $month));

$firstDay = new DateTime("$year-$month-01");
$lastDay  = (clone $firstDay)->modify('last day of this month');

/* ------------------------------------------------------------------
 * 2. DB 조회 — 당월 전략 성공/실패 계산
 * ------------------------------------------------------------------*/

// 3개월 달력 생성
$calendars = [];
for ($i = 0; $i < 3; $i++) {
    $ym     = (new DateTime("$year-$month-01"))->modify("+{$i} month");
    $first  = new DateTime($ym->format('Y-m-01'));
    $last   = (clone $first)->modify('last day of this month');
    $label  = $first->format('Y-m');
    $status = [];

    for ($d = 1; $d <= (int) $last->format('j'); $d++) {
        $ds = sprintf('%04d-%02d-%02d', $first->format('Y'), $first->format('n'), $d);
        $status[$ds] = 'none';

        // 전략 결과 계산
        $sql = "SELECT TIME_FORMAT(time,'%H:%i') AS t, open, close
                FROM futures_1min
                WHERE date = ? AND time IN ('08:55:00','08:59:00','09:00:00','09:01:00')";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $ds);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($rows) < 4) continue;

        $map = [];
        foreach ($rows as $r) $map[$r['t']] = $r;
        $dir = ($map['08:59']['close'] > $map['08:55']['open']) ? 1 : -1;
        $pnl = ($map['09:01']['close'] - $map['09:00']['open']) * $dir;
        $status[$ds] = $pnl > 0 ? 'success' : ($pnl < 0 ? 'fail' : 'even');
    }

    $calendars[] = [
        'label' => $label,
        'first' => $first,
        'last'  => $last,
        'status'=> $status
    ];
}

$prev = (clone $firstDay)->modify('-1 month');
$next = (clone $firstDay)->modify('+1 month');
?>

<!-- ----------------------------------------------------------------
     3. CSS & 레이아웃
------------------------------------------------------------------ -->
<style>
/* 컨테이너 : 좌측 달력 / 우측 차트 */
#container {
    display: flex;
    height: calc(100vh - 60px);
    margin-left: 100px;          /* 메뉴바 폭 */
    width:  calc(100% - 100px);
}

/* ───── 달력 패널 ───── */
#side {
    width: 260px;
    padding: 10px;
    border-right: 1px solid #ccc;
}
#side select,
#side button {
    height: 30px;
    font-size: 13px;
}

#calendar-wrap { display: flex; flex-direction: column; gap: 20px; }
.calendar-box table { width: 100%; border-collapse: collapse; font-size: 12px; }
.calendar-box th, .calendar-box td {
    border: 1px solid #ccc;
    text-align: center;
    height: 60px;
    position: relative;
    cursor: pointer;
}
.calendar-box td.success { background: #d4edda; }
.calendar-box td.fail    { background: #f8d7da; }
.calendar-box td.even    { background: #fff3cd; }
.calendar-box td.none    { background: #fff; }
.calendar-box div.day { position: absolute; top: 2px; right: 2px; font-size: 11px; color: #666; }

/* ───── 차트 영역 : 2×1 그리드 (하단 1분봉 넓게) ───── */
#chart-area {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;   /* 상단 좌/우 */
    grid-template-rows: 260px 1fr;    /* 상단 고정, 하단 확장 */
    gap: 10px;
    padding: 10px;
}
#chart-area > div {
    border: 1px solid #ccc;
    min-height: 200px;
}
#chart-1m { grid-column: 1 / 3; }      /* 하단 전체 폭 */
</style>

<!-- ----------------------------------------------------------------
     4. HTML 구조
------------------------------------------------------------------ -->
<div id="container">
    <!-- 달력 패널 -->
    <div id="side">
        <!-- 년·월 네비게이션 -->
        <div style="display:flex;gap:6px;margin-bottom:8px;">
            <button onclick="navMonth(<?= $prev->format('Y') ?>, <?= $prev->format('n') ?>)">◀</button>

            <select id="ySel" onchange="gotoMonth()">
                <?php for ($y = 2020; $y <= 2030; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <select id="mSel" onchange="gotoMonth()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $m ?>월</option>
                <?php endfor; ?>
            </select>

            <button onclick="navMonth(<?= $next->format('Y') ?>, <?= $next->format('n') ?>)">▶</button>
        </div>

        <!-- 달력 테이블 -->
        <div id="calendar-wrap">
            <?php foreach ($calendars as $cal): ?>
                <div class="calendar-box">
                    <h4 style="margin:5px 0; padding-left:5px;">📅 <?= $cal['label'] ?></h4>
                    <table>
                        <thead><tr>
                            <th>일</th><th>월</th><th>화</th><th>수</th><th>목</th><th>금</th><th>토</th>
                        </tr></thead>
                        <tbody>
                        <?php
                            $wd  = (int) $cal['first']->format('w');
                            $tot = (int) $cal['last']->format('j');
                            $cnt = 0;
                            echo '<tr>';
                            for ($i = 0; $i < $wd; $i++) { echo '<td></td>'; $cnt++; }
                            for ($d = 1; $d <= $tot; $d++) {
                                $ds  = sprintf('%04d-%02d-%02d', $cal['first']->format('Y'), $cal['first']->format('n'), $d);
                                $cls = $cal['status'][$ds] ?? 'none';
                                echo "<td class='$cls' onclick=loadCharts('$ds')><div class='day'>$d</div></td>";
                                if (++$cnt % 7 == 0) echo '</tr><tr>';
                            }
                            while ($cnt % 7 !== 0) { echo '<td></td>'; $cnt++; }
                            echo '</tr>';
                        ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<!-- 전략 수치 영역 -->
<div id="strategy-metrics" style="grid-column: 1 / 3; padding: 10px; border: 1px solid #ccc; font-size: 13px; line-height: 1.6;">
    <h4>📊 전략 분석 수치</h4>
    <div id="metric-content">일자를 선택하면 전략 수치가 이곳에 표시됩니다.</div>
</div>
 <!-- 차트 영역 -->
    <div id="chart-area">
        <div id="chart-5m"></div>    <!-- 5분봉 -->
        <div id="chart-day"></div>   <!-- 1일봉 -->
        <div id="chart-1m"></div>    <!-- 1분봉 -->
    </div>
</div>

<!-- ----------------------------------------------------------------
     5. JavaScript (Highcharts)
------------------------------------------------------------------ -->
<script src="https://code.highcharts.com/stock/highstock.js"></script>
<script src="https://code.highcharts.com/stock/indicators/indicators.js"></script>
<script src="https://code.highcharts.com/stock/indicators/sma.js"></script>

<script>
/* ───────── 달력 네비게이션 ───────── */
function navMonth(y, m) { location.href = `?year=${y}&month=${m}`; }
function gotoMonth()    { navMonth(ySel.value, mSel.value); }

/* ───────── AJAX 캔들 조회 */
async function fetchCandle(dateStr, tf) {
    const res = await fetch(`/modules/futures/get_candles.php?date=${dateStr}&tf=${tf}`);
    try { return await res.json(); } catch (e) { return []; }
}

Highcharts.setOptions({
    time: {
        timezoneOffset: -540 // 한국은 UTC+9 → -9×60
    }
});
/* ───────── 차트 렌더 */
function renderChart(divId, dataArr) {
    const el = document.getElementById(divId);

    if (el.chart) el.chart.destroy();

    if (!Array.isArray(dataArr) || dataArr.length === 0) {
        el.innerHTML = '<p style="text-align:center;padding-top:40px;color:#888;">No data</p>';
        return;
    }

    el.innerHTML = '';

    // ✅ 문자열 → 숫자로 변환
    const cleanData = dataArr.map(row => [
        row[0],
        parseFloat(row[1]),
        parseFloat(row[2]),
        parseFloat(row[3]),
        parseFloat(row[4])
    ]);

    el.chart = Highcharts.stockChart(el, {
        chart: {
            height: (divId === 'chart-1m' ? 350 : 250)
        },
        rangeSelector: { enabled: false },
        navigator: { enabled: false },
        title: { text: '' },
        xAxis: {
            type: 'datetime'
        },
        tooltip: {
            split: false,
            shared: true,
            valueDecimals: 2,
            pointFormat: '<b>O:</b>{point.open} <b>H:</b>{point.high} <b>L:</b>{point.low} <b>C:</b>{point.close}'
        },
        plotOptions: {
            candlestick: {
                dataGrouping: { enabled: false },
                color: '#0066ff',
                lineColor: '#0066ff',
                upColor: '#ff3333',
                upLineColor: '#ff3333'
            }
        },
        series: [
            {
                type: 'candlestick',
                id: 'price',
                name: 'Price',
                zIndex: 5, // 캔들의 zIndex를 높게 설정
                data: cleanData
            },
            {
                type: 'sma',
                linkedTo: 'price',
                params: { period: 5 },
                dashStyle: 'solid',
                color: '#f7a35c'
            },
            {
                type: 'sma',
                linkedTo: 'price',
                params: { period: 20 },
                dashStyle: 'solid',
                color: '#90ed7d'
            },
            {
                type: 'sma',
                linkedTo: 'price',
                params: { period: 120 },
                dashStyle: 'solid',
                color: '#8085e9'
            }
        ]
    });
}

/* ───────── 날짜 클릭 시 차트 로드 */
async function loadCharts(dateStr) {
    const [r1d, r5, r1m] = await Promise.all([
        fetchCandle(dateStr, '1day'),
        fetchCandle(dateStr, '5m'),
        fetchCandle(dateStr, '1m')
    ]);

    const d1day = r1d.candles || [];
    const d5 = r5.candles || [];
    const d1m = r1m.candles || [];

    renderChart('chart-day', d1day);
    renderChart('chart-5m', d5);
    renderChart('chart-1m', d1m);

    // ✅ 전략 수치 계산 (08:45~08:59 5분봉 3개 + 09:00~09:01 1분봉 2개)
    const f5 = d5.filter(row => {
        const t = new Date(row[0]);
        const h = t.getHours(), m = t.getMinutes();
        return (h === 8 && m >= 45) || (h === 8 && m <= 59);
    });

    const f1 = d1m.filter(row => {
        const t = new Date(row[0]);
        return t.getHours() === 9 && (t.getMinutes() === 0 || t.getMinutes() === 1);
    });

    const formatPt = n => (Math.round(n * 100) / 100).toFixed(2);

    const gap = d1day.length >= 2 ? formatPt(d1day[d1day.length - 1][1] - d1day[d1day.length - 2][4]) : '-';
    const vol_5min = f5.map(r => r[2] - r[3]);  // high - low
    const vol_1min = f1.map(r => r[2] - r[3]);

    document.getElementById('metric-content').innerHTML = `
        📅 <b>${dateStr}</b><br>
        🕘 시가 갭 (전일 종가 대비): <b>${gap}pt</b><br>
        🔄 5분봉 08:45~08:59 변동폭: <b>${vol_5min.map(formatPt).join(', ')}</b><br>
        ⏱️ 1분봉 09:00~09:01 변동폭: <b>${vol_1min.map(formatPt).join(', ')}</b>
    `;
}
</script>

<?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"; ?>
