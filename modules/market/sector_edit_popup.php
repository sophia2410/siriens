<?php
require($_SERVER['DOCUMENT_ROOT']."/modules/common/database.php");

// 요청된 섹터, 연도, 월 정보를 가져옵니다.
$sector_group = isset($_GET['sector_group']) ? $_GET['sector_group'] : '';
$reportYear = isset($_GET['year']) ? $_GET['year'] : date('Y');  // 기본값은 현재 연도
$reportMonth = isset($_GET['month']) ? $_GET['month'] : '';  // 월 정보는 있을 수도 있고 없을 수도 있음

// 연도 및 월에 따른 startDate와 endDate 처리
if (!empty($reportMonth)) {
    // 월이 넘어온 경우 해당 월의 첫날과 마지막 날 계산
    $startDate = date('Y-m-d', strtotime($reportMonth . '-01'));  // 해당 월의 첫날

    // 해당 월이 현재 달인지 여부를 확인
    $currentMonth = date('Y-m');
    if ($reportMonth === $currentMonth) {
        // 현재 월인 경우 오늘 날짜를 종료일로 사용
        $endDate = date('Y-m-d'); // 오늘 날짜
    } else {
        // 과거 월인 경우 그 달의 마지막 날을 종료일로 사용
        $endDate = date('Y-m-t', strtotime($reportMonth . '-01')); // 해당 월의 마지막 날
    }
} else {
    // 연도만 넘어온 경우 해당 연도의 첫날과 마지막 날 계산
    $startDate = $reportYear . '-01-01'; // 해당 연도의 1월 1일
    $endDate = $reportYear . '-12-31';   // 해당 연도의 12월 31일
}

if ($sector_group === '기타') {
    // 기타 섹터를 눌렀을 때 stock_sector에 등록되지 않은 종목과 이미 기타로 등록된 종목 조회
    $query = "
        SELECT ks.code, ks.name, IFNULL(ss.sector, '') AS sector, IFNULL(ss.sector_group, '') AS sector_group
        FROM xraytick_summary ks
        LEFT JOIN stock_sector ss ON ks.code = ss.code
        WHERE (ss.sector_group IS NULL OR ss.sector_group = '') -- stock_sector에 없거나 기타로 등록된 종목
        AND ks.tot_amt >= 300000000
        AND ks.date BETWEEN ? AND ?
        GROUP BY ks.code, ks.name
        HAVING COUNT(DISTINCT ks.date) >= 6
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('ss', $startDate, $endDate); // 10일 전 시작일과 종료일 바인딩
} else {
    // 섹터가 존재하는 경우의 조회
    $query = "
        SELECT s.code, s.name, ss.sector , ss.sector_group 
        FROM stock_sector ss
        JOIN stock s ON ss.code = s.code AND s.last_yn = 'Y'
        JOIN (
            SELECT ks.code
            FROM xraytick_summary ks
            WHERE ks.tot_amt >= 300000000
            AND ks.date BETWEEN ? AND ?
            GROUP BY ks.code, ks.name
            HAVING COUNT(DISTINCT ks.date) >= 6
        ) filtered_stocks ON filtered_stocks.code = s.code
        WHERE ss.sector_group = ?
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('sss', $startDate, $endDate, $sector_group); // 10일 전 시작일과 종료일, 섹터 바인딩
}
// echo "<pre>$query</pre>";
$stmt->execute();
$result = $stmt->get_result();

$stocks = [];
while ($row = $result->fetch_assoc()) {
    $stocks[] = [
        'code' => $row['code'],
        'name' => $row['name'],
        'current_sector' => $row['sector'],
        'current_sector_group' => $row['sector_group']  // 섹터 그룹 정보 추가
    ];
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>섹터 수정</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
        }
        input[type="text"] {
            width: 100%;
            box-sizing: border-box;
        }
        button {
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

<h3>섹터 수정: <?php echo htmlspecialchars($sector_group); ?></h3>

<form id="sectorEditForm">
    <table>
        <thead>
            <tr>
                <th>종목명</th>
                <th>현재 섹터</th>
                <th>현재 섹터그룹</th>
                <th>새 섹터</th>
                <th>새 섹터그룹</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stocks as $stock) { ?>
            <tr>
                <td><?php echo htmlspecialchars($stock['name']); ?></td>
                <td><?php echo htmlspecialchars($stock['current_sector']); ?></td>
                <td><?php echo htmlspecialchars($stock['current_sector_group']); ?></td>
                <td>
                    <input type="text" value="<?php echo htmlspecialchars($stock['current_sector']); ?>" name="new_sectors[<?php echo htmlspecialchars($stock['code']); ?>]">
                </td>
                <td>
                    <input type="text" value="<?php echo htmlspecialchars($stock['current_sector_group']); ?>" name="new_sector_groups[<?php echo htmlspecialchars($stock['code']); ?>]">
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>

    <button type="submit">섹터 수정 저장</button>
</form>

<script>
document.getElementById('sectorEditForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    console.log('전송할 데이터:', data); // 전송할 데이터를 확인

    fetch('sector_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('섹터 수정이 완료되었습니다.');
            window.opener.location.reload(); // 부모 창을 새로고침
            window.close(); // 팝업 닫기
        } else {
            console.error('서버 오류:', data); // 서버에서 반환된 오류 출력
            alert('오류가 발생했습니다.');
        }
    })
    .catch(error => {
        console.error('전송 오류:', error); // 전송 중 발생한 오류 출력
        alert('오류가 발생했습니다.');
    });
});
</script>

</body>
</html>
