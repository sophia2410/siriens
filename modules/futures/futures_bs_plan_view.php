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
$chart_api = dirname($_SERVER['PHP_SELF']) . '/replay_api_plan_multi.php';
$trade_api = dirname($_SERVER['PHP_SELF']) . '/futures_replay_trade_api.php';
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>B/S 계획매매 기준 데이뷰</title>
  <?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/highcharts.php"; ?>
  <style>
    body{margin:0;padding:10px;font-family:sans-serif;background:#f6f7fb;color:#111;}
    .panel{background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.04);padding:6px 8px;}
    .topbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;}
    input, select,button{padding:6px 10px;border:1px solid #e5e7eb;border-radius:8px;}
    button{cursor:pointer;background:#fff;}
    button:hover{background:#f8fafc;}
    .stat{margin-left:auto;color:#475569;font-size:12px;}

    .gridTop{
      display:grid;
      grid-template-columns: minmax(0,1fr) minmax(0,2fr) minmax(430px,2fr);
      gap:10px;
      align-items:stretch;
      margin-bottom:10px;
    }
    .boxTitle{font-weight:900;margin:0 0 6px;color:#111;}
    #chart15,#chart5{height:400px;min-width:0;}
    .logWrap{display:flex;flex-direction:column;min-width:0;}
    .logContent{display:grid;grid-template-columns:minmax(220px,.55fr) minmax(300px,1fr);gap:8px;flex:1;min-height:0;}
    .logPane,.commentPane{display:flex;flex-direction:column;min-width:0;min-height:0;}
    .logBox{flex:1;min-height:0;overflow:auto;}
    #dayComment{flex:1;min-height:0;width:auto;padding:8px;border:1px solid #e5e7eb;border-radius:8px;resize:none;line-height:1.45;}
    table{width:100%;border-collapse:collapse;font-size:12px;}
    th,td{border-bottom:1px solid #eef2f7;padding:6px 3px;text-align:left;white-space:nowrap;}
    th{position:sticky;top:0;background:#fff;z-index:2;}
    .actionCarry{display:inline-block;padding:2px 6px;border-radius:6px;background:#111827;color:#fff;font-weight:900;}
    .tradeTable{table-layout:fixed;}
    .tradeTable th:nth-child(1),.tradeTable td:nth-child(1){width:32px;color:#64748b;}
    .tradeTable th:nth-child(2),.tradeTable td:nth-child(2){width:66px;}
    .tradeTable th:nth-child(3),.tradeTable td:nth-child(3){width:46px;}
    .tradeTable th:nth-child(4),.tradeTable td:nth-child(4){width:62px;text-align:right;}
    .tradeTable th:nth-child(5),.tradeTable td:nth-child(5){width:34px;text-align:right;}

    .gridBottom{display:grid;grid-template-columns: 1fr;gap:10px;}
    #chart1{height:500px;min-width:0;}

    @media (max-width:1400px){
      .gridTop{grid-template-columns: minmax(0,1fr) minmax(0,2fr); }
      #chart15,#chart5{height:340px;}
      .logWrap{grid-column:1 / -1;min-height:340px;}
    }
    @media (max-width:900px){
      .gridTop{grid-template-columns: 1fr; }
      #chart15,#chart5{height:320px;}
      .logContent{grid-template-columns:1fr;}
      .logWrap{grid-column:auto;min-height:520px;}
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
    <div class="boxTitle">15분봉 (08:45 시가 + SMA 10 + B/S)</div>
    <div id="chart15"></div>
  </div>

  <div class="panel">
    <div class="boxTitle">5분봉 (VWAP + SMA 5/20 + 09:00~09:14 꼬리 포함 고저)</div>
    <div id="chart5"></div>
  </div>

  <div class="panel logWrap">
    <div class="boxTitle" style="display:flex;justify-content:space-between;align-items:center;">
      <span>체결 로그 / 일자 코멘트</span>
      <span id="dayPnlText" style="font-size:13px;font-weight:900;">0원</span>
    </div>
    <div class="logContent">
      <div class="logPane">
        <div class="logBox">
          <table class="tradeTable">
            <thead>
              <tr>
                <th>#</th>
                <th>action</th>
                <th>시간</th>
                <th>price</th>
                <th>qty</th>
              </tr>
            </thead>
            <tbody id="tradeTbody">
              <tr><td colspan="5" style="color:#94a3b8;">(체결 없음)</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="commentPane">
        <textarea id="dayComment" spellcheck="false" autocorrect="off" autocapitalize="off" autocomplete="off"
          placeholder="오늘 매매 코멘트(복기/실수/규칙 위반/잘한 점 등)"></textarea>
      </div>
    </div>

  </div>
</div>

<div class="gridBottom">
  <div class="panel">
    <div class="boxTitle">1분봉 (VWAP + 09:00~09:14 꼬리 포함 고저 + B/S)</div>
    <div id="chart1"></div>
  </div>
</div>

<script>
/** ================== 설정 ================== */
const DATE = <?= json_encode($date) ?>;
const CHART_API = <?= json_encode($chart_api) ?>;
const TRADE_API = <?= json_encode($trade_api) ?>;

// ✅ 이평선 색상
const SMA5_COLOR   = 'rgba(211,47,47,0.50)';
const SMA20_COLOR  = 'rgba(249,168,37,0.50)';
const SMA120_COLOR = 'rgba(117,117,117,0.50)';
const SMA10_COLOR  = 'rgba(37,99,235,0.50)';

// ✅ VWAP 색상
const VWAP_COLOR = 'rgba(126,34,206,0.80)';

// ✅ 30분 고저/시가 색상
const HI_COLOR   = 'rgba(255,79,179,0.95)';
const LO_COLOR   = 'rgba(79,195,255,0.95)';
const OPEN_COLOR = '#666666';

// ✅ 회차(run) 선택 상태
let simRunId = 0; // 0이면 저장값/첫 회차 자동 선택
const LS_RUN_ID_KEY = 'futures_bs_plan_view_run_id';

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

  if (!simRunId && j.runs && j.runs.length){
    simRunId = Number(j.runs[0].run_id) || 0;
    localStorage.setItem(LS_RUN_ID_KEY, String(simRunId));
    const sel = document.getElementById('simRunSelect');
    if (sel) sel.value = String(simRunId);
  }
}

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
  (rows||[]).forEach(r=>{
    if (r[field]==null) return;
    out.push([toMs(r.datetime), +r[field]]);
  });
  return out;
}
function rowsToSma(rows, period){
  const closes = [];
  return (rows || []).map(r => {
    closes.push(Number(r.close));
    if (closes.length > period) closes.shift();
    const value = closes.length === period
      ? closes.reduce((sum, v) => sum + v, 0) / period
      : null;
    return [toMs(r.datetime), value];
  });
}
function mergeRowsByDatetime(...groups){
  const byDatetime = new Map();
  groups.flat().forEach(r => {
    if (r?.datetime) byDatetime.set(r.datetime, r);
  });
  return [...byDatetime.values()].sort((a,b) => toMs(a.datetime) - toMs(b.datetime));
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

// ✅ 1분봉 캔들 기준 y축 강제 맞춤
function fitYAxisToCandles(chart, candleSeriesId, extraPaddingPct = 0.06) {
  if (!chart || !chart.yAxis?.[0]) return;

  const s = chart.get(candleSeriesId);
  if (!s) return;

  const xAxis = chart.xAxis?.[0];
  if (!xAxis) return;

  const minX = xAxis.min;
  const maxX = xAxis.max;

  const data = s.options?.data || [];
  if (!data.length) return;

  let dataMin = Infinity;
  let dataMax = -Infinity;

  for (const row of data) {
    const x = Array.isArray(row) ? row[0] : row.x;
    const high = Array.isArray(row) ? row[2] : row.high;
    const low  = Array.isArray(row) ? row[3] : row.low;

    if (!isFinite(x) || !isFinite(high) || !isFinite(low)) continue;
    if (isFinite(minX) && x < minX) continue;
    if (isFinite(maxX) && x > maxX) continue;

    if (low < dataMin) dataMin = low;
    if (high > dataMax) dataMax = high;
  }

  if (!isFinite(dataMin) || !isFinite(dataMax)) return;

  const range = Math.max(0.01, dataMax - dataMin);
  const pad = range * extraPaddingPct;

  chart.yAxis[0].setExtremes(dataMin - pad, dataMax + pad, false, false);
}

/** ================== 계획매매 기준선 ================== */
let sessionOpen = null;

function clearReferenceLines(chart){
  if (!chart?.yAxis?.[0]) return;
  ['session_open','body0900_hi','body0900_lo'].forEach(id => {
    try{ chart.yAxis[0].removePlotLine(id); }catch(e){}
  });
}

function addSessionOpenLine(chart){
  if (!chart?.yAxis?.[0] || sessionOpen == null) return;
  chart.yAxis[0].addPlotLine({
    id:'session_open', value:+sessionOpen, color:OPEN_COLOR, width:3,
    dashStyle:'Dash', zIndex:6,
    label:{ text:'시가 ' + (+sessionOpen).toFixed(2), align:'left', x:5,
      style:{ fontSize:'9px', color:'#111' } }
  });
}

function getOpeningBodyRange(rows5){
  const startTs = toTSLocal(DATE, '09:00:00');
  const endTs = toTSLocal(DATE, '09:15:00');
  const rows = mergeRowsByDatetime(rows5)
    .filter(r => {
      const x = toMs(r.datetime);
      return x >= startTs && x < endTs;
    });
  if (rows.length < 3) return null;
  return {
    // 꼬리 포함: 각 캔들의 전체 High/Low 반영
    high: Math.max(...rows.map(r => Number(r.high))),
    low: Math.min(...rows.map(r => Number(r.low)))
  };
}

function addOpeningBodyLines(chart, bodyRange){
  if (!chart?.yAxis?.[0] || !bodyRange) return;
  chart.yAxis[0].addPlotLine({
    id:'body0900_hi', value:bodyRange.high, color:HI_COLOR, width:2, zIndex:7,
    label:{ text:'꼬리 포함 고 ' + bodyRange.high.toFixed(2), align:'left', x:5,
      style:{fontSize:'10px', color:HI_COLOR, fontWeight:'bold'} }
  });
  chart.yAxis[0].addPlotLine({
    id:'body0900_lo', value:bodyRange.low, color:LO_COLOR, width:2, zIndex:7,
    label:{ text:'꼬리 포함 저 ' + bodyRange.low.toFixed(2), align:'left', x:5,
      style:{fontSize:'10px', color:LO_COLOR, fontWeight:'bold'} }
  });
}

function applyReferenceLines(rows5){
  [c15,c5,c1].forEach(chart => {
    clearReferenceLines(chart);
    addSessionOpenLine(chart);
  });
  const bodyRange = getOpeningBodyRange(rows5);
  addOpeningBodyLines(c5, bodyRange);
  addOpeningBodyLines(c1, bodyRange);
}

/** ================== 차트 생성 ================== */
function makeChart(el, opts = {}){
  const mode = opts.mode || 'oneMinute';
  const is15m = mode === 'fifteenMinute';
  const is5m = mode === 'fiveMinute';
  const showVwap = !is15m;
  const fitCandleYAxis = !!opts.fitCandleYAxis;

  const ch = Highcharts.stockChart(el, {
    chart:{
      animation:false,
      zooming:{ mouseWheel:{ enabled:false }, type:'x' },
    },
    navigator:{ enabled:false },
    scrollbar:{ enabled:false },
    rangeSelector:{ enabled:false },
    exporting:{ enabled:false },
    title:{ text:'' },
    xAxis:{
      type:'datetime',
      labels:{ format:'{value:%H:%M}', style:{ fontSize:'10px' } },
      events:{
        afterSetExtremes:function(){
          if (fitCandleYAxis && this.chart?.renderTo?.id === 'chart1') {
            fitYAxisToCandles(this.chart, 'cndl_chart1', 0.05);
            this.chart.redraw(false);
          }
        }
      }
    },
    yAxis:[
      {
        height:'90%',
        lineWidth:1,
        startOnTick:false,
        endOnTick:false,
        minPadding:0,
        maxPadding:0,
        labels:{ align:'right', x:4, style:{ fontSize:'10px' } }
      },
      {
        top:'90%',
        height:'10%',
        offset:0,
        lineWidth:1,
        min:0
      }
    ],
    series:[
      { type:'candlestick', id:`cndl_${el}`, name:el, data:[], zIndex:3, dataGrouping:{enabled:false} },

      { type:'line', name:is15m ? 'SMA 10' : 'SMA 5', data:[], lineWidth:1,
        dashStyle:is15m ? 'ShortDot' : 'Solid', color:is15m ? SMA10_COLOR : SMA5_COLOR,
        zIndex:2, dataGrouping:{enabled:false} },
      { type:'line', name:'SMA 20', data:[], lineWidth:1, color:SMA20_COLOR, zIndex:2,
        visible:!is15m, showInLegend:!is15m, dataGrouping:{enabled:false} },
      { type:'line', name:'SMA 120', data:[], lineWidth:1, color:SMA120_COLOR, zIndex:2,
        visible:!is15m && !is5m, showInLegend:!is15m && !is5m, dataGrouping:{enabled:false} },

      { type:'line', name:'VWAP', data:[], lineWidth:3, dashStyle:'ShortDash',
        color:VWAP_COLOR, zIndex:10, dataGrouping:{enabled:false},
        visible: showVwap, showInLegend: showVwap },

      { type:'column', name:'Vol', data:[], yAxis:1, dataGrouping:{enabled:false} },
    ],
    plotOptions:{
      series:{ animation:false },
      candlestick:{ color:'#2f7ed8', upColor:'#f45b5b', lineColor:'#2f7ed8', upLineColor:'#f45b5b' }
    }
  });

  ch._showVwap = showVwap;
  ch._fitCandleYAxis = fitCandleYAxis;
  return ch;
}

let c15, c5, c1;

/** ================== B/S flags ================== */
const BS_FLAG_W = 14;
const BS_FONT   = '10px';
const BS_Y_BUY  = 6;
const BS_Y_SELL = -28;
const BS_CARRY_COLOR = '#111827';
const BS_BUY_LINE_COLOR  = '#f59e0b';
const BS_SELL_LINE_COLOR = '#00acc1';
const BS_BOTH_LINE_COLOR = '#8e24aa';
const BS_TRADE_LINE_WIDTH = 2;

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

  const carryBuy = chart.addSeries({
    type:'flags', name:'Carry B',
    onSeries:candleId, onKey:'low',
    shape:'circlepin', y:BS_Y_BUY, stackDistance:14,
    fillColor:BS_CARRY_COLOR,
    width:BS_FLAG_W, style:{ color:'#fff', fontWeight:'900', fontSize:BS_FONT },
    lineWidth:0, data:[], enableMouseTracking:false
  }, false);

  const carrySell = chart.addSeries({
    type:'flags', name:'Carry S',
    onSeries:candleId, onKey:'high',
    shape:'circlepin', y:BS_Y_SELL, stackDistance:14,
    fillColor:BS_CARRY_COLOR,
    width:BS_FLAG_W, style:{ color:'#fff', fontWeight:'900', fontSize:BS_FONT },
    lineWidth:0, data:[], enableMouseTracking:false
  }, false);

  chart._bs = { buy, sell, carryBuy, carrySell };
  return chart._bs;
}

function clearBS(chart){
  if (!chart?._bs) return;
  try{ chart._bs.buy.setData([], false); }catch(e){}
  try{ chart._bs.sell.setData([], false); }catch(e){}
  try{ chart._bs.carryBuy.setData([], false); }catch(e){}
  try{ chart._bs.carrySell.setData([], false); }catch(e){}
}

function isCarryAction(action){
  return action === 'CLOSE_CARRY_LONG' || action === 'CLOSE_CARRY_SHORT';
}

// trades -> side(B/S) (포지션 추적)
function normalizeTrades(trades){
  const arr = Array.isArray(trades) ? trades.slice() : [];
  arr.sort((a,b)=> (Number(a.seq||0) - Number(b.seq||0)));

  let pos = 0;
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
    else if (action === 'CLOSE_CARRY_LONG'){
      side = 'S';
      pos = Math.max(0, pos - qty);
    }
    else if (action === 'CLOSE_CARRY_SHORT'){
      side = 'B';
      pos = Math.min(0, pos + qty);
    }

    if (!side) continue;
    out.push({ ...t, _side: side, _isCarry: isCarryAction(action) });
  }
  return out;
}

function colorBSCandles(chart, buyMap, sellMap, carryMap){
  const candle = chart?.series?.[0];
  if (!candle) return;

  const rawData = candle.options?.data || [];
  if (!rawData.length) return;

  const newData = rawData.map(row => {
    const x = Array.isArray(row) ? row[0] : row.x;

    const point = {
      x,
      open: Array.isArray(row) ? row[1] : row.open,
      high: Array.isArray(row) ? row[2] : row.high,
      low: Array.isArray(row) ? row[3] : row.low,
      close: Array.isArray(row) ? row[4] : row.close
    };

    const hasB = buyMap.has(x);
    const hasS = sellMap.has(x);
    const hasCarry = carryMap?.has(x);

    // 몸통색은 건드리지 않아 기존 양봉(빨강)/음봉(파랑)을 유지하고,
    // 매매 유형은 굵은 외곽선과 B/S 플래그로만 표현한다.
    if (hasCarry){
      point.lineColor = BS_CARRY_COLOR;
      point.lineWidth = BS_TRADE_LINE_WIDTH;
    }
    else if (hasB && hasS){
      point.lineColor = BS_BOTH_LINE_COLOR;
      point.lineWidth = BS_TRADE_LINE_WIDTH;
    }
    else if (hasB){
      point.lineColor = BS_BUY_LINE_COLOR;
      point.lineWidth = BS_TRADE_LINE_WIDTH;
    }
    else if (hasS){
      point.lineColor = BS_SELL_LINE_COLOR;
      point.lineWidth = BS_TRADE_LINE_WIDTH;
    }

    return point;
  });

  candle.setData(newData, false);
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
    const carryBuyMap = new Map();
    const carrySellMap = new Map();
    const carryMap = new Map();

    for (const t of norm){
      const dt = t.bar_dt || t.datetime || '';
      let x = toMs(dt);
      if (!Number.isFinite(x)) continue;

      // 체결 시각은 초 단위이므로 해당 시간봉의 시작 시각으로 먼저 맞춘다.
      // 1분봉도 버킷 처리하지 않으면 30초 이후 체결이 다음 분봉으로 스냅된다.
      x = bucketStart(x, tg.bucketMin);
      x = snapToCandleX(chart, x);

      if (t._isCarry){
        carryMap.set(x, (carryMap.get(x)||0)+1);
        if (t._side === 'B') carryBuyMap.set(x, (carryBuyMap.get(x)||0)+1);
        else carrySellMap.set(x, (carrySellMap.get(x)||0)+1);
      }
      else if (t._side === 'B') buyMap.set(x, (buyMap.get(x)||0)+1);
      else sellMap.set(x, (sellMap.get(x)||0)+1);
    }

    const buyPts = [...buyMap.entries()].sort((a,b)=>a[0]-b[0]).map(([x,c])=>({ x, title:(c>1?`B${c}`:'B'), text:'' }));
    const sellPts= [...sellMap.entries()].sort((a,b)=>a[0]-b[0]).map(([x,c])=>({ x, title:(c>1?`S${c}`:'S'), text:'' }));
    const carryBuyPts = [...carryBuyMap.entries()].sort((a,b)=>a[0]-b[0]).map(([x,c])=>({ x, title:(c>1?`B${c}`:'B'), text:'' }));
    const carrySellPts= [...carrySellMap.entries()].sort((a,b)=>a[0]-b[0]).map(([x,c])=>({ x, title:(c>1?`S${c}`:'S'), text:'' }));

    bs.buy.setData(buyPts, false);
    bs.sell.setData(sellPts,false);
    bs.carryBuy.setData(carryBuyPts, false);
    bs.carrySell.setData(carrySellPts,false);
    colorBSCandles(chart, buyMap, sellMap, carryMap);

    if (chart === c1) {
      fitYAxisToCandles(c1, 'cndl_chart1', 0.05);
    }

    chart.redraw(false);
  }
}

/** ================== 체결로그 렌더 ================== */
function fmtInt(n){
  const v=Number(n);
  if(!Number.isFinite(v)) return '-';
  return Math.round(v).toLocaleString('ko-KR');
}

function renderTrades(trades){
  const tb = document.getElementById('tradeTbody');
  if (!tb) return;

  if (!trades || !trades.length){
    tb.innerHTML = `<tr><td colspan="5" style="color:#94a3b8;">(체결 없음)</td></tr>`;
    return;
  }

  const actionLabel = {
    OPEN_LONG: 'L 진입',
    OPEN_SHORT: 'S 진입',
    CLOSE_PART: '부분',
    CLOSE_ALL: '전청',
    CLOSE_CARRY_LONG: '이월 L',
    CLOSE_CARRY_SHORT: '이월 S',
  };

  tb.innerHTML = trades.map(t=>{
    const seq = t.seq ?? '';
    const action = t.action ?? '';
    const actionKey = String(action).toUpperCase();
    const label = actionLabel[actionKey] || action;
    const actionHtml = isCarryAction(actionKey) ? `<span class="actionCarry" title="${action}">${label}</span>` : `<b title="${action}">${label}</b>`;
    const bar_dt = (t.bar_dt ?? '').slice(11,16);
    const price = (t.price!=null) ? Number(t.price).toFixed(2) : '';
    const qty = t.qty ?? '';
    return `<tr>
      <td>${seq}</td><td>${actionHtml}</td><td>${bar_dt}</td><td>${price}</td><td>${qty}</td>
    </tr>`;
  }).join('');
}

function renderDay(day){
  if (!day) return;
  const ta = document.getElementById('dayComment');
  if (ta) ta.value = day.day_comment || '';
}

function renderDayPnl(day){
  const el = document.getElementById('dayPnlText');
  if (!el) return;

  const pnl = Number(day?.pnl_amount_net ?? 0);
  const points = Number(day?.pnl_points ?? 0);

  el.textContent = `${pnl.toLocaleString('ko-KR')}원 (${points.toFixed(2)}pt)`;

  if (pnl > 0) {
    el.style.color = '#d32f2f';
  } else if (pnl < 0) {
    el.style.color = '#1976d2';
  } else {
    el.style.color = '#64748b';
  }
}

/** ================== 데이터 로드 ================== */
async function loadCharts(){
  setStat('차트 로딩...');

  const url = `${CHART_API}?date=${encodeURIComponent(DATE)}`
    + `&start=${encodeURIComponent(START)}&end=${encodeURIComponent(END)}`
    + `&init_time=${encodeURIComponent(END)}`
    + `&max_prev_1m=0&max_prev_5m=0&max_prev_15m=0&max_prev_60m=0`
    + `&init_max_1m=2000&init_max_5m=500&init_max_15m=300&init_max_60m=0`
    + `&hi_lo_n=30&hi_lo_n2=60`;

  const r = await fetch(url, { cache:'no-store' });
  const text = await r.text();
  if (!r.ok) throw new Error(`CHART API HTTP ${r.status}`);

  const res = JSON.parse(text);
  if (!res?.ok) throw new Error(res?.msg || 'CHART API ok=false');

  const m1 = [...(res.m1?.init||[]), ...(res.m1?.future||[])];
  const m5 = res.m5?.today || [];
  const m15 = mergeRowsByDatetime(res.m15?.init || [], res.m15?.today || [])
    .filter(r => String(r?.datetime || '').slice(0, 10) === DATE);
  sessionOpen = res.hilo_main?.open != null ? +res.hilo_main.open : null;

  // 1분
  c1.series[0].setData(rowsToCandles(m1), false);
  c1.series[1].setData(rowsToLine(m1, 'sma_5'), false);
  c1.series[2].setData(rowsToLine(m1, 'sma_20'), false);
  c1.series[3].setData(rowsToLine(m1, 'sma_120'), false);
  c1.series[4].setData(rowsToLine(m1, 'vwap_session'), false);
  c1.series[5].setData(rowsToVol(m1), false);

  // 5분
  c5.series[0].setData(rowsToCandles(m5), false);
  c5.series[1].setData(rowsToLine(m5, 'sma_5'), false);
  c5.series[2].setData(rowsToLine(m5, 'sma_20'), false);
  c5.series[3].setData([], false);
  c5.series[4].setData(rowsToLine(m5, 'vwap_session'), false);
  c5.series[5].setData(rowsToVol(m5), false);

  // 15분
  c15.series[0].setData(rowsToCandles(m15), false);
  c15.series[1].setData(rowsToSma(m15, 10), false);
  c15.series[2].setData([], false);
  c15.series[3].setData([], false);
  c15.series[4].setData([], false);
  c15.series[5].setData(rowsToVol(m15), false);

  applyReferenceLines(m5);

  c15.redraw(false);
  c5.redraw(false);
  c1.redraw(false);

  fitYAxisToCandles(c1, 'cndl_chart1', 0.05);
  c1.redraw(false);

  setStat('차트 OK');
}

async function loadTrades(){
  if (!simRunId){
    renderTrades([]);
    renderDay({});
    renderDayPnl({});
    clearBS(c1); clearBS(c15); clearBS(c5);
    fitYAxisToCandles(c1, 'cndl_chart1', 0.05);
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

  const day = j.day || {};
  renderDay(day);
  renderDayPnl(day);

  applyBS(trades);

  fitYAxisToCandles(c1, 'cndl_chart1', 0.05);
  c1.redraw(false);

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
  c15 = makeChart('chart15', { mode:'fifteenMinute', fitCandleYAxis:false });
  c5  = makeChart('chart5',  { mode:'fiveMinute',    fitCandleYAxis:false });
  c1  = makeChart('chart1',  { mode:'oneMinute',     fitCandleYAxis:true  });

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
