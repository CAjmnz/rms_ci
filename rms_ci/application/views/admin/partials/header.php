<?php
/*
 * Shared admin header partial
 *
 * Keeps the page title, page description, optional statistic, optional page
 * action, and signed-in user menu in one reusable view. Each parent page
 * passes display values only; this partial does not query or change data.
 */

// Use safe defaults so a missing optional value cannot break the page header.
$header_title = isset($header_title) && $header_title !== '' ? $header_title : 'Admin';
$header_kicker = isset($header_kicker) && $header_kicker !== '' ? $header_kicker : 'ADMIN CONTROL PANEL';
$header_description = isset($header_description) && $header_description !== '' ? $header_description : '';

// Optional right-side statistic replaces the old repeated page sub-hero cards.
$header_stat_label = isset($header_stat_label) && $header_stat_label !== '' ? $header_stat_label : '';
$header_stat_value = isset($header_stat_value) && $header_stat_value !== '' ? $header_stat_value : '';
$header_stat_id = isset($header_stat_id) && $header_stat_id !== '' ? $header_stat_id : '';
$header_stat_icon = isset($header_stat_icon) && $header_stat_icon !== '' ? $header_stat_icon : 'records';

// Optional action and account menu values remain compatible with old pages.
$header_action_url = isset($header_action_url) ? $header_action_url : '';
$header_action_label = isset($header_action_label) ? $header_action_label : '';
$header_action_symbol = isset($header_action_symbol) ? $header_action_symbol : '';
$header_show_user_menu = isset($header_show_user_menu) ? (bool) $header_show_user_menu : TRUE;
$header_primary_url = isset($header_primary_url) ? $header_primary_url : '';
$header_primary_label = isset($header_primary_label) ? $header_primary_label : '';
$header_profile_url = isset($header_profile_url) && $header_profile_url !== ''
    ? $header_profile_url
    : (strtolower(trim($header_primary_label)) === 'edit profile' && $header_primary_url !== '' ? $header_primary_url : site_url('users/profile'));
$header_logout_url = isset($header_logout_url) ? $header_logout_url : site_url('logout');

/*
 * Icon compatibility:
 * Older Documents pages may still pass the default records icon. Keep those
 * pages untouched and select the document icon from the shared header.
 */
if ($header_stat_icon === 'records' && strtolower(trim($header_title)) === 'documents') {
    $header_stat_icon = 'documents';
}
?>
<!-- Shared responsive admin header with reusable module introduction. -->
<header class="topbar">
    <div class="topbar-left">
        <!-- Opens the existing sidebar on tablet and mobile screens. -->
        <button type="button" class="mobile-menu" id="mobile-menu" aria-label="Open navigation" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <div class="topbar-title">
            <p class="topbar-kicker"><?php echo html_escape($header_kicker); ?></p>
            <h1><?php echo html_escape($header_title); ?></h1>
            <?php if ($header_description !== ''): ?>
                <p class="topbar-description"><?php echo html_escape($header_description); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="topbar-actions">
        <?php if ($header_stat_label !== '' || $header_stat_value !== ''): ?>
            <!-- Optional statistic shown consistently from Users through System. -->
            <div class="topbar-stat" aria-label="<?php echo html_escape(trim($header_stat_value . ' ' . $header_stat_label)); ?>">
                <span class="topbar-stat-icon topbar-stat-<?php echo html_escape($header_stat_icon); ?>" aria-hidden="true">
                    <?php if ($header_stat_icon === 'documents'): ?>
                        <svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"></path><path d="M14 3v5h5M9 12h6M9 16h6"></path></svg>
                    <?php elseif ($header_stat_icon === 'time'): ?>
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v5l3 2"></path></svg>
                    <?php elseif ($header_stat_icon === 'backup'): ?>
                        <svg viewBox="0 0 24 24"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 19h14"></path></svg>
                    <?php elseif ($header_stat_icon === 'subsidiaries'): ?>
                        <svg viewBox="0 0 24 24"><path d="M4 21V8l8-5 8 5v13"></path><path d="M8 21v-4h8v4M8 10h2M14 10h2M8 14h2M14 14h2"></path></svg>
                    <?php elseif ($header_stat_icon === 'departments'): ?>
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 9h18M8 4v5M16 4v5M8 13h3M13 13h3M8 17h3M13 17h3"></path></svg>
                    <?php elseif ($header_stat_icon === 'settings'): ?>
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"></path></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"></path><circle cx="9.5" cy="7" r="4"></circle><path d="M17 11a3.5 3.5 0 1 0 0-7M18 14.8a4 4 0 0 1 3 3.9V20"></path></svg>
                    <?php endif; ?>
                </span>
                <span class="topbar-stat-copy">
                    <?php if ($header_stat_value !== ''): ?>
                        <strong<?php echo $header_stat_id !== '' ? ' id="' . html_escape($header_stat_id) . '"' : ''; ?>><?php echo html_escape($header_stat_value); ?></strong>
                    <?php endif; ?>
                    <?php if ($header_stat_label !== ''): ?>
                        <small><?php echo html_escape($header_stat_label); ?></small>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Optional page action. Dashboard shortcuts stay in the sidebar only. -->
        <?php if ($header_action_url !== '' && $header_action_label !== '' && strtolower(trim($header_action_label)) !== 'dashboard'): ?>
            <a href="<?php echo html_escape($header_action_url); ?>" class="topbar-create">
                <?php if ($header_action_symbol !== ''): ?><span aria-hidden="true"><?php echo html_escape($header_action_symbol); ?></span><?php endif; ?>
                <?php echo html_escape($header_action_label); ?>
            </a>
        <?php endif; ?>

        <?php if ($header_show_user_menu): ?>
            <!-- Profile menu is controlled by assets/js/rms-header.js. -->
            <button type="button" class="user-menu-button" id="user-menu-button" aria-expanded="false" aria-controls="user-dropdown">
                <span class="top-avatar"><?php echo html_escape($initials); ?></span>
                <span class="top-user-copy"><strong><?php echo html_escape($safe_display_name); ?></strong><small><?php echo html_escape($safe_position); ?></small></span>
                <i aria-hidden="true"></i>
            </button>

            <div class="user-dropdown" id="user-dropdown">
                <a href="<?php echo html_escape($header_profile_url); ?>">Edit profile</a>
                <a href="<?php echo html_escape($header_logout_url); ?>">Sign out</a>
            </div>
        <?php endif; ?>
    </div>
</header>
