<?php

	// 종목정보
	$code = isset($_GET['code']) ? $_GET['code'] : '';
	$name = isset($_GET['name']) ? $_GET['name'] : '';

	$page_fg = (isset($_GET['page_fg'])) ? $_GET['page_fg'] : '';
	$div_height = ($page_fg == 'popup') ? '320px' : '180px';

	$pageTitle = "실시간 1Month-$name";
	
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
<head>
<style>
	.fixed-height-scrollable {
		height: <?php echo $div_height;?>; /* 고정 높이 설정 */
		overflow-y: scroll; /* 세로 스크롤바 추가 */
		overflow-x: hidden; /* 가로 스크롤바 숨김 */
		border: 1px solid #ccc; /* 선택 사항: 테두리 추가 */
		padding: 10px;
		background-color: #f9f9f9; /* 선택 사항: 배경색 추가 */
	}
	.comment-section, .price-zone-section, .price-zones-list {
		padding: 10px;
		/* border-bottom: 1px solid #ddd; */
	}

	.recent-comment {
		margin-bottom: 10px;
	}

	.form-group {
		margin-bottom: 10px;
		display: flex;
		align-items: center;
	}

	.form-control {
		width: 100%;
		box-sizing: border-box;
	}

	select, input[type="number"], textarea {
		padding: 2px;
		font-size: 14px;
	}

	textarea {
		resize: none;
	}

	.btn {
		cursor: pointer;
		background-color: #007bff;
		color: white;
		border: none;
		padding: 10px;
		border-radius: 5px;
	}

	.btn:hover {
		background-color: #0056b3;
	}
    .delete-btn {
        font-size: 14px;
        margin-left: 5px;
        padding: 0;
        width: 20px; /* 버튼 크기 조정 */
        height: 20px; /* 버튼 크기 조정 */
        background-color: white;
        color: black;
        border: 1px solid red;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .delete-btn:hover {
        background-color: red;
        color: white;
    }
	.result-container {
		display: flex;
		align-items: center;
		margin-left: 10px;
	}
	.result-item {
		margin-right: 10px;
	}
</style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.highcharts.com/stock/highstock.js"></script>
    <script src="https://code.highcharts.com/modules/annotations.js"></script>
</head>

<body>
<?php
if($code == '') {
	echo "<h3>종목선택</h3>";
	$ready = 'N';
}
else {
	// highchart 조회
	echo "<div id='container' data-code='$code' data-name='$name' style='height: 580px; min-width: 310px;'></div>";
    echo "<script src='highchart.js?v=1.0.1'></script>";

	// 공통코드 불러오기
	$status_query = "SELECT cd, nm FROM comm_cd WHERE l_cd = 'HC000' ORDER BY ord_no";
	$status_result = $mysqli->query($status_query);
	$status_options = "<option value=''>선택</option>";
	$comm_codes = [];
	while ($status_row = $status_result->fetch_assoc()) {
		$status_options .= "<option value='". $status_row['cd']."'>".$status_row['nm']."</option>";
		$comm_codes[$status_row['cd']] = $status_row['nm']; // 공통코드 배열에 저장
	}
	
	// 체결빈도 옵션 설정
	$frequency_options = "<option value=''>선택</option>";
	$frequency_levels = ['강', '중', '약'];
	foreach ($frequency_levels as $level) {
		$frequency_options .= "<option value='$level'>$level</option>";
	}

	// 최근 저장된 상태 불러오기
	$current_status_query = "SELECT status_cd, frequency, status_date FROM chart_status WHERE code = '$code' ORDER BY status_date DESC LIMIT 2";
	$current_status_result = $mysqli->query($current_status_query);
	$current_statuses = [];
	while ($current_status_row = $current_status_result->fetch_assoc()) {
		$status_cd = $current_status_row['status_cd'];
		$status_nm = isset($comm_codes[$status_cd]) ? $comm_codes[$status_cd] : $status_cd;
		$frequency = $current_status_row['frequency'];
		$current_statuses[] = ['cd' => $status_cd, 'nm' => $status_nm, 'freq' => $frequency, 'date' => $current_status_row['status_date']];
	}
	?>

	<div class="status-section" style="display: flex; align-items: center; flex-wrap: nowrap;">
		<form id="status-form" style="display: flex; align-items: center;">
			<input type='hidden' name='code' value='<?php echo $code; ?>'>
			<input type='hidden' name='name' value='<?php echo $name; ?>'>
			<input type='hidden' name='proc_fg' value='SS'>
			<div class='form-group' style='display: flex; align-items: center;'>
				<label for='status_cd'>차트 상태:</label>
				<select name='status_cd' id='status_cd' style='margin-left: 10px;'>
					<?php echo $status_options; ?>
				</select>
				<label for='frequency' style='margin-left: 10px;'>체결 빈도:</label>
				<select name='frequency' id='frequency' style='margin-left: 10px;'>
					<?php echo $frequency_options; ?>
				</select>
				<button type='button' class='btn btn-info btn-sm' style='margin-left: 10px;' onclick='saveData("status-form")'>저장</button>
			</div>
		</form>
		<div class='current-status' style="margin-left: 20px; display: flex; align-items: center;">
			<?php foreach ($current_statuses as $status): ?>
				<span style="margin-left: 10px;"><?php echo $status['nm'] ?>(<?php echo $status['freq']; ?>) (<?php echo $status['date'];?>)</span>
				<button type='button' class='delete-btn' style="margin-left: 5px;" onclick='deleteStatus("<?php echo $code; ?>", "<?php echo $status['date']; ?>")'>X</button>
			<?php endforeach; ?>
		</div>
	</div>

	<?php
	// 기간 평균 체결금액 조회
	?>

	<div style="display: flex; align-items: center;">
		<div>
			<label for="start_date_select">기간 평균금액:</label>
			<select id="start_date_select" name="start_date_select">
				<?php
					$query = "SELECT date
								FROM calendar
								WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
								AND   date >= '20240226'
								ORDER BY date
								LIMIT 120";

					$result = $mysqli->query($query);

					$option = "";
					while ($row = $result->fetch_array(MYSQLI_BOTH)) {
						$option .= "<option value='". $row['date']."'>".$row['date']."</option>";
					}
					echo $option;
				?>
			</select>

			<label for="end_date_select"> ~ </label>
			<select id="end_date_select" name="end_date_select">
				<?php
					$result->data_seek(0); // Reset result pointer to the beginning
					while ($row = $result->fetch_array(MYSQLI_BOTH)) {
						$option .= "<option value='". $row['date']."'>".$row['date']."</option>";
					}
					echo $option;
				?>
			</select>

			<button type="button" class="btn" onclick="fetchAverageAmount()">조회</button>

		</div>
		<div id="average_amount" class="result-container"></div>
	</div>

	<script>
	function fetchAverageAmount() {
		var code = $('#container').data('code');
		var startDate = $('#start_date_select').val();
		var endDate = $('#end_date_select').val();

		if (!startDate || !endDate) {
			alert('Please select both start and end dates.');
			return;
		}

		// 날짜를 localStorage에 저장
		localStorage.setItem('start_date', startDate);
		localStorage.setItem('end_date', endDate);

		var requestData = {
			code: code,
			start_date: startDate,
			end_date: endDate,
			proc_fg: 'AVG_AMT'
		};

		$.post('xrayTick_script.php', requestData, function(response) {
			if (response.success) {
				var averageAmount = parseFloat(response.average_amount);
				var percentageChanges = response.percentage_changes;

				$('#average_amount').html(
					'<div class="result-item">Average Amount: ' + averageAmount.toLocaleString() + '</div>' +
					'<div class="result-item">10% : ' + percentageChanges['10%'].toLocaleString() + '</div>' +
					'<div class="result-item">20% : ' + percentageChanges['20%'].toLocaleString() + '</div>' +
					'<div class="result-item">30% : ' + percentageChanges['30%'].toLocaleString() + '</div>'
				);
			} else {
				alert('Error: ' + response.message);
			}
		}, 'json').fail(function(jqXHR, textStatus, errorThrown) {
			console.error('Request failed:', textStatus, errorThrown);
			alert('Error: ' + textStatus + ' - ' + errorThrown);
		});
	}

	$('#start_date_select').on('change', function() {
		var selectedStartDate = $(this).val();
		$('#end_date_select').val(selectedStartDate);
	});


	$(document).ready(function() {
		// localStorage에서 날짜를 불러와 셀렉트 박스에 설정
		var startDate = localStorage.getItem('start_date');
		var endDate = localStorage.getItem('end_date');

		if (startDate) {
			$('#start_date_select').val(startDate);
		}

		if (endDate) {
			$('#end_date_select').val(endDate);
		}
	});
	</script>
	<?php
	//X-RAY 순간체결 거래량
	$query = "	SELECT cal.date, SUBSTR(STR_TO_DATE(cal.date, '%Y%m%d'),6) date_str, xray.close_rate, xray.close_amt, xray.tot_trade_amt, xray.amount, xray.avg_amt, xray.cnt
				FROM calendar cal
				LEFT OUTER JOIN 
					(
						SELECT xr.code, xr.name, xr.date, dp.close_rate close_rate, dp.close close_amt, round(dp.amount/100000000,0) tot_trade_amt, round(xr.tot_amt/100000000,1) amount, xr.avg_amt, xr.tot_cnt cnt
						FROM kiwoom_xray_tick_summary xr
						LEFT OUTER JOIN daily_price dp
						ON dp.date = xr.date
						AND dp.code = xr.code
						WHERE xr.code = '$code'
					) xray
				ON xray.date = cal.date
				WHERE cal.date >= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL -5 MONTH), '%Y%m%d')))
				AND cal.date <= (select max(date) from calendar where date <=(select DATE_FORMAT(DATE_ADD(now(), INTERVAL 0 DAY), '%Y%m%d')))
				AND cal.date >= '20240226'
				ORDER BY cal.date desc
				";
	// 데이터베이스 쿼리 실행 및 결과 저장
	$result = $mysqli->query($query);
	// 주차별 데이터 배열 초기화
	$weekly_data = [];
	// 데이터 분류
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		$date = $row['date'];
		$day_of_week = date('D', strtotime($date));  // 요일 추출 (Mon, Tue, ...)
		$week_number = date('W', strtotime($date));  // 주 번호 추출
		if (!isset($weekly_data[$week_number])) {
			$weekly_data[$week_number] = [
				'Mon' => ['date' => '', 'close_rate' => '', 'avg_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
				'Tue' => ['date' => '', 'close_rate' => '', 'avg_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
				'Wed' => ['date' => '', 'close_rate' => '', 'avg_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
				'Thu' => ['date' => '', 'close_rate' => '', 'avg_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
				'Fri' => ['date' => '', 'close_rate' => '', 'avg_amt' => '', 'tot_trade_amt' => '', 'amount' => '', 'cnt' => ''],
			];
		}
		// 요일별 데이터 저장
		if (isset($weekly_data[$week_number][$day_of_week])) {
			if($page_fg == 'popup')
				$showDt = "<a href=\"javascript:openPopupXrayTick('{$code}', '".$row['date']."')\">";
			else
				$showDt = "<a href=\"javascript:callFrameR('".$date."', '{$code}')\">";
			$weekly_data[$week_number][$day_of_week]['date'] = "<td align=center width=90  style='background-color:#fae4f1;'><b>$showDt". $row['date_str']."</a></b></td>";
			if($row['cnt'] > 0) {
				// 등락률 따라 스타일 적용
				if($row['close_rate'] > 29.5)
					$rate_style = "class='text-danger font-weight-bold'";
				else if($row['close_rate'] > 15)
					$rate_style = "class='text-danger'";
				else
					$rate_style = "";
				// 총 거래대금에 따라 스타일 적용
				if($row['tot_trade_amt'] >= 1000)
					$tot_amt_style = "style='color:#fcb9b2;' class='font-weight-bold'";
				else if($row['tot_trade_amt'] >= 500)
					$tot_amt_style = "style='color:#ffccd5;' class='font-weight-bold'";
				else if($row['tot_trade_amt'] < 9)
					$tot_amt_style = "class='small'";
				else
					$tot_amt_style = "";
				// xray 거래대금에 따라 스타일 적용
				if($row['amount'] >= 500)
					$amt_style = "mark text-danger font-weight-bold h6";
				else if($row['amount'] >= 100)
					$amt_style = "text-danger font-weight-bold h6";
				else if($row['amount'] < 9)
					$amt_style = "small";
				else
					$amt_style = "font-weight-bold";

				$weekly_data[$week_number][$day_of_week]['close_rate'] = "<td align=center $rate_style>". $row['close_rate']."%</td>";
				$weekly_data[$week_number][$day_of_week]['tot_trade_amt'] = "<td align=center $tot_amt_style>". number_format($row['tot_trade_amt'])."</td>";
				$weekly_data[$week_number][$day_of_week]['avg_amt'] = "<td align=center>". number_format($row['avg_amt'])."</td>";
				$weekly_data[$week_number][$day_of_week]['cnt'] = "<td align=right>". number_format($row['cnt'])."</td>";
				$weekly_data[$week_number][$day_of_week]['amount'] = "<td align=right class='{$amt_style}'>". number_format($row['amount'])."</td>";
			} else {
				$weekly_data[$week_number][$day_of_week]['close_rate'] = "<td align=center>&nbsp;</td>";
				$weekly_data[$week_number][$day_of_week]['tot_trade_amt'] = "<td align=center>&nbsp;</td>";
				$weekly_data[$week_number][$day_of_week]['avg_amt'] = "<td align=center>&nbsp;</td>";
				$weekly_data[$week_number][$day_of_week]['cnt'] = "<td align=center>&nbsp;</td>";
				$weekly_data[$week_number][$day_of_week]['amount'] = "<td align=center>&nbsp;</td>";
			}
		}
	}
	// 주차별 데이터 내림차순 정렬 (가장 최근 주가 위로)
	krsort($weekly_data);
	// 주 데이터를 세 개씩 묶어서 처리
	$paired_weeks = array_chunk($weekly_data, 3, true);
	// HTML 출력
	echo "<div class='fixed-height-scrollable'>";
		echo "<table class='table table-sm table-bordered text-dark' style='cellpadding:0px; cellspacing:0px'>";
		foreach ($paired_weeks as $pair) {
			echo "<tr>";  // 날짜 헤더 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['date'] ?? "<th></th>";
				}
			}
			echo "</tr><tr class='small'>";  // 등락률 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['close_rate'] ?? "<td></td>";
				}
			}
			echo "</tr><tr class='small'>";  // 총 거래대금 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['tot_trade_amt'] ?? "<td></td>";
				}
			}
			echo "</tr><tr class='small'>";  // 평균매수가 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['avg_amt'] ?? "<td></td>";
				}
			}
			// echo "</tr><tr>";  // 거래량 행
			// foreach ($pair as $week => $data) {
			// 	foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
			// 		echo $data[$day]['cnt'] ?? "<td></td>";
			// 	}
			// }
			echo "</tr><tr>";  // 거래대금 행
			foreach ($pair as $week => $data) {
				foreach (['Fri', 'Thu', 'Wed', 'Tue', 'Mon'] as $day) {
					echo $data[$day]['amount'] ?? "<td></td>";
				}
			}
			echo "</tr>";
		}
		echo "</table>";
	echo "</div>";
	
	$today = date('Ymd');

    // 등록 코멘트 불러오기
    $query = "SELECT comment, pick_yn, comment_date FROM kiwoom_xtay_tick_comments WHERE code = '$code' AND comment_date = '$today'";
    $result = $mysqli->query($query);
    if ($row = $result->fetch_assoc()) {
        $comment = $row['comment'];
        $pick = ($row['pick_yn'] == 'Y') ? 'checked' : '';
    } else {
        $comment = "";
        $pick    = "";
    }

    // 최근 코멘트를 가져와서 표시하는 부분 추가
    $query = "SELECT comment, pick_yn, comment_date FROM kiwoom_xtay_tick_comments WHERE code = '$code' AND comment_date < '$today' ORDER BY comment_date LIMIT 3";
    $result = $mysqli->query($query);
?>
    <div class="comment-section" style='border-bottom: 1px solid #ddd;'>
        <?php
        while ($row = $result->fetch_assoc()) {
            $pick_yn = ($row['pick_yn'] =='Y') ? "<b><font color=red>PICK</font></b>" : "";
            echo "<div class='recent-comment'>
                <small>작성일: {$row['comment_date']}</small>
                <p>{$pick_yn} {$row['comment']}</p>
				</div>";
        }
        ?>
        <form id="comment-form">
            <input type='hidden' name='code' value='<?php echo $code; ?>'>
            <input type='hidden' name='name' value='<?php echo $name; ?>'>
            <input type='hidden' name='today' value='<?php echo $today; ?>'>
            <input type='hidden' name='proc_fg' value='SC'>
            <div class='form-group' style='display: flex; align-items: center;'>
                <input type='checkbox' name='pick_yn' value='Y' style='margin-right: 10px;' <?php echo $pick; ?>>
                <textarea class='form-control' style='flex: 1; margin-right: 10px;' id='comment' name='comment' rows='2'><?php echo $comment; ?></textarea>
                <button type='button' class='btn btn-info btn-sm' style='margin-left: 10px;' onclick='saveData("comment-form")'>저장</button>
                <button type='button' class='btn btn-danger btn-sm' style='margin-left: 10px;' onclick='deleteData("comment-form")'>삭제</button>
            </div>
        </form>
    </div>

    <div class="price-zone-section">
        <form id="price-zone-form">
            <input type='hidden' name='code' value='<?php echo $code; ?>'>
            <input type='hidden' name='name' value='<?php echo $name; ?>'>
            <input type='hidden' name='proc_fg' value='PZS'>
            <div class='form-group' style='display: flex; align-items: center;'>
                <select name='zone_type' id='zone_type' style='margin-right: 10px;'>
                    <option value='standard'>기준봉</option>
                    <option value='average'>평균값</option>
                    <option value='support'>지지선</option>
                    <option value='resistance'>저항선</option>
                    <option value='range'>박스권</option>
                    <option value='kswing'>K스윙</option>
                </select>
                <input type='number' name='start_price' id='start_price' placeholder='시작 가격' style='margin-right: 10px;'>
                <input type='number' name='end_price' id='end_price' placeholder='종료 가격 (선택)'>
                <button type='button' class='btn btn-info btn-sm' style='margin-left: 10px;' onclick='saveData("price-zone-form")'>저장</button>
            </div>
        </form>
    </div>

	<div class="price-zones-list">
		<?php
		$price_zones_query = "SELECT zone_type, start_price, end_price FROM stock_price_zone WHERE code = '$code'";
		$price_zones_result = $mysqli->query($price_zones_query);
		echo "<div class='price-zone-items' style='display: flex; flex-wrap: wrap;'>";
		while ($zone = $price_zones_result->fetch_assoc()) {
			// zone_type을 한국어로 변환
			switch ($zone['zone_type']) {
				case 'average':
					$zone_type_kr = '평균값';
					break;
				case 'support':
					$zone_type_kr = '지지선';
					break;
				case 'resistance':
					$zone_type_kr = '저항선';
					break;
				case 'range':
					$zone_type_kr = '박스권';
					break;
				case 'standard':
					$zone_type_kr = '기준봉';
					break;
				case 'kswing':
					$zone_type_kr = 'K스윙';
					break;
				default:
					$zone_type_kr = $zone['zone_type'];
			}

			// 가격 형식 설정
			$start_price_formatted = number_format($zone['start_price']);
			$end_price_formatted = $zone['end_price'] ? number_format($zone['end_price']) : '';
			$price_display = $zone['end_price'] ? "{$start_price_formatted}~{$end_price_formatted}" : "{$start_price_formatted}";

			echo "<div class='price-zone-item' style='margin-right: 20px; display: flex; align-items: center;'>";
			echo "<span>{$zone_type_kr}: {$price_display}</span>";
			echo "<button type='button' class='delete-btn' onclick='deletePriceZone(\"{$zone['zone_type']}\", \"{$zone['start_price']}\")'>X</button>";
			echo "</div>";
		}
		echo "</div>";
		?>
	</div>

<?php
}
?>
<script>
function saveData(formId) {
    var formData = $('#' + formId).serialize();
    console.log('Sending data:', formData); // 데이터 확인용 로그
    $.post('xrayTick_script.php', formData, function(response) {
        console.log('Response:', response); // 응답 확인용 로그
        if (response.success) {
            // alert(response.message);
            location.reload();
        } else {
            alert('Error: ' + response.message);
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        console.error('Request failed:', textStatus, errorThrown); // 에러 확인용 로그
        alert('Error: ' + textStatus + ' - ' + errorThrown);
    });
}

function deleteData(formId) {
    var formData = $('#' + formId).serialize();
    console.log('Deleting data:', formData); // 데이터 확인용 로그
    $.post('xrayTick_script.php', formData + '&proc_fg=DC', function(response) {
        console.log('Response:', response); // 응답 확인용 로그
        if (response.success) {
            // alert(response.message);
            location.reload();
        } else {
            alert('Error: ' + response.message);
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        console.error('Request failed:', textStatus, errorThrown); // 에러 확인용 로그
        alert('Error: ' + textStatus + ' - ' + errorThrown);
    });
}

function deletePriceZone(zone_type, start_price) {
    var code = $('#price-zone-form input[name="code"]').val();
    var name = $('#price-zone-form input[name="name"]').val();
    var formData = {
        code: code,
        name: name,
        zone_type: zone_type,
        start_price: start_price,
        proc_fg: 'DPZS'
    };

    console.log('Deleting price zone:', formData); // 데이터 확인용 로그

    $.post('xrayTick_script.php', formData, function(response) {
        console.log('Response:', response); // 응답 확인용 로그
        if (response.success) {
            // alert(response.message);
            location.reload();
        } else {
            alert('Error: ' + response.message);
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        console.error('Request failed:', textStatus, errorThrown); // 에러 확인용 로그
        alert('Error: ' + textStatus + ' - ' + errorThrown);
    });
}

function deleteStatus(code, status_date) {
    var formData = {
        code: code,
        status_date: status_date,
        proc_fg: 'DS'
    };

    console.log('Deleting status:', formData); // 데이터 확인용 로그

    $.post('xrayTick_script.php', formData, function(response) {
        console.log('Response:', response); // 응답 확인용 로그
        if (response.success) {
            // alert(response.message);
            location.reload();
        } else {
            alert('Error: ' + response.message);
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        console.error('Request failed:', textStatus, errorThrown); // 에러 확인용 로그
        alert('Error: ' + textStatus + ' - ' + errorThrown);
    });
}

function callFrameR(date,code) {
    window.parent.viewDetail(date,code);
}

function openPopupXrayTick(code, date) {
    var url = "/boot/common/popup/stock_xray_tick.php?code=" + code + "&date=" + date;
    var width = 550;
    var height = 1500;
    var left = window.innerWidth - width; // 원하는 X 위치
    var top = 0; // 원하는 Y 위치
    var features = "width=" + width + ",height=" + height + ",scrollbars=yes,resizable=yes,left=" + left + ",top=" + top + ",screenX=" + left + ",screenY=" + top;
    var newWindow = window.open(url, "pop", features);
    if (window.focus) {
        newWindow.focus();
    }
}

</script>
</body>
</html>