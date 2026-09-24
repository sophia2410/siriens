<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/common/database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function valid_date($v) {
    if (!$v) return false;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}

function fmt_num($v, $dec = 2) {
    if ($v === null || $v === '') return '-';
    return number_format((float)$v, $dec);
}

function rate_class($v) {
    if ($v === null || $v === '') return 'flat';
    $n = (float)$v;
    if ($n > 0) return 'up';
    if ($n < 0) return 'down';
    return 'flat';
}

function fetch_one($mysqli, $sql, $types = '', $params = []) {
    $stmt = $mysqli->prepare($sql);
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function fetch_all_rows($mysqli, $sql, $types = '', $params = []) {
    $stmt = $mysqli->prepare($sql);
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

// -----------------------------------------------------------------------------
// 저장
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';

    if (!valid_date($date)) {
        http_response_code(400);
        exit('잘못된 날짜입니다.');
    }

    $allowed = ['', '상승', '중립', '하락'];

    $preView  = trim($_POST['pre_market_view'] ?? '');
    $postView = trim($_POST['post_market_view'] ?? '');

    if (!in_array($preView, $allowed, true))  $preView = '';
    if (!in_array($postView, $allowed, true)) $postView = '';

    $preComment    = trim($_POST['pre_market_comment'] ?? '');
    $postComment   = trim($_POST['post_market_comment'] ?? '');
    $reviewComment = trim($_POST['review_comment'] ?? '');

    $openType = trim($_POST['open_type'] ?? '');
    $intradayPattern = trim($_POST['intraday_pattern'] ?? '');

    $allowedOpenTypes = ['', '갭상승', '보합', '갭하락'];
    $allowedPatterns = [
        '',
        '상승 지속',
        '하락 지속',
        '상승→하락',
        '하락→상승',
        '상승 후 횡보',
        '하락 후 횡보',
        '횡보/혼조'
    ];

    if (!in_array($openType, $allowedOpenTypes, true)) $openType = '';
    if (!in_array($intradayPattern, $allowedPatterns, true)) $intradayPattern = '';

    $stmt = $mysqli->prepare("\n        INSERT INTO market_issue_comment\n        (\n            date,\n            open_type,\n            intraday_pattern,\n            pre_market_view,\n            pre_market_comment,\n            post_market_view,\n            post_market_comment,\n            review_comment\n        )\n        VALUES (\n            ?, NULLIF(?, ''), NULLIF(?, ''),\n            NULLIF(?, ''), NULLIF(?, ''),\n            NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, '')\n        )\n        ON DUPLICATE KEY UPDATE\n            open_type = VALUES(open_type),\n            intraday_pattern = VALUES(intraday_pattern),\n            pre_market_view = VALUES(pre_market_view),\n            pre_market_comment = VALUES(pre_market_comment),\n            post_market_view = VALUES(post_market_view),\n            post_market_comment = VALUES(post_market_comment),\n            review_comment = VALUES(review_comment)\n    ");
    $stmt->bind_param(
        'ssssssss',
        $date,
        $openType,
        $intradayPattern,
        $preView,
        $preComment,
        $postView,
        $postComment,
        $reviewComment
    );
    $stmt->execute();
    $stmt->close();

    header('Location: market_daily_review.php?date=' . urlencode($date) . '&saved=1');
    exit;
}

// -----------------------------------------------------------------------------
// 날짜 결정
// -----------------------------------------------------------------------------
$date = $_GET['date'] ?? '';

if (!valid_date($date)) {
    $latest = fetch_one($mysqli, "\n        SELECT MAX(issue_date) AS d\n        FROM market_issue\n        WHERE issue_date IS NOT NULL\n    ");
    $date = $latest && $latest['d'] ? $latest['d'] : date('Y-m-d');
}

// 이전/다음 날짜: market_issue가 있는 날짜 기준
$prevRow = fetch_one($mysqli, "\n    SELECT MAX(issue_date) AS d\n    FROM market_issue\n    WHERE issue_date < ?\n", 's', [$date]);

$nextRow = fetch_one($mysqli, "\n    SELECT MIN(issue_date) AS d\n    FROM market_issue\n    WHERE issue_date > ?\n", 's', [$date]);

$prevDate = $prevRow['d'] ?? null;
$nextDate = $nextRow['d'] ?? null;

// -----------------------------------------------------------------------------
// 1) 시장 이슈
// -----------------------------------------------------------------------------
$issues = fetch_all_rows($mysqli, "\n    SELECT\n        id, issue_date, source_name, title, headline, tags, video_id, video_url\n    FROM market_issue\n    WHERE issue_date = ?\n    ORDER BY id ASC\n", 's', [$date]);

// -----------------------------------------------------------------------------
// 2) 시장 결과
// 국내: 선택일 당일
// 미국: 선택일보다 이전의 가장 최근 거래일
// -----------------------------------------------------------------------------
$domesticRows = fetch_all_rows($mysqli, "\n    SELECT market_fg, date, open, high, low, close, volume, amount, close_rate\n    FROM market_index\n    WHERE date = ?\n      AND market_fg IN ('KOSPI','KOSDAQ')\n", 's', [$date]);

$domestic = [];
foreach ($domesticRows as $r) $domestic[$r['market_fg']] = $r;

$usDateRow = fetch_one($mysqli, "\n    SELECT MAX(a.date) AS d\n    FROM market_index a\n    INNER JOIN market_index b\n        ON b.date = a.date\n       AND b.market_fg = 'NASDAQ'\n    WHERE a.market_fg = 'S&P500'\n      AND a.date < ?\n", 's', [$date]);
$usDate = $usDateRow['d'] ?? null;

$us = [];
if ($usDate) {
    $usRows = fetch_all_rows($mysqli, "\n        SELECT market_fg, date, open, high, low, close, volume, amount, close_rate\n        FROM market_index\n        WHERE date = ?\n          AND market_fg IN ('S&P500','NASDAQ')\n    ", 's', [$usDate]);
    foreach ($usRows as $r) $us[$r['market_fg']] = $r;
}

// -----------------------------------------------------------------------------
// 3) 내 판단
// -----------------------------------------------------------------------------
$comment = fetch_one($mysqli, "\n    SELECT\n        date,\n        open_type, intraday_pattern,\n        pre_market_view, pre_market_comment,\n        post_market_view, post_market_comment,\n        review_comment\n    FROM market_issue_comment\n    WHERE date = ?\n", 's', [$date]);

if (!$comment) {
    $comment = [
        'open_type' => '',
        'intraday_pattern' => '',
        'pre_market_view' => '',
        'pre_market_comment' => '',
        'post_market_view' => '',
        'post_market_comment' => '',
        'review_comment' => ''
    ];
}



// -----------------------------------------------------------------------------
// 4) 최근 시장 이벤트 / 예정 일정
//    - 마켓리뷰에서는 "이벤트"를 기준으로 한 번만 표시
//    - 최근 7일 이벤트를 호재 / 중립 / 악재로 구분
//    - 관련 테마는 이벤트 카드의 태그로만 표시하여 중복을 줄임
// -----------------------------------------------------------------------------
$recentMarketEvents = fetch_all_rows($mysqli, "
    SELECT
        e.id,
        e.event_date,
        e.title,
        e.market_view,
        e.direction,
        GROUP_CONCAT(t.title ORDER BY t.sort_order, t.id SEPARATOR '||') AS theme_titles
    FROM market_event e
    LEFT JOIN market_event_theme et ON et.event_id = e.id
    LEFT JOIN market_theme t ON t.id = et.theme_id
    WHERE e.event_date BETWEEN DATE_SUB(?, INTERVAL 6 DAY) AND ?
    GROUP BY e.id, e.event_date, e.title, e.market_view, e.direction
    ORDER BY e.event_date DESC, e.id DESC
    LIMIT 15
", 'ss', [$date, $date]);

$eventBuckets = ['호재'=>[], '중립'=>[], '악재'=>[]];
$recentThemeCounts = [];

foreach ($recentMarketEvents as $ev) {
    $direction = $ev['direction'] ?: '중립';
    if (!isset($eventBuckets[$direction])) $direction = '중립';

    $ev['age_days'] = max(0, (int)((strtotime($date) - strtotime($ev['event_date'])) / 86400));
    $eventBuckets[$direction][] = $ev;

    if (!empty($ev['theme_titles'])) {
        foreach (explode('||', $ev['theme_titles']) as $themeName) {
            $themeName = trim($themeName);
            if ($themeName === '') continue;
            $recentThemeCounts[$themeName] = ($recentThemeCounts[$themeName] ?? 0) + 1;
        }
    }
}
arsort($recentThemeCounts);

$upcomingCalendar = fetch_all_rows($mysqli, "
    SELECT id, event_date, event_time, title, importance, source_type
    FROM market_calendar
    WHERE event_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
    ORDER BY event_date ASC,
             event_time IS NULL ASC,
             event_time ASC,
             importance DESC,
             id ASC
    LIMIT 8
", 'ss', [$date, $date]);

function event_dir_class($direction) {
    if ($direction === '호재') return 'event-good';
    if ($direction === '악재') return 'event-bad';
    return 'event-neutral';
}

function event_age_text($ageDays) {
    if ($ageDays <= 0) return '오늘';
    return $ageDays . '일 전';
}

// -----------------------------------------------------------------------------
// 5) 우측 차트용 선물 데이터
// -----------------------------------------------------------------------------
$futuresDaily = fetch_all_rows($mysqli, "
    SELECT date, open, high, low, close, sma_5, sma_20, sma_120
    FROM futures_1day
    WHERE date <= ?
    ORDER BY date DESC
    LIMIT 30
", 's', [$date]);
$futuresDaily = array_reverse($futuresDaily);

$futures5m = fetch_all_rows($mysqli, "
    SELECT date, time, open, high, low, close, sma_5, sma_20, sma_120
    FROM futures_5min
    WHERE date = ?
    ORDER BY time ASC
", 's', [$date]);

function render_view_radios($name, $current) {
    foreach (['상승','중립','하락'] as $v) {
        $checked = ($current === $v) ? ' checked' : '';
        $cls = $v === '상승' ? 'bull' : ($v === '하락' ? 'bear' : 'neutral');
        echo '<label class="view-radio ' . $cls . '">';
        echo '<input type="radio" name="' . h($name) . '" value="' . h($v) . '"' . $checked . '>';
        echo '<span>' . h($v) . '</span>';
        echo '</label>';
    }
}


function render_choice_radios($name, $values, $current) {
    echo '<input type="hidden" name="' . h($name) . '" id="' . h($name) . '" value="' . h($current) . '">';
    foreach ($values as $v) {
        $active = ($current === $v) ? ' active' : '';
        echo '<button type="button" class="choice-btn' . $active . '"'
           . ' data-target="' . h($name) . '"'
           . ' data-value="' . h($v) . '">'
           . h($v)
           . '</button>';
    }
}

function index_card($label, $row, $note = '') {
    $rate = $row['close_rate'] ?? null;
    $cls = rate_class($rate);
    echo '<div class="index-card">';
    echo '<div class="index-top"><strong>' . h($label) . '</strong>';
    if ($note !== '') echo '<span class="index-note">' . h($note) . '</span>';
    echo '</div>';
    if (!$row) {
        echo '<div class="empty-small">데이터 없음</div>';
    } else {
        echo '<div class="index-close">' . fmt_num($row['close'], 2) . '</div>';
        echo '<div class="index-rate ' . $cls . '">' . (($rate !== null && $rate !== '') ? (($rate > 0 ? '+' : '') . fmt_num($rate, 2) . '%') : '-') . '</div>';
        echo '<div class="index-detail">시 ' . fmt_num($row['open'], 2) . ' · 고 ' . fmt_num($row['high'], 2) . ' · 저 ' . fmt_num($row['low'], 2) . '</div>';
    }
    echo '</div>';
}

$weekdayNames = ['일','월','화','수','목','금','토'];
$weekday = $weekdayNames[(int)date('w', strtotime($date))];

$kospiRate = $domestic['KOSPI']['close_rate'] ?? null;
$preViewSummary = !empty($comment['pre_market_view']) ? $comment['pre_market_view'] : '-';
$postViewSummary = !empty($comment['post_market_view']) ? $comment['post_market_view'] : '-';
$openTypeSummary = !empty($comment['open_type']) ? $comment['open_type'] : '-';
$patternSummary = !empty($comment['intraday_pattern']) ? $comment['intraday_pattern'] : '-';
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Market Daily Review</title>
<script src="https://code.highcharts.com/stock/highstock.js"></script>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f3f5f8;color:#1f2937;font-family:Arial,'Malgun Gothic',sans-serif;font-size:14px}
a{color:inherit;text-decoration:none}
.wrap{max-width:1880px;margin:0 auto;padding:16px 20px}
.topbar{background:#fff;border:1px solid #dfe3e8;border-radius:10px;margin-bottom:14px;padding:10px 14px}
.top-main{display:flex!important;align-items:center;justify-content:flex-start!important;gap:8px;white-space:nowrap}
.date-box{display:flex;align-items:center;gap:8px;margin:0}
.date-box input{font-size:18px;font-weight:700;border:1px solid #cfd5dc;border-radius:7px;padding:7px 10px}
.nav-btn{display:inline-block;padding:8px 12px;border:1px solid #cfd5dc;border-radius:7px;background:#fff;color:#334155}
.nav-btn.disabled{opacity:.35;pointer-events:none}
.go-btn,.save-btn{border:0;border-radius:7px;padding:9px 16px;background:#1f2937;color:#fff;cursor:pointer}
.question-btn{display:inline-flex;align-items:center;padding:8px 12px;border:1px solid #cfd5dc;border-radius:7px;background:#fff;color:#334155;font-weight:700;white-space:nowrap}
.day-summary{display:flex;align-items:center;gap:14px;margin-left:18px;padding-left:18px;border-left:1px solid #e5e7eb;color:#64748b;font-size:12px;white-space:nowrap}
.day-summary strong{color:#111827}
.section{background:#fff;border:1px solid #dfe3e8;border-radius:10px;margin-bottom:9px;overflow:hidden}
.section-head{padding:8px 12px;border-bottom:1px solid #e5e7eb;background:#fafafa;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:space-between}
.section-body{padding:10px 11px}
.morning-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(420px,.85fr);gap:14px}
.morning-col{min-width:0}
.sub-title{font-size:13px;font-weight:700;color:#64748b;margin:0 0 9px}
.issue-card{border:1px solid #e5e7eb;border-radius:8px;padding:10px 11px;margin-bottom:6px}
.issue-card:last-child{margin-bottom:0}
.issue-headline{font-size:21px;font-weight:800;line-height:1.35;color:#111827;margin-bottom:7px;white-space:pre-line}
.issue-title{font-size:13px;line-height:1.55;color:#475569}
.tags{margin-top:7px;color:#64748b;font-size:12px}
.video-link{display:inline-block;margin-top:7px;color:#2563eb;font-size:12px}
.us-grid,.domestic-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.index-card{border:1px solid #e5e7eb;border-radius:8px;padding:13px;min-height:118px}
.index-top{display:flex;justify-content:space-between;gap:8px;align-items:center}.index-note{font-size:10px;color:#94a3b8}
.index-close{font-size:21px;font-weight:700;margin-top:12px}
.index-rate{font-size:15px;font-weight:700;margin-top:2px}.up{color:#dc2626}.down{color:#2563eb}.flat{color:#64748b}
.index-detail{font-size:11px;color:#94a3b8;margin-top:8px}.empty-small{color:#94a3b8;margin-top:27px}
.result-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(270px,.55fr);gap:10px;align-items:stretch}
.flow-placeholder{height:100%;min-height:118px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;padding:22px;color:#64748b;text-align:center;display:flex;align-items:center;justify-content:center;line-height:1.7}
.judgement-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.form-block{border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;min-width:0}
.form-title{padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e5e7eb;font-weight:700;display:flex;align-items:center;justify-content:space-between;gap:8px}
.form-content{padding:12px}.radio-row{display:flex;gap:7px;margin-bottom:9px}.view-radio input{display:none}.view-radio span{display:inline-block;padding:6px 14px;border:1px solid #cfd5dc;border-radius:20px;cursor:pointer;background:#fff}
.view-radio input:checked+span{font-weight:700;border-color:#111827;background:#111827;color:#fff}
textarea{width:100%;min-height:145px;border:1px solid #cfd5dc;border-radius:7px;padding:10px 11px;font-family:inherit;font-size:13px;line-height:1.55;resize:vertical}
.actions{text-align:right;margin-top:12px}.save-btn{font-size:14px;padding:9px 24px}.saved{color:#059669;font-weight:700;font-size:12px}
.hint{font-size:11px;color:#94a3b8;font-weight:400}.empty{padding:25px;text-align:center;color:#94a3b8}
.us-date{margin-top:10px;font-size:12px;color:#64748b;text-align:right}
@media(max-width:1000px){.wrap{padding:10px}.morning-grid,.result-grid,.judgement-grid{grid-template-columns:1fr}.top-main{grid-template-columns:90px 1fr 90px}.day-summary{flex-wrap:wrap;gap:8px 15px}}

.page-grid{display:grid;grid-template-columns:minmax(0,2.05fr) minmax(480px,.70fr);gap:14px;align-items:start}
.left-column{min-width:0}
.right-column{min-width:0;display:flex;flex-direction:column;gap:14px}
.chart-section{background:#fff;border:1px solid #dfe3e8;border-radius:10px;overflow:hidden}
.chart-head{padding:10px 13px;border-bottom:1px solid #e5e7eb;background:#fafafa;font-size:13px;font-weight:700;display:flex;justify-content:space-between}
.chart-box{height:275px}
.chart-box.intraday{height:305px}
.flow-mini{padding:13px}
.flow-mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.flow-mini-item{border:1px solid #e5e7eb;border-radius:7px;padding:10px}
.flow-mini-label{font-size:11px;color:#64748b}.flow-mini-value{font-size:17px;font-weight:700;margin-top:5px}
.market-pattern{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid #eef1f4}
.pattern-label{font-size:12px;color:#64748b;font-weight:700}
.pattern-placeholder{font-size:12px;color:#94a3b8}
.us-compact{border:1px solid #e5e7eb;border-radius:8px;overflow:hidden}
.us-compact-head{display:flex;justify-content:space-between;padding:9px 11px;background:#f8fafc;border-bottom:1px solid #e5e7eb;font-size:12px}
.us-row{display:grid;grid-template-columns:90px 1fr 75px;align-items:center;padding:9px 11px;border-bottom:1px solid #f0f2f5}
.us-row:last-child{border-bottom:0}.us-name{font-weight:700}.us-close{text-align:right;font-weight:700}.us-rate{text-align:right;font-weight:700}
.judgement-grid textarea{min-height:250px}.judgement-grid .form-block:last-child textarea{min-height:290px}
.choice-wrap{display:flex;flex-wrap:nowrap;gap:5px}
.choice-btn{display:inline-block;padding:5px 8px;border:1px solid #cfd5dc;border-radius:18px;background:#fff;cursor:pointer;font-size:12px;color:#334155}
.choice-btn.active{background:#111827;color:#fff;border-color:#111827;font-weight:700}
.pattern-grid{display:grid;grid-template-columns:auto 1fr;gap:6px 9px;align-items:start;margin-top:7px;padding-top:7px;border-top:1px solid #eef1f4}
.pattern-grid .pattern-label{padding-top:6px}
@media(max-width:1250px){.page-grid{grid-template-columns:1fr}.right-column{display:grid;grid-template-columns:1fr 1fr}.right-column .chart-section:last-child{grid-column:1/-1}.top-main{flex-wrap:wrap}.day-summary{margin-left:0;padding-left:0;border-left:0}}
@media(max-width:800px){.right-column{display:block}.chart-section{margin-bottom:12px}}


.sequence-title{display:flex;align-items:center;gap:9px}
.step-badge{display:inline-flex;width:24px;height:24px;border-radius:50%;align-items:center;justify-content:center;background:#111827;color:#fff;font-size:12px}
.pre-section textarea{min-height:120px}
.after-grid{display:grid;grid-template-columns:1fr 1.15fr;gap:12px}
.after-grid textarea{min-height:155px}
.after-grid .review-box textarea{min-height:175px}
.market-flow-controls{margin-top:12px;border-top:1px solid #e5e7eb;padding-top:12px}
.market-flow-controls .pattern-grid{margin-top:0;padding-top:0;border-top:0}



/* 최근 시장 이벤트 - 이벤트 중심 요약 */
.event-overview{border:1px solid #d8dee8;border-radius:9px;background:#fff;margin-bottom:10px;overflow:hidden}
.event-overview-head{display:flex;align-items:center;gap:10px;padding:8px 10px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
.event-overview-title{font-size:13px;font-weight:800;color:#111827}
.event-overview-rule{font-size:10px;color:#94a3b8}
.event-counts{margin-left:auto;display:flex;align-items:center;gap:5px;font-size:11px;white-space:nowrap}
.event-count{padding:3px 7px;border-radius:12px;border:1px solid #d8dee8;background:#fff;font-weight:700}
.event-count.good{color:#b91c1c;border-color:#fecaca;background:#fff7f7}
.event-count.bad{color:#1d4ed8;border-color:#bfdbfe;background:#f5f9ff}
.event-count.neutral{color:#64748b;border-color:#d8dee8;background:#fff}
.event-flow-link{margin-left:4px;color:#334155;border:1px solid #cbd5e1;background:#fff;border-radius:6px;padding:4px 7px;font-size:11px;font-weight:700}
.event-columns{display:grid;grid-template-columns:1fr .78fr 1fr;gap:8px;padding:8px;background:#fbfcfe}
.event-column{min-width:0;border:1px solid #edf0f4;border-radius:8px;background:#fff;padding:7px}
.event-column-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;padding:0 2px;font-size:12px;font-weight:800}
.event-column-head.good{color:#b91c1c}.event-column-head.bad{color:#1d4ed8}.event-column-head.neutral{color:#64748b}
.market-event-card{border:1px solid #e5e7eb;border-radius:7px;padding:8px 9px;margin-bottom:6px;background:#fff}
.market-event-card:last-child{margin-bottom:0}
.market-event-card.event-good{border-left:4px solid #dc2626}
.market-event-card.event-bad{border-left:4px solid #2563eb}
.market-event-card.event-neutral{border-left:4px solid #94a3b8}
.market-event-top{display:flex;align-items:center;gap:6px;min-width:0}
.market-event-date{font-size:10px;font-weight:800;color:#64748b;white-space:nowrap}
.market-event-age{font-size:9px;color:#94a3b8;white-space:nowrap}
.market-event-title{font-size:12px;font-weight:800;color:#111827;line-height:1.35;margin:5px 0 4px}
.market-event-view{font-size:11px;color:#475569;line-height:1.4;margin-bottom:6px}
.market-event-tags{display:flex;gap:4px;flex-wrap:wrap}
.market-event-tag{display:inline-block;padding:2px 6px;border-radius:10px;background:#f1f5f9;color:#64748b;font-size:9px}
.event-empty{padding:14px 4px;text-align:center;color:#c0c7d0;font-size:10px}
.event-theme-index{display:flex;align-items:center;gap:6px;padding:7px 9px;border-top:1px solid #e5e7eb;background:#fff;overflow-x:auto;white-space:nowrap}
.event-theme-index-title{font-size:10px;font-weight:800;color:#64748b;margin-right:2px}
.event-theme-chip{display:inline-flex;align-items:center;gap:3px;padding:3px 7px;border:1px solid #e5e7eb;border-radius:12px;background:#fafafa;color:#475569;font-size:9px}
.event-theme-chip strong{color:#111827}

.calendar-strip{display:flex;align-items:center;gap:7px;padding:7px 9px;border-top:1px solid #e5e7eb;background:#fff;overflow-x:auto;white-space:nowrap}
.calendar-strip-title{font-size:11px;font-weight:800;color:#475569;margin-right:2px}
.calendar-item{display:inline-flex;align-items:center;gap:5px;border:1px solid #e5e7eb;border-radius:13px;padding:4px 7px;background:#fafafa;font-size:10px;color:#475569}
.calendar-item.today{border-color:#94a3b8;background:#fff;font-weight:700}
.calendar-date{font-weight:800;color:#334155}.calendar-stars{font-size:9px;color:#64748b;letter-spacing:-1px}
@media(max-width:1450px){.event-columns{grid-template-columns:1fr}.event-column{padding:6px}.market-event-card{margin-bottom:5px}}

.head-with-radios{display:flex;align-items:center;gap:12px}
.head-with-radios .radio-row{margin:0}
.head-with-radios .view-radio span{padding:4px 11px;font-size:12px}
.issue-title-line{display:flex;align-items:center;gap:10px;min-width:0}
.issue-title-line .issue-title{flex:1;min-width:0}
.video-link.inline{margin-top:0;white-space:nowrap}
.flow-placeholder{min-height:112px!important;padding:12px!important}
@media(max-width:1500px){.choice-wrap{flex-wrap:wrap}}
</style>
</head>
<body>
<div class="wrap">

    <div class="topbar">
        <div class="top-main">
            <a class="nav-btn <?= $prevDate ? '' : 'disabled' ?>" href="<?= $prevDate ? '?date=' . h($prevDate) : '#' ?>">◀</a>

            <form class="date-box" method="get">
                <input type="date" name="date" value="<?= h($date) ?>">
                <strong><?= h($weekday) ?>요일</strong>
                <button class="go-btn" type="submit">이동</button>
            </form>

            <a class="nav-btn <?= $nextDate ? '' : 'disabled' ?>" href="<?= $nextDate ? '?date=' . h($nextDate) : '#' ?>">▶</a>

            <a class="question-btn" href="market_question.php?date=<?= h($date) ?>">시장 질문</a>

            <div class="day-summary">
                <span>장전 <strong><?= h($preViewSummary) ?></strong></span>
                <span>장후 <strong><?= h($postViewSummary) ?></strong></span>
                <span>KOSPI <strong class="<?= rate_class($kospiRate) ?>"><?= ($kospiRate !== null && $kospiRate !== '') ? (($kospiRate > 0 ? '+' : '') . fmt_num($kospiRate,2) . '%') : '-' ?></strong></span>
                <span>시장형태 <strong><?= h($openTypeSummary) ?> · <?= h($patternSummary) ?></strong></span>
                <span>외국인선물 <strong>연결 예정</strong></span>
            </div>
        </div>
    </div>

    <div class="page-grid">
    <div class="left-column">

    <form method="post">
        <input type="hidden" name="date" value="<?= h($date) ?>">

        <!-- ① 장전 참고자료 -->
        <section class="section">
            <div class="section-head">
                <span class="sequence-title"><span class="step-badge">1</span>장전 참고자료</span>
                <span class="hint">최근 7일 시장 이벤트 · 예정 일정 · 오늘의 핵심 이슈 · 직전 미국시장</span>
            </div>
            <div class="section-body">
                <div class="event-overview">
                    <div class="event-overview-head">
                        <span class="event-overview-title">현재 시장 이벤트</span>
                        <span class="event-overview-rule">최근 7일 · 이벤트는 한 번만 표시 · 테마는 태그로 확인</span>
                        <div class="event-counts">
                            <span class="event-count good">호재 <?= count($eventBuckets['호재']) ?></span>
                            <span class="event-count neutral">중립 <?= count($eventBuckets['중립']) ?></span>
                            <span class="event-count bad">악재 <?= count($eventBuckets['악재']) ?></span>
                            <a class="event-flow-link" href="market_flow_v2.php?date=<?= h($date) ?>">전체 흐름 ↗</a>
                        </div>
                    </div>

                    <?php if (!$recentMarketEvents): ?>
                        <div class="empty" style="padding:18px">최근 7일 시장 이벤트가 없습니다.</div>
                    <?php else: ?>
                    <div class="event-columns">
                        <?php
                        $eventColumnDefs = [
                            ['호재', '호재 이벤트', 'good'],
                            ['중립', '중립 / 혼재', 'neutral'],
                            ['악재', '악재 이벤트', 'bad']
                        ];
                        foreach ($eventColumnDefs as $def):
                            [$bucketKey, $columnTitle, $columnClass] = $def;
                            $bucket = $eventBuckets[$bucketKey];
                        ?>
                        <div class="event-column">
                            <div class="event-column-head <?= h($columnClass) ?>">
                                <span><?= h($columnTitle) ?></span>
                                <span><?= count($bucket) ?>건</span>
                            </div>

                            <?php if (!$bucket): ?>
                                <div class="event-empty">해당 이벤트 없음</div>
                            <?php else: ?>
                                <?php foreach ($bucket as $ev):
                                    $evClass = event_dir_class($ev['direction']);
                                    $evTags = !empty($ev['theme_titles']) ? explode('||', $ev['theme_titles']) : [];
                                ?>
                                <div class="market-event-card <?= h($evClass) ?>">
                                    <div class="market-event-top">
                                        <span class="market-event-date"><?= h(date('m/d', strtotime($ev['event_date']))) ?></span>
                                        <span class="market-event-age"><?= h(event_age_text((int)$ev['age_days'])) ?></span>
                                    </div>
                                    <div class="market-event-title"><?= h($ev['title']) ?></div>
                                    <?php if (!empty($ev['market_view'])): ?>
                                        <div class="market-event-view">→ <?= h($ev['market_view']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($evTags): ?>
                                        <div class="market-event-tags">
                                            <?php foreach ($evTags as $tag): ?>
                                                <span class="market-event-tag">#<?= h($tag) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($recentThemeCounts): ?>
                    <div class="event-theme-index">
                        <span class="event-theme-index-title">최근 7일 관련 테마</span>
                        <?php foreach ($recentThemeCounts as $themeName => $cnt): ?>
                            <span class="event-theme-chip">#<?= h($themeName) ?> <strong><?= (int)$cnt ?></strong></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <div class="calendar-strip">
                        <span class="calendar-strip-title">앞 7일 일정</span>
                        <?php if (!$upcomingCalendar): ?>
                            <span class="muted">등록된 일정 없음</span>
                        <?php else: ?>
                            <?php foreach ($upcomingCalendar as $cal):
                                $isToday = ($cal['event_date'] === $date);
                                $dateLabel = $isToday ? '오늘' : date('m/d', strtotime($cal['event_date']));
                                $timeLabel = !empty($cal['event_time']) ? substr($cal['event_time'],0,5) : '';
                            ?>
                            <span class="calendar-item <?= $isToday ? 'today' : '' ?>">
                                <span class="calendar-date"><?= h($dateLabel) ?><?= $timeLabel ? ' ' . h($timeLabel) : '' ?></span>
                                <span><?= h($cal['title']) ?></span>
                                <span class="calendar-stars"><?= h(str_repeat('★', (int)$cal['importance'])) ?></span>
                            </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="morning-grid">
                <div class="morning-col">
                    <div class="sub-title">오늘의 핵심 이슈</div>
                    <?php if (!$issues): ?>
                        <div class="empty">등록된 시장 이슈가 없습니다.</div>
                    <?php else: ?>
                        <?php foreach ($issues as $issue): ?>
                            <div class="issue-card">
                                <?php if (!empty($issue['headline'])): ?>
                                    <div class="issue-headline"><?= nl2br(h($issue['headline'])) ?></div>
                                <?php endif; ?>
                                <div class="issue-title-line">
                                    <div class="issue-title"><?= h($issue['title']) ?></div>
                                    <?php if (!empty($issue['video_url'])): ?>
                                        <a class="video-link inline" href="<?= h($issue['video_url']) ?>" target="_blank" rel="noopener">YouTube ↗</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($issue['tags'])): ?>
                                    <div class="tags"># <?= h(str_replace(',', '  # ', $issue['tags'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="morning-col">
                    <div class="sub-title">직전 미국시장</div>
                    <div class="us-compact">
                        <div class="us-compact-head">
                            <span>미국시장</span>
                            <strong><?= $usDate ? h($usDate) : '공통 데이터 없음' ?></strong>
                        </div>
                        <?php foreach (['S&P500','NASDAQ'] as $fg): $r = $us[$fg] ?? null; ?>
                        <div class="us-row">
                            <span class="us-name"><?= h($fg) ?></span>
                            <span class="us-close"><?= $r ? fmt_num($r['close'],2) : '데이터 없음' ?></span>
                            <span class="us-rate <?= rate_class($r['close_rate'] ?? null) ?>">
                                <?php if ($r && $r['close_rate'] !== null && $r['close_rate'] !== ''): ?>
                                    <?= ((float)$r['close_rate'] > 0 ? '+' : '') . fmt_num($r['close_rate'],2) ?>%
                                <?php else: ?>-<?php endif; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                </div>
            </div>
        </section>

        <!-- ② 장전 판단 -->
        <section class="section pre-section">
            <div class="section-head">
                <div class="head-with-radios">
                    <span class="sequence-title"><span class="step-badge">2</span>장전 판단</span>
                    <div class="radio-row">
                        <?php render_view_radios('pre_market_view', $comment['pre_market_view'] ?? ''); ?>
                    </div>
                </div>
                <span class="hint">이슈와 전일 미국시장을 본 뒤, 장 시작 전에 기록</span>
            </div>
            <div class="section-body">
                <textarea name="pre_market_comment" placeholder="호재·악재, 예상 방향, 중요 변수, 주의할 점 등을 기록합니다."><?= h($comment['pre_market_comment'] ?? '') ?></textarea>
            </div>
        </section>

        <!-- ③ 당일 시장 흐름 -->
        <section class="section">
            <div class="section-head">
                <span class="sequence-title"><span class="step-badge">3</span>당일 시장 흐름</span>
                <span class="hint">국내지수 · 갭 · 장중 움직임 · 외국인 현선물</span>
            </div>
            <div class="section-body result-grid">
                <div>
                    <div class="sub-title">국내 지수</div>
                    <div class="domestic-grid">
                        <?php index_card('KOSPI', $domestic['KOSPI'] ?? null, $date); ?>
                        <?php index_card('KOSDAQ', $domestic['KOSDAQ'] ?? null, $date); ?>
                    </div>

                    <div class="market-flow-controls">
                        <div class="pattern-grid">
                            <span class="pattern-label">시작</span>
                            <div class="choice-wrap">
                                <?php render_choice_radios(
                                    'open_type',
                                    ['갭상승','보합','갭하락'],
                                    $comment['open_type'] ?? ''
                                ); ?>
                            </div>

                            <span class="pattern-label">장중 흐름</span>
                            <div class="choice-wrap">
                                <?php render_choice_radios(
                                    'intraday_pattern',
                                    [
                                        '상승 지속',
                                        '하락 지속',
                                        '상승→하락',
                                        '하락→상승',
                                        '상승 후 횡보',
                                        '하락 후 횡보',
                                        '횡보/혼조'
                                    ],
                                    $comment['intraday_pattern'] ?? ''
                                ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="sub-title">외국인 현·선물 수급</div>
                    <div class="flow-placeholder">
                        다음 단계에서 KOSPI 현물 외국인 순매수와<br>
                        KOSPI200 선물 외국인 순매수를 연결합니다.
                    </div>
                </div>
            </div>
        </section>

        <!-- ④ 장후 판단 / ⑤ 복기 -->
        <section class="section">
            <div class="section-head">
                <span class="sequence-title"><span class="step-badge">4</span>장후 판단 / 복기</span>
                <?php if (isset($_GET['saved'])): ?>
                    <span class="saved">저장되었습니다.</span>
                <?php else: ?>
                    <span class="hint">실제 흐름을 해석하고 장전 판단과 비교</span>
                <?php endif; ?>
            </div>
            <div class="section-body">
                <div class="after-grid">
                    <div class="form-block">
                        <div class="form-title">
                            <div class="head-with-radios">
                                <span>장후 판단</span>
                                <div class="radio-row">
                                    <?php render_view_radios('post_market_view', $comment['post_market_view'] ?? ''); ?>
                                </div>
                            </div>
                            <span class="hint">실제 결과 + 수급</span>
                        </div>
                        <div class="form-content">
                            <textarea name="post_market_comment" placeholder="실제 시장이 왜 그렇게 움직였는지, 장중 중요 변화와 수급 등을 기록합니다."><?= h($comment['post_market_comment'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-block review-box">
                        <div class="form-title">
                            <span>복기</span>
                            <span class="hint">장전 예상과 실제의 차이</span>
                        </div>
                        <div class="form-content">
                            <textarea name="review_comment" placeholder="맞았던 점 / 놓친 점 / 판단이 바뀐 시점 / 다음에 확인할 기준 등을 기록합니다."><?= h($comment['review_comment'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="save-btn">전체 저장</button>
                </div>
            </div>
        </section>

    </form>

    </div>

    <aside class="right-column">
        <section class="chart-section">
            <div class="chart-head">
                <span>KOSPI200 일봉</span>
                <span class="hint">최근 30거래일</span>
            </div>
            <div id="futuresDailyChart" class="chart-box"></div>
        </section>

        <section class="chart-section">
            <div class="chart-head">
                <span>KOSPI200 당일 5분봉</span>
                <span class="hint"><?= h($date) ?></span>
            </div>
            <div id="futures5mChart" class="chart-box intraday"></div>
        </section>

        <section class="chart-section">
            <div class="chart-head">
                <span>외국인 수급 요약</span>
                <span class="hint">다음 단계 연결</span>
            </div>
            <div class="flow-mini">
                <div class="flow-mini-grid">
                    <div class="flow-mini-item">
                        <div class="flow-mini-label">KOSPI 현물 외국인</div>
                        <div class="flow-mini-value">-</div>
                    </div>
                    <div class="flow-mini-item">
                        <div class="flow-mini-label">KOSPI200 선물 외국인</div>
                        <div class="flow-mini-value">-</div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
    </div>


</div>


<script>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.choice-btn');
    if (!btn) return;

    const targetName = btn.dataset.target;
    const value = btn.dataset.value;
    const hidden = document.getElementById(targetName);

    if (!hidden) return;

    hidden.value = value;

    document.querySelectorAll('.choice-btn[data-target="' + targetName + '"]').forEach(function(b) {
        b.classList.toggle('active', b === btn);
    });
});
</script>

<script>
const dailyRows = <?= json_encode($futuresDaily, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
const min5Rows  = <?= json_encode($futures5m, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;

function tsDate(v){
    const p=String(v).split('-').map(Number);
    return Date.UTC(p[0],p[1]-1,p[2]);
}
function tsTime(d,t){
    const dp=String(d).split('-').map(Number);
    const tp=String(t).split(':').map(Number);
    return Date.UTC(dp[0],dp[1]-1,dp[2],tp[0]||0,tp[1]||0,tp[2]||0);
}
function candleData(rows, intraday=false){
    return rows.map(r => [
        intraday ? tsTime(r.date,r.time) : tsDate(r.date),
        Number(r.open),Number(r.high),Number(r.low),Number(r.close)
    ]);
}
function lineData(rows,key,intraday=false){
    return rows.filter(r=>r[key]!==null && r[key]!=='').map(r=>[
        intraday ? tsTime(r.date,r.time) : tsDate(r.date), Number(r[key])
    ]);
}
function makeChart(id, rows, intraday){
    if(!rows.length){
        document.getElementById(id).innerHTML='<div class="empty" style="padding-top:90px">데이터 없음</div>';
        return;
    }
    Highcharts.stockChart(id,{
        chart:{
            spacing:[8,8,8,8],
            backgroundColor:'#ffffff',
            plotBackgroundColor:'#ffffff'
        },
        rangeSelector:{enabled:false},
        navigator:{enabled:false},
        scrollbar:{enabled:false},
        credits:{enabled:false},
        legend:{
            enabled:true,
            align:'center',
            verticalAlign:'top',
            itemStyle:{fontSize:'10px',color:'#334155'},
            itemHoverStyle:{color:'#111827'}
        },
        xAxis:{
            type:'datetime',
            lineColor:'#cbd5e1',
            tickColor:'#cbd5e1',
            labels:{style:{color:'#475569'}}
        },
        yAxis:{
            opposite:true,
            title:{text:null},
            gridLineColor:'#e5e7eb',
            labels:{style:{color:'#475569'}}
        },
        tooltip:{split:false,shared:true},
        plotOptions:{series:{dataGrouping:{enabled:false}}},
        series:[
            {type:'candlestick',name:'가격',data:candleData(rows,intraday),color:'#2563eb',upColor:'#dc2626',lineColor:'#2563eb',upLineColor:'#dc2626'},
            {type:'line',name:'5',data:lineData(rows,'sma_5',intraday),lineWidth:1.2,color:'#d97706',marker:{enabled:false}},
            {type:'line',name:'20',data:lineData(rows,'sma_20',intraday),lineWidth:1.2,color:'#7c3aed',marker:{enabled:false}}
        ]
    });
}
makeChart('futuresDailyChart',dailyRows,false);
makeChart('futures5mChart',min5Rows,true);
</script>

</body>
</html>
