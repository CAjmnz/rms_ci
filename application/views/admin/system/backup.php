<?php
/* Prepare shared account, route, and cache-safe asset values. */
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
$backup_error = $this->session->flashdata('backup_error');
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
            'header_title' => 'Backup',
            'header_description' => 'Download a secure copy of the RMS database or the complete application.',
            'header_stat_label' => 'Backup options',
            'header_stat_value' => '2',
            'header_stat_icon' => 'backup',
            'header_primary_url' => site_url('administrator/users/profile'),
            'header_primary_label' => 'Edit profile',
            'header_logout_url' => $route_url('logout', 'administrator/logout'),
            'initials' => $initials, 'safe_display_name' => $safe_name,
            'safe_position' => $safe_position
        )); ?>

        <div class="system-content">
            <!-- Keep System navigation identical across all four pages. -->
            <nav class="system-tabs" aria-label="System sections">
                <a href="<?php echo site_url('administrator/system'); ?>">Global Configuration</a>
                <a href="<?php echo site_url('administrator/system/file-types'); ?>">File Type Setting</a>
                <a href="<?php echo site_url('administrator/system/access-logs'); ?>">Access Logs</a>
                <a class="active" href="<?php echo site_url('administrator/system/backup'); ?>">Backup</a>
            </nav>

            <!-- Compact Users-style sub-hero. -->

            <section class="backup-panel">
                <header class="filetype-heading">
                    <div>
                        <p>BACKUP OPTIONS</p>
                        <h2>Choose what to download</h2>
                        <span>The download is generated only after you confirm your selection.</span>
                    </div>
                </header>

                <?php echo form_open('administrator/system/backup/download', array('id' => 'backup-form')); ?>
                    <div class="backup-options">
                        <label class="backup-option">
                            <input type="radio" name="backup_mode" value="database">
                            <span class="backup-radio" aria-hidden="true"></span>
                            <span class="backup-option-icon database-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg>
                            </span>
                            <span class="backup-option-copy">
                                <strong>Database only</strong>
                                <small>Download all RMS tables and records as one SQL file.</small>
                                <em>.SQL FILE</em>
                            </span>
                        </label>

                        <label class="backup-option<?php echo $zip_available ? '' : ' unavailable'; ?>">
                            <input type="radio" name="backup_mode" value="system_database"<?php echo $zip_available ? '' : ' disabled'; ?>>
                            <span class="backup-radio" aria-hidden="true"></span>
                            <span class="backup-option-icon system-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h7l2 2h9v10H3zM3 7V5h7l2 2"/></svg>
                            </span>
                            <span class="backup-option-copy">
                                <strong>System and database</strong>
                                <small>Download the CI3 application files together with the SQL backup.</small>
                                <em><?php echo $zip_available ? '.ZIP FILE' : 'PHP ZIP EXTENSION REQUIRED'; ?></em>
                            </span>
                        </label>
                    </div>

                    <?php if ($backup_error): ?>
                        <div class="backup-alert" role="alert"><?php echo html_escape($backup_error); ?></div>
                    <?php endif; ?>
                    <div class="backup-alert" id="backup-client-error" hidden role="alert"></div>

                    <footer class="settings-actions backup-actions">
                        <a href="<?php echo $route_url('home', 'administrator/dashboard'); ?>" class="cancel">Cancel</a>
                        <button type="button" id="backup-open-confirm">
                            <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                            Create backup
                        </button>
                    </footer>
                <?php echo form_close(); ?>
            </section>
        </div>
    </main>
</div>

<!-- Locked confirmation: backdrop clicks and Escape do not close it. -->
<div class="system-modal" id="backup-confirm" hidden>
    <section class="system-modal-card backup-confirm-card" role="alertdialog" aria-modal="true" aria-labelledby="backup-confirm-title">
        <button type="button" class="modal-close-box" id="backup-confirm-close" aria-label="Close">×</button>
        <span class="backup-confirm-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 19h14"/></svg>
        </span>
        <p>CREATE BACKUP</p>
        <h2 id="backup-confirm-title">Prepare this download?</h2>
        <span id="backup-confirm-message">The server will generate your selected backup.</span>
        <div class="confirm-actions">
            <button type="button" class="secondary-action" id="backup-confirm-cancel">Cancel</button>
            <button type="button" class="primary-action" id="backup-confirm-download">Create and download</button>
        </div>
    </section>
</div>

<script src="<?php echo base_url('assets/js/jquery-3.5.1.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/rms-header.js'); ?>"></script>
<script src="<?php echo $asset_url('assets/js/rms-backup.js'); ?>"></script>
</body>
</html>
