<?php
/*
 * Global Configuration view.
 *
 * This section prepares safe display values and small URL helpers before any
 * HTML is rendered. The closures keep route and asset handling in one place.
 */
$safe_name = isset($display_name) && $display_name !== ''
    ? $display_name
    : $username;
$safe_position = isset($position) && $position !== ''
    ? $position
    : 'Super User';
$safe_routes = is_array($routes) ? $routes : array();

// Resolve configured admin routes while retaining a safe fallback.
$route_url = function ($key, $fallback) use ($safe_routes) {
    $route = isset($safe_routes[$key]) ? $safe_routes[$key] : $fallback;
    return site_url($route);
};

// Build the two-letter avatar used by the shared sidebar and header.
$name_parts = preg_split('/\s+/', trim($safe_name));
$initials = isset($name_parts[0][0]) ? strtoupper($name_parts[0][0]) : 'A';

if (count($name_parts) > 1) {
    $last_name = $name_parts[count($name_parts) - 1];
    $initials .= strtoupper($last_name[0]);
}

// Add the file modification time to local assets to prevent stale caching.
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

    <!-- Shared admin styles. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-footer.css'); ?>">

    <!-- Global Configuration module styles. -->
    <link rel="stylesheet" href="<?php echo $asset_url('assets/css/rms-system.css'); ?>">
</head>
<body>
    <div class="dashboard-app">
        <?php
        // Render the shared sidebar with System marked as the active module.
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
            // Render the account header shared by every administration page.
            $this->load->view('admin/partials/header', array(
                'header_title' => 'Global Configuration',
                'header_primary_url' => site_url('administrator/users/profile'),
                'header_primary_label' => 'Edit profile',
                'header_logout_url' => $route_url('logout', 'administrator/logout'),
                'initials' => $initials,
                'safe_display_name' => $safe_name,
                'safe_position' => $safe_position
            ));
            ?>

            <div class="system-content">
                <!-- Page introduction. -->
                <section class="system-hero">
                    <div>
                        <span>SYSTEM ADMINISTRATION</span>
                        <h1>Global Configuration</h1>
                        <p>Control the core settings used by the records management system.</p>
                    </div>
                    <div class="system-hero-icon" aria-hidden="true">⚙</div>
                </section>

                <!-- System module navigation. Future modules remain labels until implemented. -->
                <nav class="system-tabs" aria-label="System sections">
                    <a class="active" href="<?php echo site_url('administrator/system'); ?>">
                        Global Configuration
                    </a>
                    <span>File Type Setting</span>
                    <span>Access Logs</span>
                    <span>Backup</span>
                </nav>

                <!-- Existing system_setting records. -->
                <section class="settings-panel">
                    <div class="settings-heading">
                        <div>
                            <p>CONFIGURATION</p>
                            <h2>System details</h2>
                            <span>Changes apply to the existing legacy settings table.</span>
                        </div>
                        <span class="settings-count"><?php echo count($settings); ?> settings</span>
                    </div>

                    <?php
                    echo form_open(
                        'administrator/system/save',
                        array('id' => 'system-settings-form', 'novalidate' => 'novalidate')
                    );
                    ?>

                    <div class="settings-grid">
                        <?php foreach ($settings as $row): ?>
                            <?php $setting_id = (int) $row['setting_id']; ?>
                            <div class="setting-field<?php echo $setting_id === 3 ? ' maintenance-field' : ''; ?>">
                                <label for="setting-<?php echo $setting_id; ?>">
                                    <span><?php echo html_escape($row['name']); ?></span>
                                    <small>#<?php echo $setting_id; ?></small>
                                </label>

                                <?php if ($setting_id === 3): ?>
                                    <!-- Setting 3 uses the legacy Yes/No maintenance values. -->
                                    <div class="mode-toggle" id="setting-3">
                                        <label>
                                            <input
                                                type="radio"
                                                name="settings[3]"
                                                value="Yes"
                                                <?php echo strcasecmp($row['value'], 'Yes') === 0 ? ' checked' : ''; ?>
                                            >
                                            <span>Yes</span>
                                        </label>
                                        <label>
                                            <input
                                                type="radio"
                                                name="settings[3]"
                                                value="No"
                                                <?php echo strcasecmp($row['value'], 'Yes') !== 0 ? ' checked' : ''; ?>
                                            >
                                            <span>No</span>
                                        </label>
                                    </div>
                                <?php else: ?>
                                    <!-- Settings 5 and 6 are numeric; all other values are text. -->
                                    <div class="input-wrap">
                                        <input
                                            id="setting-<?php echo $setting_id; ?>"
                                            type="<?php echo in_array($setting_id, array(5, 6), TRUE) ? 'number' : 'text'; ?>"
                                            name="settings[<?php echo $setting_id; ?>]"
                                            value="<?php echo html_escape($row['value']); ?>"
                                            maxlength="100"
                                            <?php echo in_array($setting_id, array(5, 6), TRUE) ? ' min="1" step="1"' : ''; ?>
                                            required
                                        >
                                        <?php if ($setting_id === 6): ?>
                                            <b>MB</b>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <p><?php echo html_escape(trim($row['description'], "() \t\n\r\0\x0B")); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- AJAX validation messages appear here. -->
                    <div class="settings-error" id="settings-error" role="alert"></div>

                    <footer class="settings-actions">
                        <a href="<?php echo $route_url('home', 'administrator/dashboard'); ?>" class="cancel">
                            Cancel
                        </a>
                        <button type="submit" id="settings-save">
                            <span>✓</span>
                            Update settings
                        </button>
                    </footer>

                    <?php echo form_close(); ?>
                </section>
            </div>
        </main>
    </div>

    <!-- Successful-save confirmation. -->
    <div class="system-message" id="system-message" hidden>
        <div class="message-card" role="alertdialog" aria-modal="true">
            <span class="message-mark">✓</span>
            <h2>Configuration updated</h2>
            <p id="system-message-text"></p>
            <button type="button" id="system-message-close">Done</button>
        </div>
    </div>

    <script>
        // Expose only the route and CSRF values required by this module.
        window.RMS_SYSTEM = <?php echo json_encode(array(
            'save' => site_url('administrator/system/save'),
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        )); ?>;
    </script>

    <!-- Shared scripts load before the module-specific behavior. -->
    <script src="<?php echo base_url('assets/js/jquery-3.5.1.min.js'); ?>"></script>
    <script src="<?php echo base_url('assets/js/rms-header.js'); ?>"></script>
    <script src="<?php echo $asset_url('assets/js/rms-system.js'); ?>"></script>
</body>
</html>
