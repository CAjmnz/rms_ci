<?php
/*
 * File Type Setting view.
 *
 * Prepare safe account, route, and asset values before rendering the page.
 */
$safe_name = isset($display_name) && $display_name !== '' ? $display_name : $username;
$safe_position = isset($position) && $position !== '' ? $position : 'Super User';
$safe_routes = is_array($routes) ? $routes : array();

$route_url = function ($key, $fallback) use ($safe_routes) {
    $route = isset($safe_routes[$key]) ? $safe_routes[$key] : $fallback;
    return site_url($route);
};

$name_parts = preg_split('/\s+/', trim($safe_name));
$initials = isset($name_parts[0][0]) ? strtoupper($name_parts[0][0]) : 'A';

if (count($name_parts) > 1) {
    $last_name = $name_parts[count($name_parts) - 1];
    $initials .= strtoupper($last_name[0]);
}

$asset_url = function ($path) {
    $file = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $version = is_file($file) ? filemtime($file) : '1';
    return base_url($path) . '?v=' . $version;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>

    <!-- Shared administrator styles. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-footer.css'); ?>">
    <link rel="stylesheet" href="<?php echo $asset_url('assets/css/rms-system.css'); ?>">
</head>
<body class="system-page">
    <div class="dashboard-app">
        <?php
        // Keep System highlighted in the shared sidebar.
        $this->load->view('admin/partials/sidebar', array(
            'sidebar_active' => 'system',
            'sidebar_show_system' => TRUE,
            'initials' => $initials,
            'safe_display_name' => $safe_name,
            'safe_position' => $safe_position,
            'route_url' => $route_url
        ));
        ?>

        <main class="main-area">
            <?php
            // Reuse the same account menu displayed on the other admin pages.
            $this->load->view('admin/partials/header', array(
                'header_kicker' => 'SYSTEM ADMINISTRATION',
                'header_title' => 'File Type Setting',
                'header_description' => 'Control which file extensions may be used by the records system.',
                'header_stat_label' => 'File types',
                'header_stat_value' => count($filetypes),
                'header_stat_icon' => 'settings',
                'header_primary_url' => site_url('administrator/users/profile'),
                'header_primary_label' => 'Edit profile',
                'header_logout_url' => $route_url('logout', 'administrator/logout'),
                'initials' => $initials,
                'safe_display_name' => $safe_name,
                'safe_position' => $safe_position
            ));
            ?>

            <div class="system-content">
                <!-- Compact navigation shared by all System pages. -->
                <nav class="system-tabs" aria-label="System sections">
                    <a href="<?php echo site_url('administrator/system'); ?>">Global Configuration</a>
                    <a class="active" href="<?php echo site_url('administrator/system/file-types'); ?>">
                        File Type Setting
                    </a>
                    <a href="<?php echo site_url('administrator/system/access-logs'); ?>">Access Logs</a>
                <a href="<?php echo site_url('administrator/system/backup'); ?>">Backup</a>
                </nav>

                <!-- Compact Users-style sub-hero keeps System pages visually consistent. -->

                <section class="filetype-panel">
                    <!-- Page controls and live client-side search. -->
                    <header class="filetype-heading">
                        <div>
                            <p>ALLOWED EXTENSIONS</p>
                            <h2>File types</h2>
                            <span>Enabled types are available to the legacy document workflow.</span>
                        </div>
                        <button type="button" class="primary-action" id="filetype-add">
                            <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            Add file type
                        </button>
                    </header>

                    <div class="filetype-toolbar">
                        <label class="table-search">
                            <span aria-hidden="true">⌕</span>
                            <input id="filetype-search" type="search" placeholder="Search file types" autocomplete="off">
                        </label>

                        <div class="bulk-actions" aria-label="Selected file type actions">
                            <button type="button" id="filetype-edit"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16v4zM13.5 6.5l4 4"/></svg>Edit</button>
                            <button type="button" id="filetype-enable"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>Enable</button>
                            <button type="button" id="filetype-disable"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>Disable</button>
                            <button type="button" class="danger" id="filetype-delete"><svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>Delete</button>
                        </div>
                    </div>

                    <!-- Existing rows from the legacy filetypes table. -->
                    <div class="filetype-table-wrap">
                        <table class="filetype-table" id="filetype-table">
                            <thead>
                                <tr>
                                    <th class="select-column">
                                        <input type="checkbox" id="filetype-check-all" aria-label="Select all file types">
                                    </th>
                                    <th>ID</th>
                                    <th>File type name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filetypes as $row): ?>
                                    <?php $enabled = (int) $row['stat'] === 0; ?>
                                    <tr
                                        data-id="<?php echo (int) $row['filetype_id']; ?>"
                                        data-type="<?php echo html_escape($row['type']); ?>"
                                    >
                                        <td class="select-column">
                                            <input
                                                type="checkbox"
                                                class="filetype-check"
                                                value="<?php echo (int) $row['filetype_id']; ?>"
                                                aria-label="Select <?php echo html_escape($row['type']); ?>"
                                            >
                                        </td>
                                        <td><span class="id-chip">#<?php echo (int) $row['filetype_id']; ?></span></td>
                                        <td><strong><?php echo html_escape($row['type']); ?></strong></td>
                                        <td>
                                            <span class="status-pill <?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                                                <i></i><?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (count($filetypes) === 0): ?>
                                    <tr class="empty-row">
                                        <td colspan="4">No file types have been configured.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <footer class="filetype-footer">
                        <span><b id="filetype-visible-count"><?php echo count($filetypes); ?></b> file types shown</span>
                        <span id="filetype-selected-count">0 selected</span>
                    </footer>
                </section>
            </div>
        </main>
    </div>

    <!-- Add/Edit dialog: backdrop and Escape are intentionally locked. -->
    <div class="system-modal" id="filetype-modal" hidden>
        <section class="system-modal-card" role="dialog" aria-modal="true" aria-labelledby="filetype-modal-title">
            <header>
                <div>
                    <small>FILE TYPE SETTING</small>
                    <h2 id="filetype-modal-title">Add file type</h2>
                </div>
                <button type="button" class="modal-close" id="filetype-modal-close" aria-label="Close">×</button>
            </header>

            <?php echo form_open('administrator/system/file-types/save', array('id' => 'filetype-form', 'novalidate' => 'novalidate')); ?>
                <input type="hidden" name="filetype_id" id="filetype-id" value="0">

                <div class="modal-body">
                    <label for="filetype-name">File type name <b>*</b></label>
                    <div class="extension-input">
                        <span>.</span>
                        <input
                            type="text"
                            name="type"
                            id="filetype-name"
                            maxlength="16"
                            placeholder="pdf"
                            autocomplete="off"
                            required
                        >
                    </div>
                    <p>Enter an extension such as <strong>.pdf</strong>, <strong>.docx</strong>, or <strong>.jpg</strong>.</p>
                    <div class="modal-error" id="filetype-error" role="alert"></div>
                </div>

                <footer>
                    <button type="button" class="secondary-action" id="filetype-cancel">Cancel</button>
                    <button type="submit" class="primary-action" id="filetype-save">Save file type</button>
                </footer>
            <?php echo form_close(); ?>
        </section>
    </div>

    <!-- Confirmation dialog used before permanent deletion. -->
    <div class="system-modal" id="filetype-confirm" hidden>
        <section class="system-modal-card confirm-card" role="alertdialog" aria-modal="true">
            <div class="confirm-icon">!</div>
            <h2>Delete selected file types?</h2>
            <p>This permanently removes the selected records from the legacy filetypes table.</p>
            <div class="confirm-actions">
                <button type="button" class="secondary-action" id="filetype-confirm-cancel">Cancel</button>
                <button type="button" class="danger-action" id="filetype-confirm-delete">Delete</button>
            </div>
        </section>
    </div>

    <!-- Small success/error notification for completed AJAX actions. -->
    <div class="system-toast" id="filetype-toast" hidden role="status"></div>

    <script>
        // Expose only endpoints and the current CSRF token to the module script.
        window.RMS_FILETYPES = <?php echo json_encode(array(
            'save' => site_url('administrator/system/file-types/save'),
            'status' => site_url('administrator/system/file-types/status'),
            'delete' => site_url('administrator/system/file-types/delete'),
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        )); ?>;
    </script>
    <script src="<?php echo base_url('assets/js/jquery-3.5.1.min.js'); ?>"></script>
    <script src="<?php echo base_url('assets/js/rms-header.js'); ?>"></script>
    <script src="<?php echo $asset_url('assets/js/rms-filetypes.js'); ?>"></script>
</body>
</html>
