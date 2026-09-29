<?php
/*
 * Edit Profile view
 *
 * Presents the signed-in account in the shared CI3 admin layout. Only the
 * complete name, username, and optional password are submitted for updating.
 */

// Prepare safe shared-layout values and configured route fallbacks.
$safe_display_name = $display_name !== '' ? $display_name : $username;
$safe_position = $position !== '' ? $position : 'Administrator';
$safe_routes = is_array($routes) ? $routes : array();
$route_url = function ($key, $fallback) use ($safe_routes) {
    return site_url(isset($safe_routes[$key]) ? $safe_routes[$key] : $fallback);
};

// Build the two-letter avatar used by the existing shared header.
$name_parts = preg_split('/\s+/', trim($safe_display_name));
$initials = isset($name_parts[0][0]) ? strtoupper($name_parts[0][0]) : 'A';
if (count($name_parts) > 1) {
    $last_name = $name_parts[count($name_parts) - 1];
    $initials .= isset($last_name[0]) ? strtoupper($last_name[0]) : '';
}

// Use file modification times so a normal refresh receives updated page assets.
$asset_url = function ($path) {
    $file = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $path);
    return base_url($path).'?v='.(is_file($file) ? filemtime($file) : '1');
};

// Keep submitted editable values after validation errors; read-only values use DB data.
$complete_name_value = set_value('cname', isset($profile['emp_name']) ? $profile['emp_name'] : '');
$username_value = set_value('username', isset($profile['username']) ? $profile['username'] : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>
    <!-- Shared application layout and isolated Edit Profile styling. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-sidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-footer.css'); ?>">
    <link rel="stylesheet" href="<?php echo $asset_url('assets/css/rms-profile.css'); ?>">
</head>
<body class="profile-page">
<div class="dashboard-app">
    <?php
    // Keep Users highlighted because Edit Profile belongs to account management.
    $this->load->view('admin/partials/sidebar', array(
        'sidebar_active' => 'users',
        'sidebar_show_system' => TRUE,
        'initials' => $initials,
        'safe_display_name' => $safe_display_name,
        'safe_position' => $safe_position,
        'route_url' => $route_url
    ));
    ?>

    <main class="main-area">
        <?php
        // The account menu stays available, while the top action returns to Dashboard.
        $this->load->view('admin/partials/header', array(
            'header_title' => 'Edit Profile',
            'header_action_url' => $route_url('home', 'administrator/dashboard'),
            'header_action_label' => 'Dashboard',
            'header_action_symbol' => "\xE2\x86\x90",
            'header_primary_url' => site_url('administrator/users/profile'),
            'header_primary_label' => 'Edit profile',
            'header_logout_url' => $route_url('logout', 'administrator/logout'),
            'initials' => $initials,
            'safe_display_name' => $safe_display_name,
            'safe_position' => $safe_position
        ));
        ?>

        <div class="profile-content">
            <!-- Compact green sub-hero follows the approved Users/System design. -->
            <header class="profile-hero">
                <div class="profile-hero-copy">
                    <span>ACCOUNT MANAGEMENT</span>
                    <h1>My account details</h1>
                    <p>Keep your name, username, and sign-in password current.</p>
                </div>
                <div class="profile-hero-stat" aria-label="Personal profile">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"></path>
                    </svg>
                    <span>Personal profile</span>
                </div>
            </header>

            <!-- Validation and database errors stay visible beside the form. -->
            <?php if ($profile_error !== ''): ?>
                <div class="profile-alert error" role="alert"><?php echo html_escape($profile_error); ?></div>
            <?php endif; ?>
            <?php if (validation_errors() !== ''): ?>
                <div class="profile-alert error" role="alert"><?php echo validation_errors('<p>', '</p>'); ?></div>
            <?php endif; ?>

            <section class="profile-panel" aria-labelledby="profile-form-title">
                <div class="profile-panel-heading">
                    <div>
                        <p>PROFILE INFORMATION</p>
                        <h2 id="profile-form-title">Account details</h2>
                        <span>Fields marked read-only are managed by your administrator.</span>
                    </div>
                    <span class="profile-id-badge">ID <?php echo (int) $profile['user_id']; ?></span>
                </div>

                <?php echo form_open('administrator/users/profile', array('class' => 'profile-form', 'id' => 'profile-form')); ?>
                    <div class="profile-grid">
                        <!-- Editable identity fields. -->
                        <div class="profile-field editable">
                            <label for="cname">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"></path></svg>
                                Complete name <i>*</i>
                            </label>
                            <input type="text" id="cname" name="cname" maxlength="150" value="<?php echo html_escape($complete_name_value); ?>" autocomplete="name" required>
                            <small>Letters, spaces, periods, apostrophes, and hyphens only.</small>
                        </div>

                        <div class="profile-field editable">
                            <label for="username">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16M7 16h10M9 12h6M8 4h8l2 4H6z"></path></svg>
                                Username <i>*</i>
                            </label>
                            <input type="text" id="username" name="username" maxlength="25" value="<?php echo html_escape($username_value); ?>" autocomplete="username" required>
                            <small>Used when signing in to the Records Management System.</small>
                        </div>

                        <div class="profile-field editable profile-field-wide">
                            <label for="password">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"></path></svg>
                                New password
                            </label>
                            <div class="password-input">
                                <input type="password" id="password" name="password" maxlength="50" autocomplete="new-password" placeholder="Leave blank to keep your current password">
                                <button type="button" id="toggle-profile-password" aria-label="Show password" aria-pressed="false">
                                    <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                                </button>
                            </div>
                            <small>Use at least 6 characters, or leave this field empty if unchanged.</small>
                        </div>

                        <!-- Read-only organizational and account history fields. -->
                        <?php
                        $readonly_fields = array(
                            array('label' => 'Subsidiary', 'value' => $profile['sub_name'], 'icon' => 'building'),
                            array('label' => 'Department', 'value' => $profile['dept_name'], 'icon' => 'department'),
                            array('label' => 'User level', 'value' => $profile['role_title'], 'icon' => 'shield'),
                            array('label' => 'Registration date', 'value' => $profile['date_registered'], 'icon' => 'calendar'),
                            array('label' => 'Last visit date', 'value' => $profile['last_date_visit'] !== '' ? $profile['last_date_visit'] : 'No visit recorded', 'icon' => 'clock')
                        );
                        foreach ($readonly_fields as $index => $field):
                        ?>
                            <div class="profile-field readonly<?php echo $index === 4 ? ' profile-field-wide' : ''; ?>">
                                <label for="profile-readonly-<?php echo $index; ?>">
                                    <span class="field-icon <?php echo html_escape($field['icon']); ?>" aria-hidden="true"></span>
                                    <?php echo html_escape($field['label']); ?>
                                    <em>Read only</em>
                                </label>
                                <input type="text" id="profile-readonly-<?php echo $index; ?>" value="<?php echo html_escape($field['value']); ?>" readonly tabindex="-1">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <footer class="profile-actions">
                        <a href="<?php echo $route_url('home', 'administrator/dashboard'); ?>" class="profile-cancel">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
                            Cancel
                        </a>
                        <button type="submit" class="profile-save">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 20v-6h8v6"></path></svg>
                            Update my account
                        </button>
                    </footer>
                <?php echo form_close(); ?>
            </section>
        </div>

        <!-- Confirmation appears before any account information is submitted. -->
        <div class="profile-modal" id="profile-confirm-modal" aria-hidden="true">
            <div class="profile-modal-backdrop"></div>
            <section class="profile-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-confirm-title">
                <div class="profile-modal-icon confirm" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 20v-6h8v6"></path></svg>
                </div>
                <p class="profile-modal-eyebrow">CONFIRM UPDATE</p>
                <h2 id="profile-confirm-title">Update your account?</h2>
                <p>Your complete name, username, and new password (if entered) will be saved.</p>
                <div class="profile-modal-actions">
                    <button type="button" class="profile-modal-secondary" id="profile-confirm-cancel">Cancel</button>
                    <button type="button" class="profile-modal-primary" id="profile-confirm-save">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 20v-6h8v6"></path></svg>
                        <span>Yes, update account</span>
                    </button>
                </div>
            </section>
        </div>

        <!-- This modal is rendered only after the server confirms a successful update. -->
        <?php if ($profile_success !== ''): ?>
            <div class="profile-modal is-open" id="profile-success-modal" aria-hidden="false">
                <div class="profile-modal-backdrop"></div>
                <section class="profile-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-success-title">
                    <div class="profile-modal-icon success" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></svg>
                    </div>
                    <p class="profile-modal-eyebrow">UPDATE COMPLETE</p>
                    <h2 id="profile-success-title">Account updated successfully</h2>
                    <p><?php echo html_escape($profile_success); ?></p>
                    <div class="profile-modal-actions single">
                        <button type="button" class="profile-modal-primary" id="profile-success-close">Done</button>
                    </div>
                </section>
            </div>
        <?php endif; ?>

        <?php
        // Reuse the same display-only footer as the other converted pages.
        $this->load->view('admin/partials/footer', array(
            'footer_company_name' => $company_name,
            'footer_system_name' => 'Records Management System'
        ));
        ?>
    </main>
</div>

<!-- Shared layout behavior plus profile-only password visibility. -->
<script src="<?php echo base_url('assets/js/rms-sidebar.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/rms-header.js'); ?>"></script>
<script src="<?php echo $asset_url('assets/js/rms-profile.js'); ?>"></script>
</body>
</html>
