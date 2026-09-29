<?php
/*
 * Manage Users view
 *
 * Displays the joined Users directory, search, sorting, pagination, migration
 * toolbar, New User modal, and Edit User modal. Database output is escaped.
 */

// Prepare safe profile fallbacks used in the sidebar and top bar.
$safe_display_name = isset($display_name) && $display_name !== ''
    ? $display_name
    : (isset($username) && $username !== '' ? $username : 'Administrator');
$safe_position = isset($position) && $position !== '' ? $position : 'Administrator';
$safe_routes = isset($routes) && is_array($routes) ? $routes : array();
$delete_success_message = (string) $this->session->flashdata('rms_user_delete_success');

// Read the shared success result used by all completed toolbar actions.
$toolbar_success = $this->session->flashdata('rms_user_action_success');
$toolbar_success_title = '';
$toolbar_success_message = '';

if (is_array($toolbar_success)) {
    $toolbar_success_title = isset($toolbar_success['title'])
        ? (string) $toolbar_success['title']
        : 'Updated successfully';
    $toolbar_success_message = isset($toolbar_success['message'])
        ? (string) $toolbar_success['message']
        : 'The selected user was updated successfully.';
}

// Resolve a configured dashboard route, with a known CI3 fallback route.
$route_url = function ($key, $fallback) use ($safe_routes) {
    $route = isset($safe_routes[$key]) && $safe_routes[$key] !== ''
        ? $safe_routes[$key]
        : $fallback;

    return site_url($route);
};

// Build short initials for the profile avatar.
$name_parts = preg_split('/\s+/', trim($safe_display_name));
$initials = '';

if (isset($name_parts[0][0])) {
    $initials .= strtoupper($name_parts[0][0]);
}

if (count($name_parts) > 1) {
    $last_part = $name_parts[count($name_parts) - 1];
    if (isset($last_part[0])) {
        $initials .= strtoupper($last_part[0]);
    }
}

if ($initials === '') {
    $initials = 'A';
}

// Build pagination/filter URLs while preserving the current list settings.
$list_url = function ($changes) use ($search, $sort, $direction, $per_page) {
    $query = array(
        'search'    => $search,
        'sort'      => $sort,
        'direction' => $direction,
        'per_page'  => $per_page,
        'page'      => 1
    );

    foreach ($changes as $key => $value) {
        $query[$key] = $value;
    }

    if ($query['search'] === '') {
        unset($query['search']);
    }

    return site_url('administrator/users').'?'.http_build_query($query);
};

// Toggle ASC/DESC when the user clicks a sortable column heading.
$sort_url = function ($column) use ($list_url, $sort, $direction) {
    return $list_url(array(
        'sort' => $column,
        'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc'
    ));
};
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
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-sidebar.css?v=20260803-2'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css?v=20260731-1'); ?>">
    <!-- Reusable footer styles; no header or sidebar stylesheet is repeated. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-footer.css?v=20260803-1'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-users.css?v=20260812-1'); ?>">
</head>
<body>
    <div class="dashboard-app">
        <?php
        /*
         * Load the reusable sidebar with Users highlighted.
         * The joined Users query and pagination remain unchanged.
         */
        $this->load->view('admin/partials/sidebar', array(
            'sidebar_active'       => 'users',
            // Keep the System navigation entry visible on the Users page.
            'sidebar_show_system'  => TRUE,
            'initials'            => $initials,
            'safe_display_name'   => $safe_display_name,
            'safe_position'       => $safe_position,
            'route_url'           => $route_url
        ));
        ?>

        <!-- Main Users page area. -->
        <main class="main-area">
            <?php
            /*
             * Load the same header design with Users-specific title and links.
             * No Users query or list behavior is changed by this partial.
             */
            $this->load->view('admin/partials/header', array(
                'header_kicker'        => 'MANAGE USERS',
                'header_title'         => 'Users',
                'header_description'   => 'The old Users directory process, presented in the new RMS interface.',
                'header_stat_label'    => 'Matching users',
                'header_stat_value'    => number_format((int) $total_users),
                'header_stat_id'       => 'users-matching-total',
                'header_stat_icon'     => 'records',
                'header_action_url'    => $route_url('home', 'dashboard'),
                'header_action_label'  => 'Dashboard',
                'header_action_symbol' => "\xE2\x86\x90",
                'header_primary_url'   => $route_url('home', 'dashboard'),
                'header_primary_label' => 'Dashboard',
                'header_logout_url'    => $route_url('logout', 'logout'),
                'initials'             => $initials,
                'safe_display_name'    => $safe_display_name,
                'safe_position'        => $safe_position
            ));
            ?>

            <div class="users-content">
                <!-- Summary banner showing the number of records matching the query. -->

                <!-- Users list card: search, actions, table, and pagination. -->
                <section class="users-panel" aria-labelledby="users-list-title">
                    <?php if ($this->session->flashdata('rms_user_status_success')): ?>
                        <!-- One-time confirmation after Block or Unblock succeeds. -->
                        <div class="users-notice users-notice-success" role="status">
                            <?php echo html_escape($this->session->flashdata('rms_user_status_success')); ?>
                        </div>
                    <?php elseif ($this->session->flashdata('rms_user_status_error')): ?>
                        <!-- Protected accounts and failed updates are reported safely. -->
                        <div class="users-notice users-notice-error" role="alert">
                            <?php echo html_escape($this->session->flashdata('rms_user_status_error')); ?>
                        </div>
                    <?php elseif ($this->session->flashdata('rms_access_success')): ?>
                        <!-- One-time confirmation after a successful access update. -->
                        <div class="users-notice users-notice-success" role="status">
                            <?php echo html_escape($this->session->flashdata('rms_access_success')); ?>
                        </div>
                    <?php elseif ($this->session->flashdata('rms_access_error')): ?>
                        <!-- One-time error; the model keeps or restores the old access rows. -->
                        <div class="users-notice users-notice-error" role="alert">
                            <?php echo html_escape($this->session->flashdata('rms_access_error')); ?>
                        </div>
                    <?php endif; ?>
                    <div class="users-panel-heading">
                        <div>
                            <p>USERS MODULE · LIST STEP</p>
                            <h2 id="users-list-title">Manage users</h2>
                        </div>
                        <form class="users-filter" action="<?php echo site_url('administrator/users'); ?>" method="get">
                            <span aria-hidden="true"></span>
                            <input
                                type="search"
                                name="search"
                                id="users-filter"
                                value="<?php echo html_escape($search); ?>"
                                placeholder="Search all users"
                                autocomplete="off"
                            >
                            <input type="hidden" name="sort" value="<?php echo html_escape($sort); ?>">
                            <input type="hidden" name="direction" value="<?php echo html_escape($direction); ?>">
                            <input type="hidden" name="per_page" value="<?php echo (int) $per_page; ?>">
                            <button type="submit">Search</button>
                            <?php if ($search !== ''): ?>
                                <a href="<?php echo site_url('administrator/users'); ?>">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- New User and single-selection Edit now open responsive modals. -->
                    <div class="users-toolbar" aria-label="User actions">
                        <?php if ($can_create_user): ?>
                            <button
                                type="button"
                                class="toolbar-button toolbar-primary"
                                id="open-user-create-modal"
                                aria-controls="user-create-modal"
                                aria-haspopup="dialog"
                            >
                                <span aria-hidden="true">+</span> New user
                            </button>
                        <?php else: ?>
                            <button type="button" class="toolbar-button" disabled>+ New user</button>
                        <?php endif; ?>
                        <?php if ($can_create_user): ?>
                            <!-- Edit menu preserves the old Edit Info/Edit Access choices. -->
                            <div class="toolbar-edit" id="user-edit-menu">
                                <button
                                    type="button"
                                    class="toolbar-button toolbar-edit-toggle"
                                    id="toggle-user-edit-menu"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    disabled
                                >
                                    <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M4 17.25V20h2.75L17.81 8.94l-2.75-2.75L4 17.25zm15.71-10.42a1 1 0 0 0 0-1.42l-1.12-1.12a1 1 0 0 0-1.42 0l-.88.88 2.75 2.75.67-.67z"></path>
                                    </svg>
                                    <span class="toolbar-action-label">Edit</span>
                                    <i aria-hidden="true"></i>
                                </button>
                                <div class="toolbar-edit-options" id="user-edit-options" hidden>
                                    <button
                                        type="button"
                                        id="open-user-edit-modal"
                                        data-edit-base="<?php echo site_url('administrator/users/edit'); ?>"
                                    >
                                        <strong>Edit Info</strong>
                                        <small>Account and profile details</small>
                                    </button>
                                    <button
                                        type="button"
                                        id="open-user-access-modal"
                                        data-access-base="<?php echo site_url('administrator/users/access'); ?>"
                                    >
                                        <strong>Edit Access</strong>
                                        <small>Files and related folders</small>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <button type="button" class="toolbar-button" disabled>Edit</button>
                        <?php endif; ?>
                        <?php if ($can_create_user): ?>
                            <?php echo form_open('administrator/users/logout', array(
                                'class' => 'toolbar-logout-form',
                                'id'    => 'user-logout-form'
                            )); ?>
                                <input type="hidden" name="user_id" id="logout-user-id" value="">
                                <button
                                    type="submit"
                                    class="toolbar-button toolbar-logout-action"
                                    id="logout-user-action"
                                    disabled
                                >
                                    <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M10 17v2H5V5h5v2H7v10h3zm5.59-10.41L17 8l-3 3H9v2h5l3 3-1.41 1.41L10.17 12l5.42-5.41z"></path>
                                    </svg>
                                    <span class="toolbar-action-label">Logout</span>
                                </button>
                            <?php echo form_close(); ?>
                        <?php else: ?>
                            <button type="button" class="toolbar-button" disabled>Logout</button>
                        <?php endif; ?>
                        <?php if ($can_create_user): ?>
                            <?php echo form_open('administrator/users/delete', array(
                                'class' => 'toolbar-delete-form',
                                'id'    => 'user-delete-form'
                            )); ?>
                                <input type="hidden" name="user_id" id="delete-user-id" value="">
                                <button
                                    type="submit"
                                    class="toolbar-button toolbar-danger toolbar-delete-action"
                                    id="delete-user-action"
                                    disabled
                                >
                                    <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M7 20a2 2 0 0 1-2-2V7h14v11a2 2 0 0 1-2 2H7zm1-3h2V10H8v7zm4 0h2V10h-2v7zm4 0h1V10h-1v7zM4 6V4h5l1-1h4l1 1h5v2H4z"></path>
                                    </svg>
                                    <span class="toolbar-action-label">Delete</span>
                                </button>
                            <?php echo form_close(); ?>
                        <?php else: ?>
                            <button type="button" class="toolbar-button toolbar-danger toolbar-delete-action" disabled>
                                <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M7 20a2 2 0 0 1-2-2V7h14v11a2 2 0 0 1-2 2H7zm1-3h2V10H8v7zm4 0h2V10h-2v7zm4 0h1V10h-1v7zM4 6V4h5l1-1h4l1 1h5v2H4z"></path>
                                </svg>
                                <span class="toolbar-action-label">Delete</span>
                            </button>
                        <?php endif; ?>
                        <?php if ($can_create_user): ?>
                            <!-- JavaScript supplies the one selected ID; CI adds CSRF when enabled. -->
                            <?php echo form_open('administrator/users/block', array(
                                'class' => 'toolbar-status-form',
                                'id'    => 'user-block-form'
                            )); ?>
                                <input type="hidden" name="user_id" id="block-user-id" value="">
                                <button
                                    type="submit"
                                    class="toolbar-button toolbar-status-action"
                                    id="toggle-user-block"
                                    disabled
                                >
                                    <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M12 2a5 5 0 0 1 5 5v2h1a2 2 0 0 1 2 2v9H4v-9a2 2 0 0 1 2-2h1V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v2h6V7a3 3 0 0 0-3-3zm-1 9v4h2v-4h-2z"></path>
                                    </svg>
                                    <span class="toolbar-action-label" id="block-action-label">Block</span>
                                </button>
                            <?php echo form_close(); ?>
                        <?php else: ?>
                            <button type="button" class="toolbar-button toolbar-status-action" disabled>
                                <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 2a5 5 0 0 1 5 5v2h1a2 2 0 0 1 2 2v9H4v-9a2 2 0 0 1 2-2h1V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v2h6V7a3 3 0 0 0-3-3zm-1 9v4h2v-4h-2z"></path>
                                </svg>
                                <span class="toolbar-action-label">Block</span>
                            </button>
                        <?php endif; ?>
                        <?php if ($can_create_user): ?>
                            <!-- Viewer keeps the old Level 1/Level 2 role mapping. -->
                            <?php echo form_open('administrator/users/viewer', array(
                                'class' => 'toolbar-viewer-form',
                                'id'    => 'user-viewer-form'
                            )); ?>
                                <input type="hidden" name="user_id" id="viewer-user-id" value="">
                                <input type="hidden" name="viewer_level" id="viewer-level" value="">
                                <div class="toolbar-viewer" id="user-viewer-menu">
                                    <button
                                        type="button"
                                        class="toolbar-button toolbar-viewer-toggle"
                                        id="toggle-user-viewer-menu"
                                        aria-haspopup="true"
                                        aria-expanded="false"
                                        disabled
                                    >
                                        <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M12 5c-5.5 0-9.6 5.2-9.8 5.4a2.5 2.5 0 0 0 0 3.2C2.4 13.8 6.5 19 12 19s9.6-5.2 9.8-5.4a2.5 2.5 0 0 0 0-3.2C21.6 10.2 17.5 5 12 5zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"></path>
                                        </svg>
                                        <span class="toolbar-action-label">Viewer</span>
                                        <i aria-hidden="true"></i>
                                    </button>
                                    <div class="toolbar-viewer-options" id="user-viewer-options" hidden>
                                        <button type="button" data-viewer-level="1">
                                            <strong>Level 1</strong>
                                            <small>View only</small>
                                        </button>
                                        <button type="button" data-viewer-level="2">
                                            <strong>Level 2</strong>
                                            <small>View and download</small>
                                        </button>
                                    </div>
                                </div>
                            <?php echo form_close(); ?>
                        <?php else: ?>
                            <button type="button" class="toolbar-button" disabled>Viewer</button>
                        <?php endif; ?>
                        <?php if ($can_create_user): ?>
                            <!-- Uploader keeps the old allowed_upload Yes/No mapping. -->
                            <?php echo form_open('administrator/users/uploader', array(
                                'class' => 'toolbar-uploader-form',
                                'id'    => 'user-uploader-form'
                            )); ?>
                                <input type="hidden" name="user_id" id="uploader-user-id" value="">
                                <input type="hidden" name="allowed_upload" id="uploader-permission" value="">
                                <div class="toolbar-uploader" id="user-uploader-menu">
                                    <button
                                        type="button"
                                        class="toolbar-button toolbar-uploader-toggle"
                                        id="toggle-user-uploader-menu"
                                        aria-haspopup="true"
                                        aria-expanded="false"
                                        disabled
                                    >
                                        <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M11 16h2V8.83l2.59 2.58L17 10l-5-5-5 5 1.41 1.41L11 8.83V16zm-5 3h12v-2H6v2z"></path>
                                        </svg>
                                        <span class="toolbar-action-label">Uploader</span>
                                        <i aria-hidden="true"></i>
                                    </button>
                                    <div class="toolbar-uploader-options" id="user-uploader-options" hidden>
                                        <button type="button" data-uploader-value="1">
                                            <strong>Yes</strong>
                                            <small>Allow this user to upload</small>
                                        </button>
                                        <button type="button" data-uploader-value="0">
                                            <strong>No</strong>
                                            <small>Remove upload permission</small>
                                        </button>
                                    </div>
                                </div>
                            <?php echo form_close(); ?>
                        <?php else: ?>
                            <button type="button" class="toolbar-button" disabled>Uploader</button>
                        <?php endif; ?>
                        <?php if ($can_create_user): ?>
                            <button
                                type="button"
                                class="toolbar-button toolbar-export-action"
                                id="export-user-access"
                                data-export-base="<?php echo site_url('administrator/users/export'); ?>"
                                disabled
                            >
                                <svg class="toolbar-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M11 4h2v9.17l2.59-2.58L17 12l-5 5-5-5 1.41-1.41L11 13.17V4zM5 19h14v2H5v-2z"></path>
                                </svg>
                                <span class="toolbar-action-label">Export</span>
                            </button>
                        <?php else: ?>
                            <button type="button" class="toolbar-button" disabled>Export</button>
                        <?php endif; ?>
                    </div>

                    <div id="users-ajax-region" aria-live="polite">
                    <!-- Horizontal wrapper prevents the wide old-system table from breaking the page. -->
                    <div class="users-table-wrap">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="select-column">
                                        <input type="checkbox" id="select-all-users" aria-label="Select all available users on this page">
                                    </th>
                                    <th scope="col"><a href="<?php echo $sort_url('username'); ?>">Username</a></th>
                                    <th scope="col"><a href="<?php echo $sort_url('emp_name'); ?>">Name</a></th>
                                    <th scope="col"><a href="<?php echo $sort_url('sub_name'); ?>">Subsidiary</a></th>
                                    <th scope="col"><a href="<?php echo $sort_url('dept_name'); ?>">Department</a></th>
                                    <th scope="col"><a href="<?php echo $sort_url('role'); ?>">User level</a></th>
                                    <th scope="col"><a href="<?php echo $sort_url('status'); ?>">Status</a></th>
                                    <th scope="col"><a href="<?php echo $sort_url('uploader'); ?>">Uploader</a></th>
                                    <th scope="col"><a href="<?php echo $sort_url('registered'); ?>">Registered date</a></th>
                                    <th scope="col"><a href="<?php echo $sort_url('last_visit'); ?>">Last visit date</a></th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                                <?php if (empty($users)): ?>
                                    <tr class="empty-row">
                                        <td colspan="10">No users were found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <?php
                                        // Convert the old numeric status code into a readable label and CSS class.
                                        $employee_name = isset($user['emp_name']) && $user['emp_name'] !== ''
                                            ? $user['emp_name']
                                            : 'Unnamed user';
                                        $user_status = isset($user['stat']) ? (int) $user['stat'] : 0;
                                        $status_label = 'Offline';
                                        $status_class = 'status-offline';

                                        if ($user_status === 1) {
                                            $status_label = 'Online';
                                            $status_class = 'status-online';
                                        } elseif ($user_status === 2) {
                                            $status_label = 'Blocked';
                                            $status_class = 'status-blocked';
                                        } elseif ($user_status === 3) {
                                            $status_label = 'Forced logout';
                                            $status_class = 'status-forced';
                                        }

                                        // Protect the current account and Super Users from future batch actions.
                                        $can_select = (int) $user['user_id'] !== (int) $current_user_id
                                            && (int) $user['role_id'] !== 1
                                            && ((int) $current_role === 1
                                                || (int) $user['role_id'] !== (int) $current_role);
                                        ?>
                                        <tr class="user-row">
                                            <td class="select-column" data-label="Select">
                                                <?php if ($can_select): ?>
                                                    <input
                                                        type="checkbox"
                                                        class="user-selector"
                                                        value="<?php echo (int) $user['user_id']; ?>"
                                                        data-user-status="<?php echo (int) $user_status; ?>"
                                                        data-user-role="<?php echo (int) $user['role_id']; ?>"
                                                        data-user-upload="<?php echo (int) $user['allowed_upload']; ?>"
                                                        data-user-name="<?php echo html_escape($employee_name); ?>"
                                                        aria-label="Select <?php echo html_escape($employee_name); ?>"
                                                    >
                                                <?php else: ?>
                                                    <span class="protected-user" title="The current user and Super Users are protected">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Username">
                                                <span class="username-value"><?php echo html_escape(isset($user['username']) ? $user['username'] : '—'); ?></span>
                                            </td>
                                            <td data-label="Name"><?php echo html_escape($employee_name); ?></td>
                                            <td data-label="Subsidiary"><?php echo html_escape(isset($user['sub_name']) ? $user['sub_name'] : '—'); ?></td>
                                            <td data-label="Department"><?php echo html_escape(isset($user['dept_name']) ? $user['dept_name'] : '—'); ?></td>
                                            <td data-label="User level"><?php echo html_escape(isset($user['role_title']) ? $user['role_title'] : '—'); ?></td>
                                            <td data-label="Status">
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <i aria-hidden="true"></i>
                                                    <?php echo html_escape($status_label); ?>
                                                </span>
                                            </td>
                                            <td data-label="Uploader">
                                                <span class="permission-badge <?php echo (int) $user['allowed_upload'] === 1 ? 'permission-yes' : 'permission-no'; ?>">
                                                    <?php echo (int) $user['allowed_upload'] === 1 ? 'Yes' : 'No'; ?>
                                                </span>
                                            </td>
                                            <td data-label="Registered date"><?php echo html_escape($user['date_registered'] !== '' ? $user['date_registered'] : '—'); ?></td>
                                            <td data-label="Last visit date"><?php echo html_escape($user['last_date_visit'] !== '' ? $user['last_date_visit'] : '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Stable footer kept outside the table scroller. -->
                    <div class="users-footer">
                        <div class="users-result-summary">
                            <form action="<?php echo site_url('administrator/users'); ?>" method="get">
                                <label for="per-page">Show</label>
                                <select name="per_page" id="per-page">
                                    <?php foreach (array(10, 25, 50, 100) as $page_size): ?>
                                        <option value="<?php echo $page_size; ?>"<?php echo $per_page === $page_size ? ' selected' : ''; ?>>
                                            <?php echo $page_size; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span>entries</span>
                                <input type="hidden" name="search" value="<?php echo html_escape($search); ?>">
                                <input type="hidden" name="sort" value="<?php echo html_escape($sort); ?>">
                                <input type="hidden" name="direction" value="<?php echo html_escape($direction); ?>">
                            </form>
                            <p>
                                Showing <strong><?php echo (int) $first_row; ?>–<?php echo (int) $last_row; ?></strong>
                                of <strong><?php echo number_format((int) $total_users); ?></strong>
                            </p>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <nav class="users-pagination" aria-label="Users pages">
                                <?php if ($current_page > 1): ?>
                                    <a href="<?php echo $list_url(array('page' => $current_page - 1)); ?>" aria-label="Previous page">&larr;</a>
                                <?php else: ?>
                                    <span class="is-disabled" aria-hidden="true">&larr;</span>
                                <?php endif; ?>

                                <span class="page-summary">Page <?php echo (int) $current_page; ?> of <?php echo (int) $total_pages; ?></span>

                                <?php if ($current_page < $total_pages): ?>
                                    <a href="<?php echo $list_url(array('page' => $current_page + 1)); ?>" aria-label="Next page">&rarr;</a>
                                <?php else: ?>
                                    <span class="is-disabled" aria-hidden="true">&rarr;</span>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                    </div>
                </section>
            </div>

            <?php if ($can_create_user): ?>
                <?php
                /*
                 * Load the New User form as a modal on this page.
                 * It posts to the same users/create controller action.
                 */
                $this->load->view('admin/partials/user_create_modal', array(
                    'roles'             => $roles,
                    'subsidiaries'      => $subsidiaries,
                    'departments'       => $departments,
                    'error_message'     => $error_message,
                    'created_account'   => $created_account,
                    'open_create_modal' => $open_create_modal,
                    'create_form_active'=> $create_form_active
                ));
                ?>
            <?php endif; ?>

            <?php if (!empty($edit_user)): ?>
                <?php
                /*
                 * Load Edit User only after the controller safely resolves one
                 * manageable account. The password hash is never sent here.
                 */
                $this->load->view('admin/partials/user_edit_modal', array(
                    'edit_user'          => $edit_user,
                    'edit_roles'         => $edit_roles,
                    'edit_subsidiaries'  => $edit_subsidiaries,
                    'edit_departments'   => $edit_departments,
                    'edit_error_message' => $edit_error_message,
                    'edit_success'       => $edit_success,
                    'open_edit_modal'    => $open_edit_modal,
                    'edit_form_active'   => $edit_form_active
                ));
                ?>
            <?php endif; ?>

            <?php
            /*
             * Edit Access shell is always small. The large existing folder
             * tree is requested only after the manager chooses Edit Access.
             */
            $this->load->view('admin/partials/user_access_modal');
            ?>

            <!--
             * Edit User confirmation dialog.
             *
             * The existing Users JavaScript opens this dialog before allowing
             * the normal CI3 form POST. Nothing is saved until the manager
             * explicitly chooses "Save changes" here.
             -->
            <div
                class="user-modal edit-confirm-modal"
                id="user-edit-confirm-modal"
                aria-hidden="true"
            >
                <div class="user-modal-backdrop"></div>
                <section
                    class="user-modal-dialog create-message-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="edit-confirm-title"
                    aria-describedby="edit-confirm-description"
                    tabindex="-1"
                >
                    <span class="create-message-icon" aria-hidden="true">?</span>
                    <p class="create-message-kicker">CONFIRM EDIT</p>
                    <h2 id="edit-confirm-title">Save these user changes?</h2>
                    <p id="edit-confirm-description">
                        The selected account information will be updated.
                    </p>
                    <div class="create-message-actions">
                        <button type="button" class="form-cancel" data-close-edit-confirm>Cancel</button>
                        <button type="button" class="form-save" id="confirm-edit-user">Save changes</button>
                    </div>
                </section>
            </div>

            <?php if (isset($edit_success) && $edit_success !== ''): ?>
                <!--
                 * One-time success dialog rendered only after the controller
                 * confirms that the database update completed successfully.
                 -->
                <div
                    class="user-modal edit-success-modal is-open"
                    id="user-edit-success-modal"
                    aria-hidden="false"
                >
                    <div class="user-modal-backdrop"></div>
                    <section
                        class="user-modal-dialog create-message-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="edit-success-title"
                        aria-describedby="edit-success-description"
                        tabindex="-1"
                    >
                        <span class="create-message-icon create-message-icon-success" aria-hidden="true">&#10003;</span>
                        <p class="create-message-kicker">EDIT USER</p>
                        <h2 id="edit-success-title">User information saved successfully</h2>
                        <p id="edit-success-description"><?php echo html_escape($edit_success); ?></p>
                        <div class="create-message-actions create-success-actions">
                            <button type="button" class="form-save" data-close-edit-success>Done</button>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <!-- Confirmation before setting only users.stat to forced logout (3). -->
            <div class="user-modal status-confirm-modal logout-confirm-modal" id="user-logout-modal" aria-hidden="true">
                <div class="user-modal-backdrop"></div>
                <section
                    class="user-modal-dialog status-confirm-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="logout-confirm-title"
                    tabindex="-1"
                >
                    <button
                        type="button"
                        class="user-modal-close"
                        data-close-logout-modal
                        aria-label="Close confirmation"
                    >&times;</button>
                    <div class="status-confirm-icon logout-confirm-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M10 17v2H5V5h5v2H7v10h3zm5.59-10.41L17 8l-3 3H9v2h5l3 3-1.41 1.41L10.17 12l5.42-5.41z"></path>
                        </svg>
                    </div>
                    <p class="status-confirm-kicker">ACTIVE SESSION</p>
                    <h2 id="logout-confirm-title">Log out user?</h2>
                    <p id="logout-confirm-message">Confirm forced logout for this account.</p>
                    <div class="status-confirm-actions">
                        <button type="button" class="secondary-button" data-close-logout-modal>Cancel</button>
                        <button type="button" class="primary-button logout-confirm-button" id="confirm-user-logout">Log out user</button>
                    </div>
                </section>
            </div>

            <!-- Confirmation before permanently deleting one manageable account. -->
            <div class="user-modal status-confirm-modal" id="user-delete-modal" aria-hidden="true">
                <div class="user-modal-backdrop"></div>
                <section
                    class="user-modal-dialog status-confirm-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-confirm-title"
                    tabindex="-1"
                >
                    <button type="button" class="user-modal-close" data-close-delete-modal aria-label="Close confirmation">&times;</button>
                    <div class="status-confirm-icon" aria-hidden="true">!</div>
                    <p class="status-confirm-kicker">PERMANENT ACTION</p>
                    <h2 id="delete-confirm-title">Delete user?</h2>
                    <p id="delete-confirm-message">This account and its saved file access will be permanently deleted.</p>
                    <div class="status-confirm-actions">
                        <button type="button" class="secondary-button" data-close-delete-modal>Cancel</button>
                        <button type="button" class="primary-button" id="confirm-user-delete">Delete user</button>
                    </div>
                </section>
            </div>

            <!-- One-time confirmation after the server completes a deletion. -->
            <?php if ($delete_success_message !== ''): ?>
                <div class="user-modal status-confirm-modal delete-success-modal is-open" id="user-delete-success-modal" aria-hidden="false">
                    <div class="user-modal-backdrop"></div>
                    <section
                        class="user-modal-dialog status-confirm-dialog delete-success-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="delete-success-title"
                        tabindex="-1"
                    >
                        <div class="status-confirm-icon delete-success-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M9.2 16.6 4.8 12.2l1.4-1.4 3 3 8.6-8.6 1.4 1.4z"></path>
                            </svg>
                        </div>
                        <p class="status-confirm-kicker">DELETION COMPLETE</p>
                        <h2 id="delete-success-title">User deleted</h2>
                        <p><?php echo html_escape($delete_success_message); ?></p>
                        <div class="status-confirm-actions delete-success-actions">
                            <a class="primary-button delete-success-button" href="<?php echo site_url('administrator/users'); ?>">Done</a>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <?php if ($toolbar_success_message !== ''): ?>
                <!--
                 * Shared one-time confirmation for completed toolbar actions.
                 * The controller renders this only after the database update succeeds.
                 -->
                <div
                    class="user-modal status-confirm-modal action-success-modal is-open"
                    id="user-action-success-modal"
                    aria-hidden="false"
                >
                    <div class="user-modal-backdrop"></div>
                    <section
                        class="user-modal-dialog status-confirm-dialog action-success-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="action-success-title"
                        aria-describedby="action-success-message"
                        tabindex="-1"
                    >
                        <div class="status-confirm-icon action-success-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M9.2 16.6 4.8 12.2l1.4-1.4 3 3 8.6-8.6 1.4 1.4z"></path>
                            </svg>
                        </div>
                        <p class="status-confirm-kicker">ACTION COMPLETE</p>
                        <h2 id="action-success-title"><?php echo html_escape($toolbar_success_title); ?></h2>
                        <p id="action-success-message"><?php echo html_escape($toolbar_success_message); ?></p>
                        <div class="status-confirm-actions action-success-actions">
                            <button type="button" class="primary-button" data-close-action-success>Done</button>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <!-- Professional confirmation shown before changing one account status. -->
            <div class="user-modal status-confirm-modal" id="user-status-modal" aria-hidden="true">
                <div class="user-modal-backdrop"></div>
                <section
                    class="user-modal-dialog status-confirm-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="status-confirm-title"
                    tabindex="-1"
                >
                    <button
                        type="button"
                        class="user-modal-close"
                        data-close-status-modal
                        aria-label="Close confirmation"
                    >&times;</button>
                    <div class="status-confirm-icon" aria-hidden="true">!</div>
                    <p class="status-confirm-kicker">ACCOUNT STATUS</p>
                    <h2 id="status-confirm-title">Block user?</h2>
                    <p id="status-confirm-message">Confirm this account status change.</p>
                    <div class="status-confirm-actions">
                        <button type="button" class="secondary-button" data-close-status-modal>Cancel</button>
                        <button type="button" class="primary-button" id="confirm-user-status">Confirm</button>
                    </div>
                </section>
            </div>

            <!-- Confirmation shown before changing only the selected user's role_id. -->
            <div class="user-modal viewer-confirm-modal" id="user-viewer-modal" aria-hidden="true">
                <div class="user-modal-backdrop"></div>
                <section
                    class="user-modal-dialog status-confirm-dialog viewer-confirm-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="viewer-confirm-title"
                    tabindex="-1"
                >
                    <button
                        type="button"
                        class="user-modal-close"
                        data-close-viewer-modal
                        aria-label="Close confirmation"
                    >&times;</button>
                    <div class="status-confirm-icon viewer-confirm-icon" aria-hidden="true">V</div>
                    <div class="status-confirm-copy">
                        <p>VIEWER ACCESS</p>
                        <h2 id="viewer-confirm-title">Assign Viewer level?</h2>
                        <div id="viewer-confirm-message">Choose a Viewer level for this account.</div>
                    </div>
                    <div class="status-confirm-actions">
                        <button type="button" class="secondary-button" data-close-viewer-modal>Cancel</button>
                        <button type="button" class="primary-button viewer-confirm-button" id="confirm-user-viewer">Assign level</button>
                    </div>
                </section>
            </div>

            <!-- Confirmation shown before changing only users.allowed_upload. -->
            <div class="user-modal uploader-confirm-modal" id="user-uploader-modal" aria-hidden="true">
                <div class="user-modal-backdrop"></div>
                <section
                    class="user-modal-dialog status-confirm-dialog uploader-confirm-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="uploader-confirm-title"
                    tabindex="-1"
                >
                    <button
                        type="button"
                        class="user-modal-close"
                        data-close-uploader-modal
                        aria-label="Close confirmation"
                    >&times;</button>
                    <div class="status-confirm-icon uploader-confirm-icon" aria-hidden="true">U</div>
                    <div class="status-confirm-copy">
                        <p>UPLOAD PERMISSION</p>
                        <h2 id="uploader-confirm-title">Change Uploader permission?</h2>
                        <div id="uploader-confirm-message">Choose whether this account may upload.</div>
                    </div>
                    <div class="status-confirm-actions">
                        <button type="button" class="secondary-button" data-close-uploader-modal>Cancel</button>
                        <button type="button" class="primary-button uploader-confirm-button" id="confirm-user-uploader">Save permission</button>
                    </div>
                </section>
            </div>

            <?php
            /*
             * Load the shared display-only footer after the Users list.
             * The joined list query, search, sorting, and pagination are unchanged.
             */
            $this->load->view('admin/partials/footer', array(
                'footer_company_name' => isset($company_name) ? $company_name : $system_name,
                'footer_system_name'  => 'Records Management System'
            ));
            ?>
        </main>
    </div>

    <!-- Keep shared sidebar, header, and Users behavior in separate files. -->
    <script src="<?php echo base_url('assets/js/rms-sidebar.js?v=20260731-2'); ?>"></script>
    <script src="<?php echo base_url('assets/js/rms-header.js?v=20260731-1'); ?>"></script>
    <script src="<?php echo base_url('assets/js/rms-users.js?v=20260812-1'); ?>"></script>
    <script>
        /*
         * Keep browser-saved suggestions out of the New User and Edit User
         * dialogs without changing their form actions, names, or validation.
         * Read-only is removed as soon as the administrator focuses a field.
         */
        (function () {
            'use strict';

            var modalFields = document.querySelectorAll(
                '#user-create-modal input, #user-edit-modal input'
            );
            var index;

            for (index = 0; index < modalFields.length; index += 1) {
                var field = modalFields[index];
                var type = (field.getAttribute('type') || 'text').toLowerCase();

                // Hidden controls, checkboxes, radios, and buttons do not show suggestions.
                if (type === 'hidden' || type === 'checkbox' || type === 'radio' ||
                        type === 'submit' || type === 'button' || type === 'reset') {
                    continue;
                }

                field.setAttribute('autocomplete', type === 'password' ? 'new-password' : 'off');
                field.setAttribute('data-lpignore', 'true');
                field.setAttribute('data-1p-ignore', 'true');
                field.setAttribute('readonly', 'readonly');

                field.addEventListener('focus', function () {
                    this.removeAttribute('readonly');
                });
            }
        }());
    </script>
</body>
</html>
