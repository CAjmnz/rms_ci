<?php
/*
 * Shared admin sidebar
 *
 * Keeps the primary navigation, mobile overlay, and profile summary in one
 * reusable view. Sign out remains only in the header account menu so the
 * interface does not show two competing logout controls. This partial does
 * not run a database query or change any record.
 */

// Identify the current module so only its navigation item is highlighted.
$sidebar_active = isset($sidebar_active) ? (string) $sidebar_active : '';

// Preserve the slightly different menu scope used by each migrated page.
$sidebar_show_documents = isset($sidebar_show_documents)
    ? (bool) $sidebar_show_documents
    : TRUE;
$sidebar_show_system = isset($sidebar_show_system)
    ? (bool) $sidebar_show_system
    : FALSE;
$sidebar_show_help = isset($sidebar_show_help)
    ? (bool) $sidebar_show_help
    : FALSE;

// Keep Documents highlighted on every document route, even when a parent
// view does not explicitly pass its sidebar state.
$sidebar_uri = strtolower(trim(uri_string(), '/'));
$sidebar_documents_active = $sidebar_active === 'documents'
    || strpos($sidebar_uri, 'document') !== FALSE;

/*
 * SIDEBAR TOGGLE ASSET VERSIONING:
 * Load the newest shared sidebar design and behavior on every admin page.
 * The file modification time prevents Chrome from keeping an older cached
 * copy after this toggle feature is updated.
 */
$sidebar_css_file = FCPATH . 'assets/css/rms-sidebar.css';
$sidebar_js_file = FCPATH . 'assets/js/rms-sidebar.js';
$sidebar_css_version = file_exists($sidebar_css_file)
    ? filemtime($sidebar_css_file)
    : '20260810-4';
$sidebar_js_version = file_exists($sidebar_js_file)
    ? filemtime($sidebar_js_file)
    : '20260810-4';

?>

<!-- Shared sidebar assets with automatic cache-busting for all admin pages. -->
<link rel="stylesheet" href="<?php echo base_url('assets/css/rms-sidebar.css?v=' . $sidebar_css_version); ?>">

<!-- Darkens the page while the sidebar is open on tablet or mobile. -->
<div class="mobile-overlay" id="mobile-overlay" aria-hidden="true"></div>

<!--
 * RESPONSIVE SIDEBAR CONTROL:
 * This button stays outside the sliding drawer so it remains reachable when
 * Chrome becomes narrow. On desktop it collapses the sidebar; on tablet and
 * mobile it opens or closes the off-canvas navigation.
 -->
<button
    type="button"
    class="sidebar-toggle"
    id="sidebar-toggle"
    aria-label="Collapse sidebar"
    aria-expanded="true"
    title="Collapse sidebar"
>
    <span aria-hidden="true"></span>
</button>

<aside class="sidebar" id="sidebar" aria-label="Primary navigation">
    <!-- Product identity and mobile close control. -->
    <div class="sidebar-brand">
        <a href="<?php echo $route_url('home', 'administrator/dashboard'); ?>" class="brand-link">
            <span class="brand-symbol" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="brand-copy">
                <strong>ALTURAS</strong>
                <small>Records Management</small>
            </span>
        </a>
        <button type="button" class="mobile-close" id="mobile-close" aria-label="Close navigation">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <!-- Primary routes use the same configured fallbacks as the original views. -->
    <div class="sidebar-scroll">
        <p class="nav-label">WORKSPACE</p>
        <nav class="primary-nav">
            <a
                class="nav-item<?php echo $sidebar_active === 'dashboard' ? ' is-active' : ''; ?>"
                href="<?php echo $route_url('home', 'administrator/dashboard'); ?>"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 11.2 12 4l9 7.2v8.3a.5.5 0 0 1-.5.5H15v-6H9v6H3.5a.5.5 0 0 1-.5-.5z"></path>
                </svg>
                <span>Dashboard</span>
            </a>

            <?php if ($sidebar_show_documents): ?>
                <a
                    class="nav-item<?php echo $sidebar_documents_active ? ' is-active' : ''; ?>"
                    href="<?php echo $route_url('manage_documents', 'administrator/documents'); ?>"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 3h8l4 4v14H6z"></path>
                        <path d="M14 3v5h5M9 12h6M9 16h6"></path>
                    </svg>
                    <span>Documents</span>
                </a>
            <?php endif; ?>

            <a
                class="nav-item<?php echo $sidebar_active === 'users' ? ' is-active' : ''; ?>"
                href="<?php echo $route_url('manage_users', 'administrator/users'); ?>"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"></path>
                    <circle cx="9.5" cy="7" r="4"></circle>
                    <path d="M17 11a3.5 3.5 0 1 0 0-7M18 14.8a4 4 0 0 1 3 3.9V20"></path>
                </svg>
                <span>Users</span>
            </a>

            <a
                class="nav-item<?php echo $sidebar_active === 'subsidiaries' ? ' is-active' : ''; ?>"
                href="<?php echo site_url('administrator/subsidiaries'); ?>"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 21V8l8-5 8 5v13M8 21v-4h8v4M8 10h2M14 10h2M8 14h2M14 14h2"></path>
                </svg>
                <span>Subsidiaries</span>
            </a>

            <a
                class="nav-item<?php echo $sidebar_active === 'departments' ? ' is-active' : ''; ?>"
                href="<?php echo site_url('administrator/departments'); ?>"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                    <path d="M3 9h18M8 4v5M16 4v5M8 13h3M13 13h3M8 17h3M13 17h3"></path>
                </svg>
                <span>Departments</span>
            </a>

            <?php if ($sidebar_show_system): ?>
                <a
                    class="nav-item<?php echo $sidebar_active === 'system' ? ' is-active' : ''; ?>"
                    href="<?php echo $route_url('system', 'administrator/system'); ?>"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"></path>
                    </svg>
                    <span>System</span>
                </a>
            <?php endif; ?>
        </nav>

        <?php if ($sidebar_show_help): ?>
            <!-- Optional help card retained on the Dashboard only. -->
            <div class="sidebar-help">
                <span class="help-icon" aria-hidden="true">?</span>
                <div>
                    <strong>Need assistance?</strong>
                    <small>Contact your system administrator.</small>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!--
     * Permanent RMS identity footer.
     * The signed-in administrator remains available in the header account menu.
     -->
    <div class="sidebar-product-footer">
        <span class="sidebar-footer-mark" aria-hidden="true"></span>
        <span class="profile-copy">
            <strong>ALTURAS RMS</strong>
            <small>&copy; 2026 Records Management</small>
        </span>
    </div>
</aside>

<!-- Shared toggle behavior; the script guards against duplicate page includes. -->
<script src="<?php echo base_url('assets/js/rms-sidebar.js?v=' . $sidebar_js_version); ?>"></script>
