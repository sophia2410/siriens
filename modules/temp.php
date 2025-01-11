tinymce.init({
    selector: '#thought',
    height: 700,
    plugins: [
        'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'image', 'link', 'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount'
    ],
    toolbar: 'formatselect | bold italic underline | alignleft aligncenter alignright alignjustify | link image | numlist bullist | removeformat',
    menubar: false,
    branding: false,
    license_key: 'gpl',
    setup: function (editor) {
        editor.on('init', function () {
            document.addEventListener('touchstart', function () {}, { passive: true });
            document.addEventListener('touchmove', function () {}, { passive: true });
        });
    }
});
