<?php
// -----------------------------------------------------------------------------
//  📄  futures_trade_analysis.php
//  선물 자동매매 분석 메인 화면 – 전략별 진입/청산 리스트 + 틱 상세 iframe
// -----------------------------------------------------------------------------
$pageTitle = "선물 자동매매 분석";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

// ─────────────────────────────────────────────────────────────────────────────
// 1. 파라미터 (조회 날짜 · 코드)
// ─────────────────────────────────────────────────────────────────────────────
$date = $_GET['date'] ?? date('Y-m-d');
$code = $_GET['code'] ?? '101W6000';

// DB 함수 --------------------------------------------------------------------
function get_tick_data(mysqli $mysqli, string $code, string $date, string $entry_time): array
{
    $from = str_replace(':', '', date('H:i:s', strtotime("$entry_time -5 minutes")));
    $to   = str_replace(':', '', date('H:i:s', strtotime("$entry_time -1 second")));

    $rows = $mysqli->query(
        "SELECT time, ROUND(ABS(price),2) AS price
         FROM futures_realtime_tick
         WHERE code='$code' AND date='$date' AND time BETWEEN '$from' AND '$to'
         ORDER BY time"
    )->fetch_all(MYSQLI_ASSOC);

    $open  = $rows[0]['price']  ?? null;
    $close = $rows ? end($rows)['price'] : null;

    return [
        'candles'   => [
            '0855_open'       => $open,
            '0859_close'      => $close,
            'entry_ohlc'      => get_ohlc($mysqli, $code, $date, $entry_time),
            'entry_plus1_ohlc'=> get_ohlc($mysqli, $code, $date, date('H:i:s', strtotime("$entry_time +1 minute")))
        ],
        'tick_diff' => calculate_tick_difference($open, $close)
    ];
}

function get_ohlc(mysqli $mysqli, string $code, string $date, string $target_time): string
{
    $start = str_replace(':', '', $target_time);
    $end   = str_replace(':', '', date('H:i:s', strtotime("$target_time +59 seconds")));

    $prices = array_column(
        $mysqli->query(
            "SELECT ABS(price) AS price
             FROM futures_realtime_tick
             WHERE code='$code' AND date='$date' AND time BETWEEN '$start' AND '$end'
             ORDER BY time"
        )->fetch_all(MYSQLI_ASSOC),
        'price'
    );

    return $prices
        ? sprintf('%.2f / %.2f / %.2f / %.2f', $prices[0], max($prices), min($prices), end($prices))
        : '-';
}

function calculate_tick_difference(?float $open, ?float $close)
{
    return ($open === null || $close === null) ? '-' : round(abs($close - $open) / 0.05);
}

function get_trade_result(mysqli $mysqli, string $code, string $entry_datetime): ?array
{
    return $mysqli->query(
        "SELECT direction, entry_price, exit_price, pnl
         FROM futures_trade_result
         WHERE code='$code' AND entry_datetime='$entry_datetime'"
    )->fetch_assoc();
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. 스케줄 로드
// ─────────────────────────────────────────────────────────────────────────────
$schedules = $mysqli->query(
    "SELECT trade_time FROM futures_trade_schedule WHERE trade_date='$date' ORDER BY trade_time"
)->fetch_all(MYSQLI_ASSOC);
?>

<!-- ╔══════════════  스타일  ══════════════╗ -->
<style>
/***** 레이아웃 *****/
#container        {display:flex;flex-direction:column;height:100vh;margin-left:100px;width:calc(100% - 100px);}
#query-area       {padding:10px;background:#fff;border-bottom:1px solid #ccc;display:flex;gap:10px;align-items:center;}
#split-container  {display:flex;flex:1;overflow:hidden;}
#left-pane        {width:66.66%;overflow:auto;padding-right:10px;}
#right-pane       {width:33.33%;display:flex;flex-direction:column;border-left:1px solid #ccc;}

/***** 테이블 *****/
#trade-table      {border-collapse:collapse;width:100%;}
#trade-table th, #trade-table td {border:1px solid #ddd;padding:6px 4px;white-space:nowrap;font-size:13px;}
#trade-table th   {background:#f5f5f5;}
#trade-table tr.selected {background:#ffeeba;}

/***** 우측영역 *****/
#right-pane header {padding:10px;border-bottom:1px solid #ccc;font-weight:bold;display:flex;justify-content:space-between;align-items:center;}
#right-pane iframe{flex:1;border:none;width:100%;}
</style>
<!-- ╚══════════════════════════════════════╝ -->

<div id="container">
  <!-- 조회조건 -->
  <div id="query-area">
    <form method="get" style="display:flex;gap:10px;align-items:center;white-space:nowrap;">
      <label for="date">날짜:</label>
      <input type="date" id="date" name="date" value="<?= $date ?>">
      <label for="code">코드:</label>
      <input type="text"  id="code" name="code" value="<?= $code ?>" style="width:90px;">
      <button type="submit">조회</button>
    </form>
  </div>

  <!-- 좌·우 분할 -->
  <div id="split-container">

    <!-- ▣ 왼쪽: 전략 리스트 -->
    <div id="left-pane">
      <table id="trade-table">
          <thead>
              <tr>
                  <th>진입시간</th>
                  <th>5분 시가</th>
                  <th>5분 종가</th>
                  <th>틱차이</th>
                  <th>방향</th>
                  <th>진입</th>
                  <th>청산</th>
                  <th>PnL</th>
                  <th>틱차이</th>
                  <th>체결조회</th>
                  <th>진입 시고저종</th>
                  <th>진입+1Min 시고저종</th>
              </tr>
          </thead>
        <tbody>
        <?php foreach ($schedules as $row):
            $t  = $row['trade_time'];
            $dt = "$date $t";
            $tick = get_tick_data($mysqli, $code, $date, $t);
            $res  = get_trade_result($mysqli, $code, $dt);
        ?>
          <tr onclick="selectRow(this)">
            <td><?= $t ?></td>
            <td><?= $tick['candles']['0855_open'] ?></td>
            <td><?= $tick['candles']['0859_close'] ?></td>
            <td><?= $tick['tick_diff'] ?>틱</td>
            <td><?= $res ? strtoupper($res['direction']) : '-' ?></td>
            <td><?= $res['entry_price'] ?? '-' ?></td>
            <td><?= $res['exit_price']  ?? '-' ?></td>
            <td><?= $res['pnl']        ?? '-' ?></td>
            <td><?= ($res && $res['entry_price'] && $res['exit_price'])
                      ? calculate_tick_difference($res['entry_price'],$res['exit_price']).'틱' : '-' ?></td>
            <td><a href="#" onclick="showDetail(event,'<?= $code ?>','<?= $date ?>','<?= $t ?>')">보기</a></td>
            <td><?= $tick['candles']['entry_ohlc'] ?></td>
            <td><?= $tick['candles']['entry_plus1_ohlc'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ▣ 오른쪽: 체결 상세 iframe -->
    <div id="right-pane">
      <header>
        <span id="tick-detail-title">체결 상세 보기</span>
      </header>
      <iframe id="tick-detail-frame"></iframe>
    </div>
  </div>
</div>

<!-- JavaScript: 행 강조 + iframe 로드 -->
<script>
function selectRow(row){
  document.querySelectorAll('#trade-table tr').forEach(r=>r.classList.remove('selected'));
  row.classList.add('selected');
}
function showDetail(e,code,date,time){
  e.preventDefault();
  // 행 강조
  selectRow(e.target.closest('tr'));
  // 제목 & iframe 경로 갱신
  document.getElementById('tick-detail-title').textContent = `${code} @ ${time}`;
  document.getElementById('tick-detail-frame').src = `futures_tick_detail.php?code=${code}&date=${date}&time=${time}`;
}
</script>

<?php require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php";?>
