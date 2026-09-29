<?php
/*
 * New User view
 *
 * Displays the account form and existing role/subsidiary/department choices.
 * The controller performs validation; the model performs the database queries.
 */

// Prepare safe profile values for the shared admin layout.
$safe_display_name = isset($display_name) && $display_name !== ''
    ? $display_name
    : (isset($username) && $username !== '' ? $username : 'Administrator');
$safe_position = isset($position) && $position !== '' ? $position : 'Administrator';
$safe_routes = isset($routes) && is_array($routes) ? $routes : array();

// Resolve configured routes with safe CI3 fallback routes.
$route_url = function ($key, $fallback) use ($safe_routes) {
    $route = isset($safe_routes[$key]) && $safe_routes[$key] !== ''
        ? $safe_routes[$key]
        : $fallback;
    return site_url($route);
};

// Build initials for the sidebar profile avatar.
$name_parts = preg_split('/\s+/', trim($safe_display_name));
$initials = isset($name_parts[0][0]) ? strtoupper($name_parts[0][0]) : '';

if (count($name_parts) > 1) {
    $last_part = $name_parts[count($name_parts) - 1];
    $initials .= isset($last_part[0]) ? strtoupper($last_part[0]) : '';
}

if ($initials === '') {
    $initials = 'A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>
    <!-- Shared layout plus isolated sidebar, header, and Users styles. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-dashboard.css?v=20260731-2'); ?>">
    <!-- Shared sidebar styles are isolated from page content styles. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-sidebar.css?v=20260731-2'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css?v=20260731-1'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-users.css?v=20260730-3'); ?>">
</head>
<body>
    <div class="dashboard-app">
        <?php
        /*
         * Load the reusable sidebar with the original reduced New User menu.
         * Form validation and the users INSERT process remain unchanged.
         */
        $this->load->view('admin/partials/sidebar', array(
            'sidebar_active'         => 'users',
            'sidebar_show_documents' => FALSE,
            'initials'                => $initials,
            'safe_display_name'       => $safe_display_name,
            'safe_position'           => $safe_position,
            'route_url'               => $route_url
        ));
        ?>

        <!-- Main New User page. -->
        <main class="main-area">
            <?php
            /*
             * Load the shared header with the original New User return action.
             * The form, validation, and INSERT process remain unchanged.
             */
            $this->load->view('admin/partials/header', array(
                'header_title'          => 'New User',
                'header_action_url'     => site_url('users'),
                'header_action_label'   => 'Manage users',
                'header_action_symbol'  => "\xE2\x86\x90",
                'header_show_user_menu' => FALSE,
                'initials'              => $initials,
                'safe_display_name'     => $safe_display_name,
                'safe_position'         => $safe_position
            ));
            ?>

            <div class="users-content">
                <!-- Explains which Users migration step is currently active. -->
                <section class="users-hero users-create-hero">
                    <div>
                        <span class="users-eyebrow">USERS MODULE · NEW USER</span>
                        <h2>Create a user account</h2>
                        <p>The old account-creation process in the new RMS interface.</p>
                    </div>
                </section>

                <!-- Flash message appears once after a successful INSERT and redirect. -->
                <?php if (is_array($created_account)): ?>
                    <section class="account-created" role="status">
                        <div>
                            <strong>User successfully added.</strong>
                            <p>Give these account details only to the new user. The password is shown once.</p>
                        </div>
                        <dl>
                            <div><dt>Username</dt><dd><?php echo html_escape($created_account['username']); ?></dd></div>
                            <div><dt>Password</dt><dd><?php echo html_escape($created_account['password']); ?></dd></div>
                        </dl>
                    </section>
                <?php endif; ?>

                <!-- Account form populated from existing RMS lookup tables. -->
                <section class="users-panel user-form-panel" aria-labelledby="new-user-title">
                    <div class="users-panel-heading">
                        <div>
                            <p>ACCOUNT DETAILS</p>
                            <h2 id="new-user-title">Add new user</h2>
                        </div>
                        <span class="required-note"><i>*</i> Required fields</span>
                    </div>

                    <!-- Server-side business-rule and field-validation messages. -->
                    <?php if ($error_message !== ''): ?>
                        <div class="form-alert" role="alert"><?php echo html_escape($error_message); ?></div>
                    <?php endif; ?>
                    <?php if (validation_errors() !== ''): ?>
                        <div class="form-alert" role="alert"><?php echo validation_errors('<p>', '</p>'); ?></div>
                    <?php endif; ?>

                    <!-- CI3 form helper includes the configured form action and CSRF field. -->
                    <?php echo form_open('users/create', array('class' => 'user-create-form', 'id' => 'user-create-form')); ?>
                        <div class="form-grid">
                            <div class="form-field form-field-wide">
                                <label for="cname">Complete name <i>*</i></label>
                                <input type="text" id="cname" name="cname" maxlength="150"
                                    value="<?php echo set_value('cname'); ?>" autocomplete="name" required>
                                <small>Letters, spaces, periods, apostrophes, and hyphens only.</small>
                            </div>

                            <div class="form-field">
                                <label for="username">Username <i>*</i></label>
                                <input type="text" id="username" name="username" maxlength="25"
                                    value="<?php echo set_value('username'); ?>" autocomplete="off" required>
                            </div>

                            <div class="form-field">
                                <label for="password">Password <i>*</i></label>
                                <div class="password-row">
                                    <input type="text" id="password" name="password" maxlength="50"
                                        value="<?php echo set_value('password'); ?>"
                                        autocomplete="new-password" readonly required>
                                    <button type="button" id="generate-password">Generate</button>
                                </div>
                                <small>Generate a password before saving.</small>
                            </div>

                            <div class="form-field">
                                <label for="subsidiary">Subsidiary <i>*</i></label>
                                <!-- Options come from the existing subsidiaries table. -->
                                <select id="subsidiary" name="subsidiary" required>
                                    <option value="">Select subsidiary</option>
                                    <?php foreach ($subsidiaries as $subsidiary): ?>
                                        <option value="<?php echo (int) $subsidiary['sub_id']; ?>"
                                            <?php echo set_select('subsidiary', $subsidiary['sub_id']); ?>>
                                            <?php echo html_escape($subsidiary['sub_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="department">Department <i>*</i></label>
                                <!-- JavaScript filters these existing departments by data-sub-id. -->
                                <select id="department" name="department" required disabled>
                                    <option value="">Select subsidiary first</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo (int) $department['dept_id']; ?>"
                                            data-sub-id="<?php echo (int) $department['sub_id']; ?>"
                                            <?php echo set_select('department', $department['dept_id']); ?>>
                                            <?php echo html_escape($department['dept_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="role">User level <i>*</i></label>
                                <!-- Options contain only roles the logged-in manager may assign. -->
                                <select id="role" name="role" required>
                                    <option value="">Select user level</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo (int) $role['role_id']; ?>"
                                            <?php echo set_select('role', $role['role_id']); ?>>
                                            <?php echo html_escape($role['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-field uploader-field">
                                <span class="field-label">Upload permission</span>
                                <label class="toggle-option" for="al_upload">
                                    <input type="checkbox" id="al_upload" name="al_upload" value="1"
                                        <?php echo set_checkbox('al_upload', '1'); ?>>
                                    <span aria-hidden="true"></span>
                                    Allow this user to upload
                                </label>
                            </div>
                        </div>

                        <!-- Cancel is read-only; Save submits for server validation and confirmation. -->
                        <div class="form-actions">
                            <a href="<?php echo site_url('users'); ?>" class="form-cancel">Cancel</a>
                            <button type="submit" class="form-save">Save user</button>
                        </div>
                    <?php echo form_close(); ?>

                    <div class="migration-scope-note">
                        This step creates the account only. Viewer-folder permissions remain locked until their separate migration step.
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Keep shared sidebar, header, and Users behavior in separate files. -->
    <script src="<?php echo base_url('assets/js/rms-sidebar.js?v=20260731-2'); ?>"></script>
    <script src="<?php echo base_url('assets/js/rms-header.js?v=20260731-1'); ?>"></script>
    <script src="<?php echo base_url('assets/js/rms-users.js?v=20260730-3'); ?>"></script>
</body>
</html>
