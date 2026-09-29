(function () {
    'use strict';

    var form = document.getElementById('portal-profile-form');
    var password = document.getElementById('profile-new-password');
    var confirmation = document.getElementById('profile-confirm-password');
    var saveButton = form ? form.querySelector('.profile-save-button') : null;
    var strength = document.querySelector('[data-profile-strength]');
    var strengthLabel = document.querySelector('[data-profile-strength-label]');
    var completeness = document.querySelector('[data-profile-completeness]');
    var completionValue = document.querySelector('[data-profile-completeness-value]');
    var completionMessage = document.querySelector('[data-profile-completeness-message]');
    var successAlert = document.querySelector('.profile-alert.is-success');
    var completionKey = '';

    function hasClass(element, name) {
        return element && new RegExp('(^|\\s)' + name + '(\\s|$)').test(element.className);
    }

    function setClass(element, name, enabled) {
        if (!element) { return; }
        if (enabled && !hasClass(element, name)) {
            element.className += ' ' + name;
        } else if (!enabled) {
            element.className = element.className.replace(new RegExp('(^|\\s)' + name + '(?=\\s|$)', 'g'), ' ').replace(/^\s+|\s+$/g, '');
        }
    }

    function safeStorageGet(key) {
        try { return window.localStorage.getItem(key); } catch (error) { return null; }
    }

    function safeStorageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (error) { return; }
    }

    function showCompleteness(percent) {
        var complete = percent === 100;
        if (!completeness || !completionValue) { return; }
        completionValue.innerHTML = percent + '%';
        completeness.setAttribute('aria-label', 'Profile completeness ' + percent + ' percent');
        setClass(completeness, 'is-complete', complete);
        if (completionMessage) {
            completionMessage.innerHTML = complete ? 'Your profile setup is complete and up to date.' : 'Almost there! Keep your profile up to date for a better experience.';
        }
    }

    if (completeness) {
        completionKey = 'rmsProfileComplete:' + (completeness.getAttribute('data-profile-user') || 'user');
        if (successAlert) { safeStorageSet(completionKey, '100'); }
        showCompleteness(safeStorageGet(completionKey) === '100' ? 100 : 80);
    }

    function passwordRules(value) {
        return {
            length: value.length >= 8,
            case: /[a-z]/.test(value) && /[A-Z]/.test(value),
            number: /[0-9]/.test(value),
            special: /[^A-Za-z0-9]/.test(value)
        };
    }

    function updateRule(name, met) {
        var item = document.querySelector('[data-password-rule="' + name + '"]');
        setClass(item, 'is-met', met);
    }

    function updatePasswordState() {
        if (!password || !confirmation) { return true; }

        var value = password.value;
        var confirmValue = confirmation.value;
        var rules = passwordRules(value);
        var names = ['length', 'case', 'number', 'special'];
        var score = 0;
        var bars = strength ? strength.getElementsByTagName('i') : [];
        var i;

        for (i = 0; i < names.length; i += 1) {
            if (rules[names[i]]) { score += 1; }
            updateRule(names[i], rules[names[i]]);
            if (bars[i]) { setClass(bars[i], 'is-active', i < score); }
        }

        if (strengthLabel) {
            strengthLabel.innerHTML = value === '' ? 'Password strength: —' : 'Password strength: ' + ['Weak', 'Weak', 'Fair', 'Good', 'Strong'][score];
        }

        var optionalBlank = value === '' && confirmValue === '';
        var strongEnough = score === 4;
        var matches = value !== '' && confirmValue === value;
        var valid = optionalBlank || (strongEnough && matches);
        var confirmShell = confirmation.parentNode;

        setClass(confirmShell, 'is-valid', !optionalBlank && matches);
        setClass(confirmShell, 'is-invalid', confirmValue !== '' && !matches);

        if (saveButton) {
            saveButton.disabled = !valid;
            saveButton.setAttribute('aria-disabled', valid ? 'false' : 'true');
        }
        return valid;
    }

    function togglePassword(button) {
        var target = document.getElementById(button.getAttribute('data-profile-password-toggle'));
        if (!target) { return; }
        var reveal = target.type === 'password';
        target.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        target.focus();
    }

    document.addEventListener('click', function (event) {
        var node = event.target;
        while (node && node !== document && !node.getAttribute('data-profile-password-toggle')) { node = node.parentNode; }
        if (node && node !== document) {
            event.preventDefault();
            togglePassword(node);
        }
    }, false);

    if (password) { password.addEventListener('input', updatePasswordState, false); }
    if (confirmation) { confirmation.addEventListener('input', updatePasswordState, false); }

    if (form) {
        form.addEventListener('reset', function () {
            window.setTimeout(updatePasswordState, 0);
        }, false);

        form.addEventListener('submit', function (event) {
            if (!updatePasswordState()) {
                event.preventDefault();
                if (password) { password.focus(); }
            }
        }, false);
    }

    updatePasswordState();
}());
