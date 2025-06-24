<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php");

$date = $_GET['date'] ?? date('Y-m-d');
$dayOfWeek = date('N', strtotime($date)); // 1 (Monday) to 7 (Sunday)

// Determine checklist type (daily for weekdays, weekly for weekends)
$checklistType = ($dayOfWeek <= 5) ? 'daily' : 'weekly';

// Query total tasks
$totalQuery = $mysqli->prepare("SELECT COUNT(*) AS total FROM checklists WHERE checklist_type = ?");
$totalQuery->bind_param("s", $checklistType);
$totalQuery->execute();
$totalResult = $totalQuery->get_result();
$totalTasks = $totalResult->fetch_assoc()['total'] ?? 0;

// Query completed tasks (you may need to adjust this based on your completion tracking logic)
$completedQuery = $mysqli->prepare("
    SELECT COUNT(*) AS completed 
    FROM checklist_status 
    WHERE checklist_date = ? AND checklist_type = ? AND is_completed = 1
");
$completedQuery->bind_param("ss", $date, $checklistType);
$completedQuery->execute();
$completedResult = $completedQuery->get_result();
$completedTasks = $completedResult->fetch_assoc()['completed'] ?? 0;

// Return as JSON
echo json_encode([
    'total' => $totalTasks,
    'completed' => $completedTasks
]);
?>
