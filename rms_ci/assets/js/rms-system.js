(function ($) {
    'use strict';

    // Cache page configuration and frequently used elements.
    var config = window.RMS_SYSTEM || {};
    var $form = $('#system-settings-form');
    var $error = $('#settings-error');
    var $save = $('#settings-save');
    var $message = $('#system-message');

    /**
     * Store the renewed CSRF token returned by CodeIgniter.
     */
    function updateCsrf(response) {
        if (response && response.csrfName && response.csrfHash) {
            config.csrfName = response.csrfName;
            config.csrfHash = response.csrfHash;
            $form.find('input[name="' + response.csrfName + '"]').val(response.csrfHash);
        }
    }

    /**
     * Display a validation or server error above the action footer.
     */
    function showError(message) {
        $error
            .text(message)
            .addClass('show');
    }

    // Submit settings without reloading the complete admin page.
    $form.on('submit', function (event) {
        event.preventDefault();

        $error.removeClass('show').text('');
        $save
            .prop('disabled', true)
            .addClass('loading')
            .text('Saving...');

        $.ajax({
            url: config.save,
            type: 'POST',
            dataType: 'json',
            data: $form.serialize()
        })
            .done(function (response) {
                updateCsrf(response);

                if (!response.success) {
                    showError(response.message || 'The settings could not be saved.');

                    if (response.field) {
                        $('#' + response.field).focus();
                    }

                    return;
                }

                $('#system-message-text').text(response.message);
                $message.prop('hidden', false).addClass('open');
            })
            .fail(function () {
                showError('The server could not process the request. Please try again.');
            })
            .always(function () {
                $save
                    .prop('disabled', false)
                    .removeClass('loading')
                    .html('<span>✓</span> Update settings');
            });
    });

    // Close the success confirmation only from its explicit Done button.
    $('#system-message-close').on('click', function () {
        $message.removeClass('open').prop('hidden', true);
    });

    /* TEMPORARY QA TOOL: explicit typed confirmation before Documents-only reset. */
    var $clearConfirmation = $('#document-clear-confirmation');
    var $clearButton = $('#document-clear-submit');
    var $clearError = $('#document-clear-error');

    $clearConfirmation.on('input', function () {
        $clearButton.prop('disabled', $(this).val() !== 'CLEAR DOCUMENTS');
    });

    $clearButton.on('click', function () {
        if ($clearConfirmation.val() !== 'CLEAR DOCUMENTS') {
            return;
        }

        if (!window.confirm('Permanently clear ALL Documents-module records and stored document files? This cannot be undone.')) {
            return;
        }

        var request = { confirmation: 'CLEAR DOCUMENTS' };
        if (config.csrfName && config.csrfHash) {
            request[config.csrfName] = config.csrfHash;
        }

        $clearError.removeClass('show').text('');
        $clearButton.prop('disabled', true).text('Clearing Documents...');

        $.ajax({
            url: config.clearDocuments,
            type: 'POST',
            dataType: 'json',
            data: request
        }).done(function (response) {
            updateCsrf(response);
            if (!response || response.success !== true) {
                $clearError.text(response && response.message ? response.message : 'Documents data could not be cleared.').addClass('show');
                return;
            }

            $('#system-message-text').text(response.message);
            $message.prop('hidden', false).addClass('open');
            $clearConfirmation.val('');
        }).fail(function () {
            $clearError.text('The server could not clear Documents data.').addClass('show');
        }).always(function () {
            $clearButton.prop('disabled', $clearConfirmation.val() !== 'CLEAR DOCUMENTS').text('Clear all Documents data');
        });
    });
}(jQuery));
