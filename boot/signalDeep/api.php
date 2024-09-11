<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        case 'get_evening_report_title':
            if (isset($_GET['report_date'])) {
                $report_date = $mysqli->real_escape_string($_GET['report_date']);
                $query = "SELECT evening_report_title FROM market_report WHERE report_date = '$report_date'";
                $result = $mysqli->query($query);

                if ($result && $result->num_rows === 1) {
                    $row = $result->fetch_assoc();
                    echo $row['evening_report_title'];
                } else {
                    echo "No data found";
                }
            }
            break;

        case 'save_comment':
            if (isset($_POST['report_date']) && isset($_POST['comment'])) {
                $report_date = $mysqli->real_escape_string($_POST['report_date']);
                $comment = $mysqli->real_escape_string($_POST['comment']);
                $query = "UPDATE market_report SET market_comment = '$comment' WHERE report_date = '$report_date'";
                if ($mysqli->query($query)) {
                    echo "Comment saved successfully";
                } else {
                    echo "Error saving comment";
                }
            }
            break;

        // Add more cases as needed for other actions
    }
}
?>
