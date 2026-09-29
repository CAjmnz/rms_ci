<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Shared User Portal sidebar.
 * Add future User Portal navigation links here so every page stays consistent.
 */

/*
 * Resolve the active page safely from the current URL when a view does not set
 * $active_page. Folder and file URLs under /portal/documents/... continue to
 * highlight My Documents, just like the shared Administrator sidebar.
 */
if (!isset($active_page) || $active_page === '') {
    $portal_section = strtolower((string) $this->uri->segment(2));
    $active_page = in_array($portal_section, array('documents', 'profile'), TRUE)
        ? $portal_section
        : 'dashboard';
}
?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/rms-user-portal-control.css?v=44'); ?>">

<input
    type="checkbox"
    class="portal-sidebar-collapse-control"
    id="portal-sidebar-collapse-control"
    aria-label="Collapse or expand sidebar">

<label
    class="portal-sidebar-edge-toggle"
    for="portal-sidebar-collapse-control"
    title="Collapse or expand sidebar">
    <span aria-hidden="true"></span>
</label>

<aside class="portal-sidebar" id="portal-sidebar">
    <a class="portal-sidebar-brand" href="<?php echo site_url('portal/dashboard'); ?>">
        <span class="portal-records-mark" aria-hidden="true">
            <svg viewBox="0 0 48 48">
                <path d="M10 13h12l4 4h12v20H10z"/>
                <path d="M15 9h13l4 4"/>
                <path d="M17 24h14M17 29h11"/>
            </svg>
        </span>
        <span class="portal-brand-copy">
            <strong>ALTURAS</strong>
            <small>Records Management</small>
        </span>
    </a>

    <div class="portal-nav-label">WORKSPACE</div>

    <nav class="portal-navigation" aria-label="User Portal navigation">
        <a
            class="<?php echo $active_page === 'dashboard' ? 'active' : ''; ?>"
            href="<?php echo site_url('portal/dashboard'); ?>"
            <?php echo $active_page === 'dashboard' ? 'aria-current="page"' : ''; ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 11 12 3l9 8M5 10v10h14V10M9 20v-6h6v6"/>
            </svg>
            <span>Dashboard</span>
        </a>

        <a
            class="<?php echo $active_page === 'documents' ? 'active' : ''; ?>"
            href="<?php echo site_url('portal/documents'); ?>"
            <?php echo $active_page === 'documents' ? 'aria-current="page"' : ''; ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 5.5h6l2 2h8v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/>
            </svg>
            <span>My Documents</span>
        </a>

        <a
            class="<?php echo $active_page === 'profile' ? 'active' : ''; ?>"
            href="<?php echo site_url('portal/profile'); ?>"
            <?php echo $active_page === 'profile' ? 'aria-current="page"' : ''; ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 21c.7-4.2 3.3-6 8-6s7.3 1.8 8 6"/>
            </svg>
            <span>Profile</span>
        </a>
    </nav>

    <div class="portal-sidebar-support">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3 5 6v5c0 4.6 2.9 8.2 7 10 4.1-1.8 7-5.4 7-10V6zM9.5 12l1.7 1.7 3.8-4"/>
        </svg>
        <div>
            <strong>Protected workspace</strong>
            <small>Your access is managed by RMS.</small>
        </div>
    </div>
</aside>
