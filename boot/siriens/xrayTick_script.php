<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");

// // post 방식으로 넘어온 변수 확인
// foreach($_POST as $key=>$val){
//     echo "$key =>  $val \n";
// }
$response = ['success' => false, 'message' => 'Invalid action'];

if(isset($_POST['proc_fg'])) {
	if($_POST['proc_fg'] == 'CS') {// 코멘트 저장

		$tot_cnt = $_POST['tot_cnt'];
		$comment_date = $_POST['today'];

		// 종목수만큼 돌면서 종목 코멘트 등록
		for($i=0; $i<$tot_cnt; $i++){
			$code = 'code'.$i;
			$name = 'name'.$i;
			$comment = 'comment'.$i;
			$pick    = 'pick_yn'.$i;

			$pick_yn = (isset($_POST[$pick])) ? $_POST[$pick] : "N";

			// 변경이나 삭제 반영을 위해 우선 지우고 다시 등록 처리
			$qry = "DELETE FROM kiwoom_xtay_tick_comments WHERE comment_date = '$comment_date' AND code = '{$_POST[$code]}'";
			echo $qry."<br><br>";
			$mysqli->query($qry);

			// 등록 정보가 있는 경우만 저장
			if($pick_yn == 'Y' || $_POST[$comment] != '') {
				$qry = "INSERT INTO kiwoom_xtay_tick_comments (code, name, pick_yn, comment, comment_date) VALUES ('{$_POST[$code]}', '{$_POST[$name]}', '{$pick_yn}', '{$_POST[$comment]}', '{$comment_date}')";
				echo $qry."<br><br>";
				$mysqli->query($qry);
			}
		}
        $response = ['success' => true, 'message' => 'Comments saved successfully'];
	} else if($_POST['proc_fg'] == 'SC') { // 코멘트 저장 로직 (단일 상품)
        $code = $_POST['code'];
        $name = $_POST['name'];
        $today = $_POST['today'];
        $comment = $_POST['comment'];
        $pick_yn = isset($_POST['pick_yn']) ? 'Y' : 'N';

        error_log("Deleting comment for $code on $today");

        // 기존 코멘트 삭제
        $qry = "DELETE FROM kiwoom_xtay_tick_comments WHERE comment_date = '$today' AND code = '$code'";
        if (!$mysqli->query($qry)) {
            $response = ['success' => false, 'message' => 'Error deleting comment: ' . $mysqli->error];
        } else {
            error_log("Inserting new comment for $code");
            $qry = "INSERT INTO kiwoom_xtay_tick_comments (code, name, pick_yn, comment, comment_date) VALUES ('$code', '$name', '$pick_yn', '$comment', '$today')";
            if ($mysqli->query($qry)) {
                $response = ['success' => true, 'message' => 'Comment saved successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Error saving comment: ' . $mysqli->error];
            }
        }
    } elseif ($_POST['proc_fg'] == 'DC') { // 코멘트 삭제
        $code = $_POST['code'];
        $today = $_POST['today'];

        $qry = "DELETE FROM kiwoom_xtay_tick_comments WHERE comment_date = '$today' AND code = '$code'";
        if ($mysqli->query($qry)) {
            $response = ['success' => true, 'message' => 'Comment deleted successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Error deleting comment: ' . $mysqli->error];
        }
    } else if ($_POST['proc_fg'] == 'PZS') { // 가격존 저장 로직
        $code = $_POST['code'];
        $name = $_POST['name'];
        $zone_type = $_POST['zone_type'];
        $start_price = $_POST['start_price'];
        $end_price = ($_POST['end_price'] == '') ? 'NULL' : $_POST['end_price'];

        error_log("Saving price zone for $code");

        // 키가 같으면 업데이트
        $qry = "INSERT INTO stock_price_zone (code, name, zone_type, start_price, end_price) 
                VALUES ('$code', '$name', '$zone_type', $start_price, $end_price)
                ON DUPLICATE KEY UPDATE end_price = VALUES(end_price)";
        if ($mysqli->query($qry)) {
            $response = ['success' => true, 'message' => 'Price zone saved successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Error saving price zone: ' . $mysqli->error];
        }
    } elseif ($_POST['proc_fg'] == 'DPZS') { // 가격존 삭제
        $code = $_POST['code'];
        $zone_type = $_POST['zone_type'];
        $start_price = $_POST['start_price'];

        $qry = "DELETE FROM stock_price_zone WHERE code = '$code' AND zone_type = '$zone_type' AND start_price = '$start_price'";
        if ($mysqli->query($qry)) {
            $response = ['success' => true, 'message' => 'Price zone deleted successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Error deleting price zone: ' . $mysqli->error];
        }
    }else if ($_POST['proc_fg'] == 'AVG_AMT') {
        $code = $_POST['code'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
    
        $query = "
            SELECT AVG(avg_amt) as average_amount
            FROM kiwoom_xray_tick_summary
            WHERE code = '$code'
            AND date BETWEEN '$start_date' AND '$end_date'
        ";

        $query = "
            SELECT ROUND(SUM(current_price * volume) / SUM(volume),0) as average_amount
            FROM kiwoom_xray_tick_executions
            WHERE code = '$code'
            AND date BETWEEN '$start_date' AND '$end_date'
        ";

        $result = $mysqli->query($query);
        if ($row = $result->fetch_assoc()) {
            $average_amount = (float)$row['average_amount'];
    
            // 상승 비율에 따른 금액 계산
            $percentage_changes = [
                '10%' => round($average_amount * 1.10),
                '20%' => round($average_amount * 1.20),
                '30%' => round($average_amount * 1.30)
            ];
    
            echo json_encode(['success' => true, 'average_amount' => round($average_amount), 'percentage_changes' => $percentage_changes]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No data found']);
        }
        exit;
    }
}
$mysqli->close();

echo json_encode($response);
?>