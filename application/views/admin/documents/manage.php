<?php
defined('BASEPATH') or exit('No direct script access allowed');

$safe_name = isset($display_name) && $display_name !== ''
    ? $display_name
    : $username;

$safe_position = isset($position) && $position !== ''
    ? $position
    : 'Administrator';

$safe_routes = isset($routes) && is_array($routes)
    ? $routes
    : array();

$route_url = function ($key, $fallback) use ($safe_routes) {
    return site_url(
        isset($safe_routes[$key])
            ? $safe_routes[$key]
            : $fallback
    );
};

$name_parts = preg_split('/\s+/', trim($safe_name));

$initials = isset($name_parts[0][0])
    ? strtoupper($name_parts[0][0])
    : 'A';

if (count($name_parts) > 1) {
    $last_name = $name_parts[count($name_parts) - 1];

    if (isset($last_name[0])) {
        $initials .= strtoupper($last_name[0]);
    }
}

$active_label = isset($levels[$active_level])
    ? $levels[$active_level]
    : 'Filename';

$active_parent_id = isset($active_parent_id)
    ? (int) $active_parent_id
    : 0;

$current_folder = isset($current_folder) && is_array($current_folder)
    ? $current_folder
    : array();

/*
 * The small heading names the records shown in the table (Subfolder1,
 * Subfolder2, and so on). The large heading names the directory currently
 * open, such as "08 AUG". At the Manage Documents root it remains Filename.
 */
$current_directory_label = $active_label;

if (
    $active_level > 0 &&
    isset($current_folder['record_name']) &&
    trim((string) $current_folder['record_name']) !== ''
) {
    $current_directory_label = $current_folder['record_name'];
}

$manage_root_url = site_url('administrator/documents');
$encrypted_browse_urls = isset($encrypted_browse_urls) && is_array($encrypted_browse_urls)
    ? $encrypted_browse_urls
    : array();

/**
 * Prefer the opaque encrypted path when the controller supplied one.
 * Falls back to the classic query-string form only if the map is missing.
 */
$browse_url = function ($level, $parent_id) use ($manage_root_url, $encrypted_browse_urls) {
    $key = (int) $level . ':' . (int) $parent_id;
    if (isset($encrypted_browse_urls[$key]) && $encrypted_browse_urls[$key] !== '') {
        return $encrypted_browse_urls[$key];
    }
    if ((int) $level <= 0) {
        return $manage_root_url;
    }
    return $manage_root_url . '?' . http_build_query(array(
        'level' => (int) $level,
        'parent_id' => (int) $parent_id
    ));
};

$breadcrumbs = array(
    array(
        'label' => 'Manage Documents',
        'url' => $active_level > 0 ? $manage_root_url : ''
    )
);

if ($active_level > 0 && !empty($current_folder)) {
    $file_id = isset($current_folder['file_id'])
        ? (int) $current_folder['file_id']
        : 0;

    $filename = isset($current_folder['filename'])
        ? $current_folder['filename']
        : '';

    if ($file_id > 0 && $filename !== '') {
        $breadcrumbs[] = array(
            'label' => $filename,
            'record_id' => $file_id,
            'record_level' => 0,
            'pin_parent_id' => 0,
            'publish_status' => isset($current_folder['filename_publish_status'])
                ? (int) $current_folder['filename_publish_status']
                : (isset($current_folder['publish_status'])
                    ? (int) $current_folder['publish_status'] : 1),
            'url' => $active_level > 1
                ? $browse_url(1, $file_id)
                : ''
        );
    }

    for ($level = 1; $level < $active_level; $level++) {
        $id_field = 'subfolder' . $level . '_id';
        $name_field = 'subfolder' . $level . '_name';

        if (
            empty($current_folder[$id_field]) ||
            empty($current_folder[$name_field])
        ) {
            continue;
        }

        $breadcrumbs[] = array(
            'label' => $current_folder[$name_field],
            'record_id' => (int) $current_folder[$id_field],
            'record_level' => $level,
            'pin_parent_id' => $level === 1
                ? $file_id
                : (int) $current_folder['subfolder' . ($level - 1) . '_id'],
            /* Every level keeps its own DB status; never inherit the child. */
            'publish_status' => $level === $active_level - 1
                ? (int) $current_folder['publish_status']
                : (isset($current_folder['subfolder' . $level . '_publish_status'])
                    ? (int) $current_folder['subfolder' . $level . '_publish_status']
                    : 1),
            'url' => $level < $active_level - 1
                ? $browse_url($level + 1, (int) $current_folder[$id_field])
                : ''
        );
    }
}

/* Current hierarchy labels let SweetAlert identify every blocked record. */
$current_path_labels = array();
foreach ($breadcrumbs as $breadcrumb_index => $breadcrumb) {
    if ($breadcrumb_index > 0 && isset($breadcrumb['label'])) {
        $current_path_labels[] = (string) $breadcrumb['label'];
    }
}

$upload_paths = isset($upload_paths) && is_array($upload_paths)
    ? $upload_paths : array();
$subsidiaries = isset($subsidiaries) && is_array($subsidiaries)
    ? $subsidiaries : array();
$departments = isset($departments) && is_array($departments)
    ? $departments : array();
$subfolder_parent_paths = isset($subfolder_parent_paths) && is_array($subfolder_parent_paths)
    ? $subfolder_parent_paths : array();
$current_document_count = isset($current_document_count)
    ? (int) $current_document_count : 0;

/*
 * Only the Dashboard shortcut unlocks the destination selector. Normal
 * in-folder uploads remain pinned to the exact highlighted leaf path.
 */
$dashboard_upload_mode =
    (string) $this->input->get('open_upload', TRUE) === '1';

/*
 * Command-bar rules for the unified browser. The current folder is selected
 * automatically; parent folders that are not permitted leaf destinations do
 * not expose Upload. Server-side validation remains the final authority.
 */
$current_record_level = (int) $active_level - 1;
$current_can_upload = FALSE;
foreach ($upload_paths as $available_path) {
    $available_level = isset($available_path['record_level'])
        ? (int) $available_path['record_level']
        : (isset($available_path['level']) ? (int) $available_path['level'] : 0);
    if (
        $active_level > 0 &&
        $available_level === $current_record_level &&
        !empty($available_path['record_id']) &&
        (int) $available_path['record_id'] === $active_parent_id
    ) {
        $current_can_upload = TRUE;
        break;
    }
}

$build_path_label = function ($path) {
    $parts = array();
    if (!empty($path['sub_name'])) {
        $parts[] = $path['sub_name'];
    }
    if (!empty($path['dept_name'])) {
        $parts[] = $path['dept_name'];
    }
    if (!empty($path['filename'])) {
        $parts[] = $path['filename'];
    } elseif (!empty($path['record_name'])) {
        $parts[] = $path['record_name'];
    }
    for ($level = 1; $level <= 10; $level++) {
        $name_field = 'subfolder' . $level . '_name';
        $legacy_field = 'subfolder' . $level;

        if (!empty($path[$name_field])) {
            $parts[] = $path[$name_field];
        } elseif (!empty($path[$legacy_field])) {
            $parts[] = $path[$legacy_field];
        }
    }

    return '/' . implode('/', $parts);
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        <?php echo html_escape($page_title); ?>
        |
        <?php echo html_escape($system_name); ?>
    </title>

    <link
        rel="stylesheet"
        href="<?php echo base_url(
                    'assets/css/rms-dashboard.css?v=20260731-2'
                ); ?>">
    <link
        rel="stylesheet"
        href="<?php echo base_url(
                    'assets/css/jquery.dataTables.min.css?v=1.13.11'
                ); ?>">
    <link
        rel="stylesheet"
        href="<?php echo base_url(
                    /* VIEWER FIX: new version forces the browser to load the updated viewer CSS. */
                    'assets/css/rms-documents.css?v=20260907-upload-toaster-v3'
                ); ?>">
    <link
        rel="stylesheet"
        href="<?php echo base_url(
                    'assets/css/rms-sidebar.css?v=20260803-2'
                ); ?>">

    <link
        rel="stylesheet"
        href="<?php echo base_url(
                    'assets/css/rms-header.css?v=20260731-1'
                ); ?>">

    <link
        rel="stylesheet"
        href="<?php echo base_url(
                    'assets/css/rms-footer.css?v=20260803-1'
                ); ?>">
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


</head>

<body>
    <div class="dashboard-app">
        <?php
        $this->load->view(
            'admin/partials/sidebar',
            array(
                'sidebar_active' => 'documents',
                'sidebar_show_system' => TRUE,
                'initials' => $initials,
                'safe_display_name' => $safe_name,
                'safe_position' => $safe_position,
                'route_url' => $route_url
            )
        );
        ?>

        <main class="main-area">
            <?php
            $this->load->view(
                'admin/partials/header',
                array(
                    'header_kicker' => 'DOCUMENTS DIRECTORY',
                    'header_title' => 'Documents',
                    'header_stat_label' => 'Total documents',
                    'header_stat_value' => number_format($total),
                    'header_stat_id' => 'documents-matching-total',
                    'header_stat_icon' => 'documents',
                    'header_logout_url' => $route_url(
                        'logout',
                        'administrator/logout'
                    ),
                    'initials' => $initials,
                    'safe_display_name' => $safe_name,
                    'safe_position' => $safe_position
                )
            );
            ?>
            <!-- Documents-only topbar pin UI. JavaScript moves this beside Total documents. -->
            <div class="documents-pinned-topbar" id="documents-pinned-topbar" hidden>
                <button type="button" class="documents-pinned-stat" id="documents-pinned-stat"
                    aria-expanded="false" aria-controls="documents-pinned-popover">
                    <span class="documents-pinned-stat-icon" aria-hidden="true"><i class="bi bi-pin-angle-fill"></i></span>
                    <span class="documents-pinned-stat-copy">
                        <strong id="documents-pinned-count">0</strong>
                        <small>Pinned items</small>
                    </span>
                </button>

                <section class="documents-pinned-popover" id="documents-pinned-popover" hidden
                    aria-label="Pinned Items">
                    <header class="documents-pinned-popover-header">
                        <strong>Pinned Items</strong>
                        <small>Your shortcuts</small>
                    </header>
                    <div class="documents-pinned-search-wrap">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" id="documents-pinned-search"
                            placeholder="Search pinned items..." autocomplete="off">
                        <button type="button" id="documents-pinned-search-clear"
                            aria-label="Clear pinned-item search" hidden>&times;</button>
                    </div>
                    <div class="documents-pinned-filters" role="tablist" aria-label="Filter pinned items">
                        <button type="button" class="is-active" data-pinned-filter="all">All</button>
                        <button type="button" data-pinned-filter="folder">Folders</button>
                        <button type="button" data-pinned-filter="document">Documents</button>
                    </div>
                    <div class="documents-pinned-results" id="documents-pinned-results"></div>
                    <button type="button" class="documents-pinned-view-all" id="documents-pinned-view-all">
                        View all pinned items
                    </button>
                </section>
            </div>
            <div class="documents-content">

                <section class="documents-panel">
                    <div class="documents-heading">
                        <?php if ($active_level > 0 && !empty($current_folder)): ?>
                            <?php
                            $current_status = isset($current_folder['publish_status'])
                                ? (int) $current_folder['publish_status'] : 1;
                            $current_child_count = isset($current_folder['child_count'])
                                ? (int) $current_folder['child_count'] : 0;
                            $current_record_id = isset($current_folder['record_id'])
                                ? (int) $current_folder['record_id'] : $active_parent_id;
                            $context_path = implode(' / ', $current_path_labels);
                            ?>
                            <section class="documents-folder-context <?php echo $current_status === 1 ? 'is-published' : 'is-unpublished'; ?>"
                                data-pin-record-id="<?php echo $current_record_id; ?>"
                                data-pin-level="<?php echo (int) $current_record_level; ?>"
                                data-pin-parent-id="<?php echo $current_record_level === 0 ? 0 : (int) $breadcrumbs[count($breadcrumbs) - 1]['pin_parent_id']; ?>">
                                <div class="documents-context-icon-wrap">
                                    <span class="documents-context-icon"><i class="bi bi-folder-fill" aria-hidden="true"></i></span>
                                    <span class="documents-context-pin" title="Pinned" hidden><i class="bi bi-pin-angle-fill"></i></span>
                                </div>
                                <div class="documents-context-copy">
                                    <h4><?php echo html_escape($context_path); ?></h4>
                                    <div class="documents-context-meta">
                                        <span class="documents-context-status"><?php echo $current_status === 1 ? 'PUBLISHED' : 'UNPUBLISHED'; ?></span>
                                        <span>ID #<?php echo $current_record_id; ?> &middot; <?php echo $current_child_count; ?> subfolders, <?php echo $current_document_count; ?> files</span>
                                    </div>
                                    <p><?php echo $current_status === 1
                                            ? 'This folder is published and available according to the existing RMS permissions.'
                                            : 'This folder is unpublished. Only authorized unpublished paths are available for changes.'; ?></p>
                                </div>
                                <div class="documents-context-actions">
                                    <div class="doc-actions documents-context-options">
                                        <button type="button"
                                            class="doc-actions-toggle"
                                            id="documents-context-options-toggle"
                                            title="More options"
                                            aria-label="More options"
                                            aria-haspopup="true"
                                            aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <div class="doc-actions-menu documents-context-options-menu" id="documents-context-options-menu" hidden>
                                            <!-- Unpin / Pin -->
                                            <button type="button"
                                                class="doc-action-item pin-action"
                                                data-action="toggle-pin"
                                                data-item-type="folder"
                                                data-record-level="<?php echo (int) $current_record_level; ?>"
                                                data-parent-id="<?php echo $current_record_level === 0 ? 0 : (int) $breadcrumbs[count($breadcrumbs) - 1]['pin_parent_id']; ?>"
                                                data-record-id="<?php echo $current_record_id; ?>"
                                                id="documents-context-pin-action">
                                                <i class="bi bi-pin-angle-fill" aria-hidden="true"></i>
                                                <span class="pin-label">Unpin</span>
                                            </button>

                                            <?php if (!empty($current_can_change_status)): ?>
                                                <!-- Publish / Unpublish (same validations as before) -->
                                                <button type="button"
                                                    class="doc-action-item"
                                                    id="documents-current-status-action"
                                                    data-record-id="<?php echo $current_record_id; ?>"
                                                    data-record-level="<?php echo (int) $current_record_level; ?>"
                                                    data-current-status="<?php echo $current_status; ?>"
                                                    data-target-status="<?php echo $current_status === 1 ? 0 : 1; ?>"
                                                    data-action="<?php echo $current_status === 1 ? 'unpublish' : 'publish'; ?>">
                                                    <?php if ($current_status === 1): ?>
                                                        <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                                        <span>Unpublish</span>
                                                    <?php else: ?>
                                                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                                                        <span>Publish</span>
                                                    <?php endif; ?>
                                                </button>
                                            <?php endif; ?>

                                            <!-- Rename -->
                                            <button
                                                type="button"
                                                class="doc-action-item"
                                                id="documents-context-rename"
                                                data-action="rename-current-folder">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                Rename
                                            </button>
                                            <!-- File information -->
                                            <button type="button"
                                                class="doc-action-item"
                                                id="documents-context-info"
                                                data-record-id="<?php echo $current_record_id; ?>"
                                                data-record-level="<?php echo (int) $current_record_level; ?>"
                                                data-status="<?php echo $current_status; ?>"
                                                data-child-count="<?php echo $current_child_count; ?>"
                                                data-file-count="<?php echo $current_document_count; ?>"
                                                data-name="<?php echo html_escape($context_path); ?>">
                                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                                <span>File information</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="documents-context-legend" aria-label="Record color legend">
                                    <span><i class="legend-dot published"></i>Published</span>
                                    <span><i class="legend-dot unpublished"></i>Unpublished</span>
                                    <span><i class="legend-dot file"></i>File</span>
                                </div>

                            </section>
                        <?php endif; ?>
                        <div class="documents-heading-actions">
                            <!-- Relocated from the removed hero; count stays server-driven. -->

                        </div>
                    </div>
                    <nav
                        class="documents-breadcrumbs"
                        aria-label="Document folder path">
                        <?php foreach ($breadcrumbs as $index => $breadcrumb): ?>
                            <?php
                            $crumb_status_class = '';
                            if ($index > 0 && isset($breadcrumb['publish_status'])) {
                                $crumb_status_class = (int) $breadcrumb['publish_status'] === 1
                                    ? ' is-published'
                                    : ' is-unpublished';
                            }
                            $crumb_pin_attributes = $index > 0
                                ? ' data-pin-record-id="' . (int) $breadcrumb['record_id'] .
                                '" data-pin-level="' . (int) $breadcrumb['record_level'] .
                                '" data-pin-parent-id="' . (int) $breadcrumb['pin_parent_id'] . '"'
                                : '';
                            ?>
                            <?php if ($index > 0): ?>
                                <span class="documents-breadcrumb-sep" aria-hidden="true">›</span>
                            <?php endif; ?>

                            <?php if ($breadcrumb['url'] !== ''): ?>
                                <a class="documents-breadcrumb-item<?php echo $crumb_status_class; ?>"
                                    <?php echo $crumb_pin_attributes; ?>
                                    href="<?php echo html_escape($breadcrumb['url']); ?>">
                                    <?php if ($index === 0): ?>
                                        <svg class="documents-crumb-icon documents-crumb-home" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path fill="currentColor" d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z" />
                                        </svg>
                                    <?php else: ?>
                                        <svg class="documents-crumb-icon documents-crumb-folder" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path fill="currentColor" d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z" />
                                        </svg>
                                    <?php endif; ?>
                                    <?php echo html_escape($breadcrumb['label']); ?>
                                </a>
                            <?php else: ?>
                                <strong class="documents-breadcrumb-current documents-breadcrumb-item<?php echo $crumb_status_class; ?>"
                                    <?php echo $crumb_pin_attributes; ?> aria-current="page">
                                    <svg class="documents-crumb-icon documents-crumb-folder" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path fill="currentColor" d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z" />
                                    </svg>
                                    <?php echo html_escape($breadcrumb['label']); ?>
                                </strong>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($active_level > 0): ?>
                            <?php
                            $back_index = count($breadcrumbs) - 2;
                            $back_url = isset($breadcrumbs[$back_index]['url'])
                                ? $breadcrumbs[$back_index]['url']
                                : $manage_root_url;
                            ?>
                            <a
                                class="documents-back"
                                href="<?php echo html_escape($back_url); ?>">
                                &larr; Back
                            </a>
                        <?php endif; ?>

                    </nav>
                    <div class="documents-toolbar">
                        <?php if ($active_level > 0 && $current_can_upload): ?>
                            <button type="button" class="document-action primary" data-modal-open="documents-upload-modal">
                                <svg class="document-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm1 7V3.5L18.5 9H15zM8 13h8v2H8v-2zm0 4h5v2H8v-2z" />
                                </svg>
                                <span>Upload documents</span>
                            </button>
                        <?php endif; ?>

                        <?php if ($active_level === 0): ?>
                            <button type="button" class="document-action" data-modal-open="filename-modal">
                                <svg class="document-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 13h8v2H8v-2zm0 4h5v2H8v-2z" />
                                </svg>
                                <span>New filename</span>
                            </button>
                        <?php endif; ?>

                        <?php if ($active_level > 0): ?>
                            <button type="button" class="document-action" data-modal-open="subfolder-modal">
                                <svg class="document-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2zm2 6h2v2h2v2h-2v2h-2v-2h-2v-2h2v-2z" />
                                </svg>
                                <span>New folder</span>
                            </button>
                        <?php endif; ?>

                        <div class="documents-bulk">
                            <button type="button" class="document-action" id="documents-bulk-toggle" aria-expanded="false">
                                <span>Bulk actions</span><i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="documents-bulk-menu" id="documents-bulk-menu" hidden>
                                <button type="button" id="documents-publish" disabled>
                                    <svg class="document-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" />
                                    </svg>
                                    <span>Publish selected folders</span>
                                </button>

                                <button
                                    type="button"
                                    id="documents-unpublish"
                                    disabled>
                                    <svg class="document-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z" />
                                    </svg>
                                    <span>Unpublish selected folders</span>
                                </button>
                                <button type="button" id="documents-download-selected" disabled><i class="bi bi-download"></i> Download selected file</button>
                                <button type="button" id="documents-transfer-files" disabled><i class="bi bi-arrow-left-right"></i> Transfer selected files</button>
                                <button type="button" id="documents-delete-files" disabled><i class="bi bi-trash"></i> Delete selected items</button>
                            </div>
                        </div>

                        <span class="documents-selection" id="documents-selection">
                            No records selected
                        </span>
                        <span class="documents-toolbar-spacer"></span>
                        <div class="documents-search-wrap">
                            <label class="documents-folder-search">
                                <i class="bi bi-search" aria-hidden="true"></i>

                                <input
                                    id="documents-folder-search"
                                    type="search"
                                    placeholder="Search this folder"
                                    autocomplete="off">
                            </label>


                        </div>

                        <button
                            type="button"
                            class="documents-search-submit"
                            id="documents-search-submit">
                            Search
                        </button>
                        <button
                            type="button"
                            class="documents-tool-icon"
                            id="documents-search-reset"
                            title="Reset search"
                            aria-label="Reset search">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>

                    <div
                        class="document-message"
                        id="documents-message"
                        role="alert"></div>

                    <div class="documents-table-wrap">
                        <table
                            class="documents-table display"
                            id="documents-table">
                            <thead>
                                <tr>
                                    <th class="check-column sorting_disabled">
                                        <input type="checkbox" id="documents-all" aria-label="Select all records on this page">
                                    </th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Modified</th>
                                    <th>Owner</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    <p class="documents-workspace-note"><i class="bi bi-info-circle"></i> Double-click a folder to navigate deeper. Double-click a file to preview.</p>
                </section>

                <?php
                $this->load->view(
                    'admin/partials/footer',
                    array(
                        'footer_company_name' => 'RMS Administration',
                        'footer_system_name' => $system_name
                    )
                );
                ?>
            </div>
        </main>
    </div>
    <div class="rms-modal" id="documents-upload-modal" role="dialog" aria-modal="true" aria-labelledby="documents-upload-title" data-backdrop="static" data-keyboard="false">
        <div class="rms-modal-dialog rms-upload-modal-dialog">
            <?php echo form_open_multipart('administrator/documents/upload', array('id' => 'modal-upload-form')); ?>

            <div class="rms-modal-header">
                <span class="rms-modal-eyebrow">Documents Module · Upload</span>
                <h2 class="rms-modal-title" id="documents-upload-title">Upload New Documents</h2>
                <p class="rms-modal-subtitle">Upload original and optional watermark files to an unpublished path.</p>
                <button type="button" class="rms-modal-close" data-modal-close aria-label="Close">&times;</button>
            </div>

            <div class="rms-modal-body">
                <div
                    class="modal-alert"
                    id="upload-message"
                    role="alert"
                    aria-live="polite"
                    style="display: none;"></div>

                <?php if (empty($upload_paths)): ?>
                    <div class="modal-alert error">No unpublished destination is available. Unpublish the folder path first.</div>
                <?php else: ?>
                    <span class="modal-section-label">Upload Details</span>
                    <div class="modal-section-title">
                        <h3>Choose destination &amp; files</h3>
                        <span class="modal-required-note">Required fields</span>
                    </div>

                    <div class="modal-field full">
                        <label for="modal-upload-path">Destination Path <span class="req">*</span></label>
                        <select id="modal-upload-path" name="record_id" required
                            <?php if (!$dashboard_upload_mode): ?>
                            style="pointer-events: none;
               font-size: 15px;
               background-color: #f1f5f0;
               color: #555;
               cursor: not-allowed;
               appearance: none;
               -webkit-appearance: none;
               -moz-appearance: none;
               background-image: none;"
                            <?php endif; ?>>
                            <?php
                            $open_upload_group = -1;
                            $current_upload_level = (int) $active_level - 1;
                            ?>
                            <?php foreach ($upload_paths as $path): ?>
                                <?php
                                $path_id = !empty($path['record_id'])
                                    ? (int) $path['record_id']
                                    : 0;
                                $path_level = isset($path['record_level'])
                                    ? (int) $path['record_level']
                                    : (isset($path['level'])
                                        ? (int) $path['level']
                                        : 0);
                                $path_label = $build_path_label($path);
                                $is_current_upload_path = $active_level > 0 &&
                                    $path_level === $current_upload_level &&
                                    $path_id === $active_parent_id;
                                ?>

                                <?php if ($path_id > 0 && $path_label !== ''): ?>
                                    <?php if ($open_upload_group !== $path_level): ?>
                                        <?php if ($open_upload_group !== -1): ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <optgroup label="<?php echo $path_level === 0
                                                                ? 'Filename'
                                                                : 'Subfolder' . $path_level; ?>">
                                            <?php $open_upload_group = $path_level; ?>
                                        <?php endif; ?>

                                        <option
                                            value="<?php echo $path_id; ?>"
                                            data-level="<?php echo $path_level; ?>"
                                            data-file-id="<?php echo !empty($path['file_id']) ? (int) $path['file_id'] : 0; ?>"
                                            data-sub-id="<?php echo !empty($path['sub_id']) ? (int) $path['sub_id'] : 0; ?>"
                                            data-dept-id="<?php echo !empty($path['dept_id']) ? (int) $path['dept_id'] : 0; ?>"
                                            <?php echo $is_current_upload_path ? 'selected' : ''; ?>>
                                            <?php echo html_escape($path_label); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($open_upload_group !== -1): ?>
                                        </optgroup>
                                    <?php endif; ?>
                        </select>
                        <input type="hidden" name="record_level" id="modal-record-level">
                        <input type="hidden" name="file_id" id="modal-file-id">
                        <input type="hidden" name="sub_id" id="modal-sub-id">
                        <input type="hidden" name="dept_id" id="modal-dept-id">
                        <input type="hidden" name="return_level" value="<?php echo (int) $active_level; ?>">
                        <input type="hidden" name="return_parent_id" value="<?php echo (int) $active_parent_id; ?>">

                    </div>

                    <div class="modal-grid">
                        <div class="modal-field">
                            <label for="modal-original-files">
                                Original Files <span class="req">*</span>
                            </label>

                            <div
                                class="documents-drop-zone is-disabled"
                                data-input-id="modal-original-files"
                                data-file-role="original"
                                role="button"
                                tabindex="0"
                                aria-disabled="true">

                                <span class="documents-drop-icon" aria-hidden="true">
                                    &#8679;
                                </span>

                                <strong>Drag and drop original files here</strong>
                                <span>or click to browse</span>

                                <input
                                    type="file"
                                    id="modal-original-files"
                                    name="original_files[]"
                                    accept=".jpg,.jpeg,.png,.doc,.docx,.pdf,.xls,.xlsx"
                                    multiple
                                    disabled>

                                <small
                                    class="documents-file-summary"
                                    id="modal-original-summary">
                                    No files selected
                                </small>
                            </div>

                            <!-- Windows filename-validation message -->
                            <div
                                class="rms-upload-file-error"
                                id="rms-original-files-error"
                                role="alert"
                                aria-live="polite"
                                hidden>
                            </div>

                            <span class="modal-help">
                                Allowed: JPG, JPEG, PNG, DOC, DOCX, PDF and XLSX;
                                maximum 15 MB each.
                            </span>
                        </div>

                        <div class="modal-field">
                            <label for="modal-watermark-files">
                                Watermark Files
                            </label>

                            <div
                                class="documents-drop-zone is-disabled"
                                data-input-id="modal-watermark-files"
                                data-file-role="watermark"
                                role="button"
                                tabindex="0"
                                aria-disabled="true">

                                <span class="documents-drop-icon" aria-hidden="true">
                                    &#8679;
                                </span>

                                <strong>Drag and drop watermark files here</strong>
                                <span>or click to browse</span>

                                <input
                                    type="file"
                                    id="modal-watermark-files"
                                    name="watermark_files[]"
                                    accept=".jpg,.jpeg,.png,.doc,.docx,.pdf,.xlsx"
                                    multiple
                                    disabled>

                                <small
                                    class="documents-file-summary"
                                    id="modal-watermark-summary">
                                    No files selected
                                </small>
                            </div>

                            <!-- Windows filename-validation message -->
                            <div
                                class="rms-upload-file-error"
                                id="rms-watermark-files-error"
                                role="alert"
                                aria-live="polite"
                                hidden>
                            </div>

                            <span class="modal-help">
                                Optional. When selected, the number of watermark
                                files must match the originals.
                            </span>
                        </div>
                    </div>

                    <div class="documents-upload-progress" id="modal-upload-progress" hidden>
                        <span class="documents-upload-progress-bar" id="modal-upload-progress-bar"></span>
                    </div>
                    <span class="documents-upload-progress-text" id="modal-upload-progress-text"></span>
                <?php endif; ?>
            </div>

            <div class="rms-modal-footer">
                <button type="button" class="modal-cancel" data-modal-close>Cancel</button>
                <button type="submit" class="document-action primary" id="modal-upload-submit" disabled>Upload Documents</button>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>

    <div class="rms-modal" id="filename-modal" role="dialog" aria-modal="true" aria-labelledby="filename-modal-title">
        <div class="rms-modal-dialog">
            <form id="filename-form" method="post" action="<?php echo site_url('administrator/documents/create-filename'); ?>">
                <div class="rms-modal-header">
                    <span class="rms-modal-eyebrow">Documents Module · Filename</span>
                    <h2 class="rms-modal-title" id="filename-modal-title">Add New Filename</h2>
                    <p class="rms-modal-subtitle">Create a new top-level filename under a subsidiary and department.</p>
                    <button type="button" class="rms-modal-close" data-modal-close aria-label="Close">&times;</button>
                </div>

                <div class="rms-modal-body">
                    <div class="modal-alert" id="filename-message"></div>

                    <span class="modal-section-label">Filename Details</span>
                    <div class="modal-section-title">
                        <h3>Create filename record</h3>
                        <span class="modal-required-note">Required fields</span>
                    </div>

                    <div class="modal-grid">
                        <div class="modal-field">
                            <label for="filename-sub">Subsidiary <span class="req">*</span></label>
                            <select id="filename-sub" name="sub_id" required>
                                <option value="">Select subsidiary</option>
                                <?php foreach ($subsidiaries as $item): ?>
                                    <option value="<?php echo (int) $item['sub_id']; ?>">
                                        <?php echo html_escape($item['sub_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-field">
                            <label for="filename-dept">Department <span class="req">*</span></label>
                            <select id="filename-dept" name="dept_id" required disabled>
                                <option value="">Select department</option>
                                <?php foreach ($departments as $item): ?>
                                    <option
                                        value="<?php echo (int) $item['dept_id']; ?>"
                                        data-sub-id="<?php echo (int) $item['sub_id']; ?>">
                                        <?php echo html_escape($item['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="modal-field full">
                        <label for="new-filename">
                            Filename <span class="req">*</span>
                        </label>
                        <input
                            type="text"
                            name="filename"
                            id="new-filename"
                            maxlength="250"
                            required
                            data-windows-name
                            data-windows-name-type="filename"
                            data-validation-button="#save-new-filename">

                        <div
                            class="windows-name-error"
                            data-error-for="new-filename"
                            role="alert"
                            aria-live="polite"
                            hidden>
                        </div>
                    </div>
                </div>

                <div class="rms-modal-footer">
                    <button type="button" class="modal-cancel" data-modal-close>Cancel</button>
                    <button type="submit" class="modal-submit" id="save-new-filename">Save Filename</button>
                </div>
            </form>
        </div>
    </div>

    <div class="rms-modal" id="subfolder-modal" role="dialog" aria-modal="true" aria-labelledby="subfolder-modal-title">
        <div class="rms-modal-dialog">
            <form id="subfolder-form" method="post" action="<?php echo site_url('administrator/documents/create-subfolder'); ?>">
                <div class="rms-modal-header">
                    <span class="rms-modal-eyebrow">Documents Module · Subfolder</span>
                    <h2 class="rms-modal-title" id="subfolder-modal-title">Add New Subfolder</h2>
                    <p class="rms-modal-subtitle">Create a subfolder one level below an unpublished path.</p>
                    <button type="button" class="rms-modal-close" data-modal-close aria-label="Close">&times;</button>
                </div>

                <div class="rms-modal-body">
                    <div class="modal-alert" id="subfolder-message"></div>

                    <span class="modal-section-label">Subfolder Details</span>
                    <div class="modal-section-title">
                        <h3>Create subfolder</h3>
                        <span class="modal-required-note">Required fields</span>
                    </div>

                    <div class="modal-field full">
                        <label for="subfolder-parent">Path <span class="req">*</span></label>

                        <!-- The controller validates this stable database ID; the label is presentation only. -->
                        <select id="subfolder-parent" name="parent_id" required
                            style="pointer-events: none; 
               font-size: 15px;
               background-color: #f1f5f0; 
               color: #555; 
               cursor: not-allowed;
               appearance: none;
               -webkit-appearance: none;
               -moz-appearance: none;
               background-image: none;">
                            <?php
                            $open_parent_group = -1;
                            $current_parent_level = (int) $active_level - 1;
                            ?>
                            <?php foreach ($subfolder_parent_paths as $path): ?>
                                <?php
                                $parent_id = !empty($path['record_id'])
                                    ? (int) $path['record_id']
                                    : 0;
                                $parent_level = isset($path['record_level'])
                                    ? (int) $path['record_level']
                                    : 0;
                                $path_label = $build_path_label($path);
                                $is_current_path = $active_level > 0 &&
                                    $parent_level === $current_parent_level &&
                                    $parent_id === $active_parent_id;
                                ?>

                                <?php if ($parent_id > 0 && $parent_level < 10): ?>
                                    <?php if ($open_parent_group !== $parent_level): ?>
                                        <?php if ($open_parent_group !== -1): ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <optgroup label="<?php echo $parent_level === 0
                                                                ? 'Filename'
                                                                : 'Subfolder' . $parent_level; ?>">
                                            <?php $open_parent_group = $parent_level; ?>
                                        <?php endif; ?>

                                        <option
                                            value="<?php echo $parent_id; ?>"
                                            data-level="<?php echo $parent_level; ?>"
                                            <?php echo $is_current_path ? 'selected' : ''; ?>>
                                            <?php echo html_escape($path_label); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($open_parent_group !== -1): ?>
                                        </optgroup>
                                    <?php endif; ?>
                        </select>
                        <input
                            type="hidden"
                            id="subfolder-parent-level"
                            name="parent_level"
                            value="">
                        <span class="modal-help">
                            Only unpublished paths are shown. The new folder
                            will be created one level below the selected path.
                        </span>
                    </div>

                    <div class="modal-field full">
                        <label for="new-subfolder-name">Subfolder Name <span class="req">*</span></label>

                        <input
                            type="text"
                            name="subfolder_name"
                            id="new-subfolder-name"
                            autocomplete="off"
                            maxlength="250"
                            required
                            data-windows-name
                            data-windows-name-type="subfolder"
                            data-validation-button="#save-new-subfolder">

                        <div
                            class="windows-name-error"
                            data-error-for="new-subfolder-name"
                            role="alert"
                            aria-live="polite"
                            hidden>
                        </div>
                    </div>

                </div>

                <div class="rms-modal-footer">
                    <button type="button" class="modal-cancel" data-modal-close>Cancel</button>
                    <button type="submit" class="modal-submit" id="save-new-subfolder">Save Subfolder</button>
                </div>
            </form>
        </div>
    </div>

    <div class="rms-modal" id="document-edit-modal" role="dialog" aria-modal="true" aria-labelledby="document-edit-title">
        <div class="rms-modal-dialog rms-modal-dialog-wide">
            <form id="document-edit-form"
                method="post"
                action="<?php echo site_url('administrator/documents/save'); ?>">
                <input type="hidden" name="level" id="document-edit-level" value="">
                <input type="hidden" name="record_id" id="document-edit-id" value="">
                <div class="rms-modal-header">
                    <span class="rms-modal-eyebrow">Documents Module &bull; Edit Record</span>
                    <h2 class="rms-modal-title" id="document-edit-title">Edit Folder Details</h2>
                    <p class="rms-modal-subtitle">Update this RMS directory without leaving Manage Documents.</p>
                    <button type="button" class="rms-modal-close" data-modal-close>&times;</button>
                </div>
                <div class="rms-modal-body">
                    <span class="modal-section-label">Record Details</span>
                    <div class="modal-section-title">
                        <h3 id="document-edit-section-title">Filename Details</h3>
                        <span class="modal-required-note">Required fields</span>
                    </div>
                    <div class="modal-alert" id="document-edit-message"></div>
                    <div class="modal-field">
                        <label for="document-edit-name">Name *</label>
                        <input
                            type="text"
                            id="document-edit-name"
                            name="record_name"
                            maxlength="250"
                            required
                            autocomplete="off"
                            data-windows-name
                            data-validation-button="#document-edit-submit">
                        <div
                            class="windows-name-error"
                            data-error-for="document-edit-name"
                            role="alert"
                            aria-live="polite"
                            hidden></div>
                    </div>
                    <div class="modal-grid" id="document-edit-location-fields">
                        <div class="modal-field">
                            <label for="document-edit-sub">Subsidiary *</label>
                            <select id="document-edit-sub" name="sub_id">
                                <option value="">Select subsidiary</option>
                                <?php foreach ($subsidiaries as $item): ?>
                                    <option value="<?php echo (int) $item['sub_id']; ?>"><?php echo html_escape($item['sub_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-field">
                            <label for="document-edit-dept">Department *</label>
                            <select id="document-edit-dept" name="dept_id">
                                <option value="">Select department</option>
                                <?php foreach ($departments as $item): ?>
                                    <option value="<?php echo (int) $item['dept_id']; ?>" data-sub-id="<?php echo (int) $item['sub_id']; ?>"><?php echo html_escape($item['dept_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <span class="modal-help">The matching directory will be renamed under both RMS storage roots.</span>
                </div>
                <div class="rms-modal-footer">
                    <button type="button" class="modal-cancel" data-modal-close>Cancel</button>
                    <button type="submit" class="modal-submit" id="document-edit-submit">Update Record</button>
                </div>
            </form>
        </div>
    </div>

    <div class="rms-modal" id="document-view-modal" role="dialog" aria-modal="true" aria-labelledby="document-view-title">
        <div class="rms-modal-dialog rms-modal-dialog-wide">
            <div class="rms-modal-header">
                <span class="rms-modal-eyebrow">Documents Module &bull; View Record</span>
                <h2 class="rms-modal-title" id="document-view-title">Folder Details</h2>
                <p class="rms-modal-subtitle">Review the current RMS record and directory location.</p>
                <button type="button" class="rms-modal-close" data-modal-close>&times;</button>
            </div>
            <div class="rms-modal-body">
                <div class="modal-alert" id="document-view-message"></div>
                <span class="modal-section-label">Record Details</span>
                <div class="modal-section-title">
                    <h3>Directory Information</h3>
                </div>
                <dl class="document-view-grid">
                    <div>
                        <dt>Name</dt>
                        <dd id="document-view-name">&mdash;</dd>
                    </div>
                    <div>
                        <dt>Level</dt>
                        <dd id="document-view-level">&mdash;</dd>
                    </div>
                    <div>
                        <dt>Subsidiary</dt>
                        <dd id="document-view-subsidiary">&mdash;</dd>
                    </div>
                    <div>
                        <dt>Department</dt>
                        <dd id="document-view-department">&mdash;</dd>
                    </div>
                    <div class="document-view-path">
                        <dt>Directory Path</dt>
                        <dd id="document-view-path">&mdash;</dd>
                    </div>
                </dl>
            </div>
            <div class="rms-modal-footer">
                <button type="button" class="modal-submit" data-modal-close>Close</button>
            </div>
        </div>
    </div>

    <div class="rms-modal documents-transfer-modal" id="documents-transfer-modal" role="dialog" aria-modal="true" aria-labelledby="documents-transfer-title">
        <div class="rms-modal-dialog rms-modal-dialog-sm">
            <div class="rms-modal-header"><span class="rms-modal-eyebrow">Documents Module · Transfer</span>
                <h2 class="rms-modal-title" id="documents-transfer-title">Transfer files</h2>
                <p class="rms-modal-subtitle">Choose an available unpublished destination.</p><button type="button" class="rms-modal-close" data-transfer-close aria-label="Close">&times;</button>
            </div>
            <div class="rms-modal-body">
                <div class="modal-alert" id="documents-transfer-message"></div>
                <label class="transfer-search"><i class="bi bi-search"></i><input type="search" id="documents-transfer-search" placeholder="Search unpublished folders" autocomplete="off"></label>
                <div class="transfer-destination-list">
                    <?php foreach ($upload_paths as $transfer_path): ?>
                        <?php $transfer_id = isset($transfer_path['record_id']) ? (int) $transfer_path['record_id'] : 0;
                        $transfer_level = isset($transfer_path['record_level']) ? (int) $transfer_path['record_level'] : 0;
                        $transfer_label = $build_path_label($transfer_path); ?>
                        <?php if ($transfer_id > 0): ?><button type="button" class="transfer-destination" data-target-id="<?php echo $transfer_id; ?>" data-target-level="<?php echo $transfer_level; ?>" data-search-text="<?php echo html_escape(strtolower($transfer_label)); ?>"><i class="bi bi-folder2"></i><span><?php echo html_escape($transfer_label); ?></span><i class="bi bi-chevron-right"></i></button><?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <p class="transfer-empty" id="documents-transfer-empty" hidden>No matching unpublished destination was found.</p>
            </div>
            <div class="rms-modal-footer"><button type="button" class="modal-cancel" data-transfer-close>Cancel</button><button type="button" class="modal-submit" id="documents-transfer-submit" disabled>Transfer files</button></div>
        </div>
    </div>

    <!-- Direct-file preview remains on the same unified folder page. -->
    <div class="unified-viewer" id="unified-file-viewer" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="unified-file-title">
        <div class="unified-viewer-card">
            <div class="unified-viewer-head">
                <!-- VIEWER FIX: close button moved to the far right of the header. -->
                <div class="unified-viewer-heading"><small>DOCUMENT VIEWER</small>
                    <h3 id="unified-file-title">Document preview</h3><small id="unified-file-position">File 1 of 1</small>
                </div>
                <div class="unified-viewer-actions">
                    <div class="unified-file-actions-menu"><button type="button" class="document-action" id="unified-file-actions-toggle" aria-expanded="false">Actions <i class="bi bi-chevron-down"></i></button>
                        <div class="unified-file-actions-dropdown" id="unified-file-actions-dropdown" hidden><button type="button" id="unified-file-transfer"><i class="bi bi-arrow-left-right"></i>Transfer</button><a id="unified-file-download" href="#"><i class="bi bi-download"></i>Download</a><button type="button" id="unified-file-rename"><i class="bi bi-pencil"></i>Rename</button><button type="button" class="danger" id="unified-file-delete"><i class="bi bi-trash"></i>Delete</button></div>
                    </div>
                </div>
                <button type="button" class="unified-viewer-close" data-close-unified-viewer aria-label="Close document viewer">&times;</button>
            </div>
            <div class="unified-viewer-toolbar">
                <button type="button" id="unified-zoom-out"><i class="bi bi-zoom-out"></i> Zoom Out</button>
                <span class="unified-viewer-zoom" id="unified-file-zoom">100%</span>
                <button type="button" id="unified-zoom-in"><i class="bi bi-zoom-in"></i> Zoom In</button>
                <button type="button" id="unified-fit-file">Fit to Screen</button>
                <button type="button" id="unified-actual-file">Actual Size</button>
                <span class="documents-toolbar-spacer"></span>
                <!-- VIEWER FIX: replace the old Ctrl-drag instruction with left-drag + Ctrl-wheel-zoom text. -->
                <small>Hold left mouse button and drag to move &middot; Hold Ctrl + mouse wheel to zoom &middot; &larr; &rarr; Previous / next file</small>
            </div>
            <div class="unified-viewer-body">
                <button type="button" class="unified-canvas-nav previous" id="unified-previous-file" aria-label="Previous file"><i class="bi bi-chevron-left"></i></button>
                <img id="unified-file-image" alt="Document preview" hidden>
                <iframe id="unified-file-frame" title="Document preview" hidden></iframe>
                <button type="button" class="unified-canvas-nav next" id="unified-next-file" aria-label="Next file"><i class="bi bi-chevron-right"></i></button>
                <?php if ($current_can_upload): ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="documents-drop-overlay" id="documents-drop-overlay"><strong><i class="bi bi-cloud-arrow-up"></i> Drop files to upload to this folder</strong></div>

    <script>
        window.RMS_DOCUMENTS = <?php echo json_encode(array(
                                    'dataUrl' => site_url('administrator/documents') . '?datatable=1',
                                    'publishUrl' => site_url('administrator/documents/publish'),
                                    'recordUrl' => site_url('administrator/documents/record'),
                                    'updateRecordUrl' => site_url('administrator/documents/save'),
                                    'deleteRecordUrl' => site_url('administrator/documents/delete'),
                                    'togglePinUrl' => site_url('administrator/documents/toggle-pin'),
                                    'pinnedItemsUrl' => site_url('administrator/documents/pinned_items'),
                                    'deleteUploadedUrl' => site_url('administrator/documents/delete_uploaded_files'),
                                    'renameUploadedUrl' => site_url('administrator/documents/rename_uploaded_file'),
                                    'transferUploadedUrl' => site_url('administrator/documents/transfer_uploaded_files'),
                                    'folderFilesUrl' => site_url('administrator/documents/view_documents') . '?datatable=1',
                                    'uploadUrl' => site_url('administrator/documents/upload'),
                                    'createFilenameUrl' => site_url('administrator/documents/create-filename'),
                                    'createSubfolderUrl' => site_url('administrator/documents/create-subfolder'),
                                    'level' => (int) $active_level,
                                    'parentId' => (int) $active_parent_id,
                                    'currentCanUpload' => $current_can_upload ? 1 : 0,
                                    /*
 * CURRENT FOLDER ACTION:
 * Exact opened-folder identifiers used by Publish/Unpublish.
 */
                                    'currentFolderId' => isset($current_record_id)
                                        ? (int) $current_record_id
                                        : 0,
                                    'currentFolderLevel' => isset($current_record_level)
                                        ? (int) $current_record_level
                                        : -1,
                                    'currentFolderStatus' => isset($current_status)
                                        ? (int) $current_status
                                        : -1,
                                    'currentCanChangeStatus' => !empty($current_can_change_status) ? 1 : 0,
                                    'openUpload' => $dashboard_upload_mode ? 1 : 0,
                                    'currentPath' => $current_path_labels,
                                    'csrfName' => $this->security->get_csrf_token_name(),
                                    'csrfHash' => $this->security->get_csrf_hash()
                                )); ?>;
    </script>

    <script src="<?php echo base_url(
                        'assets/js/jquery-3.5.1.min.js'
                    ); ?>"></script>

    <script src="<?php echo base_url(
                        'assets/js/jquery.dataTables.min.js?v=1.13.11'
                    ); ?>"></script>

    <script src="<?php echo base_url(
                        'assets/js/rms-sidebar.js?v=20260803-1'
                    ); ?>"></script>

    <script src="<?php echo base_url(
                        'assets/js/rms-header.js?v=20260731-1'
                    ); ?>"></script>

    <script src="<?php echo base_url(
                        /*
                         * Cache version includes Level 3 owner isolation and
                         * Level 4 unrestricted unpublished-path behavior.
                         */
                        /* VIEWER FIX: new version forces the browser to load the right-drag and scroll-lock code. */
                        'assets/js/rms-documents.js?v=20260907-upload-toaster-v3'
                    ); ?>"></script>
</body>

</html>