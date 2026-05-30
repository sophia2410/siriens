<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$date = $_GET['date'] ?? date('Y-m-d');

// 이전/다음 거래일(calendar 테이블)
$prev_date = '';
$next_date = '';

$stmt = $mysqli->prepare("SELECT MAX(date) FROM calendar WHERE date < ?");
$stmt->bind_param('s', $date);
$stmt->execute();
$stmt->bind_result($prev_date);
$stmt->fetch();
$stmt->close();

$stmt = $mysqli->prepare("SELECT MIN(date) FROM calendar WHERE date > ?");
$stmt->bind_param('s', $date);
$stmt->execute();
$stmt->bind_result($next_date);
$stmt->fetch();
$stmt->close();

// 같은 폴더 기준 상대경로(필요시 수정)
$chart_api = dirname($_SERVER['PHP_SELF']) . '/replay_api_multi.php';
$trade_api = dirname($_SERVER['PHP_SELF']) . '/futures_replay_trade_api.php';
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>B/S 데이뷰</title>
  <?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/highcharts.php"; ?>
  <style>
    body{margin:0;padding:10px;font-family:sans-serif;background:#f6f7fb;color:#111;}
    .panel{background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:10px;}
    .topbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;}
    input, select,button{padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;}
    button{cursor:pointer;background:#fff;}
    button:hover{background:#f8fafc;}
    .stat{margin-left:auto;color:#475569;font-size:12px;}

    .gridTop{
      display:grid;
      grid-template-columns: 0.4fr 1fr 2.5fr 1.5fr;
      gap:10px;
      align-items:stretch;
      margin-bottom:10px;
    }
    .boxTitle{font-weight:900;margin:0 0 8px;color:#111;}
    #chart60,#chart15,#chart5{height:400px;min-width:0;}
    .logWrap{display:flex;flex-direction:column;min-width:0;}
    .logBox{height:300px;overflow:auto;}
    table{width:100%;border-collapse:collapse;font-size:12px;}
    th,td{border-bottom:1px solid #eef2f7;padding:6px 4px;text-align:left;white-space:nowrap;}
    th{position:sticky;top:0;background:#fff;z-index:2;}

    .gridBottom{display:grid;grid-template-columns: 1fr;gap:10px;}
    #chart1{height:500px;min-width:0;}

    @media (max-width:1400px){
      .gridTop{grid-template-columns: 1fr 1fr; }
      #chart60,#chart15,#chart5,.logBox{height:340px;}
    }
    @media (max-width:900px){
      .gridTop{grid-template-columns: 1fr; }
      #chart60,#chart15,#chart5,.logBox{height:320px;}
      #chart1{height:520px;}
    }
  </style>
</head>
<body>

<div class="panel topbar">
  <form method="get" id="frm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <label><b>회차</b></label>
    <select id="simRunSelect" style="min-width:180px;">
      <option value="">(회차 선택)</option>
    </select>

    <b>일자</b>
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button type="submit">조회</button>
    <button type="button" id="btnPrev" <?= empty($prev_date)?'disabled':'' ?>>◀ 이전일</button>
    <button type="button" id="btnNext" <?= empty($next_date)?'disabled':'' ?>>다음일 ▶</button>

    <input type="hidden" id="prevDate" value="<?= htmlspecialchars($prev_date ?? '') ?>">
    <input type="hidden" id="nextDate" value="<?= htmlspecialchars($next_date ?? '') ?>">
    <div class="stat" id="stat">로딩 전</div>
  </form>
</div>

<div class="gridTop">
  <div class="panel">
    <div class="boxTitle">60분봉</div>
    <div id="chart60"></div>
  </div>

  <div class="panel">
    <div class="boxTitle">15분봉 (B/S 표시)</div>
    <div id="chart15"></div>
  </div>

  <div class="panel">
    <div class="boxTitle">5분봉 (VWAP)</div>
    <div id="chart5"></div>
  </div>

  <div class="panel logWrap">
    <div class="boxTitle">체결 로그</div>
    <div class="logBox">
      <table>
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th style="width:92px;">action</th>
            <th style="width:110px;">bar_dt</th>
            <th style="width:70px;">price</th>
            <th style="width:50px;">qty</th>
            <th style="width:70px;">fee</th>
          </tr>
        </thead>
        <tbody id="tradeTbody">
          <tr><td colspan="6" style="color:#94a3b8;">(체결 없음)</td></tr>
        </tbody>
      </table>
    </div>

    <div class="simTitle">일자 코멘트</div>
    <textarea id="dayComment" rows="6" spellcheck="false" autocorrect="off" autocapitalize="off" autocomplete="off"
      style="width:98%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; resize:vertical;"
      placeholder="오늘 매매 코멘트(복기/실수/규칙 위반/잘한 점 등)"></textarea>

  </div>
</div>

<div class="gridBottom">
  <div class="panel">
    <div class="boxTitle">1분봉 (전체폭) + B/S 표시 (VWAP)</div>
    <div id="chart1"></div>
  </div>
</div>

<script>
/** ================== 설정 ================== */
const DATE = <?= json_encode($date) ?>;
const CHART_API = <?= json_encode($chart_api) ?>;
const TRADE_API = <?= json_encode($trade_api) ?>;

// ✅ 이평선 색상
const SMA5_COLOR   = '#d32f2f';
const SMA20_COLOR  = '#f9a825';
const SMA120_COLOR = '#757575';

// ✅ VWAP 색상
const VWAP_COLOR = 'hsla(281, 97%, 34%, 0.65)';

// ✅ 30분 고저/시가 색상
const HI_COLOR   = 'rgba(255,79,179,0.95)';
const LO_COLOR   = 'rgba(79,195,255,0.95)';
const OPEN_COLOR = 'rgba(102,102,102,0.95)';

// ✅ 회차(run) 선택 상태
let simRunId = 0; // 0이면 저장값/첫 회차 자동 선택
const LS_RUN_ID_KEY = 'futures_bs_view_run_id';

// 장 시간
const START = '08:45:00';
const END   = '15:45:00';

/** ================== Trade API ================== */
async function apiPost(mode, payload){
  const fd = new FormData();
  fd.append('mode', mode);
  Object.entries(payload || {}).forEach(([k,v])=>fd.append(k, v));

  const r = await fetch(TRADE_API, { method:'POST', body:fd });
  const j = await r.json();
  if (!j || !j.ok) throw new Error(j?.msg || 'TRADE API error');
  return j;
}

function renderRuns(runs){
  const sel = document.getElementById('simRunSelect');
  if (!sel) return;

  const cur = String(simRunId || '');
  sel.innerHTML = `<option value="">(회차 선택)</option>`;

  (runs || []).forEach(r=>{
    const opt = document.createElement('option');
    opt.value = r.run_id;
    opt.textContent = `${r.run_id}. ${r.run_name}`;
    if (String(r.run_id) === cur) opt.selected = true;
    sel.appendChild(opt);
  });
}

async function loadRunsAndRestore(){
  const j = await apiPost('run_list', {});
  const saved = localStorage.getItem(LS_RUN_ID_KEY);
  if (saved) simRunId = Number(saved) || 0;

  renderRuns(j.runs || []);

  // 저장된 회차가 없으면 → 첫번째 회차 자동 선택
  if (!simRunId && j.runs && j.runs.length){
    simRunId = Number(j.runs[0].run_id) || 0;
    localStorage.setItem(LS_RUN_ID_KEY, String(simRunId));
    const sel = document.getElementById('simRunSelect');
    if (sel) sel.value = String(simRunId);
  }
}

// 회차 변경 시: 체결만 다시 로드 + B/S 다시 적용
document.getElementById('simRunSelect')?.addEventListener('change', async (e)=>{
  const v = e.target.value;
  simRunId = v ? Number(v) : 0;

  if (simRunId) localStorage.setItem(LS_RUN_ID_KEY, String(simRunId));
  else localStorage.removeItem(LS_RUN_ID_KEY);

  try{
    await loadTrades();
  }catch(err){
    console.error(err);
    setStat('오류: ' + (err.message || err));
    alert(err.message || err);
  }
});

// Highcharts
Highcharts.setOptions({ time:{ useUTC:false }, credits:{ enabled:false } });

/** ================== 유틸 ================== */
function setStat(t){ document.getElementById('stat').textContent = t; }
function toMs(dtStr){
  if (!dtStr || dtStr.length < 19) return NaN;
  const y=+dtStr.slice(0,4), m=+dtStr.slice(5,7)-1, d=+dtStr.slice(8,10);
  const H=+dtStr.slice(11,13), I=+dtStr.slice(14,16), S=+dtStr.slice(17,19);
  return new Date(y,m,d,H,I,S).getTime();
}
function rowsToCandles(rows){ return (rows||[]).map(r=>[toMs(r.datetime), +r.open, +r.high, +r.low, +r.close]); }
function rowsToVol(rows){ return (rows||[]).map(r=>[toMs(r.datetime), +(r.volume||0)]); }
function rowsToLine(rows, field){
  const out=[];
  (rows||[]).forEach(r=>{ if (r[field]==null) return; out.push([toMs(r.datetime), +r[field]]); });
  return out;
}

// 버킷(08:45 앵커)
function toTSLocal(ymd, hhmmss){ return toMs(`${ymd} ${hhmmss}`); }
const ANCHOR_MS = toTSLocal(DATE, '08:45:00');
function bucketStart(ms, minutes){
  const unit = minutes * 60 * 1000;
  const delta = ms - ANCHOR_MS;
  if (delta < 0) return ANCHOR_MS;
  return ANCHOR_MS + Math.floor(delta / unit) * unit;
}

// 가까운 캔들 x로 스냅
function snapToCandleX(chart, x){
  const xd = chart?.series?.[0]?.xData;
  if (!xd || !xd.length) return x;
  let lo=0, hi=xd.length-1;
  while(lo<=hi){
    const mid=(lo+hi)>>1;
    const v=xd[mid];
    if (v===x) return x;
    if (v<x) lo=mid+1;
    else hi=mid-1;
  }
  if (hi<0) return xd[0];
  if (lo>=xd.length) return xd[xd.length-1];
  const L=xd[hi], R=xd[lo];
  return (Math.abs(x-L) <= Math.abs(R-x)) ? L : R;
}

/** ================== 30분 고저/시가 라인 ================== */
let lvl30 = null; // {open, high, low, mid, start_hhmm, end_hhmm}

function apply30mLines(chart){
  if (!chart || !lvl30) return;
  const y = chart.yAxis?.[0];
  if (!y) return;

  // 기존 라인 제거
  ['lvl30_hi','lvl30_lo','lvl30_op'].forEach(id=>{ try{ y.removePlotLine(id); }catch(e){} });

  const tag = '30m';

  if (lvl30.open != null){
    y.addPlotLine({
      id:'lvl30_op',
      value:+lvl30.open,
      color:OPEN_COLOR,
      width:1,
      dashStyle:'Dash',
      zIndex:6,
      label:{ text:`시가 ${(+lvl30.open).toFixed(2)}`, align:'left', x:5, style:{ fontSize:'10px', color:'#444' } }
    });
  }
  if (lvl30.high != null){
    y.addPlotLine({
      id:'lvl30_hi',
      value:+lvl30.high,
      color:HI_COLOR,
      width:2,
      zIndex:7,
      label:{ text:`${tag} 고 ${(+lvl30.high).toFixed(2)}`, align:'left', x:5, style:{ fontSize:'10px', color:HI_COLOR, fontWeight:'bold' } }
    });
  }
  if (lvl30.low != null){
    y.addPlotLine({
      id:'lvl30_lo',
      value:+lvl30.low,
      color:LO_COLOR,
      width:2,
      zIndex:7,
      label:{ text:`${tag} 저 ${(+lvl30.low).toFixed(2)}`, align:'left', x:5, style:{ fontSize:'10px', color:LO_COLOR, fontWeight:'bold' } }
    });
  }
}
function apply30mLinesAll(){
  apply30mLines(c60);
  apply30mLines(c15);
  apply30mLines(c5);
  apply30mLines(c1);
}

/** ================== 차트 생성 ================== */
function makeChart(el, opts = {}){
  const showVwap = !!opts.showVwap;

  const ch = Highcharts.stockChart(el, {
    chart:{ animation:false, zooming:{ mouseWheel:{ enabled:false }, type:'x' } },
    navigator:{ enabled:false },
    scrollbar:{ enabled:false },
    rangeSelector:{ enabled:false },
    exporting:{enabled: false},
    title:{ text:'' },
    xAxis:{ type:'datetime', labels:{ format:'{value:%H:%M}', style:{ fontSize:'10px' } } },
    yAxis:[
      { height:'85%', lineWidth:1, startOnTick:false, endOnTick:false, minPadding:0.01, maxPadding:0.01,
        labels:{ align:'right', x:4, style:{ fontSize:'10px' } } 
      },
      { top:'85%', height:'15%', offset:0, lineWidth:1, min:0 }
    ],
    series:[
      // 0) 캔들
      { type:'candlestick', id:`cndl_${el}`, name:el, data:[], zIndex:3, dataGrouping:{enabled:false} },

      // 1~3) SMA
      { type:'line', name:'SMA 5',   data:[], lineWidth:2, color:SMA5_COLOR,   zIndex:2, dataGrouping:{enabled:false} },
      { type:'line', name:'SMA 20',  data:[], lineWidth:2, color:SMA20_COLOR,  zIndex:2, dataGrouping:{enabled:false} },
      { type:'line', name:'SMA 120', data:[], lineWidth:2, color:SMA120_COLOR, zIndex:2, dataGrouping:{enabled:false} },

      // 4) VWAP (차트별 on/off)
      { type:'line', name:'VWAP', data:[], lineWidth:2, color:VWAP_COLOR, zIndex:10, dataGrouping:{enabled:false},
        visible: showVwap, showInLegend: showVwap },

      // 5) 거래량
      { type:'column', name:'Vol', data:[], yAxis:1, dataGrouping:{enabled:false} },
    ],
    plotOptions:{
      series:{ animation:false },
      candlestick:{ color:'#2f7ed8', upColor:'#f45b5b', lineColor:'#2f7ed8', upLineColor:'#f45b5b' }
    }
  });

  ch._showVwap = showVwap;
  return ch;
}

let c60, c15, c5, c1;

/** ================== B/S flags ================== */
const BS_FLAG_W = 14;
const BS_FONT   = '10px';
const BS_Y_BUY  = 6;
const BS_Y_SELL = -28;

function ensureBS(chart){
  if (!chart) return null;
  if (chart._bs) return chart._bs;

  const candle = chart.series?.[0];
  if (!candle) return null;

  let candleId = candle.options?.id;
  if (!candleId){
    candleId = `candle_${chart.renderTo?.id || Math.random().toString(16).slice(2)}`;
    try{ candle.update({ id:candleId }, false); }catch(e){}
  }

  const buy = chart.addSeries({
    type:'flags', name:'B',
    onSeries:candleId, onKey:'low',
    shape:'circlepin', y:BS_Y_BUY, stackDistance:14,
    fillColor:'rgba(244,67,54,0.90)',
    width:BS_FLAG_W, style:{ color:'#fff', fontWeight:'900', fontSize:BS_FONT },
    lineWidth:0, data:[], enableMouseTracking:false
  }, false);

  const sell = chart.addSeries({
    type:'flags', name:'S',
    onSeries:candleId, onKey:'high',
    shape:'circlepin', y:BS_Y_SELL, stackDistance:14,
    fillColor:'rgba(33,150,243,0.90)',
    width:BS_FLAG_W, style:{ color:'#fff', fontWeight:'900', fontSize:BS_FONT },
    lineWidth:0, data:[], enableMouseTracking:false
  }, false);

  chart._bs = { buy, sell };
  return chart._bs;
}

function clearBS(chart){
  if (!chart?._bs) return;
  try{ chart._bs.buy.setData([], false); }catch(e){}
  try{ chart._bs.sell.setData([], false); }catch(e){}
}

// trades -> side(B/S) (포지션 추적)
function normalizeTrades(trades){
  const arr = Array.isArray(trades) ? trades.slice() : [];
  arr.sort((a,b)=> (Number(a.seq||0) - Number(b.seq||0)));

  let pos = 0; // +long, -short
  const out = [];

  for (const t of arr){
    const action = String(t.action||'').toUpperCase();
    const qty = Math.max(1, parseInt(t.qty||'1',10) || 1);

    let side = null;

    if (action === 'OPEN_LONG'){ side='B'; pos += qty; }
    else if (action === 'OPEN_SHORT'){ side='S'; pos -= qty; }
    else if (action === 'CLOSE_PART'){
      if (pos>0){ side='S'; pos = Math.max(0, pos-qty); }
      else if (pos<0){ side='B'; pos = Math.min(0, pos+qty); }
    }
    else if (action === 'CLOSE_ALL'){
      if (pos>0) side='S';
      else if (pos<0) side='B';
      pos = 0;
    }

    if (!side) continue;
    out.push({ ...t, _side: side });
  }
  return out;
}

function applyBS(trades){
  const norm = normalizeTrades(trades);

  const targets = [
    { chart:c1,  bucketMin:1 },
    { chart:c15, bucketMin:15 },
    { chart:c5,  bucketMin:5 },
  ];

  for (const tg of targets){
    const chart = tg.chart;
    if (!chart?.series?.[0]) continue;

    const bs = ensureBS(chart);
    if (!bs) continue;

    clearBS(chart);

    const buyMap = new Map();
    const sellMap = new Map();

    for (const t of norm){
      const dt = t.bar_dt || t.datetime || '';
      let x = toMs(dt);
      if (!Number.isFinite(x)) continue;

      if (tg.bucketMin > 1) x = bucketStart(x, tg.bucketMin);
      x = snapToCandleX(chart, x);

      if (t._side === 'B') buyMap.set(x, (buyMap.get(x)||0)+1);
      else sellMap.set(x, (sellMap.get(x)||0)+1);
    }

    const buyPts = [...buyMap.entries()].sort((a,b)=>a[0]-b[0]).map(([x,c])=>({ x, title:(c>1?`B${c}`:'B'), text:'' }));
    const sellPts= [...sellMap.entries()].sort((a,b)=>a[0]-b[0]).map(([x,c])=>({ x, title:(c>1?`S${c}`:'S'), text:'' }));

    bs.buy.setData(buyPts, false);
    bs.sell.setData(sellPts,false);

    chart.redraw(false);
  }
}

/** ================== 체결로그 렌더 ================== */
function fmtInt(n){ const v=Number(n); if(!Number.isFinite(v)) return '-'; return Math.round(v).toLocaleString('ko-KR'); }
function renderTrades(trades){
  const tb = document.getElementById('tradeTbody');
  if (!tb) return;

  if (!trades || !trades.length){
    tb.innerHTML = `<tr><td colspan="6" style="color:#94a3b8;">(체결 없음)</td></tr>`;
    return;
  }

  tb.innerHTML = trades.map(t=>{
    const seq = t.seq ?? '';
    const action = t.action ?? '';
    const bar_dt = (t.bar_dt ?? '').slice(5,16);
    const price = (t.price!=null) ? Number(t.price).toFixed(2) : '';
    const qty = t.qty ?? '';
    const fee = (t.fee_amount!=null) ? fmtInt(t.fee_amount) : '-';
    return `<tr>
      <td>${seq}</td><td><b>${action}</b></td><td>${bar_dt}</td><td>${price}</td><td>${qty}</td><td>${fee}</td>
    </tr>`;
  }).join('');
}
function renderDay(day){
  if (!day) return;

  const ta = document.getElementById('dayComment');
  if (ta) ta.value = day.day_comment || '';
}

/** ================== 데이터 로드 ================== */
async function loadCharts(){
  setStat('차트 로딩...');

  // ✅ 전체 하루를 init에 담기 위해 init_time=end로 설정 + prevTail=0
  // ✅ 핵심: 30분 고저 계산을 위해 hi_lo_n=30
  const url = `${CHART_API}?date=${encodeURIComponent(DATE)}`
    + `&start=${encodeURIComponent(START)}&end=${encodeURIComponent(END)}`
    + `&init_time=${encodeURIComponent(END)}`
    + `&max_prev_1m=0&max_prev_5m=0&max_prev_15m=0&max_prev_60m=0`
    + `&init_max_1m=2000&init_max_5m=500&init_max_15m=200&init_max_60m=120`
    + `&hi_lo_n=30&hi_lo_n2=60`;

  const r = await fetch(url, { cache:'no-store' });
  const text = await r.text();
  if (!r.ok) throw new Error(`CHART API HTTP ${r.status}`);

  const res = JSON.parse(text);
  if (!res?.ok) throw new Error(res?.msg || 'CHART API ok=false');

  const m1 = [...(res.m1?.init||[]), ...(res.m1?.future||[])];
  const m5 = res.m5?.today || [];
  const m15= res.m15?.today || [];
  const m60= res.m60?.today || [];

  // ✅ 30분 고저(시가 포함) - API의 hilo_main 사용
  if (res.hilo_main){
    const op = (res.hilo_main.open != null) ? +res.hilo_main.open : null;
    const hi = (res.hilo_main.high != null) ? +res.hilo_main.high : null;
    const lo = (res.hilo_main.low  != null) ? +res.hilo_main.low  : null;

    lvl30 = {
      open: op,
      high: hi,
      low:  lo,
      mid: (hi!=null && lo!=null) ? (hi+lo)/2 : null,
      start_hhmm: res.hilo_main.start_hhmm || '08:45',
      end_hhmm:   res.hilo_main.end_hhmm   || '09:14'
    };
  } else {
    lvl30 = null;
  }

  // ===== setData (1m/5m = SMA + VWAP, 15/60 = SMA only) =====

  // 1분
  c1.series[0].setData(rowsToCandles(m1), false);
  c1.series[1].setData(rowsToLine(m1, 'sma_5'), false);
  c1.series[2].setData(rowsToLine(m1, 'sma_20'), false);
  c1.series[3].setData(rowsToLine(m1, 'sma_120'), false);
  c1.series[4].setData(rowsToLine(m1, 'vwap_session'), false); // ✅ VWAP
  c1.series[5].setData(rowsToVol(m1), false);

  // 5분
  c5.series[0].setData(rowsToCandles(m5), false);
  c5.series[1].setData(rowsToLine(m5, 'sma_5'), false);
  c5.series[2].setData(rowsToLine(m5, 'sma_20'), false);
  c5.series[3].setData(rowsToLine(m5, 'sma_120'), false);
  c5.series[4].setData(rowsToLine(m5, 'vwap_session'), false); // ✅ VWAP
  c5.series[5].setData(rowsToVol(m5), false);

  // 15분(SMA만, VWAP는 비움)
  c15.series[0].setData(rowsToCandles(m15), false);
  c15.series[1].setData(rowsToLine(m15, 'sma_5'), false);
  c15.series[2].setData(rowsToLine(m15, 'sma_20'), false);
  c15.series[3].setData(rowsToLine(m15, 'sma_120'), false);
  c15.series[4].setData([], false);              // ✅ VWAP 없음
  c15.series[5].setData(rowsToVol(m15), false);

  // 60분(SMA만, VWAP는 비움)
  c60.series[0].setData(rowsToCandles(m60), false);
  c60.series[1].setData(rowsToLine(m60, 'sma_5'), false);
  c60.series[2].setData(rowsToLine(m60, 'sma_20'), false);
  c60.series[3].setData(rowsToLine(m60, 'sma_120'), false);
  c60.series[4].setData([], false);              // ✅ VWAP 없음
  c60.series[5].setData(rowsToVol(m60), false);

  // ✅ 30분 고저/시가 라인 전체 적용
  apply30mLinesAll();

  // redraw
  c60.redraw(false); c15.redraw(false); c5.redraw(false); c1.redraw(false);

  setStat('차트 OK');
}

async function loadTrades(){
  if (!simRunId){
    renderTrades([]);
    renderDay([]);
    clearBS(c1); clearBS(c15); clearBS(c5);
    c1.redraw(false); c15.redraw(false); c5.redraw(false);
    setStat('회차 선택 필요');
    return;
  }

  setStat('체결 로딩...');

  const fd = new FormData();
  fd.append('mode', 'day_get');
  fd.append('run_id', String(simRunId));
  fd.append('trade_date', DATE);

  const r = await fetch(TRADE_API, { method:'POST', body:fd });
  const j = await r.json();
  if (!j?.ok) throw new Error(j?.msg || 'TRADE API ok=false');

  const trades = j.trades || [];
  renderTrades(trades);
  
  const day = j.day || [];
  renderDay(day);

  // ✅ B/S 마커(1분봉 + 15분봉 + 5분봉)
  applyBS(trades);

  setStat(`완료 (${DATE}) / 회차 ${simRunId}`);
}

/** ================== 네비게이션 ================== */
document.getElementById('btnPrev')?.addEventListener('click', ()=>{
  const d = document.getElementById('prevDate')?.value;
  if (!d) return;
  const inp = document.querySelector('input[name="date"]');
  inp.value = d;
  document.getElementById('frm').submit();
});
document.getElementById('btnNext')?.addEventListener('click', ()=>{
  const d = document.getElementById('nextDate')?.value;
  if (!d) return;
  const inp = document.querySelector('input[name="date"]');
  inp.value = d;
  document.getElementById('frm').submit();
});

/** ================== 시작 ================== */
(function init(){
  c60 = makeChart('chart60', { showVwap:false });
  c15 = makeChart('chart15', { showVwap:false });
  c5  = makeChart('chart5',  { showVwap:true  }); // ✅ 5분: SMA+VWAP
  c1  = makeChart('chart1',  { showVwap:true  }); // ✅ 1분: SMA+VWAP

  (async ()=>{
    try{
      await loadCharts();
      await loadRunsAndRestore();
      await loadTrades();
    }catch(e){
      console.error(e);
      setStat('오류: ' + (e.message || e));
      alert(e.message || e);
    }
  })();
})();
</script>

</body>
</html>
