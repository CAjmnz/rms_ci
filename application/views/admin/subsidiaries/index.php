<?php
$safe_name = isset($display_name) && $display_name !== '' ? $display_name : $username;
$safe_position = isset($position) && $position !== '' ? $position : 'Administrator';
$safe_routes = is_array($routes) ? $routes : array();
$route_url = function ($key, $fallback) use ($safe_routes) {
    return site_url(isset($safe_routes[$key]) ? $safe_routes[$key] : $fallback);
};
$parts = preg_split('/\s+/', trim($safe_name));
$initials = isset($parts[0][0]) ? strtoupper($parts[0][0]) : 'A';
if (count($parts) > 1) { $last = $parts[count($parts) - 1]; $initials .= strtoupper($last[0]); }
$list_url = function ($new_page) use ($search, $per_page, $sort, $order) {
    $query = array('page' => $new_page, 'per_page' => $per_page, 'sort' => $sort, 'order' => $order);
    if ($search !== '') { $query['search'] = $search; }
    return site_url('administrator/subsidiaries').'?'.http_build_query($query);
};
/* Create safe sortable-column URLs while retaining the current filter. */
$sort_url = function ($column) use ($search, $per_page, $sort, $order) {
    $next_order = $sort === $column && $order === 'asc' ? 'desc' : 'asc';
    $query = array('page' => 1, 'per_page' => $per_page, 'sort' => $column, 'order' => $next_order);
    if ($search !== '') { $query['search'] = $search; }
    return site_url('administrator/subsidiaries').'?'.http_build_query($query);
};
/* Automatically refresh changed Subsidiaries assets in the browser cache. */
$asset_url = function ($path) {
    $full_path = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $path);
    return base_url($path).'?v='.(is_file($full_path) ? filemtime($full_path) : '1');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-dashboard.css?v=20260731-2'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-sidebar.css?v=20260803-2'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css?v=20260731-1'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-footer.css?v=20260803-1'); ?>">
    <link rel="stylesheet" href="<?php echo $asset_url('assets/css/rms-subsidiaries.css'); ?>">
    <!-- Subsidiaries-only hero, search, action, sorting, and AJAX states. -->
    <link rel="stylesheet" href="<?php echo $asset_url('assets/css/rms-subsidiaries-enhancements.css'); ?>">
</head>
<body>
<div class="dashboard-app">
    <?php $this->load->view('admin/partials/sidebar', array(
        'sidebar_active' => 'subsidiaries', 'sidebar_show_system' => TRUE,
        'initials' => $initials, 'safe_display_name' => $safe_name,
        'safe_position' => $safe_position, 'route_url' => $route_url
    )); ?>
    <main class="main-area">
        <?php $this->load->view('admin/partials/header', array(
            'header_kicker' => 'ORGANIZATION DIRECTORY',
            'header_title' => 'Subsidiaries',
            'header_description' => 'Manage company subsidiaries used by departments and user accounts.',
            'header_stat_label' => 'Total subsidiaries',
            'header_stat_value' => number_format($total),
            'header_stat_id' => 'subsidiaries-matching-total',
            'header_stat_icon' => 'subsidiaries',
            'header_action_url' => $route_url('home', 'administrator/dashboard'),
            'header_action_label' => 'Dashboard', 'header_action_symbol' => "\xE2\x86\x90",
            'header_primary_url' => $route_url('home', 'administrator/dashboard'),
            'header_primary_label' => 'Dashboard',
            'header_logout_url' => $route_url('logout', 'administrator/logout'),
            'initials' => $initials, 'safe_display_name' => $safe_name,
            'safe_position' => $safe_position
        )); ?>

        <div class="subs-content">

            <section class="subs-panel">
                <div class="subs-heading">
                    <div><p>SUBSIDIARIES MODULE</p><h2>Manage subsidiaries</h2></div>
                    <form class="subs-search" id="subsidiaries-search-form" method="get" action="<?php echo site_url('administrator/subsidiaries'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16 16 5 5"></path></svg>
                        <input id="subsidiaries-search" type="search" name="search" value="<?php echo html_escape($search); ?>" placeholder="Search subsidiaries">
                        <input type="hidden" name="per_page" value="<?php echo (int) $per_page; ?>">
                        <input type="hidden" name="sort" value="<?php echo html_escape($sort); ?>">
                        <input type="hidden" name="order" value="<?php echo html_escape($order); ?>">
                        <button type="submit">Search</button>
                        <?php if ($search !== ''): ?><a href="<?php echo site_url('administrator/subsidiaries'); ?>">Clear</a><?php endif; ?>
                    </form>
                </div>

                <div class="subs-toolbar">
                    <button type="button" class="action primary" id="subs-add">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg><span>New subsidiary</span>
                    </button>
                    <button type="button" class="action" id="subs-edit" disabled>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"></path></svg><span>Edit</span>
                    </button>
                    <button type="button" class="action danger" id="subs-delete" disabled>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"></path></svg><span>Delete</span>
                    </button>
                </div>

                <!-- AJAX replaces only this server-rendered result region. -->
                <div id="subsidiaries-ajax-region" aria-live="polite">
                <div class="subs-table-wrap">
                    <table class="subs-table">
                        <thead><tr><th class="check"><input type="checkbox" id="subs-all" aria-label="Select all subsidiaries"></th><th><a class="sort-link<?php echo $sort === 'name' ? ' active' : ''; ?>" href="<?php echo $sort_url('name'); ?>">Subsidiary name <span><?php echo $sort === 'name' && $order === 'desc' ? '↓' : '↑'; ?></span></a></th><th class="id-col"><a class="sort-link<?php echo $sort === 'id' ? ' active' : ''; ?>" href="<?php echo $sort_url('id'); ?>">ID <span><?php echo $sort === 'id' && $order === 'desc' ? '↓' : '↑'; ?></span></a></th></tr></thead>
                        <tbody>
                        <?php if ($subsidiaries): foreach ($subsidiaries as $row): ?>
                            <tr data-id="<?php echo (int) $row['sub_id']; ?>" data-name="<?php echo html_escape($row['sub_name']); ?>">
                                <td class="check"><input class="subs-check" type="checkbox" value="<?php echo (int) $row['sub_id']; ?>" aria-label="Select <?php echo html_escape($row['sub_name']); ?>"></td>
                                <td><span class="company-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V8l8-5 8 5v13M8 21v-4h8v4M8 10h2M14 10h2M8 14h2M14 14h2"></path></svg></span><strong><?php echo html_escape($row['sub_name']); ?></strong></td>
                                <td class="id-col">#<?php echo (int) $row['sub_id']; ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V8l8-5 8 5v13"></path></svg><strong>No subsidiaries found</strong><span><?php echo $search !== '' ? 'Try a different search.' : 'Add the first subsidiary to begin.'; ?></span></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="subs-footer">
                    <form method="get" action="<?php echo site_url('administrator/subsidiaries'); ?>">
                        <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo html_escape($search); ?>"><?php endif; ?>
                        <input type="hidden" name="sort" value="<?php echo html_escape($sort); ?>">
                        <input type="hidden" name="order" value="<?php echo html_escape($order); ?>">
                        <label>Rows per page <select id="subsidiaries-per-page" name="per_page">
                            <?php foreach (array(10,25,50,100) as $size): ?><option value="<?php echo $size; ?>"<?php echo $size === $per_page ? ' selected' : ''; ?>><?php echo $size; ?></option><?php endforeach; ?>
                        </select></label>
                    </form>
                    <span>Showing <?php echo $first_row; ?>–<?php echo $last_row; ?> of <?php echo $total; ?></span>
                    <nav aria-label="Subsidiaries pagination">
                        <a class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $list_url(max(1, $page - 1)); ?>" aria-label="Previous page">&lsaquo;</a>
                        <strong><?php echo $page; ?> / <?php echo $pages; ?></strong>
                        <a class="<?php echo $page >= $pages ? 'disabled' : ''; ?>" href="<?php echo $list_url(min($pages, $page + 1)); ?>" aria-label="Next page">&rsaquo;</a>
                    </nav>
                </div>
                </div>
            </section>
        </div>
    </main>
</div>

<div class="subs-modal" id="subs-form-modal" hidden>
    <div class="subs-backdrop" aria-hidden="true"></div><section class="subs-dialog" role="dialog" aria-modal="true" aria-labelledby="subs-form-title">
        <header><span class="dialog-icon"><svg viewBox="0 0 24 24"><path d="M4 21V8l8-5 8 5v13M8 21v-4h8v4"></path></svg></span><div><small>SUBSIDIARY DETAILS</small><h2 id="subs-form-title">New subsidiary</h2></div><button type="button" class="modal-close" data-close="form" aria-label="Close">&times;</button></header>
        <?php echo form_open('administrator/subsidiaries/save', array('id' => 'subs-form')); ?>
            <input type="hidden" name="sub_id" id="subs-id" value="">
            <div class="form-body"><label for="subs-name">Subsidiary name <em>*</em></label><input type="text" name="sub_name" id="subs-name" maxlength="100" autocomplete="off" required><small>Use the official company or business-unit name.</small><div class="form-error" id="subs-form-error" role="alert"></div></div>
            <footer><button type="button" class="cancel" data-close="form">Cancel</button><button type="submit" class="save"><svg viewBox="0 0 24 24"><path d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 20v-6h8v6"></path></svg>Save subsidiary</button></footer>
        <?php echo form_close(); ?>
    </section>
</div>

<div class="subs-modal" id="subs-message-modal" hidden>
    <div class="subs-backdrop"></div><section class="subs-dialog message" role="alertdialog" aria-modal="true"><span class="message-icon" id="subs-message-icon"></span><h2 id="subs-message-title"></h2><p id="subs-message-text"></p><div class="message-actions"><button type="button" class="cancel" id="subs-message-cancel">Cancel</button><button type="button" class="save" id="subs-message-confirm">Continue</button></div></section>
</div>

<script>window.RMS_SUBS = <?php echo json_encode(array('form' => site_url('administrator/subsidiaries/form'), 'save' => site_url('administrator/subsidiaries/save'), 'delete' => site_url('administrator/subsidiaries/delete'), 'csrfName' => $this->security->get_csrf_token_name(), 'csrfHash' => $this->security->get_csrf_hash())); ?>;</script>
<script src="<?php echo base_url('assets/js/jquery-3.5.1.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/rms-sidebar.js?v=20260803-1'); ?>"></script>
<script src="<?php echo base_url('assets/js/rms-header.js?v=20260731-1'); ?>"></script>
<script src="<?php echo $asset_url('assets/js/rms-subsidiaries.js'); ?>"></script>
</body></html>
