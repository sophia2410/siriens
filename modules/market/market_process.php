<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

if ($_GET['action'] == 'save_report') {
    if (isset($_POST['report_date']) && isset($_POST['market_overview']) && isset($_POST['market_review'])) {

        $report_date = $_POST['report_date'];
        $market_overview = $_POST['market_overview'];
        $market_review = $_POST['market_review'];
        $evening_report_title = trim($_POST['evening_report_title']);
    
        $check_query = "SELECT COUNT(*) FROM market_report WHERE date = ?";
        $stmt = $mysqli->prepare($check_query);
        $stmt->bind_param('s', $report_date);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            // UPDATE
            $update_query = "UPDATE market_report SET market_overview = ?, market_review = ? , evening_report_title = CASE WHEN ? != '' THEN ? ELSE evening_report_title END WHERE date = ?";
            $stmt = $mysqli->prepare($update_query);
            if ($stmt) {
                $stmt->bind_param('sssss', $market_overview, $market_review, $evening_report_title, $evening_report_title, $report_date);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Report updated successfully']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update report']);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to prepare update statement']);
            }
        } else {
            // INSERT
            $insert_query = "INSERT INTO market_report (date, market_overview, market_review, evening_report_title) VALUES (?, ?, ?, ?)";
            $stmt = $mysqli->prepare($insert_query);
            if ($stmt) {
                $stmt->bind_param('ssss', $report_date, $market_overview, $market_review, $evening_report_title);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'New report inserted successfully']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to insert new report']);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to prepare insert statement']);
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    }
}
?>