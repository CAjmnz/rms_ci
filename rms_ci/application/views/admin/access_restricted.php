<?php
/*
 * Shared Super User restriction page
 *
 * This view keeps denied requests inside the normal ALTURAS Admin layout.
 * Authorization is enforced by the controller before this view is loaded.
 */
$safe_name = isset($display_name) && $display_name !== ''
    ? $display_name
    : (isset($username) ? $username : 'Administrator');
$safe_position = isset($position) && $position !== ''
    ? $position
    : 'Administrator';
$safe_module = isset($module_name) && $module_name !== ''
    ? $module_name
    : 'this module';
$safe_routes = isset($routes) && is_array($routes) ? $routes : array();

// Resolve configured Admin URLs without trusting user-supplied values.
$route_url = function ($key, $fallback) use ($safe_routes) {
    return site_url(isset($safe_routes[$key]) ? $safe_routes[$key] : $fallback);
};

// Build the same avatar initials used throughout the Admin Portal.
$name_parts = preg_split('/\s+/', trim($safe_name));
$initials = isset($name_parts[0][0]) ? strtoupper($name_parts[0][0]) : 'A';
if (count($name_parts) > 1) {
    $last_name = $name_parts[count($name_parts) - 1];
    $initials .= isset($last_name[0]) ? strtoupper($last_name[0]) : '';
}

$active_module = strtolower($safe_module);
$active_module = $active_module === 'system' ? 'system' : $active_module;

// Refresh this page's stylesheet automatically after future replacements.
$css_path = FCPATH . 'assets/css/rms-access-restricted.css';
$css_version = is_file($css_path) ? filemtime($css_path) : '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>

    <!-- Existing shared Admin design. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-footer.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-access-restricted.css?v=' . $css_version); ?>">
</head>
<body>
<div class="dashboard-app">
    <?php
    $this->load->view('admin/partials/sidebar', array(
        'sidebar_active' => $active_module,
        'sidebar_show_documents' => TRUE,
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
            'header_title' => 'Access Restricted',
            'header_kicker' => 'ADMIN CONTROL PANEL',
            'header_primary_url' => $route_url('edit_profile', 'administrator/users/profile'),
            'header_primary_label' => 'Edit profile',
            'header_logout_url' => $route_url('logout', 'administrator/logout'),
            'initials' => $initials,
            'safe_display_name' => $safe_name,
            'safe_position' => $safe_position
        ));
        ?>

        <div class="restriction-content">
            <section class="restriction-hero" aria-labelledby="restriction-title">
                <div class="restriction-hero-copy">
                    <span>SECURE ADMINISTRATION</span>
                    <h1 id="restriction-title">Access Restricted</h1>
                    <p>This protected area is available only to authorized Super Users.</p>
                </div>

                <span class="restriction-status" aria-label="HTTP status 403">
                    <strong>403</strong>
                    <small>Restricted</small>
                </span>
            </section>

            <section class="restriction-card">
                <div class="restriction-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"></path>
                    </svg>
                </div>

                <p class="restriction-eyebrow">PERMISSION REQUIRED</p>
                <h2><?php echo html_escape($safe_module); ?> is protected</h2>
                <p class="restriction-message">
                    Your account is signed in, but it does not have permission to open this module.
                    In accordance with the legacy RMS policy, only a Super User can manage
                    Subsidiaries, Departments, and System settings.
                </p>

                <div class="restriction-note">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 10v6M12 7h.01"></path>
                    </svg>
                    <span>If you need access, please contact your Super User or system administrator.</span>
                </div>

                <div class="restriction-actions">
                    <a class="restriction-button primary" href="<?php echo $route_url('home', 'administrator/dashboard'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M3 11.2 12 4l9 7.2v8.3a.5.5 0 0 1-.5.5H15v-6H9v6H3.5a.5.5 0 0 1-.5-.5z"></path>
                        </svg>
                        Return to Dashboard
                    </a>

                    <a class="restriction-button secondary" href="<?php echo $route_url('manage_users', 'administrator/users'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="9" cy="8" r="4"></circle>
                            <path d="M3 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M17 11h4M19 9v4"></path>
                        </svg>
                        Go to Users
                    </a>
                </div>
            </section>
        </div>

        <?php $this->load->view('admin/partials/footer'); ?>
    </main>
</div>

<!-- Existing shared navigation and account-menu behavior. -->
<script src="<?php echo base_url('assets/js/rms-header.js'); ?>"></script>
</body>
</html>
