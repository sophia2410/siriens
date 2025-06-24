<?php
$pageTitle = "BB-RSI-EMA 전략 관리";
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/futures_header.php";

// 등록/수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['strategy_submit'])) {
    $id = intval($_POST['id']);
    $name = $mysqli->real_escape_string($_POST['name']);
    $bb_level = $_POST['bb_level'] ? "'" . $mysqli->real_escape_string($_POST['bb_level']) . "'" : "NULL";
    $rsi_range = $_POST['rsi_range'] ? "'" . $mysqli->real_escape_string($_POST['rsi_range']) . "'" : "NULL";
    $ema_cross = $_POST['ema_cross'] ? "'" . $mysqli->real_escape_string($_POST['ema_cross']) . "'" : "NULL";
    $expected_direction = $mysqli->real_escape_string($_POST['expected_direction']);
    $description = $mysqli->real_escape_string($_POST['description']);

    if ($id > 0) {
        $sql = "
            UPDATE futures_bb_rsi_strategy
            SET name = '$name', bb_level = $bb_level, rsi_range = $rsi_range,
                ema_cross = $ema_cross, expected_direction = '$expected_direction',
                description = '$description'
            WHERE id = $id
        ";
        $mysqli->query($sql);
        $mysqli->query("DELETE FROM futures_bb_rsi_strategy_conditions WHERE strategy_id = $id");
        $strategy_id = $id;
    } else {
        $sql = "
            INSERT INTO futures_bb_rsi_strategy (name, bb_level, rsi_range, ema_cross, expected_direction, description)
            VALUES ('$name', $bb_level, $rsi_range, $ema_cross, '$expected_direction', '$description')
        ";
        $mysqli->query($sql);
        $strategy_id = $mysqli->insert_id;
    }

    if (!empty($_POST['feature_name'])) {
        foreach ($_POST['feature_name'] as $i => $fname) {
            if (!$fname) continue;
            $fname = $mysqli->real_escape_string($fname);
            $op = $mysqli->real_escape_string($_POST['operator'][$i]);
            $v1 = floatval($_POST['value1'][$i]);
            $v2 = ($op === 'BETWEEN') ? floatval($_POST['value2'][$i]) : 'NULL';
            $gid = isset($_POST['group_id'][$i]) ? intval($_POST['group_id'][$i]) : 1;

            $mysqli->query("INSERT INTO futures_bb_rsi_strategy_conditions (strategy_id, feature_name, operator, value1, value2, group_id)
                VALUES ($strategy_id, '$fname', '$op', $v1, $v2, $gid)");
        }
    }

    header("Location: futures_bb_rsi_strategy.php");
    exit;
}

// 삭제 처리
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $mysqli->query("DELETE FROM futures_bb_rsi_strategy WHERE id = $id");
    $mysqli->query("DELETE FROM futures_bb_rsi_strategy_conditions WHERE strategy_id = $id");
    header("Location: futures_bb_rsi_strategy.php");
    exit;
}

$strategyResult = $mysqli->query("SELECT * FROM futures_bb_rsi_strategy ORDER BY id DESC");
?>

<h2>📌 전략 등록</h2>
<form method="post">
    <input type="hidden" name="id" id="strategy-id" value="0">
    <div>전략명: <input type="text" name="name" id="strategy-name" required></div>
    <div>BB:
        <select name="bb_level" id="bb_level">
            <option value="">(무관)</option>
            <?php foreach (["상단 위","상단 근처","중심선 위","중심선 근처","중심선 아래","하단 근처","하단 아래"] as $opt)
                echo "<option value=\"$opt\">$opt</option>"; ?>
        </select>
    </div>
    <div>RSI:
        <select name="rsi_range" id="rsi_range">
            <option value="">(무관)</option>
            <?php foreach (["과열","강세","중립","과매도"] as $opt)
                echo "<option value=\"$opt\">$opt</option>"; ?>
        </select>
    </div>
    <div>EMA:
        <select name="ema_cross" id="ema_cross">
            <option value="">(무관)</option>
            <option value="정배열">정배열</option>
            <option value="역배열">역배열</option>
        </select>
    </div>
    <div>예상 방향:
        <select name="expected_direction" id="expected_direction">
            <option value="상승">상승</option>
            <option value="하락">하락</option>
        </select>
    </div>
    <div>설명:<br>
        <textarea name="description" id="strategy-desc" rows="2" cols="50"></textarea>
    </div>

    <h4>📊 Feature 조건</h4>
    <div id="feature-wrap">
        <div>
            <input name="feature_name[]" placeholder="예: bb_width">
            <select name="operator[]">
                <option value=">">></option>
                <option value=">=">>=</option>
                <option value="<"><</option>
                <option value="<="><=</option>
                <option value="=">=</option>
                <option value="BETWEEN">BETWEEN</option>
            </select>
            <input name="value1[]" size="5" placeholder="값1">
            <input name="value2[]" size="5" placeholder="값2">
            <input name="group_id[]" size="3" value="1" placeholder="group">
        </div>
    </div>
    <button type="button" onclick="addFeature()">+ 조건 추가</button><br><br>
    <button type="submit" name="strategy_submit">✅ 저장</button>
</form>

<hr>

<h3>📄 등록 전략 목록</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>ID</th><th>전략명</th><th>BB</th><th>RSI</th><th>EMA</th><th>방향</th><th>조건수</th><th>설명</th><th>수정</th><th>삭제</th>
    </tr>
<?php while ($row = $strategyResult->fetch_assoc()):
    $sid = $row['id'];
    $cond = $mysqli->query("SELECT COUNT(*) AS cnt FROM futures_bb_rsi_strategy_conditions WHERE strategy_id = $sid");
    $cnt = $cond->fetch_assoc()['cnt'];

    $cond_sql = "SELECT feature_name, operator, value1, value2, group_id FROM futures_bb_rsi_strategy_conditions WHERE strategy_id = $sid";
    $cond_result = $mysqli->query($cond_sql);
    $conditions = [];
    $cond_text = '';
    while ($c = $cond_result->fetch_assoc()) {
        $conditions[] = $c;
        $v1 = $c['value1'];
        $v2 = $c['operator'] === 'BETWEEN' ? ' ~ ' . $c['value2'] : '';
        $cond_text .= "<li>[G{$c['group_id']}] {$c['feature_name']} {$c['operator']} {$v1}{$v2}</li>";
    }
?>
    <tr>
        <td><?= $sid ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= $row['bb_level'] ?></td>
        <td><?= $row['rsi_range'] ?></td>
        <td><?= $row['ema_cross'] ?></td>
        <td><?= $row['expected_direction'] ?></td>
        <td><?= $cnt ?></td>
        <td><?= htmlspecialchars($row['description']) ?></td>
        <td><button onclick='loadStrategy(<?= json_encode($row) ?>, <?= json_encode($conditions) ?>)'>수정</button></td>
        <td><a href="?delete=<?= $sid ?>" onclick="return confirm('정말 삭제하시겠습니까?')">삭제</a></td>
    </tr>
    <tr><td colspan="10" style="text-align:left;"><ul><?= $cond_text ?></ul></td></tr>
<?php endwhile; ?>
</table>

<script>
function addFeature() {
    const div = document.createElement('div');
    div.innerHTML = `
        <input name="feature_name[]" placeholder="예: bb_width">
        <select name="operator[]">
            <option value=">">></option>
            <option value=">=">>=</option>
            <option value="<"><</option>
            <option value="<="><=</option>
            <option value="=">=</option>
            <option value="BETWEEN">BETWEEN</option>
        </select>
        <input name="value1[]" size="5" placeholder="값1">
        <input name="value2[]" size="5" placeholder="값2">
        <input name="group_id[]" size="3" value="1" placeholder="group">
    `;
    document.getElementById('feature-wrap').appendChild(div);
}

function loadStrategy(data, conds) {
    document.getElementById('strategy-id').value = data.id;
    document.getElementById('strategy-name').value = data.name;
    document.getElementById('bb_level').value = data.bb_level;
    document.getElementById('rsi_range').value = data.rsi_range;
    document.getElementById('ema_cross').value = data.ema_cross;
    document.getElementById('expected_direction').value = data.expected_direction;
    document.getElementById('strategy-desc').value = data.description;

    const wrap = document.getElementById('feature-wrap');
    wrap.innerHTML = '';
    conds.forEach(c => {
        const div = document.createElement('div');
        div.innerHTML = `
            <input name="feature_name[]" value="${c.feature_name}">
            <select name="operator[]">
                <option value=">">></option>
                <option value=">=">>=</option>
                <option value="<"><</option>
                <option value="<="><=</option>
                <option value="=">=</option>
                <option value="BETWEEN">BETWEEN</option>
            </select>
            <input name="value1[]" size="5" value="${c.value1}">
            <input name="value2[]" size="5" value="${c.value2 || ''}">
            <input name="group_id[]" size="3" value="${c.group_id || 1}" placeholder="group">
        `;
        wrap.appendChild(div);
        div.querySelector('select').value = c.operator;
    });
}
</script>
