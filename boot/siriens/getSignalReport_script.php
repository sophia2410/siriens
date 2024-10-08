<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require("../common/db/connect.php");

$b = debug_backtrace();
foreach($b as $key=>$val){
	echo "$key =>  $val \n";
}
?>
</head>

<body>
<?php
// post 방식으로 넘어온 변수 확인
foreach($_POST as $key=>$val){
	echo "$key =>  $val \n";
}

// 전체 체크박스 건수
$total_cnt    = $_POST['total_cnt'];

if($_POST['proc_fg'] == 'D') {	// 뉴스삭제처리
	// 뉴스 건수만큼 돌면서 체크박스 체크여부 확인. 이후 건별 처리
	for($i=0; $i<$total_cnt; $i++){
		$id = 'id'.$i;
		if(isset($_POST[$id])) {
			$qry = "UPDATE signal_evening
					   SET del_yn = 'Y'
					 WHERE id = $_POST[$id]";

			echo $qry."<br><br>";
			$mysqli->query($qry);
		}
	}
} else if($_POST['proc_fg'] == 'S') {// 뉴스 저장처리
	// 뉴스 건수만큼 돌면서 체크박스 체크여부 확인. 이후 건별 처리
	for($i=0; $i<$total_cnt; $i++){
		$id = 'h_id'.$i;
		$signal_grp = 'signal_grp'.$_POST[$id];
		$theme      = 'theme'.$_POST[$id];
		// $stocks     = 'stocks'.$_POST[$id];
		$stock      = 'stock'.$_POST[$id];
		$today_pick = 'today_pick'.$_POST[$id];
		$title      = 'title'.$_POST[$id];
		// $writer     = 'writer'.$_POST[$id];
		$news_date     = 'news_date'.$_POST[$id];
		// $news_time  = 'news_time'.$_POST[$id];
		// $grouping   = 'grouping'.$_POST[$id];
		// $keyword    = 'keyword'.$_POST[$id];
		// $content    = 'content'.$_POST[$id];

		$pick_yn = (isset($_POST[$today_pick])) ? $_POST[$today_pick] : "N";

		$title_val = str_replace("'", "\'", $_POST[$title]);
		$title_val = str_replace('"', '\"', $title_val);

		// $content_val = str_replace("'", "\'", $_POST[$content]);
		// $content_val = str_replace('"', '\"', $content_val);

		$qry = "UPDATE signal_evening
				   SET signal_grp= '$_POST[$signal_grp]'
					 , theme     = '$_POST[$theme]'
					 , stock     = '$_POST[$stock]'
					 , today_pick= '$pick_yn'
					 , title     = '$title_val'
					 , news_date = '$_POST[$news_date]'
				 WHERE id = $_POST[$id]";

		// 화면단순화를 위해 미사용 항목 우선 제외처리
		// , stocks    = '$_POST[$stocks]'
		// , writer    = '$_POST[$writer]'
		// , news_time = '$_POST[$news_time]'
		// , grouping  = '$_POST[$grouping]'
		// , keyword   = '$_POST[$keyword]'
		// , content   = '$content_val'

		echo $qry."<br><br>";
		$mysqli->query($qry);
	}

	// 코드 미등록 종목은 코드 등록하기
	$qry = " UPDATE signal_evening A
				SET code
			= (SELECT code
				 FROM stock B
				WHERE B.name = A.stock
				  AND B.last_yn = 'Y')
			 WHERE page_date = '$_POST[page_date]'
			   AND page_fg   = '$_POST[page_fg]'";

	echo $qry."<br><br>";
	$mysqli->query($qry);

	// today_pick 업데이트
	$qry = " UPDATE signal_evening
				SET today_pick = 'Y'
			 WHERE page_date = '$_POST[page_date]'
			   AND page_fg   = '$_POST[page_fg]'
			   AND today_pick = 'N'
			   AND stocks like concat('%',stock,'%')
			   AND stock != '' ";

	echo $qry."<br><br>";
	$mysqli->query($qry);


} else if($_POST['proc_fg'] == 'P') {// 뉴스 반영
	if($_POST['page_fg'] == 'E') { // 시그널 이브닝 반영인 경우
		// today_pick 이 아닌 종목은 stocks 데이터 지우기
		$qry = " UPDATE signal_evening
					SET stocks = ''
				WHERE page_date = '$_POST[page_date]'
					AND page_fg = '$_POST[page_fg]'
					AND today_pick = 'N'";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		// signals 테이블에 뉴스 데이터 반영
		// 같은 뉴스에 종목이 중복 등록된 경우 제일 먼저 적용된 종목이 signals 테이블에 반영되도록 수정 - 2024.06.23
		// $qry = "INSERT INTO signals
		// 		(
		// 			news_date,
		// 			news_time,
		// 			title,
		// 			content,
		// 			publisher,
		// 			writer,
		// 			link,
		// 			code,
		// 			name,
		// 			grouping,
		// 			keyword,
		// 			remark,
		// 			confirm_fg,
		// 			create_date ,
		// 			create_id
		// 		)
		// 		SELECT  news_date,
		// 				news_time,
		// 				title,
		// 				content,
		// 				publisher,
		// 				writer,
		// 				link,
		// 				code,
		// 				stock,
		// 				grouping,
		// 				keyword,
		// 				stocks,
		// 				'1',
		// 				now(),
		// 				now()
		// 			FROM signal_evening A
		// 			WHERE page_date = '$_POST[page_date]'
		// 			AND page_fg = '$_POST[page_fg]'
		// 			AND exists_yn = 'N'
		// 			AND del_yn	  = 'N'
		// 			AND link is not null";
		
		// 같은 뉴스에 종목이 중복 등록된 경우 제일 먼저 적용된 종목이 signals 테이블에 반영되는 쿼리 - 2024.06.23
		$qry = "INSERT INTO signals
				(
					news_date,
					news_time,
					title,
					content,
					publisher,
					writer,
					link,
					code,
					name,
					grouping,
					keyword,
					remark,
					confirm_fg,
					create_date,
					create_id
				)
				SELECT
					news_date,
					news_time,
					title,
					content,
					publisher,
					writer,
					link,
					code,
					stock AS name,
					grouping,
					keyword,
					stocks AS remark,
					'1' AS confirm_fg,
					NOW() AS create_date,
					NOW() AS create_id
				FROM signal_evening
				WHERE id IN (
					SELECT MIN(id)
					FROM signal_evening
					WHERE page_date = '$_POST[page_date]'
					AND page_fg = '$_POST[page_fg]'
					AND exists_yn = 'N'
					AND del_yn = 'N'
					AND link IS NOT NULL
					GROUP BY link
				)";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		// signals 테이블 반영 정보 rawdata_siri_report에 업데이트
		$qry = "UPDATE signal_evening A
				INNER JOIN signals B
					ON replace(replace(B.link,'https://',''),'http://','') = replace(replace(A.link,'https://',''),'http://','')
				SET A.exists_yn = 'Y'
					, A.signal_id = B.signal_id
					, A.confirm_fg = '1'
				WHERE A.page_date = '$_POST[page_date]'
				AND A.page_fg = '$_POST[page_fg]'
				AND A.exists_yn = 'N'
				AND A.del_yn	 = 'N'
				AND A.link is not null";

		echo $qry."<br><br>";
		$mysqli->query($qry);


	// siriens_evening 테이블 데이터를 키움 관리종목 정리한 엑셀 데이터 반영으로 변경. 그래서 아래 로직이 필요없어졌다. 
	// siriens_infostock 테이블에 siriens_evening_id 반영하는 로직은 향후 보완 및 데이터 보정 필요.
	// 2023.09.23 주석 Start------------------------------------------------------------------------------------------------

		// siriens 테이블 반영
		// 기 반영 후 수정일 경우 삭제 후 재등록 처리
		// $qry = "DELETE FROM siriens_evening
		// 		WHERE report_date = '$_POST[page_date]'
		// 		  AND create_id = 'signal_evening'";

		// echo $qry."<br><br>";
		// $mysqli->query($qry);

		// // 데이터 등록
		// $qry = "INSERT INTO siriens_evening
		// 		(
		// 			report_date,
		// 			signal_grp,
		// 			theme,
		// 			issue,
		// 			code,
		// 			name,
		// 			signal_id,
		// 			create_id,
		// 			create_date
		// 		)
		// 		SELECT	page_date,
		// 				signal_grp,
		// 				theme,
		// 				title,
		// 				code,
		// 				stock,
		// 				signal_id,
		// 				'signal_evening',
		// 				now()
		// 		FROM	signal_evening A
		// 		WHERE	page_date = '$_POST[page_date]'
		// 		AND  page_fg = '$_POST[page_fg]'
		// 		AND  today_pick = 'Y'";

		// echo $qry."<br><br>";
		// $mysqli->query($qry);

		// // siriens_infostock 테이블 반영
		// $qry = " UPDATE siriens_infostock A 
		// 		INNER JOIN siriens_evening B
		// 			ON B.report_date = A.report_date
		// 			AND B.code = A.code
		// 			SET A.siriens_evening_id = B.id
		// 		  WHERE A.report_date = '$_POST[page_date]'
		// 			AND EXISTS (SELECT * FROM siriens_evening Z WHERE Z.report_date = A.report_date AND Z.code = A.code)";

		// echo $qry."<br><br>";
		// $mysqli->query($qry);
	// 2023.09.23 주석 END------------------------------------------------------------------------------------------------

		// siriens_report 에 title 적용 => DBUp_SignalEvening.py 에서 처리하는 것으로 수정 24.09.07
		// $qry = "REPLACE INTO market_report
		// 		(
		// 			report_date,
		// 			evening_report_title
		// 		)
		// 		SELECT	page_date,
		// 				SUBSTRING_INDEX(SUBSTRING_INDEX(page_title,'\"',-2),'\"',1)
		// 		FROM	signal_evening
		// 		WHERE	page_date = '$_POST[page_date]'
		// 		AND  (page_title is not null  AND page_title <> '')";

		// echo $qry."<br><br>";
		// $mysqli->query($qry);
			
	} else { // 장전뉴스 반영인 경우
		// signals 테이블에 뉴스 데이터 반영
		$qry = "INSERT INTO signals
				(
					news_date,
					news_time,
					title,
					content,
					publisher,
					writer,
					link,
					code,
					name,
					grouping,
					keyword,
					remark,
					confirm_fg,
					create_date ,
					create_id
				)
				SELECT  news_date,
						news_time,
						title,
						content,
						publisher,
						writer,
						link,
						code,
						stock,
						grouping,
						keyword,
						stocks,
						'1',
						now(),
						now()
					FROM signal_evening A
					WHERE page_date = '$_POST[page_date]'
					AND page_fg = '$_POST[page_fg]'
					AND exists_yn = 'N'
					AND del_yn	  = 'N'
					AND link is not null";

		echo $qry."<br><br>";
		$mysqli->query($qry);

		// signals 테이블 반영 정보 rawdata_siri_report에 업데이트
		$qry = "UPDATE signal_evening A
				INNER JOIN signals B
					ON replace(replace(B.link,'https://',''),'http://','') = replace(replace(A.link,'https://',''),'http://','')
				SET A.exists_yn = 'Y'
					, A.signal_id = B.signal_id
					, A.confirm_fg = '1'
				WHERE page_date = '$_POST[page_date]'
				AND page_fg = '$_POST[page_fg]'
				AND exists_yn = 'N'
				AND del_yn	 = 'N'
				AND link is not null";

		echo $qry."<br><br>";
		$mysqli->query($qry);
	}

} else if($_POST['proc_fg'] == 'US') {// signals 테이블에 update 처리
	for($i=0; $i<$total_cnt; $i++){

		$id = 'h_id'.$i;
		$news_date = 'news_date'.$_POST[$id];
		$news_time = 'news_time'.$_POST[$id];
		$title     = 'title'.$_POST[$id];
		$content   = 'content'.$_POST[$id];
		$publisher = 'publisher'.$_POST[$id];
		$writer    = 'writer'.$_POST[$id];
		$code      = 'code'.$_POST[$id];
		$name      = 'name'.$_POST[$id];
		// $grouping  = 'grouping'.$_POST[$id];
		// $keyword   = 'keyword'.$_POST[$id];
		$remark    = 'remark'.$_POST[$id];

		$title_val = str_replace("'", "\'", $_POST[$title]);
		$title_val = str_replace('"', '\"', $title_val);

		$content_val = str_replace("'", "\'", $_POST[$content]);
		$content_val = str_replace('"', '\"', $content_val);
		
		$qry = "UPDATE  signals
				   SET  news_date  = '$_POST[$news_date]'
					 ,	news_time  = '$_POST[$news_time]'
					 ,	title      = '$title_val'
					 ,	content    = '$content_val'
					 ,	publisher  = '$_POST[$publisher]'
					 ,	writer     = '$_POST[$writer]'
					 ,	code       = '$_POST[$code]'
					 ,	name       = '$_POST[$name]'
					 ,	confirm_fg = '1'
				 WHERE  signal_id = $_POST[$id]";
		echo $qry."<br><br>";
		$mysqli->query($qry);

		//  ,	grouping   = '$_POST[$grouping]'
		//  ,	keyword    = '$_POST[$keyword]'
	}
} else if($_POST['proc_fg'] == 'UT') {// theme update 처리  // getSignalReport_sub3
	for($i=0; $i<$total_cnt; $i++){
		$signal_grp     = 'signal_grp'.$i;
		$theme          = 'theme'.$i;
		$grouping       = 'grouping'.$i;
		$signal_grp_str = 'signal_grp_str'.$i;
		$theme_str      = 'theme_str'.$i;
		$grouping_str   = 'grouping_str'.$i;
		$today_rank     = 'today_rank'.$i;

		$qry = "UPDATE signal_evening
				   SET signal_grp = '$_POST[$signal_grp_str]'
					 , theme      = '$_POST[$theme_str]'
					 , grouping   = '$_POST[$grouping_str]'
					 , today_rank = '$_POST[$today_rank]'
				 WHERE signal_grp = '$_POST[$signal_grp]'
				   AND case when theme is null then '' else theme end = '$_POST[$theme]'
				   AND case when grouping is null then '' else grouping end = '$_POST[$grouping]'";
		echo $qry."<br><br>";
		$mysqli->query($qry);
	}
} else if($_POST['proc_fg'] == 'NM') {// 빠른뉴스수정에서 변경사항 저장
	// 뉴스 건수만큼 돌면서 데이터 업데이트
	for($i=0; $i<$total_cnt; $i++){
		$id        = 'id'.$i;
		$news_date = 'news_date'.$i;
		$stock     = 'stock'.$i;

		$qry = "UPDATE signal_evening
				   SET news_date = '$_POST[$news_date]'
					 , stock   = '$_POST[$stock]'
				 WHERE id = '$_POST[$id]'";
		echo $qry."<br><br>";
		$mysqli->query($qry);
	}
} else if($_POST['proc_fg'] == 'NC') {// news confirm 처리

	// 미확인 뉴스 - 확인으로 업데이트
	for($i=0; $i<$total_cnt; $i++){
		$id    = 'id'.$i;

		$qry = "UPDATE signal_evening
				   SET confirm_fg   = '2'
				 WHERE id = '$_POST[$id]'";
		echo $qry."<br><br>";
		$mysqli->query($qry);
	}
}
$mysqli->close();
?>
<a href="javascript:window.parent.location='http://localhost/boot/news/news.php?link_date=<?=$link_date?>&signal_group=<?=$signal_group?>';"> 등록페이지로</a>
</body>
</html>