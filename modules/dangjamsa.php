<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/common/database.php';

// headline이 없는 데이터만 조회
$sql = "
    SELECT
        id,
        issue_date,
        video_id,
        title
    FROM market_issue
    WHERE headline IS NULL
      AND video_id IS NOT NULL
      AND video_id <> ''
    ORDER BY issue_date desc, id desc 
    LIMIT 20
";

$result = $mysqli->query($sql);
?>

<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>Market Issue Thumbnail</title>

<style>
body {
    margin: 20px;
    background: #222;
    color: #fff;
    font-family: Arial, sans-serif;
}

.grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.item {
    background: #333;
    padding: 10px;
    border-radius: 6px;
}

.item img {
    width: 100%;
    display: block;
}

.info {
    margin-bottom: 8px;
    line-height: 1.5;
}

.id {
    font-size: 18px;
    font-weight: bold;
}

.date {
    color: #bbb;
}

.title {
    margin-top: 6px;
    font-size: 14px;
    color: #ddd;
}
</style>
</head>

<body>

<div class="grid">

<?php while ($row = $result->fetch_assoc()): ?>

    <?php
    $id       = (int)$row['id'];
    $videoId  = $row['video_id'];
    $issueDate = $row['issue_date'];
    $title    = $row['title'];
    ?>

    <div class="item">

        <div class="info">

            <div class="id">
                ID <?= $id ?>
            </div>

            <div class="date">
                <?= htmlspecialchars($issueDate) ?>
            </div>

            <div class="title">
                <?= htmlspecialchars($title) ?>
            </div>

        </div>

        <img
            src="https://i.ytimg.com/vi/<?= htmlspecialchars($videoId) ?>/maxresdefault.jpg"
            onerror="
                this.onerror=null;
                this.src='https://i.ytimg.com/vi/<?= htmlspecialchars($videoId) ?>/hqdefault.jpg';
            "
        >

    </div>

<?php endwhile; ?>

</div>

</body>
</html>