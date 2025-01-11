<?php
function loadTinyMCE($selector = '#thought', $height = 700) {
?>
    <!-- TinyMCE JS 파일 포함 -->
    <script src='/tinymce/js/tinymce/tinymce.min.js' referrerpolicy='origin'></script>
    <script>
        var plugins = [
                'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'image', 'link', 'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount',
                'checklist', 'mediaembed', 'casechange', 'export', 'formatpainter', 'pageembed', 'a11ychecker', 'tinymcespellchecker', 'permanentpen', 'powerpaste', 'advtable', 'advcode', 'editimage', 'advtemplate', 'ai', 'mentions', 'tinycomments', 'tableofcontents', 'footnotes', 'mergetags', 'autocorrect', 'typography', 'inlinecss', 'markdown'
            ];
        var edit_toolbar = 'formatselect fontselect fontsizeselect | forecolor backcolor | bold italic underline strikethrough | blocks fontfamily fontsize |link image media table mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat';

        tinymce.init({
            selector: '<?php echo $selector; ?>', // 고유 셀렉터 적용
            height: <?php echo $height; ?>, // 동적 높이 적용
            content_css: false,  // TinyMCE에서 자동으로 로드하는 스타일 비활성화
            menubar: false,
            plugins: plugins,
            content_css: '/tinymce/tinymce-my-styles.css',
            toolbar: edit_toolbar,
            tinycomments_mode: 'embedded',
            tinycomments_author: 'Author name'
        });
    </script>
    <?php
}

// TinyMCE 관련 공통 스크립트 로딩
function loadTinyMCEScripts() {
    echo "
    <script>
        // TinyMCE 에디터에 데이터 설정
        function setTinyMCEContent(selector, content) {
            tinymce.get(selector).setContent(content);
        }

        // TinyMCE 데이터 동기화
        function syncTinyMCEData() {
            tinymce.triggerSave();
        }

        // 폼 유효성 검사 및 제출 전 TinyMCE 내용 동기화
        function validateTinyMCEForm(selector) {
            var content = tinymce.get(selector).getContent().trim();
            if (content === '') {
                alert('내용을 입력해주세요.');
                return false;
            }
            syncTinyMCEData();  // TinyMCE의 내용을 textarea에 동기화
            return true;
        }
    </script>
    ";
}
?>