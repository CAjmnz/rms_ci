(function ($) {
    'use strict';
    var selected = [], pendingDelete = null;
    var icons = {
        success: '<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></svg>',
        warning: '<svg viewBox="0 0 24 24"><path d="M12 3 2 21h20L12 3zM12 9v5M12 17h.01"></path></svg>',
        error: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="m9 9 6 6M15 9l-6 6"></path></svg>'
    };

    function syncSelection() {
        selected = [];
        $('.subs-check:checked').each(function () {
            selected.push({id: $(this).val(), name: $(this).closest('tr').attr('data-name')});
        });
        $('#subs-edit').prop('disabled', selected.length !== 1);
        /* Edit remains a single-record action; Delete accepts one or many rows. */
        $('#subs-delete').prop('disabled', selected.length === 0);
        $('#subs-all').prop('checked', $('.subs-check').length > 0 && selected.length === $('.subs-check').length);
        $('.subs-table tbody tr').removeClass('selected');
        $('.subs-check:checked').closest('tr').addClass('selected');
    }

    function openForm(record) {
        var editing = record && record.sub_id;
        $('#subs-form-title').text(editing ? 'Edit subsidiary' : 'New subsidiary');
        $('#subs-id').val(editing ? record.sub_id : '');
        $('#subs-name').val(editing ? record.sub_name : '');
        $('#subs-form-error').text('').hide();
        $('#subs-form-modal').prop('hidden', false).addClass('open');
        setTimeout(function () { $('#subs-name').focus(); }, 50);
    }

    function closeForm() {
        $('#subs-form-modal').removeClass('open').prop('hidden', true);
    }

    function message(type, title, text, confirmText, onConfirm) {
        $('#subs-message-icon').attr('class', 'message-icon ' + type).html(icons[type] || icons.error);
        $('#subs-message-title').text(title);
        $('#subs-message-text').text(text);
        $('#subs-message-confirm').text(confirmText || 'OK');
        $('#subs-message-cancel').toggle(type === 'warning');
        pendingDelete = typeof onConfirm === 'function' ? onConfirm : null;
        $('#subs-message-modal').prop('hidden', false).addClass('open');
    }

    function closeMessage(reload) {
        $('#subs-message-modal').removeClass('open').prop('hidden', true);
        pendingDelete = null;
        if (reload) { window.location.reload(); }
    }

    function post(url, data, done, failed) {
        if (window.RMS_SUBS.csrfName && typeof data === 'object') { data[window.RMS_SUBS.csrfName] = window.RMS_SUBS.csrfHash; }
        $.ajax({url: url, type: 'POST', data: data, dataType: 'json'})
            .done(done)
            .fail(function () {
                if (typeof failed === 'function') { failed(); return; }
                message('error', 'Request failed', 'The server could not complete the request. Please try again.', 'Close');
            });
    }

    $(document).on('change', '.subs-check', syncSelection);
    $(document).on('change', '#subs-all', function () { $('.subs-check').prop('checked', this.checked); syncSelection(); });
    $(document).on('click', '.subs-table tbody tr[data-id]', function (event) {
        if ($(event.target).is('input')) { return; }
        var box = $(this).find('.subs-check'); box.prop('checked', !box.prop('checked')); syncSelection();
    });
    $('#subs-add').on('click', function () { openForm(null); });
    $('#subs-edit').on('click', function () {
        if (selected.length !== 1) { return; }
        $.getJSON(window.RMS_SUBS.form + '/' + selected[0].id)
            .done(function (response) { response.success ? openForm(response.subsidiary) : message('error', 'Unable to edit', response.message, 'Close'); })
            .fail(function () { message('error', 'Request failed', 'The subsidiary details could not be loaded.', 'Close'); });
    });
    $('[data-close="form"]').on('click', closeForm);
    $('#subs-form').on('submit', function (event) {
        event.preventDefault();
        var form = $(this), button = form.find('.save');
        button.prop('disabled', true);
        post(window.RMS_SUBS.save, form.serialize(), function (response) {
            button.prop('disabled', false);
            if (!response.success) { $('#subs-form-error').text(response.message).show(); return; }
            closeForm(); message('success', 'Saved successfully', response.message, 'Done');
        });
    });
    $('#subs-delete').on('click', function () {
        var records, total, promptText;

        if (!selected.length) { return; }

        records = selected.slice(0);
        total = records.length;
        promptText = total === 1
            ? 'Delete “' + records[0].name + '”? This action cannot be undone.'
            : 'Delete the ' + total + ' selected subsidiaries? This action cannot be undone.';

        message(
            'warning',
            total === 1 ? 'Delete subsidiary?' : 'Delete selected subsidiaries?',
            promptText,
            'Delete',
            function () {
                var deleted = 0;
                var failed = [];

                /*
                 * Preserve the existing single-subsidiary delete process.
                 * Each checked row is sent to the current endpoint in sequence.
                 */
                function deleteNext(index) {
                    var record;

                    if (index >= records.length) {
                        if (failed.length === 0) {
                            message(
                                'success',
                                deleted === 1 ? 'Deleted successfully' : 'Subsidiaries deleted',
                                deleted === 1
                                    ? 'The selected subsidiary was deleted successfully.'
                                    : deleted + ' subsidiaries were deleted successfully.',
                                'Done'
                            );
                        } else {
                            message(
                                'error',
                                deleted > 0 ? 'Deletion partly completed' : 'Cannot delete subsidiaries',
                                deleted + ' deleted. Unable to delete: ' + failed.join(', ') + '.',
                                'Close'
                            );
                        }
                        return;
                    }

                    record = records[index];
                    post(
                        window.RMS_SUBS.delete,
                        {sub_id: record.id},
                        function (response) {
                            if (response.success) {
                                deleted++;
                            } else {
                                failed.push(record.name);
                            }
                            deleteNext(index + 1);
                        },
                        function () {
                            failed.push(record.name);
                            deleteNext(index + 1);
                        }
                    );
                }

                deleteNext(0);
            }
        );
    });
    $('#subs-message-cancel').on('click', function () { closeMessage(false); });
    $('#subs-message-confirm').on('click', function () {
        if (pendingDelete) { var action = pendingDelete; pendingDelete = null; action(); }
        else { closeMessage(true); }
    });
    $(document).on('keydown', function (event) {
        if (event.keyCode === 27) {
            /* The form is intentionally static; use X or Cancel to close it. */
            if (!$('#subs-form-modal').is('[hidden]')) {
                event.preventDefault();
                return;
            }

            closeMessage(false);
        }
    });
    syncSelection();
}(jQuery));

/* Server-side Subsidiaries table navigation; only the result region changes. */
(function () {
    'use strict';
    var form = document.getElementById('subsidiaries-search-form');
    var input = document.getElementById('subsidiaries-search');
    var button = form ? form.querySelector('button[type="submit"]') : null;
    var buttonText = button ? button.textContent : 'Search';
    var activeRequest = null;
    if (!form || !input) { return; }

    function encode(currentForm) {
        var fields = currentForm.elements, parts = [], index;
        for (index = 0; index < fields.length; index++) {
            if (fields[index].name && !fields[index].disabled) {
                parts.push(encodeURIComponent(fields[index].name) + '=' + encodeURIComponent(fields[index].value));
            }
        }
        return parts.join('&');
    }

    function loading(state) {
        var region = document.getElementById('subsidiaries-ajax-region');
        form.classList[state ? 'add' : 'remove']('is-searching');
        if (button) { button.disabled = state; button.textContent = state ? 'Searching...' : buttonText; }
        if (region) { region.classList[state ? 'add' : 'remove']('is-loading'); }
    }

    function syncForm(nextDocument) {
        var nextForm = nextDocument.getElementById('subsidiaries-search-form');
        var names = ['search', 'per_page', 'sort', 'order'], index, current, next;
        if (!nextForm) { return; }
        for (index = 0; index < names.length; index++) {
            current = form.querySelector('[name="' + names[index] + '"]');
            next = nextForm.querySelector('[name="' + names[index] + '"]');
            if (current && next) { current.value = next.value; }
        }
    }

    function load(url, history) {
        var region = document.getElementById('subsidiaries-ajax-region');
        var request;
        if (!region || !window.XMLHttpRequest || !window.DOMParser) { window.location.href = url; return; }
        if (activeRequest) { request = activeRequest; activeRequest = null; request.abort(); }
        loading(true);
        request = new XMLHttpRequest();
        activeRequest = request;
        request.open('GET', url, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.onreadystatechange = function () {
            var nextDocument, nextRegion, nextTotal, total;
            if (request.readyState !== 4 || request !== activeRequest) { return; }
            if (request.status < 200 || request.status >= 300) { window.location.href = url; return; }
            nextDocument = new DOMParser().parseFromString(request.responseText, 'text/html');
            nextRegion = nextDocument.getElementById('subsidiaries-ajax-region');
            if (!nextRegion) { window.location.href = url; return; }
            region.innerHTML = nextRegion.innerHTML;
            nextTotal = nextDocument.getElementById('subsidiaries-matching-total');
            total = document.getElementById('subsidiaries-matching-total');
            if (nextTotal && total) { total.textContent = nextTotal.textContent; }
            syncForm(nextDocument);
            $('#subs-edit, #subs-delete').prop('disabled', true);
            activeRequest = null;
            loading(false);
            if (history && window.history && window.history.pushState) { window.history.pushState({subsidiariesTable: true}, '', url); }
            input.focus();
        };
        request.send(null);
    }

    form.onsubmit = function (event) {
        var action = form.getAttribute('action') || window.location.pathname;
        if (event && event.preventDefault) { event.preventDefault(); }
        load(action + '?' + encode(form), true);
        return false;
    };

    document.addEventListener('click', function (event) {
        var target = event.target || event.srcElement;
        var region = document.getElementById('subsidiaries-ajax-region');
        var clear;
        while (target && target !== document && target.tagName !== 'A') { target = target.parentNode; }
        clear = target && form.contains(target);
        if (!target || target === document || (!clear && (!region || !region.contains(target)))) { return; }
        if (String(target.className).indexOf('disabled') !== -1) { event.preventDefault(); return; }
        event.preventDefault(); load(target.href, true);
    });

    document.addEventListener('change', function (event) {
        var target = event.target || event.srcElement, pageForm, action;
        if (!target || target.id !== 'subsidiaries-per-page') { return; }
        pageForm = target.form;
        action = pageForm.getAttribute('action') || window.location.pathname;
        load(action + '?' + encode(pageForm), true);
    });

    window.addEventListener('pageshow', function () { loading(false); });
    window.addEventListener('popstate', function () { load(window.location.href, false); });
}());
