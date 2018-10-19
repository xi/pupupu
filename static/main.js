(function() {
    var on = function(element, eventType, selector, fn) {
        element.addEventListener(eventType, function(event) {
            var target = event.target.closest(selector);
            if (target && element.contains(target)) {
                return fn.call(target, event);
            }
        });
    };

    new SimpleMDE({
        element: document.querySelector('textarea[name="md"]'),
        spellChecker: false,
    });

    var resize = function(event) {
        /* 0-timeout to get the already changed text */
        setTimeout(() => {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 5 + 'px';
        }, 0);
    };

    on(document, 'init', 'textarea', resize);
    on(document, 'change', 'textarea', resize);
    on(document, 'keydown', 'textarea', resize);
    document.querySelectorAll('textarea').forEach(function(e) {
        resize.call(e);
    });

    on(document, 'click', '[name="delete"]', function(event) {
        if (!window.confirm('Are you sure you want to delete this?')) {
            event.preventDefault();
        }
    });
})()
