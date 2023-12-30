<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

$watchlist_date = (isset($_GET['watchlist_date'])   ) ? $_GET['watchlist_date'] : date('Ymd',time());
?>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">
	
<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_sirianz.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

<table style="width:100%;">
	<tr>
		<td style='width:25%'>
			<!-- Page Heading -->
			<div style='border: 1px;'>
				<select id="watchlist_date" class="select" style='width:120px;'>
				<?php
					$query = " SELECT date
									, CASE WHEN B.watchlist_date is null THEN '' ELSE '(Y)' END regi_watchlist
								FROM calendar A
								LEFT OUTER JOIN (SELECT watchlist_date FROM sophia_watchlist GROUP BY watchlist_date) B
								ON B.watchlist_date = A.date
								WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))
								ORDER BY date DESC
								LIMIT 200";

					$result = $mysqli->query($query);

					$option = "";
					$i=0;
					while($row = $result->fetch_array(MYSQLI_BOTH)) {
						// 관종등록일자가 없는 경우는 제일 1행 선택되도록..
						if($watchlist_date == $row['date']) {
							$option .= "<option value='". $row['date']."' selected>".$row['date'].$row['regi_watchlist']."</option>";
						} else {
							$option .= "<option value='". $row['date']."'>".$row['date'].$row['regi_watchlist']."</option>";
						}
						$i++;
					}
					echo $option;
				?>
				</select>
				&nbsp;
				<button class="btn btn-danger btn-sm" onclick="search()"> 조 회 </button>
				&nbsp;
				<button class="btn btn-info btn-sm" onclick="show()"> 테마보기 </button>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<button class="btn btn-secondary btn-sm" onclick="getData()">GetData</button> || 
				<input type=text id=stock style='width:120px' placeholder='종목코드/명'>
				<button class="btn btn-secondary btn-sm" onclick="addData()">Add</button>
				
			</div>
		</td>
		<td style='width:75%' rowspan=2 valign=top>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeR" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 30px);" src="scenario_CMD.php">
				</iframe>
			</div>
		</td>
	</tr>
	<tr>
		<td>
			<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
				<iframe id="iframeL" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);" src="watchlist_L.php">
				</iframe>
			</div>
		</td>
	</tr>

</table>

</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<script>
// 관종등록일자 선택후 조회 - 왼쪽 프레임에 종목 리스트업
function search() {
	key_val  = document.getElementById('watchlist_date').options[document.getElementById("watchlist_date").selectedIndex].value;
	brWidth = window.innerWidth;
	iframeL.src = "watchlist_L.php?watchlist_date="+key_val+"&brWidth="+brWidth;
	return;
}

// 관종등록일자 선택후 데이터 가져오기
function getData() {

	key_val  = document.getElementById('watchlist_date').options[document.getElementById("watchlist_date").selectedIndex].value;
	if(confirm(key_val+'데이터를 가져오시겠습니까?')) {
		// 자식프레임에 있는 함수 호출하기
		document.getElementById("iframeR").contentWindow.getWatchlist(key_val);
	}
}


// 관종등록일자 선택후 데이터 가져오기
function addData() {
	key_val  = document.getElementById('watchlist_date').options[document.getElementById("watchlist_date").selectedIndex].value;
	stock = document.getElementById('stock').value;

	// 자식프레임에 있는 함수 호출하기
	document.getElementById("iframeR").contentWindow.addWatchlist(key_val,stock);
}


// 종목 선택 시 오른쪽 프레임에 내역 조회
function viewStock(wdate, sdate, cd, nm) {
	brWidth = window.innerWidth;
	iframeR.src = "scenario_CMD.php?watchlist_date="+wdate+"&scenario_date="+sdate+"&code="+cd+"&name="+nm+"&brWidth="+brWidth;
	return;
}

// 일자 선택 시 오른쪽 프레임에 내역 조회
function viewDay(date) {
	brWidth = window.innerWidth;
	iframeR.src = "watchlist_RD.php?watchlist_date="+date+"&brWidth="+brWidth;
	return;
}
</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
