(function () {
    'use strict';

    var form = document.getElementById('backup-form');
    var openButton = document.getElementById('backup-open-confirm');
    var modal = document.getElementById('backup-confirm');
    var closeButton = document.getElementById('backup-confirm-close');
    var cancelButton = document.getElementById('backup-confirm-cancel');
    var downloadButton = document.getElementById('backup-confirm-download');
    var message = document.getElementById('backup-confirm-message');
    var error = document.getElementById('backup-client-error');

    if (!form || !modal) {
        return;
    }

    // Mirror radio selection with a class for browsers without :has support.
    Array.prototype.forEach.call(form.querySelectorAll('input[name="backup_mode"]'), function (radio) {
        radio.addEventListener('change', function () {
            Array.prototype.forEach.call(form.querySelectorAll('.backup-option'), function (option) {
                option.classList.remove('selected');
            });
            if (radio.checked) {
                radio.parentNode.classList.add('selected');
            }
        });
    });

    // Return the currently selected, enabled backup option.
    function selectedMode() {
        return form.querySelector('input[name="backup_mode"]:checked:not(:disabled)');
    }

    function showError(text) {
        error.textContent = text;
        error.hidden = false;
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        openButton.focus();
    }

    // Open only after a valid option is selected.
    openButton.addEventListener('click', function () {
        var selected = selectedMode();
        if (!selected) {
            showError('Choose a backup option before continuing.');
            return;
        }

        error.hidden = true;
        message.textContent = selected.value === 'database'
            ? 'The server will generate a downloadable SQL copy of the RMS database.'
            : 'The server will package the CI3 application and database into one ZIP file.';
        modal.hidden = false;
        document.body.classList.add('modal-open');
        downloadButton.focus();
    });

    closeButton.addEventListener('click', closeModal);
    cancelButton.addEventListener('click', closeModal);

    // Disable repeated clicks while the browser starts the file download.
    downloadButton.addEventListener('click', function () {
        downloadButton.disabled = true;
        downloadButton.textContent = 'Preparing backup...';
        form.submit();
    });

    // Intentionally ignore backdrop clicks and Escape to keep the modal locked.
}());
