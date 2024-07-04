<!DOCTYPE html>
<html>
<head>
    <title>Sector Keywords</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <style>
        .accordion {
            cursor: pointer;
            transition: background-color 0.6s ease;
            padding: 10px;
            margin-bottom: 5px;
            border: 1px solid #ccc;
        }
        .panel {
            display: none;
            overflow: hidden;
            padding: 10px;
            border: 1px solid #ccc;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>섹터</h2>
        <div id="sectorList">
            <?php
            $sectors = ["IP/엔터", "IT/기술", "가상자산", "가상현실", "로봇", "반도체", "2차전지", "국제", "미래모빌리티", "바이오", "방산", "소비", "신물질", "에너지", "운송", "원자재", "정부정책", "정치", "헷징"];
            foreach ($sectors as $sector) {
                echo "<div>";
                echo "<div class='accordion' onclick='togglePanel(this, \"$sector\")'>$sector</div>";
                echo "<div class='panel' id='panel-$sector'></div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <script>
        function togglePanel(element, sector) {
            var panel = document.getElementById('panel-' + sector);
            if (panel.style.display === "block") {
                panel.style.display = "none";
            } else {
                $.ajax({
                    url: 'fetch_keywords.php',
                    type: 'POST',
                    data: { sector: sector },
                    success: function(response) {
                        if (response.error) {
                            panel.innerHTML = "<div class='alert alert-danger'>" + response.error + "</div>";
                        } else {
                            var content = "<ul class='list-group'>";
                            response.forEach(function(keyword) {
                                content += "<li class='list-group-item' onclick='xrayTick(\"sophiaWatchlist\", \"1 최근테마☆\", \"" + keyword.category + "\")'>" + keyword.category + "</li>";
                            });
                            content += "</ul>";
                            panel.innerHTML = content;
                        }
                        panel.style.display = "block";
                    },
                    error: function() {
                        panel.innerHTML = "<div class='alert alert-danger'>Error fetching data</div>";
                        panel.style.display = "block";
                    }
                });
            }
        }
    </script>
</body>
</html>
