<!-- 
- 5/15/60은 “진행봉(Partial)”이 매 1분마다 고/저/종가/거래량이 갱신되어야 정상입니다.
- 멈춤 방지: setInterval 제거(자기조절 setTimeout), redraw를 4차트 한꺼번에 하지 않고 큐로 쪼개 처리합니다.
- 고/저/시가: API hilo(08:45부터 N개 1분)로 전 차트 수평선 표시. 시간표시: 지나간 시각만 세로선 1회 추가. 
-->

<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$date  = $_GET['date']  ?? date('Y-m-d');
$speed = $_GET['speed'] ?? '2000';
$start_at = $_GET['start_at'] ?? '09:44';

// ✅ 이전/다음 거래일
$stmt_prev = $mysqli->prepare("SELECT MAX(date) FROM calendar WHERE date < ?");
$stmt_prev->bind_param('s', $date);
$stmt_prev->execute();
$stmt_prev->bind_result($prev_date);
$stmt_prev->fetch();
$stmt_prev->close();

$stmt_next = $mysqli->prepare("SELECT MIN(date) FROM calendar WHERE date > ?");
$stmt_next->bind_param('s', $date);
$stmt_next->execute();
$stmt_next->bind_result($next_date);
$stmt_next->fetch();
$stmt_next->close();

// ✅ 같은 폴더에 API가 있는 경우 가장 안전한 상대경로
$api_url = dirname($_SERVER['PHP_SELF']) . '/replay_api_multi.php';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>선물 리플레이 (60/15/5 + 1m)</title>
  <?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/highcharts.php"; ?>
  <style>
    body{margin:0;padding:10px;font-family:sans-serif;background:#fafafa;}
    .panel{border:1px solid #ddd;background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.05);padding:10px;}
    .topbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px;}
    .grid{display:grid;grid-template-columns:1.8fr 1.8fr 5fr;gap:8px;margin-bottom:8px;}
    .bottom{display:grid;grid-template-columns:1fr;gap:8px;}
    .charttitle{font-weight:700;margin:0 0 6px;color:#333;}
    .stat{margin-left:auto;font-weight:400;margin-bottom:5px;}
    input,select,button{padding:6px 8px;}
    button{cursor:pointer;}
    @media (max-width:1200px){ .grid{grid-template-columns:1fr;} }

    .dbg{
      margin-top:8px; padding:8px; background:#0f172a; color:#e2e8f0;
      border-radius:8px; font-size:12px; display:none;
    }
    .dbg .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
    .dbg code{color:#a7f3d0;}
    .dbg pre{
      margin:6px 0 0; padding:8px; background:#111827; border-radius:6px;
      max-height:180px; overflow:auto; white-space:pre-wrap; word-break:break-word;
    }
    .dbgbtn{background:#111827;color:#e2e8f0;border:1px solid #334155;border-radius:6px;}
    .chk{display:flex;gap:6px;align-items:center;}
  </style>
</head>
<body>

<div class="panel topbar">
  <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <div><b>일자</b> <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"></div>
    <div><b>시작시간</b> <input type="time" name="start_at" value="<?= htmlspecialchars($start_at) ?>" step="60"></div>

    <button type="submit">불러오기</button>

    <button type="button" id="btnPrev" <?= empty($prev_date)?'disabled':'' ?>>◀ 이전일</button>
    <button type="button" id="btnNext" <?= empty($next_date)?'disabled':'' ?>>다음일 ▶</button>

    <input type="hidden" id="prevDate" value="<?= htmlspecialchars($prev_date ?? '') ?>">
    <input type="hidden" id="nextDate" value="<?= htmlspecialchars($next_date ?? '') ?>">

    <div><b>속도</b>
      <select id="speed" name="speed">
        <option value="2000" <?= $speed==='2000'?'selected':'' ?>>2초=1분</option>
        <option value="1000" <?= $speed==='1000'?'selected':'' ?>>1초=1분</option>
        <option value="500"  <?= $speed==='500'?'selected':''  ?>>0.5초=1분</option>
      </select>
    </div>

    <button type="button" id="btnPlay">▶ 재생</button>
    <button type="button" id="btnPause">⏸ 정지</button>
        <button type="button" id="btnBack">-1봉</button>
    <button type="button" id="btnStep">+1봉</button>
    <button type="button" id="btnReset">↺ 리셋</button>

    <label class="chk"><input type="checkbox" id="chkFollow" checked>팔로우</label>
    <button type="button" class="dbgbtn" id="btnDbg">DBG</button>

    <div class="stat" id="stat">로딩 전</div>
  </form>

  <div class="dbg" id="dbgPanel">
    <div class="row">
      <div>API: <code id="dbgApi"></code></div>
      <div>idx: <code id="dbgIdx"></code></div>
      <div>last: <code id="dbgLast"></code></div>
      <div>tick(ms): <code id="dbgTick"></code></div>
      <div>redraw(ms): <code id="dbgRd"></code></div>
      <div>p5/p15/p60: <code id="dbgP"></code></div>
      <div>watchdog: <code id="dbgWd"></code></div>
      <button type="button" class="dbgbtn" id="btnDump">상태 덤프</button>
      <button type="button" class="dbgbtn" id="btnClearLog">로그 지우기</button>
    </div>
    <pre id="dbgLog"></pre>
  </div>
</div>

<div class="grid">
  <div class="panel">
    <div class="charttitle">60분봉</div>
    <div id="chart60" style="height:350px;"></div>
  </div>
  <div class="panel">
    <div class="charttitle">15분봉</div>
    <div id="chart15" style="height:350px;"></div>
  </div>
  <div class="panel">
    <div class="charttitle">5분봉</div>
    <div id="chart5" style="height:350px;"></div>
  </div>
</div>

<div class="bottom">
  <div class="panel">
    <div class="charttitle">1분봉 (전일 tail + 당일 진행) + SMA 5/20/120 + 08:45~N개 고/저/시가</div>
    <div id="chart1" style="height:420px;"></div>
  </div>
</div>

<script>
/** ================== 설정 ================== */
const DATE     = <?= json_encode($date) ?>;
const START_AT = <?= json_encode($start_at) ?>; // "HH:MM"
const INIT_TIME = (START_AT && START_AT.length===5) ? (START_AT + ':00') : START_AT;

const API_URL  = <?= json_encode($api_url) ?>;

const FIX_MAX_1  = 200;
const FIX_MAX_5  = 83;
const FIX_MAX_15 = 29;
const FIX_MAX_60 = 36;

const SMA5_COLOR   = '#d32f2f';
const SMA20_COLOR  = '#f9a825';
const SMA120_COLOR = '#757575';

const HI_COLOR   = '#ff4fb3';
const LO_COLOR   = '#4fc3ff';
const OPEN_COLOR = '#666666';

function setStat(msg){ document.getElementById('stat').textContent = msg; }

/** ================== 디버그 ================== */
const DBG = {
  enabled:false,
  logLines:[],
  lastTickAt:performance.now(),
  lastTickMs:0,
  lastRedrawMs:0,
  watchdog:'OK',
};
let lastBarDt = null;

function dbgLine(msg){
  const t = new Date().toLocaleTimeString();
  DBG.logLines.push(`[${t}] ${msg}`);
  if (DBG.logLines.length > 120) DBG.logLines.shift();
  if (DBG.enabled) document.getElementById('dbgLog').textContent = DBG.logLines.join('\n');
}
function fmtHHMM(ms){
  if (!ms) return '-';
  const d = new Date(ms);
  return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}
function dbgKV(){
  if (!DBG.enabled) return;
  document.getElementById('dbgApi').textContent = API_URL;
  document.getElementById('dbgIdx').textContent = `${idx}/${today1.length}`;
  document.getElementById('dbgLast').textContent = lastBarDt || '-';
  document.getElementById('dbgTick').textContent = DBG.lastTickMs.toFixed(1);
  document.getElementById('dbgRd').textContent = DBG.lastRedrawMs.toFixed(1);
  document.getElementById('dbgP').textContent =
    `${fmtHHMM(partial5?.bucketTs)} / ${fmtHHMM(partial15?.bucketTs)} / ${fmtHHMM(partial60?.bucketTs)}`;
  document.getElementById('dbgWd').textContent = DBG.watchdog;
}
window.addEventListener('error', (e) => {
  dbgLine(`JS 오류: ${e.message}`);
  console.error(e);
});
window.addEventListener('unhandledrejection', (e) => {
  dbgLine(`Promise 오류: ${String(e.reason)}`);
  console.error(e);
});

// watchdog
setInterval(() => {
  const now = performance.now();
  const dt = now - DBG.lastTickAt;
  DBG.watchdog = (dt > 1500 && playing) ? `STALLED ${dt.toFixed(0)}ms` : 'OK';
  dbgKV();
}, 400);

/** ================== 시간/버킷(08:45 앵커) ================== */
function toMs(datetimeStr){
  // "YYYY-MM-DD HH:MM:SS"
  if (!datetimeStr || datetimeStr.length < 19) return NaN;
  const y = +datetimeStr.slice(0,4);
  const m = +datetimeStr.slice(5,7) - 1;
  const d = +datetimeStr.slice(8,10);
  const H = +datetimeStr.slice(11,13);
  const I = +datetimeStr.slice(14,16);
  const S = +datetimeStr.slice(17,19);
  return new Date(y,m,d,H,I,S).getTime();
}
function toTSLocal(ymd, hhmmss){ return toMs(`${ymd} ${hhmmss}`); }
const ANCHOR_MS = toTSLocal(DATE, '08:45:00');
function bucketStart(ms, minutes){
  const unit = minutes * 60 * 1000;
  const delta = ms - ANCHOR_MS;
  if (delta < 0) return ANCHOR_MS;
  return ANCHOR_MS + Math.floor(delta / unit) * unit;
}

/** ================== 데이터 상태 ================== */
let init1=[], init5=[], init15=[], init60=[];
let today1=[], today5=[], today15=[], today60=[];
let map5 = new Map(), map15 = new Map(), map60 = new Map();

let initPartial5=null, initPartial15=null, initPartial60=null;
let partial5=null, partial15=null, partial60=null;

let idx=0;
let initNowTs=null;

// hilo
let open_0845=null, hi_0845=null, lo_0845=null;
let hiMeta = { start:'08:45', end:'09:04' };
let open_0845_aux=null, hi_0845_aux=null, lo_0845_aux=null;
let hiMetaAux = { start:'08:45', end:'09:44' };

// charts
let c1=null, c5=null, c15=null, c60=null;

/** ================== series 변환(DB datetime 그대로) ================== */
function rowsToCandles(rows){
  return (rows||[]).map(r => [toMs(r.datetime), +r.open, +r.high, +r.low, +r.close]);
}
function rowsToVol(rows){
  return (rows||[]).map(r => [toMs(r.datetime), +(r.volume||0)]);
}
function rowsToLine(rows, field){
  const out=[];
  (rows||[]).forEach(r=>{
    if (r[field]==null) return;
    out.push([toMs(r.datetime), +r[field]]);
  });
  return out;
}

/** ================== 차트 생성 ================== */
function makeBaseChart(el, name){
  return Highcharts.stockChart(el, {
    chart:{ animation:false, zooming:{mouseWheel:{enabled:false}, type:null}, panning:false },
    navigator:{enabled:false}, scrollbar:{enabled:false}, rangeSelector:{enabled:false},
    title:{text:''}, time:{useUTC:false},
    tooltip: { enabled: false },
    xAxis:{ type:'datetime', labels:{ format:'{value:%H:%M}', y:10, style:{ fontSize:'10px' } }, startOnTick:false, endOnTick:false },
    yAxis:[
      { height:'78%', lineWidth:1, labels:{ align:'right', x:4, reserveSpace:true, style:{ fontSize:'10px' } } },
      { top:'80%', height:'20%', offset:0, lineWidth:1, min:0 }
    ],
    series:[
      { type:'candlestick', name, data:[], zIndex:4, dataGrouping:{enabled:false} }, // 0
      { type:'line', name:'SMA 5', data:[], lineWidth:2, color:SMA5_COLOR, dataGrouping:{enabled:false} },   // 1
      { type:'line', name:'SMA 20', data:[], lineWidth:2, color:SMA20_COLOR, dataGrouping:{enabled:false} }, // 2
      { type:'line', name:'SMA 120', data:[], lineWidth:2, color:SMA120_COLOR, dataGrouping:{enabled:false} },//3
      { type:'column', name:'Vol', data:[], yAxis:1, dataGrouping:{enabled:false} } // 4
    ],
    plotOptions:{
      series:{ animation:false, enableMouseTracking:true, states:{ hover:{ enabled:false } } },
      candlestick:{ color:'#2f7ed8', upColor:'#f45b5b', lineColor:'#2f7ed8', upLineColor:'#f45b5b' }
    }
  });
}
function make1mChart(){
  return Highcharts.stockChart('chart1', {
    chart:{ animation:false, zooming:{mouseWheel:{enabled:false}, type:null}, panning:false },
    navigator:{enabled:false}, scrollbar:{enabled:false}, rangeSelector:{enabled:false},
    title:{text:''}, time:{useUTC:false},
    tooltip: { enabled: false },
    xAxis:{ type:'datetime', labels:{ format:'{value:%H:%M}', y:10, style:{ fontSize:'10px' } }, startOnTick:false, endOnTick:false },
    yAxis:[
      { height:'80%', lineWidth:1, labels:{ align:'right', x:4, reserveSpace:true, style:{ fontSize:'10px' } } },
      { top:'82%', height:'18%', offset:0, lineWidth:1, min:0 }
    ],
    series:[
      { type:'candlestick', name:'1m', data:[], zIndex:4, dataGrouping:{enabled:false} }, // 0
      { type:'line', name:'SMA 5', data:[], lineWidth:2, color:SMA5_COLOR, dataGrouping:{enabled:false} },   // 1
      { type:'line', name:'SMA 20', data:[], lineWidth:2, color:SMA20_COLOR, dataGrouping:{enabled:false} }, // 2
      { type:'line', name:'SMA 120', data:[], lineWidth:2, color:SMA120_COLOR, dataGrouping:{enabled:false} },//3
      { type:'column', name:'Vol', data:[], yAxis:1, dataGrouping:{enabled:false} } // 4
    ],
    plotOptions:{
      series:{ animation:false, enableMouseTracking:true, states:{ hover:{ enabled:false } } },
      candlestick:{ color:'#2f7ed8', upColor:'#f45b5b', lineColor:'#2f7ed8', upLineColor:'#f45b5b' }
    }
  });
}
function destroyChart(ch){ try{ ch && ch.destroy(); }catch(e){} }
function resetCharts(){
  destroyChart(c1); destroyChart(c5); destroyChart(c15); destroyChart(c60);
  document.getElementById('chart1').innerHTML = '';
  document.getElementById('chart5').innerHTML = '';
  document.getElementById('chart15').innerHTML = '';
  document.getElementById('chart60').innerHTML = '';

  c60 = makeBaseChart('chart60', '60m');
  c15 = makeBaseChart('chart15', '15m');
  c5  = makeBaseChart('chart5',  '5m');
  c1  = make1mChart();

  initTimeMarks(c1, TIME_MARKS_BY_CHART.c1);
  initTimeMarks(c5, TIME_MARKS_BY_CHART.c5);
  initTimeMarks(c15, TIME_MARKS_BY_CHART.c15);
  initTimeMarks(c60, TIME_MARKS_BY_CHART.c60);}

/** ================== 고/저/시가 라인 ================== */
function applyHiLoLines(chart){
  if (!chart) return;
  const yAxis = chart.yAxis[0];
  ['hi0845','lo0845','op0845']
    .forEach(id => { try{ yAxis.removePlotLine(id); }catch(e){} });

  // ===== 메인(20) =====
  if (hi_0845!=null && lo_0845!=null){
    yAxis.addPlotLine({
      id:'hi0845', value:hi_0845, color:HI_COLOR, width:2,
      label:{ text:`고 ${hi_0845.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:HI_COLOR} }
    });
    yAxis.addPlotLine({
      id:'lo0845', value:lo_0845, color:LO_COLOR, width:2,
      label:{ text:`저 ${lo_0845.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:LO_COLOR} }
    });
    if (open_0845 != null){
      yAxis.addPlotLine({
        id:'op0845', value:open_0845, color:OPEN_COLOR, width:1, dashStyle:'Dash',
        label:{ text:`시가 ${open_0845.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:OPEN_COLOR} }
      });
    }
  }
}

function applyHiLoLinesAll(){ applyHiLoLines(c1); applyHiLoLines(c5); applyHiLoLines(c15); applyHiLoLines(c60); }

// ===== AUX(60분) 기준선 지연 표시 =====
const AUX_MINUTES = 60;         // “60분봉이 지났을 때”
let AUX_ANCHOR_MS = null;       // aux 기준 시작시각(ms) - API에서 결정
let auxDrawn = false;           // aux를 이미 그렸는지(한번만)

function clearAuxLines(chart){
  if (!chart) return;
  const yAxis = chart.yAxis[0];
  ['hi0845_aux','lo0845_aux','op0845_aux'].forEach(id => {
    try{ yAxis.removePlotLine(id); }catch(e){}
  });
}
function clearAuxLinesAll(){
  clearAuxLines(c1); clearAuxLines(c5); clearAuxLines(c15); clearAuxLines(c60);
}

function drawAuxLines(chart){
  if (!chart) return;
  if (hi_0845_aux == null || lo_0845_aux == null) return;

  const yAxis = chart.yAxis[0];
  // 혹시 남아있으면 지우고 다시
  clearAuxLines(chart);

  const auxHi = `rgba(255,79,179,0.9)`;
  const auxLo = `rgba(79,195,255)`;

  yAxis.addPlotLine({
    id:'hi0845_aux', value:hi_0845_aux, color:auxHi, width:1, dashStyle:'ShortDot',
    label:{ text:`(60) 고 ${hi_0845_aux.toFixed(2)}`, align:'left', x:5, style:{fontSize:'10px', color:auxHi} }
  });
  yAxis.addPlotLine({
    id:'lo0845_aux', value:lo_0845_aux, color:auxLo, width:1, dashStyle:'ShortDot',
    label:{ text:`(60) 저 ${lo_0845_aux.toFixed(2)}`, align:'left', x:5, style:{fontSize:'10px', color:auxLo} }
  });
}

function drawAuxLinesAll(){
  drawAuxLines(c1); drawAuxLines(c5); drawAuxLines(c15); drawAuxLines(c60);
}

function shouldUnlockAux(nowTs){
  if (!Number.isFinite(nowTs)) return false;
  if (AUX_ANCHOR_MS == null) return false;
  return nowTs >= (AUX_ANCHOR_MS + AUX_MINUTES * 60 * 1000);
}

// “unlock 되는 순간”에만 1회 그리기
function maybeUnlockAux(nowTs, doRedraw){
  if (auxDrawn) return;
  if (!shouldUnlockAux(nowTs)) return;

  auxDrawn = true;
  drawAuxLinesAll();

  if (doRedraw) {
    queueRedrawAll();
  }
  dbgLine('AUX lines ON (unlocked once)');
}

/** ================== 시간 표시(세로선) - 차트별 커스텀 ================== */

// "HH:MM" 배열 -> plotLine 객체 배열로 변환
function marksFromHHMM(list, idPrefix){
  return (list || []).map(hhmm => ({
    id: `${idPrefix}${hhmm.replace(':','')}`, // 예: m1_0930
    hhmmss: `${hhmm}:00`,
    label: hhmm
  }));
}

// ✅ 여기만 네가 원하는대로 편집하면 됨 (차트별 시간 목록)
// - 표시하고 싶은 시간을 "HH:MM" 형태로 넣기
// - 차트에서 표시 안하고 싶으면 [] 로 두기
const TIME_MARKS_CFG = {
  m1:  ['09:20','09:45','10:45','11:45','12:45','13:45','14:45'],
  m5:  ['09:45','10:45','11:45','12:45','13:45','14:45'],
  m15: ['09:45','10:45','11:45','12:45','13:45','14:45'],
  m60: ['08:45'] // 필요하면 넣고, 아니면 빈 배열
};

// 차트별 실제 marks 오브젝트 생성
const TIME_MARKS_BY_CHART = {
  c1:  marksFromHHMM(TIME_MARKS_CFG.m1,  'm1_'),
  c5:  marksFromHHMM(TIME_MARKS_CFG.m5,  'm5_'),
  c15: marksFromHHMM(TIME_MARKS_CFG.m15, 'm15_'),
  c60: marksFromHHMM(TIME_MARKS_CFG.m60, 'm60_')
};

function initTimeMarks(chart, marks){
  if (!chart) return;

  const xa = chart.xAxis[0];

  // ✅ 이전 marks + 새 marks 모두 제거(교체/리셋 안전)
  const prev = chart._marksList || [];
  const all = [...prev, ...(marks || [])];
  const ids = new Set(all.map(m => m.id));
  ids.forEach(id => { try{ xa.removePlotLine(id); }catch(e){} });

  chart._marksAdded = new Set();
  chart._marksList = marks || [];
}

function updateTimeMarksOnce(chart, nowTs){
  if (!chart || nowTs == null) return;
  const xa = chart.xAxis[0];
  const marks = chart._marksList || [];

  for (const m of marks){
    if (chart._marksAdded.has(m.id)) continue;

    const t = toTSLocal(DATE, m.hhmmss);
    if (t <= nowTs){
      xa.addPlotLine({
        id: m.id,
        value: t,
        width: 1,
        color: 'rgba(0,0,0,0.25)',
        zIndex: 1,
        label: { text: m.label, align:'left', x:3, y:12, style:{ fontSize:'10px', color:'#444' } }
      });
      chart._marksAdded.add(m.id);
    }
  }
}

function updateTimeMarksAll(nowTs){
  updateTimeMarksOnce(c1, nowTs);
  updateTimeMarksOnce(c5, nowTs);
  updateTimeMarksOnce(c15, nowTs);
  updateTimeMarksOnce(c60, nowTs);
}

/** ================== 맵 생성(키=ms) ================== */
function buildMaps(){
  map5 = new Map(); map15 = new Map(); map60 = new Map();
  (today5||[]).forEach(r  => map5.set(toMs(r.datetime), r));
  (today15||[]).forEach(r => map15.set(toMs(r.datetime), r));
  (today60||[]).forEach(r => map60.set(toMs(r.datetime), r));
}

/** ================== 안전 upsert(x 기준) ================== */
function ensureCandleAtX(chart, x, o,h,l,c){
  const s = chart.series[0];
  const pts = s.points || [];
  const last = pts[pts.length - 1];

  if (last && last.x === x) { last.update({ open:o, high:h, low:l, close:c }, false); return; }
  const same = pts.find(p => p && p.x === x);
  if (same) { same.update({ open:o, high:h, low:l, close:c }, false); return; }

  if (last && x < last.x) return; // 역순 삽입 방지
  s.addPoint([x,o,h,l,c], false);
}
function ensureVolAtX(chart, x, v){
  const s = chart.series[4];
  const pts = s.points || [];
  const last = pts[pts.length - 1];

  if (last && last.x === x) { last.update({ y:v }, false); return; }
  const same = pts.find(p => p && p.x === x);
  if (same) { same.update({ y:v }, false); return; }

  if (last && x < last.x) return;
  s.addPoint([x, v], false);
}
function upsertLinePoint(series, x, y){
  if (y == null) return;
  const pts = series.points || [];
  const last = pts[pts.length - 1];

  if (last && last.x === x) { last.update({ y }, false); return; }
  const same = pts.find(p => p && p.x === x);
  if (same) { same.update({ y }, false); return; }

  if (last && x < last.x) return;
  series.addPoint([x, y], false);
}

/** ================== 5/15/60 진행봉 업데이트(핵심) ================== */
function updatePartialSafe(chart, bucketMin, bar, officialMap, partialRef){
  const bucketTs = bucketStart(bar.x, bucketMin);

  if (partialRef.obj && partialRef.obj.bucketTs !== bucketTs) {
    const prevTs = partialRef.obj.bucketTs;
    const off = officialMap.get(prevTs);
    if (off){
      ensureCandleAtX(chart, prevTs, +off.open, +off.high, +off.low, +off.close);
      ensureVolAtX(chart, prevTs, +(off.volume||0));
      upsertLinePoint(chart.series[1], prevTs, off.sma_5   != null ? +off.sma_5   : null);
      upsertLinePoint(chart.series[2], prevTs, off.sma_20  != null ? +off.sma_20  : null);
      upsertLinePoint(chart.series[3], prevTs, off.sma_120 != null ? +off.sma_120 : null);
    }
  }

  if (!partialRef.obj || partialRef.obj.bucketTs !== bucketTs) {
    partialRef.obj = { bucketTs, o:bar.o, h:bar.h, l:bar.l, c:bar.c, v:bar.v };
  } else {
    partialRef.obj.h = Math.max(partialRef.obj.h, bar.h);
    partialRef.obj.l = Math.min(partialRef.obj.l, bar.l);
    partialRef.obj.c = bar.c;
    partialRef.obj.v += bar.v;
  }

  const p = partialRef.obj;
  ensureCandleAtX(chart, p.bucketTs, p.o, p.h, p.l, p.c);
  ensureVolAtX(chart, p.bucketTs, p.v);
}

/** ================== 트림 ================== */
function trimChartByCandles(chart, maxCandles){
  const sC = chart.series[0];
  if (!sC || !sC.points) return;

  while (sC.points.length > maxCandles) sC.removePoint(0, false);

  const xMin = sC.points.length ? sC.points[0].x : null;
  if (xMin == null) return;

  for (let si=1; si<chart.series.length; si++){
    const s = chart.series[si];
    if (!s || !s.points) continue;
    while (s.points.length && s.points[0].x < xMin) s.removePoint(0, false);
  }
}

/** ================== 팔로우(보이게 만드는 핵심) ================== */
function applyFollowView(chart){
  if (!chart) return;
  if (!document.getElementById('chkFollow').checked) return;

  const s = chart.series[0];
  const xs = s.xData || [];          // ✅ points 말고 xData 사용
  if (xs.length < 2) return;

  const xMin = xs[0];
  const xMax = xs[xs.length - 1];

  const xa = chart.xAxis[0];
  const prev = chart._followExt || {min:null,max:null};
  if (prev.min === xMin && prev.max === xMax) return;

  chart._followExt = {min:xMin, max:xMax};
  xa.setExtremes(xMin, xMax, false, false);
}

/** ================== redraw 큐(멈춤 방지 핵심) ================== */
const redrawQueue = [];
const redrawQueued = new Set();
let redrawRaf = null;

function queueRedraw(chart){
  if (!chart || redrawQueued.has(chart)) return;
  redrawQueued.add(chart);
  redrawQueue.push(chart);
  if (!redrawRaf){
    redrawRaf = requestAnimationFrame(processRedrawQueue);
  }
}
function queueRedrawAll(){
  queueRedraw(c1); queueRedraw(c5); queueRedraw(c15); queueRedraw(c60);
}
function processRedrawQueue(){
  redrawRaf = null;
  if (!redrawQueue.length) return;

  const chart = redrawQueue.shift();
  redrawQueued.delete(chart);

  const t0 = performance.now();
  try{
    applyFollowView(chart);
    chart.redraw(false);
  }catch(e){
    dbgLine(`redraw error: ${e.message || e}`);
    console.error(e);
  }
  const dt = performance.now() - t0;
  DBG.lastRedrawMs = dt;
  if (dt > 50) dbgLine(`slow redraw ${dt.toFixed(1)}ms`);

  // 남아 있으면 다음 프레임에 또 1개 처리
  if (redrawQueue.length){
    redrawRaf = requestAnimationFrame(processRedrawQueue);
  }
  dbgKV();
}

/** ================== 매매 KPI(시간/종가/이격/손익) ================== */
let currentDay = null; // API day 상태 저장

function fmtNum(v, d=2){
  if (v == null || v === '') return '-';
  const n = Number(v);
  if (!Number.isFinite(n)) return '-';
  return n.toFixed(d);
}
function fmtInt(v){
  const n = Number(v);
  if (!Number.isFinite(n)) return '-';
  return Math.round(n).toLocaleString();
}

// 결정봉 row 가져오기
function getDecisionRow(){
  if (idx > 0 && today1 && today1.length >= idx){
    return today1[idx - 1] || null;
  }
  if (init1 && init1.length){
    return init1[init1.length - 1] || null;
  }
  return null;
}

/** ================== reset/init ================== */
function resetToInit(){
  stop();
  resetCharts();

  idx = 0;
  lastBarDt = null;

  partial5  = initPartial5  ? { ...initPartial5 }  : null;
  partial15 = initPartial15 ? { ...initPartial15 } : null;
  partial60 = initPartial60 ? { ...initPartial60 } : null;

  c1.series[0].setData(rowsToCandles(init1), false);
  c1.series[1].setData(rowsToLine(init1, 'sma_5'), false);
  c1.series[2].setData(rowsToLine(init1, 'sma_20'), false);
  c1.series[3].setData(rowsToLine(init1, 'sma_120'), false);
  c1.series[4].setData(rowsToVol(init1), false);

  c5.series[0].setData(rowsToCandles(init5), false);
  c5.series[1].setData(rowsToLine(init5, 'sma_5'), false);
  c5.series[2].setData(rowsToLine(init5, 'sma_20'), false);
  c5.series[3].setData(rowsToLine(init5, 'sma_120'), false);
  c5.series[4].setData(rowsToVol(init5), false);

  c15.series[0].setData(rowsToCandles(init15), false);
  c15.series[1].setData(rowsToLine(init15, 'sma_5'), false);
  c15.series[2].setData(rowsToLine(init15, 'sma_20'), false);
  c15.series[3].setData(rowsToLine(init15, 'sma_120'), false);
  c15.series[4].setData(rowsToVol(init15), false);

  c60.series[0].setData(rowsToCandles(init60), false);
  c60.series[1].setData(rowsToLine(init60, 'sma_5'), false);
  c60.series[2].setData(rowsToLine(init60, 'sma_20'), false);
  c60.series[3].setData(rowsToLine(init60, 'sma_120'), false);
  c60.series[4].setData(rowsToVol(init60), false);

  applyHiLoLinesAll();

  // ✅ reset 시 aux는 항상 숨김부터 시작
  clearAuxLinesAll();
  auxDrawn = false;

  // reset 시점의 “현재시간” 추정 (init1 마지막 봉 시간)
  let initLastTs = null;
  if (init1 && init1.length) initLastTs = toMs(init1[init1.length - 1].datetime);
  if (!Number.isFinite(initLastTs)) initLastTs = initNowTs;

  // ✅ 시작 자체가 이미 60분 이후면(예: 09:50부터 시작) 바로 표시되는 게 정상
  if (shouldUnlockAux(initLastTs)){
    auxDrawn = true;
    drawAuxLinesAll();
  }

  if (initNowTs != null) updateTimeMarksAll(initNowTs);

  queueRedrawAll();

  setStat(`${DATE} ${START_AT} 기준 / 남은 1분봉 ${today1.length}개`);
  dbgLine(`reset ok. future1=${today1.length}`);
  dbgKV();
}

/** ================== 1분봉 1개 진행 ================== */
function addOrUpdate1m(bar){
  const s = c1.series[0];
  const pts = s.points || [];
  const last = pts[pts.length - 1];
  if (last && last.x === bar.x){
    last.update({ open:bar.o, high:bar.h, low:bar.l, close:bar.c }, false);
  } else {
    s.addPoint([bar.x,bar.o,bar.h,bar.l,bar.c], false);
  }

  // line
  if (bar.sma5 != null) c1.series[1].addPoint([bar.x, bar.sma5], false);
  if (bar.sma20 != null) c1.series[2].addPoint([bar.x, bar.sma20], false);
  if (bar.sma120 != null) c1.series[3].addPoint([bar.x, bar.sma120], false);

  // vol
  const vS = c1.series[4];
  const vPts = vS.points || [];
  const vLast = vPts[vPts.length - 1];
  if (vLast && vLast.x === bar.x) vLast.update({ y:bar.v }, false);
  else vS.addPoint([bar.x, bar.v], false);
}

function redrawNow(chart){
  if (!chart) return;
  try{
    applyFollowView(chart);
    chart.redraw(false);
  }catch(e){
    dbgLine(`redrawNow error: ${e.message || e}`);
  }
}

function addOne(opts){
  opts = opts || {};
  const doStat   = (opts.stat !== false);
  const doRedraw = (opts.redraw !== false);

  if (idx >= today1.length) { stop(); setStat('끝'); dbgLine('END'); return; }

  const r = today1[idx];
  const x = toMs(r.datetime);
  if (!Number.isFinite(x)) { stop(); setStat(`중단: datetime 파싱 실패 idx=${idx}`); return; }

  const bar = {
    datetime: r.datetime,
    x,
    o:+r.open, h:+r.high, l:+r.low, c:+r.close,
    v:+(r.volume||0),
    sma5:  r.sma_5   != null ? +r.sma_5   : null,
    sma20: r.sma_20  != null ? +r.sma_20  : null,
    sma120:r.sma_120 != null ? +r.sma_120 : null
  };

  lastBarDt = r.datetime;

  // ✅ 데이터 업데이트는 동일
  addOrUpdate1m(bar);
  updatePartialSafe(c5,  5,  bar, map5,  { get obj(){ return partial5;  }, set obj(v){ partial5=v;  } });
  updatePartialSafe(c15, 15, bar, map15, { get obj(){ return partial15; }, set obj(v){ partial15=v; } });
  updatePartialSafe(c60, 60, bar, map60, { get obj(){ return partial60; }, set obj(v){ partial60=v; } });

  updateTimeMarksAll(bar.x);

  if (c1.series[0].points.length  > FIX_MAX_1  + 2) trimChartByCandles(c1,  FIX_MAX_1);
  if (c5.series[0].points.length  > FIX_MAX_5  + 2) trimChartByCandles(c5,  FIX_MAX_5);
  if (c15.series[0].points.length > FIX_MAX_15 + 2) trimChartByCandles(c15, FIX_MAX_15);
  if (c60.series[0].points.length > FIX_MAX_60 + 2) trimChartByCandles(c60, FIX_MAX_60);

  idx++;

  if (doStat) setStat(`${r.datetime} (${idx}/${today1.length})`);

  // ✅ 핵심: 1분봉은 즉시 반영
  if (doRedraw){
    redrawNow(c1);           // <-- 여기!
    queueRedraw(c5);
    queueRedraw(c15);
    queueRedraw(c60);
  }

  maybeUnlockAux(bar.x, doRedraw);
}

/** ================== 재생 루프 (try/catch로 “틱 스케줄 누락” 방지) ================== */
let playing = false;
function play(){
  if (playing) return;
  playing = true;
  dbgLine('PLAY');
  tick();
}
function stop(){
  if (!playing) return;
  playing = false;
  dbgLine('PAUSE');
}
function tick(){
  if (!playing) return;

  const sp = parseInt(document.getElementById('speed').value, 10);
  const t0 = performance.now();
  DBG.lastTickAt = t0;

  try{
    addOne();
  }catch(e){
    console.error(e);
    dbgLine(`tick crash: ${e.message || e}`);
    setStat('중단: tick crash (DBG 확인)');
    playing = false;
    dbgKV();
    return;
  }

  const dt = performance.now() - t0;
  DBG.lastTickMs = dt;
  if (dt > 60) dbgLine(`slow tick ${dt.toFixed(1)}ms at idx=${idx} last=${lastBarDt||''}`);
  dbgKV();

  setTimeout(tick, Math.max(0, sp - dt));
}

/** ================== 1분전 되돌리기 ================== */
function rebuildTo(targetIdx){
  stop();
  resetToInit();

  const tgt = Math.max(0, Math.min(targetIdx, today1.length));
  while (idx < tgt){
    addOne({ redraw:false, stat:false, kpi:false });
  }

  setStat(`${lastBarDt || '-'} (${idx}/${today1.length})`);
  queueRedrawAll();
}

function stepBackOne(){
  stop();
  if (idx <= 0) return;
  rebuildTo(idx - 1);
}

/** ================== 데이터 로드 ================== */
async function loadData(){
  setStat('로딩 중...');
  dbgLine('loadData start');

  const url =
    `${API_URL}?date=${encodeURIComponent(DATE)}&start=08:45:00&end=15:00:00`
    + `&init_time=${encodeURIComponent(INIT_TIME)}`
    + `&max_prev_1m=${FIX_MAX_1}&max_prev_5m=${FIX_MAX_5}&max_prev_15m=${FIX_MAX_15}&max_prev_60m=${FIX_MAX_60}`
    + `&init_max_1m=${FIX_MAX_1}&init_max_5m=${FIX_MAX_5}&init_max_15m=${FIX_MAX_15}&init_max_60m=${FIX_MAX_60}`
    + `&hi_lo_n=20&hi_lo_n2=60`;

  dbgLine(`API url: ${url}`);

  let resp, text;
  try{
    resp = await fetch(url, { cache:'no-store' });
    text = '' + await resp.text();
  }catch(e){
    console.error(e);
    dbgLine('fetch failed');
    setStat('로드 실패(fetch)');
    return;
  }

  if (!resp.ok){
    console.error('HTTP not ok:', resp.status, resp.statusText, text.slice(0,800));
    dbgLine(`HTTP ${resp.status} ${resp.statusText}`);
    setStat(`HTTP ${resp.status} ${resp.statusText}`);
    return;
  }

  let res;
  try{ res = JSON.parse(text); }
  catch(e){
    console.error('JSON parse failed. body head:', text.slice(0,1200));
    dbgLine('JSON parse fail');
    setStat('로드 실패(JSON 파싱)');
    return;
  }

  if (!res || !res.ok){
    console.error('API ok=false:', res);
    dbgLine(`API ok=false: ${res?.msg || ''}`);
    setStat(res?.msg || '로드 실패(API)');
    return;
  }

  if (!res.m60){
    dbgLine('API에 m60 없음 (replay_api_multi.php m60 포함 필요)');
    setStat('API에 m60 없음 (m60 포함 버전 필요)');
    return;
  }

  init1  = res.m1?.init  || [];
  init5  = res.m5?.init  || [];
  init15 = res.m15?.init || [];
  init60 = res.m60?.init || [];

  today1 = res.m1?.future || [];
  today5  = res.m5?.today  || [];
  today15 = res.m15?.today || [];
  today60 = res.m60?.today || [];
  buildMaps();

  initPartial5  = res.m5?.partial  ? { ...res.m5.partial }  : null;
  initPartial15 = res.m15?.partial ? { ...res.m15.partial } : null;
  initPartial60 = res.m60?.partial ? { ...res.m60.partial } : null;

  if (res.hilo_main){
    open_0845 = +res.hilo_main.open;
    hi_0845   = +res.hilo_main.high;
    lo_0845   = +res.hilo_main.low;
    hiMeta = { start: res.hilo_main.start_hhmm, end: res.hilo_main.end_hhmm };
  }

  if (res.hilo_aux){
    open_0845_aux = +res.hilo_aux.open;
    hi_0845_aux   = +res.hilo_aux.high;
    lo_0845_aux   = +res.hilo_aux.low;
    hiMetaAux = { start: res.hilo_aux.start_hhmm, end: res.hilo_aux.end_hhmm };

    // ✅ aux 기준 시작시각(예: '08:45' 또는 그날의 실제 시작)
    const auxStartHHMM = res.hilo_aux.start_hhmm || '08:45';
    AUX_ANCHOR_MS = toTSLocal(DATE, auxStartHHMM + ':00');
  } else {
    AUX_ANCHOR_MS = null;
  }

  initNowTs = (res.nowTs != null) ? +res.nowTs : null;

  dbgLine(`API OK. init1=${init1.length}, future1=${today1.length}, today60=${today60.length}`);
  resetToInit();
}

/** ================== 버튼 바인딩 ================== */
document.getElementById('btnPlay').addEventListener('click', play);
document.getElementById('btnPause').addEventListener('click', stop);
document.getElementById('btnBack').addEventListener('click', stepBackOne);
document.getElementById('btnStep').addEventListener('click', () => { stop(); addOne(); });
document.getElementById('btnReset').addEventListener('click', resetToInit);

document.getElementById('btnPrev').addEventListener('click', ()=>{
  const d = document.getElementById('prevDate').value;
  if (!d) return;
  document.querySelector('input[name="date"]').value = d;
  document.querySelector('form').submit();
});
document.getElementById('btnNext').addEventListener('click', ()=>{
  const d = document.getElementById('nextDate').value;
  if (!d) return;
  document.querySelector('input[name="date"]').value = d;
  document.querySelector('form').submit();
});

// DBG 토글
document.getElementById('btnDbg').addEventListener('click', ()=>{
  DBG.enabled = !DBG.enabled;
  document.getElementById('dbgPanel').style.display = DBG.enabled ? 'block' : 'none';
  if (DBG.enabled){
    document.getElementById('dbgLog').textContent = DBG.logLines.join('\n');
    dbgKV();
  }
});
document.getElementById('btnDump').addEventListener('click', ()=>{
  console.log('=== REPLAY STATE DUMP ===', {
    DATE, INIT_TIME, idx, today1_len: today1.length, lastBarDt,
    partial5, partial15, partial60,
    initPartial5, initPartial15, initPartial60,
    map5_size: map5.size, map15_size: map15.size, map60_size: map60.size,
    hilo: { open_0845, hi_0845, lo_0845, hiMeta },
    follow: document.getElementById('chkFollow').checked,
  });
  dbgLine('dumped to console');
});
document.getElementById('btnClearLog').addEventListener('click', ()=>{
  DBG.logLines = [];
  document.getElementById('dbgLog').textContent = '';
});

/** ================== 최초 ================== */
resetCharts();
loadData();
</script>
</body>
</html>
