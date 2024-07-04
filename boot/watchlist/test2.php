<!DOCTYPE html>
<html>
<head>
    <title>Sector and Theme Keywords</title>
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
        <h2>섹터와 테마</h2>
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#sectors">섹터</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#themes">테마</a>
            </li>
        </ul>

        <div class="tab-content">
            <div id="sectors" class="container tab-pane active"><br>
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
            <div id="themes" class="container tab-pane fade"><br>
                <div id="themeList">
                    <!-- 테마 리스트가 여기에 로드됩니다. -->
                </div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            loadThemes();
        });

        function loadThemes() {
            $.ajax({
                url: 'fetch_themes.php',
                type: 'GET',
                success: function(response) {
                    var themeList = $('#themeList');
                    if (response.error) {
                        themeList.html("<div class='alert alert-danger'>" + response.error + "</div>");
                    } else {
                        var content = "<ul class='list-group'>";
                        response.forEach(function(theme) {
                            content += "<li class='list-group-item'>" + theme.theme_nm + "</li>";
                        });
                        content += "</ul>";
                        themeList.html(content);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error: " + status + " - " + error);
                    $('#themeList').html("<div class='alert alert-danger'>Error fetching data</div>");
                }
            });
        }

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
                                content += "<li class='list-group-item' onclick='xrayTick(\"sophiaWatchlist\", \"1 최근테마☆\", \"" + keyword.keyword + "\")'>" + keyword.keyword + "</li>";
                            });
                            content += "</ul>";
                            panel.innerHTML = content;
                        }
                        panel.style.display = "block";
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error: " + status + " - " + error);
                        panel.innerHTML = "<div class='alert alert-danger'>Error fetching data</div>";
                        panel.style.display = "block";
                    }
                });
            }
        }
    </script>
</body>
</html>
