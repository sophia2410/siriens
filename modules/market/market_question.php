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

function fetch_one($mysqli, $sql, $types = '', $params = []) {
    $stmt = $mysqli->prepare($sql);
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
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
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function short_text($text, $len = 120) {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (mb_strlen($text, 'UTF-8') <= $len) return $text;
    return mb_substr($text, 0, $len, 'UTF-8') . '...';
}

$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!valid_date($selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$statusFilter = $_GET['status'] ?? '관찰중';
if (!in_array($statusFilter, ['전체','관찰중','완료'], true)) {
    $statusFilter = '관찰중';
}

$keyword = trim($_GET['keyword'] ?? '');

// -----------------------------------------------------------------------------
// 저장 / 삭제
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);
    $returnDate = $_POST['return_date'] ?? $selectedDate;
    if (!valid_date($returnDate)) $returnDate = date('Y-m-d');

    if ($action === 'delete') {
        if ($id > 0) {
            $stmt = $mysqli->prepare("DELETE FROM market_question WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: market_question.php?date=' . urlencode($returnDate) . '&status=' . urlencode($statusFilter) . '&deleted=1');
        exit;
    }

    $date = trim($_POST['date'] ?? '');
    $question = trim($_POST['question'] ?? '');
    $hypothesis = trim($_POST['hypothesis'] ?? '');
    $status = trim($_POST['status'] ?? '관찰중');
    $resultDate = trim($_POST['result_date'] ?? '');
    $resultComment = trim($_POST['result_comment'] ?? '');

    if (!valid_date($date)) {
        http_response_code(400);
        exit('질문 등록일이 올바르지 않습니다.');
    }

    if ($question === '') {
        http_response_code(400);
        exit('질문을 입력해주세요.');
    }

    if (!in_array($status, ['관찰중','완료'], true)) {
        $status = '관찰중';
    }

    if ($resultDate !== '' && !valid_date($resultDate)) {
        http_response_code(400);
        exit('결과 확인일이 올바르지 않습니다.');
    }

    if ($id > 0) {
        $stmt = $mysqli->prepare("
            UPDATE market_question
            SET
                date = ?,
                question = ?,
                hypothesis = NULLIF(?, ''),
                result_date = NULLIF(?, ''),
                result_comment = NULLIF(?, ''),
                status = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            'ssssssi',
            $date,
            $question,
            $hypothesis,
            $resultDate,
            $resultComment,
            $status,
            $id
        );
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("
            INSERT INTO market_question
            (
                date,
                question,
                hypothesis,
                result_date,
                result_comment,
                status
            )
            VALUES (
                ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?
            )
        ");
        $stmt->bind_param(
            'ssssss',
            $date,
            $question,
            $hypothesis,
            $resultDate,
            $resultComment,
            $status
        );
        $stmt->execute();
        $stmt->close();
    }

    header('Location: market_question.php?date=' . urlencode($date) . '&status=' . urlencode($statusFilter) . '&saved=1');
    exit;
}

// -----------------------------------------------------------------------------
// 수정 대상
// -----------------------------------------------------------------------------
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;

if ($editId > 0) {
    $editRow = fetch_one(
        $mysqli,
        "SELECT * FROM market_question WHERE id = ?",
        'i',
        [$editId]
    );
}

// -----------------------------------------------------------------------------
// 집계
// -----------------------------------------------------------------------------
$countRow = fetch_one($mysqli, "
    SELECT
        COUNT(*) AS total_cnt,
        SUM(CASE WHEN status = '관찰중' THEN 1 ELSE 0 END) AS watching_cnt,
        SUM(CASE WHEN status = '완료' THEN 1 ELSE 0 END) AS done_cnt
    FROM market_question
");

$totalCnt = (int)($countRow['total_cnt'] ?? 0);
$watchingCnt = (int)($countRow['watching_cnt'] ?? 0);
$doneCnt = (int)($countRow['done_cnt'] ?? 0);

// -----------------------------------------------------------------------------
// 목록
// -----------------------------------------------------------------------------
$where = [];
$types = '';
$params = [];

if ($statusFilter !== '전체') {
    $where[] = "status = ?";
    $types .= 's';
    $params[] = $statusFilter;
}

if ($keyword !== '') {
    $where[] = "(question LIKE ? OR hypothesis LIKE ? OR result_comment LIKE ?)";
    $kw = '%' . $keyword . '%';
    $types .= 'sss';
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$sql = "
    SELECT
        id, date, question, hypothesis,
        result_date, result_comment,
        status, created_at, updated_at
    FROM market_question
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= "
    ORDER BY
        CASE WHEN status = '관찰중' THEN 0 ELSE 1 END,
        date DESC,
        id DESC
    LIMIT 300
";

$rows = fetch_all_rows($mysqli, $sql, $types, $params);

$form = [
    'id' => $editRow['id'] ?? 0,
    'date' => $editRow['date'] ?? $selectedDate,
    'question' => $editRow['question'] ?? '',
    'hypothesis' => $editRow['hypothesis'] ?? '',
    'result_date' => $editRow['result_date'] ?? '',
    'result_comment' => $editRow['result_comment'] ?? '',
    'status' => $editRow['status'] ?? '관찰중',
];

?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>시장 질문 / 관찰 과제</title>
<style>
*{box-sizing:border-box}
body{
    margin:0;
    background:#f3f5f8;
    color:#1f2937;
    font-family:Arial,'Malgun Gothic',sans-serif;
    font-size:14px;
}
a{color:inherit;text-decoration:none}
button,input,textarea,select{font:inherit}
.wrap{max-width:1800px;margin:0 auto;padding:16px 20px}
.topbar{
    background:#fff;
    border:1px solid #dfe3e8;
    border-radius:10px;
    padding:11px 14px;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
}
.title-wrap{display:flex;align-items:center;gap:14px;min-width:0}
.page-title{font-size:20px;font-weight:800;color:#111827;white-space:nowrap}
.summary{display:flex;gap:14px;color:#64748b;font-size:12px;white-space:nowrap}
.summary strong{color:#111827}
.top-actions{display:flex;align-items:center;gap:7px}
.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid #cfd5dc;
    background:#fff;
    color:#334155;
    border-radius:7px;
    padding:8px 12px;
    cursor:pointer;
    white-space:nowrap;
}
.btn.primary{background:#1f2937;color:#fff;border-color:#1f2937}
.btn.danger{color:#b91c1c;border-color:#fecaca;background:#fff}
.layout{
    display:grid;
    grid-template-columns:minmax(420px,.65fr) minmax(0,1.75fr);
    gap:10px;
    align-items:start;
}
.card{
    background:#fff;
    border:1px solid #dfe3e8;
    border-radius:10px;
    overflow:hidden;
}
.card-head{
    padding:9px 12px;
    background:#fafafa;
    border-bottom:1px solid #e5e7eb;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    font-weight:700;
}
.card-body{padding:11px 12px}
.hint{font-size:11px;color:#94a3b8;font-weight:400}
.field{margin-bottom:10px}
.field:last-child{margin-bottom:0}
.label{
    display:flex;
    justify-content:space-between;
    gap:8px;
    margin-bottom:5px;
    color:#475569;
    font-size:12px;
    font-weight:700;
}
input[type=date],input[type=text],select,textarea{
    width:100%;
    border:1px solid #cfd5dc;
    border-radius:7px;
    background:#fff;
    padding:8px 9px;
    color:#1f2937;
}
textarea{resize:vertical;line-height:1.5}
.question-text{min-height:90px}
.hypothesis-text{min-height:88px}
.result-text{min-height:115px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.form-actions{display:flex;justify-content:flex-end;gap:7px;margin-top:11px}
.filterbar{
    display:flex;
    align-items:center;
    gap:7px;
    flex-wrap:wrap;
}
.filterbar input[type=text]{width:260px}
.status-tabs{display:flex;gap:5px}
.status-tab{
    padding:7px 11px;
    border:1px solid #cfd5dc;
    border-radius:18px;
    background:#fff;
    color:#475569;
    font-size:12px;
}
.status-tab.active{
    background:#111827;
    color:#fff;
    border-color:#111827;
}
.saved{font-size:12px;font-weight:700;color:#059669}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th{
    padding:8px 8px;
    background:#f8fafc;
    border-bottom:1px solid #e5e7eb;
    color:#64748b;
    font-size:11px;
    text-align:left;
    white-space:nowrap;
}
td{
    padding:9px 8px;
    border-bottom:1px solid #eef1f4;
    vertical-align:top;
}
tr:last-child td{border-bottom:0}
.col-status{width:70px}
.col-date{width:92px}
.col-question{width:29%}
.col-hypothesis{width:23%}
.col-result{width:29%}
.col-action{width:64px;text-align:center}
.status-badge{
    display:inline-block;
    border-radius:15px;
    padding:4px 8px;
    font-size:11px;
    font-weight:700;
    white-space:nowrap;
}
.status-badge.watching{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.status-badge.done{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.question-main{font-weight:700;color:#111827;line-height:1.45;white-space:pre-line}
.cell-text{color:#475569;font-size:12px;line-height:1.5;white-space:pre-line}
.result-date{font-size:11px;color:#64748b;margin-bottom:4px}
.date-link{color:#2563eb}
.edit-link{color:#2563eb;font-size:12px}
.empty{padding:35px 20px;text-align:center;color:#94a3b8}
.current-edit{
    background:#fffbeb;
    border-bottom:1px solid #fde68a;
    padding:8px 12px;
    color:#92400e;
    font-size:12px;
}
@media(max-width:1150px){
    .layout{grid-template-columns:1fr}
    .topbar{align-items:flex-start;flex-direction:column}
}
</style>
</head>
<body>
<div class="wrap">

    <div class="topbar">
        <div class="title-wrap">
            <div class="page-title">시장 질문 / 관찰 과제</div>
            <div class="summary">
                <span>전체 <strong><?= $totalCnt ?></strong></span>
                <span>관찰중 <strong><?= $watchingCnt ?></strong></span>
                <span>완료 <strong><?= $doneCnt ?></strong></span>
            </div>
        </div>

        <div class="top-actions">
            <a class="btn" href="market_daily_review.php?date=<?= h($selectedDate) ?>">일일 복기로 돌아가기</a>
        </div>
    </div>

    <div class="layout">

        <section class="card">
            <div class="card-head">
                <span><?= $editRow ? '질문 수정 / 결과 기록' : '새 질문 등록' ?></span>
                <?php if ($editRow): ?>
                    <a class="hint" href="market_question.php?date=<?= h($selectedDate) ?>&status=<?= h($statusFilter) ?>">수정 취소</a>
                <?php else: ?>
                    <span class="hint">궁금증이 생긴 순간 바로 기록</span>
                <?php endif; ?>
            </div>

            <?php if ($editRow): ?>
                <div class="current-edit">
                    <?= h($editRow['date']) ?> 질문을 수정하고 있습니다.
                </div>
            <?php endif; ?>

            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
                    <input type="hidden" name="return_date" value="<?= h($selectedDate) ?>">

                    <div class="field">
                        <div class="label">
                            <span>질문 등록일</span>
                            <span class="hint">궁금증이 생긴 날짜</span>
                        </div>
                        <input type="date" name="date" value="<?= h($form['date']) ?>" required>
                    </div>

                    <div class="field">
                        <div class="label">
                            <span>질문</span>
                            <span class="hint">나중에 검증하고 싶은 내용</span>
                        </div>
                        <textarea class="question-text" name="question" required
                            placeholder="예: 외국인이 현물·선물을 동시에 대량 매도한 날 다음날은 추가 하락이 많을까?"><?= h($form['question']) ?></textarea>
                    </div>

                    <div class="field">
                        <div class="label">
                            <span>당시 예상 / 가설</span>
                            <span class="hint">선택 사항</span>
                        </div>
                        <textarea class="hypothesis-text" name="hypothesis"
                            placeholder="예: 매도 규모가 크면 다음날 오전까지 약세가 이어질 가능성이 높다고 예상"><?= h($form['hypothesis']) ?></textarea>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <div class="label"><span>상태</span></div>
                            <select name="status">
                                <option value="관찰중" <?= $form['status'] === '관찰중' ? 'selected' : '' ?>>관찰중</option>
                                <option value="완료" <?= $form['status'] === '완료' ? 'selected' : '' ?>>완료</option>
                            </select>
                        </div>

                        <div class="field">
                            <div class="label"><span>결과 확인일</span></div>
                            <input type="date" name="result_date" value="<?= h($form['result_date']) ?>">
                        </div>
                    </div>

                    <div class="field">
                        <div class="label">
                            <span>실제 결과</span>
                            <span class="hint">확인 후 기록</span>
                        </div>
                        <textarea class="result-text" name="result_comment"
                            placeholder="예: 다음날 갭하락 출발 후 오전 저점 형성, 이후 반등. 현물 매도는 이어졌지만 선물은 매수 전환."><?= h($form['result_comment']) ?></textarea>
                    </div>

                    <div class="form-actions">
                        <?php if ($editRow): ?>
                            <button
                                type="submit"
                                name="action"
                                value="delete"
                                class="btn danger"
                                onclick="return confirm('이 질문을 삭제하시겠습니까?');"
                            >삭제</button>
                        <?php endif; ?>

                        <button type="submit" name="action" value="save" class="btn primary">
                            <?= $editRow ? '수정 저장' : '질문 등록' ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <div class="filterbar">
                    <div class="status-tabs">
                        <?php foreach (['관찰중','완료','전체'] as $st): ?>
                            <a
                                class="status-tab <?= $statusFilter === $st ? 'active' : '' ?>"
                                href="?date=<?= h($selectedDate) ?>&status=<?= h($st) ?><?= $keyword !== '' ? '&keyword=' . urlencode($keyword) : '' ?>"
                            ><?= h($st) ?></a>
                        <?php endforeach; ?>
                    </div>

                    <form method="get" class="filterbar">
                        <input type="hidden" name="date" value="<?= h($selectedDate) ?>">
                        <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
                        <input type="text" name="keyword" value="<?= h($keyword) ?>" placeholder="질문 / 예상 / 결과 검색">
                        <button class="btn" type="submit">검색</button>
                        <?php if ($keyword !== ''): ?>
                            <a class="btn" href="?date=<?= h($selectedDate) ?>&status=<?= h($statusFilter) ?>">초기화</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (isset($_GET['saved'])): ?>
                    <span class="saved">저장되었습니다.</span>
                <?php elseif (isset($_GET['deleted'])): ?>
                    <span class="saved">삭제되었습니다.</span>
                <?php else: ?>
                    <span class="hint">최대 300건 표시</span>
                <?php endif; ?>
            </div>

            <?php if (!$rows): ?>
                <div class="empty">조건에 맞는 질문이 없습니다.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th class="col-status">상태</th>
                            <th class="col-date">등록일</th>
                            <th class="col-question">질문</th>
                            <th class="col-hypothesis">당시 예상 / 가설</th>
                            <th class="col-result">실제 결과</th>
                            <th class="col-action">수정</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <?php if ($row['status'] === '완료'): ?>
                                        <span class="status-badge done">완료</span>
                                    <?php else: ?>
                                        <span class="status-badge watching">관찰중</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="date-link" href="market_daily_review.php?date=<?= h($row['date']) ?>">
                                        <?= h($row['date']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="question-main"><?= h($row['question']) ?></div>
                                </td>
                                <td>
                                    <?php if (trim((string)$row['hypothesis']) !== ''): ?>
                                        <div class="cell-text"><?= h($row['hypothesis']) ?></div>
                                    <?php else: ?>
                                        <span class="hint">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['result_date']): ?>
                                        <div class="result-date"><?= h($row['result_date']) ?></div>
                                    <?php endif; ?>

                                    <?php if (trim((string)$row['result_comment']) !== ''): ?>
                                        <div class="cell-text"><?= h($row['result_comment']) ?></div>
                                    <?php else: ?>
                                        <span class="hint">아직 결과 미기록</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-action">
                                    <a
                                        class="edit-link"
                                        href="?date=<?= h($selectedDate) ?>&status=<?= h($statusFilter) ?>&edit=<?= (int)$row['id'] ?><?= $keyword !== '' ? '&keyword=' . urlencode($keyword) : '' ?>"
                                    >수정</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </div>
</div>
</body>
</html>
