(function () {
    'use strict';

    var toggle = document.getElementById('password-toggle');
    var password = document.getElementById('password');
    var form = document.getElementById('login-form');
    var accessModal = document.getElementById('access-restricted-modal');
    var accessModalClose = document.querySelector
        ? document.querySelector('[data-access-modal-close]')
        : null;

    /* Close the role-restriction dialog without changing authentication state. */
    function closeAccessModal() {
        if (!accessModal) {
            return;
        }

        accessModal.className = 'access-modal-backdrop';
        accessModal.setAttribute('aria-hidden', 'true');
    }

    if (accessModalClose) {
        accessModalClose.onclick = closeAccessModal;
        accessModalClose.focus();
    }

    if (toggle && password) {
        toggle.onclick = function () {
            var showPassword = password.type === 'password';

            password.type = showPassword ? 'text' : 'password';
            toggle.className = showPassword
                ? 'password-toggle is-visible'
                : 'password-toggle';
            toggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
            toggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            password.focus();
        };
    }

    if (form) {
        form.onreset = function () {
            window.setTimeout(function () {
                if (password) {
                    password.type = 'password';
                }

                if (toggle) {
                    toggle.className = 'password-toggle';
                    toggle.setAttribute('aria-pressed', 'false');
                    toggle.setAttribute('aria-label', 'Show password');
                }

                var username = document.getElementById('username');
                if (username) {
                    username.focus();
                }
            }, 0);
        };
    }

    document.addEventListener('keydown', function (event) {
        event = event || window.event;
        if (event.keyCode === 27) {
            closeAccessModal();
        }
    });
}());
