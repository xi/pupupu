(function() {
    var trans = function(s) {
        return s;
    };

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
        this.style.height = '0';
        this.style.height = this.scrollHeight + this.offsetHeight + 'px';
    };

    on(document, 'input', 'textarea', resize);
    document.querySelectorAll('textarea').forEach(function(e) {
        resize.call(e);
    });

    on(document, 'click', '[name="delete"]', function(event) {
        if (!window.confirm(trans('Are you sure you want to delete this?'))) {
            event.preventDefault();
        }
    });
})()
