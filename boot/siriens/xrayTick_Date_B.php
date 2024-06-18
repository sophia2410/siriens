<?php
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
<head>
</head>

<?php
// echo "<pre>"; // 브라우저에서 보기 좋게 포맷팅
// print_r($_GET); // $_GET 배열의 내용을 출력
// echo "</pre>";
$search_date = (isset($_GET['search_date'])) ? $_GET['search_date'] : '';
?>

<body>
<form name="form1" method='POST' action='siriensEvening_script.php' onsubmit="return false">

<?php
if($search_date == '') {
	echo "<h3>일자 선택 후 조회</h3>";
	$ready = 'N';
} else {
		//X-RAY 순간체결 거래량
		$query = "
		SELECT 
		  xray.code,
		  xray.name,
		  MAX(CASE WHEN td.rank = 1 THEN td.date ELSE '' END) 'D-day0 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 1 THEN dp.close_rate ELSE 0 END) AS 'D-0 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 1 THEN xray.amount ELSE 0 END) AS 'D-0 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 1 THEN xray.cnt ELSE 0 END) AS 'D-0 Count',
		  MAX(CASE WHEN td.rank = 2 THEN td.date ELSE '' END) 'D-day1 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 2 THEN dp.close_rate ELSE 0 END) AS 'D-1 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 2 THEN xray.amount ELSE 0 END) AS 'D-1 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 2 THEN xray.cnt ELSE 0 END) AS 'D-1 Count',
		  MAX(CASE WHEN td.rank = 3 THEN td.date ELSE '' END) 'D-day2 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 3 THEN dp.close_rate ELSE 0 END) AS 'D-2 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 3 THEN xray.amount ELSE 0 END) AS 'D-2 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 3 THEN xray.cnt ELSE 0 END) AS 'D-2 Count',
		  MAX(CASE WHEN td.rank = 4 THEN td.date ELSE '' END) 'D-day3 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 4 THEN dp.close_rate ELSE 0 END) AS 'D-3 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 4 THEN xray.amount ELSE 0 END) AS 'D-3 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 4 THEN xray.cnt ELSE 0 END) AS 'D-3 Count',
		  MAX(CASE WHEN td.rank = 5 THEN td.date ELSE '' END) 'D-day4 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 5 THEN dp.close_rate ELSE 0 END) AS 'D-4 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 5 THEN xray.amount ELSE 0 END) AS 'D-4 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 5 THEN xray.cnt ELSE 0 END) AS 'D-4 Count',
		  MAX(CASE WHEN td.rank = 6 THEN td.date ELSE '' END) 'D-day5 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 6 THEN dp.close_rate ELSE 0 END) AS 'D-5 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 6 THEN xray.amount ELSE 0 END) AS 'D-5 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 6 THEN xray.cnt ELSE 0 END) AS 'D-5 Count',
		  MAX(CASE WHEN td.rank = 7 THEN td.date ELSE '' END) 'D-day6 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 7 THEN dp.close_rate ELSE 0 END) AS 'D-6 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 7 THEN xray.amount ELSE 0 END) AS 'D-6 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 7 THEN xray.cnt ELSE 0 END) AS 'D-6 Count',
		  MAX(CASE WHEN td.rank = 8 THEN td.date ELSE '' END) 'D-day7 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 8 THEN dp.close_rate ELSE 0 END) AS 'D-7 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 8 THEN xray.amount ELSE 0 END) AS 'D-7 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 8 THEN xray.cnt ELSE 0 END) AS 'D-7 Count',
		  MAX(CASE WHEN td.rank = 9 THEN td.date ELSE '' END) 'D-day8 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 9 THEN dp.close_rate ELSE 0 END) AS 'D-8 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 9 THEN xray.amount ELSE 0 END) AS 'D-8 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 9 THEN xray.cnt ELSE 0 END) AS 'D-8 Count',
		  MAX(CASE WHEN td.rank = 10 THEN td.date ELSE '' END) 'D-day9 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 10 THEN dp.close_rate ELSE 0 END) AS 'D-9 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 10 THEN xray.amount ELSE 0 END) AS 'D-9 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 10 THEN xray.cnt ELSE 0 END) AS 'D-9 Count',
		  MAX(CASE WHEN td.rank = 11 THEN td.date ELSE '' END) 'D-day10 Date',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 11 THEN dp.close_rate ELSE 0 END) AS 'D-10 CloseRate',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 11 THEN xray.amount ELSE 0 END) AS 'D-10 Amount',
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 11 THEN xray.cnt ELSE 0 END) AS 'D-10 Count'
		FROM 
		  (
			  SELECT
			  date, code, name, round(sum(volume*current_price)/100000000,1) amount,  count(*) cnt
			FROM
				kiwoom_xray_tick_executions
			WHERE
				date BETWEEN DATE_FORMAT(DATE_SUB('{$search_date}', INTERVAL 20 DAY), '%Y%m%d') AND DATE_FORMAT('{$search_date}', '%Y%m%d')
			GROUP BY
				date, code, name
			) xray
		LEFT outer JOIN 
		  daily_price dp ON dp.date = xray.date AND dp.code = xray.code
		JOIN (
		  SELECT 
			date,
			RANK() OVER (ORDER BY date DESC) as rank
		  FROM 
			calendar
		  WHERE 
			date <= '{$search_date}'
		  ORDER BY 
			date DESC
		  LIMIT 11
		) td ON xray.date = td.date
		WHERE 
		  xray.cnt > 20 
		GROUP BY 
		  xray.code, xray.name
		ORDER BY 
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 1 THEN xray.amount ELSE 0 END) DESC,
		  MAX(CASE WHEN xray.date = td.date AND td.rank = 2 THEN xray.amount ELSE 0 END) DESC
		";
		// echo "<pre>$query</pre>";

		// 특징주 등록을 위한 엑셀파일용 쿼리문, 파이썬 프로그램에서 사용.
		$filename = 'XrayTick';
		$file_where = "WHERE `D-day0 Date` != '' ORDER BY `D-0 Amount` DESC";
		$text =  $filename. "\n" .$file_where. "\n" .$query;

		// 데이터베이스 연결 및 쿼리 실행
		$result = $mysqli->query($query);

		file_put_contents('E:/Project/202410/www/pyObsidian/vars_downExcel.txt', $text);
		
		// 테이블 헤더
		echo "<table class='table table-sm small table-bordered text-dark'>";
		echo "<tr class='table-secondary' align=center>"; // 첫 번째 헤더 행
		echo "<th rowspan='2'>코드</th>";
		echo "<th rowspan='2'>명</th>";

		// 동적으로 일자별 헤더 생성
		for ($i = 0; $i <= 10; $i++) {
			echo "<th colspan='3' style='border-left: 2px solid gray'>D-day".($i > 0 ? " -$i" : "")." Date</th>";
		}
		
		echo "</tr>";
		
		echo "<tr class='table-secondary' align=center>"; // 두 번째 헤더 행
		for ($i = 0; $i <= 10; $i++) {
			echo "<th style='border-left: 2px solid gray'>등락률</th>";
			echo "<th>금액</th>";
			echo "<th>건수</th>";
		}
		echo "</tr>";
		
		// 데이터 행 생성
		while ($row = $result->fetch_assoc()) {
			echo "<tr align=right>";
			echo "<td align=center><a href='../siriens/stock_B.php?code=".$row['code']."&name=".$row['name']."&brWidth=2500' onclick='window.open(this.href, \'stock\' target='_blank'>".$row['code']."</a></td>";
			echo "<td align=left><h6><b>".$row['name']."</b></h6></td>";
			for ($i = 0; $i <= 10; $i++) {
				if($row["D-$i Amount"] > 0) echo "<td style='border-left: 2px solid gray' title='".$row["D-day$i Date"]."'><a href=\"javascript:openPopupXrayTick('".$row['code']."', '".$row["D-day$i Date"]."')\">".$row["D-$i CloseRate"]."% </a></td>";
				else echo "<td style='border-left: 2px solid gray' title='".$row["D-day$i Date"]."'>".$row["D-$i CloseRate"]."% </td>";

				$amt_style = ($row["D-$i Amount"] > 500) ? "mark text-danger font-weight-bold" : (($row["D-$i Amount"] > 100) ? "text-danger font-weight-bold" : "font-weight-bold");

				if($row["D-$i Amount"] > 0) echo "<td class='".$amt_style."'>".number_format($row["D-$i Amount"])."억</td>" ;
				else echo "<td>-</td>" ;

				if($row["D-$i Amount"] > 0) echo "<td class='".$amt_style."'>".number_format($row["D-$i Count"])."</td>";
				else echo "<td>-</td>" ;
			}
			echo "</tr>";
		}
		
		echo "</table>";


}
?>
</form>
<script>
function openPopupXrayTick(code, date) {
    var url = "/boot/common/popup/stock_xray_tick.php?code=" + code + "&date=" + date;
    var newWindow = window.open(url, "pop", "width=600,height=800,scrollbars=yes,resizable=yes");
    if (window.focus) {
        newWindow.focus();
    }
}
</script>
</body>
</html>