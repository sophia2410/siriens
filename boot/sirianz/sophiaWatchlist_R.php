<?php
// 관심종목 + 시나리오 = 우측, 일자 뷰화면

require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
//var_dump($_SERVER);
?>
<head>
<link rel="shortcut icon" href="#">
<style>
	.content {
	height: 400px;
	border: 0px solid hsl(0, 0%, 75%);
	}
	.content > img {
	width: auto;
	height: 100%;
	}
	.scroll_box {
		width: 1800px;
		height: 183px;
		display: flex;
		overflow-x: auto;
	}
	table th, tr, td{
		padding: 0.1rem;
	}
</style>
</head>

<?php
$sector   = (isset($_GET['sector'])) ? $_GET['sector'] : '';
$theme 	  = (isset($_GET['theme']))  ? $_GET['theme'] : '';
$category = (isset($_GET['category'])) ? $_GET['category'] : '';

$brWidth = (isset($_GET['brWidth'])) ? $_GET['brWidth'] : '1800';
?>

<body>

<?php
if($sector == ''){
	echo "<h3></h3>";
} else {
	$query = "SELECT sector, theme, category, code, name, sort_theme, sort_stock, news_title, news_link,
					 CASE WHEN sophia_pick = 'Y' THEN '<font color=red><b>(★)</b></font>' ELSE '' END sophia_pick
			 FROM watchlist_sophia A
			 WHERE sector = '$sector'
			 AND theme LIKE CASE WHEN '$theme' != '' THEN '%".$theme."%' ELSE '%' END
			 AND category LIKE CASE WHEN '$category' != '' THEN '%".$category."%' ELSE '%' END
			ORDER BY sector, sort_theme, sort_stock";

	// echo "<pre>$query</pre>";
	$result = $mysqli->query($query);
	$j=0;
	$pre_category = '';
	while($row = $result->fetch_array(MYSQLI_BOTH)) {
		if($pre_category != $row['sector'].$row['theme'].$row['category']) {
			if($pre_category != '') {
				echo "</div>";
				$j=0;
			}
			echo "<div class='h5 font-weight-bold text-gray-800' style=' margin:0px; margin-top:10px; margin-bottom:10px;'>▷ ".$row['sector']." ".$row['theme']." ".$row['category']."</div>";
			echo "<div class='row'> ";
		}

		echo "<div class='col-xl-3 col-md-6 mb-4'>
				<div style='margin: 0; margin-left:10px'>
					<div class='row no-gutters align-items-center'>
						<div class='col mr-0'>
							<div class='font-weight-bold text-primary text-uppercase mb-1'>
								".$row['name']." ".$row['sophia_pick']."
							</div>
							<div style='margin: 0;'>
								<img class='img-fluid' src='https://ssl.pstatic.net/imgfinance/chart/item/candle/day/".$row['code'].".png?sidcode=1681518352718'>
							</div>
						</div>
					</div>
				</div>
				</div>";

		$j++;

		if($j%4 == 0) {
			echo "</div>";
			echo "<div class='row'>";
		}

		$pre_category  = $row['sector'].$row['theme'].$row['category'];
	}
	echo "</div>";
}
?>
</html>