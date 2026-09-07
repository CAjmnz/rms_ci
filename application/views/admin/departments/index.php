<?php
/*
 * Departments management view.
 * It reuses the same organization design language as Users and Subsidiaries.
 */
$safe_name = isset($display_name) && $display_name !== ''
    ? $display_name
    : $username;

$safe_position = isset($position) && $position !== ''
    ? $position
    : 'Administrator';

$safe_routes = is_array($routes) ? $routes : array();

$route_url = function ($key, $fallback) use ($safe_routes) {
    return site_url(
        isset($safe_routes[$key]) ? $safe_routes[$key] : $fallback
    );
};

$parts = preg_split('/\s+/', trim($safe_name));

$initials = isset($parts[0][0])
    ? strtoupper($parts[0][0])
    : 'A';

if (count($parts) > 1) {
    $last = $parts[count($parts) - 1];
    $initials .= strtoupper($last[0]);
}

$list_url = function ($new_page) use ($search, $per_page, $sort, $order) {
    $query = array(
        'page' => $new_page,
        'per_page' => $per_page,
        'sort' => $sort,
        'order' => $order
    );

    if ($search !== '') {
        $query['search'] = $search;
    }

    return site_url('administrator/departments')
        . '?'
        . http_build_query($query);
};

/* Build trusted sort links while preserving the current filters. */
$sort_url = function ($column) use ($search, $per_page, $sort, $order) {
    $next_order = $sort === $column && $order === 'asc' ? 'desc' : 'asc';
    $query = array(
        'page' => 1,
        'per_page' => $per_page,
        'sort' => $column,
        'order' => $next_order
    );
    if ($search !== '') { $query['search'] = $search; }
    return site_url('administrator/departments').'?'.http_build_query($query);
};

/* Version shared assets automatically after every project-file replacement. */
$asset_url = function ($path) {
    $full_path = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $path);
    $version = is_file($full_path) ? filemtime($full_path) : '1';
    return base_url($path).'?v='.$version;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?php echo html_escape($page_title); ?> |
        <?php echo html_escape($system_name); ?>
    </title>

    <link
        rel="stylesheet"
        href="<?php echo $asset_url('assets/css/rms-dashboard.css'); ?>"
    >
    <link
        rel="stylesheet"
        href="<?php echo $asset_url('assets/css/rms-sidebar.css'); ?>"
    >
    <link
        rel="stylesheet"
        href="<?php echo $asset_url('assets/css/rms-header.css'); ?>"
    >
    <link
        rel="stylesheet"
        href="<?php echo $asset_url('assets/css/rms-footer.css'); ?>"
    >

    <!-- Shared organization design keeps Departments identical to Subsidiaries. -->
    <link
        rel="stylesheet"
        href="<?php echo $asset_url('assets/css/rms-subsidiaries.css'); ?>"
    >

    <!-- Department-only rules load last so this modal cannot inherit broken selector experiments. -->
    <link
        rel="stylesheet"
        href="<?php echo $asset_url('assets/css/rms-departments.css'); ?>"
    >
    <link rel="stylesheet" href="<?php echo base_url('assets/react/rms-react.css'); ?>">
</head>

<body><div data-rms-react-root="departments">
<div class="dashboard-app">

    <?php
    $this->load->view('admin/partials/sidebar', array(
        'sidebar_active' => 'departments',
        'sidebar_show_system' => TRUE,
        'initials' => $initials,
        'safe_display_name' => $safe_name,
        'safe_position' => $safe_position,
        'route_url' => $route_url
    ));
    ?>

    <main class="main-area">

        <?php
        $this->load->view('admin/partials/header', array(
            'header_kicker' => 'ORGANIZATION DIRECTORY',
            'header_title' => 'Departments',
            'header_description' => 'Manage departments and assign each department to its corresponding subsidiary.',
            'header_stat_label' => 'Total departments',
            'header_stat_value' => number_format($total),
            'header_stat_id' => 'departments-matching-total',
            'header_stat_icon' => 'departments',
            'header_logout_url' => $route_url(
                'logout',
                'administrator/logout'
            ),
            'initials' => $initials,
            'safe_display_name' => $safe_name,
            'safe_position' => $safe_position
        ));
        ?>

        <div class="subs-content">


            <section class="subs-panel">

                <div class="subs-heading">
                    <div>
                        <p>DEPARTMENTS MODULE</p>
                        <h2>Manage departments</h2>
                    </div>

                    <form
                        class="subs-search"
                        id="departments-search-form"
                        method="get"
                        action="<?php echo site_url('administrator/departments'); ?>"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m16 16 5 5"></path>
                        </svg>

                        <input
                            id="departments-search"
                            type="search"
                            name="search"
                            value="<?php echo html_escape($search); ?>"
                            placeholder="Search departments"
                        >

                        <input
                            type="hidden"
                            name="per_page"
                            value="<?php echo (int) $per_page; ?>"
                        >
                        <input type="hidden" name="sort" value="<?php echo html_escape($sort); ?>">
                        <input type="hidden" name="order" value="<?php echo html_escape($order); ?>">

                        <button type="submit">Search</button>

                        <?php if ($search !== ''): ?>
                            <a href="<?php echo site_url('administrator/departments'); ?>">
                                Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="subs-toolbar">

                    <button
                        type="button"
                        class="action primary"
                        id="dept-add"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5v14M5 12h14"></path>
                        </svg>

                        <span>New department</span>
                    </button>

                    <button
                        type="button"
                        class="action"
                        id="dept-edit"
                        disabled
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"></path>
                        </svg>

                        <span>Edit</span>
                    </button>

                    <button
                        type="button"
                        class="action danger"
                        id="dept-delete"
                        disabled
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"></path>
                        </svg>

                        <span>Delete</span>
                    </button>
                </div>

                <!-- AJAX replaces only this result region; the page shell remains untouched. -->
                <div id="departments-ajax-region" aria-live="polite">
                <div class="subs-table-wrap">
                    <table class="subs-table">
                        <thead>
                            <tr>
                                <th class="check">
                                    <input
                                        type="checkbox"
                                        id="dept-all"
                                        aria-label="Select all departments"
                                    >
                                </th>

                                <th><a class="sort-link<?php echo $sort === 'name' ? ' active' : ''; ?>" href="<?php echo $sort_url('name'); ?>">Department name <span><?php echo $sort === 'name' && $order === 'desc' ? '↓' : '↑'; ?></span></a></th>
                                <th><a class="sort-link<?php echo $sort === 'subsidiary' ? ' active' : ''; ?>" href="<?php echo $sort_url('subsidiary'); ?>">Subsidiary <span><?php echo $sort === 'subsidiary' && $order === 'desc' ? '↓' : '↑'; ?></span></a></th>
                                <th class="id-col"><a class="sort-link<?php echo $sort === 'id' ? ' active' : ''; ?>" href="<?php echo $sort_url('id'); ?>">ID <span><?php echo $sort === 'id' && $order === 'desc' ? '↓' : '↑'; ?></span></a></th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (!empty($departments)): ?>

                            <?php foreach ($departments as $row): ?>
                                <tr
                                    data-id="<?php echo (int) $row['dept_id']; ?>"
                                    data-name="<?php echo html_escape($row['dept_name']); ?>"
                                    data-sub-id="<?php echo (int) $row['sub_id']; ?>"
                                    data-sub-name="<?php echo html_escape($row['sub_name']); ?>"
                                >
                                    <td class="check">
                                        <input
                                            class="dept-check"
                                            type="checkbox"
                                            value="<?php echo (int) $row['dept_id']; ?>"
                                            aria-label="Select <?php echo html_escape($row['dept_name']); ?>"
                                        >
                                    </td>

                                    <td>
                                        <div class="department-name-cell">
                                            <span class="company-icon">
                                                <svg
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M4 21V8h16v13M8 8V4h8v4M8 12h2M14 12h2M8 16h2M14 16h2"></path>
                                                </svg>
                                            </span>

                                            <strong>
                                                <?php echo html_escape($row['dept_name']); ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td class="subsidiary-cell">
                                        <?php echo html_escape($row['sub_name']); ?>
                                    </td>

                                    <td class="id-col">
                                        #<?php echo (int) $row['dept_id']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="empty">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M4 21V8h16v13M8 8V4h8v4"></path>
                                    </svg>

                                    <strong>No departments found</strong>

                                    <span>
                                        <?php
                                        echo $search !== ''
                                            ? 'Try a different search.'
                                            : 'Add the first department to begin.';
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="subs-footer">

                    <form
                        method="get"
                        action="<?php echo site_url('administrator/departments'); ?>"
                    >
                        <?php if ($search !== ''): ?>
                            <input
                                type="hidden"
                                name="search"
                                value="<?php echo html_escape($search); ?>"
                            >
                        <?php endif; ?>
                        <input type="hidden" name="sort" value="<?php echo html_escape($sort); ?>">
                        <input type="hidden" name="order" value="<?php echo html_escape($order); ?>">

                        <label>
                            Rows per page

                            <select
                                id="departments-per-page"
                                name="per_page"
                            >
                                <?php foreach (array(10, 25, 50, 100) as $size): ?>
                                    <option
                                        value="<?php echo $size; ?>"
                                        <?php echo $size === $per_page ? ' selected' : ''; ?>
                                    >
                                        <?php echo $size; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </form>

                    <span>
                        Showing <?php echo $first_row; ?>–<?php echo $last_row; ?>
                        of <?php echo $total; ?>
                    </span>

                    <nav aria-label="Departments pagination">
                        <a
                            class="<?php echo $page <= 1 ? 'disabled' : ''; ?>"
                            href="<?php echo $list_url(max(1, $page - 1)); ?>"
                            aria-label="Previous page"
                        >
                            &lsaquo;
                        </a>

                        <strong>
                            <?php echo $page; ?> / <?php echo $pages; ?>
                        </strong>

                        <a
                            class="<?php echo $page >= $pages ? 'disabled' : ''; ?>"
                            href="<?php echo $list_url(min($pages, $page + 1)); ?>"
                            aria-label="Next page"
                        >
                            &rsaquo;
                        </a>
                    </nav>
                </div>
                </div>
            </section>
        </div>

        <!-- Use the same shared footer shown on Dashboard and Users. -->
        <?php $this->load->view('admin/partials/footer'); ?>
    </main>
</div>

<!-- Add/Edit Department Modal -->
<div class="subs-modal" id="dept-form-modal" hidden>
    <div class="subs-backdrop" aria-hidden="true"></div>

    <section
        class="subs-dialog dept-form-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="dept-form-title"
    >
        <!-- Centered heading follows the approved Department modal mockup. -->
        <header class="dept-form-header">
            <div class="dept-form-heading">
                <small id="dept-form-eyebrow">DEPARTMENTS MODULE &middot; NEW DEPARTMENT</small>
                <h2 id="dept-form-title">New department</h2>
                <p>Create a department and assign it to a subsidiary.</p>
            </div>

            <button
                type="button"
                class="modal-close"
                data-close="form"
                aria-label="Close"
            >
                &times;
            </button>
        </header>

        <?php
        echo form_open(
            'administrator/departments/save',
            array('id' => 'dept-form')
        );
        ?>

            <input
                type="hidden"
                name="dept_id"
                id="dept-id"
                value=""
            >

            <!--
             * Department-only modal scope.
             * These classes prevent older shared form styles from collapsing
             * the select, label, and helper text into the same row.
             -->
            <div class="form-body dept-form-body">

                <!--
                 * Single authoritative sub_id control.
                 * Keeping one native select prevents the duplicate control and
                 * oversized icon seen when older CSS conflicts with a custom picker.
                 -->
                <div class="form-group dept-form-group dept-subsidiary-field">
                    <label for="dept-subsidiary">
                        Subsidiary <em>*</em>
                    </label>

                    <div class="dept-subsidiary-select-wrap">
                        <select
                            name="sub_id"
                            id="dept-subsidiary"
                            required
                        >
                            <option value="">Select subsidiary</option>

                            <?php foreach ($subsidiaries as $subsidiary): ?>
                                <option
                                    value="<?php echo (int) $subsidiary['sub_id']; ?>"
                                >
                                    <?php echo html_escape($subsidiary['sub_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <small class="dept-field-help">
                        Choose the parent subsidiary for this department.
                    </small>
                </div>

                <div class="form-group dept-form-group">
                    <label for="dept-name">
                        Department name <em>*</em>
                    </label>

                    <input
                        type="text"
                        name="department_name"
                        id="dept-name"
                        placeholder="e.g. Human Resources"
                        maxlength="100"
                        autocomplete="off"
                        required
                    >

                    <small>
                        Use the department’s official name.
                    </small>
                </div>

                <div
                    class="form-error"
                    id="dept-form-error"
                    role="alert"
                ></div>
            </div>

            <footer>
                <button
                    type="button"
                    class="cancel"
                    data-close="form"
                >
                    Cancel
                </button>

                <button type="submit" class="save">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 20v-6h8v6"></path>
                    </svg>

                    Save department
                </button>
            </footer>

        <?php echo form_close(); ?>
    </section>
</div>

<!-- Confirmation/Message Modal -->
<div class="subs-modal" id="dept-message-modal" hidden>
    <div class="subs-backdrop"></div>

    <section
        class="subs-dialog message"
        role="alertdialog"
        aria-modal="true"
    >
        <span
            class="message-icon"
            id="dept-message-icon"
        ></span>

        <h2 id="dept-message-title"></h2>
        <p id="dept-message-text"></p>

        <div class="message-actions">
            <button
                type="button"
                class="cancel"
                id="dept-message-cancel"
            >
                Cancel
            </button>

            <button
                type="button"
                class="save"
                id="dept-message-confirm"
            >
                Continue
            </button>
        </div>
    </section>
</div>

</div><script>
window.RMS_DEPARTMENTS = <?php echo json_encode(array(
    'form' => site_url('administrator/departments/form'),
    'save' => site_url('administrator/departments/save'),
    'delete' => site_url('administrator/departments/delete'),
    'csrfName' => $this->security->get_csrf_token_name(),
    'csrfHash' => $this->security->get_csrf_hash()
)); ?>;
</script>

<script src="<?php echo base_url('assets/react/rms-react.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/jquery-3.5.1.min.js'); ?>"></script>
<script src="<?php echo $asset_url('assets/js/rms-sidebar.js'); ?>"></script>
<script src="<?php echo $asset_url('assets/js/rms-header.js'); ?>"></script>
<!-- Department-only AJAX table behavior is kept outside the page markup. -->
<script src="<?php echo $asset_url('assets/js/rms-departments.js'); ?>"></script>

<script>
(function ($) {
    'use strict';

    var config = window.RMS_DEPARTMENTS;
    var selectedId = 0;
    var selectedRow = null;
    var selectedDepartments = [];
    var confirmAction = null;

    var $formModal = $('#dept-form-modal');
    var $messageModal = $('#dept-message-modal');
    var $form = $('#dept-form');
    var $subsidiarySelect = $('#dept-subsidiary');

    /* Consistent SVG feedback icons match the Users modal language. */
    var messageIcons = {
        success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>',
        warning: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 21h20L12 3zM12 9v5M12 17h.01"></path></svg>',
        error: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="m9 9 6 6M15 9l-6 6"></path></svg>'
    };

    function openFormModal() {
        $formModal.removeAttr('hidden');
        $('body').addClass('modal-open');
    }

    function closeFormModal() {
        $formModal.attr('hidden', true);
        $('body').removeClass('modal-open');
        $('#dept-form-error').text('');
    }

    function closeMessageModal() {
        $messageModal.attr('hidden', true);
        $('body').removeClass('modal-open');
        confirmAction = null;
    }

    function showMessage(title, message, confirmLabel, callback) {
        var normalizedTitle = String(title || '').toLowerCase();
        var type = normalizedTitle.indexOf('delete ') === 0
            ? 'warning'
            : 'success';

        if (normalizedTitle.indexOf('unable') !== -1 ||
            normalizedTitle.indexOf('failed') !== -1) {
            type = 'error';
        } else if (normalizedTitle.indexOf('warning') !== -1) {
            type = 'warning';
        }

        $('#dept-message-icon')
            .attr('class', 'message-icon ' + type)
            .html(messageIcons[type]);
        $('#dept-message-title').text(title);
        $('#dept-message-text').text(message);
        $('#dept-message-confirm').text(confirmLabel || 'Continue');

        confirmAction = typeof callback === 'function'
            ? callback
            : null;

        if (confirmAction) {
            $('#dept-message-cancel').show();
        } else {
            $('#dept-message-cancel').hide();
            $('#dept-message-confirm').text('OK');
        }

        $messageModal.removeAttr('hidden');
        $('body').addClass('modal-open');
    }

    function resetForm() {
        $form[0].reset();
        $('#dept-id').val('');
        $('#dept-form-error').text('');
    }

    function updateSelection() {
        var $selected = $('.dept-check:checked');

        selectedDepartments = [];

        $selected.each(function () {
            var $checkbox = $(this);
            var $row = $checkbox.closest('tr');

            selectedDepartments.push({
                id: parseInt($checkbox.val(), 10),
                name: $row.attr('data-name')
            });
        });

        if ($selected.length === 1) {
            selectedId = parseInt($selected.val(), 10);
            selectedRow = $selected.closest('tr');
        } else {
            selectedId = 0;
            selectedRow = null;
        }

        $('#dept-edit').prop('disabled', $selected.length !== 1);
        /* Delete supports one or many checked departments. */
        $('#dept-delete').prop('disabled', $selected.length === 0);

        /* Match the Subsidiaries marking: green row and left accent. */
        $('.dept-check').closest('tr').removeClass('selected');
        $selected.closest('tr').addClass('selected');

        $('#dept-all').prop(
            'checked',
            $('.dept-check').length > 0 &&
            $('.dept-check:checked').length === $('.dept-check').length
        );

        $('#dept-all').prop(
            'indeterminate',
            $selected.length > 0 &&
            $selected.length < $('.dept-check').length
        );
    }

    $('#dept-add').on('click', function () {
        resetForm();
        $('#dept-form-eyebrow').html('DEPARTMENTS MODULE &middot; NEW DEPARTMENT');
        $('#dept-form-title').text('New department');
        openFormModal();
        $subsidiarySelect.focus();
    });

    $('#dept-edit').on('click', function () {
        if (!selectedId || !selectedRow) {
            return;
        }

        resetForm();

        $('#dept-id').val(selectedId);
        $('#dept-name').val(selectedRow.attr('data-name'));
        $('#dept-subsidiary').val(selectedRow.attr('data-sub-id'));
        $('#dept-form-eyebrow').html('DEPARTMENTS MODULE &middot; EDIT DEPARTMENT');
        $('#dept-form-title').text('Edit department');

        openFormModal();
        $('#dept-name').focus();
    });

    $('#dept-delete').on('click', function () {
        if (!selectedDepartments.length) {
            return;
        }

        var departmentsToDelete = selectedDepartments.slice(0);
        var selectedCount = departmentsToDelete.length;
        var promptText = selectedCount === 1
            ? 'Are you sure you want to delete "' +
                departmentsToDelete[0].name +
                '"? This action cannot be undone.'
            : 'Are you sure you want to delete these ' +
                selectedCount +
                ' departments? This action cannot be undone.';

        showMessage(
            selectedCount === 1
                ? 'Delete department?'
                : 'Delete selected departments?',
            promptText,
            'Delete',
            function () {
                var deletedCount = 0;
                var failedNames = [];

                /*
                 * Keep the existing single-delete backend process intact.
                 * Requests run one at a time so CI3's regenerated CSRF token
                 * is carried safely into the next checked department.
                 */
                function deleteNext(index) {
                    var department;
                    var request;

                    if (index >= departmentsToDelete.length) {
                        closeMessageModal();

                        if (failedNames.length === 0) {
                            showMessage(
                                deletedCount === 1
                                    ? 'Department deleted'
                                    : 'Departments deleted',
                                deletedCount === 1
                                    ? 'The selected department was deleted successfully.'
                                    : deletedCount + ' departments were deleted successfully.',
                                'OK',
                                function () {
                                    window.location.reload();
                                }
                            );
                        } else {
                            showMessage(
                                deletedCount > 0
                                    ? 'Deletion partly completed'
                                    : 'Unable to delete',
                                deletedCount + ' deleted. Unable to delete: ' +
                                    failedNames.join(', ') + '.',
                                'OK',
                                function () {
                                    window.location.reload();
                                }
                            );
                        }

                        return;
                    }

                    department = departmentsToDelete[index];
                    request = {dept_id: department.id};
                    request[config.csrfName] = config.csrfHash;

                    $.ajax({
                        url: config.delete,
                        type: 'POST',
                        dataType: 'json',
                        data: request
                    }).done(function (response) {
                        if (response && response.csrfHash) {
                            config.csrfName = response.csrfName || config.csrfName;
                            config.csrfHash = response.csrfHash;
                        }

                        if (response && response.success) {
                            deletedCount++;
                        } else {
                            failedNames.push(department.name);
                        }

                        deleteNext(index + 1);
                    }).fail(function (xhr) {
                        /* Keep a refreshed token if the server returned one. */
                        if (xhr.responseJSON && xhr.responseJSON.csrfHash) {
                            config.csrfName = xhr.responseJSON.csrfName || config.csrfName;
                            config.csrfHash = xhr.responseJSON.csrfHash;
                        }

                        failedNames.push(department.name);
                        deleteNext(index + 1);
                    });
                }

                deleteNext(0);
            }
        );
    });

    /* Delegation keeps Select All working after the AJAX table is replaced. */
    $(document).on('change', '#dept-all', function () {
        $('.dept-check').prop('checked', this.checked);
        updateSelection();
    });

    $(document).on('change', '.dept-check', function () {
        updateSelection();
    });

    /* Clicking a Department row toggles its checkbox, matching Subsidiaries. */
    $(document).on('click', '.subs-table tbody tr[data-id]', function (event) {
        var $checkbox;

        if ($(event.target).is('input, a, button, select, label')) {
            return;
        }

        $checkbox = $(this).find('.dept-check');
        $checkbox.prop('checked', !$checkbox.prop('checked'));
        updateSelection();
    });

    $('[data-close="form"]').on('click', closeFormModal);

    /* Keep validation attached directly to the single sub_id select. */
    $subsidiarySelect.on('invalid', function (event) {
        $('#dept-form-error').text('Please select a subsidiary.');
    });

    $subsidiarySelect.on('change focus', function () {
        if ($(this).val()) {
            $('#dept-form-error').text('');
        }
    });

    $('#dept-message-cancel').on('click', closeMessageModal);

    $('#dept-message-confirm').on('click', function () {
        if (confirmAction) {
            var callback = confirmAction;
            confirmAction = null;
            callback();
        } else {
            closeMessageModal();
        }
    });

    $form.on('submit', function (event) {
        event.preventDefault();

        var $submit = $form.find('button[type="submit"]');
        var originalText = $submit.text();

        $('#dept-form-error').text('');
        $submit.prop('disabled', true).text('Saving...');

        $.ajax({
            url: config.save,
            type: 'POST',
            dataType: 'json',
            data: $form.serialize()
        }).done(function (response) {
            /* Store CI3's regenerated token for consecutive AJAX requests. */
            if (response && response.csrfHash) {
                config.csrfName = response.csrfName || config.csrfName;
                config.csrfHash = response.csrfHash;
            }
            if (!response.success) {
                $('#dept-form-error').text(
                    response.message || 'The department could not be saved.'
                );
                return;
            }

            closeFormModal();

            showMessage(
                response.storage_warning
                    ? 'Saved with storage warning'
                    : 'Saved successfully',
                response.warning || response.message,
                'OK',
                function () {
                    window.location.reload();
                }
            );
        }).fail(function (xhr) {
            var message = 'The server could not process the request.';

            if (
                xhr.responseJSON &&
                xhr.responseJSON.message
            ) {
                message = xhr.responseJSON.message;
            }

            $('#dept-form-error').text(message);
        }).always(function () {
            $submit
                .prop('disabled', false)
                .text(originalText);
        });
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            /* The form is intentionally static; use X or Cancel to close it. */
            if (!$formModal.is('[hidden]')) {
                event.preventDefault();
                return;
            }

            if (!$messageModal.is('[hidden]')) {
                closeMessageModal();
            }
        }
    });

    updateSelection();

})(jQuery);
</script>
</body>
</html>
