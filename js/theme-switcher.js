/**
 * Theme Switcher — Idol Stage Timetable
 * Loads theme CSS override files and persists selection to localStorage.
 * Themes: sakura (default), ocean, forest, midnight, sunset, dark, gray
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'idol-theme';
    var DEFAULT = 'sakura';
    var VALID_THEMES = ['sakura', 'ocean', 'forest', 'midnight', 'sunset', 'dark', 'gray'];
    var themeLink = null;

    function applyTheme(name) {
        if (VALID_THEMES.indexOf(name) === -1) {
            name = DEFAULT;
        }

        // Remove previous theme override CSS
        if (themeLink && themeLink.parentNode) {
            themeLink.parentNode.removeChild(themeLink);
            themeLink = null;
        }

        // sakura = default, no extra file needed
        if (name !== DEFAULT) {
            var switcher = document.querySelector('.theme-switcher');
            var version = switcher ? (switcher.getAttribute('data-version') || '1') : '1';
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.id = 'theme-override';
            link.href = 'styles/themes/' + name + '.css?v=' + version;
            document.head.appendChild(link);
            themeLink = link;
        }

        // Update active button state
        var buttons = document.querySelectorAll('.theme-btn');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            if (btn.getAttribute('data-theme') === name) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }

        localStorage.setItem(STORAGE_KEY, name);
    }

    function attachHandlers() {
        var buttons = document.querySelectorAll('.theme-btn');
        for (var i = 0; i < buttons.length; i++) {
            (function (btn) {
                btn.addEventListener('click', function () {
                    applyTheme(btn.getAttribute('data-theme'));
                });
            })(buttons[i]);
        }
    }

    function init() {
        var saved = localStorage.getItem(STORAGE_KEY) || DEFAULT;
        applyTheme(saved);
        attachHandlers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
