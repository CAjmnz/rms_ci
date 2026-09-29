<?php
$safe_display_name = isset($display_name) && $display_name !== ''
    ? $display_name
    : (isset($username) && $username !== '' ? $username : 'Administrator');
$safe_position = isset($position) && $position !== '' ? $position : 'Administrator';
$safe_last_login = isset($last_login) && $last_login !== ''
    ? $last_login
    : 'First login in this session';
$safe_routes = isset($routes) && is_array($routes) ? $routes : array();
$route_url = function ($key, $fallback) use ($safe_routes) {
    $route = isset($safe_routes[$key]) && $safe_routes[$key] !== ''
        ? $safe_routes[$key]
        : $fallback;

    return site_url($route);
};

/*
 * The Dashboard shortcut opens the existing Documents page and asks only its
 * uploader to open. No separate upload page or duplicate permission path is
 * introduced.
 */
$dashboard_upload_url = $route_url(
    'manage_documents',
    'administrator/documents'
);
$dashboard_upload_url .= strpos($dashboard_upload_url, '?') === FALSE
    ? '?open_upload=1'
    : '&open_upload=1';
$dashboard_pins_url = site_url('administrator/documents/pinned_items');
$dashboard_documents_url = $route_url(
    'manage_documents',
    'administrator/documents'
);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>
    <!-- Base dashboard layout and page-specific content styles. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-dashboard.css?v=20260904-pinned-ui-v2'); ?>">
    <!-- Shared sidebar styles are isolated from page content styles. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-sidebar.css?v=20260810-3'); ?>">
    <!-- Shared header styles are isolated from dashboard content styles. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css?v=20260731-1'); ?>">
</head>
<body>
    <div class="dashboard-app">
        <?php
        /*
         * Load the reusable sidebar with Dashboard-specific active/menu state.
         * This view-only operation does not run or change a database query.
         */
        $this->load->view('admin/partials/sidebar', array(
            'sidebar_active'         => 'dashboard',
            'sidebar_documents_open' => TRUE,
            'sidebar_show_system'     => TRUE,
            'sidebar_show_help'       => TRUE,
            'initials'                => $initials,
            'safe_display_name'       => $safe_display_name,
            'safe_position'           => $safe_position,
            'route_url'               => $route_url
        ));
        ?>

        <main class="main-area">
            <?php
            /*
             * Load the reusable header with Dashboard-specific text and links.
             * This view-only operation does not run or change a database query.
             */
            $this->load->view('admin/partials/header', array(
                'header_title'         => 'Dashboard',
                'header_action_url'    => '',
                'header_action_label'  => '',
                'header_action_symbol' => '',
                'header_primary_url'   => $route_url('edit_profile', 'users/profile'),
                'header_primary_label' => 'Edit profile',
                'header_logout_url'    => $route_url('logout', 'logout'),
                'initials'             => $initials,
                'safe_display_name'    => $safe_display_name,
                'safe_position'        => $safe_position
            ));
            ?>

            <div class="dashboard-content">
                <section class="welcome-card">
                    <div class="welcome-copy">
                        <span class="welcome-pill">RECORDS WORKSPACE</span>
                        <h2>Good day, <?php echo html_escape($safe_display_name); ?>.</h2>
                        <p>Here is a clear overview of your records management workspace.</p>
                        <div class="last-login">
                            <span class="status-dot" aria-hidden="true"></span>
                            <span>Last login: <strong><?php echo html_escape($safe_last_login); ?></strong></span>
                        </div>
                    </div>
                    <div class="welcome-graphic" aria-hidden="true">
                        <span class="folder-back"></span>
                        <span class="folder-front"></span>
                        <span class="paper paper-one"></span>
                        <span class="paper paper-two"></span>
                        <span class="paper paper-three"></span>
                        <span class="graphic-ring ring-one"></span>
                        <span class="graphic-ring ring-two"></span>
                    </div>
                </section>

                <section class="section-block" aria-labelledby="overview-title">
                    <div class="section-heading">
                        <div>
                            <p>LIVE SUMMARY</p>
                            <h2 id="overview-title">Records overview</h2>
                        </div>
                        <span class="section-note">Values use your existing RMS data</span>
                    </div>

                    <div class="metric-grid">
<a href="<?php echo $route_url('manage_documents', 'documents'); ?>"
   class="metric-card metric-pending"
   aria-label="<?php echo number_format((int) $pending_count); ?> unpublished folders">

    <span class="metric-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 7v5l3 2"></path>
        </svg>
    </span>

    <span class="metric-copy">
        <small>Pending</small>

        <strong>
            <?php echo number_format((int) $pending_count); ?>
        </strong>

        <em>Unpublished folders</em>
    </span>

    <span class="metric-arrow" aria-hidden="true">&rarr;</span>
</a>
                        <a href="<?php echo $route_url('manage_documents', 'documents'); ?>" class="metric-card metric-documents">
                            <span class="metric-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M6 3h8l4 4v14H6z"></path>
                                    <path d="M14 3v5h5M9 12h6M9 16h6"></path>
                                </svg>
                            </span>
                            <span class="metric-copy">
                                <small>Documents</small>
                                <strong><?php echo number_format((int) $documents_count); ?></strong>
                                <em>Total folders</em>
                            </span>
                            <span class="metric-arrow" aria-hidden="true">&rarr;</span>
                        </a>

                        <a href="<?php echo $route_url('manage_users', 'users'); ?>" class="metric-card metric-users">
                            <span class="metric-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"></path>
                                    <circle cx="9.5" cy="7" r="4"></circle>
                                    <path d="M17 11a3.5 3.5 0 1 0 0-7M18 14.8a4 4 0 0 1 3 3.9V20"></path>
                                </svg>
                            </span>
                            <span class="metric-copy">
                                <small>Users</small>
                                <strong><?php echo number_format((int) $users_count); ?></strong>
                                <em>Registered accounts</em>
                            </span>
                            <span class="metric-arrow" aria-hidden="true">&rarr;</span>
                        </a>

                        <a href="<?php echo $route_url('manage_users', 'users'); ?>" class="metric-card metric-online">
                            <span class="metric-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="9" cy="8" r="4"></circle>
                                    <path d="M3 21v-2a6 6 0 0 1 12 0v2"></path>
                                    <circle cx="18" cy="7" r="3"></circle>
                                    <path d="M17 13a5 5 0 0 1 4 4.9V20"></path>
                                </svg>
                            </span>
                            <span class="metric-copy">
                                <small>Online</small>
                                <strong><?php echo number_format((int) $online_count); ?></strong>
                                <em>Active sessions</em>
                            </span>
                            <span class="metric-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </section>

                               <div class="dashboard-lower-grid">
                    <section class="content-card quick-card" aria-labelledby="quick-actions-title">
                        <div class="card-heading">
                            <div>
                                <p>SHORTCUTS</p>
                                <h2 id="quick-actions-title">Quick actions</h2>
                            </div>
                        </div>
                        <div class="quick-grid">
                            <a href="<?php echo html_escape($dashboard_upload_url); ?>" class="quick-link">
                                <span class="quick-icon quick-green" aria-hidden="true">+</span>
                                <span><strong>Upload New document</strong><small>Open uploader</small></span>
                                <i aria-hidden="true">&rarr;</i>
                            </a>
                            <a href="<?php echo $route_url('manage_documents', 'documents'); ?>" class="quick-link">
                                <span class="quick-icon quick-blue" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6zM14 3v5h5M9 12h6M9 16h6"></path></svg>
                                </span>
                                <span><strong>Manage documents</strong><small>Browse all records</small></span>
                                <i aria-hidden="true">&rarr;</i>
                            </a>
                            <a href="<?php echo $route_url('manage_users', 'users'); ?>" class="quick-link">
                                <span class="quick-icon quick-purple" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"></circle><path d="M3 21v-2a6 6 0 0 1 12 0v2M17 11a3.5 3.5 0 1 0 0-7"></path></svg>
                                </span>
                                <span><strong>Manage users</strong><small>Review user accounts</small></span>
                                <i aria-hidden="true">&rarr;</i>
                            </a>
                            <a href="<?php echo $route_url('manage_departments', 'departments'); ?>" class="quick-link">
                                <span class="quick-icon quick-amber" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 9h18M8 13h3M13 13h3M8 17h3M13 17h3"></path></svg>
                                </span>
                                <span><strong>Departments</strong><small>Manage department list</small></span>
                                <i aria-hidden="true">&rarr;</i>
                            </a>
                        </div>
                    </section>

                    <section class="content-card dashboard-pins-card" aria-labelledby="dashboard-pins-title">
                        <div class="dashboard-pins-heading">
                            <h2 id="dashboard-pins-title">Pinned Items</h2>
                            <a href="<?php echo html_escape($dashboard_documents_url); ?>">View all</a>
                        </div>
                        <div class="dashboard-pins-search">
                            <span aria-hidden="true">&#128269;</span>
                            <input type="search" id="dashboard-pins-search" placeholder="Search pinned items..." autocomplete="off">
                        </div>
                        <div class="dashboard-pins-filters" role="tablist" aria-label="Filter pinned items">
                            <button type="button" class="is-active" data-dashboard-pin-filter="all">All</button>
                            <button type="button" data-dashboard-pin-filter="filename">Filenames</button>
                            <button type="button" data-dashboard-pin-filter="subfolder">Subfolders</button>
                            <button type="button" data-dashboard-pin-filter="document">Documents</button>
                        </div>
                        <div class="dashboard-pins-list" id="dashboard-pins-list" aria-live="polite">
                            <div class="dashboard-pins-loading">Loading pinned items...</div>
                        </div>
                        <a class="dashboard-pins-view-all" href="<?php echo html_escape($dashboard_documents_url); ?>">
                            View all pinned items
                        </a>
                        <p class="dashboard-pins-note">Personal pins <span>&bull;</span> Database-backed <span>&bull;</span> Same RMS account</p>
                    </section>
                </div>

                <aside class="content-card account-card" aria-labelledby="account-title">
                    <div class="card-heading">
                        <div>
                            <p>YOUR SESSION</p>
                            <h2 id="account-title">Account details</h2>
                        </div>
                        <span class="secure-badge"><i aria-hidden="true"></i> Secure</span>
                    </div>
                    <dl class="account-list">
                        <div>
                            <dt>Employee</dt>
                            <dd><?php echo html_escape($safe_display_name); ?></dd>
                        </div>
                        <div>
                            <dt>Employee ID</dt>
                            <dd><?php echo isset($employee_id) && $employee_id !== '' ? html_escape($employee_id) : '&mdash;'; ?></dd>
                        </div>
                        <div>
                            <dt>Position</dt>
                            <dd><?php echo html_escape($safe_position); ?></dd>
                        </div>
                        <div>
                            <dt>Location</dt>
                            <dd><?php echo isset($location) && $location !== '' ? html_escape($location) : '&mdash;'; ?></dd>
                        </div>
                    </dl>
                    <a href="<?php echo $route_url('edit_profile', 'users/profile'); ?>" class="profile-link">
                        Edit profile <span aria-hidden="true">&rarr;</span>
                    </a>
                </aside>
                </div>

            </div>

            <footer class="dashboard-footer" aria-label="System footer">
                <span class="dashboard-footer-brand">ALTURAS RMS</span>
                <span class="dashboard-footer-divider" aria-hidden="true"></span>
                <span>Secure records workspace</span>
            </footer>
        </main>
    </div>

    <!-- Sidebar, header, and page behavior remain in separate files. -->
    <script src="<?php echo base_url('assets/js/rms-sidebar.js?v=20260731-2'); ?>"></script>
    <script src="<?php echo base_url('assets/js/rms-header.js?v=20260731-1'); ?>"></script>
    <script>
    (function () {
        'use strict';

        var endpoint = <?php echo json_encode($dashboard_pins_url); ?>;
        var list = document.getElementById('dashboard-pins-list');
        var search = document.getElementById('dashboard-pins-search');
        var filters = document.querySelectorAll('[data-dashboard-pin-filter]');
        var activeFilter = 'all';
        var searchTimer = null;
        var request = null;

        function escapeHtml(value) {
            var node = document.createElement('div');
            node.appendChild(document.createTextNode(value == null ? '' : String(value)));
            return node.innerHTML;
        }

        function render(items) {
            if (!items.length) {
                list.innerHTML = '<div class="dashboard-pins-empty">' +
                    '<strong>No pinned items yet.</strong>' +
                    '<span>Pin a Filename, Subfolder, or Document from Document Management.</span>' +
                    '</div>';
                return;
            }

            var groups = [];

            items.forEach(function (item) {
                var label = String(item.type_label || 'Document');
                if (groups.indexOf(label) === -1) {
                    groups.push(label);
                }
            });

            list.innerHTML = groups.map(function (label) {
                var rows = items.filter(function (item) {
                    return String(item.type_label || 'Document') === label;
                }).map(function (item) {
                    var folder = item.item_type === 'folder';
                    var icon = folder ? '&#128193;' : '&#128196;';
                    return '<a class="dashboard-pin-row" href="' + escapeHtml(item.url || '#') + '">' +
                        '<span class="dashboard-pin-icon" aria-hidden="true">' + icon +
                        '<span class="dashboard-pin-mark">&#128204;</span></span>' +
                        '<span class="dashboard-pin-copy">' +
                        '<span class="dashboard-pin-title"><strong>' + escapeHtml(item.name) + '</strong>' +
                        '<span class="dashboard-pin-type">' + escapeHtml(item.type_label) + '</span></span>' +
                        '<span class="dashboard-pin-parent"><b>PARENT</b><em>' +
                        escapeHtml(item.parent || 'Root') + '</em></span>' +
                        '<small>' + escapeHtml(item.path) + '</small></span>' +
                        '<span class="dashboard-pin-menu" aria-hidden="true">&#8942;</span></a>';
                }).join('');

                return '<div class="dashboard-pin-group"><strong>' + escapeHtml(label) +
                    '</strong></div>' + rows;
            }).join('');
        }

        function loadPins() {
            var query = encodeURIComponent(search ? search.value.trim() : '');
            if (request && request.readyState !== 4) {
                request.abort();
            }

            request = new XMLHttpRequest();
            request.open('GET', endpoint + '?type=' + encodeURIComponent(activeFilter) +
                '&search=' + query + '&all=1', true);
            request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            request.onreadystatechange = function () {
            var response;
            if (request.readyState !== 4) {
                return;
            }
            if (request.status >= 200 && request.status < 300) {
                try {
                    response = JSON.parse(request.responseText);
                } catch (ignore) {
                    response = null;
                }
                if (response && response.success) {
                    render(response.items || []);
                    return;
                }
            }
            list.innerHTML = '<div class="dashboard-pins-empty"><strong>Pinned items are unavailable.</strong>' +
                '<span>Please refresh the page or open Document Management.</span></div>';
            };
            request.send(null);
        }

        if (search) {
            search.addEventListener('input', function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(loadPins, 220);
            });
        }

        Array.prototype.forEach.call(filters, function (button) {
            button.addEventListener('click', function () {
                activeFilter = button.getAttribute('data-dashboard-pin-filter') || 'all';
                Array.prototype.forEach.call(filters, function (item) {
                    item.classList.remove('is-active');
                });
                button.classList.add('is-active');
                loadPins();
            });
        });

        loadPins();
    }());
    </script>
</body>
</html>
