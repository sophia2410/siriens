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
	if($_POST['proc_fg'] == 'updateThemeOne') {
		
		if($_POST['sector'] != $_POST['org_sector'] || $_POST['theme'] != $_POST['org_theme']) {

			$mod_sector = $_POST['sector'];
			$mod_theme  = $_POST['theme'];
			$org_sector = $_POST['org_sector'];
			$org_theme  = $_POST['org_theme'];
			
			$qry = "UPDATE 0day_stocks
			SET sector	= '$mod_sector'
			,	theme	= '$mod_theme'
			WHERE sector='$org_sector' 
			AND   theme	='$org_theme'";

			echo $qry."<br><br>";
			$mysqli->query($qry);

			echo '<script>
					window.parent.location.href="http://localhost/boot/siriens/scenario_PopT.php?sector='.$mod_sector.'&theme='.$mod_theme.'";
					window.parent.reload ();
				</script>';
		
		} else {
			echo '<script>alert("변경사항이 없습니다."); parent.focus();</script>';
		}
	} else if($_POST['proc_fg'] == 'updateThemeEach') {
		for($i=0; $i<$_POST['cnt']; $i++){
			// 시그널이브닝 테마,이슈,키워드 변경 정보 업데이트
			$hot_theme	= 'hot_theme'.$i;
			$sector		= 'sector'.$i;
			$theme		= 'theme'.$i;
			$issue		= 'issue'.$i;
			$hot_theme  = isset($_POST[$hot_theme]) ? 'Y' : 'N';
			$stock_keyword	= 'stock_keyword'.$i;
			$theme_comment	= 'theme_comment'.$i;
			$0day_date	= '0day_date'.$i;
			$code			= 'code'.$i;

			$qry = "UPDATE 0day_stocks
					SET sector		= '".$_POST[$sector]."'
					,	theme		= '".$_POST[$theme]."'
					,	issue		= '".$_POST[$issue]."'
					, 	hot_theme	= '$hot_theme'
					, 	theme_comment= '".$_POST[$theme_comment]."'
					WHERE 0day_date ='".$_POST[$0day_date]."'
					AND code ='".$_POST[$code]."'";
			echo $qry."<br>";
			$mysqli->query($qry);
		}
		$sector = $_POST['sector'];
		$theme  = $_POST['theme'];
	
		echo '<script>
				window.parent.location.href="http://localhost/boot/siriens/scenario_PopT.php?sector='.$sector.'&theme='.$theme.'";
				window.parent.reload ();
			</script>';
	}
}

$mysqli->close();
?>
</body>
</html>