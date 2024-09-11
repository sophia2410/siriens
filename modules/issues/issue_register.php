<?php
$pageTitle = "이슈 및 테마 등록 및 조회"; // 페이지별 타이틀 설정
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");

require($_SERVER['DOCUMENT_ROOT']."/modules/issues/issue_register_form.php");
require($_SERVER['DOCUMENT_ROOT']."/modules/issues/issue_list.php");

// dateParam 설정
$dateParam  = $_GET['date'] ?? date('Y-m-d');
$issueParam = $_GET['issue'] ?? '';
$newIssueParam = $_GET['new_issue'] ?? '';
$formattedDate = str_replace('-', '', $dateParam);

// 마켓이슈 불러오기
$issuesQuery = $mysqli->prepare("SELECT mi.*, kg.group_name FROM market_issues mi LEFT JOIN keyword_groups kg ON mi.keyword_group_id = kg.group_id WHERE mi.date = ? ORDER BY mi.hot_theme DESC, kg.group_name ASC");
$issuesQuery->bind_param('s', $formattedDate);
$issuesQuery->execute();
$issuesResult = $issuesQuery->get_result();

// 종목 데이터 불러오기
$stocksQuery = $mysqli->prepare("
    SELECT *
    FROM market_issue_stocks
    WHERE date = ? 
    ORDER BY is_leader DESC, is_watchlist DESC, close_rate DESC");
$stocksQuery->bind_param('s', $formattedDate); 
$stocksQuery->execute();
$stocksResult = $stocksQuery->get_result();

$stocksData = [];
while ($stock = $stocksResult->fetch_assoc()) {
    $stocksData[$stock['issue_id']][] = $stock;
}

// 상승률 높은 종목 불러오기
$query = "SELECT 
              vdp.code, 
              vdp.name, 
              vdp.close_rate, 
              vdp.amount AS trade_amount,
              CASE WHEN mis.code IS NOT NULL THEN 1 ELSE 0 END AS registered,
              (
                  SELECT 
                      kg.group_name 
                  FROM 
                      keyword_groups kg
                  INNER JOIN 
                      market_issues mi ON mi.keyword_group_id = kg.group_id
                  INNER JOIN 
                      market_issue_stocks mis_sub ON mis_sub.issue_id = mi.issue_id
                  WHERE 
                      mis_sub.code = vdp.code
                  AND mi.date != vdp.date
                  ORDER BY 
                      mi.date DESC
                  LIMIT 1
              ) AS recent_keyword_group
          FROM 
              v_daily_price vdp
          LEFT JOIN 
              market_issue_stocks mis ON vdp.code = mis.code AND vdp.date = mis.date
          WHERE 
              vdp.date = ? 
              AND 
              ((vdp.close_rate > 10 AND vdp.amount > 50) OR
              (vdp.close_rate > 29.5 AND vdp.amount > 30))
          ORDER BY 
              recent_keyword_group DESC, vdp.close_rate DESC";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $formattedDate);
$stmt->execute();
$result = $stmt->get_result();
?>

<head>
    <!-- 페이지 전용 스타일 -->
    <style>
        #issue_register_container, #middle-panel, #history-panel, #issue_list_container {
            overflow-y: auto;
            padding: 15px; /* Reduced padding from 20px to 15px */
        }
		#issue_register_container {
			flex: 3;
			background-color: #fff;
			border-right: 1px solid #ddd;
			height: 100%; /* 고정된 높이 설정 */
		}

		#middle-panel {
			flex: 2;
			background-color: #e8e8e8;
			border-right: 1px solid #ddd;
			height: 100%; /* 고정된 높이 설정 */
		}

		#history-panel {
			flex: 2;
			background-color: #f5f5f5;
			border-right: 1px solid #ddd;
			padding: 15px;
			overflow-y: auto;
			height: 100%; /* 고정된 높이 설정 */
		}

		#issue_list_container {
			flex: 6;
			background-color: #f8f8f8;
			height: 100%; /* 고정된 높이 설정 */
		}
        #theme-table {
            margin-top: 30px; /* Reduced margin */
        }
        .accordion-content {
            display: ''; /* Initially hidden */
        }
    </style>
</head>

<body>
<div id="container">
    
    <!-- 이슈 및 테마 등록 화면 -->
    <div id="issue_register_container">
        <?php render_issue_register_form($dateParam, $issueParam, $newIssueParam); ?>
    </div>
    
    <!-- 종목 조회 화면 -->
    <div id="middle-panel">
        <h2>Stock for <?= htmlspecialchars($dateParam) ?></h2>
        <table>
            <thead>
                <tr>
                    <th>종목</th>
                    <th>등락률</th>
                    <th>거래대금</th>
                    <th>최근 키워드</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr style="<?= $row['registered'] ? 'text-decoration: line-through;' : '' ?>">
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td align="right"><?= htmlspecialchars($row['close_rate']) ?>%</td>
                    <td align="right"><?= number_format($row['trade_amount']) ?>억</td>
                    <td><?= htmlspecialchars($row['recent_keyword_group']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <!-- 과거 이력 조회 화면 -->
    <div id="history-panel">
        <h2>Keyword History</h2>
        <div id="history-content">
            <!-- This is where the historical data will be injected via AJAX -->
        </div>
    </div>

    <!-- 마켓 이슈 리스트 화면 -->
    <div id="issue_list_container">
        <?php render_issue_list($mysqli, 'date', $formattedDate); ?>
    </div>
</div>

<script>
	function IssueRegister_Initialize() {
		console.log("이슈 등록 초기화");
		// 키워드 포커스 아웃 시 과거 이력 가져오기
		document.getElementById('issue_register_keyword').addEventListener('blur', function() {
			const keywordInput = this.value.trim();
			const reportDate = document.getElementById('issue_register_date').value; // Get the value of the report date input

			if (keywordInput.startsWith('#')) { // Check if input starts with #
				$.ajax({
					url: 'fetch_history.php',
					type: 'GET',
					data: {
						keywords: keywordInput,
						exclude_date: reportDate // Pass the report date as the exclude_date parameter
					},
					success: function(response) {
						console.log(response);
						if (typeof response === "string") {
							// Parse JSON only if it's a string
							try {
								response = JSON.parse(response);
							} catch (error) {
								console.error('Error parsing JSON:', error);
								$('#history-content').html('<p>Failed to parse response as JSON.</p>');
								return;
							}
						}

						const data = response.data;

						if (data.theme) {
							$('#issue_register_theme').val(data.theme);
						}

						// Inject the fetched history into the history panel regardless
						$('#history-content').html(response.html);
					},
					error: function() {
						$('#history-content').html('<p>Failed to fetch the history.</p>');
					}
				});
			}
		});
	}
</script>

<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/common_footer.php");
?>
</body>
</html>