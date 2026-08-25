(function () {
    'use strict';
    var message = document.querySelector('textarea[name="error_message"]');
    var preview = document.getElementById('djog-preview-text');
    if (message && preview) {
        var update = function () {
            preview.textContent = message.value || 'Your custom protection message will appear here.';
        };
        message.addEventListener('input', update);
        update();
    }
}());
