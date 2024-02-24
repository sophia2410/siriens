<html>
<head>
<?php
header('Content-Type: text/html; charset=utf-8');
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>
</head>

<body>
<?php
// post 방식으로 넘어온 변수 확인
foreach($_POST as $key=>$val){
	echo "$key =>  $val \n";
}

if(isset($_POST['proc_fg'])) {	
	// 시리언즈 이브닝 종목 정보 업데이트
	if($_POST['proc_fg'] == 'stockSave') {	
		for($i=0; $i<$_POST['cnt']; $i++){
			$id			= 'id'.$i;
			$code		= 'code'.$i;
			$signal_grp = 'signal_grp'.$i;
			$issue		= 'issue'.$i;
			$keyword	= 'keyword'.$i;
			$stock_keyword = 'stock_keyword'.$i;

			$qry = "UPDATE siriens_evening
					SET signal_grp	= '".$_POST[$signal_grp]."'
					,	issue		= '".$_POST[$issue]."'
					, 	keyword		= '".$_POST[$keyword]."'
					,	stock_keyword = '".$_POST[$stock_keyword]."'
					WHERE report_date ='".$_POST['report_date']."'
					AND id='".$_POST[$id]."'";
			echo $qry."<br>";
			$mysqli->query($qry);

		}
	}
	// 시리언즈 이브닝 종목 삭제
	else if($_POST['proc_fg'] == 'stockDel') {	// siriensEvening_B.php
		for($i=0; $i<$_POST['cnt']; $i++){
			$check	= 'check'.$i;
			$id		= 'id'.$i;
			
			if(isset($_POST[$check])) {
				$qry = "DELETE FROM siriens_evening
						WHERE report_date = '".$_POST['report_date']."'
						AND id = '".$_POST[$id]."'";

				// echo $qry."<br>";
				$mysqli->query($qry);
			}
		}
	}
	// 테마 정보 등록,수정
	else if($_POST['proc_fg'] == 'themeSave') {	
		for($i=0; $i<$_POST['cnt']; $i++){
			
			// 시그널이브닝 테마,이슈,키워드 변경 정보 업데이트
			$theme		= 'theme'.$i;
			$issue		= 'issue'.$i;
			$keyword	= 'keyword'.$i;
			$org_theme	= 'org_theme'.$i;
			$org_issue	= 'org_issue'.$i;
			$org_keyword= 'org_keyword'.$i;

			$qry = "UPDATE siriens_evening
					SET theme		= '".$_POST[$theme]."'
					,	issue		= '".$_POST[$issue]."'
					, 	keyword		= '".$_POST[$keyword]."'
					WHERE report_date ='".$_POST['report_date']."'
					AND theme  ='".$_POST[$org_theme]."'
					AND issue  ='".$_POST[$org_issue]."'
					AND keyword='".$_POST[$org_keyword]."'";
			echo $qry."<br>";
			$mysqli->query($qry);

			// 시그널이브닝 테마,이슈,키워드 변경 정보 업데이트
			$theme_tb_id= 'theme_tb_id'.$i;
			$signal_id1	= 'signal_id1_'.$i;
			$signal_id2	= 'signal_id2_'.$i;
			$signal_id3	= 'signal_id3_'.$i;

			if($_POST[$theme_tb_id] == 'NoData') {
				$qry = "INSERT INTO siriens_evening_theme (report_date, theme, issue, keyword, signal_id1, signal_id2, signal_id3, create_id, create_dtime)
						VALUES ('".$_POST['report_date']."', '".$_POST[$theme]."', '".$_POST[$issue]."', '".$_POST[$keyword]."',
								 CASE WHEN '".$_POST[$signal_id1]."' = '' THEN NULL ELSE '".$_POST[$signal_id1]."' END, 
								 CASE WHEN '".$_POST[$signal_id2]."' = '' THEN NULL ELSE '".$_POST[$signal_id2]."' END, 
								 CASE WHEN '".$_POST[$signal_id3]."' = '' THEN NULL ELSE '".$_POST[$signal_id3]."' END, 
								 'SiriensEvening_script', now())";
				echo $qry."<br>";
				$mysqli->query($qry);

			} else {
				$qry = "UPDATE siriens_evening_theme
						SET theme		= '".$_POST[$theme]."'
						,	issue		= '".$_POST[$issue]."'
						, 	keyword		= '".$_POST[$keyword]."'
						,	signal_id1 = CASE WHEN '".$_POST[$signal_id1]."' = '' THEN NULL ELSE '".$_POST[$signal_id1]."' END
						,	signal_id2 = CASE WHEN '".$_POST[$signal_id2]."' = '' THEN NULL ELSE '".$_POST[$signal_id2]."' END
						,	signal_id3 = CASE WHEN '".$_POST[$signal_id3]."' = '' THEN NULL ELSE '".$_POST[$signal_id3]."' END
						WHERE id =".$_POST[$theme_tb_id];
				
				echo $qry."<br>";
				$mysqli->query($qry);
			}

		}

		// 등록된 테마 데이타 중 중복건 확인해서 정리
		// 진행중으로 변경
		$qry = "UPDATE siriens_evening_theme
				SET   dup_check  = 'X'
				WHERE report_date ='".$_POST['report_date']."'";
		echo $qry."<br>";
		$mysqli->query($qry);

		// 테마,이슈,키워드 진행 맥스아이디에 OK 업데이트
		$qry = "UPDATE siriens_evening_theme A
				INNER JOIN (SELECT max_id, max_signal_id1, max_signal_id2, max_signal_id3
							FROM (SELECT MAX(id) max_id, MAX(signal_id1) max_signal_id1, MAX(signal_id2) max_signal_id2, MAX(signal_id3) max_signal_id3 
									FROM siriens_evening_theme 
									WHERE report_date = '".$_POST['report_date']."'
									GROUP BY theme, issue, keyword ) Z
							) B
				ON B.max_id = A.id
				SET   A.signal_id1 = B.max_signal_id1
					, A.signal_id2 = B.max_signal_id2
					, A.signal_id3 = B.max_signal_id3
					, A.dup_check  = 'O'";
		echo $qry."<br>";
		$mysqli->query($qry);

		$qry = "DELETE FROM siriens_evening_theme
				WHERE report_date ='".$_POST['report_date']."'
				AND	  dup_check != 'O'";
		echo $qry."<br>";
		$mysqli->query($qry);

	}
	// 오늘의 테마 등록
	else if($_POST['proc_fg'] == 'themePick') {
		for($i=0; $i<$_POST['cnt']; $i++){
			$check	= 'check'.$i;
			$theme_tb_id= 'theme_tb_id'.$i;
			
			if(isset($_POST[$check])) $today_theme_yn = 'Y';
			else $today_theme_yn = 'N';

			$qry = "UPDATE siriens_evening_theme
					SET	  today_theme_yn  = '$today_theme_yn'
					WHERE id =".$_POST[$theme_tb_id];
			echo $qry."<br>";
			$mysqli->query($qry);
		}
	}
	
}

$mysqli->close();
?>
</body>
<?php

if(isset($_POST['regi_id'])) {
	if($_POST['regi_id'] == ! 'nomad') {
		//echo "<script>window.parent.parent.searchMochaten('".$_POST['code']."');</script>";
	}
}
?>
</html>