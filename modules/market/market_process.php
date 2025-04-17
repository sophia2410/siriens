<?php
require($_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php");

// ------ 마켓오버뷰에 등록하는 한국경제 뉴스브리핑 활용해서 마켓 이슈 등록하기 추가 2025.03.14
// 🔹 네이버 뉴스 검색 함수
function searchNewsOnNaver($query) {
    global $naver_client_id, $naver_client_secret;

    // 특수문자 제거 후 UTF-8 변환
    $query = preg_replace('/[^\p{L}\p{N}\s]/u', '', $query);
    $query = trim($query);
    $query = mb_convert_encoding($query, "UTF-8", "auto"); 
    $query = urlencode($query);

    $url = "https://openapi.naver.com/v1/search/news.json?query={$query}&display=1&sort=sim";

    $headers = [
        "X-Naver-Client-Id: $naver_client_id",
        "X-Naver-Client-Secret: $naver_client_secret",
        "User-Agent: Mozilla/5.0"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return null;
    }

    return json_decode($response, true, 512, JSON_UNESCAPED_UNICODE);
}

// 🔹 `market_overview`에서 뉴스 제목 추출 및 `market_issues` 저장
function processMarketOverview($mysqli, $report_date) {
    $query = "SELECT market_overview FROM market_report WHERE date = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('s', $report_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        $html = $row['market_overview'];
    } else {
        $html = null;
    }
    
    $stmt->close();

    if (!$html) {
        return;
    }

    // 🔹 HTML 파싱하여 <table> 내부의 특정 태그에서 기사 제목 추출
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $tables = $dom->getElementsByTagName('table');
    if ($tables->length > 0) {
        $firstTable = $tables->item(0);
        $cells = $firstTable->getElementsByTagName('td');

        foreach ($cells as $cell) {
            $paragraphs = $cell->getElementsByTagName('p');

            foreach ($paragraphs as $p) {
                $issueTitle = trim(html_entity_decode($p->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // 🔹 <strong> 태그 안의 텍스트만 가져오기
                $strongTag = $p->getElementsByTagName('strong');
                if ($strongTag->length > 0) {
                    $issueTitle = trim(html_entity_decode($strongTag->item(0)->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }

                // 🔹 특수 공백 제거
                $issueTitle = preg_replace('/[\pZ\pC]+/u', ' ', $issueTitle);

                // 🔹 제목이 비어있으면 스킵
                if (strlen($issueTitle) == 0) {
                    continue;
                }

                // 🔹 중복 검사: `market_issues`에 같은 제목이 있는지 확인
                $check_query = "SELECT COUNT(*) AS cnt FROM market_issues WHERE issue_title = ?";
                $check_stmt = $mysqli->prepare($check_query);
                $check_stmt->bind_param('s', $issueTitle);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $row = $result->fetch_assoc();

                $count = $row ? $row['cnt'] : 0; // 데이터가 없으면 0으로 처리
                $check_stmt->close();

                if ($count > 0) {
                    continue; // 이미 존재하면 저장하지 않음
                }

                // 🔹 네이버 뉴스 검색
                $search_results = searchNewsOnNaver($issueTitle);
                $issueLink = "blank"; // 기본값 설정
                $issueContent = null;

                if (!empty($search_results['items'])) {
                    $first_news = $search_results['items'][0];
                    $issueContent = html_entity_decode(strip_tags($first_news['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $issueLink = $first_news['link'];
                }

                // 🔹 market_issues에 데이터 삽입
                $insert_query = "INSERT INTO market_issues (issue_title, issue_link, issue_content, date) VALUES (?, ?, ?, ?)";
                $insert_stmt = $mysqli->prepare($insert_query);
                $insert_stmt->bind_param('ssss', $issueTitle, $issueLink, $issueContent, $report_date);
                $insert_stmt->execute();

                // 🔹 생성된 issue_id 가져오기
                $issueId = $mysqli->insert_id;
                $insert_stmt->close();

                // 🔹 issue_id가 정상적으로 생성되었으면 keyword_issue_mappings 테이블에 등록
                if ($issueId > 0) {
                    $keyword_id = 608; //#한경브리핑

                    $mappingStmt = $mysqli->prepare("INSERT INTO keyword_issue_mappings (issue_id, keyword_id) VALUES (?, ?)");
                    $mappingStmt->bind_param('ii', $issueId, $keyword_id);
                    $mappingStmt->execute();
                    $mappingStmt->close();
                }
            }
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'Report updated successfully']);
}
// ------ 마켓오버뷰에 등록하는 한국경제 뉴스브리핑 활용해서 마켓 이슈 등록하기

// 🔹 `market_report` 저장 후 뉴스 검색 실행
if ($_GET['action'] == 'save_report') {
    if (isset($_POST['report_date']) && isset($_POST['market_overview']) && isset($_POST['market_review'])) {
        $report_date = $_POST['report_date'];
        $market_overview = $_POST['market_overview'];
        $market_review = $_POST['market_review'];
        $morning_brief = $_POST['morning_brief'];
        $evening_brief = $_POST['evening_brief'];
        $evening_report_title = trim($_POST['evening_report_title']);

        $check_query = "SELECT COUNT(*) FROM market_report WHERE date = ?";
        $stmt = $mysqli->prepare($check_query);
        $stmt->bind_param('s', $report_date);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $update_query = "UPDATE market_report SET market_overview = ?, market_review = ?, morning_brief = ?, evening_brief = ?, evening_report_title = CASE WHEN ? != '' THEN ? ELSE evening_report_title END WHERE date = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param('sssssss', $market_overview, $market_review, $morning_brief, $evening_brief, $evening_report_title, $evening_report_title, $report_date);
            $stmt->execute();
            $stmt->close();
        } else {
            $insert_query = "INSERT INTO market_report (date, market_overview, market_review, morning_brief, evening_brief, evening_report_title) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($insert_query);
            $stmt->bind_param('ssssss', $report_date, $market_overview, $market_review, $morning_brief, $evening_brief, $evening_report_title);
            $stmt->execute();
            $stmt->close();
        }

        // 🔹 뉴스 검색 및 `market_issues` 저장 실행
        processMarketOverview($mysqli, $report_date);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    }
}

$mysqli->close();
?>