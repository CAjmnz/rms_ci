<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$safe_name = !empty($display_name)
    ? $display_name
    : $username;

$safe_position = !empty($position)
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

/**
 * Builds the visible hierarchy for each unpublished upload path.
 */
$build_path_label = function ($path) {
    $parts = array();

    if (!empty($path['filename'])) {
        $parts[] = $path['filename'];
    } elseif (!empty($path['record_name'])) {
        $parts[] = $path['record_name'];
    }

    for ($level = 1; $level <= 10; $level++) {
        $field = 'subfolder' . $level;

        if (!empty($path[$field])) {
            $parts[] = $path[$field];
        }
    }

    return implode(' / ', $parts);
};

$upload_paths = isset($upload_paths) && is_array($upload_paths)
    ? $upload_paths
    : array();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?php echo html_escape($page_title); ?>
        |
        <?php echo html_escape($system_name); ?>
    </title>

    <link
        rel="stylesheet"
        href="<?php echo base_url(
            'assets/css/rms-dashboard.css?v=20260731-3'
        ); ?>"
    >

    <link
        rel="stylesheet"
        href="<?php echo base_url(
            'assets/css/rms-sidebar.css?v=20260803-3'
        ); ?>"
    >

    <link
        rel="stylesheet"
        href="<?php echo base_url(
            'assets/css/rms-header.css?v=20260731-1'
        ); ?>"
    >

    <link
        rel="stylesheet"
        href="<?php echo base_url(
            'assets/css/rms-footer.css?v=20260803-1'
        ); ?>"
    >

    <style>
        :root {
            --green-950: #063b2d;
            --green-900: #07513c;
            --green-800: #086a4d;
            --green-700: #0a865e;
            --green-600: #10a66f;
            --green-500: #25bd7e;
            --green-100: #dff7eb;
            --green-50: #effaf4;
            --ink: #18322b;
            --muted: #71837d;
            --line: #e1ebe6;
            --surface: #ffffff;
            --canvas: #f3f7f5;
            --danger: #bd3329;
            --warning-bg: #fff7e2;
            --warning-text: #80601c;
        }

        * {
            box-sizing: border-box;
        }

        .documents-create-content {
            min-height: calc(100vh - 78px);
            padding: 28px;
            background: var(--canvas);
        }

        .documents-create-hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .documents-create-hero small {
            display: block;
            margin-bottom: 6px;
            color: var(--green-700);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.4px;
        }

        .documents-create-hero h2 {
            margin: 0;
            color: var(--ink);
            font-size: 28px;
        }

        .documents-create-hero p {
            max-width: 680px;
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 17px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--surface);
            color: var(--green-800);
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .back-button:hover {
            border-color: var(--green-500);
            color: var(--green-700);
        }

        .upload-panel {
            overflow: hidden;
            max-width: 980px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: 0 10px 30px rgba(6, 59, 45, .06);
        }

        .upload-panel-header {
            padding: 22px 24px;
            border-bottom: 1px solid var(--line);
        }

        .upload-panel-header small {
            color: var(--green-700);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.2px;
        }

        .upload-panel-header h3 {
            margin: 5px 0 0;
            color: var(--ink);
            font-size: 20px;
        }

        .upload-notice {
            margin: 22px 24px 0;
            padding: 14px 16px;
            border: 1px solid #efd798;
            border-radius: 10px;
            background: var(--warning-bg);
            color: var(--warning-text);
            font-size: 13px;
            line-height: 1.6;
        }

        .upload-notice strong {
            display: block;
            margin-bottom: 3px;
        }

        .upload-form {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 23px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 800;
        }

        .required {
            color: var(--danger);
        }

        .form-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .form-control {
            display: block;
            width: 100%;
            min-height: 46px;
            padding: 10px 13px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            color: var(--ink);
            font-family: inherit;
            font-size: 14px;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--green-500);
            box-shadow: 0 0 0 3px rgba(37, 189, 126, .12);
        }

        .form-control:disabled {
            background: #f4f6f5;
            color: var(--muted);
            cursor: not-allowed;
        }

        .selected-path {
            display: none;
            margin-top: 12px;
            padding: 14px 16px;
            border: 1px solid var(--green-100);
            border-radius: 10px;
            background: var(--green-50);
        }

        .selected-path.visible {
            display: block;
        }

        .selected-path span {
            display: block;
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .8px;
        }

        .selected-path strong {
            color: var(--green-900);
            font-size: 13px;
            line-height: 1.5;
        }

        .file-box {
            padding: 19px;
            border: 1px dashed #b8cec4;
            border-radius: 12px;
            background: #fbfefd;
        }

        .file-box input[type="file"] {
            display: block;
            width: 100%;
            color: var(--ink);
            font-size: 13px;
        }

        .file-box input[type="file"]::file-selector-button {
            margin-right: 12px;
            padding: 10px 15px;
            border: 0;
            border-radius: 8px;
            background: var(--green-700);
            color: #fff;
            cursor: pointer;
            font-weight: 700;
        }

        .file-summary {
            display: none;
            margin-top: 10px;
            color: var(--green-800);
            font-size: 12px;
            font-weight: 700;
        }

        .file-summary.visible {
            display: block;
        }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 4px;
        }

        .upload-button,
        .cancel-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 19px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
        }

        .upload-button {
            border: 1px solid var(--green-700);
            background: var(--green-700);
            color: #fff;
            cursor: pointer;
        }

        .upload-button:disabled {
            border-color: #b5c2bd;
            background: #b5c2bd;
            cursor: not-allowed;
        }

        .cancel-button {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            text-decoration: none;
        }

        .no-paths {
            padding: 30px 24px;
            text-align: center;
        }

        .no-paths strong {
            display: block;
            margin-bottom: 8px;
            color: var(--ink);
            font-size: 17px;
        }

        .no-paths p {
            max-width: 560px;
            margin: 0 auto 18px;
            color: var(--muted);
            line-height: 1.6;
        }

        @media (max-width: 760px) {
            .documents-create-content {
                padding: 18px;
            }

            .documents-create-hero {
                flex-direction: column;
            }

            .form-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .upload-button,
            .cancel-button {
                width: 100%;
            }
        }
    </style>
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
                'header_title' => 'New Documents',
                'header_kicker' => 'DOCUMENT MANAGEMENT',
                'header_action_url' => site_url(
                    'administrator/documents'
                ),
                'header_action_label' => 'Manage Documents',
                'header_action_symbol' => '←',
                'header_primary_url' => $route_url(
                    'home',
                    'administrator/dashboard'
                ),
                'header_primary_label' => 'Dashboard',
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

        <div class="documents-create-content">
            <section class="documents-create-hero">
                <div>
                    <small>DOCUMENT UPLOAD</small>

                    <h2>Add New Documents</h2>

                    <p>
                        Select an unpublished folder path, browse the
                        original files and watermark files, and then
                        upload them to the selected RMS directory.
                    </p>
                </div>

                <a
                    class="back-button"
                    href="<?php echo site_url(
                        'administrator/documents'
                    ); ?>"
                >
                    Back to Manage Documents
                </a>
            </section>

            <section class="upload-panel">
                <div class="upload-panel-header">
                    <small>NEW DOCUMENTS V1</small>
                    <h3>Document Upload Form</h3>
                </div>

                <div class="upload-notice">
                    <strong>Before uploading</strong>

                    The main folder and its subfolders must be
                    unpublished from Manage Documents until you reach
                    the final destination folder.
                </div>

                <?php if (!empty($upload_paths)): ?>
                    <?php echo form_open_multipart(
                        'administrator/documents/upload',
                        array(
                            'class' => 'upload-form',
                            'id' => 'document-upload-form'
                        )
                    ); ?>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="upload-path"
                            >
                                Destination Path
                                <span class="required">*</span>
                            </label>

                            <select
                                class="form-control"
                                id="upload-path"
                                name="record_id"
                                required
                            >
                                <option value="">
                                    Select an unpublished path
                                </option>

                                <?php foreach (
                                    $upload_paths as $path
                                ): ?>
                                    <?php
                                    $path_id = !empty($path['record_id'])
                                        ? (int) $path['record_id']
                                        : 0;

                                    $path_label = $build_path_label($path);

                                    $path_level = isset(
                                        $path['record_level']
                                    )
                                        ? (int) $path['record_level']
                                        : (
                                            isset($path['level'])
                                                ? (int) $path['level']
                                                : 0
                                        );

                                    $file_id = !empty($path['file_id'])
                                        ? (int) $path['file_id']
                                        : 0;

                                    $sub_id = !empty($path['sub_id'])
                                        ? (int) $path['sub_id']
                                        : 0;

                                    $dept_id = !empty($path['dept_id'])
                                        ? (int) $path['dept_id']
                                        : 0;
                                    ?>

                                    <?php if (
                                        $path_id > 0 &&
                                        $path_label !== ''
                                    ): ?>
                                        <option
                                            value="<?php echo $path_id; ?>"
                                            data-label="<?php echo
                                                html_escape($path_label);
                                            ?>"
                                            data-level="<?php echo
                                                $path_level;
                                            ?>"
                                            data-file-id="<?php echo
                                                $file_id;
                                            ?>"
                                            data-sub-id="<?php echo
                                                $sub_id;
                                            ?>"
                                            data-dept-id="<?php echo
                                                $dept_id;
                                            ?>"
                                        >
                                            <?php echo html_escape(
                                                $path_label
                                            ); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>

                            <input
                                type="hidden"
                                name="record_level"
                                id="record-level"
                                value=""
                            >

                            <input
                                type="hidden"
                                name="file_id"
                                id="file-id"
                                value=""
                            >

                            <input
                                type="hidden"
                                name="sub_id"
                                id="sub-id"
                                value=""
                            >

                            <input
                                type="hidden"
                                name="dept_id"
                                id="dept-id"
                                value=""
                            >

                            <span class="form-help">
                                Only unpublished destinations are
                                available here.
                            </span>

                            <div
                                class="selected-path"
                                id="selected-path"
                            >
                                <span>SELECTED DESTINATION</span>
                                <strong id="selected-path-label"></strong>
                            </div>
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="original-files"
                            >
                                Browse Original Files
                                <span class="required">*</span>
                            </label>

                            <div class="file-box">
                                <input
                                    type="file"
                                    id="original-files"
                                    name="original_files[]"
                                    multiple
                                    required
                                    disabled
                                >

                                <div
                                    class="file-summary"
                                    id="original-summary"
                                ></div>
                            </div>

                            <span class="form-help">
                                Select one or more original document
                                files from your computer.
                            </span>
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="watermark-files"
                            >
                                Watermark Files
                            </label>

                            <div class="file-box">
                                <input
                                    type="file"
                                    id="watermark-files"
                                    name="watermark_files[]"
                                    multiple
                                    disabled
                                >

                                <div
                                    class="file-summary"
                                    id="watermark-summary"
                                ></div>
                            </div>

                            <span class="form-help">
                                If required, select the corresponding
                                watermarked copies.
                            </span>
                        </div>

                        <div class="form-actions">
                            <button
                                type="submit"
                                class="upload-button"
                                id="upload-button"
                                disabled
                            >
                                Upload Documents
                            </button>

                            <a
                                class="cancel-button"
                                href="<?php echo site_url(
                                    'administrator/documents'
                                ); ?>"
                            >
                                Cancel
                            </a>
                        </div>

                    <?php echo form_close(); ?>
                <?php else: ?>
                    <div class="no-paths">
                        <strong>
                            No unpublished upload paths are available
                        </strong>

                        <p>
                            Open Manage Documents and unpublish the
                            required main folder and subfolders until
                            you reach the destination folder.
                        </p>

                        <a
                            class="back-button"
                            href="<?php echo site_url(
                                'administrator/documents'
                            ); ?>"
                        >
                            Open Manage Documents
                        </a>
                    </div>
                <?php endif; ?>
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

<script src="<?php echo base_url(
    'assets/js/jquery-3.5.1.min.js'
); ?>"></script>

<script src="<?php echo base_url(
    'assets/js/rms-sidebar.js?v=20260803-1'
); ?>"></script>

<script src="<?php echo base_url(
    'assets/js/rms-header.js?v=20260731-1'
); ?>"></script>

<script>
(function ($) {
    'use strict';

    var $path = $('#upload-path');
    var $originalFiles = $('#original-files');
    var $watermarkFiles = $('#watermark-files');
    var $uploadButton = $('#upload-button');
    var $selectedPath = $('#selected-path');
    var $selectedPathLabel = $('#selected-path-label');

    function updatePath() {
        var $selected = $path.find('option:selected');
        var hasPath = $path.val() !== '';

        $('#record-level').val(
            hasPath ? $selected.data('level') : ''
        );

        $('#file-id').val(
            hasPath ? $selected.data('file-id') : ''
        );

        $('#sub-id').val(
            hasPath ? $selected.data('sub-id') : ''
        );

        $('#dept-id').val(
            hasPath ? $selected.data('dept-id') : ''
        );

        $originalFiles.prop('disabled', !hasPath);
        $watermarkFiles.prop('disabled', !hasPath);

        if (hasPath) {
            $selectedPathLabel.text(
                $selected.data('label')
            );

            $selectedPath.addClass('visible');
        } else {
            $selectedPathLabel.text('');
            $selectedPath.removeClass('visible');

            $originalFiles.val('');
            $watermarkFiles.val('');
        }

        updateUploadButton();
        updateFileSummary(
            $originalFiles,
            $('#original-summary')
        );

        updateFileSummary(
            $watermarkFiles,
            $('#watermark-summary')
        );
    }

    function updateFileSummary($input, $summary) {
        var input = $input.get(0);
        var count = input && input.files
            ? input.files.length
            : 0;

        if (count === 0) {
            $summary
                .removeClass('visible')
                .text('');

            return;
        }

        $summary
            .addClass('visible')
            .text(
                count === 1
                    ? '1 file selected'
                    : count + ' files selected'
            );
    }

    function updateUploadButton() {
        var input = $originalFiles.get(0);

        var originalCount = input && input.files
            ? input.files.length
            : 0;

        $uploadButton.prop(
            'disabled',
            $path.val() === '' || originalCount === 0
        );
    }

    $path.on('change', updatePath);

    $originalFiles.on('change', function () {
        updateFileSummary(
            $originalFiles,
            $('#original-summary')
        );

        updateUploadButton();
    });

    $watermarkFiles.on('change', function () {
        updateFileSummary(
            $watermarkFiles,
            $('#watermark-summary')
        );
    });

    $('#document-upload-form').on('submit', function (event) {
        if ($path.val() === '') {
            event.preventDefault();
            window.alert('Please select a destination path.');
            return;
        }

        if (
            !$originalFiles.get(0).files ||
            $originalFiles.get(0).files.length === 0
        ) {
            event.preventDefault();
            window.alert(
                'Please select at least one original file.'
            );
        }
    });

    updatePath();

})(jQuery);
</script>
</body>
</html>