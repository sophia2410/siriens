<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
session_start();

$date = trim((string)($_GET['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    exit('잘못된 날짜입니다.');
}

$eventsRaw = (string)($_GET['events'] ?? '[]');
$events = json_decode($eventsRaw, true);
if (!is_array($events)) $events = [];

$cleanEvents = [];
foreach ($events as $ev) {
    if (!is_array($ev)) continue;

    $dt = (string)($ev['datetime'] ?? '');
    $dir = (string)($ev['dir'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dt)) continue;
    if ($dir !== 'up' && $dir !== 'down') continue;

    $cleanEvents[] = [
        'datetime' => $dt,
        'time' => (string)($ev['time'] ?? substr($dt, 11, 5)),
        'dir' => $dir,
        'body' => isset($ev['body']) ? (float)$ev['body'] : null,
        'body_ratio' => isset($ev['body_ratio']) ? (float)$ev['body_ratio'] : null,
        'relative_ratio' => isset($ev['relative_ratio']) ? (float)$ev['relative_ratio'] : null,
        'basis_type' => (string)($ev['basis_type'] ?? ''),
    ];
}

usort($cleanEvents, static fn($a, $b) => strcmp($a['datetime'], $b['datetime']));
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($date, ENT_QUOTES) ?> 장대봉 상세</title>

    <?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/highcharts.php"; ?>

    <style>
        * { box-sizing:border-box; }

        body {
            margin:0;
            padding:12px;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background:#f7f8fa;
            color:#101828;
        }

        .topbar {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:16px;
            padding:10px 12px;
            margin-bottom:12px;
            background:#fff;
            border:1px solid #e4e7ec;
            border-radius:8px;
        }

        .title {
            font-size:19px;
            font-weight:800;
            margin-bottom:7px;
        }

        .event-list {
            display:flex;
            flex-wrap:wrap;
            gap:6px;
        }

        .badge {
            display:inline-flex;
            align-items:center;
            gap:5px;
            padding:4px 8px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
        }

        .badge.up { color:#b42318; background:#fee4e2; }
        .badge.down { color:#175cd3; background:#dbeafe; }
        .badge.none { color:#475467; background:#f2f4f7; }

        .close-btn {
            border:1px solid #d0d5dd;
            background:#fff;
            color:#344054;
            padding:7px 10px;
            border-radius:6px;
            cursor:pointer;
            font-size:12px;
        }

        .top-grid {
            display:grid;
            grid-template-columns:minmax(0,1fr) minmax(0,2fr);
            gap:12px;
            margin-bottom:12px;
        }

        .chart-card {
            background:#fff;
            border:1px solid #d0d5dd;
            border-radius:8px;
            padding:8px;
            box-shadow:0 1px 3px rgba(16,24,40,.04);
            min-width:0;
        }

        .chart-head {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
            margin:0 2px 5px;
        }

        .chart-name {
            font-size:13px;
            font-weight:800;
        }

        .chart-note {
            font-size:11px;
            color:#667085;
        }

        #chart15m,
        #chart5m {
            width:100%;
            height:330px;
        }

        #chart1m {
            width:100%;
            height:610px;
        }

        .loading {
            height:100%;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#98a2b3;
            font-size:13px;
        }

        .error {
            padding:20px;
            color:#b42318;
            font-size:13px;
        }

        @media (max-width:900px) {
            body { padding:8px; }
            .top-grid { grid-template-columns:1fr; }
            #chart15m,#chart5m { height:320px; }
            #chart1m { height:520px; }
            .topbar { flex-direction:column; }
        }
    </style>
</head>

<body>

<div class="topbar">
    <div>
        <div class="title"><?= htmlspecialchars($date, ENT_QUOTES) ?> 장대봉 상세</div>

        <div class="event-list">
            <?php if (!$cleanEvents): ?>
                <span class="badge none">표시 대상 장대봉 없음</span>
            <?php else: ?>
                <?php foreach ($cleanEvents as $ev): ?>
                    <span class="badge <?= $ev['dir'] === 'up' ? 'up' : 'down' ?>">
                        <?= $ev['dir'] === 'up' ? '▲ 장대양봉' : '▼ 장대음봉' ?>
                        <?= htmlspecialchars($ev['time'], ENT_QUOTES) ?>
                        <?php if ($ev['body'] !== null): ?>
                            · <?= number_format($ev['body'], 2) ?>pt
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <button class="close-btn" type="button" onclick="window.close()">닫기</button>
</div>

<div class="top-grid">
    <div class="chart-card">
        <div class="chart-head">
            <div class="chart-name">15분봉 · 하루 전체</div>
            <div class="chart-note">전체 흐름</div>
        </div>
        <div id="chart15m"><div class="loading">불러오는 중…</div></div>
    </div>

    <div class="chart-card">
        <div class="chart-head">
            <div class="chart-name">5분봉 · 하루 전체</div>
            <div class="chart-note">장대봉 해당 5분봉 표시</div>
        </div>
        <div id="chart5m"><div class="loading">불러오는 중…</div></div>
    </div>
</div>

<div class="chart-card">
    <div class="chart-head">
        <div class="chart-name">1분봉 · 하루 전체</div>
        <div class="chart-note">08:45~15:45 전체 · 장대 5분봉 시작 분봉 표시</div>
    </div>
    <div id="chart1m"><div class="loading">불러오는 중…</div></div>
</div>

<script>
const DAY = <?= json_encode($date) ?>;
const EVENTS = <?= json_encode(
    $cleanEvents,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;

const API = './get_1min_data.php';

const SMA5_COLOR = '#d32f2f';
const SMA20_COLOR = '#f9a825';
const VWAP_COLOR = '#6d28d9';

function toLocalTS(s) {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/.exec(String(s || ''));

    if (!m) {
        return Date.parse(String(s).replace(' ', 'T'));
    }

    return new Date(
        +m[1], +m[2]-1, +m[3],
        +m[4], +m[5], +m[6]
    ).getTime();
}

function tsFromRow(r) {
    if (r.ts != null && r.ts !== '') {
        const v = +r.ts;
        return v < 1e12 ? v * 1000 : v;
    }

    return toLocalTS(r.datetime);
}

function lineData(rows, key) {
    return (rows || [])
        .filter(r => r[key] != null && r[key] !== '' && Number.isFinite(+r[key]))
        .map(r => [tsFromRow(r), +r[key]]);
}

function markerStyle(dir) {
    return dir === 'up'
        ? {
            fill:'rgba(239,68,68,.14)',
            edge:'rgba(220,38,38,.95)',
            text:'#b42318',
            title:'▲'
        }
        : {
            fill:'rgba(59,130,246,.16)',
            edge:'rgba(37,99,235,.95)',
            text:'#175cd3',
            title:'▼'
        };
}

function addEventMarkers(chart, interval, priceSeriesId) {
    if (!chart || !EVENTS.length) return;

    const priceSeries = chart.get(priceSeriesId);
    if (!priceSeries) return;

    const halfMs = interval === '5m'
        ? 2.5 * 60 * 1000
        : 0.5 * 60 * 1000;

    EVENTS.forEach((ev, idx) => {
        const x = toLocalTS(ev.datetime);
        const st = markerStyle(ev.dir);

        chart.xAxis[0].addPlotBand({
            id:`big-band-${interval}-${idx}`,
            from:x-halfMs,
            to:x+halfMs,
            color:st.fill,
            zIndex:1
        });

        chart.xAxis[0].addPlotLine({
            id:`big-line-${interval}-${idx}`,
            value:x,
            color:st.edge,
            width:2,
            dashStyle:'ShortDash',
            zIndex:6
        });

        chart.addSeries({
            type:'flags',
            name:ev.dir === 'up' ? '장대양봉' : '장대음봉',
            onSeries:priceSeriesId,
            onKey:ev.dir === 'up' ? 'high' : 'low',
            shape:'circlepin',
            width:18,
            y:ev.dir === 'up' ? -26 : 8,
            fillColor:st.edge,
            color:st.text,
            lineWidth:0,
            zIndex:9,
            dataGrouping:{enabled:false},
            enableMouseTracking:false,
            data:[{
                x:x,
                title:st.title,
                text:`${ev.time} ${ev.dir === 'up' ? '장대양봉' : '장대음봉'}`
            }]
        }, false);
    });
}

function addTimeGuides(chart) {
    const xa = chart.xAxis[0];

    [
        ['09:00','rgba(253,250,38,.95)',3],
        ['09:30','rgba(167,248,215,.95)',3],
        ['10:00','rgba(253,250,38,.95)',3],
        ['11:00','rgba(253,250,38,.95)',2],
        ['12:30','rgba(167,248,215,.75)',2],
        ['15:00','rgba(167,248,215,.75)',2]
    ].forEach(([hm,color,width], i) => {
        xa.addPlotLine({
            id:`guide-${i}`,
            value:toLocalTS(`${DAY} ${hm}:00`),
            color,
            width,
            zIndex:0
        });
    });
}

function buildChart(containerId, interval, rows, opts = {}) {
    const el = document.getElementById(containerId);

    if (!Array.isArray(rows) || !rows.length) {
        el.innerHTML = '<div class="loading">해당 일자 데이터가 없습니다.</div>';
        return;
    }

    const priceSeriesId = `price-${interval}`;

    const candles = rows.map(r => [
        tsFromRow(r),
        +r.open,
        +r.high,
        +r.low,
        +r.close
    ]);

    const volume = rows.map(r => ({
        x:tsFromRow(r),
        y:+r.volume,
        color:(+r.close >= +r.open) ? '#f45b5b' : '#2f7ed8'
    }));

    const sma5 = lineData(rows, 'sma_5');
    const sma20 = lineData(rows, 'sma_20');
    const vwap = lineData(rows, 'vwap_session');

    const chart = Highcharts.stockChart(containerId, {
        chart:{
            height:interval === '1m' ? 610 : 330,
            zooming:{mouseWheel:{enabled:false}, type:'x'},
            panning:true,
            panKey:'shift'
        },

        exporting:{enabled:false},
        navigator:{enabled:false},
        scrollbar:{enabled:false},
        rangeSelector:{enabled:false},
        legend:{enabled:false},
        title:{text:''},
        time:{useUTC:false},

        xAxis:{
            type:'datetime',
            ordinal:true,
            startOnTick:false,
            endOnTick:false,
            minPadding:0,
            maxPadding:0,
            labels:{
                format:'{value:%H:%M}',
                style:{fontSize:'9px'}
            },
            crosshair:{
                width:1,
                color:'#98a2b3',
                dashStyle:'ShortDot'
            }
        },

        yAxis:[
            {
                height:'82%',
                lineWidth:1,
                startOnTick:false,
                endOnTick:false,
                minPadding:.02,
                maxPadding:.02
            },
            {
                top:'82%',
                height:'18%',
                offset:0,
                lineWidth:1,
                min:0
            }
        ],

        plotOptions:{
            series:{
                states:{hover:{enabled:false}},
                turboThreshold:0
            },
            candlestick:{
                color:'#2f7ed8',
                upColor:'#f45b5b',
                lineColor:'#2f7ed8',
                upLineColor:'#f45b5b'
            }
        },

        tooltip:{
            shared:true,
            split:false,
            useHTML:true,
            formatter:function(){
                const candleP = (this.points || []).find(
                    p => p.series.type === 'candlestick'
                );

                if (!candleP) return false;

                const tm = Highcharts.dateFormat(
                    '%Y-%m-%d %H:%M',
                    candleP.x
                );

                const p = candleP.point;

                let html = `<b>${tm}</b><br/>--------------<br/>`;
                html += `O ${Highcharts.numberFormat(p.open,2)}<br/>`;
                html += `H ${Highcharts.numberFormat(p.high,2)}<br/>`;
                html += `L ${Highcharts.numberFormat(p.low,2)}<br/>`;
                html += `C ${Highcharts.numberFormat(p.close,2)}<br/>`;

                const vol = (this.points || []).find(
                    q => q.series.type === 'column'
                );

                if (vol) {
                    html += `Volume ${Highcharts.numberFormat(vol.y,0)}<br/>`;
                }

                return html;
            }
        },

        series:[
            {
                id:`sma20-${interval}`,
                type:'line',
                name:'SMA 20',
                data:sma20,
                color:SMA20_COLOR,
                lineWidth:2,
                zIndex:1,
                enableMouseTracking:false,
                dataGrouping:{enabled:false}
            },
            {
                id:`sma5-${interval}`,
                type:'line',
                name:'SMA 5',
                data:sma5,
                color:SMA5_COLOR,
                lineWidth:1.5,
                zIndex:1,
                enableMouseTracking:false,
                dataGrouping:{enabled:false}
            },
            {
                id:priceSeriesId,
                type:'candlestick',
                name:'Price',
                data:candles,
                zIndex:3,
                dataGrouping:{enabled:false}
            },
            {
                id:`volume-${interval}`,
                type:'column',
                name:'Volume',
                data:volume,
                yAxis:1,
                zIndex:1,
                borderWidth:0,
                pointPadding:.02,
                groupPadding:.02,
                dataGrouping:{enabled:false}
            },
            {
                id:`vwap-${interval}`,
                type:'line',
                name:'VWAP',
                data:vwap,
                color:VWAP_COLOR,
                lineWidth:2,
                zIndex:2,
                enableMouseTracking:false,
                dataGrouping:{enabled:false}
            }
        ]
    });

    if (opts.showEventMarkers) {
        addEventMarkers(chart, interval, priceSeriesId);
    }

    addTimeGuides(chart);

    const minX = tsFromRow(rows[0]);
    const maxX = tsFromRow(rows[rows.length - 1]);

    chart.xAxis[0].setExtremes(minX, maxX, false, false);
    chart.redraw(false);
}

async function fetchInterval(interval) {
    const url =
        `${API}?date=${encodeURIComponent(DAY)}` +
        `&interval=${encodeURIComponent(interval)}` +
        `&all_day=1`;

    const res = await fetch(url, {
        method:'GET',
        cache:'no-store',
        headers:{'Accept':'application/json'}
    });

    if (!res.ok) {
        throw new Error(`${interval} HTTP ${res.status}`);
    }

    const rows = await res.json();

    if (!Array.isArray(rows)) {
        throw new Error(`${interval} 응답 형식 오류`);
    }

    return rows;
}

async function loadDay() {
    try {
        // 세 차트 모두 동일한 get_1min_data.php + all_day=1 규칙 사용
        const [rows15m, rows5m, rows1m] = await Promise.all([
            fetchInterval('15m'),
            fetchInterval('5m'),
            fetchInterval('1m')
        ]);

        buildChart('chart15m', '15m', rows15m, {
            showEventMarkers:false
        });

        buildChart('chart5m', '5m', rows5m, {
            showEventMarkers:true
        });

        buildChart('chart1m', '1m', rows1m, {
            showEventMarkers:true
        });

    } catch (err) {
        console.error(err);

        ['chart15m','chart5m','chart1m'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.innerHTML =
                    `<div class="error">차트 조회 실패: ${String(err?.message || err)}</div>`;
            }
        });
    }
}
loadDay();
</script>

</body>
</html>
