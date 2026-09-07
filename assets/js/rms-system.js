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
}(jQuery));
