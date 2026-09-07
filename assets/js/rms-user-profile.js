(function () {
    'use strict';

    /* Toggle each optional profile password without changing its value. */
    var passwordToggles = document.querySelectorAll('[data-profile-password-toggle]');
    Array.prototype.forEach.call(passwordToggles, function (toggle) {
        toggle.addEventListener('click', function () {
            var input = document.getElementById(toggle.getAttribute('data-profile-password-toggle'));
            if (!input) {
                return;
            }

            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            toggle.classList.toggle('is-visible', reveal);
            input.focus();
        });
    });

    /* Prevent repeated profile submissions while the server saves changes. */
    var profileForm = document.getElementById('portal-profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function () {
            var saveButton = profileForm.querySelector('.profile-save-button');
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.textContent = 'Saving changes...';
            }
        });
    }
}());
