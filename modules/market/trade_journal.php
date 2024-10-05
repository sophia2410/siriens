
<?php
$pageTitle = "주식 매매일지";
require($_SERVER['DOCUMENT_ROOT']."/modules/common/common_header.php");
require($_SERVER['DOCUMENT_ROOT']."/modules/market/event_list.php");

// 이미지 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    $imageName = basename($_FILES['image']['name']);
    $uploadFile = $uploadDir . $imageName;

    // 이미지 파일 업로드 처리
    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
        // 업로드 성공 시 JSON으로 URL 반환
        echo json_encode(['url' => '/uploads/' . $imageName]);
        exit;  // 추가: JSON 응답 후 스크립트 종료
    } else {
        // 업로드 실패 시 JSON으로 에러 메시지 반환
        http_response_code(500);
        echo json_encode(['error' => '이미지 업로드 실패']);
        exit;  // 추가: JSON 응답 후 스크립트 종료
    }
}

// HTML 렌더링 코드는 이미지 업로드 요청이 아닐 때만 실행됩니다.
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>주식 매매일지</title>
    <style>
        #editor {
            width: 100%;
            min-height: 200px;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 20px;
        }

        .journal-entry {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
        }

        .journal-image {
            max-width: 300px;
            height: auto;
        }

        .journal-comment {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>주식 매매일지 작성</h1>

    <!-- 이미지 복사/붙여넣기 가능한 에디터 영역 -->
    <div id="editor" contenteditable="true" placeholder="여기에 이미지나 텍스트를 복사하여 붙여넣으세요"></div>

    <button id="saveButton">저장</button>

    <h2>저장된 매매일지</h2>
    <!-- 저장된 일지 목록 표시 -->
    <div id="journalList"></div>

    <script>
        document.getElementById('editor').addEventListener('paste', function (event) {
            const clipboardItems = event.clipboardData.items;
            for (let item of clipboardItems) {
                if (item.type.indexOf('image') !== -1) {
                    const blob = item.getAsFile();
                    const formData = new FormData();
                    formData.append('image', blob);

                    // 이미지 서버 업로드
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json()) // JSON 응답 처리
                    .then(data => {
                        if (data.url) {
                            const imgElement = document.createElement('img');
                            imgElement.src = data.url;
                            imgElement.className = 'journal-image';
                            document.getElementById('editor').appendChild(imgElement);
                        } else {
                            alert('이미지 업로드 실패');
                        }
                    })
                    .catch(error => {
                        console.error('이미지 업로드 중 오류 발생:', error);
                    });

                    event.preventDefault(); // 기본 붙여넣기 동작 방지
                }
            }
        });

        // 저장 버튼 클릭 처리
        document.getElementById('saveButton').addEventListener('click', function () {
            const content = document.getElementById('editor').innerHTML;
            console.log('저장된 내용:', content);
        });
    </script>
</body>
</html>
