<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header_sub.php");
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/tinymce_module.php");

$tradeDate = isset($_GET['trade_date']) ? $_GET['trade_date'] : date('Y-m-d');
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'iframe'; // 기본값은 iframe

// 기본 폼 변수
$journalId   = '';
$tradeMethod = '';
$profitLoss  = '';
$tradeItems  = '';
$comment     = '';

// DB 조회
$tradeDateEsc = $mysqli->real_escape_string($tradeDate);
$query = "
    SELECT 
        id,
        trade_date,
        trade_method,
        profit_loss,
        trade_items,
        UNCOMPRESS(comment) AS comment
    FROM journal_trade
    WHERE trade_date = '$tradeDateEsc'
    LIMIT 1
";
$result = $mysqli->query($query);
if ($row = $result->fetch_assoc()) {
    $journalId   = $row['id'];
    $tradeMethod = $row['trade_method'];
    $profitLoss  = $row['profit_loss'];
    $tradeItems  = $row['trade_items'];
    $comment     = $row['comment'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>매매등록 팝업</title>
    <style>
        /* 전체 화면 높이를 사용하기 위한 기본 세팅 */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: auto;
        }

        /* 메인 컨테이너 */
        #editor_container {
            height: 100%;
            box-sizing: border-box;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #fff;
        }

        h2 {
            margin-top: 0;
            margin-bottom: 15px;
        }

        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .form-row input[type="date"],
        .form-row input[type="text"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
        }

        /* 코멘트 에디터: 높이를 남은 공간에서 최대화하기 위한 예시 */
        #comment {
            width: 100%;
            height: calc(100% - 180px); 
            /* 폼 요소들의 높이를 뺀 값으로 설정(대략값) */
            box-sizing: border-box;
            resize: vertical;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .buttons button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: #fff;
        }
        .save-btn {
            background: #5bc0de;
        }
        .delete-btn {
            background: #d9534f;
        }
    </style>
</head>
<body>
<div id="editor_container">
    <h2>종목 매매 등록</h2>

    <form id="journalForm" action="journal_trade_process.php" method="POST" 
          style="height:100%; display:flex; flex-direction:column;">
         <input type="hidden" name="source" value="<?= htmlspecialchars($mode) ?>">

        <!-- 수정 시 journal_id가 있으면 UPDATE, 없으면 INSERT -->
        <input type="hidden" name="journal_id" value="<?= htmlspecialchars($journalId) ?>">

        <div class="form-row">
            <input type="date" name="trade_date" id="trade_date" 
                   value="<?= htmlspecialchars($tradeDate) ?>" required>
            <input type="text" name="trade_method" id="trade_method" placeholder="매매방식"
                   value="<?= htmlspecialchars($tradeMethod) ?>" required>
            <input type="text" name="profit_loss" id="profit_loss" placeholder="수익/손실"
                   value="<?= htmlspecialchars($profitLoss) ?>" required>
            <input type="text" name="trade_items" id="trade_items" placeholder="종목"
                   value="<?= htmlspecialchars($tradeItems) ?>" required>
        </div>

        <!-- 코멘트 에디터 -->
        <textarea name="comment" id="comment" placeholder="코멘트"><?= htmlspecialchars($comment) ?></textarea>

        <div class="buttons">
            <button type="submit" class="save-btn"><?= $journalId ? '수정하기' : '등록하기' ?></button>
            <?php if (!empty($journalId)): ?>
            <button type="button" class="delete-btn" onclick="deleteJournal()">삭제하기</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php
// TinyMCE 에디터 로드
loadTinyMCE('#comment', 1000);
loadTinyMCEScripts();
?>

<script>
function deleteJournal() {
    if (confirm("정말로 삭제하시겠습니까?")) {
        var source = document.querySelector('input[name="source"]').value;
        var journalId = document.querySelector('input[name="journal_id"]').value;
        var tradeDate = document.getElementById('trade_date').value;
        window.location.href = "journal_trade_process.php?action=delete&journal_id=" + journalId 
            + "&trade_date=" + encodeURIComponent(tradeDate) + "&source=" + source;
    }
}
</script>
</body>
</html>
