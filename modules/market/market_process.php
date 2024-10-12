<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

if ($_GET['action'] == 'save_report') {
    if (isset($_POST['report_date']) && isset($_POST['market_overview']) && isset($_POST['us_market_overview']) && isset($_POST['other_market_overview']) && isset($_POST['market_review']) && isset($_POST['sophia_review'])) {
        $report_date = $_POST['report_date'];
        $market_overview = $_POST['market_overview'];
        $us_market_overview = $_POST['us_market_overview'];
        $other_market_overview = $_POST['other_market_overview'];
        $market_review = $_POST['market_review'];
        $sophia_review = $_POST['sophia_review'];
    
        $check_query = "SELECT COUNT(*) FROM market_report WHERE date = ?";
        $stmt = $mysqli->prepare($check_query);
        $stmt->bind_param('s', $report_date);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
    
        if ($count > 0) {
            // UPDATE
            $update_query = "UPDATE market_report SET market_overview = ?, us_market_overview = ?, other_market_overview = ?, market_review = ?, sophia_review = ? WHERE date = ?";
            $stmt = $mysqli->prepare($update_query);
            if ($stmt) {
                $stmt->bind_param('ssssss', $market_overview, $us_market_overview, $other_market_overview, $market_review, $sophia_review, $report_date);
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
            $insert_query = "INSERT INTO market_report (date, market_overview, us_market_overview, other_market_overview, market_review, sophia_review) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($insert_query);
            if ($stmt) {
                $stmt->bind_param('ssssss', $report_date, $market_overview, $us_market_overview, $other_market_overview, $market_review, $sophia_review);
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