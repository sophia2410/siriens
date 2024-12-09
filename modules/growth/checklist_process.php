<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null; // action이 설정되어 있지 않으면 null 할당
    $checklistTitle = $_POST['checklist_title'] ?? null;
    $checklistType = $_POST['checklist_type'] ?? null;
    $displayOrder = $_POST['display_order'] ?? null;

    if ($action === 'register') {
        // 체크리스트 항목 등록 로직
        $stmt = $mysqli->prepare("
            INSERT INTO checklists (checklist_title, checklist_type, display_order) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('ssi', $checklistTitle, $checklistType, $displayOrder);
        $stmt->execute();
        echo "<script>window.location.href='checklist_item_register.php';</script>";
    } elseif ($action === 'update') {
        // 체크리스트 항목 수정 로직
        $checklistId = $_POST['checklist_id'];
        $stmt = $mysqli->prepare("
            UPDATE checklists 
            SET checklist_title = ?, checklist_type = ?, display_order = ? 
            WHERE checklist_id = ?
        ");
        $stmt->bind_param('ssii', $checklistTitle, $checklistType, $displayOrder, $checklistId);
        $stmt->execute();
        echo "<script>window.location.href='checklist_item_register.php';</script>";
    } elseif (isset($_POST['status'])) {
        // 일별 체크리스트 저장 로직
        foreach ($_POST['status'] as $checklistId => $dates) {
            foreach ($dates as $date => $status) {
                $completed = isset($status) ? 'completed' : 'pending';

                $stmt = $mysqli->prepare("
                    INSERT INTO checklist_records (checklist_id, check_date, status)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ");
                $stmt->bind_param('iss', $checklistId, $date, $completed);
                $stmt->execute();
            }
        }
        echo "<script>
                alert('일별 체크리스트 수행 상태가 저장되었습니다.'); 
                window.opener.fetchTodoCount();
                window.location.href='checklist_task_register.php';
             </script>";
    } elseif (isset($_POST['weekly_status'])) {
        // 주간 체크리스트 저장 로직
        $weekEnd = $_POST['week_end'];

        foreach ($_POST['weekly_status'] as $checklistId => $status) {
            $completed = isset($status) ? 'completed' : 'pending';
            $stmt = $mysqli->prepare("
                INSERT INTO checklist_records (checklist_id, check_date, status)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status)
            ");
            $stmt->bind_param('iss', $checklistId, $weekEnd, $completed);
            $stmt->execute();
        }
        echo "<script>alert('주간 체크리스트 수행 상태가 저장되었습니다.'); window.location.href='checklist_task_register.php';</script>";
    }
}

// 삭제 로직
if ($_GET['action'] === 'delete') {
    $checklistId = $_GET['checklist_id'];
    $stmt = $mysqli->prepare("DELETE FROM checklists WHERE checklist_id = ?");
    $stmt->bind_param('i', $checklistId);
    $stmt->execute();
    echo "<script>window.location.href='checklist_item_register.php';</script>";
}
?>
