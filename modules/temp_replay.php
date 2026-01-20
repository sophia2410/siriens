<?php
// futures_replay_view.php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
$date  = $_GET['date']  ?? date('Y-m-d');
$speed = $_GET['speed'] ?? '1000';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>선물차트 리플레이</title>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    body{margin:0;padding:10px;font-family:sans-serif;background:#fafafa;}
    .panel{border:1px solid #ddd;background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.05);padding:10px;}
    .topbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px;}
    .grid{display:grid;grid-template-columns:2fr 5fr;gap:12px;margin-bottom:12px;}
    .bottom{display:grid;grid-template-columns:1fr;gap:12px;}
    .charttitle{font-weight:700;margin:0 0 6px;color:#333;}
    .stat{margin-left:auto;font-weight:700;}
    input,select,button{padding:6px 8px;}
    button{cursor:pointer;}
    .hint{font-size:12px;color:#666;margin-top:6px;line-height:1.45;}
    button:disabled{opacity:.5;cursor:not-allowed;}
  </style>
</head>
<body>

<div class="panel topbar">
  <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <div><b>일자</b> <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"></div>

    <div><b>속도</b>
      <select id="speed" name="speed">
        <option value="2000" <?= $speed==='2000'?'selected':'' ?>>2초=1분</option>
        <option value="1000" <?= $speed==='1000'?'selected':'' ?>>1초=1분</option>
        <option value="500"  <?= $speed==='500'?'selected':''  ?>>0.5초=1분</option>
      </select>
    </div>

    <button type="submit">불러오기</button>
    <button type="button" id="btnPlay">▶ 재생</button>
    <button type="button" id="btnPause">⏸ 정지</button>
    <button type="button" id="btnStep1">+1분</button>
    <button type="button" id="btnStep5">+5분</button>
    <button type="button" id="btnStep10">+10분</button>
    <button type="button" id="btnReset">↺ 리셋</button>

    <div class="stat" id="stat">로딩 전</div>
  </form>

  <div class="hint">
    - 불러오기: 전일 tail(고정 max) + 당일 프리필(init_1m) 적용<br>
    - 재생/스텝: 브라우저에서 1분씩 계산하지 않고, advance API가 N분 묶음 계산 결과를 내려주면 그대로 upsert 적용<br>
    - 첫 N개(기본 20개) 1분봉 기준 시초/최고/최저 라인을 표시(시작 시간이 달라져도 동일)
  </div>
</div>

<div class="grid">
  <div class="panel">
    <div class="charttitle">15분봉</div>
    <div id="chart15" style="height:300px;"></div>
  </div>
  <div class="panel">
    <div class="charttitle">5분봉</div>
    <div id="chart5" style="height:300px;"></div>
  </div>
</div>

<div class="bottom">
  <div class="panel">
    <div class="charttitle">1분봉 (전일 tail + 당일 진행) + SMA 5/20/120 + 첫 20개 시초/고/저</div>
    <div id="chart1" style="height:380px;"></div>
  </div>
</div>

<script>
const DATE = <?= json_encode($date) ?>;

// ===== 고정 최대 캔들 =====
const FIX_MAX_1  = 120;
const FIX_MAX_5  = 83;
const FIX_MAX_15 = 29;

// ===== 초기 프리필(당일 진행된 상태로 시작) =====
const INIT_1M = 20;
const LINE_N  = INIT_1M;

// ===== SMA 색 =====
const SMA5_COLOR   = '#d32f2f';
const SMA20_COLOR  = '#f9a825';
const SMA120_COLOR = '#757575';

// ===== 라인 색 =====
const HI_COLOR   = '#ff4fb3';
const LO_COLOR   = '#4fc3ff';
const OPEN_COLOR = '#666666';

let timer = null;

// ===== 데이터 상태 =====
let prev1=[], today1=[];
let prev5=[], today5=[];
let prev15=[], today15=[];
let todayTotal1 = 0;

// ===== 진행 상태 =====
let idx = 0;
let partial5 = null;
let partial15 = null;

// ===== 첫 N개(20) 라인 =====
let firstN = LINE_N;
let open_1st = null;
let hi_1stN = null;
let lo_1stN = null;
let locked_1stN = false;

// ===== 차트 =====
let c1, c5, c15;

// ===== 안정성 =====
let isBusy = false;
let isPlaying = false;

// ===== redraw 최적화 =====
let rafPending = false;
function scheduleRedraw(){
  if (rafPending) return;
  rafPending = true;
  requestAnimationFrame(() => {
    rafPending = false;
    if (c1) c1.redraw(false);
    if (c5) c5.redraw(false);
    if (c15) c15.redraw(false);
  });
}

function ts(dt){ return new Date(dt).getTime(); }
function pad2(n){ return String(n).padStart(2,'0'); }
function setStat(msg){ document.getElementById('stat').textContent = msg; }

function setControlsDisabled(disabled){
  // pause는 항상 살아있게
  $('#btnPause').prop('disabled', false);
  $('#btnPlay,#btnStep1,#btnStep5,#btnStep10,#btnReset').prop('disabled', disabled);
}

function floorBucket(ms, minutes){
  const d = new Date(ms);
  d.setSeconds(0,0);
  d.setMinutes(Math.floor(d.getMinutes()/minutes)*minutes);
  return d.getTime();
}

/* =========================
   차트 생성
========================= */
function makeBaseChart(el, name){
  return Highcharts.stockChart(el, {
    chart:{ zooming:{mouseWheel:{enabled:false}, type:null}, panning:false },
    navigator:{enabled:false}, scrollbar:{enabled:false}, rangeSelector:{enabled:false},
    title:{text:''}, time:{useUTC:false},
    xAxis: { type:'datetime', labels:{format:'{value:%H:%M}'}, startOnTick:false, endOnTick:false },
    yAxis:[
      { height:'78%', lineWidth:1, labels:{ align:'right', x:8, reserveSpace:true } },
      { top:'80%', height:'20%', offset:0, lineWidth:1, min:0 }
    ],
    series:[
      { type:'candlestick', name, data:[], zIndex:4 }, // 0
      { type:'line', name:'SMA 5', data:[], lineWidth:2, color:SMA5_COLOR },   // 1
      { type:'line', name:'SMA 20', data:[], lineWidth:2, color:SMA20_COLOR }, // 2
      { type:'line', name:'SMA 120', data:[], lineWidth:2, color:SMA120_COLOR },//3
      { type:'column', name:'Vol', data:[], yAxis:1 } // 4
    ],
    plotOptions:{
      series:{ enableMouseTracking:true, states:{ hover:{ enabled:false } } },
      candlestick:{ color:'#2f7ed8', upColor:'#f45b5b', lineColor:'#2f7ed8', upLineColor:'#f45b5b' }
    }
  });
}
function make1mChart(){
  return Highcharts.stockChart('chart1', {
    chart:{ zooming:{mouseWheel:{enabled:false}, type:null}, panning:false },
    navigator:{enabled:false}, scrollbar:{enabled:false}, rangeSelector:{enabled:false},
    title:{text:''}, time:{useUTC:false},
    xAxis: { type:'datetime', labels:{format:'{value:%H:%M}'}, minPadding:0.05, maxPadding:0.02, startOnTick:false, endOnTick:false },
    yAxis:[
      { height:'80%', lineWidth:1, labels:{ align:'right', x:8, reserveSpace:true } },
      { top:'82%', height:'18%', offset:0, lineWidth:1, min:0 }
    ],
    series:[
      { type:'candlestick', name:'1m', data:[], zIndex:4 }, // 0
      { type:'line', name:'SMA 5', data:[], lineWidth:2, color:SMA5_COLOR },   // 1
      { type:'line', name:'SMA 20', data:[], lineWidth:2, color:SMA20_COLOR }, // 2
      { type:'line', name:'SMA 120', data:[], lineWidth:2, color:SMA120_COLOR },//3
      { type:'column', name:'Vol', data:[], yAxis:1 } // 4
    ],
    plotOptions:{
      series:{ enableMouseTracking:true, states:{ hover:{ enabled:false } } },
      candlestick:{ color:'#2f7ed8', upColor:'#f45b5b', lineColor:'#2f7ed8', upLineColor:'#f45b5b' }
    }
  });
}

/* =========================
   “첫 N개” 라인
========================= */
function applyHiLoLines(chart){
  if (!chart) return;
  const yAxis = chart.yAxis[0];

  ['hiN','loN','opN'].forEach(id => { try{ yAxis.removePlotLine(id); }catch(e){} });
  if (hi_1stN==null || lo_1stN==null) return;

  yAxis.addPlotLine({
    id:'hiN', value:hi_1stN, color:HI_COLOR, width:2,
    label:{ text:`첫 ${firstN}개 고 ${hi_1stN.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:HI_COLOR} }
  });
  yAxis.addPlotLine({
    id:'loN', value:lo_1stN, color:LO_COLOR, width:2,
    label:{ text:`첫 ${firstN}개 저 ${lo_1stN.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:LO_COLOR} }
  });
  if (open_1st != null){
    yAxis.addPlotLine({
      id:'opN', value:open_1st, color:OPEN_COLOR, width:1, dashStyle:'Dash',
      label:{ text:`첫 봉 시초 ${open_1st.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:OPEN_COLOR} }
    });
  }
}
function applyHiLoLinesAll(){ applyHiLoLines(c1); applyHiLoLines(c5); applyHiLoLines(c15); }

/* =========================
   upsert 유틸
========================= */
function upsertLinePoint(series, x, y){
  if (y == null) return;
  const last = series.points && series.points[series.points.length - 1];
  if (last && last.x === x) { last.update({ y }, false); return; }
  const same = series.points && series.points.find(p => p && p.x === x);
  if (same) { same.update({ y }, false); return; }
  series.addPoint([x, y], false);
}

function ensureCandleAtX(chart, x, o,h,l,c){
  const s = chart.series[0];
  const last = s.points && s.points[s.points.length - 1];
  if (last && last.x === x) { last.update({ open:o, high:h, low:l, close:c }, false); return; }
  const same = s.points && s.points.find(p => p && p.x === x);
  if (same) { same.update({ open:o, high:h, low:l, close:c }, false); return; }
  s.addPoint([x, o,h,l,c], false);
}
function ensureVolAtX(chart, x, v){
  const s = chart.series[4];
  const last = s.points && s.points[s.points.length - 1];
  if (last && last.x === x) { last.update({ y:v }, false); return; }
  const same = s.points && s.points.find(p => p && p.x === x);
  if (same) { same.update({ y:v }, false); return; }
  s.addPoint([x, v], false);
}

/* =========================
   트림
========================= */
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

/* =========================
   reset
========================= */
function resetCharts(){
  $('#chart15').empty(); $('#chart5').empty(); $('#chart1').empty();
  c15 = makeBaseChart('chart15', '15m');
  c5  = makeBaseChart('chart5',  '5m');
  c1  = make1mChart();
}

function resetToStart(){
  // ✅ 여기서 pause() 절대 호출하지 않음 (로드/프리필 꼬임 방지)
  resetCharts();

  idx = 0;
  partial5 = null;
  partial15 = null;

  firstN = LINE_N;
  open_1st = null;
  hi_1stN = null;
  lo_1stN = null;
  locked_1stN = false;

  // 1m 전일 tail
  const prevStart1 = Math.max(0, prev1.length - FIX_MAX_1);
  for (let i=prevStart1; i<prev1.length; i++){
    const r = prev1[i];
    const x = ts(r.datetime);
    c1.series[0].addPoint([x, +r.open, +r.high, +r.low, +r.close], false);
    if (r.sma_5   != null) c1.series[1].addPoint([x, +r.sma_5], false);
    if (r.sma_20  != null) c1.series[2].addPoint([x, +r.sma_20], false);
    if (r.sma_120 != null) c1.series[3].addPoint([x, +r.sma_120], false);
    c1.series[4].addPoint([x, +(r.volume||0)], false);
  }

  // 5m 전일 tail
  const prevStart5 = Math.max(0, prev5.length - FIX_MAX_5);
  for (let i=prevStart5; i<prev5.length; i++){
    const r = prev5[i];
    const x = floorBucket(ts(r.datetime), 5);
    ensureCandleAtX(c5, x, +r.open, +r.high, +r.low, +r.close);
    ensureVolAtX(c5, x, +(r.volume||0));
    if (r.sma_5   != null) upsertLinePoint(c5.series[1], x, +r.sma_5);
    if (r.sma_20  != null) upsertLinePoint(c5.series[2], x, +r.sma_20);
    if (r.sma_120 != null) upsertLinePoint(c5.series[3], x, +r.sma_120);
  }

  // 15m 전일 tail
  const prevStart15 = Math.max(0, prev15.length - FIX_MAX_15);
  for (let i=prevStart15; i<prev15.length; i++){
    const r = prev15[i];
    const x = floorBucket(ts(r.datetime), 15);
    ensureCandleAtX(c15, x, +r.open, +r.high, +r.low, +r.close);
    ensureVolAtX(c15, x, +(r.volume||0));
    if (r.sma_5   != null) upsertLinePoint(c15.series[1], x, +r.sma_5);
    if (r.sma_20  != null) upsertLinePoint(c15.series[2], x, +r.sma_20);
    if (r.sma_120 != null) upsertLinePoint(c15.series[3], x, +r.sma_120);
  }

  scheduleRedraw();
  setStat('리셋 완료: 전일 tail 표시');
}

/* =========================
   Prefill 적용 (불러오기 시 20분)
========================= */
function applyPrefill(pf){
  if (!pf || !pf.init_n || pf.init_n <= 0) {
    setStat('프리필 없음: idx=0부터 시작');
    return;
  }

  // idx 설정
  idx = Math.min(pf.init_n || 0, todayTotal1 || 999999);

  // 첫 N개 라인(서버 계산)
  if (pf.lines){
    firstN = pf.lines.n || LINE_N;
    open_1st = (pf.lines.open_1st != null) ? +pf.lines.open_1st : null;
    hi_1stN  = (pf.lines.hi_1stN  != null) ? +pf.lines.hi_1stN  : null;
    lo_1stN  = (pf.lines.lo_1stN  != null) ? +pf.lines.lo_1stN  : null;
    locked_1stN = !!pf.lines.locked;
    if (locked_1stN) applyHiLoLinesAll();
  }

  // 1m init_n개 추가
  (pf.m1 || []).forEach(r => {
    const x = ts(r.datetime);
    c1.series[0].addPoint([x, +r.open, +r.high, +r.low, +r.close], false);
    if (r.sma_5   != null) c1.series[1].addPoint([x, +r.sma_5], false);
    if (r.sma_20  != null) c1.series[2].addPoint([x, +r.sma_20], false);
    if (r.sma_120 != null) c1.series[3].addPoint([x, +r.sma_120], false);
    c1.series[4].addPoint([x, +(r.volume||0)], false);
  });

  // 5m 완성봉 upsert
  (pf.m5 || []).forEach(r => {
    const x = floorBucket(ts(r.datetime), 5);
    ensureCandleAtX(c5, x, +r.open, +r.high, +r.low, +r.close);
    ensureVolAtX(c5, x, +(r.volume||0));
    upsertLinePoint(c5.series[1], x, r.sma_5   != null ? +r.sma_5   : null);
    upsertLinePoint(c5.series[2], x, r.sma_20  != null ? +r.sma_20  : null);
    upsertLinePoint(c5.series[3], x, r.sma_120 != null ? +r.sma_120 : null);
  });

  // 15m 완성봉 upsert
  (pf.m15 || []).forEach(r => {
    const x = floorBucket(ts(r.datetime), 15);
    ensureCandleAtX(c15, x, +r.open, +r.high, +r.low, +r.close);
    ensureVolAtX(c15, x, +(r.volume||0));
    upsertLinePoint(c15.series[1], x, r.sma_5   != null ? +r.sma_5   : null);
    upsertLinePoint(c15.series[2], x, r.sma_20  != null ? +r.sma_20  : null);
    upsertLinePoint(c15.series[3], x, r.sma_120 != null ? +r.sma_120 : null);
  });

  // partial 5/15 표시
  partial5 = pf.partial5 || null;
  if (partial5 && partial5.bucketTs != null) {
    const x = +partial5.bucketTs;
    ensureCandleAtX(c5, x, +partial5.o, +partial5.h, +partial5.l, +partial5.c);
    ensureVolAtX(c5, x, +(partial5.v||0));
  }
  partial15 = pf.partial15 || null;
  if (partial15 && partial15.bucketTs != null) {
    const x = +partial15.bucketTs;
    ensureCandleAtX(c15, x, +partial15.o, +partial15.h, +partial15.l, +partial15.c);
    ensureVolAtX(c15, x, +(partial15.v||0));
  }

  // 트림
  if (c1.series[0].points.length  > FIX_MAX_1  + 2) trimChartByCandles(c1,  FIX_MAX_1);
  if (c5.series[0].points.length  > FIX_MAX_5  + 2) trimChartByCandles(c5,  FIX_MAX_5);
  if (c15.series[0].points.length > FIX_MAX_15 + 2) trimChartByCandles(c15, FIX_MAX_15);

  setStat(`프리필 적용: 1분 ${idx}봉 진행 상태로 시작`);
  scheduleRedraw();
}

/* =========================
   advanceBy: 스텝/재생 모두 사용
========================= */
async function advanceBy(n){
  if (isBusy) return;
  if (n <= 0) return;

  if (todayTotal1 && idx >= todayTotal1) {
    pause();
    setStat('끝(더 이상 진행할 1분봉 없음)');
    return;
  }

  isBusy = true;
  setControlsDisabled(true);

  try {
    const fromIdx = idx;
    const url = `./replay_advance_api.php?date=${encodeURIComponent(DATE)}`
      + `&start=08:45:00&end=15:00:00`
      + `&from_idx=${fromIdx}&n=${n}`
      + `&line_n=${LINE_N}`;

    const res = await $.getJSON(url);
    if (!res || !res.ok) { setStat(res?.msg || 'advance 실패'); return; }

    applyAdvance(res);

  } catch(e){
    console.error(e);
    pause();
    setStat('advance 실패: 서버 에러(네트워크/콘솔 확인)');
  } finally {
    isBusy = false;
    setControlsDisabled(false);
  }
}

function applyAdvance(res){
  // 1m append
  (res.bars1 || []).forEach(r => {
    const x = ts(r.datetime);
    c1.series[0].addPoint([x, +r.open, +r.high, +r.low, +r.close], false);
    if (r.sma_5   != null) c1.series[1].addPoint([x, +r.sma_5], false);
    if (r.sma_20  != null) c1.series[2].addPoint([x, +r.sma_20], false);
    if (r.sma_120 != null) c1.series[3].addPoint([x, +r.sma_120], false);
    c1.series[4].addPoint([x, +(r.volume||0)], false);
  });

  // 5m 완성봉 upsert
  (res.finalized5 || []).forEach(r => {
    const x = floorBucket(ts(r.datetime), 5);
    ensureCandleAtX(c5, x, +r.open, +r.high, +r.low, +r.close);
    ensureVolAtX(c5, x, +(r.volume||0));
    upsertLinePoint(c5.series[1], x, r.sma_5   != null ? +r.sma_5   : null);
    upsertLinePoint(c5.series[2], x, r.sma_20  != null ? +r.sma_20  : null);
    upsertLinePoint(c5.series[3], x, r.sma_120 != null ? +r.sma_120 : null);
  });

  // 15m 완성봉 upsert
  (res.finalized15 || []).forEach(r => {
    const x = floorBucket(ts(r.datetime), 15);
    ensureCandleAtX(c15, x, +r.open, +r.high, +r.low, +r.close);
    ensureVolAtX(c15, x, +(r.volume||0));
    upsertLinePoint(c15.series[1], x, r.sma_5   != null ? +r.sma_5   : null);
    upsertLinePoint(c15.series[2], x, r.sma_20  != null ? +r.sma_20  : null);
    upsertLinePoint(c15.series[3], x, r.sma_120 != null ? +r.sma_120 : null);
  });

  // partial 표시
  partial5 = res.partial5 || null;
  if (partial5 && partial5.bucketTs != null) {
    const x = +partial5.bucketTs;
    ensureCandleAtX(c5, x, +partial5.o, +partial5.h, +partial5.l, +partial5.c);
    ensureVolAtX(c5, x, +(partial5.v||0));
  }

  partial15 = res.partial15 || null;
  if (partial15 && partial15.bucketTs != null) {
    const x = +partial15.bucketTs;
    ensureCandleAtX(c15, x, +partial15.o, +partial15.h, +partial15.l, +partial15.c);
    ensureVolAtX(c15, x, +(partial15.v||0));
  }

  // 첫 N개 라인 갱신
  if (res.lines){
    firstN = res.lines.n || LINE_N;
    open_1st = (res.lines.open_1st != null) ? +res.lines.open_1st : open_1st;
    hi_1stN  = (res.lines.hi_1stN  != null) ? +res.lines.hi_1stN  : hi_1stN;
    lo_1stN  = (res.lines.lo_1stN  != null) ? +res.lines.lo_1stN  : lo_1stN;
    locked_1stN = !!res.lines.locked;
    if (locked_1stN) applyHiLoLinesAll();
  }

  // 트림
  if (c1.series[0].points.length  > FIX_MAX_1  + 2) trimChartByCandles(c1,  FIX_MAX_1);
  if (c5.series[0].points.length  > FIX_MAX_5  + 2) trimChartByCandles(c5,  FIX_MAX_5);
  if (c15.series[0].points.length > FIX_MAX_15 + 2) trimChartByCandles(c15, FIX_MAX_15);

  // idx 갱신
  if (res.next_idx != null) idx = +res.next_idx;

  const t = (res.last_ts != null) ? new Date(+res.last_ts) : null;
  const hhmm = t ? `${pad2(t.getHours())}:${pad2(t.getMinutes())}` : '--:--';
  setStat(`진행 idx=${idx}/${todayTotal1||'?'} (${hhmm})`);

  scheduleRedraw();

  if (todayTotal1 && idx >= todayTotal1) {
    pause();
    setStat('끝(당일 범위 완료)');
  }
}

/* =========================
   play / pause
========================= */
function play(){
  if (timer) return;
  isPlaying = true;

  const sp = () => parseInt(document.getElementById('speed').value, 10) || 1000;
  timer = setInterval(() => {
    if (!isPlaying) return;
    if (isBusy) return;
    advanceBy(1);
  }, sp());

  setStat(`재생 시작 (idx=${idx})`);
}
function pause(){
  isPlaying = false;
  if (timer) { clearInterval(timer); timer = null; }
  setControlsDisabled(false);
  setStat(`정지 (idx=${idx})`);
}

/* =========================
   load
========================= */
function loadData(){
  setStat('로딩 중...');
  pause();               // 로드시 재생 끊기
  setControlsDisabled(true);
  resetCharts();

  const url = `./replay_api_multi.php?date=${encodeURIComponent(DATE)}&start=08:45:00&end=15:00:00`
    + `&max_prev_1m=${FIX_MAX_1}&max_prev_5m=${FIX_MAX_5}&max_prev_15m=${FIX_MAX_15}`
    + `&init_1m=${INIT_1M}`;

  $.getJSON(url, function(res){
    if (!res || !res.ok) { setStat(res?.msg || '로드 실패'); return; }

    prev1  = res.m1.prev  || [];
    today1 = res.m1.today || [];

    prev5  = res.m5.prev  || [];
    today5 = res.m5.today || [];

    prev15 = res.m15.prev || [];
    today15 = res.m15.today || [];

    todayTotal1 = today1.length || 0;

    // 전일 tail 먼저
    resetToStart();

    // 프리필 적용
    if (res.prefill) applyPrefill(res.prefill);

    if (!todayTotal1) setStat('당일 1분 데이터 없음');
  }).fail(function(){
    setStat('로드 실패: replay_api_multi.php 확인');
  }).always(function(){
    setControlsDisabled(false);
    isBusy = false;
  });
}

/* =========================
   bind
========================= */
$(function(){
  $('#btnPlay').on('click', (e)=>{ e.preventDefault(); play(); });
  $('#btnPause').on('click', (e)=>{ e.preventDefault(); pause(); });
  $('#btnStep1').on('click', (e)=>{ e.preventDefault(); advanceBy(1); });
  $('#btnStep5').on('click', (e)=>{ e.preventDefault(); advanceBy(5); });
  $('#btnStep10').on('click', (e)=>{ e.preventDefault(); advanceBy(10); });
  $('#btnReset').on('click', (e)=>{ e.preventDefault(); resetToStart(); });

  loadData();
});
</script>
</body>
</html>
