<?php
header('Content-Type: application/json');

if (isset($_GET['url'])) {
    $url = $_GET['url'];

    // URL에서 HTML을 가져옴
    $html = file_get_contents($url);

    // 제목 추출
    if (preg_match("/<title>(.*)<\/title>/i", $html, $matches)) {
        $title = $matches[1];
        echo json_encode(['success' => true, 'title' => $title, 'url' => $url]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>
