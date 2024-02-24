<?php
if($_POST['downfile'] == 'excel') {
	// 파이썬 스크립트 실행
	echo "엑셀 다운로드 호출성공";
	$command = 'C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe E:/Project/202410/www/pyObsidian/Watchlist_DownExcel.py';
	$output = shell_exec($command);
} else if ($_POST['downfile'] == 'markdown') {
	echo "옵시디언 파일생성 호출성공";
	$command = 'C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe E:/Project/202410/www/pyObsidian/Watchlist_DownMD.py';
	$output = shell_exec($command);
}

?>