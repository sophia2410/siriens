<?php
$pageTitle = "체크리스트 항목 관리"; 
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header_sub.php");

// 체크리스트 항목 조회 (일별, 주별로 나누어 정렬)
$dailyChecklistsQuery = $mysqli->prepare("SELECT * FROM checklists WHERE checklist_type = 'daily' ORDER BY display_order ASC");
$dailyChecklistsQuery->execute();
$dailyChecklists = $dailyChecklistsQuery->get_result();

$weeklyChecklistsQuery = $mysqli->prepare("SELECT * FROM checklists WHERE checklist_type = 'weekly' ORDER BY display_order ASC");
$weeklyChecklistsQuery->execute();
$weeklyChecklists = $weeklyChecklistsQuery->get_result();

// 수정 모드인 경우
$editMode = isset($_GET['edit_id']);
if ($editMode) {
    $editId = $_GET['edit_id'];
    $editQuery = $mysqli->prepare("SELECT * FROM checklists WHERE checklist_id = ?");
    $editQuery->bind_param('i', $editId);
    $editQuery->execute();
    $checklistToEdit = $editQuery->get_result()->fetch_assoc();
}
?>

<head>
    <style>
        .checklist {
            display: flex;
            justify-content: space-between;
            margin: 20px;
        }
        form, .checklist-section {
            flex: 1;
            padding: 20px;
            background-color: #f7f7f7;
            border-radius: 5px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            margin-right: 20px;
        }
        form input, form select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        form button {
            padding: 10px 20px;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        h3 {
            margin-top: 20px;
        }
    </style>
</head>

<body>
<h2>체크리스트 항목 관리</h2>

<div class="checklist">
    <!-- 항목 등록 및 수정 폼 -->
    <form action="checklist_process.php" method="POST">
        <input type="hidden" name="action" value="<?= $editMode ? 'update' : 'register'; ?>">
        <?php if ($editMode): ?>
            <input type="hidden" name="checklist_id" value="<?= $checklistToEdit['checklist_id']; ?>">
        <?php endif; ?>

        <label for="checklist_title">체크리스트 제목:</label>
        <input type="text" id="checklist_title" name="checklist_title" value="<?= $editMode ? htmlspecialchars($checklistToEdit['checklist_title']) : ''; ?>" required>

        <label for="checklist_type">체크리스트 유형:</label>
        <select id="checklist_type" name="checklist_type" required>
            <option value="daily" <?= $editMode && $checklistToEdit['checklist_type'] == 'daily' ? 'selected' : ''; ?>>일별</option>
            <option value="weekly" <?= $editMode && $checklistToEdit['checklist_type'] == 'weekly' ? 'selected' : ''; ?>>주별</option>
        </select>

        <label for="display_order">조회 순서:</label>
        <input type="number" id="display_order" name="display_order" value="<?= $editMode ? $checklistToEdit['display_order'] : '0'; ?>" required>

        <button type="submit"><?= $editMode ? '수정' : '등록'; ?></button>
        <?php if ($editMode): ?>
            <button type="submit" formaction="checklist_process.php?action=delete&checklist_id=<?= $checklistToEdit['checklist_id']; ?>">삭제</button>
        <?php endif; ?>
    </form>

    <!-- 일별 체크리스트 항목 조회 -->
    <div class="checklist-section">
        <h3>일별 체크리스트</h3>
        <table>
            <thead>
                <tr>
                    <th>제목</th>
                    <th>정렬 순서</th>
                    <th>수정</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($daily = $dailyChecklists->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($daily['checklist_title']); ?></td>
                        <td><?= htmlspecialchars($daily['display_order']); ?></td>
                        <td>
                            <a href="checklist_item_register.php?edit_id=<?= $daily['checklist_id']; ?>">수정</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- 주간 체크리스트 항목 조회 -->
        <h3>주간 체크리스트</h3>
        <table>
            <thead>
                <tr>
                    <th>제목</th>
                    <th>정렬 순서</th>
                    <th>수정</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($weekly = $weeklyChecklists->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($weekly['checklist_title']); ?></td>
                        <td><?= htmlspecialchars($weekly['display_order']); ?></td>
                        <td>
                            <a href="checklist_item_register.php?edit_id=<?= $weekly['checklist_id']; ?>">수정</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"); ?>
</body>
