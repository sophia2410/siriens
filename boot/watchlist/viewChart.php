<?php
// 차트 보기 화면. 각 화면에서 링크 됨. 화면 ID에 따라 분기 처리
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>

<head>
<link rel="shortcut icon" href="#">
<script 
  src="https://code.jquery.com/jquery-3.6.0.min.js"
  integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" 
  crossorigin="anonymous">
</script>
</head>

<?php
$pgmId = (isset($_GET['pgmId'])) ? $_GET['pgmId'] : '';

$search_date  = (isset($_GET['search_date'])  ) ? $_GET['search_date']   : '';

$sector   = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme 	  = (isset($_GET['theme']))  ? $_GET['theme'] : '';
$category = (isset($_GET['category'])) ? $_GET['category'] : '';

$viewFg = (isset($_GET['viewFg'])) ? $_GET['viewFg'] : 'day';

$btn_class1 = $btn_class2 = $btn_class3 = $btn_class4 = $btn_class5 = "btn-secondary";

switch ($viewFg) {
	case "day"	   : $btn_class1 = "btn-danger"; break;
	case "week"    : $btn_class2 = "btn-danger"; break;
	case "month"   : $btn_class3 = "btn-danger"; break;
	case "oneday"  : $btn_class4 = "btn-danger"; break;
	case "oneweek" : $btn_class5 = "btn-danger"; break;
}
?>

<body>

<!-- 조회 조건을 변경할 수 있는 버튼을 생성합니다. -->
<button class="btn btn-info btn-sm" id="excel_down">관종엑셀 다운로드</button>&nbsp;
<!-- 사용하지 않아 우선 막아두기.. 기능은 살아있음 -->
<!-- <button class="btn btn-info btn-sm" id="mdfile_down">옵시디언 파일생성</button> -->
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<button class="btn <?=$btn_class1?> btn-sm" onclick="changeCondition('day')">일</button>
<button class="btn <?=$btn_class2?> btn-sm" onclick="changeCondition('week')">주</button>
<button class="btn <?=$btn_class3?> btn-sm" onclick="changeCondition('month')">월</button> &nbsp;
<button class="btn <?=$btn_class4?> btn-sm" onclick="changeCondition('oneday')">1일</button>
<button class="btn <?=$btn_class5?> btn-sm" onclick="changeCondition('oneweek')">1주</button>&nbsp;&nbsp;

<!-- 
<button class="btn btn-secondary btn-sm" onclick="changeCondition('day')">일</button>
<button class="btn btn-secondary btn-sm" onclick="changeCondition('week')">주</button>
<button class="btn btn-secondary btn-sm" onclick="changeCondition('month')">월</button> &nbsp;
<button class="btn btn-secondary btn-sm" onclick="changeCondition('oneday')">1일</button>
<button class="btn btn-secondary btn-sm" onclick="changeCondition('oneweek')">1주</button>&nbsp;&nbsp;
 -->
<?php
if($pgmId == ''){
	echo "<h3></h3>";
} else {
	// group key 구하기
	$grpqry = '';
	if($pgmId == 'kiwoomapi_opt10029_224') {
		$grpqry= "SELECT RTRIM(CONCAT(' [ 224 - ' , S.market_fg,' ] ')) AS group_key
						,RTRIM(CONCAT(' [ 224 - ' , S.market_fg,' ] ')) AS group_val
					FROM 
					(	SELECT 'KOSPI' market_fg
						UNION ALL
						SELECT 'KOSDAQ' market_fg ) S
					ORDER BY S.market_fg";
	} else if($pgmId == 'kiwoomapi_opt10029') {
		$grpqry= "SELECT group_key, group_val, sortby
					FROM (
							SELECT RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme, '  ', A.category)) AS group_key
								, CONCAT('[', A.theme, (CASE WHEN A.category != '' THEN CONCAT('-', A.category) ELSE '' END),']') AS group_val
								, MIN(A.sort_theme) sortby
							FROM watchlist_sophia A
							JOIN kiwoom_opt10029 B
							ON B.code = A.code
							WHERE A.sector = '$sector'
							AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
							AND B.date = '$search_date'
							GROUP BY A.sector, A.theme, A.category ) G
					ORDER BY sortby";
	} else if($pgmId == 'aStarWatchlist') {
		$grpqry= "SELECT group_key, group_val, sortby
					FROM (
							SELECT RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme)) AS group_key
								, CONCAT('[', A.theme,']') AS group_val
								, MIN(A.sort_theme) sortby
							FROM watchlist_astar A
							WHERE A.sector = '$sector'
							AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
							GROUP BY A.sector, A.theme ) G
					ORDER BY sortby";
	} else if($pgmId == 'sophiaWatchlist') {
		$grpqry= "SELECT group_key, group_val, sortby
					FROM (
							SELECT RTRIM(CONCAT(' [ ' , A.sector,' ] ', A.theme, '  ', A.category)) AS group_key
								, CONCAT('[', A.theme, (CASE WHEN A.category != '' THEN CONCAT('-', A.category) ELSE '' END),']') AS group_val
								, MIN(A.sort_theme) sortby
							FROM watchlist_sophia A
							WHERE A.sector = '$sector'
							AND A.theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
							GROUP BY A.sector, A.theme, A.category ) G
					ORDER BY sortby";
	}

	// echo "<pre>$grpqry</pre>";

	if($grpqry != '') {
		echo "<div class='category-list'>";
		$result = $mysqli->query($grpqry);
		while($row = $result->fetch_array(MYSQLI_BOTH)) {
			echo "<a href='javascript:void(0);' onclick=\"scrollToIframeSection('" . $row['group_key'] . "')\">" . $row['group_val'] . "</a> ";
		}
		echo "</div>";
	}
}
?>
<table style="width:100%;">
	<tr>
	<td>
		<div style="margin: 0; border: 0; font: inherit;vertical-align: baseline; padding: 0;height: calc(100vh - 100px);">
			<iframe id="iframe" style="width: 100%; margin: 0; border: 0; font: inherit; vertical-align: baseline; padding: 0; height: calc(100vh - 100px);">
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
// 페이지 GET Params 구하기
function getQueryParams() {
	var params = new URLSearchParams(window.location.search);
	return params;
}

function setIframeSrc(iframeId, baseUrl) {
	var params = getQueryParams();
	var iframe = document.getElementById(iframeId);
	iframe.src = baseUrl + "?" + params.toString();
}

window.onload = function() {
	setIframeSrc('iframe', 'viewChart_B.php');
}

function search(sortBy='amount_last_min') {
	date   = document.getElementById('date').value;
	minute   = document.getElementById('minute').value;

	iframe.src = "kiwoomRealtime_B.php?date="+date+"&minute="+minute+"&sortBy="+sortBy;
	return;
}

// 차트 형식 버튼을 클릭할 때마다 화면 리로드
function changeCondition(newCondition) {
    // 현재 페이지의 URL을 가져옵니다.
    var url = window.location.href;

    // URL에 조회 조건이 있으면 제거합니다.
    if (url.indexOf("&viewFg=") != -1) {
        url = url.substring(0, url.indexOf("&viewFg="));
    }
    // URL에 새로운 조회 조건을 추가합니다.
    url = url + "&viewFg=" + newCondition;
	// 자기 페이지를 새로운 URL로 리로드합니다.
    window.location.href = url;
}

function scrollToIframeSection(sectionId) {
    var iframe = document.getElementById('iframe'); // iframe의 ID에 맞게 조정하세요.
    var currentSrc = iframe.src;
    
    // URL에서 해시(#) 부분 제거
    var baseSrc = currentSrc.split('#')[0];
    
    // 새로운 해시를 포함한 URL 설정
    var newSrc = baseSrc + "#" + sectionId;
    
    // iframe의 src 속성을 업데이트하여 내부 페이지에서 스크롤
    iframe.src = newSrc;
}

$("#excel_down").click(function() {
	$.ajax({
	method: "POST",
	url: "viewChart_runPy.php",
	data: {downfile: "excel"}
	})
	.done(function(result) {
      alert('다운로드 완료!');
	});
});

$("#mdfile_down").click(function() {
	$.ajax({
	method: "POST",
	url: "viewChart_runPy.php",
	data: {downfile: "markdown"}
	})
	.done(function(result) {
      alert('생성 완료!');
	});
});

</script>

<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>