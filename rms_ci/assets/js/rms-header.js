/*
 * Shared admin header behavior
 *
 * Controls only the signed-in user dropdown. Sidebar behavior remains in
 * rms-dashboard.js, while module-specific behavior remains in its module file.
 */
(function () {
    'use strict';

    var userButton = document.getElementById('user-menu-button');
    var userDropdown = document.getElementById('user-dropdown');

    // Open or close the profile dropdown and keep ARIA state synchronized.
    function setUserMenu(open) {
        if (!userButton || !userDropdown) {
            return;
        }

        userDropdown.className = open ? 'user-dropdown is-open' : 'user-dropdown';
        userButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Toggle the profile dropdown without triggering the document click handler.
    if (userButton) {
        userButton.onclick = function (event) {
            event.stopPropagation();
            setUserMenu(userButton.getAttribute('aria-expanded') !== 'true');
        };
    }

    // Close the dropdown when the user clicks outside it.
    document.addEventListener('click', function (event) {
        if (userDropdown && userButton
            && !userDropdown.contains(event.target)
            && !userButton.contains(event.target)) {
            setUserMenu(false);
        }
    });

    // Escape provides a keyboard-accessible way to close the dropdown.
    document.addEventListener('keydown', function (event) {
        event = event || window.event;

        if (event.keyCode === 27) {     
            setUserMenu(false);
        }
    });
}());
