<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
session_start();

/*
 * 장대 5분봉 전용 모아보기
 *
 * 카테고리
 *  - early_up   : 장초반 장대양봉 (09:05~09:30)
 *  - early_down : 장초반 장대음봉 (09:05~09:30)
 *  - early_none : 장초반 장대봉 없음
 *  - late_up    : 장후반 장대양봉 (12:30~15:00)
 *  - late_down  : 장후반 장대음봉 (12:30~15:00)
 *  - late_none  : 장후반 장대봉 없음
 *
 * 장초반 기준
 *  - 후보시간: 09:05~09:30
 *  - 기준값: 직전 20거래일 각각의 09:00~10:00 5분봉 몸통 중앙값을 구한 뒤,
 *            그 20개 일별 중앙값의 중앙값
 *  - 후보봉 몸통 >= 기준값 × 2.0
 *  - 몸통 / 전체 고저폭 >= 65%
 *  - 현재일/미래봉은 기준 계산에 사용하지 않음
 *
 * 장후반 기준
 *  - 후보시간: 12:30~15:00
 *  - 20일 기준 사용하지 않음
 *  - 후보봉 몸통 >= 당일 직전 60분(12개 5분봉)의 평균 전체 변동폭(high-low) × 2.0
 *  - 몸통 / 전체 고저폭 >= 70%
 *
 * 설계 의도
 *  - 장초반은 원래 변동성이 크므로 동일시각끼리 비교하지 않고
 *    최근 20거래일의 첫 1시간(09:00~10:00) 분포와 비교
 *  - 장후반은 그날의 장세/변동성에 맞춰 최근 30분 대비 갑자기 커진 봉만 포착
 */

$CATEGORY_LABELS = [
    'early_up'   => '장초반 장대양봉',
    'early_down' => '장초반 장대음봉',
    'early_none' => '장초반 장대봉 없음',
    'late_up'    => '장후반 장대양봉',
    'late_down'  => '장후반 장대음봉',
    'late_none'  => '장후반 장대봉 없음',
];

$category = $_GET['category'] ?? 'early_up';
if (!isset($CATEGORY_LABELS[$category])) $category = 'early_up';

$fromDate = '2025-01-01';

$earlyLookbackDays = 20;
$earlyMultiple = 2.0;
$earlyBodyRangeMin = 0.65;

$lateLookbackBars = 12;       // 직전 60분
$lateMultiple = 2.0;
$lateBodyRangeMin = 0.70;

// 직전 20거래일 기준을 2025-01-01부터 최대한 빨리 확보하기 위해
// 표시 시작일보다 90일 앞선 데이터도 내부 계산용으로 읽는다.
$historyFromDate = (new DateTimeImmutable($fromDate))
    ->modify('-90 days')
    ->format('Y-m-d');

$sql = "
    SELECT
        DATE(datetime) AS trade_date,
        datetime,
        open,
        high,
        low,
        close,
        volume
    FROM futures_5min
    WHERE datetime >= ?
      AND TIME(datetime) BETWEEN '09:00:00' AND '15:30:00'
    ORDER BY datetime ASC
";

$stmt = $mysqli->prepare($sql);
$historyFromDateTime = $historyFromDate . ' 00:00:00';
$stmt->bind_param('s', $historyFromDateTime);
$stmt->execute();
$res = $stmt->get_result();

$byDate = [];
while ($row = $res->fetch_assoc()) {
    $d = $row['trade_date'];
    if (!isset($byDate[$d])) $byDate[$d] = [];

    foreach (['open','high','low','close','volume'] as $k) {
        $row[$k] = (float)$row[$k];
    }

    $row['body'] = abs($row['close'] - $row['open']);
    $row['range'] = max(0.0, $row['high'] - $row['low']);
    $row['body_ratio'] = $row['range'] > 0
        ? $row['body'] / $row['range']
        : 0.0;

    $row['dir'] = $row['close'] > $row['open']
        ? 'up'
        : ($row['close'] < $row['open'] ? 'down' : 'flat');

    $row['time'] = substr($row['datetime'], 11, 5);

    $byDate[$d][] = $row;
}
$stmt->close();

ksort($byDate);

function medianOf(array $values): float {
    $values = array_values(array_filter(
        $values,
        static fn($v) => is_numeric($v)
    ));

    $n = count($values);
    if ($n === 0) return 0.0;

    sort($values, SORT_NUMERIC);
    $mid = intdiv($n, 2);

    if ($n % 2 === 1) return (float)$values[$mid];

    return ((float)$values[$mid - 1] + (float)$values[$mid]) / 2.0;
}

function meanOf(array $values): float {
    $values = array_values(array_filter(
        $values,
        static fn($v) => is_numeric($v)
    ));

    if (!$values) return 0.0;
    return array_sum($values) / count($values);
}

function lastN(array $values, int $n): array {
    if ($n <= 0) return [];
    return count($values) <= $n ? $values : array_slice($values, -$n);
}

function candleMeta(
    array $bar,
    float $base,
    int $lookbackCount,
    string $basisType
): array {
    $ratio = $base > 0 ? ($bar['body'] / $base) : null;

    return [
        'datetime' => $bar['datetime'],
        'time' => $bar['time'],
        'dir' => $bar['dir'],
        'open' => $bar['open'],
        'high' => $bar['high'],
        'low' => $bar['low'],
        'close' => $bar['close'],
        'body' => $bar['body'],
        'range' => $bar['range'],
        'body_ratio' => $bar['body_ratio'],
        'base' => $base,
        'relative_ratio' => $ratio,
        'lookback_count' => $lookbackCount,
        'basis_type' => $basisType,
        'volume' => $bar['volume'],
    ];
}

$eventsByCategory = [
    'early_up' => [],
    'early_down' => [],
    'early_none' => [],
    'late_up' => [],
    'late_down' => [],
    'late_none' => [],
];

$earlyExpectedTimes = [
    '09:05', '09:10', '09:15', '09:20', '09:25', '09:30'
];

$lateExpectedTimes = [];
for ($h = 12, $m = 30; ; ) {
    $lateExpectedTimes[] = sprintf('%02d:%02d', $h, $m);
    if ($h === 15 && $m === 0) break;

    $m += 5;
    if ($m >= 60) {
        $m = 0;
        $h++;
    }
}

// 직전 거래일별 09:00~10:00 "일별 몸통 중앙값" 히스토리
$earlyDailyMedianHistory = [];

foreach ($byDate as $date => $bars) {
    $isDisplayDate = ($date >= $fromDate);

    // -----------------------------
    // 1) 장초반 기준값: 직전 20거래일의 첫 1시간 일별 중앙값의 중앙값
    // -----------------------------
    $earlyBase = null;

    if (count($earlyDailyMedianHistory) >= $earlyLookbackDays) {
        $prev20 = lastN($earlyDailyMedianHistory, $earlyLookbackDays);
        $earlyBase = medianOf($prev20);
    }

    $earlyActualCount = 0;
    $lateActualCount = 0;

    // 빠른 검색을 위해 시간=>봉
    $barsByTime = [];
    foreach ($bars as $b) {
        $barsByTime[$b['time']] = $b;
    }

    // -----------------------------
    // 2) 장초반 이벤트 판정
    // -----------------------------
    foreach ($earlyExpectedTimes as $time) {
        if (!isset($barsByTime[$time])) continue;

        $earlyActualCount++;
        $bar = $barsByTime[$time];

        if (
            $isDisplayDate
            && $earlyBase !== null
            && $earlyBase > 0
            && $bar['dir'] !== 'flat'
            && $bar['body_ratio'] >= $earlyBodyRangeMin
            && $bar['body'] >= $earlyBase * $earlyMultiple
        ) {
            $key = $bar['dir'] === 'up' ? 'early_up' : 'early_down';

            $eventsByCategory[$key][$date][] = candleMeta(
                $bar,
                $earlyBase,
                $earlyLookbackDays,
                'prev20days_0900_1000_daily_body_median_of_medians'
            );
        }
    }

    // -----------------------------
    // 3) 장후반 이벤트 판정
    //    각 후보봉 직전 6개 5분봉의 평균 "전체 range"와 비교
    // -----------------------------
    foreach ($lateExpectedTimes as $time) {
        if (!isset($barsByTime[$time])) continue;

        $lateActualCount++;
        $bar = $barsByTime[$time];

        // 현재 후보봉보다 이전 봉들만 사용
        $prevBars = [];
        foreach ($bars as $b) {
            if ($b['datetime'] < $bar['datetime']) {
                $prevBars[] = $b;
            }
        }

        if (count($prevBars) < $lateLookbackBars) continue;

        $prev6 = array_slice($prevBars, -$lateLookbackBars);
        $prev6Ranges = array_map(
            static fn($b) => $b['range'],
            $prev6
        );

        $lateBase = meanOf($prev6Ranges);

        if (
            $isDisplayDate
            && $lateBase > 0
            && $bar['dir'] !== 'flat'
            && $bar['body_ratio'] >= $lateBodyRangeMin
            && $bar['body'] >= $lateBase * $lateMultiple
        ) {
            $key = $bar['dir'] === 'up' ? 'late_up' : 'late_down';

            $eventsByCategory[$key][$date][] = candleMeta(
                $bar,
                $lateBase,
                $lateLookbackBars,
                'same_day_prev30m_avg_range'
            );
        }
    }

    // -----------------------------
    // 4) 장대봉 없음
    // -----------------------------
    if ($isDisplayDate) {
        $earlyReady = (
            $earlyBase !== null
            && $earlyBase > 0
            && $earlyActualCount >= count($earlyExpectedTimes)
        );

        // 12:30 이후는 정상 장이면 각 후보마다 직전 6봉이 확보되므로
        // 후보시간 봉들이 모두 존재하면 없음 판정 가능
        $lateReady = (
            $lateActualCount >= count($lateExpectedTimes)
        );

        if (
            $earlyReady
            && empty($eventsByCategory['early_up'][$date])
            && empty($eventsByCategory['early_down'][$date])
        ) {
            $eventsByCategory['early_none'][$date] = [];
        }

        if (
            $lateReady
            && empty($eventsByCategory['late_up'][$date])
            && empty($eventsByCategory['late_down'][$date])
        ) {
            $eventsByCategory['late_none'][$date] = [];
        }
    }

    // -----------------------------
    // 5) 당일 판정 종료 후, 오늘 09:00~10:00 일별 중앙값을 히스토리에 추가
    //    → 오늘 후보 판정에 오늘/미래 봉이 섞이지 않음
    // -----------------------------
    $todayEarlyBodies = [];

    foreach ($bars as $bar) {
        if ($bar['time'] >= '09:00' && $bar['time'] <= '10:00') {
            $todayEarlyBodies[] = $bar['body'];
        }
    }

    if (count($todayEarlyBodies) >= 10) {
        $earlyDailyMedianHistory[] = medianOf($todayEarlyBodies);

        // 최근 40개 정도만 보관
        if (count($earlyDailyMedianHistory) > 40) {
            $earlyDailyMedianHistory = array_slice(
                $earlyDailyMedianHistory,
                -40
            );
        }
    }
}

// 날짜 내림차순
foreach ($eventsByCategory as $key => $events) {
    krsort($eventsByCategory[$key]);
}

$selectedEvents = $eventsByCategory[$category];

$categoryCounts = [];
foreach ($eventsByCategory as $key => $events) {
    $eventCount = 0;

    foreach ($events as $dateEvents) {
        $eventCount += count($dateEvents);
    }

    $categoryCounts[$key] = [
        'days' => count($events),
        'events' => $eventCount,
    ];
}

$dates = [];
foreach ($selectedEvents as $date => $events) {
    $dates[] = [
        'date' => $date,
        'events' => $events,
    ];
}

function bigCandleDetailUrl(string $date, array $eventsByCategory): string {
    $all = [];
    foreach (['early_up','early_down','late_up','late_down'] as $key) {
        foreach (($eventsByCategory[$key][$date] ?? []) as $ev) {
            $all[] = $ev;
        }
    }

    usort($all, static fn($a, $b) => strcmp($a['datetime'] ?? '', $b['datetime'] ?? ''));

    return './futures_big_candle_detail.php?date=' . rawurlencode($date)
        . '&events=' . rawurlencode(json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>장대 5분봉 모아보기</title>
    <?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/highcharts.php"; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --up: #e53935;
            --down: #1e88e5;
            --bg: #f5f6f8;
            --card: #fff;
            --line: #d8dce2;
            --text: #222;
            --muted: #667085;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(245,246,248,.96);
            backdrop-filter: blur(8px);
            padding: 0 0 12px;
            margin-bottom: 12px;
        }
        .top-title {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 10px;
        }
        .category-tabs {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 8px;
        }
        .category-tab {
            display: block;
            text-decoration: none;
            color: #344054;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 9px;
            padding: 11px 12px;
            text-align: center;
            font-weight: 700;
            transition: .12s ease;
        }
        .category-tab:hover { border-color: #98a2b3; transform: translateY(-1px); }
        .category-tab.active {
            color: #fff;
            border-color: #111827;
            background: #111827;
        }
        .category-tab .count {
            display: block;
            margin-top: 3px;
            font-size: 11px;
            font-weight: 500;
            opacity: .78;
        }
        .summary {
            margin: 10px 0 0;
            font-size: 12px;
            color: var(--muted);
        }
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .chart-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(16,24,40,.04);
            min-width: 0;
        }
        .chart-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 12px 7px;
            border-bottom: 1px solid #eef0f3;
        }
        .date-line {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .date-link {
            font-size: 14px;
            font-weight: 800;
            color: #111827;
            text-decoration: none;
        }
        .date-link:hover { text-decoration: underline; }
        .event-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 999px;
            padding: 3px 7px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }
        .event-badge.up { color: #b42318; background: #fee4e2; }
        .event-badge.down { color: #175cd3; background: #dbeafe; }
        .event-badge.none { color: #475467; background: #f2f4f7; }
        .event-stats {
            margin-top: 4px;
            font-size: 11px;
            color: var(--muted);
        }
        .open-detail {
            flex: 0 0 auto;
            text-decoration: none;
            font-size: 12px;
            color: #475467;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 4px 7px;
            background: #fff;
        }
        .chart {
            height: 390px;
            min-width: 0;
        }
        .empty {
            border: 1px dashed #cfd4dc;
            border-radius: 10px;
            background: #fff;
            color: var(--muted);
            padding: 48px 16px;
            text-align: center;
        }
        .loading-note {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 390px;
            color: #98a2b3;
            font-size: 12px;
        }
        @media (max-width: 1100px) {
            .chart-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            body { padding: 10px; }
            .category-tabs { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .chart { height: 340px; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <div class="top-title">장대 5분봉 모아보기</div>
    <div class="category-tabs">
        <?php foreach ($CATEGORY_LABELS as $key => $label): ?>
            <?php $cnt = $categoryCounts[$key]; ?>
            <a class="category-tab <?= $category === $key ? 'active' : '' ?>"
               href="?category=<?= urlencode($key) ?>">
                <?= htmlspecialchars($label) ?>
                <span class="count">
                    <?php if (substr($key, -5) === '_none'): ?>
                        <?= number_format($cnt['days']) ?>일
                    <?php else: ?>
                        <?= number_format($cnt['days']) ?>일 · <?= number_format($cnt['events']) ?>개 봉
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="summary">
        현재: <b><?= htmlspecialchars($CATEGORY_LABELS[$category]) ?></b>
        · <?= number_format(count($selectedEvents)) ?>일
        <?php if (substr($category, -5) !== '_none'): ?>
            · 대상봉은 차트에서 색 띠 + ★로 표시
        <?php else: ?>
            · 해당 시간대에 장대양봉·장대음봉이 모두 없는 날짜
        <?php endif; ?>
        <br>
        장초반: 직전 20거래일 09:00~10:00 일별 몸통 중앙값의 중앙값 × 2.0 · 몸통비 65% / 장후반: 당일 직전 30분 평균 range × 2.0 · 몸통비 70%
    </div>
</div>

<?php if (!$dates): ?>
    <div class="empty">조건에 해당하는 날짜가 없습니다.</div>
<?php else: ?>
    <div class="chart-grid">
        <?php foreach ($dates as $i => $item): ?>
            <?php
                $events = $item['events'];
                $first = $events[0] ?? null;
                $dirClass = $first && $first['dir'] === 'up' ? 'up' : 'down';
            ?>
            <div class="chart-card" data-date="<?= htmlspecialchars($item['date']) ?>">
                <div class="chart-card-head">
                    <div>
                        <div class="date-line">
                            <a class="date-link"
                               href="<?= htmlspecialchars(bigCandleDetailUrl($item['date'], $eventsByCategory), ENT_QUOTES) ?>"
                               target="futuresBigCandleDetail">
                                <?= htmlspecialchars($item['date']) ?>
                            </a>
                            <?php if (!$events): ?>
                                <span class="event-badge none">장대봉 없음</span>
                            <?php else: ?>
                                <?php foreach ($events as $ev): ?>
                                    <span class="event-badge <?= $ev['dir'] === 'up' ? 'up' : 'down' ?>">
                                        ★ <?= htmlspecialchars($ev['time']) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($first): ?>
                            <div class="event-stats">
                                <?php foreach ($events as $ev): ?>
                                    <?= htmlspecialchars($ev['time']) ?>
                                    몸통 <?= number_format($ev['body'], 2) ?>pt
                                    · <?= ($ev['basis_type'] ?? '') === 'same_day_prev30m_avg_range' ? '직전30분 대비' : '20일 첫1시간 대비' ?>
              <?= $ev['relative_ratio'] !== null ? number_format($ev['relative_ratio'], 2) . '배' : '-' ?>
                                    · 몸통비 <?= number_format($ev['body_ratio'] * 100, 0) ?>%
                                    <?php if ($ev !== end($events)): ?> / <?php endif; ?>
                                <?php endforeach; reset($events); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a class="open-detail"
                       href="<?= htmlspecialchars(bigCandleDetailUrl($item['date'], $eventsByCategory), ENT_QUOTES) ?>"
                       target="futuresBigCandleDetail">상세</a>
                </div>
                <div id="chart-<?= $i ?>" class="chart">
                    <div class="loading-note">차트 대기중…</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
const dateList = <?= json_encode($dates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const activeCategory = <?= json_encode($category) ?>;
const isNoneCategory = activeCategory.endsWith('_none');
const isUpCategory = activeCategory.endsWith('_up');
const MARK_FILL = isNoneCategory
    ? 'rgba(71, 84, 103, 0.08)'
    : (isUpCategory ? 'rgba(239, 68, 68, 0.14)' : 'rgba(59, 130, 246, 0.16)');
const MARK_EDGE = isNoneCategory
    ? 'rgba(71, 84, 103, 0.75)'
    : (isUpCategory ? 'rgba(220, 38, 38, 0.90)' : 'rgba(37, 99, 235, 0.90)');
const MARK_TEXT = isNoneCategory ? '#475467' : (isUpCategory ? '#b42318' : '#175cd3');
const CHART_LIMIT = 90;

function toLocalTS(s) {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/.exec(String(s || ''));
    if (!m) return Date.parse(String(s).replace(' ', 'T'));
    return new Date(+m[1], +m[2]-1, +m[3], +m[4], +m[5], +m[6]).getTime();
}

function tsFromRow(r) {
    if (r.ts != null) {
        const v = +r.ts;
        return v < 1e12 ? v * 1000 : v;
    }
    return toLocalTS(r.datetime);
}

function lineData(rows, key) {
    return rows
        .filter(r => r[key] != null && r[key] !== '' && Number.isFinite(+r[key]))
        .map(r => [tsFromRow(r), +r[key]]);
}

function addTargetMarker(chart, priceSeries, event) {
    const x = toLocalTS(event.datetime);
    const half = 2.5 * 60 * 1000;

    chart.xAxis[0].addPlotBand({
        id: `bigcandle-band-${x}`,
        from: x - half,
        to: x + half,
        color: MARK_FILL,
        zIndex: 1
    });
    chart.xAxis[0].addPlotLine({
        id: `bigcandle-line-${x}`,
        value: x,
        color: MARK_EDGE,
        width: 2,
        dashStyle: 'ShortDash',
        zIndex: 5
    });

    chart.addSeries({
        type: 'flags',
        name: '대상 장대봉',
        onSeries: priceSeries.options.id,
        onKey: event.dir === 'up' ? 'high' : 'low',
        shape: 'circlepin',
        width: 18,
        y: event.dir === 'up' ? -26 : 8,
        fillColor: MARK_EDGE,
        color: MARK_TEXT,
        lineWidth: 0,
        zIndex: 9,
        dataGrouping: { enabled: false },
        enableMouseTracking: false,
        data: [{ x, title: '★', text: `${event.time} 대상 장대봉` }]
    }, false);
}

function drawChart(containerId, item) {
    const container = document.getElementById(containerId);
    if (!container || container.dataset.loaded === '1') return;
    container.dataset.loaded = '1';

    $.getJSON(`./get_1min_data.php?date=${encodeURIComponent(item.date)}&interval=5m&limit=${CHART_LIMIT}`)
        .done(function(data) {
            if (!Array.isArray(data) || !data.length) {
                container.innerHTML = '<div class="loading-note">5분봉 데이터가 없습니다.</div>';
                return;
            }

            const candles = data.map(r => [tsFromRow(r), +r.open, +r.high, +r.low, +r.close]);
            const volume = data.map(r => ({
                x: tsFromRow(r),
                y: +r.volume,
                color: (+r.close >= +r.open) ? '#f45b5b' : '#2f7ed8'
            }));
            const sma5 = lineData(data, 'sma_5');
            const sma20 = lineData(data, 'sma_20');
            const vwap = lineData(data, 'vwap_session');
            const priceSeriesId = `price-${item.date}`;

            const chart = Highcharts.stockChart(containerId, {
                chart: {
                    height: 390,
                    zooming: { mouseWheel: { enabled: false }, type: null },
                    panning: false
                },
                exporting: { enabled: false },
                navigator: { enabled: false },
                scrollbar: { enabled: false },
                rangeSelector: { enabled: false },
                title: { text: '' },
                time: { useUTC: false },
                xAxis: {
                    type: 'datetime',
                    ordinal: true,
                    startOnTick: false,
                    endOnTick: false,
                    minPadding: 0,
                    maxPadding: 0,
                    labels: { format: '{value:%H:%M}', style: { fontSize: '9px' } },
                    crosshair: { width: 1, color: '#98a2b3', dashStyle: 'ShortDot' }
                },
                yAxis: [
                    {
                        height: '82%',
                        lineWidth: 1,
                        startOnTick: false,
                        endOnTick: false,
                        minPadding: 0.02,
                        maxPadding: 0.02
                    },
                    {
                        top: '82%',
                        height: '18%',
                        offset: 0,
                        lineWidth: 1,
                        min: 0
                    }
                ],
                legend: { enabled: false },
                plotOptions: {
                    series: { states: { hover: { enabled: false } } },
                    candlestick: {
                        color: '#2f7ed8',
                        upColor: '#f45b5b',
                        lineColor: '#2f7ed8',
                        upLineColor: '#f45b5b'
                    }
                },
                tooltip: {
                    shared: true,
                    split: false,
                    useHTML: true,
                    valueDecimals: 2
                },
                series: [
                    {
                        id: 'sma20',
                        type: 'line',
                        name: 'SMA 20',
                        data: sma20,
                        color: '#f9a825',
                        lineWidth: 2,
                        zIndex: 1,
                        enableMouseTracking: false,
                        dataGrouping: { enabled: false }
                    },
                    {
                        id: 'sma5',
                        type: 'line',
                        name: 'SMA 5',
                        data: sma5,
                        color: '#d32f2f',
                        lineWidth: 1.5,
                        zIndex: 1,
                        enableMouseTracking: false,
                        dataGrouping: { enabled: false }
                    },
                    {
                        id: priceSeriesId,
                        type: 'candlestick',
                        name: 'Price',
                        data: candles,
                        zIndex: 3,
                        dataGrouping: { enabled: false }
                    },
                    {
                        id: 'volume',
                        type: 'column',
                        name: 'Volume',
                        data: volume,
                        yAxis: 1,
                        zIndex: 1,
                        borderWidth: 0,
                        pointPadding: 0.05,
                        groupPadding: 0.05,
                        dataGrouping: { enabled: false }
                    },
                    {
                        id: 'vwap',
                        type: 'line',
                        name: 'VWAP',
                        data: vwap,
                        color: '#6d28d9',
                        lineWidth: 2,
                        zIndex: 2,
                        enableMouseTracking: false,
                        dataGrouping: { enabled: false }
                    }
                ]
            });

            const priceSeries = chart.get(priceSeriesId);
            (item.events || []).forEach(ev => addTargetMarker(chart, priceSeries, ev));
            chart.redraw(false);

            // 전체 장 흐름은 15:30까지 보여준다. (장후반 장대봉 판정구간은 12:30~15:00)
            const dayRows = data.filter(r => String(r.datetime).slice(0, 10) === item.date);
            if (dayRows.length) {
                const minX = tsFromRow(dayRows[0]);
                const maxX = tsFromRow(dayRows[dayRows.length - 1]);
                if (Number.isFinite(minX) && Number.isFinite(maxX)) {
                    chart.xAxis[0].setExtremes(minX, maxX, false, false);
                    chart.redraw(false);
                }
            }
        })
        .fail(function() {
            container.innerHTML = '<div class="loading-note">차트 조회 실패</div>';
        });
}

// 100개 가까운 날짜가 있어도 처음부터 전부 요청하지 않도록 lazy load.
const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const idx = Number(el.dataset.idx);
        if (Number.isInteger(idx) && dateList[idx]) {
            drawChart(`chart-${idx}`, dateList[idx]);
            observer.unobserve(el);
        }
    });
}, { rootMargin: '700px 0px' });

dateList.forEach((item, idx) => {
    const el = document.getElementById(`chart-${idx}`);
    if (el) {
        el.dataset.idx = String(idx);
        observer.observe(el);
    }
});
</script>
</body>
</html>
