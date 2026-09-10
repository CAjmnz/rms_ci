/* =========================================================
   DOCUMENTS TOASTER — OPTION B
   Automatically reports successful and failed Documents POST actions.
   This code is isolated to URLs containing "/documents/".
   ========================================================= */
(function (window, document, $) {
    'use strict';

    var toastDuration = 5000;
    var toastCounter = 0;
    var pendingToastKey = 'rms_documents_pending_toast';

    function getToastContainer() {
        var container = document.getElementById(
            'rms-documents-toast-container'
        );

        if (!container) {
            container = document.createElement('div');
            container.id = 'rms-documents-toast-container';
            container.className = 'rms-documents-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'false');

            document.body.appendChild(container);
        }

        return container;
    }

    function removeToast(toast) {
        if (!toast || toast.classList.contains('is-removing')) {
            return;
        }

        toast.classList.add('is-removing');

        window.setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 220);
    }

    function saveToastForRefresh(toastData) {
        try {
            window.sessionStorage.setItem(
                pendingToastKey,
                JSON.stringify(toastData)
            );
        } catch (error) {
            // The toaster still works when sessionStorage is unavailable.
        }
    }

    function removeSavedToast(id) {
        try {
            var saved = JSON.parse(
                window.sessionStorage.getItem(pendingToastKey) || 'null'
            );

            if (saved && saved.id === id) {
                window.sessionStorage.removeItem(pendingToastKey);
            }
        } catch (error) {
            // Nothing must break when sessionStorage is unavailable.
        }
    }

    /*
     * DOCUMENTS TOASTER:
     * type must be "success" or "error".
     */
    window.RMSDocumentsToast = function (
        type,
        title,
        message,
        actionKey,
        preserveForRefresh
    ) {
        type = type === 'error' ? 'error' : 'success';

        window.RMSDocumentsToast.suppressComplete = true;

        var container = getToastContainer();
        var toastId = 'documents-toast-' + (++toastCounter);
        var toast = document.createElement('section');
        var icon = document.createElement('span');
        var content = document.createElement('span');
        var heading = document.createElement('strong');
        var description = document.createElement('small');
        var closeButton = document.createElement('button');
        var progress = document.createElement('span');

        /*
         * Replace an existing notification for the same action.
         * This prevents duplicate notifications during batched uploads.
         */
        if (actionKey) {
            Array.prototype.forEach.call(
                container.querySelectorAll(
                    '[data-documents-toast-action]'
                ),
                function (existingToast) {
                    if (
                        existingToast.getAttribute(
                            'data-documents-toast-action'
                        ) === actionKey
                    ) {
                        existingToast.remove();
                    }
                }
            );
        }

        toast.id = toastId;
        toast.className =
            'rms-documents-toast rms-documents-toast--' + type;

        toast.setAttribute(
            'data-documents-toast-action',
            actionKey || toastId
        );

        toast.setAttribute(
            'role',
            type === 'error' ? 'alert' : 'status'
        );

        icon.className = 'rms-documents-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = type === 'success' ? '✓' : '!';

        content.className = 'rms-documents-toast-content';

        heading.textContent = title || (
            type === 'success'
                ? 'Action completed successfully'
                : 'Action failed'
        );

        description.textContent = message || (
            type === 'success'
                ? 'The Documents action was completed.'
                : 'The Documents action could not be completed.'
        );

        closeButton.type = 'button';
        closeButton.className = 'rms-documents-toast-close';
        closeButton.setAttribute(
            'aria-label',
            'Close notification'
        );
        closeButton.textContent = '×';

        progress.className = 'rms-documents-toast-progress';

        content.appendChild(heading);
        content.appendChild(description);

        toast.appendChild(icon);
        toast.appendChild(content);
        toast.appendChild(closeButton);
        toast.appendChild(progress);

        container.appendChild(toast);

        /*
         * Save successful or failed actions briefly.
         * If the existing code reloads the page, the toaster reappears
         * after the reload instead of disappearing immediately.
         */
        if (preserveForRefresh !== false) {
            saveToastForRefresh({
                id: toastId,
                type: type,
                title: heading.textContent,
                message: description.textContent,
                actionKey: actionKey || toastId,
                createdAt: Date.now()
            });
        }

        window.requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        closeButton.addEventListener('click', function () {
            removeSavedToast(toastId);
            removeToast(toast);
        });

        window.setTimeout(function () {
            removeSavedToast(toastId);
            removeToast(toast);
        }, toastDuration);

        return toast;
    };

    function getResponse(xhr) {
        if (xhr.responseJSON) {
            return xhr.responseJSON;
        }

        try {
            return JSON.parse(xhr.responseText || '{}');
        } catch (error) {
            return {};
        }
    }

    function documentsActionName(url, success) {
        url = String(url || '').toLowerCase();

        if (url.indexOf('create-filename') !== -1) {
            return success
                ? 'Filename created successfully'
                : 'Filename could not be created';
        }

        if (url.indexOf('create-subfolder') !== -1) {
            return success
                ? 'Subfolder created successfully'
                : 'Subfolder could not be created';
        }

        if (
            url.indexOf('update') !== -1 ||
            url.indexOf('edit') !== -1
        ) {
            return success
                ? 'Document updated successfully'
                : 'Document could not be updated';
        }

        if (url.indexOf('publish') !== -1) {
            return success
                ? 'Publish status updated successfully'
                : 'Publish status could not be updated';
        }

        if (url.indexOf('upload') !== -1) {
            return success
                ? 'Documents uploaded successfully'
                : 'Documents could not be uploaded';
        }

        if (url.indexOf('rename') !== -1) {
            return success
                ? 'File renamed successfully'
                : 'File could not be renamed';
        }

        if (
            url.indexOf('transfer') !== -1 ||
            url.indexOf('move') !== -1
        ) {
            return success
                ? 'Files transferred successfully'
                : 'Files could not be transferred';
        }

        if (url.indexOf('delete') !== -1) {
            return success
                ? 'Items deleted successfully'
                : 'Items could not be deleted';
        }

        return success
            ? 'Action completed successfully'
            : 'Action failed';
    }

    /*
     * DOCUMENTS AJAX FEEDBACK:
     * Observe only POST requests belonging to the Documents module.
     * Existing action handlers, DataTables and database logic are untouched.
     */
    if ($) {
        $(document)
            .off('ajaxComplete.rmsDocumentsToast')
            .on(
                'ajaxComplete.rmsDocumentsToast',
                function (event, xhr, settings) {
                    var method = String(
                        settings.type || settings.method || 'GET'
                    ).toUpperCase();

                    var url = String(settings.url || '');

                    if (
                        method !== 'POST' ||
                        url.toLowerCase().indexOf('/documents/') === -1
                    ) {
                        return;
                    }

                    /*
                     * Uploads use the dedicated live Google-Drive-style
                     * progress toaster below. Do not also show the generic
                     * Documents success/error toaster for every upload batch.
                     */
                    if (url.toLowerCase().indexOf('/documents/upload') !== -1) {
                        return;
                    }

                    if (window.RMSDocumentsToast.suppressComplete) {
                        window.RMSDocumentsToast.suppressComplete = false;
                        return;
                    }

                    var response = getResponse(xhr);
                    var success;

                    if (typeof response.success === 'boolean') {
                        success = response.success;
                    } else if (xhr.status >= 400) {
                        success = false;
                    } else {
                        /*
                         * Do not guess when the server response does not
                         * contain a success value.
                         */
                        return;
                    }

                    window.RMSDocumentsToast(
                        success ? 'success' : 'error',
                        documentsActionName(url, success),
                        response.message || (
                            success
                                ? 'The action was completed successfully.'
                                : 'The server could not complete the action.'
                        ),
                        method + ':' + url,
                        true
                    );
                }
            );
    }

    /*
     * DOCUMENTS REFRESH RESTORE:
     * Show the notification again when an action reloads the page.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var saved = null;

        try {
            saved = JSON.parse(
                window.sessionStorage.getItem(
                    pendingToastKey
                ) || 'null'
            );

            window.sessionStorage.removeItem(
                pendingToastKey
            );
        } catch (error) {
            saved = null;
        }

        if (
            !saved ||
            !saved.createdAt ||
            Date.now() - saved.createdAt > 10000
        ) {
            return;
        }

        window.RMSDocumentsToast(
            saved.type,
            saved.title,
            saved.message,
            saved.actionKey,
            false
        );
    });
})(window, document, window.jQuery);


/* KEEP YOUR EXISTING CODE DIRECTLY BELOW THIS LINE. */
(function ($) {
    'use strict';

    var config = window.RMS_DOCUMENTS || {};
    var $tableElement = $('#documents-table');
    var $selectAll = $('#documents-all');
    var $publishButton = $('#documents-publish');
    var $unpublishButton = $('#documents-unpublish');
    var $selection = $('#documents-selection');
    var $downloadSelected = $('#documents-download-selected');
    var $transferSelected = $('#documents-transfer-files');
    var $deleteFiles = $('#documents-delete-files');
    var $message = $('#documents-message');
    var $uploadForm = $('#modal-upload-form');
    var $uploadPath = $('#modal-upload-path');
    var $uploadButton = $('#modal-upload-submit');
    var $uploadMessage = $('#upload-message');
    var $uploadProgress = $('#modal-upload-progress');
    var $uploadProgressBar = $('#modal-upload-progress-bar');
    var $uploadProgressText = $('#modal-upload-progress-text');
    var uploadToastState = {
        element: null,
        total: 0,
        collapsed: false
    };
    var uploadDroppedFiles = {
        original: null,
        watermark: null
    };
    var table = null;
    var manageTablePositioned = false;
    var restoredSearch = '';
    var groupCounts = { unpublished: 0, published: 0, files: 0 };
    var invalidCharacters = /[<>:"/\\|?*\x00-\x1F]/;
    var trailingPeriodOrSpace = /[. ]$/;

    var reservedWindowsName =
        /^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i;

    function windowsNameError(value) {
        var name = String(value || '');

        if (!name.trim()) {
            return '';
        }
        if (invalidCharacters.test(name)) {
            return 'Invalid name. Remove these characters: < > : " / \\ | ? *';
        }
        if (trailingPeriodOrSpace.test(name)) {
            return 'A Windows name cannot end with a period or space.';
        }
        if (name === '.' || name === '..') {
            return 'The name "." or ".." is not allowed.';
        }
        if (reservedWindowsName.test(name)) {
            return 'This name is reserved by Windows. Choose another name.';
        }

        return '';
    }

    function validateWindowsNameField(field) {
        var message = windowsNameError(field.value);

        if (!message && field.hasAttribute('data-no-special-characters')) {
            var strictName = String(field.value || '');
            if (/[^A-Za-z0-9 _-]/.test(strictName)) {
                message = 'Special characters are not allowed. Use letters, numbers, spaces, hyphens, or underscores only.';
            }
        }
        var errorElement = document.querySelector(
            '[data-error-for="' + field.id + '"]'
        );
        var buttonSelector = field.getAttribute('data-validation-button');
        var saveButton = buttonSelector
            ? document.querySelector(buttonSelector)
            : null;

        field.classList.toggle('has-name-error', Boolean(message));
        field.setCustomValidity(message);

        if (errorElement) {
            errorElement.textContent = message;
            errorElement.hidden = !message;
        }
        if (saveButton) {
            saveButton.disabled = Boolean(message);
        }

        return !message;
    }

    window.RMSWindowsName = {
        error: windowsNameError,
        validateField: validateWindowsNameField
    };

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-windows-name]')) {
            validateWindowsNameField(event.target);
        }
    });

    document.addEventListener('blur', function (event) {
        if (event.target.matches('[data-windows-name]')) {
            validateWindowsNameField(event.target);
        }
    }, true);

    document.addEventListener('submit', function (event) {
        var fields = event.target.querySelectorAll('[data-windows-name]');
        var valid = true;

        Array.prototype.forEach.call(fields, function (field) {
            if (!validateWindowsNameField(field)) {
                valid = false;
            }
        });

        if (!valid) {
            event.preventDefault();
        }
    });

    /**
     * SweetAlert-style dialog used throughout Documents. Keeping the dialog
     * local avoids a CDN dependency on the legacy RMS server while replacing
     * browser alert/confirm/prompt boxes with one accessible UI.
     */
    function documentsSwal(options) {
        options = options || {};
        var deferred = $.Deferred();
        var $overlay = $('<div class="documents-swal" role="presentation"></div>');
        var $dialog = $('<div class="documents-swal-dialog" role="alertdialog" aria-modal="true"></div>');
        var icon = options.icon || 'warning';
        var $input = null;

        $dialog.append($('<span class="documents-swal-icon"></span>').addClass(icon));
        $dialog.append($('<h3></h3>').text(options.title || 'Please confirm'));
        if (options.text) {
            $dialog.append($('<p></p>').text(options.text));
        }
        if ($.isArray(options.lines) && options.lines.length) {
            var $list = $('<ul class="documents-swal-list"></ul>');
            $.each(options.lines, function (index, line) {
                $list.append($('<li></li>').text(line));
            });
            $dialog.append($list);
        }
        if (options.input) {
            $input = $('<input class="documents-swal-input" type="text">')
                .val(options.inputValue || '')
                .attr('placeholder', options.inputPlaceholder || '');
            $dialog.append($input);
        }

        var $actions = $('<div class="documents-swal-actions"></div>');
        if (options.showCancel !== false) {
            $('<button type="button" class="documents-swal-cancel"></button>')
                .text(options.cancelText || 'Cancel')
                .appendTo($actions)
                .on('click', function () {
                    $overlay.remove();
                    deferred.resolve({ confirmed: false, value: null });
                });
        }
        $('<button type="button" class="documents-swal-confirm"></button>')
            .toggleClass('danger', options.confirmDanger === true)
            .text(options.confirmText || 'OK')
            .appendTo($actions)
            .on('click', function () {
                var value = $input ? $.trim($input.val()) : null;
                if ($input && options.inputRequired && value === '') {
                    $input.addClass('has-error').trigger('focus');
                    return;
                }
                $overlay.remove();
                deferred.resolve({ confirmed: true, value: value });
            });
        $dialog.append($actions);
        $overlay.append($dialog).appendTo('body');
        window.setTimeout(function () {
            ($input || $dialog.find('.documents-swal-confirm')).trigger('focus');
        }, 0);
        return deferred.promise();
    }

    function documentsAlert(title, text, icon) {
        return documentsSwal({
            title: title,
            text: text,
            icon: icon || 'warning',
            showCancel: false,
            confirmText: 'OK'
        });
    }

    /** Server-provided folder pins used by breadcrumb/context markers. */
    var hierarchyFolderPins = {};

    function updateHierarchyPinMarkers() {
        $('[data-pin-record-id][data-pin-level][data-pin-parent-id]').each(function () {
            var $item = $(this);
            var key = Number($item.attr('data-pin-level')) + ':' +
                Number($item.attr('data-pin-record-id'));
            var pinned = Number(hierarchyFolderPins[key]) === 1;
            $item.toggleClass('is-pinned', pinned);
            $item.find('.documents-context-pin').prop('hidden', !pinned);
        });
    }

    /** Save one personal pin in the RMS database. */
    function togglePinnedItem($button) {
        if ($button.data('pinBusy')) {
            return;
        }

        var request = {
            item_type: String($button.attr('data-item-type') || ''),
            record_level: Number($button.attr('data-record-level')),
            parent_id: Number($button.attr('data-parent-id') || 0)
        };

        if (request.item_type === 'document') {
            request.record_token = String($button.attr('data-record-token') || '');
        } else {
            request.record_id = Number($button.attr('data-record-id') || 0);
        }

        request[config.csrfName] = config.csrfHash;
        $button.data('pinBusy', true).prop('disabled', true);

        $.ajax({
            url: config.togglePinUrl,
            type: 'POST',
            dataType: 'json',
            data: request
        }).done(function (response) {
            updateCsrf(response);

            if (!response || response.success !== true) {
                window.RMSDocumentsToast(
                    'error',
                    'Pin could not be saved',
                    response && response.message
                        ? response.message
                        : 'The server rejected the pin request.',
                    'documents-pin',
                    false
                );
                return;
            }

            window.RMSDocumentsToast(
                'success',
                response.pinned ? 'Item pinned successfully' : 'Item unpinned successfully',
                response.message,
                'documents-pin',
                false
            );
            if (typeof window.RMSDocumentsRefreshPins === 'function') {
                window.RMSDocumentsRefreshPins();
            }
            table.page('first').draw(false);
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'The server could not save the pin.';
            window.RMSDocumentsToast(
                'error',
                'Pin could not be saved',
                message,
                'documents-pin',
                false
            );
        }).always(function () {
            $button.data('pinBusy', false).prop('disabled', false);
        });
    }

    /** Documents-only topbar card and global database-backed pin popup. */
    function initializePinnedTopbar() {
        var $pinUi = $('#documents-pinned-topbar');
        var $topbarActions = $('.topbar-actions').first();

        if (!$pinUi.length || !$topbarActions.length || !config.pinnedItemsUrl) {
            return;
        }

        var $totalCard = $topbarActions.find('.topbar-stat').last();
        if ($totalCard.length) {
            $pinUi.insertAfter($totalCard);
        } else {
            $pinUi.prependTo($topbarActions);
        }
        $pinUi.prop('hidden', false);

        var $button = $('#documents-pinned-stat');
        var $popover = $('#documents-pinned-popover');
        var $search = $('#documents-pinned-search');
        var $clear = $('#documents-pinned-search-clear');
        var $results = $('#documents-pinned-results');
        var $viewAll = $('#documents-pinned-view-all');
        var activeFilter = 'all';
        var showAll = false;
        var searchTimer = null;
        var request = null;

        function renderPinnedItems(response) {
            var items = response && $.isArray(response.items)
                ? response.items : [];
            var groups = [];

            $.each(items, function (_, item) {
                var key = String(item.type_label || 'Document').toLowerCase();
                var exists = $.grep(groups, function (group) {
                    return group.key === key;
                }).length > 0;

                if (!exists) {
                    groups.push({ key: key, label: String(item.type_label || 'Document') });
                }
            });

            $('#documents-pinned-count').text(
                response && typeof response.count !== 'undefined'
                    ? Number(response.count) : 0
            );
            $results.empty();

            if (!items.length) {
                $('<div class="documents-pinned-empty"></div>')
                    .append('<i class="bi bi-pin-angle"></i>')
                    .append('<strong>No pinned items found</strong>')
                    .append('<small>Pin a Filename, Subfolder, or document to add a shortcut.</small>')
                    .appendTo($results);
                return;
            }

            $.each(groups, function (_, group) {
                var groupedItems = $.grep(items, function (item) {
                    return String(item.type_label || '').toLowerCase() === group.key;
                });

                if (!groupedItems.length) {
                    return;
                }

                $('<div class="documents-pinned-group-title"></div>')
                    .text(group.label)
                    .appendTo($results);

                $.each(groupedItems, function (__, item) {
                    var isFolder = item.item_type === 'folder';
                    var icon = isFolder ? 'bi-folder' : 'bi-file-earmark-text';
                    $('<div class="documents-pinned-result"></div>')
                        .attr('data-pin-url', item.url || '#')
                        .attr('href', item.url || '#')
                        .append(
                            '<span class="documents-pinned-result-icon">' +
                            '<i class="bi ' + icon + '"></i>' +
                            '<i class="bi bi-pin-angle-fill documents-pinned-result-pin"></i>' +
                            '</span>'
                        )
                        .append(
                            '<span class="documents-pinned-result-copy">' +
                            '<span class="documents-pinned-result-heading">' +
                            '<strong>' + escapeHtml(item.name) + '</strong>' +
                            '<em>' + escapeHtml(item.type_label) + '</em>' +
                            '</span>' +
                            '<span class="documents-pinned-parent"><b>PARENT</b> <em>' +
                            escapeHtml(item.parent || 'Root') + '</em></span>' +
                            '<small class="documents-pinned-path">' + escapeHtml(item.path) + '</small>' +
                            '</span>'
                        )
                        .append(
                            '<a class="documents-pinned-open" href="' + escapeHtml(item.url || '#') +
                            '" aria-label="Open ' + escapeHtml(item.name) + '">' +
                            '<i class="bi bi-three-dots-vertical"></i></a>'
                        )
                        .appendTo($results);
                });
            });
        }

        function loadPinnedItems() {
            if (request) {
                request.abort();
            }

            $results.addClass('is-loading').html(
                '<div class="documents-pinned-loading"><span></span>Loading pinned items...</div>'
            );
            request = $.ajax({
                url: config.pinnedItemsUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    search: $.trim($search.val()),
                    type: activeFilter,
                    all: showAll ? 1 : 0
                }
            }).done(function (response) {
                if (!response || response.success !== true) {
                    renderPinnedItems({ count: 0, items: [] });
                    return;
                }
                updateCsrf(response);
                renderPinnedItems(response);
            }).fail(function (xhr, status) {
                if (status !== 'abort') {
                    renderPinnedItems({ count: 0, items: [] });
                }
            }).always(function () {
                request = null;
                $results.removeClass('is-loading');
            });
        }

        window.RMSDocumentsRefreshPins = loadPinnedItems;

        $button.on('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var opening = $popover.prop('hidden');
            $popover.prop('hidden', !opening);
            $button.attr('aria-expanded', opening ? 'true' : 'false');
            $pinUi.toggleClass('is-open', opening);
            if (opening) {
                loadPinnedItems();
                window.setTimeout(function () { $search.trigger('focus'); }, 0);
            }
        });

        $popover.on('click', function (event) {
            event.stopPropagation();
        });

        $results.on('click', '.documents-pinned-result', function (event) {
            if ($(event.target).closest('.documents-pinned-open').length) {
                return;
            }

            window.location.href = String($(this).attr('data-pin-url') || '#');
        });

        $(document).on('click.documentsPinned', function () {
            $popover.prop('hidden', true);
            $button.attr('aria-expanded', 'false');
            $pinUi.removeClass('is-open');
        });

        $search.on('input', function () {
            $clear.prop('hidden', $.trim($search.val()) === '');
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(loadPinnedItems, 220);
        });

        $clear.on('click', function () {
            $search.val('').trigger('input').trigger('focus');
        });

        $('.documents-pinned-filters button').on('click', function () {
            activeFilter = String($(this).attr('data-pinned-filter') || 'all');
            $(this).addClass('is-active').siblings().removeClass('is-active');
            loadPinnedItems();
        });

        $viewAll.on('click', function () {
            showAll = !showAll;
            $viewAll.text(showAll ? 'Show fewer pinned items' : 'View all pinned items');
            $popover.toggleClass('is-view-all', showAll);
            loadPinnedItems();
        });

        loadPinnedItems();
    }

    initializePinnedTopbar();

    /**
     * Build a unique localStorage key for DataTables state per hierarchy location.
     * Includes level + parentId so each folder keeps its own page/sort/search.
     * Query strings are not part of DataTables' default key, so without this
     * custom key, page 2 of one folder can overwrite page 2 of another folder.
     */
    function manageTableStateKey() {
        return 'rms_documents_table_' +
            Number(config.level || 0) + '_' +
            Number(config.parentId || 0);
    }

    function reloadSearchKey() {
        return manageTableStateKey() + '_reload_search';
    }

    /**
     * Restore search only for an actual browser refresh. A new visit to
     * Documents (including returning from another module) starts unfiltered.
     */
    function searchForThisLoad() {
        var navigation = window.performance && window.performance.getEntriesByType
            ? window.performance.getEntriesByType('navigation')[0]
            : null;
        var isReload = navigation
            ? navigation.type === 'reload'
            : window.performance && window.performance.navigation &&
            window.performance.navigation.type === 1;
        if (!isReload) return '';
        try {
            return window.sessionStorage.getItem(reloadSearchKey()) || '';
        } catch (error) {
            return '';
        }
    }

    /**
     * Preserve only the search currently applied to DataTables.
     *
     * A short value that was merely typed must not unexpectedly become active
     * after the user refreshes the browser.
     */
    function rememberSearchForRefresh() {
        var appliedSearch = table
            ? String(table.search() || '')
            : '';

        try {
            window.sessionStorage.setItem(
                reloadSearchKey(),
                appliedSearch
            );
        } catch (error) {
            /* Search still works when sessionStorage is disabled. */
        }
    }

    /**
     * Read a previously saved DataTables state from localStorage.
     * Returns null when missing or when storage is unavailable.
     */
    function loadStoredTableState(key) {
        try {
            var value = window.localStorage.getItem(key);
            return value ? JSON.parse(value) : null;
        } catch (error) {
            return null;
        }
    }

    /**
     * Persist DataTables state (page, length, order, search) to localStorage.
     * Failures are ignored so the page still works in private mode.
     */
    function saveStoredTableState(key, data) {
        try {
            window.localStorage.setItem(key, JSON.stringify(data));
        } catch (error) {
            /* Browsing remains functional when storage is disabled. */
        }
    }

    /**
     * Ask the live DataTable instance to write its current state.
     * Used before navigation so the user returns to the same page/sort.
     */
    function saveManageTableState() {
        if (table && table.state) {
            table.state.save();
        }
    }

    /**
     * Open Manage Documents at the page position approved for the directory
     * layout: top bar and record card visible, rather than table-centered.
     */
    function positionManageTable() {
        if (manageTablePositioned || !$tableElement.length) {
            return;
        }

        manageTablePositioned = true;
        window.setTimeout(function () {
            window.scrollTo({ top: 0, behavior: 'auto' });
        }, 0);
    }

    /**
     * Escape a value for safe insertion into HTML attribute or text nodes.
     * Prevents XSS when rendering server-supplied names and IDs.
     */
    function escapeHtml(value) {
        return $('<div>').text(
            value === null || value === undefined
                ? ''
                : String(value)
        ).html();
    }

    /**
     * Return all row checkboxes currently drawn in the table body.
     */
    function currentChecks() {
        return $tableElement.find('.document-check');
    }

    /**
     * Collect record_ids for checked folder rows only.
     * Used by bulk Publish / Unpublish (files are excluded).
     */
    function selectedIds() {
        var ids = [];

        currentChecks().filter(':checked').each(function () {
            if ($(this).attr('data-item-type') === 'folder') {
                ids.push($(this).attr('data-record-id'));
            }
        });

        return ids;
    }

    /**
     * Sync toolbar buttons and the selection label with the current checkboxes.
     * Enables Publish / Unpublish only when every selected folder allows it.
     * Enables Download when exactly one file is selected; Delete when only files.
     */
    function updateSelection() {
        var $checks = currentChecks();
        var $selectedChecks = $checks.filter(':checked');
        var selected = $selectedChecks.length;
        var total = $checks.length;
        var canPublish = selected > 0;
        var canUnpublish = selected > 0;

        /*
         * A bulk action is enabled only when every selected row supports
         * the same operation.
         *
         * Published records may be unpublished.
         * For Level 3, all globally unpublished records stay visible, but the
         * ownership value supplied by the server enables Publish only for the
         * current user's records. For Level 4, every globally unpublished
         * record is actionable regardless of its Level 3 owner.
         *
         * The server repeats the role-appropriate validation for security.
         */
        $selectedChecks.each(function () {
            if ($(this).attr('data-item-type') !== 'folder') {
                canPublish = false;
                canUnpublish = false;
                return;
            }
            var published =
                Number($(this).attr('data-published')) === 1;

            /*
             * LEVEL 3 SHARED STATUS:
             * Ownership does not restrict Publish or Unpublish.
             * Owner restrictions remain active for other operations.
             */
            if (published) {
                canPublish = false;
            }

            if (!published) {
                canUnpublish = false;
            }
        });

        $publishButton.prop('disabled', !canPublish);
        $unpublishButton.prop('disabled', !canUnpublish);
        var selectedFiles = $selectedChecks.filter('[data-item-type="file"]').length;
        var selectedFolders = $selectedChecks.filter('[data-item-type="folder"]').length;
        $downloadSelected.prop('disabled', selectedFiles !== 1 || selectedFolders > 0);
        $transferSelected.prop('disabled', selectedFiles === 0 || selectedFolders > 0);
        $deleteFiles.prop('disabled', selected === 0);
        currentChecks().each(function () {
            $(this).closest('tr').toggleClass('is-selected', this.checked);
        });

        $selectAll.prop(
            'checked',
            total > 0 && selected === total
        );

        $selectAll.prop(
            'indeterminate',
            selected > 0 && selected < total
        );

        if (selected === 0) {
            $selection.text('No records selected');
        } else if (selected === 1) {
            $selection.text('1 record selected');
        } else {
            $selection.text(selected + ' records selected');
        }
    }

    /**
     * Show a success or error banner above the table.
     * @param {string} type  'success' or 'error'
     * @param {string} text  Message body
     */
    var documentsMessageTimer = null;

    function showMessage(type, text) {
        window.clearTimeout(documentsMessageTimer);

        $message
            .removeClass('success error')
            .addClass(type)
            .text(text);

        /* DOCUMENTS MESSAGE: remove the table banner after no more than 20 seconds. */
        documentsMessageTimer = window.setTimeout(function () {
            $message.removeClass('success error').empty();
        }, 5000);
    }
    /*
 * DOCUMENTS ACTION FEEDBACK:
 * Use the compact Option B toaster when it is installed.
 * Preserve the existing message banner as a safe fallback.
 */
    function notifyDocumentsAction(
        type,
        title,
        message,
        actionKey
    ) {
        if (typeof window.RMSDocumentsToast === 'function') {
            window.RMSDocumentsToast(
                type,
                title,
                message,
                actionKey,
                true
            );

            return;
        }

        showMessage(type, message);
    }

    /**
     * DataTables render for the selection checkbox column.
     * Stores item type, publish flag, and ownership on the input for bulk actions.
     */
    function renderCheckbox(data, type, row) {
        if (type !== 'display') {
            return row.record_id;
        }

        var itemType = row.item_type || 'folder';
        var itemId = itemType === 'file' ? row.data_id : row.record_id;
        return (
            '<input type="checkbox" class="document-check" ' +
            'value="' + escapeHtml(itemType + '-' + itemId) + '" ' +
            'data-item-type="' + escapeHtml(itemType) + '" ' +
            'data-record-id="' + escapeHtml(itemId) + '" ' +
            'data-published="' +
            (Number(row.publish_status) === 1 ? '1' : '0') +
            '" ' +
            'data-owned="' +
            (Number(row.owned_by_current_user) === 1 ? '1' : '0') +
            '" ' +
            'aria-label="Select ' +
            escapeHtml(row.record_name) +
            '">'
        );
    }

    /**
     * DataTables render for the Name column.
     * Folders show icon + name + child count; files show file icon + page hint.
     * Double-click navigation is handled separately via createdRow / events.
     */
    function renderName(data, type, row) {
        if (type !== 'display') {
            return data;
        }

        var childText;

        if (row.item_type === 'file') {
            /*
             * WATERMARK-FIRST DISPLAY:
             * Keep the original filename visible while identifying the
             * source that will be opened in the document viewer.
             */
            var hasWatermark =
                Number(row.has_watermark) === 1;

            var previewLabel = row.preview_label || (
                hasWatermark
                    ? 'Watermarked preview'
                    : 'Watermarked preview unavailable'
            );

            return (
                '<div class="record-name record-name-link unified-file" ' +
                'title="Double-click to open ' +
                escapeHtml(previewLabel) + '">' +

                '<span class="record-icon-wrap">' +
                '<span class="folder-icon file-icon" aria-hidden="true">' +
                '<i class="bi bi-file-earmark-text"></i>' +
                '</span>' +

                (hasWatermark
                    ? '<span class="documents-watermark-indicator" ' +
                    'title="Watermark displayed" ' +
                    'aria-label="Watermark displayed">' +
                    '<i class="bi bi-shield-check" aria-hidden="true"></i>' +
                    '</span>'
                    : '') +

                (Number(row.is_pinned) === 1
                    ? '<span class="document-pin-marker" title="Pinned">' +
                    '<i class="bi bi-pin-angle-fill"></i>' +
                    '</span>'
                    : '') +
                '</span>' +

                '<span class="record-name-text">' +
                '<strong>' +
                escapeHtml(row.record_name) +
                '</strong>' +

                '<small class="' +
                (hasWatermark
                    ? 'documents-watermark-label'
                    : 'documents-original-preview-label') +
                '">' +
                escapeHtml(previewLabel) +
                ' &middot; Page ' +
                escapeHtml(row.page_no || 1) +
                '</small>' +
                '</span>' +
                '</div>'
            );
        }

        if (Number(row.document_count) > 0) {
            childText = Number(row.document_count) === 1
                ? '1 uploaded file'
                : row.document_count + ' uploaded files';
        } else if (row.next_url) {
            childText = Number(row.child_count) === 1
                ? '1 subfolder'
                : row.child_count + ' subfolders';
        } else if (Number(config.level) >= 10) {
            childText = 'Last folder level';
        } else {
            childText = 'No subfolders';
        }

        var contents =
            '<span class="record-icon-wrap">' +
            '<span class="folder-icon" aria-hidden="true"><i class="bi bi-folder"></i></span>' +
            (Number(row.is_pinned) === 1
                ? '<span class="document-pin-marker" title="Pinned"><i class="bi bi-pin-angle-fill"></i></span>'
                : '') +
            '</span>' +
            '<span class="record-name-text">' +
            '<strong>' +
            escapeHtml(row.record_name) +
            '</strong>' +
            '<small>' +
            'ID #' + escapeHtml(row.record_id) +
            ' &middot; ' + escapeHtml(childText) +
            '</small>' +
            '</span>';

        if (row.view_url || row.next_url) {
            return (
                '<div class="record-name record-name-link" ' +
                'title="Double-click to open ' +
                escapeHtml(row.record_name) +
                '">' +
                contents +
                '</div>'
            );
        }

        return (
            '<div class="record-name is-leaf">' +
            contents +
            '</div>'
        );
    }

    /**
     * DataTables render for date columns (created / modified).
     * Empty values become an em dash.
     */
    function renderDate(data, type) {
        if (type !== 'display') {
            return data;
        }

        return data ? escapeHtml(data) : '&mdash;';
    }

    /**
     * DataTables render for the Status column (legacy "Remarks").
     * publish_status 1 → "publish", otherwise "unpublish".
     * Uploaded file rows show a neutral "file" badge instead.
     */
    function renderRemarks(data, type, row) {
        if (type !== 'display') {
            return data;
        }

        if (row && row.item_type === 'file') {
            return '<span class="status-badge file-status">file</span>';
        }

        if (Number(data) === 1) {
            return (
                '<span class="status-badge published">' +
                'publish' +
                '</span>'
            );
        }

        return (
            '<span class="status-badge unpublished">' +
            'unpublish' +
            '</span>'
        );
    }

    /**
     * DataTables render for Downloadable (legacy stat field).
     * Legacy rule: stat === 0 means Yes (downloadable); any other value is No.
     */
    function renderDownloadable(data, type) {
        if (type !== 'display') {
            return data;
        }

        /*
         * Legacy RMS rule:
         * stat = 0 means the record is downloadable.
         */
        if (Number(data) === 0) {
            return (
                '<span class="status-badge published">' +
                'Yes' +
                '</span>'
            );
        }

        return (
            '<span class="status-badge unpublished">' +
            'No' +
            '</span>'
        );
    }

    /**
     * DataTables render for review status (legacy r_stat).
     * r_stat === 0 → accepted; otherwise pending.
     */
    function renderReviewStatus(data, type) {
        if (type !== 'display') {
            return data;
        }

        /*
         * Legacy RMS rule:
         * r_stat = 0 means the record is accepted.
         */
        if (Number(data) === 0) {
            return (
                '<span class="status-badge published">' +
                'accepted' +
                '</span>'
            );
        }

        return (
            '<span class="status-badge unpublished">' +
            'pending' +
            '</span>'
        );
    }

    /**
     * DataTables render for owner / user labels.
     * Empty values fall back to "Admin" to match legacy display.
     */
    function renderUserLabel(data, type) {
        if (type !== 'display') {
            return data;
        }

        var name =
            data === null ||
                data === undefined ||
                data === ''
                ? 'Admin'
                : String(data);

        return (
            '<span class="status-badge published">' +
            escapeHtml(name) +
            '</span>'
        );
    }

    /**
     * DataTables render for the Actions column.
     * Builds a compact three-dots dropdown. Menu items differ for files vs folders
     * and depend on publish state and ownership flags from the server.
     */
    function renderActions(data, type, row) {
        if (type !== 'display') {
            return '';
        }

        if (row.item_type === 'file') {
            return (
                '<div class="doc-actions"><button type="button" class="doc-actions-toggle" ' +
                'title="More actions" aria-label="More actions" ' +
                'aria-haspopup="true" aria-expanded="false"><i class="bi bi-three-dots-vertical"></i></button>' +
                '<div class="doc-actions-menu" hidden>' +

                /*
                 * WATERMARK-FIRST DISPLAY:
                 * The Open action uses row.view_url. The server securely selects
                 * the watermark or falls back to the original.
                 */
                (Number(row.has_watermark) === 1
                    ? '<button type="button" class="doc-action-item" ' +
                    'data-action="preview-file" data-id="' +
                    escapeHtml(row.data_id) + '">' +
                    '<i class="bi bi-eye"></i>Open watermarked preview</button>'
                    : '<span class="doc-action-item is-disabled" aria-disabled="true">' +
                    '<i class="bi bi-eye-slash"></i>Watermarked preview unavailable</span>') +

                '<button type="button" class="doc-action-item pin-action" ' +
                'data-action="toggle-pin" data-item-type="document" ' +
                'data-record-level="' + Number(config.level - 1) + '" ' +
                'data-parent-id="' + Number(config.parentId) + '" ' +
                'data-record-token="' + escapeHtml(row.data_id) + '">' +
                '<i class="bi bi-pin-angle-fill"></i>' +
                (Number(row.is_pinned) === 1 ? 'Unpin' : 'Pin') + '</button>' +
                '<a class="doc-action-item" href="' + escapeHtml(row.download_url) + '">' +
                '<i class="bi bi-download"></i>Download original</a>' +
                '<button type="button" class="doc-action-item" data-action="file-information" data-id="' +
                escapeHtml(row.data_id) + '"><i class="bi bi-info-circle"></i>File information</button>' +
                '<button type="button" class="doc-action-item" data-action="rename-file" data-id="' +
                escapeHtml(row.data_id) + '"><i class="bi bi-pencil"></i>Rename</button>' +
                '<button type="button" class="doc-action-item" data-action="transfer-file" data-id="' +
                escapeHtml(row.data_id) + '"><i class="bi bi-arrow-left-right"></i>Transfer</button>' +

                (Number(config.currentCanUpload) === 1
                    ? '<span class="doc-action-divider" aria-hidden="true"></span>' +
                    '<button type="button" class="doc-action-item danger" ' +
                    'data-action="delete-file" data-id="' +
                    escapeHtml(row.data_id) + '">' +
                    '<i class="bi bi-trash"></i>Delete</button>'
                    : '') +

                '</div></div>'
            );
        }

        var id = escapeHtml(row.record_id);

        var published =
            Number(row.publish_status) === 1;

        var ownedByCurrentUser =
            Number(row.owned_by_current_user) === 1;

        var items = '';

        items += '<button type="button" class="doc-action-item pin-action" data-action="toggle-pin" data-item-type="folder" data-record-level="' + Number(config.level) + '" data-parent-id="' + Number(config.parentId) + '" data-record-id="' + id + '"><i class="bi bi-pin-angle-fill"></i>' +
            (Number(row.is_pinned) === 1 ? 'Unpin' : 'Pin') + '</button>';

        if (!published && ownedByCurrentUser) {
            items += '<button type="button" class="doc-action-item" data-action="edit" data-id="' + id + '"><i class="fa fa-edit"></i>Edit</button>';
            items += '<button type="button" class="doc-action-item danger" data-action="delete" data-id="' + id + '"><i class="fa fa-trash"></i>Delete</button>';
        }

        items += '<button type="button" class="doc-action-item" data-action="view" data-id="' + id + '"><i class="fa fa-search"></i>View</button>';
        items += '<button type="button" class="doc-action-item" data-action="folder-information" data-id="' + id + '"><i class="bi bi-info-circle"></i>Folder information</button>';
        items += '<span class="doc-action-heading">PERMISSIONS</span>';

        /*
 * LEVEL 3 SHARED STATUS:
 * Every Level 3 Admin may publish or unpublish.
 * Ownership still controls Edit and Delete above this section.
 */
        if (published) {
            items +=
                '<button type="button" class="doc-action-item" ' +
                'data-action="unpublish" data-id="' + id + '">' +
                '<i class="fa fa-ban"></i>Unpublish</button>';
        } else {
            items +=
                '<button type="button" class="doc-action-item" ' +
                'data-action="publish" data-id="' + id + '">' +
                '<i class="fa fa-check"></i>Publish</button>';
        }

        return (
            '<div class="doc-actions">' +
            '<button type="button" ' +
            'class="doc-actions-toggle" ' +
            'title="More actions" ' +
            'aria-label="More actions" ' +
            'aria-haspopup="true" ' +
            'aria-expanded="false">' +
            '<i class="bi bi-three-dots-vertical" aria-hidden="true"></i>' +
            '</button>' +
            '<div class="doc-actions-menu" hidden>' +
            items +
            '</div>' +
            '</div>'
        );
    }

    /**
     * Hide every open Actions dropdown and reset aria-expanded.
     */
    function closeAllActionMenus() {
        $('.doc-actions-menu').prop('hidden', true).removeAttr('style');

        $('.doc-actions-toggle')
            .attr('aria-expanded', 'false');

        $('.doc-actions').removeClass('is-open');
        $tableElement.find('tbody tr').removeClass('is-actions-open');
    }

    function closeBulkMenu() {
        $('#documents-bulk-menu').prop('hidden', true);
        $('#documents-bulk-toggle').attr('aria-expanded', 'false');
    }

    /** Position a row menu in the viewport so bottom rows never add scrolling. */
    function positionActionMenu($toggle, $menu) {
        var rect = $toggle.get(0).getBoundingClientRect();
        var width = Math.max(190, $menu.outerWidth());
        var height = $menu.outerHeight();
        var top = rect.bottom + 6;
        if (top + height > window.innerHeight - 12) {
            top = Math.max(12, rect.top - height - 6);
        }
        $menu.css({
            position: 'fixed',
            top: top + 'px',
            left: Math.max(12, rect.right - width) + 'px',
            width: width + 'px',
            zIndex: 10060
        });
    }

    $(document).on(
        'click',
        '.doc-actions-toggle',
        function (event) {
            event.preventDefault();
            event.stopPropagation();

            var $wrap = $(this).closest('.doc-actions');
            var $menu = $wrap.find('.doc-actions-menu');
            var willOpen = $menu.prop('hidden');

            closeAllActionMenus();
            closeBulkMenu();

            if (willOpen) {
                $menu.prop('hidden', false);
                positionActionMenu($(this), $menu);

                $(this).attr(
                    'aria-expanded',
                    'true'
                );

                $wrap.addClass('is-open');
                $wrap.closest('tr').addClass('is-actions-open');
            }
        }
    );

    $(document).on(
        'click',
        '.doc-actions-menu',
        function (event) {
            event.stopPropagation();
        }
    );

    $(document).on('click', function () {
        closeAllActionMenus();
    });

    /**
     * Refresh CSRF token name/hash after each successful POST so the next
     * request is not rejected by CodeIgniter's CSRF filter.
     */
    function updateCsrf(response) {
        if (response && response.csrfName && response.csrfHash) {
            config.csrfName = response.csrfName;
            config.csrfHash = response.csrfHash;
        }
    }

    /**
     * Fetch one folder/file record JSON for edit or view modals.
     * @param {number|string} recordId
     * @param {function} done  Called with the record object on success
     */
function loadRecord(recordId, done, requestedLevel) {
    var recordLevel =
        typeof requestedLevel === 'number'
            ? requestedLevel
            : Number(config.level);

    $.ajax({
        url: config.recordUrl,
        type: 'GET',
        dataType: 'json',
        data: {
            level: recordLevel,
            record_id: recordId
        }
    }).done(function (response) {
        updateCsrf(response);

        if (
            !response ||
            response.success !== true ||
            !response.record
        ) {
            showMessage(
                'error',
                response && response.message
                    ? response.message
                    : 'The record could not be loaded.'
            );
            return;
        }

        done(response.record);
    }).fail(function (xhr) {
        showMessage(
            'error',
            xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'The server could not load the record.'
        );
    });
}
    /**
     * Open the edit modal and populate fields for the given record id.
     */
    function showEditModal(recordId, requestedLevel) {
    var recordLevel =
        typeof requestedLevel === 'number'
            ? requestedLevel
            : Number(config.level);

    loadRecord(
        recordId,
        function (record) {
            $('#document-edit-level').val(recordLevel);
            $('#document-edit-id').val(recordId);
            $('#document-edit-name').val(
                record.record_name || ''
            );

            $('#document-edit-title').text(
                recordLevel === 0
                    ? 'Edit Filename'
                    : 'Edit Subfolder' + recordLevel
            );

            $('#document-edit-section-title').text(
                recordLevel === 0
                    ? 'Filename Name'
                    : 'Subfolder' + recordLevel + ' Name'
            );

            $('#document-edit-message')
                .removeClass('success error')
                .hide()
                .text('');

            if (recordLevel === 0) {
                $('#document-edit-location-fields').show();

                $('#document-edit-sub')
                    .prop('required', true)
                    .val(record.sub_id || '')
                    .trigger('change');

                $('#document-edit-dept')
                    .prop('required', true)
                    .val(record.dept_id || '');
            } else {
                $('#document-edit-location-fields').hide();

                $('#document-edit-sub, #document-edit-dept')
                    .prop('required', false)
                    .val('');
            }

            openModal('document-edit-modal');
        },
        recordLevel
    );
}

    function showCurrentFolderRenameModal() {
        var recordId = Number(config.currentFolderId);
        var recordLevel = Number(config.currentFolderLevel);

        if (
            !isFinite(recordId) ||
            !isFinite(recordLevel) ||
            recordId <= 0 ||
            recordLevel < 0
        ) {
            showMessage(
                'error',
                'The current folder information is invalid.'
            );
            return;
        }

        showEditModal(recordId, recordLevel);
    }
    /**
     * Open the read-only view modal for the given record id.
     */
    function showViewModal(recordId) {
        loadRecord(recordId, function (record) {
            var level = Number(config.level);
            var parts = [record.sub_name, record.dept_name, record.filename];
            var index;
            for (index = 1; index <= level; index++) {
                if (record['subfolder' + index + '_name']) {
                    parts.push(record['subfolder' + index + '_name']);
                }
            }
            $('#document-view-name').text(record.record_name || '—');
            $('#document-view-level').text(level === 0 ? 'Filename' : 'Subfolder' + level);
            $('#document-view-subsidiary').text(record.sub_name || '—');
            $('#document-view-department').text(record.dept_name || '—');
            $('#document-view-path').text(parts.filter(function (part) { return part; }).join(' / '));
            $('#document-view-message').hide();
            openModal('document-view-modal');
        });
    }

    /**
     * Confirm and delete one folder record, then reload the DataTable.
     */
    function deleteRecord(recordId) {
        documentsSwal({
            title: 'Delete this folder?',
            text: 'The folder and all physical files inside it will be permanently deleted.',
            icon: 'warning',
            confirmText: 'Delete',
            confirmDanger: true
        }).done(function (result) {
            if (!result.confirmed) return;

            var request = { level: config.level, record_id: recordId };
            request[config.csrfName] = config.csrfHash;

            $.ajax({
                url: config.deleteRecordUrl,
                type: 'POST',
                dataType: 'json',
                data: request
            }).done(function (response) {
                updateCsrf(response);
                if (!response.success) {
                    documentsAlert('Delete failed', response.message || 'The record could not be deleted.', 'error');
                    return;
                }
                showMessage('success', response.message);
                table.ajax.reload(null, false);
            }).fail(function (xhr) {
                documentsAlert('Delete failed', xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The server could not delete the record.', 'error');
            });
        });
    }

    /* File / Folder information drawer. Opens only from explicit action commands. */
    function documentsInfoValue(value) {
        return (value === null || value === undefined || value === '') ? '—' : String(value);
    }

    function documentsInfoFileSize(row) {
        if (!row) return '—';
        return documentsInfoValue(row.file_size_formatted || row.file_size_label || row.file_size || row.size || '');
    }

    var documentsInfoSelected = null;

    function documentsInfoPathToken(row) {
        if (!row) return '';
        if (row.access_token) return String(row.access_token);

        var ids = [];
        if (config.currentPathIds && $.isArray(config.currentPathIds)) {
            ids = config.currentPathIds.slice(0);
        }

        /* Uploaded files inherit access from the exact folder path they live in. */
        if (row.item_type !== 'file') {
            ids.push(Number(row.record_id || 0));
        }

        ids = $.grep(ids, function (id) { return Number(id) > 0; });
        return ids.length ? (ids.length - 1) + ':' + ids.join('/') : '';
    }

    function documentsInfoPath() {
        if (config.currentPath && $.isArray(config.currentPath) && config.currentPath.length) {
            return config.currentPath.join(' / ');
        }
        return 'Manage Documents';
    }

    function documentsInfoOpenDrawer() {
        $('#documents-info-drawer').addClass('is-open').attr('aria-hidden', 'false');
        $('.documents-content').addClass('is-info-drawer-open');
        $('.documents-info-tab').removeClass('is-active').filter('[data-info-tab="details"]').addClass('is-active');
        $('.documents-info-panel').removeClass('is-active').filter('[data-info-panel="details"]').addClass('is-active');
    }

    function documentsInfoCloseDrawer() {
        $('#documents-info-drawer').removeClass('is-open').attr('aria-hidden', 'true');
        $('.documents-content').removeClass('is-info-drawer-open');
    }

    function documentsInfoSetIcon(type, name) {
        var $icon = $('#documents-info-icon');
        var extension = '';
        if (name && String(name).indexOf('.') !== -1) extension = String(name).split('.').pop().toLowerCase();
        if (type === 'folder') $icon.html('<i class="bi bi-folder-fill documents-info-folder-icon"></i>');
        else if (extension === 'pdf') $icon.html('<i class="bi bi-file-earmark-pdf-fill documents-info-pdf-icon"></i>');
        else if (extension === 'doc' || extension === 'docx') $icon.html('<i class="bi bi-file-earmark-word-fill documents-info-word-icon"></i>');
        else if (extension === 'xls' || extension === 'xlsx') $icon.html('<i class="bi bi-file-earmark-excel-fill documents-info-excel-icon"></i>');
        else if ($.inArray(extension, ['png', 'jpg', 'jpeg', 'gif', 'webp']) !== -1) $icon.html('<i class="bi bi-file-earmark-image-fill documents-info-image-icon"></i>');
        else $icon.html('<i class="bi bi-file-earmark-fill documents-info-file-icon"></i>');
    }

    var documentsInfoActivityRequest = null;
    var documentsInfoAccessRequest = null;

    function renderDocumentsInfoAccess(users, creator) {
        users = $.isArray(users) ? users : [];
        creator = documentsInfoValue(creator || 'Admin');

        $('#documents-info-creator-name').text(creator);
        $('#documents-info-creator-avatar').text(documentsInfoActivityInitials(creator));

        var allowed = $.grep(users, function (user) {
            return Number(user.has_access) === 1;
        });
        var $people = $('#documents-info-access-people').empty();
        var $list = $('#documents-info-access-list').empty();

        $.each(allowed.slice(0, 5), function (_, user) {
            var displayName = user.emp_name || user.username || 'User';
            $('<span class="documents-info-access-avatar"></span>')
                .text(documentsInfoActivityInitials(displayName))
                .attr('title', displayName)
                .appendTo($people);
        });

        if (allowed.length > 5) {
            $('<span class="documents-info-access-avatar is-more"></span>')
                .text('+' + (allowed.length - 5))
                .attr('title', (allowed.length - 5) + ' more users')
                .appendTo($people);
        }

        if (!allowed.length) {
            $people.append('<span class="documents-info-access-none">No tagged users</span>');
            $list.append('<div class="documents-info-access-empty">No RMS users are currently tagged to this folder path.</div>');
            return;
        }

        $('<div class="documents-info-access-list-title"></div>')
            .text('People who can access this item')
            .appendTo($list);

        $.each(allowed, function (_, user) {
            var displayName = user.emp_name || user.username || 'User';
            var username = user.username || '';
            $('<div class="documents-info-access-user"></div>')
                .append('<span class="documents-info-access-avatar">' + escapeHtml(documentsInfoActivityInitials(displayName)) + '</span>')
                .append(
                    '<span class="documents-info-access-user-copy"><strong>' + escapeHtml(displayName) + '</strong>' +
                    (username ? '<small>' + escapeHtml(username) + '</small>' : '') + '</span>'
                )
                .appendTo($list);
        });
    }

    function loadDocumentsInfoAccess(row) {
        var token = documentsInfoPathToken(row);
        var creator = row && row.created_by ? row.created_by : 'Admin';

        $('#documents-info-creator-name').text(documentsInfoValue(creator));
        $('#documents-info-creator-avatar').text(documentsInfoActivityInitials(creator));
        $('#documents-info-access-people').html('<span class="documents-info-access-none">Loading...</span>');
        $('#documents-info-access-list').html('<div class="documents-info-access-empty">Loading access...</div>');

        if (!token || !config.accessUrl) {
            renderDocumentsInfoAccess([], creator);
            return;
        }

        if (documentsInfoAccessRequest && documentsInfoAccessRequest.readyState !== 4) {
            documentsInfoAccessRequest.abort();
        }

        documentsInfoAccessRequest = $.ajax({
            url: config.accessUrl,
            type: 'GET',
            dataType: 'json',
            data: { path_token: token }
        }).done(function (response) {
            if (response && response.success) {
                renderDocumentsInfoAccess(response.users || [], creator);
                return;
            }
            renderDocumentsInfoAccess([], creator);
        }).fail(function (xhr, status) {
            if (status === 'abort') return;
            $('#documents-info-access-people').html('<span class="documents-info-access-none">Unavailable</span>');
            $('#documents-info-access-list').html('<div class="documents-info-access-empty">Access information could not be loaded.</div>');
        });
    }

    function documentsInfoActivityInitials(identity) {
        var words = $.trim(identity || 'Unknown').split(/\s+/);
        var initials = '';
        $.each(words, function (index, word) {
            if (word && initials.length < 2) initials += word.charAt(0).toUpperCase();
        });
        return initials || '?';
    }

    function documentsInfoActivityYear(dateText) {
        var match = String(dateText || '').match(/(20\d{2})/);
        return match ? match[1] : 'Activity';
    }

    function documentsInfoActivityAction(activity) {
        var text = $.trim(activity || 'Activity');
        var colon = text.indexOf(':');
        if (colon !== -1) text = text.substring(0, colon);
        return text || 'Activity';
    }

    function renderDocumentsInfoActivity(rows, itemName, itemType) {
        var $target = $('#documents-info-activity');
        rows = $.isArray(rows) ? rows : [];

        if (!rows.length) {
            $target.html(
                '<div class="documents-info-activity-empty">' +
                    '<i class="bi bi-clock-history"></i>' +
                    '<strong>No recorded activity for this item yet.</strong>' +
                    '<span>New RMS actions for this item will appear here automatically.</span>' +
                '</div>'
            );
            return;
        }

        var html = '';
        var lastYear = '';
        $.each(rows, function (_, row) {
            var year = documentsInfoActivityYear(row.date);
            if (year !== lastYear) {
                html += '<div class="documents-info-activity-year">' + escapeHtml(year === String(new Date().getFullYear()) ? 'This year' : year) + '</div>';
                lastYear = year;
            }

            var identity = documentsInfoValue(row.identity);
            var action = documentsInfoActivityAction(row.activity);
            var iconClass = itemType === 'file' ? 'bi-file-earmark-fill' : 'bi-folder-fill';

            html += '<article class="documents-info-activity-item">' +
                '<div class="documents-info-activity-avatar" aria-hidden="true">' + escapeHtml(documentsInfoActivityInitials(identity)) + '</div>' +
                '<div class="documents-info-activity-copy">' +
                    '<div class="documents-info-activity-sentence"><strong>' + escapeHtml(identity) + '</strong> ' + escapeHtml(action.toLowerCase()) + '</div>' +
                    '<time>' + escapeHtml(documentsInfoValue(row.date)) + '</time>' +
                    '<div class="documents-info-activity-target"><i class="bi ' + iconClass + '"></i><span>' + escapeHtml(documentsInfoValue(itemName)) + '</span></div>' +
                '</div>' +
            '</article>';
        });
        $target.html(html);
    }

    function loadDocumentsInfoActivity(type, id, name) {
        var $target = $('#documents-info-activity');
        $target.html('<div class="documents-info-activity-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Loading activity...</div>');

        if (!config.activityUrl) {
            renderDocumentsInfoActivity([], name, type);
            return;
        }

        if (documentsInfoActivityRequest && documentsInfoActivityRequest.readyState !== 4) {
            documentsInfoActivityRequest.abort();
        }

        documentsInfoActivityRequest = $.ajax({
            url: config.activityUrl,
            type: 'GET',
            dataType: 'json',
            data: { type: type, id: id || 0, name: name || '' }
        }).done(function (response) {
            if (response && response.success) {
                renderDocumentsInfoActivity(response.activities || [], name, type);
                return;
            }
            renderDocumentsInfoActivity([], name, type);
        }).fail(function (xhr, status) {
            if (status === 'abort') return;
            $target.html('<div class="documents-info-activity-empty"><i class="bi bi-exclamation-circle"></i><strong>Activity could not be loaded.</strong><span>Please try opening the information card again.</span></div>');
        });
    }

    function showDocumentInformation(row) {
        if (!row) return;
        documentsInfoSelected = row;
        var name = documentsInfoValue(row.record_name);
        $('#documents-info-name').text(name);
        $('#documents-info-subtitle').text(documentsInfoFileSize(row));
        documentsInfoSetIcon('file', name);
        $('#documents-info-type').text(documentsInfoValue(row.preview_label || row.preview_type || 'Document'));
        $('#documents-info-location').text(documentsInfoPath());
        $('#documents-info-owner').text(documentsInfoValue(row.created_by));
        $('#documents-info-created').text(documentsInfoValue(row.date_created));
        $('#documents-info-modified').text(documentsInfoValue(row.date_modified));
        $('#documents-info-status').text('File');
        $('#documents-info-size').text(documentsInfoFileSize(row));
        $('#documents-info-subfolders-row, #documents-info-files-row').hide();
        $('#documents-info-size-row').show();
        documentsInfoOpenDrawer();
        loadDocumentsInfoActivity('file', row.data_id || row.record_id || 0, name);
        loadDocumentsInfoAccess(row);
    }

    function showFolderInformation(row) {
        if (!row) return;
        documentsInfoSelected = row;
        var name = documentsInfoValue(row.record_name);
        var status = Number(row.publish_status) === 1 ? 'Published' : 'Unpublished';
        $('#documents-info-name').text(name);
        $('#documents-info-subtitle').text(status);
        documentsInfoSetIcon('folder', name);
        $('#documents-info-type').text('Folder');
        $('#documents-info-location').text(documentsInfoPath());
        $('#documents-info-subfolders').text(documentsInfoValue(row.child_count));
        $('#documents-info-files').text(documentsInfoValue(row.document_count));
        $('#documents-info-owner').text(documentsInfoValue(row.created_by));
        $('#documents-info-created').text(documentsInfoValue(row.date_created));
        $('#documents-info-modified').text(documentsInfoValue(row.date_modified));
        $('#documents-info-status').text(status);
        $('#documents-info-subfolders-row, #documents-info-files-row').show();
        $('#documents-info-size-row').hide();
        documentsInfoOpenDrawer();
        loadDocumentsInfoActivity('folder', row.record_id || 0, name);
        loadDocumentsInfoAccess(row);
    }

    function showCurrentFolderInformation($button) {
        var pathName = documentsInfoValue($button.data('name'));
        var currentIds = (config.currentPathIds && $.isArray(config.currentPathIds))
            ? config.currentPathIds.slice(0)
            : [];
        currentIds = $.grep(currentIds, function (id) { return Number(id) > 0; });
        documentsInfoSelected = {
            item_type: 'folder',
            record_id: Number($button.data('record-id') || 0),
            record_name: pathName,
            created_by: 'Admin',
            access_token: currentIds.length ? (currentIds.length - 1) + ':' + currentIds.join('/') : ''
        };
        var status = Number($button.data('status')) === 1 ? 'Published' : 'Unpublished';
        $('#documents-info-name').text(pathName);
        $('#documents-info-subtitle').text(status);
        documentsInfoSetIcon('folder', pathName);
        $('#documents-info-type').text('Folder');
        $('#documents-info-location').text(documentsInfoPath());
        $('#documents-info-subfolders').text(documentsInfoValue($button.data('child-count')));
        $('#documents-info-files').text(documentsInfoValue($button.data('file-count')));
        $('#documents-info-owner, #documents-info-created, #documents-info-modified').text('—');
        $('#documents-info-status').text(status);
        $('#documents-info-subfolders-row, #documents-info-files-row').show();
        $('#documents-info-size-row').hide();
        documentsInfoOpenDrawer();
        loadDocumentsInfoActivity('folder', $button.data('record-id') || 0, pathName);
        loadDocumentsInfoAccess(documentsInfoSelected);
    }

    var lastHighlightedFileRow = null;

    $tableElement.on('click', 'tbody tr', function (event) {
        if ($(event.target).closest('input, button, a, select, label, .doc-actions-menu').length) return;
        if (typeof marqueeSuppressClick !== 'undefined' && marqueeSuppressClick) {
            marqueeSuppressClick = false;
            return;
        }

        var row = table.row(this).data();
        if (!row) return;

        var $row = $(this);

        /*
         * A normal row click is for opening/highlighting the individual row;
         * it must not check its bulk-action checkbox. Checkboxes are changed
         * only by an explicit checkbox click or the drag/marquee selection
         * workflow used for multi-file transfer.
         */
        if (row.item_type === 'file') {
            $tableElement.find('tbody tr').removeClass('documents-row--selected');
            $row.addClass('documents-row--selected');
            lastHighlightedFileRow = this;
        } else {
            $tableElement.find('tbody tr').removeClass('documents-row--selected');
            $row.addClass('documents-row--selected');
        }

        if ($('#documents-info-drawer').hasClass('is-open')) {
            if (row.item_type === 'file') showDocumentInformation(row);
            else showFolderInformation(row);
        }
    });

    function renderDocumentsAccessModal(users) {
        users = $.isArray(users) ? users : [];
        var creator = documentsInfoSelected && documentsInfoSelected.created_by
            ? documentsInfoSelected.created_by
            : 'Admin';
        var $select = $('#documents-access-select').empty();
        var $list = $('#documents-access-current-list').empty();
        var allowed = $.grep(users, function (user) { return Number(user.has_access) === 1; });
        var available = $.grep(users, function (user) { return Number(user.has_access) !== 1; });

        $('#documents-access-creator-name').text(documentsInfoValue(creator));
        $('#documents-access-creator-avatar').text(documentsInfoActivityInitials(creator));

        $.each(available, function (_, user) {
            $('<option>')
                .val(user.user_id)
                .text((user.emp_name || user.username || 'User') + (user.username ? ' (' + user.username + ')' : ''))
                .appendTo($select);
        });

        if (!available.length) {
            $('<option disabled>No more users available to add</option>').appendTo($select);
        }

        if (!allowed.length) {
            $list.html('<div class="documents-access-empty">No users are currently tagged to this folder path.</div>');
        } else {
            $.each(allowed, function (_, user) {
                var displayName = user.emp_name || user.username || 'User';
                var username = user.username || '';
                var $row = $('<div class="documents-access-current-user"></div>');
                $('<input type="checkbox" class="documents-access-remove-check" aria-label="Select user for removal">')
                    .val(user.user_id)
                    .appendTo($row);
                $('<span class="documents-info-access-avatar"></span>')
                    .text(documentsInfoActivityInitials(displayName))
                    .appendTo($row);
                $('<span class="documents-access-current-copy"><strong></strong><small></small></span>')
                    .find('strong').text(displayName).end()
                    .find('small').text(username).end()
                    .appendTo($row);
                $('<button type="button" class="documents-access-remove-one" title="Remove access" aria-label="Remove access"><i class="bi bi-trash"></i></button>')
                    .attr('data-user-id', user.user_id)
                    .appendTo($row);
                $row.appendTo($list);
            });
        }

        $('#documents-access-remove-selected').prop('disabled', true);
    }

    function loadDocumentsAccessModal() {
        if (!documentsInfoSelected || !config.accessUrl) return;
        var token = documentsInfoPathToken(documentsInfoSelected);
        if (!token) return;
        $('#documents-access-current-list').html('<div class="documents-access-empty">Loading access...</div>');
        $('#documents-access-select').empty();
        $.getJSON(config.accessUrl, { path_token: token }).done(function (response) {
            if (!response || !response.success) {
                $('#documents-access-current-list').html('<div class="documents-access-empty">Access information could not be loaded.</div>');
                return;
            }
            renderDocumentsAccessModal(response.users || []);
        }).fail(function () {
            $('#documents-access-current-list').html('<div class="documents-access-empty">Access information could not be loaded.</div>');
        });
    }

    function updateDocumentsAccess(action, userIds, $button) {
        if (!documentsInfoSelected || !userIds.length) return;
        var request = {
            path_token: documentsInfoPathToken(documentsInfoSelected),
            action: action,
            user_ids: userIds
        };
        request[config.csrfName] = config.csrfHash;
        if ($button && $button.length) $button.prop('disabled', true);

        $.ajax({ url: config.accessUrl, type: 'POST', dataType: 'json', data: request }).done(function (response) {
            if (response && response.csrfName && response.csrfHash) {
                config.csrfName = response.csrfName;
                config.csrfHash = response.csrfHash;
            }
            if (!response || !response.success) {
                documentsAlert('Access not updated', response && response.message ? response.message : 'Access could not be updated.', 'error');
                return;
            }
            renderDocumentsAccessModal(response.users || []);
            renderDocumentsInfoAccess(
                response.users || [],
                documentsInfoSelected && documentsInfoSelected.created_by
                    ? documentsInfoSelected.created_by
                    : 'Admin'
            );
            showMessage('success', action === 'add' ? 'User access added successfully.' : 'User access removed successfully.');
        }).fail(function () {
            documentsAlert('Access not updated', 'The server could not update folder access.', 'error');
        }).always(function () {
            if ($button && $button.length) $button.prop('disabled', false);
        });
    }

    $(document).on('click', '#documents-info-close', documentsInfoCloseDrawer);
    $(document).on('click', '#documents-info-manage-access', function () {
        if (!documentsInfoSelected) return;
        var token = documentsInfoPathToken(documentsInfoSelected);
        if (!token) {
            documentsAlert('Access unavailable', 'This item is not inside a taggable RMS folder path.', 'info');
            return;
        }
        if (!config.accessUrl) return;
        $('#documents-access-subtitle').text('Add or remove users who can access “' + documentsInfoSelected.record_name + '”.');
        $('#documents-access-modal').addClass('show');
        loadDocumentsAccessModal();
    });

    $(document).on('click', '#documents-access-close, #documents-access-cancel', function () {
        $('#documents-access-modal').removeClass('show');
    });

    $(document).on('click', '#documents-access-add', function () {
        var ids = $('#documents-access-select').val() || [];
        if (!ids.length) {
            documentsAlert('Select users', 'Select one or more users to add.', 'info');
            return;
        }
        updateDocumentsAccess('add', ids, $(this));
    });

    $(document).on('change', '.documents-access-remove-check', function () {
        $('#documents-access-remove-selected').prop('disabled', $('.documents-access-remove-check:checked').length === 0);
    });

    $(document).on('click', '.documents-access-remove-one', function () {
        updateDocumentsAccess('remove', [$(this).attr('data-user-id')], $(this));
    });

    $(document).on('click', '#documents-access-remove-selected', function () {
        var ids = $.map($('.documents-access-remove-check:checked'), function (checkbox) { return $(checkbox).val(); });
        if (!ids.length) return;
        updateDocumentsAccess('remove', ids, $(this));
    });

    $(document).on('click', '.documents-info-tab', function () {
        var tab = $(this).data('info-tab');
        $('.documents-info-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.documents-info-panel').removeClass('is-active').filter('[data-info-panel="' + tab + '"]').addClass('is-active');
    });

    $(document).on(
        'click',
        '.doc-action-item',
        function (event) {
            if ($(this).is('a') && !$(this).data('action')) {
                saveManageTableState();
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            var action = $(this).data('action');
            var id = $(this).data('id');

            closeAllActionMenus();

            if (action === 'toggle-pin') {
                togglePinnedItem($(this));
            } else if (action === 'file-information') {
                showDocumentInformation(table.row($(this).closest('tr')).data());
            } else if (action === 'folder-information') {
                showFolderInformation(table.row($(this).closest('tr')).data());
            } else if (action === 'preview-file') {
                var row = table.row($(this).closest('tr')).data();
                openUnifiedFileViewer(row);
            } else if (action === 'rename-file') {
                renameUnifiedFile(table.row($(this).closest('tr')).data());
            } else if (action === 'transfer-file') {
                var transferRow =
                    table.row($(this).closest('tr')).data();

                documentsSwal({
                    title: 'Transfer this file?',
                    text: 'You will choose an unpublished destination folder next.',
                    icon: 'question',
                    confirmText: 'Continue'
                }).done(function (result) {
                    if (result.confirmed) {
                        openTransferModal([transferRow], false);
                    }
                });

            } else if (action === 'delete-file') {
                /*
                 * FILE ACTION:
                 * Delete the exact uploaded-file row whose menu was opened.
                 */
                var deleteRow =
                    table.row($(this).closest('tr')).data();

                if (
                    !deleteRow ||
                    !deleteRow.data_id ||
                    Number(config.currentCanUpload) !== 1
                ) {
                    notifyDocumentsAction(
                        'error',
                        'File could not be deleted',
                        'This file is not available for deletion.',
                        'delete-uploaded-file'
                    );

                    return;
                }

                documentsSwal({
                    title: 'Delete this file?',
                    text: 'The uploaded file will be permanently deleted. This action cannot be undone.',
                    icon: 'warning',
                    confirmText: 'Delete file',
                    confirmDanger: true
                }).done(function (result) {
                    if (!result.confirmed) {
                        return;
                    }

                    var request = {
                        /*
                         * config.level points to the displayed children.
                         * The currently opened folder is one level above.
                         */
                        level: Math.max(
                            0,
                            Number(config.level || 0) - 1
                        ),
                        record_id: Number(config.parentId || 0),
                        data_ids: [deleteRow.data_id]
                    };

                    request[config.csrfName] =
                        config.csrfHash;

                    $.ajax({
                        url: config.deleteUploadedUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: request
                    }).done(function (response) {
                        updateCsrf(response);

                        if (!response || response.success !== true) {
                            notifyDocumentsAction(
                                'error',
                                'File could not be deleted',
                                response && response.message
                                    ? response.message
                                    : 'The file could not be deleted.',
                                'delete-uploaded-file'
                            );

                            return;
                        }

                        notifyDocumentsAction(
                            'success',
                            'File deleted successfully',
                            response.message ||
                            'File deleted successfully.',
                            'delete-uploaded-file'
                        );

                        /*
                         * Reload the same location so the table and context
                         * file count are both updated.
                         */
                        saveManageTableState();

                        window.setTimeout(function () {
                            window.location.reload();
                        }, 650);
                    }).fail(function (xhr) {
                        notifyDocumentsAction(
                            'error',
                            'File could not be deleted',
                            xhr.responseJSON && xhr.responseJSON.message
                                ? xhr.responseJSON.message
                                : 'The file could not be deleted.',
                            'delete-uploaded-file'
                        );
                    });
                });

                        } else if (action === 'rename-current-folder') {
                showCurrentFolderRenameModal();
            } else if (action === 'edit') {
                documentsSwal({
                    title: 'Edit this folder?',
                    text: 'Review the folder details before saving your changes.',
                    icon: 'question',
                    confirmText: 'Continue'
                }).done(function (result) {
                    if (result.confirmed) showEditModal(id);
                });
            } else if (action === 'delete') {
                deleteRecord(id);
            } else if (action === 'view') {
                showViewModal(id);
            } else if (action === 'publish' || action === 'unpublish') {
                currentChecks().prop('checked', false);
                currentChecks().filter('[value="folder-' + id + '"]').prop('checked', true);
                updateSelection();
                changePublishStatus(action === 'publish' ? 1 : 0);
            }
        }
    );

    /**
     * Create the server-side DataTable for Manage Documents.
     * Sends level + parent_id on every ajax request so the list is scoped
     * to the current folder (Google Drive–style drill-down).
     * Column order: Checkbox, Name, Status, Modified, Owner, Actions.
     */
    function initializeDataTable() {
        if (!$tableElement.length) {
            return;
        }

        if (!$.fn.DataTable) {
            showMessage(
                'error',
                'The document table library could not be loaded.'
            );

            return;
        }

        $.fn.dataTable.ext.errMode = 'none';
        restoredSearch = searchForThisLoad();

        table = $tableElement.DataTable({
            processing: true,
            serverSide: true,
            deferRender: true,
            autoWidth: false,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            searchDelay: 350,
            order: [[1, 'asc']],
            stateSave: true,
            stateDuration: -1,
            stateSaveCallback: function (settings, data) {
                /* Search is refresh-only; never persist it across page visits. */
                data.search = data.search || {};
                data.search.search = '';
                saveStoredTableState(manageTableStateKey(), data);
            },
            stateLoadCallback: function () {
                var state = loadStoredTableState(manageTableStateKey());
                if (state && state.search) state.search.search = '';
                return state;
            },

            /* =========================================================
            PAGINATION TOP + BOTTOM
            Show entries sits on the LEFT of the same bar as pagination.
            "Showing X to Y of Z" text is completely removed.
            ========================================================= */
            pagingType: 'simple',

            dom:
                '<"documents-data-controls-top"l<"documents-pagination-bar"ip>>' +
                'rt' +
                '<"documents-data-footer"l<"documents-pagination-bar"ip>>',

            language: {
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_-_END_ of _TOTAL_',
                infoEmpty: 'Showing 0-0 of 0',
                infoFiltered: '',
                emptyTable: 'No document records found.',
                zeroRecords: 'No matching document records found.',
                paginate: {
                    previous: '←',
                    next: '→'
                }
            },
            ajax: {
                url: config.dataUrl,
                type: 'GET',

                data: function (request) {
                    request.level = config.level;
                    request.parent_id = config.parentId;
                },

                dataSrc: function (response) {
                    groupCounts = response && response.groupCounts
                        ? response.groupCounts
                        : { unpublished: 0, published: 0, files: 0 };
                    hierarchyFolderPins = response && response.folderPins
                        ? response.folderPins
                        : {};
                    updateHierarchyPinMarkers();
                    if (response && response.error) {
                        showMessage(
                            'error',
                            response.error
                        );
                    }

                    return (
                        response &&
                        $.isArray(response.data)
                    )
                        ? response.data
                        : [];
                },

                error: function (xhr) {
                    var text =
                        xhr.responseJSON &&
                            xhr.responseJSON.error
                            ? xhr.responseJSON.error
                            : 'The server could not load the document records.';

                    showMessage('error', text);
                }
            },

            /*
             * Column order (matches manage.php thead):
             * Checkbox | Name | Status | Modified | Owner | Actions
             * Size was removed. Status and Owner were swapped vs the
             * previous Owner | Modified | Size | Status layout.
             */
            columns: [
                {
                    /* Row selection checkbox */
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'check-column',
                    render: renderCheckbox
                },
                {
                    /* Folder or file name with icon and child summary */
                    data: 'record_name',
                    defaultContent: '',
                    className: 'folder-name-column',
                    render: renderName
                },
                {
                    /* Publish / unpublish badge (was after Owner; now before Modified) */
                    data: 'publish_status',
                    defaultContent: 0,
                    render: renderRemarks
                },
                {
                    /* Last modified date */
                    data: 'date_modified',
                    defaultContent: '',
                    render: renderDate
                },
                {
                    /* Owner username from created_by (was before Modified; now after) */
                    data: null,
                    defaultContent: 'Admin',
                    render: function (data, type, row) {
                        return renderUserLabel(
                            (row && row.created_by) ? row.created_by : 'Admin',
                            type
                        );
                    }
                },
                {
                    /* Per-row Actions menu (Open, Publish, Edit, etc.) */
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'actions-column',
                    render: renderActions
                }
            ],

            createdRow: function (row, data) {
                var openUrl = data.item_type === 'file'
                    ? (data.view_url || (config.fileUrl && data.data_id
                        ? config.fileUrl + '?token=' + encodeURIComponent(data.data_id)
                        : ''))
                    : data.next_url;
                $(row).attr('data-item-type', data.item_type || 'folder')
                    .attr('draggable', data.item_type === 'file' ? 'true' : 'false')
                    .toggleClass('is-pinned-record', Number(data.is_pinned) === 1)
                    .toggleClass('documents-row--file', data.item_type === 'file')
                    .toggleClass(
                        'documents-row--unpublished',
                        data.item_type !== 'file' && Number(data.publish_status) === 0
                    )
                    .toggleClass(
                        'documents-row--published',
                        data.item_type !== 'file' && Number(data.publish_status) === 1
                    );

                if (openUrl) {
                    $(row).children('td').eq(1)
                        .addClass('folder-name-openable')
                        .attr('data-item-type', data.item_type || 'folder')
                        .attr(
                            'data-next-url',
                            openUrl
                        );
                }
            },

            drawCallback: function () {
                var api = this.api();
                var order = api.order();
                var lastGroup = '';
                var pageInfo = api.page.info();
                var $wrapper = $(api.table().container());
                $wrapper.find('.page-of-label').remove();
                if (pageInfo.pages > 0) {
                    $wrapper.find('.dataTables_paginate .paginate_button.previous').after(
                        '<span class="page-of-label">Page ' + (pageInfo.page + 1) + ' of ' + pageInfo.pages + '</span>'
                    );
                }
                closeAllActionMenus();
                $tableElement.find('tbody .documents-status-group').remove();

                /* Group labels are presentation rows only when Status is ordered. */
                if (order.length && Number(order[0][0]) === 2) {
                    api.rows({ page: 'current' }).every(function () {
                        var data = this.data();
                        var node = this.node();
                        var group = data.item_type === 'file'
                            ? 'files'
                            : (Number(data.publish_status) === 0
                                ? 'unpublished' : 'published');
                        if (group === lastGroup) return;
                        lastGroup = group;
                        var label = group === 'files'
                            ? 'FILES'
                            : group.toUpperCase();
                        $('<tr class="documents-status-group documents-status-group--' + group + '">' +
                            '<td colspan="6">' + label + ' (' +
                            Number(groupCounts[group] || 0) + ')</td></tr>')
                            .insertBefore(node);
                    });
                }

                $selectAll.prop({
                    checked: false,
                    indeterminate: false
                });

                updateSelection();
                positionManageTable();
                updateHierarchyPinMarkers();
            },

            initComplete: function () {
                var api = this.api();
                $('#documents-folder-search').val(restoredSearch);
                if (restoredSearch) {
                    api.search(restoredSearch).draw();
                }
            },

            language: {
                emptyTable:
                    'This folder is empty.',

                info:
                    'Showing _START_ to _END_ of _TOTAL_ records',

                infoEmpty:
                    'Showing 0 to 0 of 0 records',

                lengthMenu:
                    'Show _MENU_ entries',

                processing:
                    'Loading folders and files...',

                search: '',

                searchPlaceholder:
                    'Search this folder'
            }
        });

        $tableElement.on(
            'error.dt',
            function (event, settings, code, text) {
                showMessage(
                    'error',
                    text ||
                    'The document table could not be loaded.'
                );
            }
        );
    }

    /*
 * CURRENT FOLDER ACTION:
 * Publish or unpublish the exact folder currently opened.
 * This reuses the existing Documents publish endpoint and permissions.
 */
    function changeCurrentFolderStatus() {
        var $button = $('#documents-current-status-action');

        if (
            !$button.length ||
            Number(config.currentCanChangeStatus) !== 1
        ) {
            return;
        }

        var recordId = Number(
            $button.attr('data-record-id')
        );

        var recordLevel = Number(
            $button.attr('data-record-level')
        );

        var targetStatus = Number(
            $button.attr('data-target-status')
        );

        var publishing = targetStatus === 1;

        if (
            recordId <= 0 ||
            recordLevel < 0 ||
            (targetStatus !== 0 && targetStatus !== 1)
        ) {
            notifyDocumentsAction(
                'error',
                'Status could not be updated',
                'The current folder information is invalid.',
                'current-folder-status'
            );

            return;
        }

        documentsSwal({
            title: publishing
                ? 'Publish this folder?'
                : 'Unpublish this folder?',
            /*
   * LEVEL 3 SHARED STATUS:
   * Explain that any Level 3 Admin may reverse the status.
   */
            text: publishing
                ? 'This folder will become published according to the existing RMS permissions.'
                : 'This folder will become unpublished. Any Level 3 Admin will still be able to publish it.',
            icon: 'question',
            confirmText: publishing
                ? 'Publish'
                : 'Unpublish'
        }).done(function (result) {
            if (!result.confirmed) {
                return;
            }

            var request = {
                level: recordLevel,
                publish: targetStatus,
                record_ids: [recordId]
            };

            request[config.csrfName] = config.csrfHash;

            $button.prop('disabled', true);

            $.ajax({
                url: config.publishUrl,
                type: 'POST',
                dataType: 'json',
                data: request
            }).done(function (response) {
                updateCsrf(response);

                if (!response || response.success !== true) {
                    notifyDocumentsAction(
                        'error',
                        'Status could not be updated',
                        response && response.message
                            ? response.message
                            : 'The folder status could not be updated.',
                        'current-folder-status'
                    );

                    $button.prop('disabled', false);
                    return;
                }

                notifyDocumentsAction(
                    'success',
                    publishing
                        ? 'Folder published successfully'
                        : 'Folder unpublished successfully',
                    response.message || (
                        publishing
                            ? 'Folder published successfully.'
                            : 'Folder unpublished successfully.'
                    ),
                    'current-folder-status'
                );

                /*
                 * Save the existing DataTable position, then reload the
                 * same current folder so the context panel, breadcrumb,
                 * ownership paths and file count are rebuilt correctly.
                 */
                saveManageTableState();

                window.setTimeout(function () {
                    window.location.reload();
                }, 650);
            }).fail(function (xhr) {
                notifyDocumentsAction(
                    'error',
                    'Status could not be updated',
                    xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'The folder status could not be updated.',
                    'current-folder-status'
                );

                $button.prop('disabled', false);
            });
        });
    }
        // ===== Context card options dropdown (⋮) =====
    (function initContextOptionsMenu() {
        var $toggle = $('#documents-context-options-toggle');
        var $menu   = $('#documents-context-options-menu');

        if (!$toggle.length || !$menu.length) return;

        // Toggle open/close
        $toggle.on('click', function (e) {
            e.stopPropagation();
            var isOpen = !$menu.prop('hidden');
            // close other menus first
            closeAllActionMenus();
            $menu.prop('hidden', isOpen);
            $toggle.attr('aria-expanded', isOpen ? 'false' : 'true');
        });

        // Close when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.documents-context-options').length) {
                $menu.prop('hidden', true);
                $toggle.attr('aria-expanded', 'false');
            }
        });

        // Update pin label when hierarchy pins change
        var originalUpdate = updateHierarchyPinMarkers;
        updateHierarchyPinMarkers = function () {
            originalUpdate();
            var $ctx = $('.documents-folder-context[data-pin-record-id]');
            if (!$ctx.length) return;
            var key = Number($ctx.attr('data-pin-level')) + ':' + Number($ctx.attr('data-pin-record-id'));
            var pinned = Number(hierarchyFolderPins[key]) === 1;
            $('#documents-context-pin-action .pin-label').text(pinned ? 'Unpin' : 'Pin');
        };

        // Pin / Unpin
        $('#documents-context-pin-action').on('click', function (e) {
            e.stopPropagation();
            togglePinnedItem($(this));
            $menu.prop('hidden', true);
            $toggle.attr('aria-expanded', 'false');
        });

        // Publish / Unpublish – reuses existing function & validations
        $('#documents-current-status-action').on('click', function (e) {
            e.stopPropagation();
            changeCurrentFolderStatus();
            $menu.prop('hidden', true);
            $toggle.attr('aria-expanded', 'false');
        });

        // Rename – trigger the existing edit flow for the current folder
        // Rename – opens the existing small RMS edit modal
// Rename the exact folder displayed in the current-folder banner.
$('#documents-context-rename').on('click', function (event) {
    event.preventDefault();
    event.stopPropagation();

showCurrentFolderRenameModal();
        });
        // Current-folder information
        $('#documents-context-info').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            showCurrentFolderInformation($(this));
            $menu.prop('hidden', true);
            $toggle.attr('aria-expanded', 'false');
        });
    })();
    /* CURRENT FOLDER ACTION: bind once to the context-panel button. */
    $(document).on(
        'click',
        '#documents-current-status-action',
        function (event) {
            event.preventDefault();
            changeCurrentFolderStatus();
        }
    );
    /**
     * Bulk publish (1) or unpublish (0) the selected folder record_ids.
     * Reloads the table on success and updates the CSRF token from the response.
     */
    function changePublishStatus(publish) {
        var ids = selectedIds();

        var actionName =
            publish === 1
                ? 'publish'
                : 'unpublish';

        if (!ids.length) {
            return;
        }

        documentsSwal({
            title:
                (publish === 1 ? 'Publish' : 'Unpublish') +
                ' selected folders?',
            text: publish === 1
                ? 'The selected folders will be published. Ownership does not restrict this Level 3 status action.'
                : 'The selected folders will become unpublished. Any Level 3 Admin will still be able to publish them.',
            icon: 'question',
            confirmText:
                publish === 1
                    ? 'Publish'
                    : 'Unpublish'
        }).done(function (result) {
            if (!result.confirmed) return;

            var request = {
                level: config.level,
                publish: publish,
                record_ids: ids
            };

            request[config.csrfName] =
                config.csrfHash;

            $publishButton.prop(
                'disabled',
                true
            );

            $unpublishButton.prop(
                'disabled',
                true
            );

            $.ajax({
                url: config.publishUrl,
                type: 'POST',
                dataType: 'json',
                data: request
            }).done(function (response) {
                if (
                    response.csrfName &&
                    response.csrfHash
                ) {
                    config.csrfName =
                        response.csrfName;

                    config.csrfHash =
                        response.csrfHash;
                }

                if (!response.success) {
                    showMessage(
                        'error',
                        response.message ||
                        'The records could not be updated.'
                    );

                    updateSelection();
                    return;
                }

                showMessage(
                    'success',
                    response.message
                );

                /*
                 * Refresh the complete current location so the Add New
                 * Document and Add New Subfolder Path lists immediately
                 * reflect the current user's publish/unpublish changes.
                 */
                window.setTimeout(function () {
                    saveManageTableState();
                    window.location.reload();
                }, 400);
            }).fail(function (xhr) {
                var text =
                    xhr.responseJSON &&
                        xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'The server could not process the request.';

                showMessage('error', text);
                updateSelection();
            });
        });
    }

    $selectAll.on('change', function () {
        currentChecks().prop(
            'checked',
            this.checked
        );

        updateSelection();
    });

    $(document).on(
        'change',
        '.document-check',
        updateSelection
    );

    $tableElement.on(
        'dblclick',
        'tbody td.folder-name-openable',
        function (event) {
            if (
                $(event.target).closest(
                    'input, button, select, label'
                ).length
            ) {
                return;
            }

            saveManageTableState();
            if ($(this).attr('data-item-type') === 'file') {
                openUnifiedFileViewer(table.row($(this).closest('tr')).data());
                return;
            }
            window.location.href = $(this).attr('data-next-url');
        }
    );

    /* Full in-folder viewer: navigation, fit/zoom and left-button image pan. */
    var unifiedFiles = [];
    var unifiedFileIndex = 0;
    var unifiedScale = 1;
    var unifiedOffsetX = 0;
    var unifiedOffsetY = 0;
    var unifiedDragging = false;
    var unifiedDragX = 0;
    var unifiedDragY = 0;
    var unifiedInspectTimer = null;
    /* VIEWER FIX: remember the page scroll position while the viewer locks the background. */
    var unifiedViewerScrollY = 0;
    var unifiedViewerBodyTop = '';
    var unifiedViewMode = 'page';
    var unifiedVerticalDragging = false;
    var unifiedVerticalDragX = 0;
    var unifiedVerticalDragY = 0;
    var unifiedVerticalScrollLeft = 0;
    var unifiedVerticalScrollTop = 0;

    function markUnifiedInspecting(active) {
        window.clearTimeout(unifiedInspectTimer);
        $('#unified-file-viewer').toggleClass('is-inspecting', active);
        if (active && !unifiedDragging) {
            unifiedInspectTimer = window.setTimeout(function () {
                $('#unified-file-viewer').removeClass('is-inspecting');
            }, 650);
        }
    }


    /*
     * WATERMARK-FIRST DISPLAY:
     * Preserve the original filename while row.view_url selects the
     * secure watermark-first preview.
     */
    function normalizedViewerFile(file) {
        var fallbackViewUrl = '';

        if (file && file.data_id && config.fileUrl) {
            fallbackViewUrl = config.fileUrl + '?token=' + encodeURIComponent(file.data_id);
        }

        return {
            data_id: file.data_id || '',
            page_no: Number(file.page_no || 0),
            record_name:
                file.record_name ||
                file.data_name ||
                'Document',
            view_url: file.view_url || fallbackViewUrl,
            download_url:
                file.download_url ||
                file.view_url ||
                fallbackViewUrl,
            has_watermark:
                Number(file.has_watermark) === 1 ? 1 : 0,
            preview_type:
                file.preview_type || 'unavailable',
            preview_label:
                file.preview_label ||
                (
                    Number(file.has_watermark) === 1
                        ? 'Watermarked preview'
                        : 'Watermarked preview unavailable'
                )
        };
    }

    function applyUnifiedVerticalZoom() {
        $('#unified-vertical-scroll .unified-vertical-page img').each(function () {
            if (!this.naturalWidth || !this.naturalHeight) return;
            $(this).css({
                width: Math.max(1, Math.round(this.naturalWidth * unifiedScale)) + 'px',
                height: Math.max(1, Math.round(this.naturalHeight * unifiedScale)) + 'px',
                transform: 'none'
            });
        });
    }

    function renderUnifiedTransform() {
        $('#unified-file-image').css('transform',
            'translate(' + unifiedOffsetX + 'px,' + unifiedOffsetY + 'px) scale(' + unifiedScale + ')');
        applyUnifiedVerticalZoom();
        $('#unified-file-zoom').text(Math.round(unifiedScale * 100) + '%');
    }

    function renderUnifiedVerticalPages() {
        var $scroll = $('#unified-vertical-scroll');
        $scroll.empty();

        $.each(unifiedFiles, function (index, file) {
            var extension = String(file.record_name || '').split('.').pop().toLowerCase();
            var isImage = /^(jpg|jpeg|png|gif|webp|bmp)$/.test(extension);
            var $page = $('<div class="unified-vertical-page"></div>')
                .attr('data-file-index', index);

            $('<div class="unified-vertical-page-label"></div>')
                .text('Page ' + (index + 1) + ' of ' + unifiedFiles.length)
                .appendTo($page);

            if (isImage && file.view_url) {
                $('<img alt="Document page" draggable="false">')
                    .attr('src', file.view_url)
                    .on('load', function () {
                        applyUnifiedVerticalZoom();
                    })
                    .appendTo($page);
            } else {
                $('<div class="unified-vertical-page-label"></div>')
                    .text(file.record_name + ' cannot be displayed in vertical image mode.')
                    .appendTo($page);
            }

            $scroll.append($page);
        });
    }

    function setUnifiedViewMode(mode) {
        unifiedViewMode = mode === 'vertical' ? 'vertical' : 'page';
        var vertical = unifiedViewMode === 'vertical';

        $('#unified-file-viewer').toggleClass('is-vertical', vertical);
        $('#unified-view-mode-label').text(vertical ? 'Vertical Scroll' : 'Page Navigation');
        $('#unified-view-mode-menu [data-view-mode]')
            .removeClass('is-active')
            .filter('[data-view-mode="' + unifiedViewMode + '"]')
            .addClass('is-active');
        $('#unified-view-mode-menu').prop('hidden', true);
        $('#unified-view-mode-toggle').attr('aria-expanded', 'false');

        if (vertical) {
            $('#unified-vertical-scroll').prop('hidden', false);
            renderUnifiedVerticalPages();
        } else {
            $('#unified-vertical-scroll').prop('hidden', true).empty();
            displayUnifiedFile(unifiedFileIndex);
        }
    }

    function fitUnifiedImage() {
        markUnifiedInspecting(true);
        var image = $('#unified-file-image').get(0);
        var stage = $('.unified-viewer-body').get(0);
        if (!image || !stage || !image.naturalWidth || !image.naturalHeight) return;
        unifiedScale = Math.min(
            (stage.clientWidth - 28) / image.naturalWidth,
            (stage.clientHeight - 28) / image.naturalHeight,
            1
        );
        unifiedOffsetX = 0;
        unifiedOffsetY = 0;
        renderUnifiedTransform();
    }

    function changeUnifiedZoom(delta) {
        markUnifiedInspecting(true);
        unifiedScale = Math.max(.1, Math.min(5, unifiedScale + delta));
        renderUnifiedTransform();
    }

    /* VIEWER FIX: safely end right-button dragging and clear the drag state. */
    function stopUnifiedDrag() {
        unifiedDragging = false;
        unifiedVerticalDragging = false;
        $('#unified-file-image, #unified-vertical-scroll .unified-vertical-page img')
            .removeClass('is-dragging');
        markUnifiedInspecting(false);
    }

    /* VIEWER FIX: lock both document roots and remember the scroll position so it can be restored exactly. */
    function lockUnifiedViewerBackground() {
        unifiedViewerScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        unifiedViewerBodyTop = document.body.style.top;
        document.body.style.top = '-' + unifiedViewerScrollY + 'px';
        $('html, body').addClass('modal-open rms-viewer-locked');
    }

    /* VIEWER FIX: restore the exact page position once no other modal still needs the lock. */
    function unlockUnifiedViewerBackground() {
        if ($('.rms-modal.show').length) {
            return;
        }
        $('html, body').removeClass('modal-open rms-viewer-locked');
        document.body.style.top = unifiedViewerBodyTop;
        window.scrollTo(0, unifiedViewerScrollY);
    }

    function displayUnifiedFile(index) {
        if (!unifiedFiles.length) return;
        /* VIEWER FIX: cancel any active drag before switching to another file. */
        stopUnifiedDrag();
        unifiedFileIndex = (index + unifiedFiles.length) % unifiedFiles.length;
        var file = unifiedFiles[unifiedFileIndex];
        var extension = String(file.record_name).split('.').pop().toLowerCase();
        var isImage = /^(jpg|jpeg|png|gif|webp|bmp)$/.test(extension);
        $('#unified-file-title').text(file.record_name);
        $('#unified-file-position').text('File ' + (unifiedFileIndex + 1) + ' of ' + unifiedFiles.length);
        $('#unified-file-download').attr('href', file.download_url);
        $('#unified-previous-file, #unified-next-file').prop('disabled', unifiedFiles.length < 2);
        $('#unified-zoom-out, #unified-zoom-in, #unified-fit-file, #unified-actual-file').prop('disabled', !isImage);
        unifiedScale = 1; unifiedOffsetX = 0; unifiedOffsetY = 0;
        if (isImage) {
            $('#unified-file-frame').prop('hidden', true).attr('src', '');
            $('#unified-file-image').prop('hidden', false).one('load', fitUnifiedImage).attr('src', file.view_url);
        } else {
            $('#unified-file-image').prop('hidden', true).attr('src', '');
            $('#unified-file-frame').prop('hidden', false).attr('src', file.view_url);
            $('#unified-file-zoom').text('100%');
        }
    }

    function openUnifiedFileViewer(file) {
        if (!file) return;
        var initial = normalizedViewerFile(file);
        if (!initial.view_url) {
            documentsAlert(
                'Preview unavailable',
                'The document preview URL could not be created.',
                'error'
            );
            return;
        }
        unifiedFiles = [initial];
        $('#unified-file-viewer').addClass('show').attr('aria-hidden', 'false');
        /* VIEWER FIX: lock the background as soon as the viewer opens. */
        lockUnifiedViewerBackground();
        displayUnifiedFile(0);

        /* Load every direct file so Next/Previous is independent of paging. */
        /* Load files from the exact folder currently open in Manage Documents.
         * Using the browse level/parent here can load a sibling/previous folder
         * after deep navigation or transfers, replacing the clicked file with
         * the wrong document in the viewer. */
        var viewerFolderLevel = Number(config.currentFolderLevel);
        var viewerFolderId = Number(config.currentFolderId || 0);
        if (viewerFolderLevel < 0 || !viewerFolderId) {
            viewerFolderLevel = Math.max(0, Number(config.level || 0) - 1);
            viewerFolderId = Number(config.parentId || 0);
        }
        $.getJSON(config.folderFilesUrl, {
            level: viewerFolderLevel,
            record_id: viewerFolderId,
            draw: 1, start: 0, length: 100, search: { value: '' }
        }).done(function (response) {
            if (!response || !$.isArray(response.data) || !response.data.length) return;
            unifiedFiles = $.map(response.data, normalizedViewerFile);
            var found = -1;
            $.each(unifiedFiles, function (index, candidate) {
                /* Document tokens are encrypted with a random IV, so the same
                 * data_id receives a different token on each AJAX response.
                 * Match the clicked document by its stable visible identity
                 * within the current folder instead of comparing token text. */
                if (
                    candidate.record_name === initial.record_name &&
                    Number(candidate.page_no || 0) === Number(initial.page_no || 0)
                ) {
                    found = index;
                    return false;
                }
            });
            if (found >= 0) {
                unifiedFileIndex = found;
                if (unifiedViewMode === 'vertical') {
                    renderUnifiedVerticalPages();
                } else {
                    displayUnifiedFile(found);
                }
            }
        });
    }

    function closeUnifiedViewer() {
        /* VIEWER FIX: stop dragging, release capture, and restore the background scroll position. */
        stopUnifiedDrag();
        $('#unified-file-viewer').removeClass('show').attr('aria-hidden', 'true');
        $('#unified-file-image, #unified-file-frame').attr('src', '');
        unlockUnifiedViewerBackground();
    }

    function renameUnifiedFile(file) {
        file = file ? normalizedViewerFile(file) : unifiedFiles[unifiedFileIndex];
        if (!file || !file.data_id) return;
        documentsSwal({
            title: 'Rename this file?',
            text: 'Enter the new file name, then confirm the change.',
            icon: 'question',
            input: true,
            inputRequired: true,
            inputValue: file.record_name,
            confirmText: 'Rename'
        }).done(function (result) {
            var name = result.value;
            if (!result.confirmed || name === file.record_name) return;

            var cleanName = $.trim(String(name || ''));
            var baseName = cleanName.replace(/\.[^.]+$/, '');
            if (!baseName || /[^A-Za-z0-9 _-]/.test(baseName)) {
                documentsAlert(
                    'Invalid file name',
                    'Special characters are not allowed. Use letters, numbers, spaces, hyphens, or underscores only.',
                    'error'
                );
                return;
            }

            var request = {
                level: Math.max(0, Number(config.level || 0) - 1),
                record_id: Number(config.parentId || 0),
                data_id: file.data_id,
                name: name
            };
            request[config.csrfName] = config.csrfHash;
            $.post(config.renameUploadedUrl, request, function (response) {
                updateCsrf(response);
                if (!response || response.success !== true) {
                    documentsAlert('Rename failed', response && response.message ? response.message : 'The file could not be renamed.', 'error');
                    return;
                }
                file.record_name = name;
                $('#unified-file-title').text(file.record_name);
                table.ajax.reload(null, false);
            }, 'json').fail(function () {
                documentsAlert('Rename failed', 'The server could not rename the file.', 'error');
            });
        });
    }

    $('#unified-file-transfer').on('click', function () {
        var file = unifiedFiles[unifiedFileIndex];
        if (!file) return;
        documentsSwal({ title: 'Transfer this file?', text: 'You will choose an unpublished destination folder next.', icon: 'question', confirmText: 'Continue' }).done(function (result) { if (result.confirmed) openTransferModal([file], true); });
    });
    $('#unified-file-delete').on('click', function () {
        var file = unifiedFiles[unifiedFileIndex];
        if (!file) return;
        documentsSwal({ title: 'Delete this file?', text: 'This action cannot be undone.', icon: 'warning', confirmText: 'Delete', confirmDanger: true }).done(function (result) {
            if (!result.confirmed) return;
            var request = { level: Math.max(0, Number(config.level || 0) - 1), record_id: Number(config.parentId || 0), data_ids: [file.data_id] };
            request[config.csrfName] = config.csrfHash;
            $.post(config.deleteUploadedUrl, request, function (response) { updateCsrf(response); if (!response || response.success !== true) { documentsAlert('Delete failed', response && response.message ? response.message : 'The file could not be deleted.', 'error'); return; } closeUnifiedViewer(); table.ajax.reload(null, false); showMessage('success', response.message); }, 'json').fail(function () { documentsAlert('Delete failed', 'The server could not delete the file.', 'error'); });
        });
    });
    $('#unified-file-actions-toggle').on('click', function (event) { event.stopPropagation(); var $menu = $('#unified-file-actions-dropdown'), open = $menu.prop('hidden'); $menu.prop('hidden', !open); $(this).attr('aria-expanded', open ? 'true' : 'false'); });
    $('#unified-view-mode-toggle').on('click', function (event) {
        event.stopPropagation();
        var $menu = $('#unified-view-mode-menu');
        var open = $menu.prop('hidden');
        $menu.prop('hidden', !open);
        $(this).attr('aria-expanded', open ? 'true' : 'false');
    });
    $('#unified-view-mode-menu').on('click', '[data-view-mode]', function (event) {
        event.stopPropagation();
        setUnifiedViewMode($(this).attr('data-view-mode'));
    });
    $(document).on('click', function (event) {
        if (!$(event.target).closest('.unified-file-actions-menu').length) {
            $('#unified-file-actions-dropdown').prop('hidden', true);
            $('#unified-file-actions-toggle').attr('aria-expanded', 'false');
        }
        if (!$(event.target).closest('.unified-view-mode').length) {
            $('#unified-view-mode-menu').prop('hidden', true);
            $('#unified-view-mode-toggle').attr('aria-expanded', 'false');
        }
    });
    $(document).on('click', '[data-close-unified-viewer]', closeUnifiedViewer);

    /*
     * Never allow native HTML drag/drop to escape from the preview modal.
     * Without this guard, dragging a preview image can bubble a browser file
     * drop into the page-level uploader and open the Upload Documents modal.
     */
    $('#unified-file-viewer').on(
        'dragstart.unifiedViewer dragenter.unifiedViewer dragover.unifiedViewer dragleave.unifiedViewer drop.unifiedViewer',
        function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
            }
            return false;
        }
    );

    $('#unified-file-rename').on('click', function () { renameUnifiedFile(); });
    $('#unified-previous-file').on('click', function () { displayUnifiedFile(unifiedFileIndex - 1); });
    $('#unified-next-file').on('click', function () { displayUnifiedFile(unifiedFileIndex + 1); });
    $('#unified-zoom-in').on('click', function () { changeUnifiedZoom(.1); });
    $('#unified-zoom-out').on('click', function () { changeUnifiedZoom(-.1); });
    $('#unified-fit-file').on('click', fitUnifiedImage);
    $('#unified-actual-file').on('click', function () { markUnifiedInspecting(true); unifiedScale = 1; unifiedOffsetX = 0; unifiedOffsetY = 0; renderUnifiedTransform(); });
    /* Hold the left mouse button and drag to pan the current document. */
    $('#unified-file-image')
    .on('dragstart.unifiedViewer', function (event) {
        event.preventDefault();
    })
    .on('contextmenu.unifiedViewer', function (event) {
        event.preventDefault();
    }).on('mousedown.unifiedViewer', function (event) {
        var originalEvent = event.originalEvent || event;
        if (originalEvent.button !== 0) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        unifiedDragging = true;
        markUnifiedInspecting(true);
        unifiedDragX = originalEvent.clientX - unifiedOffsetX;
        unifiedDragY = originalEvent.clientY - unifiedOffsetY;
        $(this).addClass('is-dragging');
    });
    $(document).on('mousemove.unifiedViewer', function (event) {
        if (!unifiedDragging) {
            return;
        }
        var originalEvent = event.originalEvent || event;
        event.preventDefault();
        unifiedOffsetX = originalEvent.clientX - unifiedDragX;
        unifiedOffsetY = originalEvent.clientY - unifiedDragY;
        renderUnifiedTransform();
    }).on('mouseup.unifiedViewer', function (event) {
        if (!unifiedDragging) {
            return;
        }
        event.preventDefault();
        stopUnifiedDrag();
    });
    /* VIEWER FIX: also stop dragging if the window loses focus mid-drag (e.g. alt-tab). */
    $(window).on('blur.unifiedViewer', function () {
        if (unifiedDragging || unifiedVerticalDragging) {
            stopUnifiedDrag();
        }
    });
    /* Keyboard +/- and 0 zoom shortcuts remain available while the viewer is open. */
    $(document).on('keydown.unifiedViewer', function (event) {
        if (!$('#unified-file-viewer').hasClass('show') || !event.ctrlKey) return;
        if (event.key === '+' || event.key === '=') { event.preventDefault(); changeUnifiedZoom(.1); }
        if (event.key === '-') { event.preventDefault(); changeUnifiedZoom(-.1); }
    });
    /* VIEWER FIX: Ctrl + wheel zoom only. */
    $('.unified-viewer-body').on('wheel.unifiedViewer', function (event) {
        if (!event.ctrlKey) return;
        event.preventDefault();
        event.stopPropagation();

        var originalEvent = event.originalEvent || event;
        var verticalScroll = $('#unified-vertical-scroll').get(0);
        var oldScale = unifiedScale;
        var oldScrollWidth = verticalScroll ? verticalScroll.scrollWidth : 0;
        var oldScrollHeight = verticalScroll ? verticalScroll.scrollHeight : 0;
        var oldScrollLeft = verticalScroll ? verticalScroll.scrollLeft : 0;
        var oldScrollTop = verticalScroll ? verticalScroll.scrollTop : 0;

        changeUnifiedZoom(originalEvent.deltaY < 0 ? .1 : -.1);

        if (
            unifiedViewMode === 'vertical' &&
            verticalScroll &&
            oldScale !== unifiedScale
        ) {
            var xRatio = oldScrollWidth > 0
                ? (oldScrollLeft + originalEvent.offsetX) / oldScrollWidth
                : 0;
            var yRatio = oldScrollHeight > 0
                ? (oldScrollTop + originalEvent.offsetY) / oldScrollHeight
                : 0;

            window.requestAnimationFrame(function () {
                verticalScroll.scrollLeft = Math.max(
                    0,
                    (verticalScroll.scrollWidth * xRatio) - originalEvent.offsetX
                );
                verticalScroll.scrollTop = Math.max(
                    0,
                    (verticalScroll.scrollHeight * yRatio) - originalEvent.offsetY
                );
            });
        }
    });

    $('#unified-vertical-scroll')
        .on('dragstart.unifiedViewer', 'img', function (event) {
            event.preventDefault();
        })
        .on('contextmenu.unifiedViewer', function (event) {
            event.preventDefault();
        })
        .on('mousedown.unifiedViewer', 'img', function (event) {
            var originalEvent = event.originalEvent || event;
            var scroll = $('#unified-vertical-scroll').get(0);
            if (unifiedViewMode !== 'vertical' || originalEvent.button !== 0 || !scroll) return;
            event.preventDefault();
            event.stopPropagation();
            unifiedVerticalDragging = true;
            unifiedVerticalDragX = originalEvent.clientX;
            unifiedVerticalDragY = originalEvent.clientY;
            unifiedVerticalScrollLeft = scroll.scrollLeft;
            unifiedVerticalScrollTop = scroll.scrollTop;
            $(this).addClass('is-dragging');
            markUnifiedInspecting(true);
        });

    $(document).on('mousemove.unifiedVerticalViewer', function (event) {
        if (!unifiedVerticalDragging) return;
        var scroll = $('#unified-vertical-scroll').get(0);
        if (!scroll) return;
        var originalEvent = event.originalEvent || event;
        event.preventDefault();
        scroll.scrollLeft = unifiedVerticalScrollLeft - (originalEvent.clientX - unifiedVerticalDragX);
        scroll.scrollTop = unifiedVerticalScrollTop - (originalEvent.clientY - unifiedVerticalDragY);
    }).on('mouseup.unifiedVerticalViewer', function (event) {
        if (!unifiedVerticalDragging) return;
        event.preventDefault();
        unifiedVerticalDragging = false;
        $('#unified-vertical-scroll .unified-vertical-page img').removeClass('is-dragging');
        markUnifiedInspecting(false);
    });

    /* Arrow keys navigate only while the viewer is open and no form is active. */
    $(document).on('keydown.unifiedNavigation', function (event) {
        if (!$('#unified-file-viewer').hasClass('show') || event.ctrlKey || event.altKey || event.metaKey) return;
        if ($(event.target).is('input, textarea, select')) return;
        if (unifiedViewMode === 'vertical') {
            var scroll = $('#unified-vertical-scroll').get(0);
            if (scroll && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
                event.preventDefault();
                scroll.scrollBy({
                    top: event.key === 'ArrowDown' ? 180 : -180,
                    behavior: 'smooth'
                });
            }
        } else {
            if (event.key === 'ArrowLeft') { event.preventDefault(); displayUnifiedFile(unifiedFileIndex - 1); }
            if (event.key === 'ArrowRight') { event.preventDefault(); displayUnifiedFile(unifiedFileIndex + 1); }
        }
        if (event.key === 'Escape') { event.preventDefault(); closeUnifiedViewer(); }
    });

    $publishButton.on('click', function () {
        changePublishStatus(1);
    });

    $unpublishButton.on('click', function () {
        changePublishStatus(0);
    });

    $('#documents-bulk-toggle').on('click', function (event) {
        event.stopPropagation();
        closeAllActionMenus();
        var $menu = $('#documents-bulk-menu');
        var open = $menu.prop('hidden');
        $menu.prop('hidden', !open);
        $(this).attr('aria-expanded', open ? 'true' : 'false');
    });
    $(document).on('click', function (event) {
        if (!$(event.target).closest('.documents-bulk').length) {
            closeBulkMenu();
        }
    });

    /**
     * Apply the value currently entered in the Documents search field.
     *
     * Short RMS names such as IT, ICM, CAR, and IAD are applied only when
     * Search is clicked or Enter is pressed. The server-side DataTable performs
     * an Ajax redraw; the complete browser page is not reloaded.
     */
    function applyDocumentsSearch() {
        var value = String(
            $('#documents-folder-search').val() || ''
        );

        if (!table) {
            return;
        }

        table.search(value).draw();
    }

    /**
     * Search rules:
     *
     * Empty input  = clear immediately.
     * 1–4 chars    = remain pending.
     * 5+ chars     = search automatically.
     */
    // DOCUMENT SEARCH: typing must never reload the DataTable.
    $('#documents-folder-search').off('input.documentsSearch');

    /* DOCUMENT SEARCH: apply only after Search is clicked. */
    $('#documents-search-submit')
        .off('click.documentsSearch')
        .on('click.documentsSearch', function () {
            if (!table) {
                return;
            }

            table
                .search(
                    String($('#documents-folder-search').val() || '').trim()
                )
                .page('first')
                .draw(false);
        });

    /* DOCUMENT SEARCH: Enter performs the same explicit search. */
    $('#documents-folder-search')
        .off('keydown.documentsSearch')
        .on('keydown.documentsSearch', function (event) {
            if (event.key !== 'Enter' && event.which !== 13) {
                return;
            }

            event.preventDefault();
            $('#documents-search-submit').trigger('click');
        });

    /* DOCUMENT SEARCH: Reset clears and reloads only the DataTable. */
    $('#documents-search-reset')
        .off('click.documentsSearch')
        .on('click.documentsSearch', function () {
            $('#documents-folder-search').val('');

            if (table) {
                table.search('').page('first').draw(false);
            }

            $(this).blur();
        });

    /* Apply short or long searches explicitly. */
    $('#documents-search-submit').on('click', function () {
        applyDocumentsSearch();
    });

    /* Enter applies a pending 1–4 character search. */
    $('#documents-folder-search').on('keydown', function (event) {
        if (event.key === 'Enter' || event.which === 13) {
            event.preventDefault();
            applyDocumentsSearch();
        }
    });

    $('#documents-search-reset').on('click', function () {
        $('#documents-folder-search').val('');

        if (table) {
            table.search('').draw();
        }

        try {
            window.sessionStorage.removeItem(
                reloadSearchKey()
            );
        } catch (error) {
            /* Reset still works when sessionStorage is unavailable. */
        }

        $('#documents-folder-search').trigger('focus');
    });
    $(window).on('beforeunload.documentsSearch', rememberSearchForRefresh);
    $('#documents-filter-toggle').on('click', function () {
        var selectedOnly = !$(this).hasClass('is-active');
        $(this).toggleClass('is-active', selectedOnly);
        currentChecks().each(function () {
            $(this).closest('tr').toggle(!selectedOnly || this.checked);
        });
    });

    function selectedFileRows() {
        var rows = [];
        currentChecks().filter(':checked[data-item-type="file"]').each(function () {
            var row = table.row($(this).closest('tr')).data();
            if (row) rows.push(row);
        });
        return rows;
    }

    function selectedFolderRows() {
        var rows = [];
        currentChecks().filter(':checked[data-item-type="folder"]').each(function () {
            var row = table.row($(this).closest('tr')).data();
            if (row) rows.push(row);
        });
        return rows;
    }

    function displayedHierarchyPath(row) {
        var parts = $.isArray(config.currentPath) ? config.currentPath.slice(0) : [];
        parts.push(row.record_name || 'Unnamed folder');
        return parts.join(' / ');
    }

    var transferFiles = [], transferTarget = null, transferCloseViewer = false;
    function closeTransferModal() {
        $('#documents-transfer-modal').removeClass('show');
        $('#documents-transfer-message').removeClass('error success').text('').hide();
        $('.transfer-destination').removeClass('is-selected');
        $('#documents-transfer-submit').prop('disabled', true);
        transferTarget = null;
        if (!$('#unified-file-viewer').hasClass('show')) $('body').removeClass('modal-open');
    }
    function openTransferModal(files, closeViewerAfter) {
        if (!files || !files.length) return;
        transferFiles = files; transferCloseViewer = closeViewerAfter === true; transferTarget = null;
        $('#documents-transfer-search').val('').trigger('input');
        $('.transfer-destination').removeClass('is-selected');
        $('#documents-transfer-submit').prop('disabled', true);
        $('#documents-transfer-modal').addClass('show'); $('body').addClass('modal-open');
    }
    function transferToTarget(files, level, id, closeViewerAfter) {
        if (!files.length || !id) return;
        /* Use the exact opened-folder identity as the transfer source. After a
         * successful move the document belongs to its new folder, so deriving
         * the source from the browse level/parent can point at the previous
         * hierarchy and prevents the same document from being moved again. */
        var sourceLevel = Number(config.currentFolderLevel);
        var sourceId = Number(config.currentFolderId || 0);
        if (sourceLevel < 0 || !sourceId) {
            sourceLevel = Math.max(0, Number(config.level || 0) - 1);
            sourceId = Number(config.parentId || 0);
        }
        var request = { level: sourceLevel, record_id: sourceId, target_level: Number(level), target_id: Number(id), data_ids: $.map(files, function (file) { return file.data_id; }) };
        request[config.csrfName] = config.csrfHash;
        $.post(config.transferUploadedUrl, request, function (response) {
            updateCsrf(response);
            if (!response || response.success !== true) { $('#documents-transfer-message').addClass('error').text(response && response.message ? response.message : 'The files could not be transferred.').show(); return; }
            closeTransferModal(); if (closeViewerAfter) closeUnifiedViewer();
            currentChecks().prop('checked', false); updateSelection(); table.ajax.reload(null, false); showMessage('success', response.message);
        }, 'json').fail(function () { $('#documents-transfer-message').addClass('error').text('The server could not transfer the selected files.').show(); });
    }
    $transferSelected.on('click', function () {
        var files = selectedFileRows();
        if (!files.length) return;
        documentsSwal({
            title: 'Transfer selected files?',
            text: 'You will choose an unpublished destination folder next.',
            icon: 'question',
            confirmText: 'Continue'
        }).done(function (result) {
            if (result.confirmed) openTransferModal(files, false);
        });
    });
    $(document).on('click', '[data-transfer-close]', closeTransferModal);
    $(document).on('click', '.transfer-destination', function () { transferTarget = { level: Number($(this).attr('data-target-level')), id: Number($(this).attr('data-target-id')) }; $('.transfer-destination').removeClass('is-selected'); $(this).addClass('is-selected'); $('#documents-transfer-submit').prop('disabled', false); });
    $('#documents-transfer-search').on('input', function () { var term = $.trim(String(this.value || '')).toLowerCase(), shown = 0; $('.transfer-destination').each(function () { var visible = !term || String($(this).attr('data-search-text')).indexOf(term) !== -1; $(this).toggle(visible); if (visible) shown += 1; }); $('#documents-transfer-empty').prop('hidden', shown > 0); });
    $('#documents-transfer-submit').on('click', function () { if (transferTarget) transferToTarget(transferFiles, transferTarget.level, transferTarget.id, transferCloseViewer); });

    /* Google Drive-style marquee selection for document rows.
     * Hold the left mouse button in the table workspace and drag across file rows.
     * Every file row touched by the selection rectangle becomes part of the same
     * selection and can then be dragged as one group to an unpublished folder. */
    var marqueeSelecting = false;
    var marqueeMoved = false;
    var marqueeSuppressClick = false;
    var marqueeStartX = 0;
    var marqueeStartY = 0;
    var marqueeAdditive = false;
    var $marquee = $('#documents-marquee-selection');

    function marqueePoint(event) {
        var original = event.originalEvent || event;
        return {
            x: Number(original.pageX || 0),
            y: Number(original.pageY || 0)
        };
    }

    function marqueeRect(current) {
        return {
            left: Math.min(marqueeStartX, current.x),
            top: Math.min(marqueeStartY, current.y),
            right: Math.max(marqueeStartX, current.x),
            bottom: Math.max(marqueeStartY, current.y)
        };
    }

    function rowIntersectsMarquee(row, rect) {
        var box = row.getBoundingClientRect();
        var left = box.left + window.pageXOffset;
        var top = box.top + window.pageYOffset;
        var right = left + box.width;
        var bottom = top + box.height;
        return !(right < rect.left || left > rect.right || bottom < rect.top || top > rect.bottom);
    }

    $tableElement.on('mousedown.documentsMarquee', 'tbody', function (event) {
        if (event.which !== 1) return;
        if ($(event.target).closest('input, button, a, select, label, .doc-actions-menu').length) return;

        var $row = $(event.target).closest('tr');
        if ($row.length && $row.attr('data-item-type') !== 'file') return;

        /* A second drag on any already-selected file must remain a native
         * HTML5 drag so the entire highlighted group can be dropped into a folder. */
        if ($row.length && $row.find('.document-check').prop('checked')) return;

        var point = marqueePoint(event);
        marqueeSelecting = true;
        marqueeMoved = false;
        marqueeStartX = point.x;
        marqueeStartY = point.y;
        marqueeAdditive = event.ctrlKey || event.metaKey;

        if (!marqueeAdditive) currentChecks().prop('checked', false);

        $marquee.css({
            left: point.x - $('.documents-table-wrap').offset().left,
            top: point.y - $('.documents-table-wrap').offset().top,
            width: 0,
            height: 0
        }).addClass('is-active');

        event.preventDefault();
    });

    $(document).on('mousemove.documentsMarquee', function (event) {
        if (!marqueeSelecting) return;
        var point = marqueePoint(event);
        var rect = marqueeRect(point);
        if (Math.abs(point.x - marqueeStartX) > 4 || Math.abs(point.y - marqueeStartY) > 4) marqueeMoved = true;

        var wrapOffset = $('.documents-table-wrap').offset();
        $marquee.css({
            left: rect.left - wrapOffset.left,
            top: rect.top - wrapOffset.top,
            width: rect.right - rect.left,
            height: rect.bottom - rect.top
        });

        $tableElement.find('tbody tr[data-item-type="file"]').each(function () {
            var $check = $(this).find('.document-check');
            var intersects = rowIntersectsMarquee(this, rect);
            if (marqueeAdditive) {
                if (intersects) $check.prop('checked', true);
            } else {
                $check.prop('checked', intersects);
            }
        });
        updateSelection();
        event.preventDefault();
    }).on('mouseup.documentsMarquee', function (event) {
        if (!marqueeSelecting) return;
        marqueeSelecting = false;
        $marquee.removeClass('is-active').css({ width: 0, height: 0 });
        updateSelection();
        if (marqueeMoved) {
            marqueeSuppressClick = true;
            event.preventDefault();
            event.stopPropagation();
        }
    });

    $tableElement.on('dragstart', 'tbody tr[data-item-type="file"]', function (event) {
        var $row = $(this);
        var $check = $row.find('.document-check');

        /* Dragging an unselected file makes it the only highlighted file. */
        if (!$check.prop('checked')) {
            currentChecks().prop('checked', false);
            $check.prop('checked', true);
            lastHighlightedFileRow = this;
            updateSelection();
        }

        var files = selectedFileRows();
        currentChecks().filter(':checked[data-item-type="file"]').each(function () {
            $(this).closest('tr').addClass('is-dragging-files');
        });

        if (event.originalEvent && event.originalEvent.dataTransfer) {
            event.originalEvent.dataTransfer.effectAllowed = 'move';
            event.originalEvent.dataTransfer.setData('text/rms-files', 'selected');
            event.originalEvent.dataTransfer.setData('text/plain', files.length + ' document' + (files.length === 1 ? '' : 's'));

            var $ghost = $('<div class="documents-drag-ghost" id="documents-drag-ghost"></div>')
                .append('<i class="bi bi-files"></i>')
                .append($('<span></span>').text(files.length + ' document' + (files.length === 1 ? '' : 's')))
                .appendTo('body');
            if (event.originalEvent.dataTransfer.setDragImage && $ghost[0]) {
                event.originalEvent.dataTransfer.setDragImage($ghost[0], 24, 18);
            }
        }
    })
        .on('dragend', 'tbody tr', function () {
            $('#documents-drag-ghost').remove();
            $tableElement.find('tr').removeClass('is-dragging-files is-transfer-target');
        })
        .on('dragover', 'tbody tr[data-item-type="folder"]', function (event) {
            var row = table.row(this).data();
            if (!row || Number(row.publish_status) !== 0 || Number(row.owned_by_current_user) !== 1) return;
            event.preventDefault();
            if (event.originalEvent && event.originalEvent.dataTransfer) {
                event.originalEvent.dataTransfer.dropEffect = 'move';
            }
            $tableElement.find('tbody tr[data-item-type="folder"]').removeClass('is-transfer-target');
            $(this).addClass('is-transfer-target');
        })
        .on('dragleave', 'tbody tr[data-item-type="folder"]', function () {
            $(this).removeClass('is-transfer-target');
        })
        .on('drop', 'tbody tr[data-item-type="folder"]', function (event) {
            event.preventDefault();
            var $target = $(this);
            $target.removeClass('is-transfer-target');
            var row = table.row(this).data();
            var files = selectedFileRows();
            if (!row || !files.length) return;

            transferToTarget(
                files,
                row.record_level !== undefined ? row.record_level : Number(config.level || 0),
                row.record_id,
                false
            );
        });
    $downloadSelected.on('click', function () {
        var files = selectedFileRows();
        if (files.length === 1) window.location.href = files[0].download_url;
    });
    $deleteFiles.on('click', function () {
        var files = selectedFileRows();
        var folders = selectedFolderRows();
        if (!files.length && !folders.length) return;

        var blockers = $.map(folders, function (folder) {
            return Number(folder.publish_status) === 1
                ? displayedHierarchyPath(folder)
                : null;
        });
        if (blockers.length) {
            documentsSwal({
                title: 'Published folders cannot be deleted',
                text: 'Unpublish these folders first, or remove them from the selection:',
                lines: blockers,
                icon: 'warning',
                showCancel: false,
                confirmText: 'Review selection'
            });
            return;
        }

        documentsSwal({
            title: 'Delete selected items?',
            text: (files.length + folders.length) +
                ' selected item(s) and their physical contents will be permanently deleted.',
            icon: 'warning',
            confirmText: 'Delete items',
            confirmDanger: true
        }).done(function (result) {
            if (!result.confirmed) return;

            function deleteFoldersAt(index) {
                if (index >= folders.length) {
                    currentChecks().prop('checked', false);
                    updateSelection();
                    table.ajax.reload(null, false);
                    showMessage('success', 'The selected items were deleted successfully.');
                    return;
                }
                var request = { level: config.level, record_id: folders[index].record_id };
                request[config.csrfName] = config.csrfHash;
                $.post(config.deleteRecordUrl, request, function (response) {
                    updateCsrf(response);
                    if (!response || response.success !== true) {
                        documentsAlert('Delete stopped', response && response.message ? response.message : 'A folder could not be deleted.', 'error');
                        table.ajax.reload(null, false);
                        return;
                    }
                    deleteFoldersAt(index + 1);
                }, 'json').fail(function () {
                    documentsAlert('Delete stopped', 'The server could not delete a selected folder.', 'error');
                    table.ajax.reload(null, false);
                });
            }

            if (!files.length) {
                deleteFoldersAt(0);
                return;
            }

            var fileRequest = {
                level: Math.max(0, Number(config.level || 0) - 1),
                record_id: Number(config.parentId || 0),
                data_ids: $.map(files, function (file) { return file.data_id; })
            };
            fileRequest[config.csrfName] = config.csrfHash;
            $.post(config.deleteUploadedUrl, fileRequest, function (response) {
                updateCsrf(response);
                if (!response || response.success !== true) {
                    documentsAlert('Delete failed', response && response.message ? response.message : 'The selected files could not be deleted.', 'error');
                    return;
                }
                deleteFoldersAt(0);
            }, 'json').fail(function () {
                documentsAlert('Delete failed', 'The server could not delete the selected files.', 'error');
            });
        });
    });

    function openModal(id) {
        var $modal = $('#' + id);
        $modal.addClass('show');
        $('html, body').addClass('modal-open rms-modal-locked');

        /* Autofocus every RMS modal. Prefer an explicit [autofocus] target,
         * otherwise focus the first usable form control or action button. */
        window.setTimeout(function () {
            var $focusTarget = $modal.find('[autofocus]:visible:enabled').first();

            if (!$focusTarget.length) {
                $focusTarget = $modal.find(
                    'input:visible:enabled:not([type="hidden"]), ' +
                    'textarea:visible:enabled, select:visible:enabled, ' +
                    'button:visible:enabled, a[href]:visible'
                ).filter(function () {
                    return $(this).attr('tabindex') !== '-1';
                }).first();
            }

            if ($focusTarget.length) {
                $focusTarget.trigger('focus');
                if ($focusTarget.is('input[type="text"], input[type="search"], textarea')) {
                    $focusTarget.trigger('select');
                }
            }
        }, 0);
    }

    function closeModal($modal) {
        $modal.removeClass('show');

        if (!$('.rms-modal.show').length) {
            $('html, body').removeClass('modal-open rms-modal-locked');
        }
    }

    function uploadInput(role) {
        return role === 'watermark'
            ? $('#modal-watermark-files')
            : $('#modal-original-files');
    }

    function uploadFiles(role) {
        if ($.isArray(uploadDroppedFiles[role])) {
            return uploadDroppedFiles[role];
        }

        var input = uploadInput(role).get(0);

        if (!input || !input.files) {
            return [];
        }

        return Array.prototype.slice.call(input.files);
    }

    /**
     * Build a stable identity for one selected file. Exact duplicates are
     * ignored within the same upload side while different files with similar
     * names remain available.
     */
    function uploadFileIdentity(file) {
        return [
            String(file && file.name || '').toLowerCase(),
            Number(file && file.size || 0),
            String(file && file.type || '').toLowerCase(),
            Number(file && file.lastModified || 0)
        ].join('|');
    }

    /**
     * Append every follow-up Browse or Drop selection to the files already
     * chosen for this role. For example, nine originals plus one later
     * original become ten instead of restarting the original count at one.
     */
    function appendUploadFiles(role, newFiles) {
        var merged = uploadFiles(role).slice(0);
        var known = {};

        $.each(merged, function (index, file) {
            known[uploadFileIdentity(file)] = true;
        });

        $.each(
            Array.prototype.slice.call(newFiles || []),
            function (index, file) {
                var identity = uploadFileIdentity(file);

                if (!known[identity]) {
                    known[identity] = true;
                    merged.push(file);
                }
            }
        );

        uploadDroppedFiles[role] = merged;

        /*
         * Clear only the native input value. The File objects remain in the
         * stored array, and selecting the same browser file again still fires
         * a change event without replacing the existing multi-file list.
         */
        uploadInput(role).val('');
        uploadFileSummary(role);
        clearUploadMessage();
        updateUploadState();
    }

    function uploadFileSummary(role) {
        var files = uploadFiles(role);
        var $summary = role === 'watermark'
            ? $('#modal-watermark-summary')
            : $('#modal-original-summary');

        if (!files.length) {
            $summary.text('No files selected');
            return;
        }

        var names = [];
        var visibleCount = Math.min(files.length, 3);
        var index;

        for (index = 0; index < visibleCount; index++) {
            names.push(files[index].name);
        }

        var text = files.length === 1
            ? '1 file: ' + names.join(', ')
            : files.length + ' files: ' + names.join(', ');

        if (files.length > visibleCount) {
            text += ' and ' +
                (files.length - visibleCount) +
                ' more';
        }

        $summary.text(text);
    }

    function setUploadFilenameError(role, message) {
        var errorSelector = role === 'watermark'
            ? '#rms-watermark-files-error'
            : '#rms-original-files-error';
        var inputId = role === 'watermark'
            ? 'modal-watermark-files'
            : 'modal-original-files';
        var $error = $(errorSelector);
        var $zone = $('.documents-drop-zone[data-input-id="' + inputId + '"]');

        $zone.toggleClass('has-file-error', Boolean(message));
        $error.text(message || '').prop('hidden', !message);
    }

    function showUploadMessage(type, message) {
        if (!$uploadMessage.length) {
            return;
        }

        $uploadMessage
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .show();
    }

    function clearUploadMessage() {
        $uploadMessage
            .removeClass('success error')
            .text('')
            .hide();
    }

    function removeUploadProgressToast() {
        if (uploadToastState.element && uploadToastState.element.parentNode) {
            uploadToastState.element.parentNode.removeChild(uploadToastState.element);
        }
        uploadToastState.element = null;
        uploadToastState.total = 0;
        uploadToastState.collapsed = false;
    }

    function showUploadProgressToast(totalItems) {
        removeUploadProgressToast();

        var container = document.createElement('section');
        var header = document.createElement('div');
        var title = document.createElement('strong');
        var actions = document.createElement('span');
        var collapse = document.createElement('button');
        var close = document.createElement('button');
        var body = document.createElement('div');
        var status = document.createElement('div');
        var uploadIcon = document.createElement('span');
        var statusText = document.createElement('span');
        var spinner = document.createElement('span');
        var progressTrack = document.createElement('span');
        var progressBar = document.createElement('span');

        uploadToastState.total = Math.max(1, Number(totalItems) || 1);

        container.className = 'rms-upload-toast is-visible';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');

        header.className = 'rms-upload-toast-header';
        title.className = 'rms-upload-toast-title';
        title.textContent = 'Uploading ' + uploadToastState.total +
            (uploadToastState.total === 1 ? ' item' : ' items');

        actions.className = 'rms-upload-toast-actions';
        collapse.type = 'button';
        collapse.className = 'rms-upload-toast-collapse';
        collapse.setAttribute('aria-label', 'Collapse upload progress');
        collapse.textContent = '⌄';

        close.type = 'button';
        close.className = 'rms-upload-toast-close';
        close.setAttribute('aria-label', 'Hide upload progress');
        close.textContent = '×';

        body.className = 'rms-upload-toast-body';
        status.className = 'rms-upload-toast-status';
        uploadIcon.className = 'rms-upload-toast-upload-icon';
        uploadIcon.textContent = '⇧';
        statusText.className = 'rms-upload-toast-status-text';
        statusText.textContent = 'Uploading items 0 of ' + uploadToastState.total;
        spinner.className = 'rms-upload-toast-spinner';
        spinner.setAttribute('aria-hidden', 'true');

        progressTrack.className = 'rms-upload-toast-track';
        progressBar.className = 'rms-upload-toast-bar';
        progressTrack.appendChild(progressBar);

        status.appendChild(uploadIcon);
        status.appendChild(statusText);
        status.appendChild(spinner);
        body.appendChild(status);
        body.appendChild(progressTrack);
        actions.appendChild(collapse);
        actions.appendChild(close);
        header.appendChild(title);
        header.appendChild(actions);
        container.appendChild(header);
        container.appendChild(body);
        document.body.appendChild(container);

        collapse.addEventListener('click', function () {
            uploadToastState.collapsed = !uploadToastState.collapsed;
            container.classList.toggle('is-collapsed', uploadToastState.collapsed);
            collapse.textContent = uploadToastState.collapsed ? '⌃' : '⌄';
            collapse.setAttribute(
                'aria-label',
                uploadToastState.collapsed
                    ? 'Expand upload progress'
                    : 'Collapse upload progress'
            );
        });

        close.addEventListener('click', function () {
            removeUploadProgressToast();
        });

        uploadToastState.element = container;
    }

    function updateUploadProgressToast(percent) {
        var toast = uploadToastState.element;
        if (!toast) {
            return;
        }

        var total = uploadToastState.total;
        var current = Math.min(total, Math.floor(total * percent / 100));
        if (percent > 0 && current === 0) {
            current = 1;
        }

        var text = toast.querySelector('.rms-upload-toast-status-text');
        var bar = toast.querySelector('.rms-upload-toast-bar');
        if (text) {
            text.textContent = percent >= 100
                ? 'Finishing upload...'
                : 'Uploading items ' + current + ' of ' + total;
        }
        if (bar) {
            bar.style.width = percent + '%';
        }
    }

    function setUploadProgress(percent) {
        percent = Math.max(
            0,
            Math.min(100, Number(percent) || 0)
        );

        updateUploadProgressToast(percent);

        if (percent <= 0) {
            $uploadProgress.prop('hidden', true);
            $uploadProgressBar.css('width', '0%');
            $uploadProgressText.text('');
            return;
        }

        $uploadProgress.prop('hidden', false);
        $uploadProgressBar.css('width', percent + '%');
        $uploadProgressText.text(
            percent < 100
                ? 'Uploading: ' + percent + '%'
                : 'Saving document records...'
        );
    }

    function updateUploadState() {
        var hasPath = $uploadPath.length &&
            $uploadPath.val() !== '';

        var originalCount = uploadFiles('original').length;
        var watermarkCount = uploadFiles('watermark').length;
        var countsMatch = watermarkCount === 0 ||
            watermarkCount === originalCount;
        var uploading = $uploadForm.data('uploading') === true;
        var originalNameError = validateUploadFiles(
            uploadFiles('original'),
            'original',
            true
        );
        var watermarkNameError = validateUploadFiles(
            uploadFiles('watermark'),
            'watermark',
            true
        );

        setUploadFilenameError('original', originalNameError);
        setUploadFilenameError('watermark', watermarkNameError);

        $('#modal-original-files, #modal-watermark-files').prop(
            'disabled',
            !hasPath || uploading
        );

        $('.documents-drop-zone')
            .toggleClass('is-disabled', !hasPath || uploading)
            .attr(
                'aria-disabled',
                !hasPath || uploading ? 'true' : 'false'
            );

        $uploadForm.find('[data-modal-close]').prop(
            'disabled',
            uploading
        );

        $uploadButton.prop(
            'disabled',
            uploading ||
            !hasPath ||
            originalCount === 0 ||
            !countsMatch ||
            Boolean(originalNameError) ||
            Boolean(watermarkNameError)
        );

        if (
            !uploading &&
            hasPath &&
            originalCount > 0 &&
            watermarkCount > 0 &&
            !countsMatch
        ) {
            showUploadMessage(
                'error',
                'The number of watermark files must match the number of original files.'
            );
        }
    }

    function clearUploadFiles() {
        uploadDroppedFiles.original = null;
        uploadDroppedFiles.watermark = null;

        $('#modal-original-files, #modal-watermark-files').val('');

        uploadFileSummary('original');
        uploadFileSummary('watermark');
        setUploadFilenameError('original', '');
        setUploadFilenameError('watermark', '');
    }

    function selectCurrentUploadPath() {
        if (!$uploadPath.length) {
            return;
        }

        var currentLevel = Number(config.level) - 1;
        var currentId = String(config.parentId || '');
        var $match = $();

        /*
         * When Manage Documents is opened inside a folder, target that exact
         * folder automatically if it is in this user's unpublished Path list.
         * At Subfolder4 this means level=5 and parent_id=Subfolder4 ID.
         */
        if (currentLevel >= 0 && currentId !== '') {
            $uploadPath.find('option[data-level]').each(function () {
                if (
                    String($(this).val()) === currentId &&
                    Number($(this).attr('data-level')) === currentLevel
                ) {
                    $match = $(this);
                    return false;
                }
            });
        }

        $uploadPath.find('option').prop('selected', false);

        if ($match.length) {
            $match.prop('selected', true);
        } else {
            $uploadPath.find('option:first').prop('selected', true);
        }

        $uploadPath.trigger('change');
    }

    function prepareUploadModal() {
        clearUploadMessage();
        clearUploadFiles();
        setUploadProgress(0);
        $uploadForm.data('uploading', false);
        $uploadButton.text('Upload Documents');
        selectCurrentUploadPath();
        updateUploadState();
    }

    function validateUploadFiles(files, label, namesOnly) {
        var allowed = {
            jpg: true,
            jpeg: true,
            png: true,
            doc: true,
            docx: true,
            pdf: true,
            xlsx: true
        };

        var maximumSize = 15 * 1024 * 1024;
        var index;

        if (files.length > 250) {
            return 'A maximum of 250 ' + label +
                ' files may be uploaded at one time.';
        }

        for (index = 0; index < files.length; index++) {
            var name = String(files[index].name || '');
            var nameError = windowsNameError(name);
            var parts = name.toLowerCase().split('.');
            var extension = parts.length > 1
                ? parts.pop()
                : '';

            if (nameError) {
                return name + ': ' + nameError;
            }

            if (namesOnly === true) {
                continue;
            }

            if (!allowed[extension]) {
                return 'This file type is not allowed: ' + name;
            }

            if (
                Number(files[index].size) <= 0 ||
                Number(files[index].size) > maximumSize
            ) {
                return 'Each file must be no larger than 15 MB: ' + name;
            }
        }

        return '';
    }

    function updateUploadCsrf(response) {
        if (
            !response ||
            !response.csrfName ||
            !response.csrfHash
        ) {
            return;
        }

        var oldName = config.csrfName;

        config.csrfName = response.csrfName;
        config.csrfHash = response.csrfHash;

        $uploadForm.find('input').filter(function () {
            return this.name === oldName ||
                this.name === config.csrfName;
        }).attr('name', config.csrfName).val(config.csrfHash);
    }

    function selectCurrentSubfolderPath() {
        var $parent = $('#subfolder-parent');
        var currentLevel =
            Number(config.level) - 1;

        var currentId =
            String(config.parentId || '');

        var $match = $();

        /*
         * The current folder is selected automatically only when it is
         * present in the server-generated Path list.
         *
         * Published folders and folders unpublished by another user are
         * not included in that list and therefore cannot be selected.
         */
        if (
            currentLevel >= 0 &&
            currentId !== ''
        ) {
            $parent.find(
                'option[data-level]'
            ).each(function () {
                if (
                    String($(this).val()) === currentId &&
                    Number(
                        $(this).attr('data-level')
                    ) === currentLevel
                ) {
                    $match = $(this);
                    return false;
                }
            });
        }

        $parent.find('option').prop(
            'selected',
            false
        );

        if ($match.length) {
            $match.prop('selected', true);
        } else {
            $parent
                .find('option:first')
                .prop('selected', true);
        }

        $parent.trigger('change');
    }

    $('[data-modal-open]').on(
        'click',
        function () {
            var modalId =
                $(this).data('modal-open');

            if (
                modalId === 'subfolder-modal'
            ) {
                selectCurrentSubfolderPath();
            }

            if (
                modalId === 'documents-upload-modal'
            ) {
                prepareUploadModal();
            }

            openModal(modalId);
        }
    );

    $('a[href="#documents-upload-modal"]').on(
        'click',
        function (event) {
            event.preventDefault();

            prepareUploadModal();

            openModal(
                'documents-upload-modal'
            );
        }
    );

    /*
     * Folder-level drag/drop does not depend on table selection. The existing
     * upload modal and server validation still enforce ownership and leaf-only
     * destinations; dropped files merely prefill the Original Files list.
     */
    var documentDragDepth = 0;
    function isSelectedFileTransferDrag(event) {
        var transfer = event.originalEvent &&
            event.originalEvent.dataTransfer;

        return transfer &&
            Array.prototype.indexOf.call(
                transfer.types || [],
                'text/rms-files'
            ) !== -1;
    }
    function droppedFileList(event) {
        var transfer = event.originalEvent && event.originalEvent.dataTransfer;
        return transfer && transfer.files
            ? Array.prototype.slice.call(transfer.files)
            : [];
    }
    $(document).on('dragenter.documentsUpload dragover.documentsUpload', function (event) {
        /* Dragging/panning a document preview must stay inside the viewer and
         * must never be interpreted as a file upload drag by the page below. */
        if ($('#unified-file-viewer').hasClass('show')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return false;
        }
        /* Internal row transfers must not open the upload-file overlay. */
        if (isSelectedFileTransferDrag(event)) return;
        if (!Number(config.currentCanUpload)) return;
        event.preventDefault();
        if (event.type === 'dragenter') documentDragDepth += 1;
        if (!$('#unified-file-viewer').hasClass('show')) {
            $('#documents-drop-overlay').addClass('show');
        }
    }).on('dragleave.documentsUpload', function (event) {
        if (!Number(config.currentCanUpload)) return;
        event.preventDefault();
        documentDragDepth = Math.max(0, documentDragDepth - 1);
        if (documentDragDepth === 0) $('#documents-drop-overlay').removeClass('show');
    }).on('drop.documentsUpload', function (event) {
        if ($('#unified-file-viewer').hasClass('show')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return false;
        }
        if (!Number(config.currentCanUpload)) return;
        event.preventDefault();
        documentDragDepth = 0;
        $('#documents-drop-overlay').removeClass('show');
        var files = droppedFileList(event);
        if (!files.length) return;
        var error = validateUploadFiles(files, 'original');
        if (error) { documentsAlert('Upload warning', error, 'warning'); return; }
        closeUnifiedViewer();
        prepareUploadModal();
        uploadDroppedFiles.original = files;
        uploadFileSummary('original');
        updateUploadState();
        openModal('documents-upload-modal');
    });

    $('[data-modal-close]').on(
        'click',
        function () {
            closeModal(
                $(this).closest('.rms-modal')
            );
        }
    );

    $(document).on(
        'keydown',
        function (event) {
            if (
                event.keyCode === 27 &&
                $uploadForm.data('uploading') !== true
            ) {
                closeModal(
                    $('.rms-modal.show')
                );
            }
        }
    );

    $('#filename-sub').on(
        'change',
        function () {
            var subId = $(this).val();
            var $department =
                $('#filename-dept');

            $department
                .val('')
                .prop(
                    'disabled',
                    !subId
                );

            $department.find(
                'option[data-sub-id]'
            ).each(function () {
                $(this).prop(
                    'hidden',
                    String(
                        $(this).data('sub-id')
                    ) !== String(subId)
                );
            });
        }
    ).trigger('change');

    $('#document-edit-sub').on('change', function () {
        var subId = $(this).val();
        var $department = $('#document-edit-dept');
        var selectedDepartment = $department.val();

        $department.find('option[data-sub-id]').each(function () {
            $(this).prop('hidden', String($(this).data('sub-id')) !== String(subId));
        });

        if ($department.find('option[value="' + selectedDepartment + '"]:not([hidden])').length === 0) {
            $department.val('');
        }

        $department.prop('disabled', !subId);
    });

    $('#subfolder-parent').on(
        'change',
        function () {
            var level = $(this)
                .find('option:selected')
                .attr('data-level');

            $('#subfolder-parent-level').val(
                level === undefined
                    ? ''
                    : level
            );
        }
    ).trigger('change');

    $('#modal-upload-path').on(
        'change',
        function () {
            var $option = $(this)
                .find('option:selected');

            var selected =
                $(this).val() !== '';

            $('#modal-record-level').val(
                selected
                    ? $option.attr('data-level')
                    : ''
            );

            $('#modal-file-id').val(
                selected
                    ? $option.attr('data-file-id')
                    : ''
            );

            $('#modal-sub-id').val(
                selected
                    ? $option.attr('data-sub-id')
                    : ''
            );

            $('#modal-dept-id').val(
                selected
                    ? $option.attr('data-dept-id')
                    : ''
            );

            $('#modal-selected-destination')
                .toggleClass(
                    'visible',
                    selected
                )
                .text(
                    selected
                        ? $option.text()
                        : ''
                );

            if (!selected) {
                clearUploadFiles();
            }

            updateUploadState();
        }
    );

    $('.documents-drop-zone').on(
        'click',
        function (event) {
            var $zone = $(this);

            if (
                $zone.hasClass('is-disabled') ||
                $(event.target).is('input[type="file"]')
            ) {
                return;
            }

            $('#' + $zone.data('input-id')).trigger('click');
        }
    );

    $('.documents-drop-zone').on(
        'keydown',
        function (event) {
            if (event.keyCode !== 13 && event.keyCode !== 32) {
                return;
            }

            event.preventDefault();
            $(this).trigger('click');
        }
    );

    $('.documents-drop-zone').on(
        'dragenter dragover',
        function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (!$(this).hasClass('is-disabled')) {
                $(this).addClass('is-dragover');
            }
        }
    );

    $('.documents-drop-zone').on(
        'dragleave dragend',
        function (event) {
            event.preventDefault();
            event.stopPropagation();
            $(this).removeClass('is-dragover');
        }
    );

    $('.documents-drop-zone').on(
        'drop',
        function (event) {
            event.preventDefault();
            event.stopPropagation();

            var $zone = $(this);
            $zone.removeClass('is-dragover');

            if ($zone.hasClass('is-disabled')) {
                return;
            }

            var role = String($zone.data('file-role'));
            var transfer = event.originalEvent &&
                event.originalEvent.dataTransfer;

            if (
                (role !== 'original' && role !== 'watermark') ||
                !transfer ||
                !transfer.files ||
                !transfer.files.length
            ) {
                return;
            }

            appendUploadFiles(role, transfer.files);
        }
    );

    $('#modal-original-files').on('change', function () {
        appendUploadFiles('original', this.files);
    });

    $('#modal-watermark-files').on('change', function () {
        appendUploadFiles('watermark', this.files);
    });

    $uploadForm.on('submit', function (event) {
        event.preventDefault();

        if ($uploadForm.data('uploading') === true) {
            return;
        }

        var originalFiles = uploadFiles('original');
        var watermarkFiles = uploadFiles('watermark');
        var validation;

        if (!$uploadPath.length || $uploadPath.val() === '') {
            showUploadMessage(
                'error',
                'Please select an unpublished destination path.'
            );
            return;
        }

        if (!originalFiles.length) {
            showUploadMessage(
                'error',
                'Please select at least one original file.'
            );
            return;
        }

        if (
            watermarkFiles.length > 0 &&
            watermarkFiles.length !== originalFiles.length
        ) {
            showUploadMessage(
                'error',
                'The number of watermark files must match the number of original files.'
            );
            return;
        }

        validation = validateUploadFiles(
            originalFiles,
            'original'
        );

        if (validation !== '') {
            showUploadMessage('error', validation);
            return;
        }

        validation = validateUploadFiles(
            watermarkFiles,
            'watermark'
        );

        if (validation !== '') {
            showUploadMessage('error', validation);
            return;
        }

        clearUploadMessage();
        showUploadProgressToast(originalFiles.length + watermarkFiles.length);
        setUploadProgress(1);
        $uploadForm.data('uploading', true);
        $uploadButton.text('Uploading...');
        updateUploadState();

        /*
         * PHP commonly limits one request to 20 uploaded file parts. A set of
         * 16 originals plus 16 watermarks is therefore sent as paired batches
         * instead of one 32-file request. Eight pairs leave safe headroom while
         * keeping every original aligned with its matching watermark.
         */
        var filesPerBatch = watermarkFiles.length ? 8 : 16;
        var batchCount = Math.ceil(
            originalFiles.length / filesPerBatch
        );
        var uploadStopped = false;

        function finishUploadState() {
            $uploadForm.data('uploading', false);
            $uploadButton.text('Upload Documents');
            updateUploadState();
        }

        function failUpload(message) {
            uploadStopped = true;
            showUploadMessage(
                'error',
                message || 'The documents could not be uploaded.'
            );
            setUploadProgress(0);
            removeUploadProgressToast();
            finishUploadState();
        }

        function completeUpload() {
            /*
 * WATERMARK-FIRST DISPLAY:
 * Tell the user which preview source will be shown.
 */
            var message;

            if (watermarkFiles.length > 0) {
                message = originalFiles.length === 1
                    ? 'The document was uploaded successfully. The watermarked preview is now displayed.'
                    : originalFiles.length +
                    ' documents were uploaded successfully. Their watermarked previews are now displayed.';
            } else {
                message = originalFiles.length === 1
                    ? 'The document was uploaded successfully. Preview is unavailable because no watermark was uploaded.'
                    : originalFiles.length +
                    ' documents were uploaded successfully. Preview is unavailable because no watermarks were uploaded.';
            }
            setUploadProgress(100);
            window.setTimeout(removeUploadProgressToast, 900);
            showUploadMessage('success', message);
            showMessage('success', message);
            finishUploadState();

            if (table) {
                table.ajax.reload(null, false);
            }

            window.setTimeout(function () {
                closeModal($('#documents-upload-modal'));
                clearUploadFiles();
                setUploadProgress(0);
                clearUploadMessage();
                selectCurrentUploadPath();
            }, 850);
        }

        function buildUploadBatch(start, end) {
            var formData = new FormData();

            $uploadForm.find('input, select, textarea').each(function () {
                var type = String(this.type || '').toLowerCase();

                if (
                    !this.name ||
                    type === 'file' ||
                    this.disabled ||
                    (
                        (type === 'checkbox' || type === 'radio') &&
                        !this.checked
                    ) ||
                    this.name === config.csrfName
                ) {
                    return;
                }

                formData.append(this.name, $(this).val());
            });

            if (config.csrfName) {
                formData.append(config.csrfName, config.csrfHash);
            }

            /* Continue legacy page numbering across sequential batches. */
            formData.append('page_offset', start);

            $.each(
                originalFiles.slice(start, end),
                function (index, file) {
                    formData.append(
                        'original_files[]',
                        file,
                        file.name
                    );
                }
            );

            if (watermarkFiles.length) {
                $.each(
                    watermarkFiles.slice(start, end),
                    function (index, file) {
                        formData.append(
                            'watermark_files[]',
                            file,
                            file.name
                        );
                    }
                );
            }

            return formData;
        }

        function uploadBatch(batchIndex) {
            if (uploadStopped) {
                return;
            }

            if (batchIndex >= batchCount) {
                completeUpload();
                return;
            }

            var start = batchIndex * filesPerBatch;
            var end = Math.min(
                start + filesPerBatch,
                originalFiles.length
            );

            $.ajax({
                url: config.uploadUrl || $uploadForm.attr('action'),
                type: 'POST',
                dataType: 'json',
                data: buildUploadBatch(start, end),
                processData: false,
                contentType: false,
                xhr: function () {
                    var xhr = $.ajaxSettings.xhr();

                    if (xhr.upload) {
                        xhr.upload.addEventListener(
                            'progress',
                            function (progressEvent) {
                                if (!progressEvent.lengthComputable) {
                                    return;
                                }

                                var batchProgress =
                                    progressEvent.loaded /
                                    progressEvent.total;

                                setUploadProgress(Math.round(
                                    (
                                        batchIndex +
                                        batchProgress
                                    ) /
                                    batchCount * 100
                                ));
                            },
                            false
                        );
                    }

                    return xhr;
                }
            }).done(function (response) {
                updateUploadCsrf(response);

                if (!response || !response.success) {
                    failUpload(
                        response && response.message
                            ? response.message
                            : 'The documents could not be uploaded.'
                    );
                    return;
                }

                uploadBatch(batchIndex + 1);
            }).fail(function (xhr) {
                var message;

                if (
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {
                    message = xhr.responseJSON.message;
                } else if (xhr.status === 413) {
                    message =
                        'The selected upload is larger than the server allows.';
                } else {
                    message =
                        'The server could not upload the documents.';
                }

                failUpload(message);
            });
        }

        uploadBatch(0);
    });

    function ajaxModalForm(
        $form,
        $formMessage,
        useDialog
    ) {
        $form.on(
            'submit',
            function (event) {
                event.preventDefault();

                var $button =
                    $form.find(
                        '[type="submit"]'
                    );

                var request =
                    $form.serializeArray();

                request.push({
                    name: config.csrfName,
                    value: config.csrfHash
                });

                $button.prop(
                    'disabled',
                    true
                );

                $formMessage
                    .removeClass(
                        'success error'
                    )
                    .hide();

                $.ajax({
                    url: $form.attr('action'),
                    type: 'POST',
                    dataType: 'json',
                    data: request
                }).done(function (response) {
                    if (
                        response.csrfName &&
                        response.csrfHash
                    ) {
                        config.csrfName =
                            response.csrfName;

                        config.csrfHash =
                            response.csrfHash;
                    }

                    if (!response.success) {
                        if (useDialog) {
                            documentsAlert(
                                'Subfolder could not be created',
                                response.message || 'The record could not be saved.',
                                'error'
                            );
                            $button.prop('disabled', false);
                            return;
                        }
                        $formMessage
                            .addClass('error')
                            .text(
                                response.message ||
                                'The record could not be saved.'
                            )
                            .show();

                        $button.prop(
                            'disabled',
                            false
                        );

                        return;
                    }

                    if (useDialog) {
                        documentsAlert(
                            'Subfolder created',
                            response.message || 'The subfolder was created successfully.',
                            'success'
                        ).done(function () {
                            saveManageTableState();
                            window.location.reload();
                        });
                        return;
                    }

                    $formMessage
                        .addClass('success')
                        .text(response.message)
                        .show();

                    window.setTimeout(
                        function () {
                            saveManageTableState();
                            window.location.reload();
                        },
                        650
                    );
                }).fail(function (xhr) {
                    var text =
                        xhr.responseJSON &&
                            xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'The server could not process the request.';

                    if (useDialog) {
                        documentsAlert(
                            'Subfolder could not be created',
                            text,
                            'error'
                        );
                        $button.prop('disabled', false);
                        return;
                    }

                    $formMessage
                        .addClass('error')
                        .text(text)
                        .show();

                    $button.prop(
                        'disabled',
                        false
                    );
                });
            }
        );
    }

    ajaxModalForm(
        $('#filename-form'),
        $('#filename-message')
    );

    ajaxModalForm(
        $('#subfolder-form'),
        $('#subfolder-message'),
        true
    );

    ajaxModalForm(
        $('#document-edit-form'),
        $('#document-edit-message')
    );

    initializeDataTable();
    updateSelection();

    /*
     * The Dashboard Quick Action lands on Manage Documents with open_upload=1.
     * Reuse the normal modal setup so ownership, unpublished-path filtering,
     * file validation and upload batching remain exactly the same.
     */
    if (Number(config.openUpload) === 1 && $uploadForm.length) {
        prepareUploadModal();
        openModal('documents-upload-modal');
    }
})(jQuery);

/* Uploaded-document DataTable and image viewer. */
(function ($) {
    'use strict';

    $(function () {
        var config = window.VIEW_DOCUMENTS_CONFIG || {};
        var $table = $('#view-documents-table');

        if (!$table.length) {
            return;
        }

        var $selectAll = $('#select-all-documents');
        var $selectionLabel = $('#view-documents-selection');
        var $viewButton = $('#view-selected-document');
        var $downloadButton = $('#download-selected-document');
        var $deleteButton = $('#delete-selected-document');
        var $transferButton = $('#transfer-selected-document');
        var $renameButton = $('#rename-selected-document');
        var $viewer = $('#uploaded-file-viewer');
        var $stage = $('#uploaded-file-stage');
        var $image = $('#uploaded-image');
        var $frame = $('#uploaded-file-frame');
        var $zoomLabel = $('#uploaded-file-zoom');
        var $zoomControls = $('.image-zoom-control');
        var files = [];
        var fileIndex = 0;
        var imageScale = 1;
        var imageFitScale = 1;
        var imageOffsetX = 0;
        var imageOffsetY = 0;
        var dragging = false;
        var dragStartX = 0;
        var dragStartY = 0;
        var viewerScrollY = 0;
        var previousBodyTop = '';
        var viewTablePositioned = false;

        function viewTableStateKey() {
            return 'rms_view_documents_table_' +
                Number(config.level || 0) + '_' +
                Number(config.recordId || 0);
        }

        function loadViewTableState() {
            try {
                var value = window.localStorage.getItem(viewTableStateKey());
                return value ? JSON.parse(value) : null;
            } catch (error) {
                return null;
            }
        }

        function saveViewTableState(data) {
            try {
                window.localStorage.setItem(
                    viewTableStateKey(),
                    JSON.stringify(data)
                );
            } catch (error) {
                /* The viewer still works when browser storage is disabled. */
            }
        }

        function positionViewTable() {
            if (viewTablePositioned) {
                return;
            }
            viewTablePositioned = true;
            window.setTimeout(function () {
                var tableArea = $table.closest('.dataTables_wrapper').get(0);
                if (!tableArea) {
                    return;
                }
                var top = tableArea.getBoundingClientRect().top +
                    window.pageYOffset - 16;
                window.scrollTo({ top: Math.max(0, top), behavior: 'auto' });
            }, 0);
        }

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        function selectedIds() {
            return $table.find('.document-row-check:checked').map(function () {
                return String($(this).val());
            }).get();
        }

        function selectedFiles(dataTable) {
            var ids = selectedIds();
            return dataTable.rows().data().toArray().filter(function (row) {
                return ids.indexOf(String(row.data_id)) !== -1;
            });
        }

        function updateSelection() {
            var count = selectedIds().length;
            $selectionLabel.text(count === 0
                ? 'No records selected'
                : count + (count === 1 ? ' record selected' : ' records selected'));
            $viewButton.prop('disabled', count === 0);
            $downloadButton.prop('disabled', count !== 1);
            $deleteButton.prop('disabled', count === 0);
            $transferButton.prop('disabled', count === 0);
            $renameButton.prop('disabled', count !== 1);
        }

        function closeActionModal($modal) {
            $modal.removeClass('show');
            if (!$('.rms-modal.show').length) $('body').removeClass('modal-open');
        }

        /* All mutating viewer actions share refreshed CodeIgniter CSRF data. */
        function postViewerAction(url, request, failureMessage, done) {
            request[config.csrfName] = config.csrfHash;
            $.ajax({ url: url, type: 'POST', dataType: 'json', data: request })
                .done(function (response) {
                    if (response && response.csrfName && response.csrfHash) {
                        config.csrfName = response.csrfName;
                        config.csrfHash = response.csrfHash;
                    }
                    if (!response || response.success !== true) {
                        documentsAlert('Action could not be completed', response && response.message ? response.message : failureMessage, 'error');
                        return;
                    }
                    done(response);
                }).fail(function (xhr) {
                    documentsAlert('Action could not be completed', xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message : failureMessage, 'error');
                }).always(updateSelection);
        }

        var dataTable = $table.DataTable({
            processing: true,
            serverSide: true,
            pagingType: 'simple',
            lengthMenu: [10, 25, 50, 100],
            pageLength: 50,
            stateSave: true,
            stateDuration: -1,
            stateSaveCallback: function (settings, data) {
                saveViewTableState(data);
            },
            stateLoadCallback: function () {
                return loadViewTableState();
            },
            ajax: {
                url: config.ajaxUrl,
                type: 'GET',
                data: function (request) {
                    request.datatable = 1;
                    request.level = Number(config.level || 0);
                    request.record_id = Number(config.recordId || 0);
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'check-column',
                    render: function (data, type, row) {
                        return type !== 'display' ? row.data_id :
                            '<input type="checkbox" class="document-row-check" value="' +
                            escapeHtml(row.data_id) + '">';
                    }
                },
                { data: 'data_id' },
                {
                    data: 'data_name',

                    /*
                     * WATERMARK-FIRST DISPLAY:
                     * Preserve the original filename and identify the preview source.
                     */
                    render: function (data, type, row) {
                        if (type !== 'display') {
                            return data;
                        }

                        var hasWatermark =
                            Number(row.has_watermark) === 1;

                        var previewLabel = row.preview_label || (
                            hasWatermark
                                ? 'Watermarked preview'
                                : 'Watermarked preview unavailable'
                        );

                        return (
                            '<div class="uploaded-document-name">' +
                            '<span class="uploaded-document-icon">' +
                            '<i class="bi bi-file-earmark-text" aria-hidden="true"></i>' +

                            (hasWatermark
                                ? '<i class="bi bi-shield-check ' +
                                'documents-watermark-indicator-inline" ' +
                                'title="Watermark displayed" ' +
                                'aria-label="Watermark displayed"></i>'
                                : '') +
                            '</span>' +

                            '<span>' +
                            '<strong>' +
                            escapeHtml(data) +
                            '</strong>' +

                            '<small class="' +
                            (hasWatermark
                                ? 'documents-watermark-label'
                                : 'documents-original-preview-label') +
                            '">' +
                            escapeHtml(previewLabel) +
                            '</small>' +
                            '</span>' +
                            '</div>'
                        );
                    }
                },
                { data: 'page_no' },
                { data: 'date_uploaded', render: $.fn.dataTable.render.text() },
                {
                    data: 'status',
                    orderable: false,
                    render: function (data, type) {
                        if (type !== 'display') {
                            return data;
                        }
                        return Number(data) === 0
                            ? '<span class="status-badge published">&#10003;</span>'
                            : '<span class="status-badge unpublished">&#10005;</span>';
                    }
                }
            ],
            language: {
                emptyTable: 'No files have been uploaded to this folder yet.',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'No entries to show',
                lengthMenu: 'Show _MENU_ entries',
                search: 'Search:',
                paginate: {
                    previous: '‹',      // or '<' or 'Prev' or '←'
                    next: '›'           // or '>' or 'Next' or '→'
                }
            },
            drawCallback: function () {
                $selectAll.prop({ checked: false, indeterminate: false });
                updateSelection();
                positionViewTable();
            }
        });

        function isImage(name) {
            return /\.(?:jpe?g|png|gif|bmp|webp|svg)$/i.test(String(name));
        }

        function renderImage() {
            imageScale = Math.max(0.1, Math.min(5, imageScale));
            $image.css('transform',
                'translate(-50%, -50%) translate(' + imageOffsetX + 'px,' +
                imageOffsetY + 'px) scale(' + imageScale + ')');
            $zoomLabel.text(Math.round(imageScale * 100) + '%');
        }

        function fitImage() {
            var image = $image.get(0);
            var stage = $stage.get(0);
            if (!image || !stage || !image.naturalWidth || !image.naturalHeight) {
                return;
            }
            var style = window.getComputedStyle(stage);
            var width = stage.clientWidth - parseFloat(style.paddingLeft || 0) -
                parseFloat(style.paddingRight || 0);
            var height = stage.clientHeight - parseFloat(style.paddingTop || 0) -
                parseFloat(style.paddingBottom || 0);
            imageFitScale = Math.min(
                Math.max(width, 1) / image.naturalWidth,
                Math.max(height, 1) / image.naturalHeight,
                1
            );
            imageScale = imageFitScale;
            imageOffsetX = 0;
            imageOffsetY = 0;
            renderImage();
        }

        function changeZoom(amount) {
            imageScale += amount;
            renderImage();
        }

        function displayFile(index) {
            if (!files.length) {
                return;
            }
            fileIndex = (index + files.length) % files.length;
            var file = files[fileIndex];
            var imageFile = isImage(file.data_name);
            /* VIEWER FIX: switching files must cancel and release any active drag. */
            stopImageDrag();
            imageOffsetX = 0;
            imageOffsetY = 0;
            $('#uploaded-file-title, #uploaded-file-name').text(file.data_name);
            $('#uploaded-file-position').text(
                'File ' + (fileIndex + 1) + ' of ' + files.length
            );
            $('#previous-uploaded-file').prop('disabled', files.length < 2);
            $('#next-uploaded-file').prop('disabled', files.length < 2);
            $zoomControls.prop('disabled', !imageFile);

            if (imageFile) {
                $frame.prop('hidden', true).attr('src', 'about:blank');
                $image.prop('hidden', false).one('load', function () {
                    window.requestAnimationFrame(fitImage);
                }).attr('src', file.view_url);
            } else {
                $image.prop('hidden', true).attr('src', '');
                $frame.prop('hidden', false).attr('src', file.view_url);
                $zoomLabel.text('100%');
            }
        }

        /* VIEWER FIX: lock both document roots and preserve the background position. */
        function lockViewerBackground() {
            viewerScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
            previousBodyTop = document.body.style.top;
            document.body.style.top = '-' + viewerScrollY + 'px';
            $('html, body').addClass('modal-open rms-viewer-locked');
        }

        /* VIEWER FIX: restore the exact page position after the viewer closes. */
        function unlockViewerBackground() {
            $('html, body').removeClass('modal-open rms-viewer-locked');
            document.body.style.top = previousBodyTop;
            window.scrollTo(0, viewerScrollY);
        }

        /* VIEWER FIX: safely end right-button dragging and clear the drag state. */
        function stopImageDrag() {
            dragging = false;
            $image.removeClass('is-dragging');
        }

        function openViewer(selected) {
            files = selected;
            if (!files.length) {
                return;
            }
            $viewer.addClass('show');
            lockViewerBackground();
            displayFile(0);
        }

        function closeViewer() {
            stopImageDrag();
            $viewer.removeClass('show');
            $image.prop('hidden', true).attr('src', '').removeClass('is-dragging');
            $frame.attr('src', 'about:blank');
            if (!$('.rms-modal.show').length) {
                unlockViewerBackground();
            }
        }

        $selectAll.on('change', function () {
            $table.find('.document-row-check').prop('checked', this.checked);
            updateSelection();
        });
        $(document).on('change', '.document-row-check', updateSelection);
        $table.on('dblclick', 'tbody tr', function (event) {
            if ($(event.target).is('input, button, a')) {
                return;
            }
            var row = dataTable.row(this).data();
            if (row) {
                openViewer([row]);
            }
        });
        $viewButton.on('click', function () {
            openViewer(selectedFiles(dataTable));
        });
        $downloadButton.on('click', function () {
            var selected = selectedFiles(dataTable);
            if (selected.length === 1) {
                window.location.href = selected[0].download_url;
            }
        });
        $deleteButton.on('click', function () {
            var ids = selectedIds();
            if (!ids.length) return;
            documentsSwal({
                title: 'Delete selected files?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                confirmText: 'Delete files',
                confirmDanger: true
            }).done(function (result) {
                if (!result.confirmed) return;

                var request = {
                    level: Number(config.level || 0),
                    record_id: Number(config.recordId || 0),
                    data_ids: ids
                };
                request[config.csrfName] = config.csrfHash;
                $deleteButton.prop('disabled', true);

                $.ajax({
                    url: config.deleteUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: request
                }).done(function (response) {
                    if (response && response.csrfName && response.csrfHash) {
                        config.csrfName = response.csrfName;
                        config.csrfHash = response.csrfHash;
                    }
                    if (!response || response.success !== true) {
                        documentsAlert('Delete failed', response && response.message
                            ? response.message
                            : 'The selected files could not be deleted.', 'error');
                        return;
                    }
                    $selectAll.prop('checked', false);
                    dataTable.ajax.reload(null, false);
                }).fail(function (xhr) {
                    documentsAlert('Delete failed',
                        xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'The selected files could not be deleted.', 'error');
                }).always(updateSelection);
            });
        });
        $renameButton.on('click', function () {
            var selected = selectedFiles(dataTable);
            if (selected.length !== 1) return;
            $('#renamed-uploaded-file').val(selected[0].data_name);
            $('#rename-uploaded-file-modal').addClass('show');
            $('body').addClass('modal-open');
        });
        $('#save-renamed-uploaded-file').on('click', function () {
            var selected = selectedIds();
            var $modal = $('#rename-uploaded-file-modal');
            var nameField = document.getElementById('renamed-uploaded-file');
            if (selected.length !== 1) return;
            if (
                nameField &&
                window.RMSWindowsName &&
                !window.RMSWindowsName.validateField(nameField)
            ) {
                nameField.focus();
                return;
            }
            postViewerAction(config.renameUrl, {
                level: Number(config.level || 0), record_id: Number(config.recordId || 0),
                data_id: selected[0], name: $('#renamed-uploaded-file').val()
            }, 'The file could not be renamed.', function () {
                closeActionModal($modal);
                dataTable.ajax.reload(null, false);
            });
        });
        $transferButton.on('click', function () {
            if (!selectedIds().length) return;
            documentsSwal({ title: 'Transfer selected files?', text: 'You will choose an unpublished destination folder next.', icon: 'question', confirmText: 'Continue' }).done(function (result) {
                if (!result.confirmed) return;
                $('#transfer-uploaded-file-modal').addClass('show');
                $('body').addClass('modal-open');
            });
        });
        $('#save-transferred-uploaded-files').on('click', function () {
            var destination = String($('#transfer-uploaded-file-path').val() || '').split(':');
            var $modal = $('#transfer-uploaded-file-modal');
            if (destination.length !== 2) {
                documentsAlert('Destination required', 'Please select a destination leaf folder.', 'warning');
                return;
            }
            postViewerAction(config.transferUrl, {
                level: Number(config.level || 0), record_id: Number(config.recordId || 0),
                data_ids: selectedIds(), target_level: Number(destination[0]),
                target_id: Number(destination[1])
            }, 'The selected files could not be transferred.', function () {
                closeActionModal($modal);
                dataTable.ajax.reload(null, false);
            });
        });
        $('.modal-cancel').on('click', function () {
            closeActionModal($(this).closest('.rms-modal'));
        });
        $('#close-uploaded-file').on('click', closeViewer);
        $viewer.on('click', function (event) {
            if (event.target === this) {
                closeViewer();
            }
        });
        $('#previous-uploaded-file').on('click', function () {
            displayFile(fileIndex - 1);
        });
        $('#next-uploaded-file').on('click', function () {
            displayFile(fileIndex + 1);
        });
        $('#zoom-in-file').on('click', function () { changeZoom(0.1); });
        $('#zoom-out-file').on('click', function () { changeZoom(-0.1); });
        $('#fit-uploaded-file').on('click', fitImage);
        $('#actual-size-file').on('click', function () {
            imageScale = 1;
            imageOffsetX = 0;
            imageOffsetY = 0;
            renderImage();
        });
        /* VIEWER FIX: Ctrl + wheel zooms only inside this viewer stage. */
        $stage.on('wheel.uploadedViewer', function (event) {
            if ($image.prop('hidden') || !event.originalEvent.ctrlKey) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            changeZoom(event.originalEvent.deltaY < 0 ? 0.1 : -0.1);
        });

        /* VIEWER FIX: hold the left mouse button and drag.
           Uses mousedown on the image plus document-level mousemove/mouseup (rather than
           Pointer Events + setPointerCapture) so dragging keeps working even if the pointer
           moves outside the image bounds mid-drag. */
        $image.on('mousedown.uploadedViewer', function (event) {
            var originalEvent = event.originalEvent || event;
            if (originalEvent.button !== 0) {
                return;
            }
            dragging = true;
            dragStartX = originalEvent.clientX - imageOffsetX;
            dragStartY = originalEvent.clientY - imageOffsetY;
            $image.addClass('is-dragging');
            event.preventDefault();
            event.stopPropagation();
        });

        $(document).on('mousemove.uploadedViewer', function (event) {
            if (!dragging) {
                return;
            }
            var originalEvent = event.originalEvent || event;
            imageOffsetX = originalEvent.clientX - dragStartX;
            imageOffsetY = originalEvent.clientY - dragStartY;
            renderImage();
            event.preventDefault();
        }).on('mouseup.uploadedViewer', function (event) {
            if (!dragging) {
                return;
            }
            event.preventDefault();
            stopImageDrag();
        });

        /* VIEWER FIX: also stop dragging if the window loses focus mid-drag (e.g. alt-tab). */
        $(window).on('blur.uploadedViewer', function () {
            if (dragging) {
                stopImageDrag();
            }
        });

        $(document).on('keydown.uploadedImage', function (event) {
            if (!$viewer.hasClass('show') || $image.prop('hidden') || !event.ctrlKey) {
                return;
            }
            if (event.key === '+' || event.key === '=') {
                event.preventDefault();
                changeZoom(0.1);
            } else if (event.key === '-') {
                event.preventDefault();
                changeZoom(-0.1);
            } else if (event.key === '0') {
                event.preventDefault();
                fitImage();
            }
        });
        $(document).on('keydown.uploadedViewer', function (event) {
            if (event.key === 'Escape' && $viewer.hasClass('show')) {
                closeViewer();
            }
        });
    });


})(jQuery);
