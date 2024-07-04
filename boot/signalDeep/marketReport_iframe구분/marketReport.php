<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

	$report_date   = (isset($_GET['report_date']) )  ? $_GET['report_date']   : date('Ymd',time());

	// Fetch dates for the select box
	$query = "
	SELECT date report_date, CONCAT(yyyy, '년 ', mm, '월 ', dd, '일 ') report_date_str
	FROM calendar
	WHERE date <= NOW()
	ORDER BY date DESC
	LIMIT 50";

	$result = $mysqli->query($query);
	$dates = [];
	while($row = $result->fetch_assoc()) {
		$dates[] = $row;
	}

?>
	<style>
        body, html {
            height: 100%;
            margin: 0;
            display: flex;
        }
        .split {
            height: 100%;
            position: fixed;
        }
        .left {
            width: 80%; /* 4/5 of the screen width */
            left: 0;
        }
        .right {
            width: 20%; /* 1/5 of the screen width */
            right: 0;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        #controls {
            top: 10px;
            left: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        #evening_subject {
            margin-left: 10px;
        }
    </style>
    <script>
        function search() {
            var selectedDate = document.getElementById('report_date').value;
            document.getElementById('leftFrame').src = 'marketReport_L.php?report_date=' + selectedDate;
            document.getElementById('rightFrame').src = 'marketReport_R.php?report_date=' + selectedDate;

            // AJAX request to get evening_subject
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'api.php?action=get_evening_subject&report_date=' + selectedDate, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.getElementById('evening_subject').innerText = xhr.responseText;
                }
            };
            xhr.send();
        }

        window.onload = function() {
            var select = document.getElementById('report_date');
            if (select.options.length > 0) {
                var initialDate = select.options[0].value;
                select.value = initialDate;
                search();
            }
        }
    </script>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_siriens.php");
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">
	
	<div id="controls">
        <select id="report_date">
            <?php foreach($dates as $date): ?>
                <option value="<?= $date['report_date'] ?>"><?= $date['report_date_str'] ?></option>
            <?php endforeach; ?>
        </select>
        <input type="button" class="btn btn-danger btn-sm" value="조 회" onclick="search()">
        <span id="evening_subject"></span>
    </div>

        <div class="split left">
            <iframe id="leftFrame" src="marketReport_L.php"></iframe>
        </div>
        <div class="split right">
            <iframe id="rightFrame" src="marketReport_R.php"></iframe>
        </div>

</div>
<!-- End of Main Content -->
</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->
</body>

<?php
if($_SERVER["HTTP_HOST"] == 'localhost') {
	$PATH = "http://localhost";
} else {
	$PATH = "https://siriens.mycafe24.com";
}
?>


<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
?>
</html>
