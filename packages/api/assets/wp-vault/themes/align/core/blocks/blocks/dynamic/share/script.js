document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.align-share-copy');
        if (!btn) return;
        e.preventDefault();

        var shareUrl = btn.getAttribute('data-share-url') || window.location.href;
        
        var copyToClipboard = function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            } else {
                var textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                return new Promise(function(resolve, reject) {
                    document.execCommand('copy') ? resolve() : reject();
                    textArea.remove();
                });
            }
        };

        copyToClipboard(shareUrl).then(function() {
            btn.classList.add('is-copied');
            setTimeout(function() {
                btn.classList.remove('is-copied');
            }, 2000);
        }).catch(function(err) {
            console.error('Failed to copy share link: ', err);
        });
    });
});
