/* global window, document, jQuery */
(function ($) {
    'use strict';

    var config = window.RMS_FILETYPES || {};
    var $modal = $('#filetype-modal');
    var $confirm = $('#filetype-confirm');
    var $form = $('#filetype-form');
    var $error = $('#filetype-error');
    var $toast = $('#filetype-toast');

    /** Return the identifiers currently selected in the table. */
    function selectedIds() {
        var ids = [];
        $('.filetype-check:checked').each(function () {
            ids.push($(this).val());
        });
        return ids;
    }

    /** Refresh selection labels and the select-all checkbox state. */
    function updateSelection() {
        var selected = selectedIds().length;
        var available = $('.filetype-check:visible').length;
        var checkedVisible = $('.filetype-check:visible:checked').length;

        $('#filetype-selected-count').text(selected + ' selected');
        $('#filetype-check-all').prop('checked', available > 0 && checkedVisible === available);
    }

    /** Open the static Add/Edit modal without attaching a backdrop close event. */
    function openModal(row) {
        $form[0].reset();
        $error.text('').removeClass('visible');

        if (row) {
            $('#filetype-id').val(row.data('id'));
            $('#filetype-name').val(String(row.data('type')).replace(/^\./, ''));
            $('#filetype-modal-title').text('Edit file type');
            $('#filetype-save').text('Update file type');
        } else {
            $('#filetype-id').val('0');
            $('#filetype-modal-title').text('Add file type');
            $('#filetype-save').text('Save file type');
        }

        $modal.removeAttr('hidden');
        $('body').addClass('modal-open');
        window.setTimeout(function () { $('#filetype-name').focus(); }, 40);
    }

    /** Close only when an explicit Close or Cancel control calls this function. */
    function closeModal() {
        $modal.attr('hidden', 'hidden');
        $('body').removeClass('modal-open');
    }

    /** Update CSRF values after every successful or failed server response. */
    function refreshCsrf(response) {
        if (response && response.csrfName && response.csrfHash) {
            config.csrfName = response.csrfName;
            config.csrfHash = response.csrfHash;
            $form.find('input[name="' + response.csrfName + '"]').val(response.csrfHash);
        }
    }

    /** Display a compact message, then hide it automatically. */
    function showToast(message, success) {
        $toast
            .removeClass('success error')
            .addClass(success ? 'success' : 'error')
            .text(message)
            .removeAttr('hidden');

        window.setTimeout(function () {
            $toast.attr('hidden', 'hidden');
        }, 3500);
    }

    /** Post data with the current CSRF token and consistent error handling. */
    function post(url, data, done) {
        data[config.csrfName] = config.csrfHash;

        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (response) {
            refreshCsrf(response);
            done(response);
        }).fail(function (xhr) {
            var message = 'The request could not be completed. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
                refreshCsrf(xhr.responseJSON);
            }
            showToast(message, false);
        });
    }

    /** Reload after an accepted mutation so the table remains server-authoritative. */
    function handleMutation(response) {
        if (!response.success) {
            showToast(response.message, false);
            return;
        }
        showToast(response.message, true);
        window.setTimeout(function () { window.location.reload(); }, 550);
    }

    // Open Add and Edit forms.
    $('#filetype-add').on('click', function () { openModal(null); });
    $('#filetype-edit').on('click', function () {
        var ids = selectedIds();
        if (ids.length !== 1) {
            showToast('Select exactly one file type to edit.', false);
            return;
        }
        openModal($('#filetype-table tbody tr[data-id="' + ids[0] + '"]'));
    });

    // Explicit modal close controls; backdrop clicks and Escape are ignored.
    $('#filetype-modal-close, #filetype-cancel').on('click', closeModal);

    // Save an added or edited extension through the CI3 endpoint.
    $form.on('submit', function (event) {
        event.preventDefault();
        $error.text('').removeClass('visible');
        $('#filetype-save').prop('disabled', true).text('Saving...');

        post(config.save, {
            filetype_id: $('#filetype-id').val(),
            type: $('#filetype-name').val()
        }, function (response) {
            $('#filetype-save').prop('disabled', false).text(
                parseInt($('#filetype-id').val(), 10) > 0 ? 'Update file type' : 'Save file type'
            );

            if (!response.success) {
                $error.text(response.message).addClass('visible');
                $('#filetype-name').focus();
                return;
            }

            closeModal();
            handleMutation(response);
        });
    });

    // Enable or disable all selected records using the legacy stat values.
    $('#filetype-enable, #filetype-disable').on('click', function () {
        var ids = selectedIds();
        if (!ids.length) {
            showToast('Select at least one file type.', false);
            return;
        }

        post(config.status, {
            ids: ids,
            status: this.id === 'filetype-enable' ? 0 : 1
        }, handleMutation);
    });

    // Show the locked delete confirmation only after records are selected.
    $('#filetype-delete').on('click', function () {
        if (!selectedIds().length) {
            showToast('Select at least one file type.', false);
            return;
        }
        $confirm.removeAttr('hidden');
        $('body').addClass('modal-open');
    });

    $('#filetype-confirm-cancel').on('click', function () {
        $confirm.attr('hidden', 'hidden');
        $('body').removeClass('modal-open');
    });

    $('#filetype-confirm-delete').on('click', function () {
        var ids = selectedIds();
        $(this).prop('disabled', true).text('Deleting...');
        post(config.delete, { ids: ids }, handleMutation);
    });

    // Keep row selection and the select-all control synchronized.
    $(document).on('change', '.filetype-check', updateSelection);
    $('#filetype-check-all').on('change', function () {
        $('.filetype-check:visible').prop('checked', this.checked);
        updateSelection();
    });

    // Filter rows locally because the legacy table normally contains few values.
    $('#filetype-search').on('input', function () {
        var query = $.trim($(this).val()).toLowerCase();
        var visible = 0;

        $('#filetype-table tbody tr[data-id]').each(function () {
            var matches = String($(this).data('type')).toLowerCase().indexOf(query) !== -1;
            $(this).toggle(matches);
            if (matches) { visible += 1; }
        });

        $('#filetype-visible-count').text(visible);
        updateSelection();
    });
}(jQuery));
