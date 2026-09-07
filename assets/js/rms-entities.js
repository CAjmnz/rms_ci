(function ($) {
    'use strict';

    function selectedIds() {
        return $('.js-row-check:checked').map(function () {
            return this.value;
        }).get();
    }

    function showMessage(element, text, isError) {
        element.text(text).prop('hidden', false).toggleClass('is-error', isError);
    }

    $('.js-check-all').on('change', function () {
        $('.js-row-check').prop('checked', this.checked);
    });

    $('.js-edit').on('click', function () {
        var ids = selectedIds();
        if (ids.length !== 1) {
            window.alert('Select exactly one record to edit.');
            return;
        }
        window.location.href = $(this).attr('data-form') + '/' + encodeURIComponent(ids[0]);
    });

    $('.js-delete').on('click', function () {
        var button = $(this);
        var ids = selectedIds();
        if (!ids.length) {
            window.alert('Select at least one record to delete.');
            return;
        }
        if (!window.confirm('Delete the selected record(s)?')) {
            return;
        }

        button.prop('disabled', true);
        $.ajax({
            url: button.attr('data-url'),
            type: 'POST',
            dataType: 'json',
            data: {ids: ids}
        }).done(function (response) {
            if (response && response.success) {
                window.location.reload();
                return;
            }
            window.alert(response && response.message ? response.message : 'The selected records could not be deleted.');
        }).fail(function () {
            window.alert('The server could not complete the request.');
        }).always(function () {
            button.prop('disabled', false);
        });
    });

    $('.js-entity-form').on('submit', function (event) {
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var message = form.siblings('.js-message');
        event.preventDefault();
        message.prop('hidden', true).removeClass('is-error');
        button.prop('disabled', true);

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            dataType: 'json',
            data: form.serialize()
        }).done(function (response) {
            if (!response || !response.success) {
                showMessage(message, response && response.message ? response.message : 'The record could not be saved.', true);
                return;
            }
            showMessage(message, response.message || 'Saved successfully.', false);
            window.setTimeout(function () {
                window.location.href = response.redirect || form.attr('data-return-url');
            }, 450);
        }).fail(function () {
            showMessage(message, 'The server could not complete the request.', true);
        }).always(function () {
            button.prop('disabled', false);
        });
    });
}(jQuery));
