<?php
$pageTitle = "체크리스트 관리"; 
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_header_sub.php");

$dateParam = $_GET['date'] ?? date('Y-m-d');

// 주간 시작일과 종료일 계산 (월요일 ~ 금요일)
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($dateParam)));
$weekEnd = date('Y-m-d', strtotime('friday this week', strtotime($dateParam)));

// 이전 주와 다음 주로 이동하는 기능
$prevWeek = date('Y-m-d', strtotime('-1 week', strtotime($dateParam)));
$nextWeek = date('Y-m-d', strtotime('+1 week', strtotime($dateParam)));

// daily checklist 불러오기 (해당 주간의 일별 체크리스트)
$dailyQuery = $mysqli->prepare("
    SELECT c.checklist_id, c.checklist_title, GROUP_CONCAT(cr.check_date) as check_dates, GROUP_CONCAT(cr.status) as statuses
    FROM checklists c
    LEFT JOIN checklist_records cr ON c.checklist_id = cr.checklist_id AND cr.check_date BETWEEN ? AND ?
    WHERE c.checklist_type = 'daily'
    GROUP BY c.checklist_id
    ORDER BY c.display_order ASC
");
$dailyQuery->bind_param('ss', $weekStart, $weekEnd);
$dailyQuery->execute();
$dailyChecklists = $dailyQuery->get_result();


// 주간 체크리스트 불러오기
$weeklyQuery = $mysqli->prepare("
    SELECT c.checklist_id, c.checklist_title, cr.status
    FROM checklists c
    LEFT JOIN checklist_records cr ON c.checklist_id = cr.checklist_id AND cr.check_date = ?
    WHERE c.checklist_type = 'weekly'
    ORDER BY c.display_order ASC
");
$weeklyQuery->bind_param('s', $weekEnd); // 주간 체크리스트는 주간 종료일 기준으로 확인
$weeklyQuery->execute();
$weeklyChecklists = $weeklyQuery->get_result();
?>

<head>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        button {
            padding: 10px 20px;
            margin-top: 10px;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .completed {
            color: green;
            font-weight: bold;
        }
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        /* 팝업 버튼 스타일 수정 */
        .popup-button {
            background-color: #5cb85c;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 10px auto;
            display: block;
            cursor: pointer;
        }
        /* 레이아웃 조정 */
        #container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .checklist-container {
            display: flex;
            justify-content: space-between;
            width: 100%;
            max-width: 1200px;
        }
        .checklist-section {
            flex: 1;
            margin-right: 20px;
        }
        .checklist-section:last-child {
            margin-right: 0;
        }
    </style>
</head>

<body>
<div>
    <h2>체크리스트 관리 - <?= htmlspecialchars($dateParam); ?></h2>

    <div class="navigation-buttons">
        <a href="checklist_task_register.php?date=<?= $prevWeek; ?>">
            <button>이전 주</button>
        </a>
        <a href="checklist_task_register.php?date=<?= $nextWeek; ?>">
            <button>다음 주</button>
        </a>
        <button class="popup-button" onclick="openChecklistItemPopup()">체크리스트 항목 등록</button>
    </div>


    <script>
        function openChecklistItemPopup() {
            window.open('checklist_item_register.php', '체크리스트 항목 등록', 'width=1000,height=600');
        }
    </script>

    <!-- 체크리스트 관리 화면 -->
    <div class="checklist-container">
        <!-- 일별 체크리스트 -->
        <div class="checklist-section">
            <h3>일별 체크리스트 (<?= $weekStart; ?> ~ <?= $weekEnd; ?>)</h3>
            <form action="checklist_process.php" method="POST">
                <input type="hidden" name="week_start" value="<?= $weekStart; ?>">
                <input type="hidden" name="week_end" value="<?= $weekEnd; ?>">
                <table>
                    <thead>
                        <tr>
                            <th>체크리스트</th>
                            <?php
                            // 월요일부터 금요일까지의 날짜 헤더 출력
                            for ($i = 0; $i < 5; $i++) {
                                $currentDate = date('Y-m-d', strtotime($weekStart . " + $i days"));
                                echo "<th>$currentDate<br>(" . date('D', strtotime($currentDate)) . ")</th>";
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($daily = $dailyChecklists->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($daily['checklist_title']); ?></td>
                                <?php 
                                // 상태 및 날짜 배열로 분리
                                $dates = explode(',', $daily['check_dates']);
                                $statuses = explode(',', $daily['statuses']);
                                
                                for ($i = 0; $i < 5; $i++): 
                                    $currentDate = date('Y-m-d', strtotime($weekStart . " + $i days"));
                                    // 해당 날짜에 수행 여부 확인
                                    $index = array_search($currentDate, $dates);
                                    $checked = ($index !== false && $statuses[$index] === 'completed') ? 'checked' : '';
                                ?>
                                    <td>
                                        <input type="checkbox" name="status[<?= $daily['checklist_id']; ?>][<?= $currentDate; ?>]" <?= $checked; ?>>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <button type="submit">수행 상태 저장</button>
            </form>
        </div>

        <!-- 주간 체크리스트 -->
        <div class="checklist-section">
            <h3>주간 체크리스트</h3>
            <form action="checklist_process.php" method="POST">
                <input type="hidden" name="week_end" value="<?= $weekEnd; ?>">
                <table>
                    <thead>
                        <tr>
                            <th>체크리스트</th>
                            <th>완료 여부</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($weekly = $weeklyChecklists->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($weekly['checklist_title']); ?></td>
                                <td>
                                    <input type="checkbox" name="weekly_status[<?= $weekly['checklist_id']; ?>]" <?= ($weekly['status'] === 'completed') ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <button type="submit">주간 체크리스트 저장</button>
            </form>
        </div>
    </div>
</div>

<?php require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php"); ?>
</body>