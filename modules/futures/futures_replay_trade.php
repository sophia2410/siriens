<!-- 
- 5/15/60은 “진행봉(Partial)”이 매 1분마다 고/저/종가/거래량이 갱신되어야 정상입니다.
- 멈춤 방지: setInterval 제거(자기조절 setTimeout), redraw를 4차트 한꺼번에 하지 않고 큐로 쪼개 처리합니다.
- 고/저/시가: API hilo(08:45부터 N개 1분)로 전 차트 수평선 표시. 시간표시: 지나간 시각만 세로선 1회 추가. 
-->

<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";

$date  = $_GET['date']  ?? date('Y-m-d');
$speed = $_GET['speed'] ?? '3000';

$lvl = $_GET['lvl'] ?? '60'; // '20' or '60'

// start_at이 명시되지 않았으면 lvl에 맞춰 기본값 자동 지정
if (!isset($_GET['start_at']) || $_GET['start_at'] === '') {
  $start_at = ($lvl === '20') ? '09:04' : '09:44';
} else {
  $start_at = $_GET['start_at'];
}

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
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
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
    
    .ui-hide { display:none !important; }
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
    .layout{
      display:grid;
      grid-template-columns: 2fr 1fr;
      gap:12px;
      align-items:start;
    }
    .leftCol{ display:flex; flex-direction:column; gap:12px; min-width:0; }
    .rightCol{
      display:flex; flex-direction:column; gap:12px; min-width:0;
      position:sticky; top:10px;
      height: calc(100vh - 20px);
      overflow:auto;
    }

    .simTitle{ font-weight:800; margin:0 0 8px; color:#111; }
    .simRow{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .simRow label{ font-size:12px; color:#334155; }
    .qtyBtn{ padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; font-weight:900; cursor:pointer; }
    .qtyBtn:hover{ background:#f8fafc; }
    .qtyBtn.active{ background: #9696a3; color:#fff; border-color:#9696a3; }
    .simBox{ border:1px solid #e5e7eb; border-radius:8px; padding:10px; background:#fff; }
    .simKPI{ display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .kpi{ border:1px solid #e5e7eb; border-radius:8px; padding:8px; background:#fafafa; }
    .kpi .k{ font-size:12px; color:#475569; }
    .kpi .v{ font-weight:900; font-size:16px; color:#111; margin-top:2px; }
    .priceWrap{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;}
    .priceMain{font-weight:900;font-size:18px;color:#111;}
    .priceOhl{display:flex;gap:8px;font-size:12px;color:#475569;font-weight:700;white-space:nowrap;}
    .priceOhl b{font-weight:900;color:#111;}

    .nowStrip{
      border:1px solid #e5e7eb;
      border-radius:10px;
      padding:10px;
      background:#fff;
    }
    .nowStrip .hint{
      margin-bottom:6px;
      font-size:12px;
      color:#64748b;
      line-height:1.4;
    }
    .pill{
      display:inline-block;
      padding:3px 8px;
      border:1px solid #e2e8f0;
      border-radius:999px;
      font-size:12px;
      color:#334155;
      background:#f8fafc;
      margin-right:6px;
    }

    .tradeBtns{ display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px; }
    .tradeBtns button{ padding:10px 8px; font-weight:900; border-radius:8px; border:1px solid #e5e7eb; }
    .btnLong{ background:#fff0f0; }
    .btnShort{ background:#f0f6ff; }
    .btnClose{ background:#f3f4f6; }

    table.simTbl{ width:100%; border-collapse:collapse; font-size:12px; }
    table.simTbl th, table.simTbl td{ border-bottom:1px solid #e5e7eb; padding:6px 4px; text-align:left; }
    table.simTbl th{ position:sticky; top:0; background:#fff; z-index:2; }

    @media (max-width:1200px){
      .layout{ grid-template-columns: 1fr; }
      .rightCol{ position:static; height:auto; overflow:visible; }
      .grid{ grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<div class="layout">

  <!-- ================= 왼쪽: 기존 리플레이/차트 ================= -->
  <div class="leftCol">
    <div class="panel topbar">
      <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div><b>일자</b> <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"></div>
        <div><b>시작시간</b> <input type="time" name="start_at" value="<?= htmlspecialchars($start_at) ?>" step="60"></div>

        <button type="submit">불러오기</button>

        <button type="button" id="btnPrev" <?= empty($prev_date)?'disabled':'' ?>>◀ 이전일</button>
        <button type="button" id="btnNext" <?= empty($next_date)?'disabled':'' ?>>다음일 ▶</button>

        <input type="hidden" id="prevDate" value="<?= htmlspecialchars($prev_date ?? '') ?>">
        <input type="hidden" id="nextDate" value="<?= htmlspecialchars($next_date ?? '') ?>">

        <div><b>R/S 기준</b>
          <select id="lvlMode" name="lvl">
            <option value="20" <?= $lvl==='20'?'selected':'' ?>>장초 20분</option>
            <option value="60" <?= $lvl==='60'?'selected':'' ?>>장초 60분</option>
          </select>
        </div>

        <div><b>속도</b>
          <select id="speed" name="speed">
            <option value="3000" <?= $speed==='3000'?'selected':'' ?>>3초=1분</option>
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

        <label class="chk ui-hide"><input type="checkbox" id="chkFollow" checked>팔로우</label>
        <button type="button" class="dbgbtn ui-hide" id="btnDbg">DBG</button>

        <!-- 상태 문구(로딩/진행) -->
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
  </div>

  <!-- ================= 오른쪽: 매매/조회 패널 ================= -->
  <div class="rightCol">

    <div class="panel">
      <div class="simTitle">매매 연습</div>

      <div class="simBox">
        <div class="simRow">
          <label><b>회차</b></label>
          <select id="simRunSelect" style="min-width:180px;">
            <option value="">(회차 선택)</option>
          </select>
          <button type="button" id="btnRunReload">새로고침</button>
          <label><b>신규 회차</b></label>
          <input id="simRunName" placeholder="예: 1회차" style="min-width:140px;">
          <button type="button" id="btnRunCreate">생성</button>
        </div>

        <div class="simRow" style="margin-top:8px;">
          <div class="kpi" style="width:45%;">
            <div class="k">현재 일자</div>
            <div class="v" id="simDateKpi">-</div>
          </div>
          <div class="kpi" style="width:45%;">
            <div class="k">day_id</div>
            <div class="v" id="simDayIdKpi">-</div>
          </div>
        </div>

        <div class="simRow" style="margin-top:8px;">
          <label><b>시작시간</b></label>
          <span id="simStartAtKpi">-</span>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="simTitle">상태</div>

      <div class="simKPI">

        <div class="kpi">
          <div class="k">포지션</div>
          <div class="v" id="kpiPos">-</div>
        </div>
        <div class="kpi">
          <div class="k">평단</div>
          <div class="v" id="kpiAvg">-</div>
        </div>

        <div class="kpi">
          <div class="k">총손익(pts)</div>
          <div class="v" id="kpiPnlPts">-</div>
        </div>
        <div class="kpi">
          <div class="k">순손익(원)</div>
          <div class="v" id="kpiNetAmt">-</div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="simTitle">주문 입력</div>

      <!-- ✅ 현재 시간/종가/이격 표시 (차트 진행마다 갱신) -->
      <div class="nowStrip">
        <div class="hint">
          <span class="pill" id="pillPoint">1pt = 250,000원</span>
          <span class="pill">R=<b id="lvR">-</b></span>
          <span class="pill">Flip=<b id="lvFlip">-</b></span>
          <span class="pill">S=<b id="lvS">-</b></span>
          <span class="pill">1R=<b id="lv1R">-</b></span>
          <span class="pill">Base=<b id="lvBase">-</b></span>
        </div>
        <div class="simKPI">
          <div class="kpi">
            <div class="k">현재 시각(결정봉)</div>
            <div class="v" id="kpiNowDt">-</div>
          </div>
          <div class="kpi">
            <div class="k">현재 종가(결정봉)</div>
            <div class="v priceWrap">
              <span class="priceMain" id="kpiNowClose">-</span>

              <span class="priceOhl">
                <span>시 <b id="kpiNowOpen">-</b></span>
                <span>고 <b id="kpiNowHigh">-</b></span>
                <span>저 <b id="kpiNowLow">-</b></span>
              </span>
            </div>
          </div>
          <div class="kpi">
            <div class="k">평단 이격(pts)</div>
            <div class="v" id="kpiGapPts">-</div>
          </div>
          <div class="kpi">
            <div class="k">평단 이격(원)</div>
            <div class="v" id="kpiGapAmt">-</div>
          </div>
        </div>
      </div>

      <div class="simRow">
        <label><b>수량</b></label>

        <div style="display:flex; gap:6px; align-items:center;">
          <button type="button" id="btnQty1" class="qtyBtn">1</button>
          <button type="button" id="btnQty2" class="qtyBtn">2</button>
          <button type="button" id="btnQty3" class="qtyBtn">3</button>

          <input type="number" id="tradeQty" value="3" min="1" style="width:90px;">
        </div>

        <button type="button" id="btnUndo">↩ 마지막 취소</button>
      </div>

      <!-- 오른쪽 조작 버튼(복제) -->
      <div class="simRow" style="margin-top:8px;">
        <button type="button" id="btnPlayR">▶ 재생</button>
        <button type="button" id="btnPauseR">⏸ 정지</button>
        <button type="button" id="btnBackR">-1봉</button>
        <button type="button" id="btnStepR">+1봉</button>
        <button type="button" id="btnResetR">↺ 리셋</button>
      </div>

      <div class="tradeBtns" style="margin-top:10px;">
        <button type="button" class="btnLong"  id="btnOpenLong">롱 진입</button>
        <button type="button" class="btnShort" id="btnOpenShort">숏 진입</button>
        <button type="button" class="btnClose" id="btnClosePart">부분 청산</button>
        <button type="button" class="btnClose" id="btnCloseAll">전량 청산</button>
      </div>

      <div style="margin-top:10px; font-size:12px; color:#475569;">
        * 주문 버튼을 누르면 자동 정지(stop) 후 현재 결정봉 종가 기준으로 슬리피지를 적용해 기록합니다.
      </div>
    </div>

    <div class="panel">
      <div class="simTitle">체결 로그</div>
      <table class="simTbl">
        <thead>
          <tr>
            <th style="width:46px;">#</th>
            <th style="width:92px;">action</th>
            <th style="width:120px;">bar_dt</th>
            <th style="width:80px;">price</th>
            <th style="width:60px;">qty</th>
            <th style="width:80px;">fee</th>
          </tr>
        </thead>
        <tbody id="tradeTbody">
          <tr><td colspan="6" style="color:#94a3b8;">(아직 기록 없음)</td></tr>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <div class="simTitle">일자 코멘트</div>

      <textarea id="dayComment" rows="6" spellcheck="false" autocorrect="off" autocapitalize="off" autocomplete="off"
        style="width:98%; padding:8px; border:1px solid #e5e7eb; border-radius:8px; resize:vertical;"
        placeholder="오늘 매매 코멘트(복기/실수/규칙 위반/잘한 점 등)"></textarea>

      <div class="simRow" style="margin-top:8px;">
        <button type="button" id="btnDayCommentSave">저장</button>
        <span id="dayCommentStat" style="font-size:12px; color:#64748b;"></span>
      </div>
    </div>

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
const FIX_MAX_60 = 29;

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
let mid_0845_aux=null;
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
    // tooltip: { enabled: false },
    xAxis:{ type:'datetime', labels:{ format:'{value:%H:%M}', y:10, style:{ fontSize:'10px' } }, startOnTick:false, endOnTick:false },
    yAxis:[
      { height:'78%', lineWidth:1, labels:{ align:'right', x:4, reserveSpace:true, style:{ fontSize:'10px' } } },
      { top:'80%', height:'20%', offset:0, lineWidth:1, min:0 }
    ],
    series:[
      { type:'candlestick', id: `cndl_${name}`, name, data:[], zIndex:4, dataGrouping:{enabled:false} }, // 0
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
    // tooltip: { enabled: false },
    xAxis:{ type:'datetime', labels:{ format:'{value:%H:%M}', y:10, style:{ fontSize:'10px' } }, startOnTick:false, endOnTick:false },
    yAxis:[
      { height:'80%', lineWidth:1, labels:{ align:'right', x:4, reserveSpace:true, style:{ fontSize:'10px' } } },
      { top:'82%', height:'18%', offset:0, lineWidth:1, min:0 }
    ],
    series:[
      { type:'candlestick', id:'cndl_1m', name:'1m', data:[], zIndex:4, dataGrouping:{enabled:false} }, // 0
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
  initTimeMarks(c60, TIME_MARKS_BY_CHART.c60);
}

/** ================== 고/저/시가 라인 ================== */
function applyHiLoLines(chart){
  if (!chart) return;
  const yAxis = chart.yAxis[0];

  ['hi0845','lo0845','op0845']
    .forEach(id => { try{ yAxis.removePlotLine(id); }catch(e){} });

  if (hi_0845!=null && lo_0845!=null){
    yAxis.addPlotLine({
      id:'hi0845', value:hi_0845, color:HI_COLOR, width:1, dashStyle:'ShortDot',
      // label:{ text:`고 ${hi_0845.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:HI_COLOR} }
    });
    yAxis.addPlotLine({
      id:'lo0845', value:lo_0845, color:LO_COLOR, width:1, dashStyle:'ShortDot',
      // label:{ text:`저 ${lo_0845.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:LO_COLOR} }
    });
    if (open_0845 != null){
      yAxis.addPlotLine({
        id:'op0845', value:open_0845, color:OPEN_COLOR, width:1, dashStyle:'Dash',
        // label:{ text:`시가 ${open_0845.toFixed(2)}`, align:'left', x:5, style:{fontSize:'11px', color:OPEN_COLOR} }
      });
    }
  }
}
function applyHiLoLinesAll(){ applyHiLoLines(c1); applyHiLoLines(c5); applyHiLoLines(c15); applyHiLoLines(c60); }

/** ================== R/S/Flip 기준(20/60 선택) ================== */

// API에서 받은 장초 hilo 세트 저장
let lvl20 = null; // {open, high, low, mid, start_hhmm, end_hhmm}
let lvl60 = null;

// 현재 선택 모드
let LV_UNLOCK_MINUTES = 60;  // 20 or 60
let LV_ANCHOR_MS = null;
let lvDrawn = false;

function getReplayNowTs(){
  // 리플레이 진행 기준 "현재시각" (결정봉)
  if (idx > 0 && today1 && today1[idx - 1]) return toMs(today1[idx - 1].datetime);
  if (init1 && init1.length) return toMs(init1[init1.length - 1].datetime);
  if (initNowTs != null) return +initNowTs;
  return null;
}

function clearLevelLines(chart){
  if (!chart) return;
  const yAxis = chart.yAxis[0];
  ['lvl_hi','lvl_lo','lvl_op','lvl_mid'].forEach(id => {
    try{ yAxis.removePlotLine(id); }catch(e){}
  });
}
function clearLevelLinesAll(){
  clearLevelLines(c1); clearLevelLines(c5); clearLevelLines(c15); clearLevelLines(c60);
}

function drawLevelLines(chart, lvl){
  if (!chart || !lvl) return;
  const yAxis = chart.yAxis[0];
  clearLevelLines(chart);

  const tag = LV_UNLOCK_MINUTES;

  if (lvl.high != null){
    yAxis.addPlotLine({
      id:'lvl_hi', value:+lvl.high, color:HI_COLOR, width:2,
      label:{ text:`(${tag}) 고 ${(+lvl.high).toFixed(2)}`, align:'left', x:5, style:{fontSize:'10px', color:HI_COLOR} }
    });
  }
  if (lvl.low != null){
    yAxis.addPlotLine({
      id:'lvl_lo', value:+lvl.low, color:LO_COLOR, width:2,
      label:{ text:`(${tag}) 저 ${(+lvl.low).toFixed(2)}`, align:'left', x:5, style:{fontSize:'10px', color:LO_COLOR} }
    });
  }
  if (lvl.open != null){
    yAxis.addPlotLine({
      id:'lvl_op', value:+lvl.open, color:OPEN_COLOR, width:1, dashStyle:'Dash'
    });
  }
  if (lvl.mid != null){
    yAxis.addPlotLine({
      id:'lvl_mid', value:+lvl.mid, color:'rgba(0,0,0,0.65)', width:2, dashStyle:'ShortDot',
      label:{ text:`(${tag}) 중 ${(+lvl.mid).toFixed(2)}`, align:'left', x:5, style:{fontSize:'10px', color:'#111'} }
    });
  }
}
function drawLevelLinesAll(){
  const lvl = (LV_UNLOCK_MINUTES === 20) ? lvl20 : lvl60;
  drawLevelLines(c1, lvl);
  drawLevelLines(c5, lvl);
  drawLevelLines(c15, lvl);
  drawLevelLines(c60, lvl);
}

function setLevelTexts(lvl, unlocked){
  // Base 표시
  setText('lvBase', unlocked ? `${LV_UNLOCK_MINUTES}m` : `${LV_UNLOCK_MINUTES}m(대기)`);

  if (!unlocked || !lvl || lvl.high == null || lvl.low == null){
    setText('lvR','-'); setText('lvS','-'); setText('lvFlip','-'); setText('lv1R','-');
    return;
  }
  const R = Number(lvl.high);
  const S = Number(lvl.low);
  const Flip = (lvl.mid != null) ? Number(lvl.mid) : (R + S) / 2;
  const oneR = (R - S) * 0.25;

  setText('lvR', R.toFixed(2));
  setText('lvS', S.toFixed(2));
  setText('lvFlip', Flip.toFixed(2));
  setText('lv1R', oneR.toFixed(2));
}

function shouldUnlockLevels(nowTs){
  if (!Number.isFinite(nowTs)) return false;
  if (LV_ANCHOR_MS == null) return false;
  return nowTs >= (LV_ANCHOR_MS + LV_UNLOCK_MINUTES * 60 * 1000);
}

function maybeUnlockLevels(nowTs, doRedraw){
  if (lvDrawn) return;

  const lvl = (LV_UNLOCK_MINUTES === 20) ? lvl20 : lvl60;
  if (!lvl) return;

  // 잠금 상태면 '-' 유지
  if (!shouldUnlockLevels(nowTs)){
    setLevelTexts(lvl, false);
    return;
  }

  // unlock 순간 1회만
  lvDrawn = true;
  drawLevelLinesAll();
  setLevelTexts(lvl, true);

  if (doRedraw) queueRedrawAll();
  dbgLine(`LEVEL lines ON (${LV_UNLOCK_MINUTES}m)`);
}

function applyLevelMode(min){
  const m = (min === 20 || min === 60) ? min : 60;
  LV_UNLOCK_MINUTES = m;

  lvDrawn = false;
  clearLevelLinesAll();

  const lvl = (m === 20) ? lvl20 : lvl60;

  const startHHMM = lvl?.start_hhmm || '08:45';
  LV_ANCHOR_MS = toTSLocal(DATE, startHHMM + ':00');

  // 우선 대기 표시
  setLevelTexts(lvl, false);

  // 현재 리플레이 시각 기준으로 즉시 unlock 가능한지 판단
  const nowTs = getReplayNowTs();
  maybeUnlockLevels(nowTs, true);

  // UI 반영 + 저장
  const sel = document.getElementById('lvlMode');
  if (sel) sel.value = String(m);
  try{ localStorage.setItem('replay_level_mode', String(m)); }catch(e){}
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
  m1:  ['08:45','09:05','09:45','10:45','11:45','12:45','13:45','14:45'],
  m5:  ['08:45','09:05','09:45','10:45','11:45','12:45','13:45','14:45'],
  m15: ['08:45','09:45','10:45','11:45','12:45','13:45','14:45'],
  // m60: ['08:45'] // 필요하면 넣고, 아니면 빈 배열
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

/** ================== BS 마커(조회 전용) - 최종(심플) ==================
 * - 조회(ensureDay)로 trades 받았을 때만 적용 (리플레이 tick 중 갱신 X)
 * - CLOSE_PART/CLOSE_ALL도 "현재 포지션 방향" 추적해서 B/S로 변환
 * - 5분봉은 bucketStart(x,5)로 내리고, 마지막에 "실제 캔들 x"로 스냅해서 빗겨보임 방지
 *
 * 필요: c1, c5, toMs(), bucketStart(), queueRedraw()
 */

// ===== 설정(가시성/거리/크기) =====
const BS_Y_BUY  = 6;    // B: 아래로 px
const BS_Y_SELL = -30;   // S: 위로 px

const BS_FLAG_W = 10;    // ✅ 크기(작게) - 더 작게는 12~13
const BS_FONT   = '9px';// ✅ 글자(작게) - 더 작게는 9px

// ===== 내부 상태 =====
let BS_READY = false;
let BS_PENDING_TRADES = null;
let BS_APPLIED_SIG = null;

// ===== 캔들 x로 스냅(가장 가까운 캔들로) =====
function bsSnapToCandleX(chart, x){
  const xd = chart?.series?.[0]?.xData;
  if (!xd || !xd.length) return x;

  // binary search로 삽입 위치 찾기
  let lo = 0, hi = xd.length - 1;
  while (lo <= hi){
    const mid = (lo + hi) >> 1;
    const v = xd[mid];
    if (v === x) return x;
    if (v < x) lo = mid + 1;
    else hi = mid - 1;
  }

  // hi = x보다 작은 쪽, lo = x보다 큰 쪽
  if (hi < 0) return xd[0];
  if (lo >= xd.length) return xd[xd.length - 1];

  // ✅ 더 가까운 쪽 선택
  const left = xd[hi], right = xd[lo];
  return (Math.abs(x - left) <= Math.abs(right - x)) ? left : right;
}

// ===== flags 시리즈 확보(없으면 생성) =====
function bsEnsureSeries(chart){
  if (!chart) return null;
  if (chart._bs) return chart._bs;

  const candleSeries = chart.series?.[0];
  if (!candleSeries) return null;

  // ✅ onSeries는 "id"가 필수라서, 없으면 여기서 강제로 만들어줌(중요!)
  let candleId = candleSeries.options?.id;
  if (!candleId){
    candleId = `candle_${chart.renderTo?.id || Math.random().toString(16).slice(2)}`;
    try { candleSeries.update({ id: candleId }, false); } catch(e) {}
  }

  const buy = chart.addSeries({
    type: 'flags',
    name: 'B',
    onSeries: candleId,
    onKey: 'low',          // ✅ 저가 기준(아래로 띄우면 캔들 안 가림)
    shape: 'circlepin',
    y: BS_Y_BUY,
    stackDistance: 14,
    color: '#111',
    fillColor: 'rgba(244,67,54,0.90)', // ✅ 빨강
    width: BS_FLAG_W,                  // ✅ 작게
    style: { color:'#fff', fontWeight:'900', fontSize: BS_FONT },
    lineWidth: 0,
    data: [],
    enableMouseTracking: false
  }, false);

  const sell = chart.addSeries({
    type: 'flags',
    name: 'S',
    onSeries: candleId,
    onKey: 'high',         // ✅ 고가 기준(위로 띄우면 캔들 안 가림)
    shape: 'circlepin',
    y: BS_Y_SELL,
    stackDistance: 14,
    color: '#111',
    fillColor: 'rgba(33,150,243,0.90)', // ✅ 파랑
    width: BS_FLAG_W,                   // ✅ 작게
    style: { color:'#fff', fontWeight:'900', fontSize: BS_FONT },
    lineWidth: 0,
    data: [],
    enableMouseTracking: false
  }, false);

  chart._bs = { buy, sell };
  return chart._bs;
}

function bsClear(chart){
  if (!chart?._bs) return;
  try{ chart._bs.buy?.setData([], false); }catch(e){}
  try{ chart._bs.sell?.setData([], false); }catch(e){}
}

// ===== trades → B/S 변환(포지션 추적 포함) =====
function bsNormalizeTrades(trades){
  const arr = Array.isArray(trades) ? trades.slice() : [];

  // seq가 있으면 seq 우선, 없으면 시간
  arr.sort((a,b)=>{
    const sa = Number(a?.seq); const sb = Number(b?.seq);
    const hasSeq = Number.isFinite(sa) && Number.isFinite(sb);
    if (hasSeq) return sa - sb;
    const ta = toMs(a?.bar_dt || a?.datetime || '');
    const tb = toMs(b?.bar_dt || b?.datetime || '');
    return (ta||0) - (tb||0);
  });

  let pos = 0; // +면 롱, -면 숏
  const out = [];

  for (const t of arr){
    const action = String(t?.action || '').toUpperCase();
    const qty = Math.max(1, Math.abs(parseInt(t?.qty || '1', 10)) || 1);

    let side = null;

    if (action === 'OPEN_LONG'){
      side = 'B';
      pos += qty;
    } else if (action === 'OPEN_SHORT'){
      side = 'S';
      pos -= qty;

    } else if (action === 'CLOSE_LONG'){
      side = 'S';     // 롱 청산=매도
      pos -= qty;
    } else if (action === 'CLOSE_SHORT'){
      side = 'B';     // 숏 청산=매수
      pos += qty;

    } else if (action === 'CLOSE_PART'){
      if (pos > 0){ side = 'S'; pos = Math.max(0, pos - qty); }
      else if (pos < 0){ side = 'B'; pos = Math.min(0, pos + qty); }
      else side = null;

    } else if (action === 'CLOSE_ALL'){
      if (pos > 0) side = 'S';
      else if (pos < 0) side = 'B';
      else side = null;
      pos = 0;
    }

    if (!side) continue;
    out.push({ ...t, _side: side });
  }

  return out;
}

// ===== 중복 적용 방지용 시그니처 =====
function bsMakeSig(trades){
  const n = trades?.length || 0;
  if (!n) return '0';
  const first = trades[0];
  const last = trades[n-1];
  return [
    n,
    first?.seq ?? '',
    first?.action ?? '',
    first?.bar_dt ?? first?.datetime ?? '',
    last?.seq ?? '',
    last?.action ?? '',
    last?.bar_dt ?? last?.datetime ?? ''
  ].join('|');
}

// ===== 실제 차트에 찍기(조회 시점 1회) =====
function bsApplyFromTrades(trades){
  const norm = bsNormalizeTrades(trades);
  const sig = bsMakeSig(norm);
  if (sig === BS_APPLIED_SIG) return;
  BS_APPLIED_SIG = sig;

  const targets = [
    { chart: c1, bucketMin: 1 },
    { chart: c5, bucketMin: 5 },
  ];

  for (const tg of targets){
    const chart = tg.chart;
    if (!chart?.series?.[0]) continue;

    const bs = bsEnsureSeries(chart);
    if (!bs) continue;

    // 기존 표시 제거 후 재적용
    bsClear(chart);

    const buyMap  = new Map(); // x -> count
    const sellMap = new Map(); // x -> count

    for (const t of norm){
      const dt = t.bar_dt || t.datetime || '';
      let x = toMs(dt);
      if (!Number.isFinite(x)) continue;

      // 5분봉이면 버킷으로 내림
      if (tg.bucketMin > 1) x = bucketStart(x, tg.bucketMin);

      // ✅ 가장 가까운 캔들 x로 스냅
      x = bsSnapToCandleX(chart, x);

      if (t._side === 'B') buyMap.set(x, (buyMap.get(x) || 0) + 1);
      else sellMap.set(x, (sellMap.get(x) || 0) + 1);
    }

    // map -> points (정렬해서 안정적으로)
    const buyPts = [...buyMap.entries()]
      .sort((a,b)=>a[0]-b[0])
      .map(([x,cnt]) => ({ x, title: cnt > 1 ? `B${cnt}` : 'B', text:'' }));

    const sellPts = [...sellMap.entries()]
      .sort((a,b)=>a[0]-b[0])
      .map(([x,cnt]) => ({ x, title: cnt > 1 ? `S${cnt}` : 'S', text:'' }));

    bs.buy.setData(buyPts, false);
    bs.sell.setData(sellPts, false);

    queueRedraw(chart);
  }
}

// ===== 차트 준비 완료( setData 끝난 뒤 1회 호출 ) =====
function bsMarkChartReady(){
  BS_READY = true;

  if (BS_PENDING_TRADES){
    bsApplyFromTrades(BS_PENDING_TRADES);
    BS_PENDING_TRADES = null;
  }
}

// ===== ensureDay 성공 후 trades 넣기 =====
function bsFeedTrades(trades){
  const arr = Array.isArray(trades) ? trades : [];
  if (!BS_READY || !c1?.series?.[0]) {
    BS_PENDING_TRADES = arr;  // 차트가 아직 준비 전이면 대기
    return;
  }
  bsApplyFromTrades(arr);
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

  if (last && x < last.x) return;
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
  if (!chart) return;

  // ✅ 우리 기본 시리즈만 다룬다: 0=캔들, 1~3=SMA, 4=볼륨
  const sC = chart.series?.[0];
  if (!sC?.points?.length) return;

  while (sC.points.length > maxCandles){
    const p0 = sC.points[0];
    if (p0?.remove) p0.remove(false);
    else break;
  }

  const xMin = sC.points[0]?.x;
  if (xMin == null) return;

  // SMA/볼륨도 캔들의 xMin 이전은 제거
  for (const si of [1,2,3,4]){
    const s = chart.series?.[si];
    if (!s?.points?.length) continue;

    while (s.points.length){
      const p = s.points[0];
      if (!p || p.x == null || p.x >= xMin) break;
      if (p.remove) p.remove(false);
      else break;
    }
  }
}

/** ================== 팔로우 ================== */
function applyFollowView(chart){
  if (!chart) return;

  const cb = document.getElementById('chkFollow');
  if (!cb || !cb.checked) return;

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

/** ================== redraw 큐 ================== */
const redrawQueue = [];
const redrawQueued = new Set();
let redrawRaf = null;
let UI_LOCK = false;

function lockUiOps(){
  UI_LOCK = true;
  stop();

  // ✅ 남아있는 redraw 작업 싹 비움
  redrawQueue.length = 0;
  redrawQueued.clear();
  if (redrawRaf){
    cancelAnimationFrame(redrawRaf);
    redrawRaf = null;
  }
}

function unlockUiOps(){
  UI_LOCK = false;
}

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
  if (UI_LOCK) return;
  if (!redrawQueue.length) return;

  const chart = redrawQueue.shift();
  redrawQueued.delete(chart);

  const t0 = performance.now();
  try{
    applyFollowView(chart);
    chart.redraw(false);

    // ✅ 1분봉이 실제로 그려진 직후 KPI 갱신
    if (chart === c1){
      simSetNowTsLabel();
      updateLiveKpis();
    }
  }catch(e){
    dbgLine(`redraw error: ${e.message || e}`);
    console.error(e);
  }
  const dt = performance.now() - t0;
  DBG.lastRedrawMs = dt;
  if (dt > 50) dbgLine(`slow redraw ${dt.toFixed(1)}ms`);

  if (redrawQueue.length){
    redrawRaf = requestAnimationFrame(processRedrawQueue);
  }
  dbgKV();
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
  bsMarkChartReady();

  // ✅ 20/60 선택 기준 적용(초기에는 잠금 상태로 시작)
  const selMin = parseInt(document.getElementById('lvlMode')?.value || '60', 10) || 60;
  applyLevelMode(selMin);

  if (initNowTs != null) updateTimeMarksAll(initNowTs);

  queueRedrawAll();

  setStat(`${DATE} ${START_AT} 기준 / 남은 1분봉 ${today1.length}개`);
  dbgLine(`reset ok. future1=${today1.length}`);
  dbgKV();

  updateLiveKpis();
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

  if (bar.sma5 != null) c1.series[1].addPoint([bar.x, bar.sma5], false);
  if (bar.sma20 != null) c1.series[2].addPoint([bar.x, bar.sma20], false);
  if (bar.sma120 != null) c1.series[3].addPoint([bar.x, bar.sma120], false);

  const vS = c1.series[4];
  const vPts = vS.points || [];
  const vLast = vPts[vPts.length - 1];
  if (vLast && vLast.x === bar.x) vLast.update({ y:bar.v }, false);
  else vS.addPoint([bar.x, bar.v], false);
}

function addOne(opts){
  if (UI_LOCK) return;

  opts = opts || {};
  const doStat   = (opts.stat !== false);
  const doRedraw = (opts.redraw !== false);
  const doKpi    = (opts.kpi !== false);

  if (idx >= today1.length) { stop(); setStat('끝'); dbgLine('END'); return; }

  const r = today1[idx];
  const x = toMs(r.datetime);
  if (!Number.isFinite(x)) { stop(); setStat(`중단: datetime 파싱 실패 idx=${idx}`); dbgLine('datetime parse fail'); return; }

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
  if (doRedraw) queueRedrawAll();
  maybeUnlockLevels(bar.x, doRedraw);

  dbgKV();

  if (doKpi) updateLiveKpis();
}

/** ================== 재생 루프 ================== */
let playing = false;

// ✅ 예약 타이머/재진입 가드
let tickTimer = null;
let tickRunning = false;

function play(){
  if (playing) return;
  playing = true;

  // ✅ 남아있던 예약 타이머 제거(중복 tick 방지)
  if (tickTimer){ clearTimeout(tickTimer); tickTimer = null; }

  dbgLine('PLAY');
  tick(); // 즉시 1회 실행
}

function stop(){
  // ✅ playing=false 뿐 아니라, 예약된 tick도 반드시 취소
  playing = false;
  if (tickTimer){ clearTimeout(tickTimer); tickTimer = null; }
  dbgLine('PAUSE');
}

function tick(){
  if (!playing) return;

  // ✅ 재진입 방지(겹친 tick 차단)
  if (tickRunning) return;
  tickRunning = true;

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
    if (tickTimer){ clearTimeout(tickTimer); tickTimer = null; }
    tickRunning = false;
    dbgKV();
    return;
  }

  const dt = performance.now() - t0;
  DBG.lastTickMs = dt;
  if (dt > 60) dbgLine(`slow tick ${dt.toFixed(1)}ms at idx=${idx} last=${lastBarDt||''}`);
  dbgKV();

  tickRunning = false;

  // ✅ 다음 tick 예약(ID 저장)
  if (playing){
    tickTimer = setTimeout(tick, Math.max(0, sp - dt));
  }
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
  updateLiveKpis();
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

    // ✅ 20분 기준 세트
    lvl20 = {
      open: open_0845,
      high: hi_0845,
      low:  lo_0845,
      mid: (hi_0845 + lo_0845) / 2,
      start_hhmm: res.hilo_main.start_hhmm || '08:45',
      end_hhmm:   res.hilo_main.end_hhmm   || '09:04'
    };
  }

  if (res.hilo_aux){
    open_0845_aux = +res.hilo_aux.open;
    hi_0845_aux   = +res.hilo_aux.high;
    lo_0845_aux   = +res.hilo_aux.low;
    mid_0845_aux  = (res.hilo_aux.mid != null)
    ? +res.hilo_aux.mid
    : ((hi_0845_aux + lo_0845_aux) / 2); // ✅ 추가(안전)
    hiMetaAux = { start: res.hilo_aux.start_hhmm, end: res.hilo_aux.end_hhmm };

    // ✅ 60분 기준 세트
    lvl60 = {
      open: open_0845_aux,
      high: hi_0845_aux,
      low:  lo_0845_aux,
      mid:  mid_0845_aux,
      start_hhmm: res.hilo_aux.start_hhmm || '08:45',
      end_hhmm:   res.hilo_aux.end_hhmm   || '09:44'
    };
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

// ✅ R/S 기준(20/60) 변경 시 즉시 반영
document.getElementById('lvlMode')?.addEventListener('change', (e)=>{
  const m = parseInt(e.target.value, 10) || 60;

  // ✅ base에 따라 시작시간 자동 변경
  const startMap = { 20:'09:04', 60:'09:44' };
  const inp = document.querySelector('input[name="start_at"]');
  if (inp && startMap[m]) inp.value = startMap[m];

  // ✅ 페이지 재조회(그래야 PHP/JS 상수 START_AT/INIT_TIME까지 같이 바뀜)
  document.querySelector('form')?.submit();
});

// DBG 토글
document.getElementById('btnDbg')?.addEventListener('click', ()=>{
  DBG.enabled = !DBG.enabled;
  document.getElementById('dbgPanel').style.display = DBG.enabled ? 'block' : 'none';
  if (DBG.enabled){
    document.getElementById('dbgLog').textContent = DBG.logLines.join('\n');
    dbgKV();
  }
});
document.getElementById('btnDump')?.addEventListener('click', ()=>{
  console.log('=== REPLAY STATE DUMP ===', {
    DATE, INIT_TIME, idx, today1_len: today1.length, lastBarDt,
    partial5, partial15, partial60,
    initPartial5, initPartial15, initPartial60,
    map5_size: map5.size, map15_size: map15.size, map60_size: map60.size,
    hilo: { open_0845, hi_0845, lo_0845, hiMeta },
    follow: document.getElementById('chkFollow')?.checked,
  });
  dbgLine('dumped to console');
});
document.getElementById('btnClearLog')?.addEventListener('click', ()=>{
  DBG.logLines = [];
  document.getElementById('dbgLog').textContent = '';
});

// --- 매매시뮬용 코드 Start (FULL) -----------------------------

const TRADE_API = 'futures_replay_trade_api.php';

// 표시/계산 상수
// const POINT_VALUE = 250000; // 1pt=25만원(코스피 선물)
const POINT_VALUE = 50000; // 1pt=5만원(코스피 미니선물)
const FEE_RATE = 0.00003;    // 0.003% (서버도 동일 적용)

// 상태
let simRunId = 0;
let simDayId = 0;
let apiBusy = false;

// day 상태(서버에서 받아서 들고 있음)
let simDayState = {
  pos_qty: 0,
  avg_price: null,
  pnl_points: 0,     // realized 누적
  fee_total: 0
};

// localStorage key (일자 바뀌어도 회차 유지)
const LS_RUN_ID_KEY = 'futures_sim_run_id';

function setBusy(on){
  apiBusy = !!on;
  const ids = [
    'btnRunReload','btnRunCreate','simRunSelect',
    'btnOpenLong','btnOpenShort','btnClosePart','btnCloseAll','btnUndo',
    'btnDayCommentSave'
  ];
  ids.forEach(id=>{
    const el = document.getElementById(id);
    if (el) el.disabled = apiBusy;
  });
}

function toast(msg){ alert(msg); }

function fmtNum(v, d=2){
  if (v == null || v === '') return '-';
  const n = Number(v);
  if (!Number.isFinite(n)) return '-';
  return n.toFixed(d);
}
function fmtInt(v){
  const n = Number(v);
  if (!Number.isFinite(n)) return '-';
  return Math.round(n).toLocaleString('ko-KR');
}

function getDecisionRow(){
  if (idx > 0 && today1 && today1.length >= idx){
    return today1[idx - 1] || null;
  }
  if (init1 && init1.length){
    return init1[init1.length - 1] || null;
  }
  return null;
}

// ✅ 실시간(1분마다) 시간/종가/이격/총손익 갱신
function setText(id, v){
  const el = document.getElementById(id);
  if (el) el.textContent = v;
}

function updateLiveKpis(){
  const row = getDecisionRow();

  const hhmm = row?.datetime ? row.datetime.slice(11,16) : '-';

  const open1  = row?.open  != null ? Number(row.open)  : NaN;
  const high1  = row?.high  != null ? Number(row.high)  : NaN;
  const low1   = row?.low   != null ? Number(row.low)   : NaN;
  const close1 = row?.close != null ? Number(row.close) : NaN;

  const closeTxt = Number.isFinite(close1) ? close1.toFixed(2) : '-';

  // ✅ 주문 입력 패널(결정봉)
  setText('kpiNowDt', hhmm);
  setText('kpiNowClose', closeTxt);
  setText('kpiNowOpen', Number.isFinite(open1) ? open1.toFixed(2) : '-');
  setText('kpiNowHigh', Number.isFinite(high1) ? high1.toFixed(2) : '-');
  setText('kpiNowLow',  Number.isFinite(low1)  ? low1.toFixed(2)  : '-');

  // 포지션/평단
  const posQty = Number(simDayState?.pos_qty || 0);
  const avg = (simDayState?.avg_price != null) ? Number(simDayState.avg_price) : NaN;

  setText('kpiPos', posQty === 0 ? 'FLAT' : (posQty > 0 ? `LONG x${posQty}` : `SHORT x${Math.abs(posQty)}`));
  setText('kpiAvg', Number.isFinite(avg) ? avg.toFixed(2) : '-');

  // 이격(내 포지션 기준 +가 유리)
  let gapPts = null;
  if (posQty !== 0 && Number.isFinite(avg) && Number.isFinite(close1)){
    gapPts = (posQty > 0) ? (close1 - avg) : (avg - close1);
  }
  setText('kpiGapPts', (gapPts == null) ? '-' : gapPts.toFixed(2));
  setText('kpiGapAmt', (gapPts == null) ? '-' : fmtInt(gapPts * POINT_VALUE));

  // 총손익(pts): realized + unrealized
  const realizedPts = Number(simDayState?.pnl_points || 0);
  let unrealPts = 0;
  if (posQty !== 0 && Number.isFinite(avg) && Number.isFinite(close1)){
    unrealPts = (close1 - avg) * posQty; // posQty 부호로 숏 자동 반영
  }
  const totalPts = realizedPts + unrealPts;
  setText('kpiPnlPts', Number.isFinite(totalPts) ? totalPts.toFixed(2) : '-');

  // 순손익(원): pts*POINT_VALUE - 수수료누적
  const feeTotal = Number(simDayState?.fee_total || 0);
  const netAmt = Math.round(totalPts * POINT_VALUE) - feeTotal;
  setText('kpiNetAmt', Number.isFinite(netAmt) ? fmtInt(netAmt) : '-');
}

async function apiPost(mode, payload){
  const fd = new FormData();
  fd.append('mode', mode);
  Object.entries(payload || {}).forEach(([k,v]) => fd.append(k, v));

  const r = await fetch(TRADE_API, { method:'POST', body:fd });
  let j = null;
  try { j = await r.json(); } catch(e){
    throw new Error('API 응답 JSON 파싱 실패(HTML/Notice 섞였는지 확인)');
  }
  if (!j || !j.ok) throw new Error(j?.msg || 'API error');
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
    opt.textContent = `${r.run_id}. ${r.run_name} (${r.start_date})`;
    if (String(r.run_id) === cur) opt.selected = true;
    sel.appendChild(opt);
  });
}

function renderTrades(trades){
  const tb = document.getElementById('tradeTbody');
  if (!tb) return;

  if (!trades || !trades.length){
    tb.innerHTML = `<tr><td colspan="6" style="color:#94a3b8;">(아직 기록 없음)</td></tr>`;
    return;
  }

  tb.innerHTML = trades.map(t => {
    const seq = t.seq ?? '';
    const action = t.action ?? '';
    const bar_dt = (t.bar_dt ?? '').slice(5,16); // "MM-DD HH:MM"
    const price = (t.price != null) ? Number(t.price).toFixed(2) : '';
    const qty = t.qty ?? '';
    const fee = (t.fee_amount != null) ? fmtInt(t.fee_amount) : '-';
    return `
      <tr>
        <td>${seq}</td>
        <td><b>${action}</b></td>
        <td>${bar_dt}</td>
        <td>${price}</td>
        <td>${qty}</td>
        <td>${fee}</td>
      </tr>
    `;
  }).join('');
}

function renderDay(day){
  if (!day) return;

  simDayState = {
    pos_qty: Number(day.pos_qty || 0),
    avg_price: (day.avg_price != null ? Number(day.avg_price) : null),
    pnl_points: Number(day.pnl_points || 0),
    fee_total: Number(day.fee_total || 0),
  };

  simDayId = Number(day.day_id || 0);
  document.getElementById('simDayIdKpi').textContent = simDayId ? String(simDayId) : '-';
  document.getElementById('simDateKpi').textContent = day.trade_date || DATE;
  document.getElementById('simStartAtKpi').textContent = day.start_at || INIT_TIME || '-';

  document.getElementById('kpiPos').textContent = day.pos_text || '-';
  document.getElementById('kpiAvg').textContent = (day.avg_price != null) ? fmtNum(day.avg_price, 2) : '-';

  const ta = document.getElementById('dayComment');
  if (ta) ta.value = day.day_comment || '';

  const st = document.getElementById('dayCommentStat');
  if (st) st.textContent = '';

  updateLiveKpis();
}

function simSetNowTsLabel(){
  const row = getDecisionRow();
  const el = document.getElementById('kpiNowTs');
  if (!el) return;
  el.textContent = row?.datetime || lastBarDt || '-';
}

// ✅ addOne/resetToInit 끝날 때마다 “실시간 KPI” 업데이트되게(1분마다 바뀌어야 하니까)
(function attachLiveUpdate(){
  const _addOne = addOne;
  addOne = function(opts){
    _addOne(opts);
    simSetNowTsLabel();
  };

  const _resetToInit = resetToInit;
  resetToInit = function(){
    _resetToInit();
    simSetNowTsLabel();
    updateLiveKpis();
  };
})();

async function loadRunListAndRestore(){
  stop();

  setBusy(true);
  try{
    const j = await apiPost('run_list', {});
    const saved = localStorage.getItem(LS_RUN_ID_KEY);
    if (saved && !simRunId) simRunId = Number(saved);

    renderRuns(j.runs);

    const sel = document.getElementById('simRunSelect');
    if (sel && simRunId){
      sel.value = String(simRunId);

      lockUiOps();
      try{
        await ensureDay();
      }finally{
        unlockUiOps();
        queueRedrawAll();
      }
    }else{
      // 회차 없을 수 있음(정상) → 그냥 화면만 기본값
      simDayId = 0;
      simDayState = { pos_qty:0, avg_price:null, pnl_points:0, fee_total:0 };
      renderTrades([]);
      renderDay({ day_id:0, trade_date:DATE, start_at:INIT_TIME, pos_qty:0, avg_price:null, pnl_points:0, fee_total:0, pos_text:'FLAT' });
    }
  }catch(e){
    toast(e.message || String(e));
  }finally{
    setBusy(false);
  }
}

async function createRun(){
  const name = (document.getElementById('simRunName').value || '').trim();
  if (!name){
    toast('회차명을 입력해줘 (예: 1회차)');
    return;
  }

  setBusy(true);
  try{
    const j = await apiPost('run_create', {
      run_name: name,
      start_date: DATE,
    });
    simRunId = Number(j.run_id || 0);
    localStorage.setItem(LS_RUN_ID_KEY, String(simRunId));

    const j2 = await apiPost('run_list', {});
    renderRuns(j2.runs);

    const sel = document.getElementById('simRunSelect');
    if (sel) sel.value = String(simRunId);

    lockUiOps();
    try{
      await ensureDay();
    }finally{
      unlockUiOps();
      queueRedrawAll();
    }
  }catch(e){
    toast(e.message || String(e));
  }finally{
    setBusy(false);
  }
}

async function ensureDay(){
  if (!simRunId){
    simDayId = 0;
    document.getElementById('simDayIdKpi').textContent = '-';
    return;
  }

  const startAt = INIT_TIME || '09:00:00';

  setBusy(true);
  try{
    const j = await apiPost('day_get', {
      run_id: simRunId,
      trade_date: DATE,
      start_at: startAt,
    });
    renderDay(j.day);
    renderTrades(j.trades);
    simSetNowTsLabel();
    updateLiveKpis();
    bsFeedTrades(j.trades);
  }catch(e){
    toast(e.message || String(e));
  }finally{
    setBusy(false);
  }
}

async function tradeAdd(action){
  stop(); // 버튼 누르면 자동 정지

  if (!simRunId){
    toast('먼저 회차를 선택/생성해줘.');
    return;
  }
  if (!simDayId){
    await ensureDay();
    if (!simDayId) return;
  }

  const row = getDecisionRow();
  if (!row){
    toast('결정봉이 없습니다. +1봉 진행 후 등록해줘.');
    return;
  }

  const qty = (action === 'CLOSE_PART') ? 1 : Math.max(1, parseInt(document.getElementById('tradeQty').value || '1', 10));
  const price = Number(row.close); // ✅ 결정봉 종가 그대로 저장
  const bar_dt = row.datetime;

  if (!Number.isFinite(price)){
    toast('결정봉 종가(price)가 유효하지 않습니다.');
    return;
  }

  setBusy(true);
  try{
    const j = await apiPost('trade_add', {
      day_id: simDayId,
      action,
      bar_dt,
      price,
      qty,
      note: '',
    });
    renderDay(j.day);
    renderTrades(j.trades);
    simSetNowTsLabel();
    updateLiveKpis();
  }catch(e){
    toast(e.message || String(e));
  }finally{
    setBusy(false);
  }
}

async function tradeUndo(){
  stop();
  if (!simDayId){
    toast('취소할 day가 없습니다. 회차 선택 후 진행해줘.');
    return;
  }

  setBusy(true);
  try{
    const j = await apiPost('trade_undo', { day_id: simDayId });
    renderDay(j.day);
    renderTrades(j.trades);
    simSetNowTsLabel();
    updateLiveKpis();
  }catch(e){
    toast(e.message || String(e));
  }finally{
    setBusy(false);
  }
}

async function saveDayComment(){
  stop();

  if (!simDayId){
    toast('저장할 day가 없습니다. 회차 선택 후 day를 먼저 생성/조회해줘.');
    return;
  }

  const ta = document.getElementById('dayComment');
  const comment = (ta?.value || '');

  setBusy(true);
  try{
    await apiPost('day_comment_set', { day_id: simDayId, comment });
    const st = document.getElementById('dayCommentStat');
    if (st) st.textContent = '저장됨';
  }catch(e){
    toast(e.message || String(e));
  }finally{
    setBusy(false);
  }
}

// 이벤트 바인딩
document.getElementById('btnRunReload').addEventListener('click', loadRunListAndRestore);
document.getElementById('btnRunCreate').addEventListener('click', createRun);

document.getElementById('simRunSelect').addEventListener('change', async (e)=>{
  lockUiOps();
  try{
    const v = e.target.value;
    simRunId = v ? Number(v) : 0;
    simDayId = 0;

    if (simRunId) localStorage.setItem(LS_RUN_ID_KEY, String(simRunId));
    else localStorage.removeItem(LS_RUN_ID_KEY);

    await ensureDay();
  }finally{
    unlockUiOps();
    queueRedrawAll();
  }
});

function setQty(n){
  const inp = document.getElementById('tradeQty');
  if (!inp) return;

  inp.value = String(n);

  // active 토글
  document.getElementById('btnQty1')?.classList.toggle('active', n === 1);
  document.getElementById('btnQty2')?.classList.toggle('active', n === 2);
  document.getElementById('btnQty3')?.classList.toggle('active', n === 3);
}

// 버튼 바인딩
document.getElementById('btnQty1')?.addEventListener('click', ()=>setQty(1));
document.getElementById('btnQty2')?.addEventListener('click', ()=>setQty(2));
document.getElementById('btnQty3')?.addEventListener('click', ()=>setQty(3));

// 초기 active
setQty(parseInt(document.getElementById('tradeQty')?.value || '1', 10) || 1);

// 사용자가 직접 숫자 바꾸면 active도 따라가게
document.getElementById('tradeQty')?.addEventListener('input', (e)=>{
  const v = parseInt(e.target.value || '0', 10);

  // ✅ input은 input값을 강제로 바꾸지 말고, active 표시만 맞춘다
  document.getElementById('btnQty1')?.classList.toggle('active', v === 1);
  document.getElementById('btnQty2')?.classList.toggle('active', v === 2);
  document.getElementById('btnQty3')?.classList.toggle('active', v === 3);
});

document.getElementById('btnOpenLong').addEventListener('click', ()=>tradeAdd('OPEN_LONG'));
document.getElementById('btnOpenShort').addEventListener('click', ()=>tradeAdd('OPEN_SHORT'));
document.getElementById('btnClosePart').addEventListener('click', ()=>tradeAdd('CLOSE_PART'));
document.getElementById('btnCloseAll').addEventListener('click', ()=>tradeAdd('CLOSE_ALL'));
document.getElementById('btnUndo').addEventListener('click', tradeUndo);
document.getElementById('btnDayCommentSave')?.addEventListener('click', saveDayComment);
// 포커스 아웃 시 자동 저장
document.getElementById('dayComment')?.addEventListener('blur', ()=>{
  const st = document.getElementById('dayCommentStat');
  if (st) st.textContent = '저장 중...';
  saveDayComment();
});

// 오른쪽 컨트롤 버튼(복제)
document.getElementById('btnPlayR')?.addEventListener('click', play);
document.getElementById('btnPauseR')?.addEventListener('click', stop);
document.getElementById('btnBackR')?.addEventListener('click', stepBackOne);
document.getElementById('btnStepR')?.addEventListener('click', () => { stop(); addOne(); });
document.getElementById('btnResetR')?.addEventListener('click', resetToInit);

// 초기 표시
document.getElementById('simDateKpi').textContent = DATE;
document.getElementById('simStartAtKpi').textContent = INIT_TIME || '-';
simSetNowTsLabel();
updateLiveKpis();

// 최초: 회차 로드
loadRunListAndRestore();

// --- 매매시뮬용 코드 End -----------------------------------

/** ================== 최초 ================== */
resetCharts();
loadData();

</script>
</body>
</html>
