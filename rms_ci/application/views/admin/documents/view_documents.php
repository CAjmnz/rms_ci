<?php
defined('BASEPATH') or exit('No direct script access allowed');
$safe_name = isset($display_name) && $display_name !== '' ? $display_name : (isset($username) ? $username : 'Administrator');
$safe_position = isset($position) && $position !== '' ? $position : 'Administrator';
$safe_routes = isset($routes) && is_array($routes) ? $routes : array();
$route_url = function ($key, $fallback) use ($safe_routes) {
    return site_url(isset($safe_routes[$key]) ? $safe_routes[$key] : $fallback);
};
$name_parts = preg_split('/\\s+/', trim($safe_name));
$initials = isset($name_parts[0][0]) ? strtoupper($name_parts[0][0]) : 'A';
if (count($name_parts) > 1) {
    $last_name = $name_parts[count($name_parts) - 1];
    if (isset($last_name[0])) {
        $initials .= strtoupper($last_name[0]);
    }
}
$safe_folder_name = isset($current_folder['record_name']) && trim((string) $current_folder['record_name']) !== ''
    ? $current_folder['record_name']
    : 'Documents';
$manage_url = site_url('administrator/documents');
$total_documents = isset($total) ? (int) $total : 0;
$transfer_paths = isset($transfer_paths) && is_array($transfer_paths) ? $transfer_paths : array();
$build_path_label = function ($path) {
    $parts = array();
    foreach (array('sub_name', 'dept_name', 'filename') as $field) {
        if (!empty($path[$field])) $parts[] = $path[$field];
    }
    for ($level = 1; $level <= 10; $level++) {
        $field = 'subfolder' . $level . '_name';
        if (!empty($path[$field])) $parts[] = $path[$field];
    }
    return '/' . implode('/', $parts);
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
    <link rel="stylesheet" href="<?php echo base_url('assets/css/jquery.dataTables.min.css?v=1.13.11'); ?>">
    <!-- VIEWER FIX: new version forces the browser to load the updated shared documents CSS. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-documents.css?v=20260826-watermark-first-v22'); ?>">
    <!-- VIEWER FIX: new version forces the browser to load the updated modal CSS. -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-documents-view.css?v=20260824-viewer-fix-v18'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-sidebar.css?v=20260803-2'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-header.css?v=20260731-1'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-footer.css?v=20260803-1'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/bootstrap-icons.css'); ?>">
    
</head>
<body>
<div class="dashboard-app">
    <?php $this->load->view('admin/partials/sidebar', array(
        'sidebar_active' => 'documents',
        'sidebar_show_system' => TRUE,
        'initials' => $initials,
        'safe_display_name' => $safe_name,
        'safe_position' => $safe_position,
        'route_url' => $route_url
    )); ?>
    <main class="main-area">
        <?php $this->load->view('admin/partials/header', array(
            'header_title' => 'View Documents',
            'header_kicker' => 'DOCUMENT MANAGEMENT',
            'header_primary_url' => $route_url('home', 'administrator/dashboard'),
            'header_primary_label' => 'Dashboard',
            'header_logout_url' => $route_url('logout', 'administrator/logout'),
            'initials' => $initials,
            'safe_display_name' => $safe_name,
            'safe_position' => $safe_position
        )); ?>
        <div class="documents-content view-documents-page">
            <section class="manage-hero">
                <div>
                    <span class="manage-hero-eyebrow">UPLOADED DOCUMENTS</span>
                    <h2><?php echo html_escape($safe_folder_name); ?></h2>
                    <p>Select a file and click View. Use the left and right controls to browse selected files.</p>
                </div>
                <div class="manage-hero-stat">
                    <span>Total Documents</span>
                    <strong><?php echo number_format($total_documents); ?></strong>
                </div>
            </section>
            <section class="documents-panel">
                <div class="documents-heading">
                    <div>
                        <small>DOCUMENT RECORDS</small>
                        <h3><?php echo html_escape($safe_folder_name); ?></h3>
                    </div>
                    <a href="<?php echo html_escape($manage_url); ?>" class="documents-back">&larr; Back</a>
                </div>
                <nav class="documents-breadcrumbs" aria-label="Document folder path">
                    <a href="<?php echo html_escape($manage_url); ?>">
                        <svg class="documents-crumb-icon documents-crumb-home" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z"/></svg>
                        Manage Documents
                    </a>
                    <span class="documents-breadcrumb-sep" aria-hidden="true">›</span>
                    <strong class="documents-breadcrumb-current" aria-current="page">
                        <svg class="documents-crumb-icon documents-crumb-folder" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        <?php echo html_escape($safe_folder_name); ?>
                    </strong>
                    <span class="documents-breadcrumb-sep" aria-hidden="true">›</span>
                    <span class="view-documents-crumb">Uploaded Files</span>
                </nav>
                <div class="documents-toolbar">
                    <button type="button" class="document-action primary" id="view-selected-document" disabled>
                        <i class="bi bi-eye" aria-hidden="true"></i> View
                    </button>
                    <?php if (!empty($can_download_documents)): ?>
                    <button type="button" class="document-action" id="download-selected-document" disabled>
                        <i class="bi bi-download" aria-hidden="true"></i> Download
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($can_manage_uploaded_files)): ?>
                    <button type="button" class="document-action warning" id="delete-selected-document" disabled>
                        <i class="bi bi-trash" aria-hidden="true"></i> Delete
                    </button>
                    <button type="button" class="document-action" id="transfer-selected-document" disabled>
                        <i class="bi bi-arrow-right-circle" aria-hidden="true"></i> Transfer
                    </button>
                    <button type="button" class="document-action" id="rename-selected-document" disabled>
                        <i class="bi bi-pencil-square" aria-hidden="true"></i> Rename
                    </button>
                    <?php endif; ?>
                    <a href="<?php echo html_escape($manage_url); ?>" class="document-action view-documents-cancel">
                        <i class="bi bi-x-lg" aria-hidden="true"></i> Cancel
                    </a>
                    <span class="documents-selection" id="view-documents-selection">No records selected</span>
                </div>
                <div class="documents-table-wrap">
                    <table class="documents-table" id="view-documents-table" width="100%">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" id="select-all-documents" aria-label="Select all documents"></th>
                                <th>ID</th>
                                <th>Data Name</th>
                                <th>Page No</th>
                                <th>Date Uploaded</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<div
    class="rms-modal"
    id="uploaded-file-viewer"
    role="dialog"
    aria-modal="true"
    aria-labelledby="uploaded-file-title">

    <div class="rms-modal-dialog uploaded-file-dialog">
        <div class="rms-modal-header rms-modal-header-featured uploaded-viewer-header">

            <!-- CLOSE BUTTON MOVED TO RIGHT -->
            <button
                type="button"
                class="rms-modal-close uploaded-viewer-close"
                id="close-uploaded-file"
                aria-label="Close document viewer">
                &times;
            </button>

            <div class="uploaded-viewer-heading">
                <span class="rms-modal-eyebrow">DOCUMENT VIEWER</span>

                <h2
                    class="rms-modal-title"
                    id="uploaded-file-title">
                    Uploaded File
                </h2>

                <p id="uploaded-file-position">File 0 of 0</p>
            </div>

            <?php if (!empty($can_manage_uploaded_files)): ?>
                <button
                    type="button"
                    class="document-action uploaded-viewer-actions"
                    id="uploaded-file-actions"
                    aria-haspopup="true"
                    aria-expanded="false">
                    Actions
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
            <?php endif; ?>
        </div>

        <div
            class="uploaded-file-toolbar"
            aria-label="Image zoom controls">

            <button
                type="button"
                class="document-action image-zoom-control"
                id="zoom-out-file"
                disabled>
                <i class="bi bi-zoom-out" aria-hidden="true"></i>
                Zoom Out
            </button>

            <span id="uploaded-file-zoom">100%</span>

            <button
                type="button"
                class="document-action image-zoom-control"
                id="zoom-in-file"
                disabled>
                <i class="bi bi-zoom-in" aria-hidden="true"></i>
                Zoom In
            </button>

            <button
                type="button"
                class="document-action image-zoom-control"
                id="fit-uploaded-file"
                disabled>
                Fit to Screen
            </button>

            <button
                type="button"
                class="document-action image-zoom-control"
                id="actual-size-file"
                disabled>
                Actual Size
            </button>

            <span class="uploaded-viewer-help">
                <!-- VIEWER FIX: explain left-mouse dragging separately from Ctrl-wheel zoom. -->
                Hold left mouse button and drag to move &middot;
                Hold Ctrl + mouse wheel to zoom
            </span>
        </div>

        <div
            class="uploaded-file-stage"
            id="uploaded-file-stage">

            <button
                type="button"
                class="uploaded-stage-navigation previous"
                id="previous-uploaded-file"
                aria-label="Previous file">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>

            <img
                id="uploaded-image"
                alt="Uploaded document"
                draggable="false"
                hidden>

            <iframe
                id="uploaded-file-frame"
                title="Uploaded document viewer"
                src="about:blank">
            </iframe>

            <button
                type="button"
                class="uploaded-stage-navigation next"
                id="next-uploaded-file"
                aria-label="Next file">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <div class="uploaded-file-navigation">
            <strong id="uploaded-file-name"></strong>
        </div>
    </div>
</div>

<?php if (!empty($can_manage_uploaded_files)): ?>
<div class="rms-modal" id="rename-uploaded-file-modal" role="dialog" aria-modal="true" aria-labelledby="rename-uploaded-file-title">
    <div class="rms-modal-dialog rms-modal-dialog-small">
        <div class="rms-modal-header rms-modal-header-featured">
            <div><span class="rms-modal-eyebrow">UPLOADED FILE</span><h2 class="rms-modal-title" id="rename-uploaded-file-title">Rename File</h2></div>
            <button type="button" class="rms-modal-close modal-cancel" aria-label="Close">&times;</button>
        </div>
        <div class="rms-modal-body">
            <label for="renamed-uploaded-file">New filename</label>
            <input
                type="text"
                id="renamed-uploaded-file"
                maxlength="250"
                required
                data-windows-name
                data-windows-name-type="filename"
                data-validation-button="#save-renamed-uploaded-file">
            <div
                class="windows-name-error"
                data-error-for="renamed-uploaded-file"
                role="alert"
                aria-live="polite"
                hidden></div>
        </div>
        <div class="rms-modal-footer"><button type="button" class="document-action modal-cancel">Cancel</button><button type="button" class="document-action primary" id="save-renamed-uploaded-file">Save Rename</button></div>
    </div>
</div>

<div class="rms-modal" id="transfer-uploaded-file-modal" role="dialog" aria-modal="true" aria-labelledby="transfer-uploaded-file-title">
    <div class="rms-modal-dialog rms-modal-dialog-small">
        <div class="rms-modal-header rms-modal-header-featured">
            <div><span class="rms-modal-eyebrow">UPLOADED FILES</span><h2 class="rms-modal-title" id="transfer-uploaded-file-title">Transfer Files</h2></div>
            <button type="button" class="rms-modal-close modal-cancel" aria-label="Close">&times;</button>
        </div>
        <div class="rms-modal-body"><label for="transfer-uploaded-file-path">Destination leaf folder</label><select id="transfer-uploaded-file-path"><option value="">Select an unpublished leaf path</option>
            <?php foreach ($transfer_paths as $path): $path_level = (int) $path['record_level']; $path_id = (int) $path['record_id']; ?>
            <option value="<?php echo $path_level . ':' . $path_id; ?>"><?php echo html_escape($build_path_label($path)); ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="rms-modal-footer"><button type="button" class="document-action modal-cancel">Cancel</button><button type="button" class="document-action primary" id="save-transferred-uploaded-files">Transfer</button></div>
    </div>
</div>
<?php endif; ?>

<script>
window.VIEW_DOCUMENTS_CONFIG = <?php echo json_encode(array(
    'ajaxUrl' => site_url('administrator/documents/view_documents'),
    'deleteUrl' => site_url('administrator/documents/delete_uploaded_files'),
    'renameUrl' => site_url('administrator/documents/rename_uploaded_file'),
    'transferUrl' => site_url('administrator/documents/transfer_uploaded_files'),
    'level' => (int) $folder_level,
    'recordId' => (int) $record_id,
    'canDownload' => !empty($can_download_documents),
    'canManage' => !empty($can_manage_uploaded_files),
    'csrfName' => $this->security->get_csrf_token_name(),
    'csrfHash' => $this->security->get_csrf_hash()
)); ?>;
</script>
<script src="<?php echo base_url('assets/js/jquery-3.5.1.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/jquery.dataTables.min.js?v=1.13.11'); ?>"></script>
<script src="<?php echo base_url('assets/js/rms-sidebar.js?v=20260803-1'); ?>"></script>
<script src="<?php echo base_url('assets/js/rms-header.js?v=20260731-1'); ?>"></script>
<!-- VIEWER FIX: new version forces the browser to load right-drag and scroll-lock code. -->
<script src="<?php echo base_url('assets/js/rms-documents.js?v=20260826-watermark-only-v24'); ?>"></script>
</body>
</html>
