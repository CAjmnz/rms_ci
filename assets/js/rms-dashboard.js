/*
 * Shared admin sidebar behavior
 *
 * Controls the responsive sidebar and expandable navigation groups only.
 * Header dropdown behavior is isolated in assets/js/rms-header.js.
 */
(function () {
    'use strict';

    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('mobile-overlay');
    var openButton = document.getElementById('mobile-menu');
    var closeButton = document.getElementById('mobile-close');
    var expandButtons = document.querySelectorAll
        ? document.querySelectorAll('.nav-expand')
        : [];

    // Open or close the responsive sidebar and synchronize accessibility state.
    function setSidebar(open) {
        if (!sidebar || !overlay || !openButton) {
            return;
        }

        sidebar.className = open ? 'sidebar is-open' : 'sidebar';
        overlay.className = open ? 'mobile-overlay is-visible' : 'mobile-overlay';
        overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.className = open ? 'nav-open' : '';
    }

    // Open the sidebar from the header's mobile menu button.
    if (openButton) {
        openButton.onclick = function () {
            setSidebar(true);
        };
    }

    if (closeButton) {
        closeButton.onclick = function () {
            setSidebar(false);
        };
    }

    if (overlay) {
        overlay.onclick = function () {
            setSidebar(false);
        };
    }

    // Expand or collapse each sidebar submenu without reloading the page.
    for (var index = 0; index < expandButtons.length; index++) {
        expandButtons[index].onclick = function () {
            var targetId = this.getAttribute('data-target');
            var target = document.getElementById(targetId);
            var open = this.getAttribute('aria-expanded') === 'true';

            if (!target) {
                return;
            }

            this.setAttribute('aria-expanded', open ? 'false' : 'true');
            target.className = open ? 'nav-submenu' : 'nav-submenu is-open';
        };
    }

    // Escape closes the mobile sidebar for keyboard users.
    document.addEventListener('keydown', function (event) {
        event = event || window.event;

        if (event.keyCode === 27) {
            setSidebar(false);
        }
    });
}());
