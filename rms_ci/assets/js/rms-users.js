/*
 * Manage Users table selection.
 *
 * Keeps the "select all" checkbox synchronized with selectable user rows.
 * It changes only checkbox state in the browser and sends no database request.
 */
(function () {
    'use strict';

    var selectAll = document.getElementById('select-all-users');
    var editToggle = document.getElementById('toggle-user-edit-menu');
    var editOptions = document.getElementById('user-edit-options');
    var editInfoButton = document.getElementById('open-user-edit-modal');
    var editAccessButton = document.getElementById('open-user-access-modal');
    var logoutForm = document.getElementById('user-logout-form');
    var logoutButton = document.getElementById('logout-user-action');
    var logoutUserId = document.getElementById('logout-user-id');
    var deleteForm = document.getElementById('user-delete-form');
    var deleteButton = document.getElementById('delete-user-action');
    var deleteUserId = document.getElementById('delete-user-id');
    var blockForm = document.getElementById('user-block-form');
    var blockButton = document.getElementById('toggle-user-block');
    var blockActionLabel = document.getElementById('block-action-label');
    var blockUserId = document.getElementById('block-user-id');
    var viewerForm = document.getElementById('user-viewer-form');
    var viewerMenu = document.getElementById('user-viewer-menu');
    var viewerToggle = document.getElementById('toggle-user-viewer-menu');
    var viewerOptions = document.getElementById('user-viewer-options');
    var viewerUserId = document.getElementById('viewer-user-id');
    var viewerLevel = document.getElementById('viewer-level');
    var uploaderForm = document.getElementById('user-uploader-form');
    var uploaderMenu = document.getElementById('user-uploader-menu');
    var uploaderToggle = document.getElementById('toggle-user-uploader-menu');
    var uploaderOptions = document.getElementById('user-uploader-options');
    var uploaderUserId = document.getElementById('uploader-user-id');
    var uploaderPermission = document.getElementById('uploader-permission');
    var exportButton = document.getElementById('export-user-access');
    var selectors = document.querySelectorAll
        ? document.querySelectorAll('.user-selector')
        : [];
    var tableBody = document.getElementById('users-table-body');

    /** Escapes a user name before it is placed inside confirmation markup. */
    function escapeActionText(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /** Enables Edit only when exactly one manageable row is selected. */
    function updateActionState() {
        var checked = 0;
        var selectedId = '';
        var selectedStatus = 0;
        var selectedRole = 0;
        var selectedUpload = 0;
        var selectedName = '';
        var selectorIndex;

        for (selectorIndex = 0; selectorIndex < selectors.length; selectorIndex++) {
            var selectedRow = selectors[selectorIndex].closest
                ? selectors[selectorIndex].closest('.user-row')
                : selectors[selectorIndex].parentNode.parentNode;

            if (selectors[selectorIndex].checked) {
                checked++;
                selectedId = selectors[selectorIndex].value;
                selectedStatus = parseInt(
                    selectors[selectorIndex].getAttribute('data-user-status'),
                    10
                ) || 0;
                selectedRole = parseInt(
                    selectors[selectorIndex].getAttribute('data-user-role'),
                    10
                ) || 0;
                selectedUpload = parseInt(
                    selectors[selectorIndex].getAttribute('data-user-upload'),
                    10
                ) === 1 ? 1 : 0;
                selectedName = selectors[selectorIndex].getAttribute('data-user-name') || 'this user';
            }

            // Gives the selected account a clear visual state in the table.
            if (selectedRow) {
                if (selectors[selectorIndex].checked) {
                    selectedRow.classList.add('is-selected');
                } else {
                    selectedRow.classList.remove('is-selected');
                }
            }
        }

        if (selectAll) {
            selectAll.checked = selectors.length > 0 && checked === selectors.length;
            selectAll.indeterminate = checked > 0 && checked < selectors.length;
        }

        if (editToggle) {
            editToggle.disabled = checked !== 1;
            editToggle.setAttribute('data-selected-user', checked === 1 ? selectedId : '');

            // Highlight Edit only when one valid account is ready to edit.
            if (checked === 1) {
                editToggle.classList.add('is-ready');
            } else {
                editToggle.classList.remove('is-ready');
                editToggle.classList.remove('is-active');
            }

            // Close the choices when selection becomes invalid.
            if (checked !== 1 && editOptions) {
                editOptions.hidden = true;
                editToggle.setAttribute('aria-expanded', 'false');
            }
        }

        if (editInfoButton) {
            editInfoButton.setAttribute('data-selected-user', checked === 1 ? selectedId : '');
        }

        if (editAccessButton) {
            editAccessButton.setAttribute('data-selected-user', checked === 1 ? selectedId : '');
        }

        // Forced logout is valid only for one currently online account.
        if (logoutButton && logoutUserId) {
            var canForceLogout = checked === 1 && selectedStatus === 1;
            logoutButton.disabled = !canForceLogout;
            logoutUserId.value = checked === 1 ? selectedId : '';
            logoutButton.setAttribute('data-user-name', checked === 1 ? selectedName : '');

            if (canForceLogout) {
                logoutButton.classList.add('is-ready');
                logoutButton.setAttribute('title', 'Log out the selected online user');
            } else {
                logoutButton.classList.remove('is-ready');
                logoutButton.setAttribute(
                    'title',
                    checked === 1
                        ? 'Only an online user can be logged out'
                        : 'Select one online user'
                );
            }
        }

        // Delete is available only when exactly one manageable row is selected.
        if (deleteButton && deleteUserId) {
            deleteButton.disabled = checked !== 1;
            deleteUserId.value = checked === 1 ? selectedId : '';
            deleteButton.setAttribute('data-user-name', checked === 1 ? selectedName : '');

            if (checked === 1) {
                deleteButton.classList.add('is-ready');
                deleteButton.setAttribute('title', 'Delete the selected user');
            } else {
                deleteButton.classList.remove('is-ready');
                deleteButton.setAttribute('title', 'Select one user to delete');
            }
        }

        // Block and Unblock use the same protected, single-selection action.
        if (blockButton && blockUserId) {
            blockButton.disabled = checked !== 1;
            blockUserId.value = checked === 1 ? selectedId : '';
            blockButton.setAttribute('data-user-name', checked === 1 ? selectedName : '');
            blockButton.setAttribute('data-action', selectedStatus === 2 ? 'unblock' : 'block');
            if (blockActionLabel) {
                blockActionLabel.innerHTML = selectedStatus === 2 ? 'Unblock' : 'Block';
            }

            if (checked === 1) {
                blockButton.classList.add('is-ready');
                if (selectedStatus === 2) {
                    blockButton.classList.add('is-unblock');
                } else {
                    blockButton.classList.remove('is-unblock');
                }
            } else {
                blockButton.classList.remove('is-ready');
                blockButton.classList.remove('is-unblock');
            }
        }

        // Viewer is available for one manageable account and keeps role 3/4 labels.
        if (viewerToggle && viewerUserId) {
            viewerToggle.disabled = checked !== 1;
            viewerUserId.value = checked === 1 ? selectedId : '';
            viewerToggle.setAttribute('data-user-name', checked === 1 ? selectedName : '');
            viewerToggle.setAttribute('data-user-role', checked === 1 ? selectedRole : '');

            if (checked === 1) {
                viewerToggle.classList.add('is-ready');
            } else {
                viewerToggle.classList.remove('is-ready');
                viewerToggle.classList.remove('is-active');
                viewerToggle.setAttribute('aria-expanded', 'false');
                if (viewerOptions) {
                    viewerOptions.hidden = true;
                }
            }

            if (viewerOptions && viewerOptions.querySelectorAll) {
                var viewerChoices = viewerOptions.querySelectorAll('[data-viewer-level]');
                for (var choiceIndex = 0; choiceIndex < viewerChoices.length; choiceIndex++) {
                    var choiceLevel = parseInt(
                        viewerChoices[choiceIndex].getAttribute('data-viewer-level'),
                        10
                    );
                    var choiceRole = choiceLevel === 1 ? 4 : 3;
                    if (checked === 1 && selectedRole === choiceRole) {
                        viewerChoices[choiceIndex].classList.add('is-current');
                    } else {
                        viewerChoices[choiceIndex].classList.remove('is-current');
                    }
                }
            }
        }

        // Uploader uses the selected account's current allowed_upload value.
        if (uploaderToggle && uploaderUserId) {
            uploaderToggle.disabled = checked !== 1;
            uploaderUserId.value = checked === 1 ? selectedId : '';
            uploaderToggle.setAttribute('data-user-name', checked === 1 ? selectedName : '');
            uploaderToggle.setAttribute('data-user-upload', checked === 1 ? selectedUpload : '');

            if (checked === 1) {
                uploaderToggle.classList.add('is-ready');
            } else {
                uploaderToggle.classList.remove('is-ready');
                uploaderToggle.classList.remove('is-active');
                uploaderToggle.setAttribute('aria-expanded', 'false');
                if (uploaderOptions) {
                    uploaderOptions.hidden = true;
                }
            }

            if (uploaderOptions && uploaderOptions.querySelectorAll) {
                var uploaderChoices = uploaderOptions.querySelectorAll('[data-uploader-value]');
                for (var uploaderChoiceIndex = 0; uploaderChoiceIndex < uploaderChoices.length; uploaderChoiceIndex++) {
                    var uploaderValue = parseInt(
                        uploaderChoices[uploaderChoiceIndex].getAttribute('data-uploader-value'),
                        10
                    );
                    if (checked === 1 && selectedUpload === uploaderValue) {
                        uploaderChoices[uploaderChoiceIndex].classList.add('is-current');
                    } else {
                        uploaderChoices[uploaderChoiceIndex].classList.remove('is-current');
                    }
                }
            }
        }

        // Export follows the old rule: exactly one manageable user is required.
        if (exportButton) {
            exportButton.disabled = checked !== 1;
            exportButton.setAttribute('data-selected-user', checked === 1 ? selectedId : '');

            if (checked === 1) {
                exportButton.classList.add('is-ready');
            } else {
                exportButton.classList.remove('is-ready');
            }
        }
    }

    if (selectAll) {
        // Apply the main checkbox state to every selectable row on this page.
        selectAll.onclick = function () {
            var index;

            for (index = 0; index < selectors.length; index++) {
                selectors[index].checked = selectAll.checked;
            }

            updateActionState();
        };

    }

    // Recalculate the Edit state whenever a row checkbox changes.
    for (var index = 0; index < selectors.length; index++) {
        selectors[index].onchange = updateActionState;
    }

    /*
     * Let the administrator select a row by clicking its username. This makes
     * the small checkbox easier to use and avoids scrolling back to it after
     * reading columns on the right. Protected rows have no checkbox and are
     * intentionally ignored.
     */
    for (var usernameIndex = 0; usernameIndex < selectors.length; usernameIndex++) {
        (function (selector) {
            var row = selector.closest
                ? selector.closest('.user-row')
                : selector.parentNode.parentNode;
            var username = row && row.querySelector
                ? row.querySelector('.username-value')
                : null;

            if (!username) {
                return;
            }

            username.setAttribute('title', 'Click to select this user');
            username.onclick = function (event) {
                if (event && event.preventDefault) {
                    event.preventDefault();
                }
                selector.checked = !selector.checked;
                updateActionState();
            };
        }(selectors[usernameIndex]));
    }

    /*
     * Make the complete selection cell a click target. This removes the need
     * to aim at the small native checkbox and remains reliable while the old,
     * wide Users table is horizontally or vertically scrolled.
     */
    if (tableBody) {
        tableBody.onclick = function (event) {
            var target = event.target || event.srcElement;
            var cell = target;
            var selector;

            // Username already has its own handler above; do not toggle twice.
            if (target && target.className
                && String(target.className).indexOf('username-value') !== -1) {
                return;
            }

            while (cell && cell !== tableBody
                && String(cell.className || '').indexOf('select-column') === -1) {
                cell = cell.parentNode;
            }

            if (!cell || cell === tableBody || !cell.querySelector) {
                return;
            }

            selector = cell.querySelector('.user-selector');
            if (!selector) {
                return;
            }

            // A native checkbox click already changed its own checked state.
            if (target !== selector) {
                selector.checked = !selector.checked;
            }

            updateActionState();
        };
    }

    /*
     * Recover clicks when a transparent page layer covers one horizontal
     * screen band. The problem follows the screen position rather than a
     * specific account, so a normal checkbox handler never receives that
     * click. This helper uses the pointer coordinates to find the real table
     * row and checkbox underneath the covering layer.
     *
     * This changes only the checkbox's browser state. It does not submit a
     * form, call a controller, or write anything to the database.
     */
    function selectorAtScreenPoint(clientX, clientY) {
        var selectorIndex;

        for (selectorIndex = 0; selectorIndex < selectors.length; selectorIndex++) {
            var selector = selectors[selectorIndex];
            var row = selector.closest
                ? selector.closest('.user-row')
                : selector.parentNode.parentNode;
            var cell = selector.parentNode;
            var rowRect;
            var cellRect;

            if (!row || !cell || !row.getBoundingClientRect
                || !cell.getBoundingClientRect) {
                continue;
            }

            rowRect = row.getBoundingClientRect();
            cellRect = cell.getBoundingClientRect();

            if (clientY >= rowRect.top && clientY <= rowRect.bottom
                && clientX >= cellRect.left && clientX <= cellRect.right) {
                return selector;
            }
        }

        return null;
    }

    if (tableBody && document.addEventListener) {
        var lastPointerTarget = null;
        var lastPointerCursor = '';

        /*
         * Show the hand cursor in the true checkbox area even when the event
         * is landing on the unwanted transparent layer above the table.
         */
        document.addEventListener('mousemove', function (event) {
            var selector = selectorAtScreenPoint(event.clientX, event.clientY);
            var target = event.target || event.srcElement;

            if (lastPointerTarget && lastPointerTarget.style) {
                lastPointerTarget.style.cursor = lastPointerCursor;
                lastPointerTarget = null;
                lastPointerCursor = '';
            }

            if (selector && target && target.style) {
                lastPointerTarget = target;
                lastPointerCursor = target.style.cursor;
                target.style.cursor = 'pointer';
            }
        }, true);

        /*
         * Capture runs before the covering element handles the click. Native
         * checkbox/cell clicks are left to the existing handlers so a working
         * row is never toggled twice.
         */
        document.addEventListener('click', function (event) {
            var selector = selectorAtScreenPoint(event.clientX, event.clientY);
            var target = event.target || event.srcElement;
            var realCell;

            if (!selector) {
                return;
            }

            realCell = selector.parentNode;

            if (target === selector
                || (realCell && realCell.contains && realCell.contains(target))) {
                return;
            }

            if (event.preventDefault) {
                event.preventDefault();
            }
            if (event.stopPropagation) {
                event.stopPropagation();
            }

            selector.checked = !selector.checked;
            updateActionState();
        }, true);
    }

    // Open or close the two old Edit choices for one selected user.
    if (editToggle && editOptions) {
        editToggle.onclick = function () {
            editOptions.hidden = !editOptions.hidden;
            editToggle.setAttribute('aria-expanded', editOptions.hidden ? 'false' : 'true');

            // The stronger state clearly identifies the currently open action.
            if (editOptions.hidden) {
                editToggle.classList.remove('is-active');
            } else {
                editToggle.classList.add('is-active');
            }
        };
    }

    // Edit Info continues to use the already-tested protected controller route.
    if (editInfoButton) {
        editInfoButton.onclick = function () {
            var userId = editInfoButton.getAttribute('data-selected-user');
            var editBase = editInfoButton.getAttribute('data-edit-base');

            if (!userId || !editBase) {
                return;
            }

            window.location.href = editBase.replace(/\/$/, '') + '/' + userId;
        };
    }

    // Start the read-only CSV download for the one selected account.
    if (exportButton) {
        exportButton.onclick = function () {
            var userId = exportButton.getAttribute('data-selected-user');
            var exportBase = exportButton.getAttribute('data-export-base');

            if (exportButton.disabled || !userId || !exportBase) {
                return;
            }

            window.location.href = exportBase.replace(/\/$/, '') + '/' + userId;
        };
    }

    // Confirm before changing the selected online account to forced logout (3).
    if (logoutForm && logoutButton && logoutUserId) {
        var logoutModal = document.getElementById('user-logout-modal');
        var logoutDialog = logoutModal && logoutModal.querySelector
            ? logoutModal.querySelector('.status-confirm-dialog')
            : null;
        var logoutMessage = document.getElementById('logout-confirm-message');
        var logoutConfirm = document.getElementById('confirm-user-logout');
        var logoutCloseButtons = logoutModal && logoutModal.querySelectorAll
            ? logoutModal.querySelectorAll('[data-close-logout-modal]')
            : [];

        function closeLogoutModal() {
            if (!logoutModal) {
                return;
            }

            logoutModal.className = 'user-modal status-confirm-modal logout-confirm-modal';
            logoutModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('user-modal-open');
            logoutButton.focus();
        }

        logoutForm.onsubmit = function (event) {
            var userName = logoutButton.getAttribute('data-user-name') || 'this user';

            if (logoutButton.disabled || logoutUserId.value === '') {
                return false;
            }

            if (event && event.preventDefault) {
                event.preventDefault();
            }

            if (!logoutModal || !logoutDialog || !logoutConfirm) {
                return window.confirm('Log out ' + userName + '?');
            }

            logoutMessage.innerHTML = 'Log out <strong>' + escapeActionText(userName)
                + '</strong>? Their session will end on the next request.';
            logoutModal.className =
                'user-modal status-confirm-modal logout-confirm-modal is-open';
            logoutModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('user-modal-open');
            logoutDialog.focus();
            return false;
        };

        for (var logoutIndex = 0; logoutIndex < logoutCloseButtons.length; logoutIndex++) {
            logoutCloseButtons[logoutIndex].onclick = closeLogoutModal;
        }

        if (logoutConfirm) {
            logoutConfirm.onclick = function () {
                logoutConfirm.disabled = true;
                logoutConfirm.innerHTML = 'Logging out...';
                logoutForm.submit();
            };
        }

        document.addEventListener('keydown', function (event) {
            if ((event.key === 'Escape' || event.keyCode === 27)
                && logoutModal
                && logoutModal.className.indexOf('is-open') !== -1) {
                closeLogoutModal();
            }
        });
    }

    // Confirm before permanently deleting one manageable account.
    if (deleteForm && deleteButton && deleteUserId) {
        var deleteModal = document.getElementById('user-delete-modal');
        var deleteDialog = deleteModal && deleteModal.querySelector
            ? deleteModal.querySelector('.status-confirm-dialog')
            : null;
        var deleteMessage = document.getElementById('delete-confirm-message');
        var deleteConfirm = document.getElementById('confirm-user-delete');
        var deleteCloseButtons = deleteModal && deleteModal.querySelectorAll
            ? deleteModal.querySelectorAll('[data-close-delete-modal]')
            : [];

        function closeDeleteModal() {
            if (!deleteModal) {
                return;
            }

            deleteModal.className = 'user-modal status-confirm-modal';
            deleteModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('user-modal-open');
            deleteButton.focus();
        }

        deleteForm.onsubmit = function (event) {
            var userName = deleteButton.getAttribute('data-user-name') || 'this user';

            if (deleteButton.disabled || deleteUserId.value === '') {
                return false;
            }

            if (event && event.preventDefault) {
                event.preventDefault();
            }

            if (!deleteModal || !deleteDialog || !deleteConfirm) {
                return window.confirm('Permanently delete ' + userName + '?');
            }

            deleteMessage.innerHTML = 'Permanently delete <strong>'
                + escapeActionText(userName)
                + '</strong>? The account and its saved file access cannot be restored.';
            deleteModal.className = 'user-modal status-confirm-modal is-open';
            deleteModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('user-modal-open');
            deleteDialog.focus();
            return false;
        };

        for (var deleteIndex = 0; deleteIndex < deleteCloseButtons.length; deleteIndex++) {
            deleteCloseButtons[deleteIndex].onclick = closeDeleteModal;
        }

        if (deleteConfirm) {
            deleteConfirm.onclick = function () {
                deleteConfirm.disabled = true;
                deleteConfirm.innerHTML = 'Deleting...';
                deleteForm.submit();
            };
        }

        document.addEventListener('keydown', function (event) {
            if ((event.key === 'Escape' || event.keyCode === 27)
                && deleteModal
                && deleteModal.className.indexOf('is-open') !== -1) {
                closeDeleteModal();
            }
        });
    }

    // Open a professional confirmation before changing one status value.
    if (blockForm && blockButton) {
        var statusModal = document.getElementById('user-status-modal');
        var statusDialog = statusModal && statusModal.querySelector
            ? statusModal.querySelector('.status-confirm-dialog')
            : null;
        var statusTitle = document.getElementById('status-confirm-title');
        var statusMessage = document.getElementById('status-confirm-message');
        var statusConfirm = document.getElementById('confirm-user-status');
        var statusCloseButtons = statusModal && statusModal.querySelectorAll
            ? statusModal.querySelectorAll('[data-close-status-modal]')
            : [];

        function closeStatusModal() {
            if (!statusModal) {
                return;
            }

            statusModal.className = 'user-modal status-confirm-modal';
            statusModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('user-modal-open');
            blockButton.focus();
        }

        blockForm.onsubmit = function (event) {
            var action = blockButton.getAttribute('data-action') === 'unblock'
                ? 'Unblock'
                : 'Block';
            var userName = blockButton.getAttribute('data-user-name') || 'this user';

            if (blockButton.disabled || !blockUserId || blockUserId.value === '') {
                return false;
            }

            if (event && event.preventDefault) {
                event.preventDefault();
            }

            if (!statusModal || !statusDialog || !statusConfirm) {
                return window.confirm(action + ' ' + userName + '?');
            }

            statusTitle.innerHTML = action + ' user?';
            statusMessage.innerHTML = action + ' <strong>' + escapeActionText(userName)
                + '</strong>? Only this account status will change.';
            statusConfirm.innerHTML = action + ' user';
            statusConfirm.className = action === 'Unblock'
                ? 'primary-button status-unblock-confirm'
                : 'primary-button status-block-confirm';
            statusModal.className = 'user-modal status-confirm-modal is-open';
            statusModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('user-modal-open');
            statusDialog.focus();
            return false;
        };

        for (var statusIndex = 0; statusIndex < statusCloseButtons.length; statusIndex++) {
            statusCloseButtons[statusIndex].onclick = closeStatusModal;
        }

        if (statusConfirm) {
            statusConfirm.onclick = function () {
                statusConfirm.disabled = true;
                statusConfirm.innerHTML = 'Saving...';
                blockForm.submit();
            };
        }

        document.addEventListener('keydown', function (event) {
            if ((event.key === 'Escape' || event.keyCode === 27)
                && statusModal
                && statusModal.className.indexOf('is-open') !== -1) {
                closeStatusModal();
            }
        });
    }

    /*
     * Viewer keeps the old two-level menu while adding a clear confirmation.
     * Only the hidden user_id and viewer_level fields are submitted.
     */
    if (viewerForm && viewerToggle && viewerOptions && viewerUserId && viewerLevel) {
        var viewerModal = document.getElementById('user-viewer-modal');
        var viewerDialog = viewerModal && viewerModal.querySelector
            ? viewerModal.querySelector('.viewer-confirm-dialog')
            : null;
        var viewerTitle = document.getElementById('viewer-confirm-title');
        var viewerMessage = document.getElementById('viewer-confirm-message');
        var viewerConfirm = document.getElementById('confirm-user-viewer');
        var viewerCloseButtons = viewerModal && viewerModal.querySelectorAll
            ? viewerModal.querySelectorAll('[data-close-viewer-modal]')
            : [];
        var viewerChoiceButtons = viewerOptions.querySelectorAll
            ? viewerOptions.querySelectorAll('[data-viewer-level]')
            : [];

        function closeViewerMenu() {
            viewerOptions.hidden = true;
            viewerToggle.setAttribute('aria-expanded', 'false');
            viewerToggle.classList.remove('is-active');
        }

        function closeViewerModal() {
            if (!viewerModal) {
                return;
            }

            viewerModal.className = 'user-modal viewer-confirm-modal';
            viewerModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('user-modal-open');
            viewerToggle.focus();
        }

        viewerToggle.onclick = function () {
            if (viewerToggle.disabled) {
                return;
            }

            viewerOptions.hidden = !viewerOptions.hidden;
            viewerToggle.setAttribute('aria-expanded', viewerOptions.hidden ? 'false' : 'true');
            if (viewerOptions.hidden) {
                viewerToggle.classList.remove('is-active');
            } else {
                viewerToggle.classList.add('is-active');
            }
        };

        for (var viewerIndex = 0; viewerIndex < viewerChoiceButtons.length; viewerIndex++) {
            viewerChoiceButtons[viewerIndex].onclick = function () {
                var level = parseInt(this.getAttribute('data-viewer-level'), 10);
                var levelText = level === 1
                    ? 'Level 1 (view only)'
                    : 'Level 2 (view and download)';
                var userName = viewerToggle.getAttribute('data-user-name') || 'this user';

                if (level !== 1 && level !== 2) {
                    return;
                }

                viewerLevel.value = level;
                closeViewerMenu();

                if (!viewerModal || !viewerDialog || !viewerConfirm) {
                    if (window.confirm('Assign ' + levelText + ' to ' + userName + '?')) {
                        viewerForm.submit();
                    }
                    return;
                }

                viewerTitle.innerHTML = 'Assign Viewer Level ' + level + '?';
                viewerMessage.innerHTML = 'Set <strong>' + escapeActionText(userName)
                    + '</strong> to <strong>' + levelText + '</strong>? Only this account\'s user level will change.';
                viewerConfirm.innerHTML = 'Assign Level ' + level;
                viewerModal.className = 'user-modal viewer-confirm-modal is-open';
                viewerModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('user-modal-open');
                viewerDialog.focus();
            };
        }

        for (var viewerCloseIndex = 0; viewerCloseIndex < viewerCloseButtons.length; viewerCloseIndex++) {
            viewerCloseButtons[viewerCloseIndex].onclick = closeViewerModal;
        }

        if (viewerConfirm) {
            viewerConfirm.onclick = function () {
                if (viewerUserId.value === '' || viewerLevel.value === '') {
                    return;
                }
                viewerConfirm.disabled = true;
                viewerConfirm.innerHTML = 'Saving...';
                viewerForm.submit();
            };
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                if (viewerModal && viewerModal.className.indexOf('is-open') !== -1) {
                    closeViewerModal();
                } else if (!viewerOptions.hidden) {
                    closeViewerMenu();
                }
            }
        });
    }

    /*
     * Uploader preserves the old allowed_upload Yes/No choices and submits
     * only the selected user_id plus the validated permission value.
     */
    if (uploaderForm && uploaderToggle && uploaderOptions
        && uploaderUserId && uploaderPermission) {
        var uploaderModal = document.getElementById('user-uploader-modal');
        var uploaderDialog = uploaderModal && uploaderModal.querySelector
            ? uploaderModal.querySelector('.uploader-confirm-dialog')
            : null;
        var uploaderTitle = document.getElementById('uploader-confirm-title');
        var uploaderMessage = document.getElementById('uploader-confirm-message');
        var uploaderConfirm = document.getElementById('confirm-user-uploader');
        var uploaderCloseButtons = uploaderModal && uploaderModal.querySelectorAll
            ? uploaderModal.querySelectorAll('[data-close-uploader-modal]')
            : [];
        var uploaderChoiceButtons = uploaderOptions.querySelectorAll
            ? uploaderOptions.querySelectorAll('[data-uploader-value]')
            : [];

        function closeUploaderMenu() {
            uploaderOptions.hidden = true;
            uploaderToggle.setAttribute('aria-expanded', 'false');
            uploaderToggle.classList.remove('is-active');
        }

        function closeUploaderModal() {
            if (!uploaderModal) {
                return;
            }

            uploaderModal.className = 'user-modal uploader-confirm-modal';
            uploaderModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('user-modal-open');
            uploaderConfirm.disabled = false;
            uploaderConfirm.innerHTML = 'Save permission';
            uploaderToggle.focus();
        }

        uploaderToggle.onclick = function () {
            if (uploaderToggle.disabled) {
                return;
            }

            uploaderOptions.hidden = !uploaderOptions.hidden;
            uploaderToggle.setAttribute('aria-expanded', uploaderOptions.hidden ? 'false' : 'true');
            if (uploaderOptions.hidden) {
                uploaderToggle.classList.remove('is-active');
            } else {
                uploaderToggle.classList.add('is-active');
            }
        };

        for (var uploaderIndex = 0; uploaderIndex < uploaderChoiceButtons.length; uploaderIndex++) {
            uploaderChoiceButtons[uploaderIndex].onclick = function () {
                var permission = parseInt(this.getAttribute('data-uploader-value'), 10);
                var permissionText = permission === 1 ? 'Yes (allow uploads)' : 'No (remove upload permission)';
                var userName = uploaderToggle.getAttribute('data-user-name') || 'this user';

                if (permission !== 0 && permission !== 1) {
                    return;
                }

                uploaderPermission.value = permission;
                closeUploaderMenu();

                if (!uploaderModal || !uploaderDialog || !uploaderConfirm) {
                    if (window.confirm('Set Uploader to ' + permissionText + ' for ' + userName + '?')) {
                        uploaderForm.submit();
                    }
                    return;
                }

                uploaderTitle.innerHTML = permission === 1
                    ? 'Allow this user to upload?'
                    : 'Remove upload permission?';
                uploaderMessage.innerHTML = 'Set Uploader for <strong>'
                    + escapeActionText(userName) + '</strong> to <strong>'
                    + permissionText + '</strong>? Only this account\'s upload permission will change.';
                uploaderConfirm.innerHTML = permission === 1 ? 'Allow uploads' : 'Remove permission';
                uploaderModal.className = 'user-modal uploader-confirm-modal is-open';
                uploaderModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('user-modal-open');
                uploaderDialog.focus();
            };
        }

        for (var uploaderCloseIndex = 0; uploaderCloseIndex < uploaderCloseButtons.length; uploaderCloseIndex++) {
            uploaderCloseButtons[uploaderCloseIndex].onclick = closeUploaderModal;
        }

        uploaderConfirm.onclick = function () {
            if (uploaderUserId.value === ''
                || (uploaderPermission.value !== '0' && uploaderPermission.value !== '1')) {
                return;
            }
            uploaderConfirm.disabled = true;
            uploaderConfirm.innerHTML = 'Saving...';
            uploaderForm.submit();
        };

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                if (uploaderModal && uploaderModal.className.indexOf('is-open') !== -1) {
                    closeUploaderModal();
                } else if (!uploaderOptions.hidden) {
                    closeUploaderMenu();
                }
            }
        });
    }

    // Clicking elsewhere dismisses the Edit choices without changing selection.
    document.addEventListener('click', function (event) {
        var menu = document.getElementById('user-edit-menu');

        if (menu && editOptions && !menu.contains(event.target)) {
            editOptions.hidden = true;
            if (editToggle) {
                editToggle.setAttribute('aria-expanded', 'false');
                editToggle.classList.remove('is-active');
            }
        }

        if (viewerMenu && viewerOptions && !viewerMenu.contains(event.target)) {
            viewerOptions.hidden = true;
            if (viewerToggle) {
                viewerToggle.setAttribute('aria-expanded', 'false');
                viewerToggle.classList.remove('is-active');
            }
        }

        if (uploaderMenu && uploaderOptions && !uploaderMenu.contains(event.target)) {
            uploaderOptions.hidden = true;
            if (uploaderToggle) {
                uploaderToggle.setAttribute('aria-expanded', 'false');
                uploaderToggle.classList.remove('is-active');
            }
        }
    });

    /* Reconnect selection behavior after AJAX replaces the table rows. */
    window.rmsUsersRefreshSelection = function () {
        var selectorIndex;

        selectAll = document.getElementById('select-all-users');
        selectors = document.querySelectorAll
            ? document.querySelectorAll('.user-selector')
            : [];
        tableBody = document.getElementById('users-table-body');

        if (selectAll) {
            selectAll.onclick = function () {
                var index;
                for (index = 0; index < selectors.length; index++) {
                    selectors[index].checked = selectAll.checked;
                }
                updateActionState();
            };
        }

        for (selectorIndex = 0; selectorIndex < selectors.length; selectorIndex++) {
            (function (selector) {
                var row = selector.closest
                    ? selector.closest('.user-row')
                    : selector.parentNode.parentNode;
                var username = row && row.querySelector
                    ? row.querySelector('.username-value')
                    : null;

                selector.onchange = updateActionState;

                if (username) {
                    username.setAttribute('title', 'Click to select this user');
                    username.onclick = function (event) {
                        if (event && event.preventDefault) {
                            event.preventDefault();
                        }
                        selector.checked = !selector.checked;
                        updateActionState();
                    };
                }
            }(selectors[selectorIndex]));
        }

        if (tableBody) {
            tableBody.onclick = function (event) {
                var target = event.target || event.srcElement;
                var cell = target;
                var selector;

                if (target && target.className
                    && String(target.className).indexOf('username-value') !== -1) {
                    return;
                }

                while (cell && cell !== tableBody
                    && String(cell.className || '').indexOf('select-column') === -1) {
                    cell = cell.parentNode;
                }

                if (!cell || cell === tableBody || !cell.querySelector) {
                    return;
                }

                selector = cell.querySelector('.user-selector');
                if (!selector) {
                    return;
                }

                if (target !== selector) {
                    selector.checked = !selector.checked;
                }
                updateActionState();
            };
        }

        updateActionState();
    };

    updateActionState();
}());

/*
 * Toolbar action success modal.
 *
 * The server renders this dialog only after Logout, Delete, Block/Unblock,
 * Viewer, or Uploader completes successfully. Closing it changes only the
 * browser display; the completed database update is not repeated.
 */
(function () {
    'use strict';

    var modal = document.getElementById('user-action-success-modal');
    var dialog = modal ? modal.querySelector('.action-success-dialog') : null;
    var closeButtons = modal && modal.querySelectorAll
        ? modal.querySelectorAll('[data-close-action-success]')
        : [];
    var index;

    if (!modal || !dialog) {
        return;
    }

    /** Closes the one-time result and returns to the refreshed Users list. */
    function closeSuccessModal() {
        modal.className = 'user-modal status-confirm-modal action-success-modal';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('user-modal-open');
    }

    for (index = 0; index < closeButtons.length; index++) {
        closeButtons[index].onclick = closeSuccessModal;
    }

    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && modal.className.indexOf('is-open') !== -1) {
            closeSuccessModal();
        }
    });

    document.body.classList.add('user-modal-open');
    dialog.focus();
}());

/* AJAX server-side Users table: search, sorting, page size, and pagination. */
(function () {
    'use strict';

    var search = document.getElementById('users-filter');
    var form = search ? search.form : null;
    var startingValue = search ? search.value.replace(/^\s+|\s+$/g, '') : '';
    var searchTimer = null;
    var delay = 800;
    var minimumAutomaticLength = 3;
    var submitButton = form ? form.querySelector('button[type="submit"]') : null;
    var originalButtonText = submitButton ? submitButton.textContent : 'Search';
    var requestStarted = false;
    var activeRequest = null;

    if (!search || !form) {
        return;
    }

    /** Show a compact status while the table data is loading. */
    function showSearchingState() {
        if (requestStarted) {
            return false;
        }

        requestStarted = true;
        form.classList.add('is-searching');
        form.setAttribute('aria-busy', 'true');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-label', 'Searching all users');
            submitButton.textContent = 'Searching...';
        }

        return true;
    }

    function clearSearchingState() {
        requestStarted = false;
        form.classList.remove('is-searching');
        form.removeAttribute('aria-busy');

        if (submitButton) {
            submitButton.disabled = false;
            submitButton.removeAttribute('aria-label');
            submitButton.textContent = originalButtonText;
        }
    }

    function encodeForm(targetForm) {
        var fields = targetForm.elements;
        var parts = [];
        var index;

        for (index = 0; index < fields.length; index++) {
            if (!fields[index].name || fields[index].disabled) {
                continue;
            }
            parts.push(encodeURIComponent(fields[index].name) + '='
                + encodeURIComponent(fields[index].value));
        }
        return parts.join('&');
    }

    function syncFilterState(nextDocument) {
        var nextForm = nextDocument.querySelector('.users-filter');
        var names = ['sort', 'direction', 'per_page'];
        var index;

        if (!nextForm) {
            return;
        }

        for (index = 0; index < names.length; index++) {
            var currentField = form.querySelector('[name="' + names[index] + '"]');
            var nextField = nextForm.querySelector('[name="' + names[index] + '"]');
            if (currentField && nextField) {
                currentField.value = nextField.value;
            }
        }
    }

    /** Request the normal CI page, but paint only its table result region. */
    function loadTable(url, addHistory) {
        var region = document.getElementById('users-ajax-region');

        if (!showSearchingState()) {
            return;
        }

        if (!region || !window.XMLHttpRequest || !window.DOMParser) {
            window.location.href = url;
            return;
        }

        region.classList.add('is-loading');
        region.setAttribute('aria-busy', 'true');

        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new XMLHttpRequest();
        activeRequest.open('GET', url, true);
        activeRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        activeRequest.onreadystatechange = function () {
            var nextDocument;
            var nextRegion;
            var nextTotal;
            var currentTotal;

            if (activeRequest.readyState !== 4) {
                return;
            }

            if (activeRequest.status < 200 || activeRequest.status >= 300) {
                window.location.href = url;
                return;
            }

            nextDocument = new DOMParser().parseFromString(activeRequest.responseText, 'text/html');
            nextRegion = nextDocument.getElementById('users-ajax-region');

            if (!nextRegion) {
                window.location.href = url;
                return;
            }

            region.innerHTML = nextRegion.innerHTML;
            nextTotal = nextDocument.getElementById('users-matching-total');
            currentTotal = document.getElementById('users-matching-total');
            if (nextTotal && currentTotal) {
                currentTotal.textContent = nextTotal.textContent;
            }

            syncFilterState(nextDocument);
            startingValue = search.value.replace(/^\s+|\s+$/g, '');
            clearSearchingState();
            region.classList.remove('is-loading');
            region.removeAttribute('aria-busy');
            activeRequest = null;

            if (window.rmsUsersRefreshSelection) {
                window.rmsUsersRefreshSelection();
            }

            if (addHistory && window.history && window.history.pushState) {
                window.history.pushState({usersTable: true}, '', url);
            }

            search.focus();
            if (search.setSelectionRange) {
                search.setSelectionRange(search.value.length, search.value.length);
            }
        };
        activeRequest.send(null);
    }

    function submitWithAjax() {
        var action = form.getAttribute('action') || window.location.pathname;
        var query = encodeForm(form);

        loadTable(action + (query ? '?' + query : ''), true);
    }

    /** Submit only when the trimmed search value has actually changed. */
    function submitAutomaticSearch() {
        var currentValue = search.value.replace(/^\s+|\s+$/g, '');

        searchTimer = null;

        if (currentValue === startingValue) {
            return;
        }

        // Do not reload while a short search term is still being typed.
        // Clearing the field remains automatic; Search/Enter always work.
        if (currentValue !== '' && currentValue.length < minimumAutomaticLength) {
            return;
        }

        submitWithAjax();
    }

    /** Restart the short delay whenever the administrator continues typing. */
    search.oninput = function () {
        if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
        }

        searchTimer = window.setTimeout(submitAutomaticSearch, delay);
    };

    // Clicking Search or pressing Enter must submit only once.
    form.onsubmit = function (event) {
        if (searchTimer !== null) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
        }

        if (event && event.preventDefault) {
            event.preventDefault();
        }

        submitWithAjax();

        return false;
    };

    document.addEventListener('click', function (event) {
        var target = event.target || event.srcElement;
        var region = document.getElementById('users-ajax-region');

        while (target && target !== document && target.tagName !== 'A') {
            target = target.parentNode;
        }

        if (!target || target === document || !region || !region.contains(target)) {
            return;
        }

        if (!target.closest || (!target.closest('th') && !target.closest('.users-pagination'))) {
            return;
        }

        event.preventDefault();
        loadTable(target.href, true);
    });

    document.addEventListener('change', function (event) {
        var target = event.target || event.srcElement;
        var footerForm;
        var action;
        var query;

        if (!target || target.id !== 'per-page') {
            return;
        }

        footerForm = target.form;
        action = footerForm.getAttribute('action') || window.location.pathname;
        query = encodeForm(footerForm);
        loadTable(action + (query ? '?' + query : ''), true);
    });

    if (window.addEventListener) {
        window.addEventListener('pageshow', clearSearchingState);
        window.addEventListener('popstate', function () {
            loadTable(window.location.href, false);
        });
    }
}());

/*
 * Edit Access modal, loading animation, and hierarchy search.
 *
 * The modal opens immediately, then requests the tree only when needed. Search
 * is performed entirely in the browser, so repeated searches do not query the
 * database. A result shows its parent path and its related descendants while
 * unrelated branches stay hidden.
 */
(function () {
    'use strict';

    var modal = document.getElementById('user-access-modal');
    var dialog = modal ? modal.querySelector('.access-modal-dialog') : null;
    var content = document.getElementById('access-modal-content');
    var userName = document.getElementById('access-user-name');
    var openButton = document.getElementById('open-user-access-modal');
    var editToolbarButton = document.getElementById('toggle-user-edit-menu');
    var closeButtons = modal && modal.querySelectorAll
        ? modal.querySelectorAll('[data-close-access-modal]')
        : [];
    var confirmModal = document.getElementById('access-save-confirm-modal');
    var confirmDialog = confirmModal
        ? confirmModal.querySelector('.create-message-dialog')
        : null;
    var confirmSaveButton = document.getElementById('confirm-save-access');
    var cancelConfirmButtons = confirmModal && confirmModal.querySelectorAll
        ? confirmModal.querySelectorAll('[data-close-access-confirm]')
        : [];
    var loadingMarkup = content ? content.innerHTML : '';
    var activeRequest = null;
    var previousFocus = null;
    var pendingAccessForm = null;

    if (!modal || !dialog || !content || !openButton) {
        return;
    }

    /** Closes the dialog and cancels a still-running tree request. */
    function closeModal() {
        if (activeRequest && activeRequest.readyState !== 4) {
            activeRequest.abort();
        }

        modal.className = 'user-modal access-modal';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('user-modal-open');

        if (editToolbarButton) {
            editToolbarButton.classList.remove('is-active');
        }

        if (previousFocus && previousFocus.focus) {
            previousFocus.focus();
        }
    }

    /** Opens the page-styled confirmation above the loaded access tree. */
    function openSaveConfirmation(form) {
        pendingAccessForm = form;

        if (!confirmModal || !confirmDialog) {
            return false;
        }

        confirmModal.className = 'user-modal access-confirm-modal is-open';
        confirmModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('user-modal-open');
        confirmDialog.focus();
        return true;
    }

    /** Returns to Edit Access without changing any selected permission. */
    function closeSaveConfirmation() {
        if (!confirmModal) {
            return;
        }

        confirmModal.className = 'user-modal access-confirm-modal';
        confirmModal.setAttribute('aria-hidden', 'true');
        pendingAccessForm = null;

        if (dialog && modal.className.indexOf('is-open') !== -1) {
            dialog.focus();
        }
    }

    /** Displays a friendly message when the tree request cannot complete. */
    function showLoadError(message) {
        content.innerHTML =
            '<div class="access-load-error" role="alert">' +
                '<span aria-hidden="true">!</span>' +
                '<h3>Access list could not be loaded</h3>' +
                '<p>' + escapeHtml(message || 'Please close this window and try again.') + '</p>' +
                '<button type="button" id="retry-access-load">Try again</button>' +
            '</div>';

        var retryButton = document.getElementById('retry-access-load');
        if (retryButton) {
            retryButton.onclick = loadAccessTree;
        }
    }

    /** Escapes error text before inserting it into an HTML status message. */
    function escapeHtml(value) {
        var holder = document.createElement('div');
        holder.appendChild(document.createTextNode(String(value)));
        return holder.innerHTML;
    }

    /** Returns a direct nested UL child without selecting deeper descendants. */
    function directChildList(node) {
        var index;

        for (index = 0; index < node.children.length; index++) {
            if (node.children[index].tagName.toLowerCase() === 'ul') {
                return node.children[index];
            }
        }

        return null;
    }

    /** Normalizes visible labels and search text for dependable matching. */
    function normalizeSearchText(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .replace(/^\s+|\s+$/g, '');
    }

    /** Reads the label users can actually see instead of a helper attribute. */
    function visibleNodeName(node) {
        var label = node.querySelector
            ? node.querySelector('.access-node-name')
            : null;

        if (label) {
            return normalizeSearchText(label.textContent || label.innerText || '');
        }

        return normalizeSearchText(node.getAttribute('data-node-name') || '');
    }

    /** Returns whether this exact file/folder node is tagged to the user. */
    function isNodeChecked(node) {
        var row = node.firstElementChild;
        var checkbox = row && row.querySelector
            ? row.querySelector('input[name="access_path[]"]')
            : null;

        return checkbox ? checkbox.checked : false;
    }

    /** Marks whether every branch contains a direct or descendant name match. */
    function evaluateBranches(list, query) {
        var branchHasMatch = false;
        var index;

        for (index = 0; index < list.children.length; index++) {
            var node = list.children[index];
            var childList;
            var childHasMatch;
            var name;

            /*
             * A valueless HTML data attribute returns an empty string in the
             * browser. Checking it as a Boolean incorrectly skipped every
             * file/folder. A null check correctly recognizes the attribute.
             */
            if (node.getAttribute('data-access-node') === null) {
                continue;
            }

            // Search the real rendered label so filenames always remain findable.
            name = visibleNodeName(node);
            /*
             * With no search, show only permissions already tagged to this
             * user. A real search still checks the complete available tree.
             */
            node._accessSelfMatch = query === ''
                ? isNodeChecked(node)
                : name.indexOf(query) !== -1;
            childList = directChildList(node);
            childHasMatch = childList ? evaluateBranches(childList, query) : false;
            node._accessBranchMatch = node._accessSelfMatch || childHasMatch;
            branchHasMatch = branchHasMatch || node._accessBranchMatch;
        }

        return branchHasMatch;
    }

    /** Shows matches, their parent path, and descendants related to a match. */
    function displayBranches(list, matchedAncestor, searchActive) {
        var visibleCount = 0;
        var index;

        for (index = 0; index < list.children.length; index++) {
            var node = list.children[index];
            var childList;
            var visible;

            /* Use the same null check while deciding which branches display. */
            if (node.getAttribute('data-access-node') === null) {
                continue;
            }

            visible = searchActive
                ? matchedAncestor || node._accessBranchMatch
                : node._accessBranchMatch;

            /*
             * Use an explicit CSS class instead of relying only on the HTML
             * hidden property. This works consistently after AJAX inserts the
             * tree and after several searches in the same modal.
             */
            if (visible) {
                node.classList.remove('access-search-hidden');
                node.removeAttribute('hidden');
            } else {
                node.classList.add('access-search-hidden');
                node.setAttribute('hidden', 'hidden');
            }

            if (visible) {
                visibleCount++;
            }

            childList = directChildList(node);
            if (childList) {
                visibleCount += displayBranches(
                    childList,
                    searchActive && (matchedAncestor || node._accessSelfMatch),
                    searchActive
                );
            }
        }

        return visibleCount;
    }

    /** Applies the entered file/folder search without making a server request. */
    function filterAccessTree() {
        var search = document.getElementById('access-tree-search');
        var panel = document.getElementById('access-tree-panel');
        var root = panel ? panel.querySelector('.access-tree-level') : null;
        var summary = document.getElementById('access-search-summary');
        var noResults = document.getElementById('access-no-results');
        var clearButton = document.getElementById('clear-access-search');
        var query = search ? normalizeSearchText(search.value) : '';
        var visibleCount = 0;

        if (root) {
            evaluateBranches(root, query);
            visibleCount = displayBranches(root, false, query !== '');
        }

        if (summary) {
            summary.innerHTML = query === ''
                ? 'Showing this user\'s tagged access'
                : visibleCount + ' related result' + (visibleCount === 1 ? '' : 's') +
                    ' for <strong>' + escapeHtml(query) + '</strong>';
        }

        if (noResults) {
            noResults.hidden = visibleCount !== 0;

            if (visibleCount === 0) {
                var emptyTitle = noResults.querySelector('strong');
                var emptyHelp = noResults.querySelector('span');

                if (emptyTitle) {
                    emptyTitle.innerHTML = query === ''
                        ? 'No access is currently tagged'
                        : 'No matching file or folder';
                }
                if (emptyHelp) {
                    emptyHelp.innerHTML = query === ''
                        ? 'Use Search to find a file or folder to tag.'
                        : 'Try a shorter or more general search.';
                }
            }
        }

        // Return to the top so the first matching branch is immediately visible.
        if (panel) {
            panel.scrollTop = 0;
            panel.scrollLeft = 0;
        }

        if (clearButton) {
            clearButton.hidden = query === '';
        }
    }

    /** Recounts selected permission rows after any checkbox change. */
    function updateSelectedCount() {
        var countLabel = document.getElementById('access-selected-count');
        var checkboxes = content.querySelectorAll
            ? content.querySelectorAll('input[name="access_path[]"]')
            : [];
        var selected = 0;
        var index;

        for (index = 0; index < checkboxes.length; index++) {
            if (checkboxes[index].checked) {
                selected++;
            }
        }

        if (countLabel) {
            countLabel.innerHTML = selected + ' selected';
        }
    }

    /** Connects search, checkbox, and save behavior after HTML is loaded. */
    function initializeAccessTree() {
        var search = document.getElementById('access-tree-search');
        var searchButton = document.getElementById('run-access-search');
        var clearButton = document.getElementById('clear-access-search');
        var form = document.getElementById('user-access-form');
        var checkboxes = content.querySelectorAll
            ? content.querySelectorAll('input[name="access_path[]"]')
            : [];
        var index;

        if (searchButton) {
            searchButton.onclick = filterAccessTree;
        }

        if (search) {
            // Live filtering makes the result visible even without mouse input.
            search.oninput = filterAccessTree;
            search.onkeydown = function (event) {
                var key = event.key || event.keyCode;
                if (key === 'Enter' || key === 13) {
                    event.preventDefault();
                    filterAccessTree();
                }
            };
            search.focus();
        }

        if (clearButton) {
            clearButton.onclick = function () {
                search.value = '';
                filterAccessTree();
                search.focus();
            };
        }

        for (index = 0; index < checkboxes.length; index++) {
            checkboxes[index].onchange = updateSelectedCount;
        }

        if (form) {
            form.onsubmit = function (event) {
                if (event && event.preventDefault) {
                    event.preventDefault();
                }

                openSaveConfirmation(form);
                return false;
            };
        }

        // Initially show only this user's existing tags and their parent path.
        filterAccessTree();
    }

    /** Requests the optimized server-rendered tree for the selected user. */
    function loadAccessTree() {
        var userId = openButton.getAttribute('data-selected-user');
        var accessBase = openButton.getAttribute('data-access-base');
        var requestUrl;

        if (!userId || !accessBase) {
            showLoadError('Select exactly one user first.');
            return;
        }

        content.innerHTML = loadingMarkup;
        requestUrl = accessBase.replace(/\/$/, '') + '/' + userId;
        activeRequest = new XMLHttpRequest();
        activeRequest.open('GET', requestUrl, true);
        activeRequest.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        activeRequest.onreadystatechange = function () {
            var response;
            var responseText;
            var responseHtml = '';

            if (activeRequest.readyState !== 4) {
                return;
            }

            if (activeRequest.status < 200 || activeRequest.status >= 300) {
                showLoadError('The server returned an error while preparing access.');
                return;
            }

            responseText = activeRequest.responseText || '';

            /*
             * The corrected controller returns the rendered old access list
             * directly as HTML. JSON is still accepted so this JavaScript can
             * work during a careful one-file-at-a-time deployment.
             */
            if (responseText.indexOf('id="user-access-form"') !== -1
                || responseText.indexOf("id='user-access-form'") !== -1) {
                responseHtml = responseText;
            } else {
                try {
                    response = JSON.parse(responseText);
                } catch (error) {
                    showLoadError('The server response did not contain the file access list.');
                    return;
                }

                if (!response.success || !response.html) {
                    showLoadError(response.message || 'The access list is unavailable.');
                    return;
                }

                userName.innerHTML = escapeHtml(response.user_name || 'Selected user');
                responseHtml = response.html;
            }

            content.innerHTML = responseHtml;
            initializeAccessTree();
        };

        activeRequest.send(null);
    }

    /** Opens the shell immediately so the loading state is never a blank wait. */
    openButton.onclick = function () {
        previousFocus = document.activeElement;
        modal.className = 'user-modal access-modal is-open';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('user-modal-open');
        if (editToolbarButton) {
            editToolbarButton.classList.add('is-active');
        }
        dialog.focus();
        loadAccessTree();
    };

    for (var index = 0; index < closeButtons.length; index++) {
        closeButtons[index].onclick = closeModal;
    }

    for (var confirmIndex = 0; confirmIndex < cancelConfirmButtons.length; confirmIndex++) {
        cancelConfirmButtons[confirmIndex].onclick = closeSaveConfirmation;
    }

    /** Submits through the unchanged CI3 route only after modal confirmation. */
    if (confirmSaveButton) {
        confirmSaveButton.onclick = function () {
            var formToSubmit = pendingAccessForm;

            if (!formToSubmit) {
                closeSaveConfirmation();
                return;
            }

            confirmSaveButton.disabled = true;
            confirmSaveButton.innerHTML = '<span class="button-spinner" aria-hidden="true"></span> Saving...';

            // Hide both dialogs while the existing server update is running.
            confirmModal.className = 'user-modal access-confirm-modal';
            confirmModal.setAttribute('aria-hidden', 'true');
            modal.className = 'user-modal access-modal';
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('user-modal-open');

            formToSubmit.submit();
        };
    }

    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && confirmModal
            && confirmModal.className.indexOf('is-open') !== -1) {
            closeSaveConfirmation();
        } else if ((key === 'Escape' || key === 27)
            && modal.className.indexOf('is-open') !== -1) {
            closeModal();
        }
    });
}());

/*
 * Edit Access success modal.
 *
 * The existing server flash message is the source of truth. This dialog is
 * rendered only after the controller confirms that access was saved.
 */
(function () {
    'use strict';

    var successModal = document.getElementById('access-save-success-modal');
    var successDialog = successModal
        ? successModal.querySelector('.create-message-dialog')
        : null;
    var closeButtons = successModal && successModal.querySelectorAll
        ? successModal.querySelectorAll('[data-close-access-success]')
        : [];
    var successMessage = successModal
        ? successModal.getAttribute('data-success-message')
        : '';
    var pageNotices = document.querySelectorAll
        ? document.querySelectorAll('.users-notice-success')
        : [];
    var index;

    if (!successModal || !successDialog) {
        return;
    }

    /** Avoid displaying the same server confirmation twice on the page. */
    for (index = 0; index < pageNotices.length; index++) {
        if (successMessage
            && String(pageNotices[index].textContent || pageNotices[index].innerText || '')
                .replace(/^\s+|\s+$/g, '') === successMessage) {
            pageNotices[index].style.display = 'none';
        }
    }

    function closeSuccessModal() {
        successModal.className = 'user-modal access-success-modal';
        successModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('user-modal-open');
    }

    for (index = 0; index < closeButtons.length; index++) {
        closeButtons[index].onclick = closeSuccessModal;
    }

    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && successModal.className.indexOf('is-open') !== -1) {
            closeSuccessModal();
        }
    });

    document.body.classList.add('user-modal-open');
    successDialog.focus();
}());

/*
 * New User modal behavior.
 *
 * Opens the form without navigating away from Manage Users. Closing the modal
 * changes browser UI only; it does not submit a form or touch the database.
 */
(function () {
    'use strict';

    var modal = document.getElementById('user-create-modal');
    var dialog = modal ? modal.querySelector('.user-modal-dialog') : null;
    var openButton = document.getElementById('open-user-create-modal');
    var closeButtons = modal && modal.querySelectorAll
        ? modal.querySelectorAll('[data-close-user-modal]')
        : [];
    var previousFocus = null;

    if (!modal || !dialog) {
        return;
    }

    /** Opens the modal and moves keyboard focus into the dialog. */
    function openModal() {
        previousFocus = document.activeElement;
        modal.className = 'user-modal is-open';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('user-modal-open');
        if (openButton) {
            openButton.classList.add('is-active');
        }
        dialog.focus();
    }

    /** Closes the modal and returns focus to the New User button. */
    function closeModal() {
        modal.className = 'user-modal';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('user-modal-open');
        if (openButton) {
            openButton.classList.remove('is-active');
        }

        if (previousFocus && previousFocus.focus) {
            previousFocus.focus();
        } else if (openButton) {
            openButton.focus();
        }
    }

    if (openButton) {
        openButton.onclick = openModal;
    }

    for (var index = 0; index < closeButtons.length; index++) {
        closeButtons[index].onclick = closeModal;
    }

    // Escape provides the expected keyboard shortcut for dismissing a dialog.
    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && modal.className.indexOf('is-open') !== -1) {
            closeModal();
        }
    });

    // Server validation or a successful INSERT can render the modal already open.
    if (modal.className.indexOf('is-open') !== -1) {
        document.body.classList.add('user-modal-open');
        if (openButton) {
            openButton.classList.add('is-active');
        }
        dialog.focus();
    }
}());

/*
 * Edit User modal behavior.
 *
 * A server-generated Edit URL opens the selected account. Closing affects
 * browser presentation only and never submits an UPDATE.
 */
(function () {
    'use strict';

    var modal = document.getElementById('user-edit-modal');
    var dialog = modal ? modal.querySelector('.user-modal-dialog') : null;
    var closeButtons = modal && modal.querySelectorAll
        ? modal.querySelectorAll('[data-close-edit-modal]')
        : [];
    var editToolbarButton = document.getElementById('toggle-user-edit-menu');

    if (!modal || !dialog) {
        return;
    }

    /** Closes the Edit dialog and returns to the visible Users list. */
    function closeModal() {
        modal.className = 'user-modal';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('user-modal-open');
        if (editToolbarButton) {
            editToolbarButton.classList.remove('is-active');
        }
    }

    for (var index = 0; index < closeButtons.length; index++) {
        closeButtons[index].onclick = closeModal;
    }

    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && modal.className.indexOf('is-open') !== -1) {
            closeModal();
        }
    });

    // Controller validation and successful UPDATE responses open the modal.
    if (modal.className.indexOf('is-open') !== -1) {
        document.body.classList.add('user-modal-open');
        if (editToolbarButton) {
            editToolbarButton.classList.add('is-active');
        }
        dialog.focus();
    }
}());

/*
 * Edit User form behavior.
 *
 * Filters departments, optionally generates a new password, and confirms the
 * one-record UPDATE. A blank password remains blank and preserves the old hash.
 */
(function () {
    'use strict';

    var subsidiary = document.getElementById('edit-subsidiary');
    var department = document.getElementById('edit-department');
    var generateButton = document.getElementById('generate-edit-password');
    var password = document.getElementById('edit-password');
    var editForm = document.getElementById('user-edit-form');
    var confirmModal = document.getElementById('user-edit-confirm-modal');
    var confirmDialog = confirmModal
        ? confirmModal.querySelector('.create-message-dialog')
        : null;
    var confirmButton = document.getElementById('confirm-edit-user');
    var cancelConfirmButtons = confirmModal && confirmModal.querySelectorAll
        ? confirmModal.querySelectorAll('[data-close-edit-confirm]')
        : [];
    var editModal = document.getElementById('user-edit-modal');
    var editDialog = editModal ? editModal.querySelector('.user-modal-dialog') : null;
    var pendingEditSubmit = false;

    /** Shows only departments belonging to the selected subsidiary. */
    function updateDepartments() {
        var selectedSubsidiary;
        var selectedDepartment;
        var options;
        var visibleOptions = 0;
        var index;

        if (!subsidiary || !department) {
            return;
        }

        selectedSubsidiary = subsidiary.value;
        selectedDepartment = department.value;
        options = department.getElementsByTagName('option');

        for (index = 0; index < options.length; index++) {
            if (!options[index].getAttribute('data-sub-id')) {
                options[index].hidden = false;
                continue;
            }

            options[index].hidden =
                options[index].getAttribute('data-sub-id') !== selectedSubsidiary;

            if (!options[index].hidden) {
                visibleOptions++;
            }
        }

        department.disabled = selectedSubsidiary === '' || visibleOptions === 0;

        if (department.disabled) {
            department.value = '';
            options[0].text = selectedSubsidiary === ''
                ? 'Select subsidiary first'
                : 'No departments available';
        } else {
            options[0].text = 'Select department';

            if (!selectedDepartment
                || department.options[department.selectedIndex].hidden) {
                department.value = '';
            }
        }
    }

    /** Creates a compatible temporary password only when explicitly requested. */
    function generatePassword() {
        var characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        var generated = '';
        var index;

        for (index = 0; index < 10; index++) {
            generated += characters.charAt(Math.floor(Math.random() * characters.length));
        }

        password.value = generated;
        password.focus();
        password.select();
    }

    if (subsidiary && department) {
        subsidiary.onchange = updateDepartments;
        updateDepartments();
    }

    if (generateButton && password) {
        generateButton.onclick = generatePassword;
    }

    function closeEditConfirmation() {
        if (!confirmModal) {
            return;
        }

        confirmModal.className = 'user-modal edit-confirm-modal';
        confirmModal.setAttribute('aria-hidden', 'true');
        pendingEditSubmit = false;

        if (editDialog) {
            editDialog.focus();
        }
    }

    if (editForm) {
        editForm.onsubmit = function (event) {
            if (pendingEditSubmit) {
                return true;
            }

            if (!confirmModal || !confirmDialog) {
                return true;
            }

            if (event && event.preventDefault) {
                event.preventDefault();
            }

            confirmModal.className = 'user-modal edit-confirm-modal is-open';
            confirmModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('user-modal-open');
            confirmDialog.focus();
            return false;
        };
    }

    for (var confirmIndex = 0; confirmIndex < cancelConfirmButtons.length; confirmIndex++) {
        cancelConfirmButtons[confirmIndex].onclick = closeEditConfirmation;
    }

    if (confirmButton && editForm) {
        confirmButton.onclick = function () {
            pendingEditSubmit = true;
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<span class="button-spinner" aria-hidden="true"></span> Saving...';
            editForm.submit();
        };
    }

    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && confirmModal
            && confirmModal.className.indexOf('is-open') !== -1) {
            closeEditConfirmation();
        }
    });
}());

/* Displays Edit Info success only after the controller sets its flash message. */
(function () {
    'use strict';

    var successModal = document.getElementById('user-edit-success-modal');
    var successDialog = successModal
        ? successModal.querySelector('.create-message-dialog')
        : null;
    var closeButtons = successModal && successModal.querySelectorAll
        ? successModal.querySelectorAll('[data-close-edit-success]')
        : [];
    var editModal = document.getElementById('user-edit-modal');
    var index;

    if (!successModal || !successDialog) {
        return;
    }

    function closeSuccessModal() {
        successModal.className = 'user-modal edit-success-modal';
        successModal.setAttribute('aria-hidden', 'true');

        if (editModal) {
            editModal.className = 'user-modal';
            editModal.setAttribute('aria-hidden', 'true');
        }

        document.body.classList.remove('user-modal-open');
    }

    for (index = 0; index < closeButtons.length; index++) {
        closeButtons[index].onclick = closeSuccessModal;
    }

    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && successModal.className.indexOf('is-open') !== -1) {
            closeSuccessModal();
        }
    });

    document.body.classList.add('user-modal-open');
    successDialog.focus();
}());

/*
 * New User form behavior.
 *
 * Filters departments by subsidiary, generates a temporary password, and asks
 * for confirmation before the normal CI3 form submission.
 */
(function () {
    'use strict';

    var subsidiary = document.getElementById('subsidiary');
    var department = document.getElementById('department');
    var generateButton = document.getElementById('generate-password');
    var password = document.getElementById('password');
    var createForm = document.getElementById('user-create-form');
    var createModal = document.getElementById('user-create-modal');
    var confirmModal = document.getElementById('user-create-confirm-modal');
    var confirmDialog = confirmModal
        ? confirmModal.querySelector('.create-message-dialog')
        : null;
    var confirmButton = document.getElementById('confirm-create-user');
    var cancelConfirmButtons = confirmModal && confirmModal.querySelectorAll
        ? confirmModal.querySelectorAll('[data-close-create-confirm]')
        : [];
    var submitButton = createForm
        ? createForm.querySelector('button[type="submit"]')
        : null;
    var allowConfirmedSubmit = false;

    /** Opens the custom confirmation above the completed New User form. */
    function openConfirmation() {
        if (!confirmModal || !confirmDialog) {
            return;
        }

        confirmModal.className = 'user-modal create-confirm-modal is-open';
        confirmModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('user-modal-open');
        confirmDialog.focus();
    }

    /** Returns to the New User form without changing any entered value. */
    function closeConfirmation() {
        if (!confirmModal) {
            return;
        }

        confirmModal.className = 'user-modal create-confirm-modal';
        confirmModal.setAttribute('aria-hidden', 'true');

        if (submitButton) {
            submitButton.focus();
        }
    }

    /**
     * Shows only departments that belong to the selected subsidiary.
     */
    function updateDepartments() {
        var selectedSubsidiary;
        var selectedDepartment;
        var options;
        var visibleOptions = 0;
        var index;

        if (!subsidiary || !department) {
            return;
        }

        selectedSubsidiary = subsidiary.value;
        selectedDepartment = department.value;
        options = department.getElementsByTagName('option');

        // Hide department options whose data-sub-id does not match.
        for (index = 0; index < options.length; index++) {
            if (!options[index].getAttribute('data-sub-id')) {
                options[index].hidden = false;
                continue;
            }

            options[index].hidden =
                options[index].getAttribute('data-sub-id') !== selectedSubsidiary;

            if (!options[index].hidden) {
                visibleOptions++;
            }
        }

        department.disabled = selectedSubsidiary === '' || visibleOptions === 0;

        // Reset invalid choices and provide a useful first-option message.
        if (department.disabled) {
            department.value = '';
            options[0].text = selectedSubsidiary === ''
                ? 'Select subsidiary first'
                : 'No departments available';
        } else {
            options[0].text = 'Select department';

            if (!selectedDepartment
                || department.options[department.selectedIndex].hidden) {
                department.value = '';
            }
        }
    }

    /**
     * Creates a 10-character password without easily confused characters.
     *
     * The server hashes this value using the old RMS-compatible MD5 process
     * only after validation and user confirmation.
     */
    function generatePassword() {
        var characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        var generated = '';
        var index;

        for (index = 0; index < 10; index++) {
            generated += characters.charAt(
                Math.floor(Math.random() * characters.length)
            );
        }

        password.value = generated;
        password.focus();
        password.select();
    }

    // Activate dependent Department filtering when both dropdowns are present.
    if (subsidiary && department) {
        subsidiary.onchange = updateDepartments;
        updateDepartments();
    }

    // Connect the Generate button to password generation.
    if (generateButton && password) {
        generateButton.onclick = generatePassword;
    }

    // Prevent accidental account creation with a page-styled confirmation modal.
    if (createForm) {
        createForm.onsubmit = function (event) {
            if (allowConfirmedSubmit) {
                return true;
            }

            if (event && event.preventDefault) {
                event.preventDefault();
            }

            openConfirmation();
            return false;
        };
    }

    for (var closeIndex = 0; closeIndex < cancelConfirmButtons.length; closeIndex++) {
        cancelConfirmButtons[closeIndex].onclick = closeConfirmation;
    }

    // Submit the unchanged CI3 form only after explicit confirmation.
    if (confirmButton && createForm) {
        confirmButton.onclick = function () {
            allowConfirmedSubmit = true;
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<span class="button-spinner" aria-hidden="true"></span> Saving...';

            if (createModal) {
                createModal.className = 'user-modal';
                createModal.setAttribute('aria-hidden', 'true');
            }

            if (confirmModal) {
                confirmModal.className = 'user-modal create-confirm-modal';
                confirmModal.setAttribute('aria-hidden', 'true');
            }

            createForm.submit();
        };
    }

    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && confirmModal
            && confirmModal.className.indexOf('is-open') !== -1) {
            closeConfirmation();
        }
    });
}());

/*
 * New User success modal behavior.
 *
 * Existing one-time flash data supplies the credentials. The create form stays
 * closed after the successful redirect, and this smaller message closes back
 * to the refreshed Users list.
 */
(function () {
    'use strict';

    var successModal = document.getElementById('user-create-success-modal');
    var successDialog = successModal
        ? successModal.querySelector('.create-message-dialog')
        : null;
    var closeButtons = successModal && successModal.querySelectorAll
        ? successModal.querySelectorAll('[data-close-create-success]')
        : [];
    var openButton = document.getElementById('open-user-create-modal');

    if (!successModal || !successDialog) {
        return;
    }

    /** Closes the one-time success message and reveals the refreshed list. */
    function closeSuccess() {
        successModal.className = 'user-modal create-success-modal';
        successModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('user-modal-open');

        // Remove ?new_user=1 so refreshing does not reopen an empty form.
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        if (openButton) {
            openButton.classList.remove('is-active');
            openButton.focus();
        }
    }

    for (var index = 0; index < closeButtons.length; index++) {
        closeButtons[index].onclick = closeSuccess;
    }

    document.addEventListener('keydown', function (event) {
        var key = event.key || event.keyCode;

        if ((key === 'Escape' || key === 27)
            && successModal.className.indexOf('is-open') !== -1) {
            closeSuccess();
        }
    });

    document.body.classList.add('user-modal-open');
    successDialog.focus();
}());
