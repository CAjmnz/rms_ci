<?php
/* Prepare account, route, and cache-safe asset values for the shared layout. */
$safe_name = isset($display_name) && $display_name !== '' ? $display_name : $username;
$safe_position = isset($position) && $position !== '' ? $position : 'Super User';
$safe_routes = is_array($routes) ? $routes : array();
$route_url = function ($key, $fallback) use ($safe_routes) {
    return site_url(isset($safe_routes[$key]) ? $safe_routes[$key] : $fallback);
};
$name_parts = preg_split('/\s+/', trim($safe_name));
$initials = isset($name_parts[0][0]) ? strtoupper($name_parts[0][0]) : 'A';
if (count($name_parts) > 1) {
    $last_name = $name_parts[count($name_parts) - 1];
    $initials .= strtoupper($last_name[0]);
}
$asset_url = function ($path) {
    $file = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $path);
    return base_url($path) . '?v=' . (is_file($file) ? filemtime($file) : '1');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-footer.css'); ?>">
    <link rel="stylesheet" href="<?php echo $asset_url('assets/css/rms-system.css'); ?>">
</head>
<body class="system-page">
<div class="dashboard-app">
    <?php $this->load->view('admin/partials/sidebar', array(
        'sidebar_active' => 'system', 'sidebar_show_system' => TRUE,
        'initials' => $initials, 'safe_display_name' => $safe_name,
        'safe_position' => $safe_position, 'route_url' => $route_url
    )); ?>

    <main class="main-area">
        <?php $this->load->view('admin/partials/header', array(
            'header_kicker' => 'SYSTEM ADMINISTRATION',
            'header_title' => 'Access Logs',
            'header_description' => 'Review administrator and records activity captured by the legacy system.',
            'header_stat_label' => 'Log entries',
            'header_stat_value' => count($logs),
            'header_stat_icon' => 'time',
            'header_primary_url' => site_url('administrator/users/profile'),
            'header_primary_label' => 'Edit profile',
            'header_logout_url' => $route_url('logout', 'administrator/logout'),
            'initials' => $initials, 'safe_display_name' => $safe_name,
            'safe_position' => $safe_position
        )); ?>

        <div class="system-content">
            <!-- System navigation remains identical on every implemented page. -->
            <nav class="system-tabs" aria-label="System sections">
                <a href="<?php echo site_url('administrator/system'); ?>">Global Configuration</a>
                <a href="<?php echo site_url('administrator/system/file-types'); ?>">File Type Setting</a>
                <a class="active" href="<?php echo site_url('administrator/system/access-logs'); ?>">Access Logs</a>
                <a href="<?php echo site_url('administrator/system/backup'); ?>">Backup</a>
            </nav>

            <!-- Compact green sub-hero matches Users and the other System pages. -->

            <section class="access-log-panel">
                <header class="filetype-heading">
                    <div>
                        <p>ACTIVITY HISTORY</p>
                        <h2>System access records</h2>
                        <span>Entries are read directly from the two existing RMS log files.</span>
                    </div>
                    <button type="button" class="danger-action toolbar-danger" id="access-log-clear">
                        <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                        Delete all logs
                    </button>
                </header>

                <div class="filetype-toolbar access-log-toolbar">
                    <label class="table-search">
                        <span aria-hidden="true">⌕</span>
                        <input id="access-log-search" type="search" placeholder="Search username, date, or activity" autocomplete="off">
                    </label>
                    <label class="entries-control">Show
                        <select id="access-log-limit" aria-label="Entries per page">
                            <option value="10">10</option><option value="25">25</option>
                            <option value="50">50</option><option value="100">100</option>
                        </select> entries
                    </label>
                </div>

                <div class="filetype-table-wrap">
                    <table class="filetype-table access-log-table" id="access-log-table">
                        <thead><tr><th>Username / IP</th><th>Date</th><th>Activity</th><th>Source</th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $row): ?>
                            <tr data-log-row>
                                <td><strong><?php echo html_escape($row['identity']); ?></strong></td>
                                <td><?php echo html_escape($row['date']); ?></td>
                                <td><?php echo html_escape($row['activity']); ?></td>
                                <td><span class="source-pill"><?php echo html_escape($row['source']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="empty-row" id="access-log-empty"<?php echo count($logs) ? ' hidden' : ''; ?>>
                            <td colspan="4">No access logs are available.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <footer class="filetype-footer access-log-footer">
                    <span id="access-log-range">Showing 0 entries</span>
                    <div class="pagination-controls">
                        <button type="button" id="access-log-prev" aria-label="Previous page">‹</button>
                        <span id="access-log-page">Page 1 of 1</span>
                        <button type="button" id="access-log-next" aria-label="Next page">›</button>
                    </div>
                </footer>
            </section>
        </div>
    </main>
</div>

<!-- Locked confirmation: only Cancel and Delete close or complete it. -->
<div class="system-modal" id="access-log-confirm" hidden>
    <section class="system-modal-card confirm-card" role="alertdialog" aria-modal="true" aria-labelledby="access-log-confirm-title">
        <div class="confirm-icon">!</div>
        <h2 id="access-log-confirm-title">Delete all access logs?</h2>
        <p>This permanently empties both legacy RMS log files and cannot be undone.</p>
        <div class="confirm-actions">
            <button type="button" class="secondary-action" id="access-log-cancel">Cancel</button>
            <button type="button" class="danger-action" id="access-log-confirm-delete">Delete all</button>
        </div>
    </section>
</div>
<div class="system-toast" id="access-log-toast" hidden role="status"></div>

<script>window.RMS_ACCESS_LOGS = <?php echo json_encode(array(
    'clear' => site_url('administrator/system/access-logs/clear'),
    'csrfName' => $this->security->get_csrf_token_name(),
    'csrfHash' => $this->security->get_csrf_hash()
)); ?>;</script>
<script src="<?php echo base_url('assets/js/jquery-3.5.1.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/rms-header.js'); ?>"></script>
<script src="<?php echo $asset_url('assets/js/rms-access-logs.js'); ?>"></script>
</body>
</html>
