<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Stock Market Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>2024.06.20(목) 삼성전자, 오늘부터 글로벌 전략회의... 대왕고래·화장품 등 개별주 장세</h1>
        </div>
        <div class="main-content">
            <div class="left-panel">
                <div class="market-comment">
                    <h2>Today Market Comment</h2>
                    <p>
                        <?php
                         $query = "SELECT evening_subject comment FROM market_report WHERE report_date = '20240618'";
                        $result = $mysqli->query($query);
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            echo nl2br(htmlspecialchars($row['comment'], ENT_QUOTES, 'UTF-8'));
                        } else {
                            echo "No comments available.";
                        }
                        ?>
                    </p>
                </div>
                <div class="stocks">
                    <h2>Today Stock</h2>
                    <div class="stock-section">
                        <h3>대왕고래</h3>
                        <ul>
                            <?php
                            $query = 'SELECT name, `change` FROM stocks WHERE category = "대왕고래"';
                            $result = $mysqli->query($query);
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($row['change'], ENT_QUOTES, 'UTF-8') . ")</li>";
                            }
                            ?>
                        </ul>
                    </div>
                    <div class="stock-section">
                        <h3>K-뷰티</h3>
                        <ul>
                            <?php
                            $query = 'SELECT name, `change` FROM stocks WHERE category = "K-뷰티"';
                            $result = $mysqli->query($query);
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($row['change'], ENT_QUOTES, 'UTF-8') . ")</li>";
                            }
                            ?>
                        </ul>
                    </div>
                    <div class="stock-section">
                        <h3>K-푸드</h3>
                        <ul>
                            <?php
                            $query = 'SELECT name, `change` FROM stocks WHERE category = "K-푸드"';
                            $result = $mysqli->query($query);
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($row['change'], ENT_QUOTES, 'UTF-8') . ")</li>";
                            }
                            ?>
                        </ul>
                    </div>
                    <div class="stock-section">
                        <h3>반도체</h3>
                        <ul>
                            <?php
                            $query = 'SELECT name, `change` FROM stocks WHERE category = "반도체"';
                            $result = $mysqli->query($query);
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($row['change'], ENT_QUOTES, 'UTF-8') . ")</li>";
                            }
                            ?>
                        </ul>
                    </div>
                    <div class="stock-section">
                        <h3>개별주</h3>
                        <ul>
                            <?php
                            $query = 'SELECT name, `change` FROM stocks WHERE category = "개별주"';
                            $result = $mysqli->query($query);
                            while ($row = $result->fetch_assoc()) {
                                echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($row['change'], ENT_QUOTES, 'UTF-8') . ")</li>";
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="right-panel">
                <div class="recent-themes">
                    <h2>최근 테마</h2>
                    <ul>
                        <li>대왕고래
                            <ul>
                                <?php
                                // 대왕고래 테마의 관련 종목들
                                $query = 'SELECT name FROM stocks WHERE category = "대왕고래"';
                                $result = $mysqli->query($query);
                                while ($row = $result->fetch_assoc()) {
                                    echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</li>";
                                }
                                ?>
                            </ul>
                        </li>
                        <li>K-뷰티
                            <ul>
                                <?php
                                // K-뷰티 테마의 관련 종목들
                                $query = 'SELECT name FROM stocks WHERE category = "K-뷰티"';
                                $result = $mysqli->query($query);
                                while ($row = $result->fetch_assoc()) {
                                    echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</li>";
                                }
                                ?>
                            </ul>
                        </li>
                        <li>K-푸드
                            <ul>
                                <?php
                                // K-푸드 테마의 관련 종목들
                                $query = 'SELECT name FROM stocks WHERE category = "K-푸드"';
                                $result = $mysqli->query($query);
                                while ($row = $result->fetch_assoc()) {
                                    echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</li>";
                                }
                                ?>
                            </ul>
                        </li>
                        <li>반도체
                            <ul>
                                <?php
                                // 반도체 테마의 관련 종목들
                                $query = 'SELECT name FROM stocks WHERE category = "반도체"';
                                $result = $mysqli->query($query);
                                while ($row = $result->fetch_assoc()) {
                                    echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</li>";
                                }
                                ?>
                            </ul>
                        </li>
                    </ul>
                </div>
                <div class="top-gainers">
                    <h2>상한가</h2>
                    <ul>
                        <?php
                        // 상한가 종목들
                        $query = 'SELECT name FROM top_gainers';
                        $result = $mysqli->query($query);
                        while ($row = $result->fetch_assoc()) {
                            echo "<li>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>