<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Shared User Portal header.
 *
 * This file opens the HTML document, loads the shared sidebar, and renders the
 * top navigation. Each page sets $active_page and $topbar_title before loading
 * this partial.
 */

/*
 * Some User Portal pages (such as Dashboard) do not provide a topbar title.
 * Keep the shared header safe without changing the page/controller workflow.
 */
$topbar_title = isset($topbar_title) ? (string) $topbar_title : '';

/* Build the same two-letter avatar format used by the Administrator header. */
$portal_initials = '';
$portal_name_parts = preg_split('/\s+/', trim($display_name));
foreach ($portal_name_parts as $portal_name_part) {
    if ($portal_name_part !== '' && strlen($portal_initials) < 2) {
        $portal_initials .= strtoupper(substr($portal_name_part, 0, 1));
    }
}
if ($portal_initials === '') {
    $portal_initials = 'U';
}

/* Use the existing organization label below the account name. */
$portal_position = $department !== '' ? $department : 'RMS User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | RMS User Portal</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-user-portal.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-portal-header.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-portal-shell-match.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-profile-approved-layout-fix.css'); ?>">
</head>
<body class="portal-workspace-body">
    <?php $this->load->view('user/partials/sidebar'); ?>

    <button
        class="portal-sidebar-overlay"
        type="button"
        data-portal-sidebar-close
        aria-label="Close navigation">
    </button>

    <main class="portal-workspace">
        <header class="topbar">
            <div class="topbar-left">
                <button
                    id="mobile-menu"
                    class="mobile-menu"
                    type="button"
                    data-portal-sidebar-toggle
                    aria-label="Open navigation"
                    aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>

                <div class="topbar-title<?php echo trim((string) $topbar_title) === '' ? ' is-kicker-only' : ''; ?>">
                    <p class="topbar-kicker">USER PORTAL</p>
                    <h1><?php echo html_escape($topbar_title); ?></h1>
                </div>
            </div>

            <div class="topbar-actions">
                <button
                    type="button"
                    class="user-menu-button"
                    id="user-menu-button"
                    data-portal-account-toggle
                    aria-expanded="false"
                    aria-controls="user-dropdown">
                    <span class="top-avatar"><?php echo html_escape($portal_initials); ?></span>
                    <span class="top-user-copy">
                        <strong><?php echo html_escape($display_name); ?></strong>
                        <small><?php echo html_escape($portal_position); ?></small>
                    </span>
                    <i aria-hidden="true"></i>
                </button>

                <div class="user-dropdown" id="user-dropdown" data-portal-account-menu>
                    <a href="<?php echo site_url('portal/profile'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21c.7-4.2 3.3-6 8-6s7.3 1.8 8 6"/>
                        </svg>
                        <span>My Profile</span>
                    </a>
                    <a href="<?php echo site_url('portal/logout'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"/>
                        </svg>
                        <span>Sign out</span>
                    </a>
                </div>
            </div>
        </header>
