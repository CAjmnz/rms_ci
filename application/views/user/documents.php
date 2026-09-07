<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/* Configure the reusable User Portal shell for the document workspace. */
$active_page = 'documents';
$topbar_title = '';

/* Use recent authorized files only at the root of My Documents. */
$workspace_documents = !empty($documents)
    ? $documents
    : (isset($recent_documents) ? $recent_documents : array());
$showing_recent_documents = empty($documents)
    && $current_folder === ''
    && $search === ''
    && !empty($workspace_documents);

$this->load->view('user/partials/header');
?>

<!-- Documents-only readability improvements; cache version prevents stale CSS. -->
<link rel="stylesheet" href="<?php echo base_url('assets/css/rms-document-table-readable.css?v=56'); ?>">

<section class="portal-page-content portal-drive-page">
    <div class="portal-content-container">
        <section class="portal-subhero portal-drive-hero" aria-labelledby="documents-title">
            <span class="portal-drive-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M3 6h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                </svg>
            </span>
            <div>
                <small>AUTHORIZED RECORDS</small>
                <h1 id="documents-title">My Documents</h1>
                <p>Browse the folders and files assigned to your account.</p>
            </div>

        </section>

        <section class="drive-workspace" aria-label="Authorized document library">
            <div class="drive-command-bar">
                <nav class="drive-breadcrumbs drive-command-breadcrumbs" aria-label="Document location">
                    <span class="drive-home-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 5.5h6l2 2h8v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/>
                        </svg>
                    </span>

                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php if ($index > 0): ?>
                            <svg class="drive-crumb-arrow" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m9 6 6 6-6 6"/>
                            </svg>
                        <?php endif; ?>

                        <?php if ($index === count($breadcrumbs) - 1): ?>
                            <strong
                                title="<?php echo html_escape($crumb['label']); ?>"
                                aria-label="Current folder: <?php echo html_escape($crumb['label']); ?>">
                                <?php echo html_escape($crumb['label']); ?>
                            </strong>
                        <?php else: ?>
                            <a
                                href="<?php echo site_url('portal/documents') . ($crumb['token'] !== '' ? '?folder=' . rawurlencode($crumb['token']) : ''); ?>"
                                title="<?php echo html_escape($crumb['label']); ?>"
                                aria-label="Open folder: <?php echo html_escape($crumb['label']); ?>">
                                <?php echo html_escape($crumb['label']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <form
                    class="drive-search"
                    method="get"
                    action="<?php echo site_url('portal/documents'); ?>">
                    <label class="sr-only" for="document-search">Search all folders and files</label>
                    <span class="drive-search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="m16 16 5 5"/>
                        </svg>
                    </span>
                    <input
                        id="document-search"
                        name="search"
                        type="search"
                        value="<?php echo html_escape($search); ?>"
                        placeholder="Search all folders and files">
                    <button type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="m16 16 5 5"/>
                        </svg>
                        <span>Search</span>
                    </button>
                </form>

                <?php if (!empty($workspace_documents)): ?>
                    <div class="drive-view-switch drive-command-view-switch" role="group" aria-label="File layout">
                        <button
                            class="is-active"
                            type="button"
                            data-document-view="grid"
                            aria-label="Grid view"
                            aria-pressed="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                            <span>Grid</span>
                        </button>
                        <button
                            type="button"
                            data-document-view="list"
                            aria-label="List view"
                            aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M9 6h12M9 12h12M9 18h12"/>
                                <circle cx="4.5" cy="6" r="1"/>
                                <circle cx="4.5" cy="12" r="1"/>
                                <circle cx="4.5" cy="18" r="1"/>
                            </svg>
                            <span>List</span>
                        </button>
                    </div>
                <?php endif; ?>

                <span class="drive-access-badge">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 3 5 6v5c0 4.6 2.9 8.2 7 10 4.1-1.8 7-5.4 7-10V6z"/>
                        <path d="m9.5 12 1.7 1.7 3.8-4"/>
                    </svg>
                    <?php echo $role_id === 3 ? 'View &amp; download' : 'View only'; ?>
                </span>

                <?php if ($current_folder !== ''): ?>
                    <a
                        class="drive-back-button"
                        href="<?php echo site_url('portal/documents') . ($parent_folder !== '' ? '?folder=' . rawurlencode($parent_folder) : ''); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M19 12H5M10 7l-5 5 5 5"/>
                        </svg>
                        <span>Back</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="drive-content">
                <header class="drive-section-heading">
                    <div>
                        <small>SECURE LIBRARY</small>
                        <h2><?php echo count($breadcrumbs) === 1 ? 'Assigned folders' : html_escape($breadcrumbs[count($breadcrumbs) - 1]['label']); ?></h2>
                    </div>
                    <span><?php echo count($folders) + count($documents); ?> item<?php echo count($folders) + count($documents) === 1 ? '' : 's'; ?></span>
                </header>

                <?php if (!empty($folders)): ?>
                    <section class="drive-folder-section" aria-labelledby="folder-section-title">
                        <h3 id="folder-section-title"><?php echo $search !== '' ? 'Matching folders' : 'Quick access'; ?></h3>
                        <div class="drive-folder-grid">
                            <?php foreach ($folders as $folder): ?>
                                <a
                                    class="drive-folder-card"
                                    href="<?php echo site_url('portal/documents') . '?folder=' . rawurlencode($folder['token']); ?>"
                                    title="<?php echo html_escape(isset($folder['path_label']) ? $folder['path_label'] : $folder['folder_name']); ?>"
                                    aria-label="Open folder: <?php echo html_escape(isset($folder['path_label']) ? $folder['path_label'] : $folder['folder_name']); ?>">
                                    <span class="drive-folder-visual" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M3 6h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        </svg>
                                    </span>
                                    <span class="drive-folder-copy">
                                        <strong><?php echo html_escape($folder['folder_name']); ?></strong>
                                        <small><?php echo (int) $folder['file_count']; ?> file<?php echo (int) $folder['file_count'] === 1 ? '' : 's'; ?></small>
                                    </span>
                                    <svg class="drive-open-arrow" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="m9 6 6 6-6 6"/>
                                    </svg>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($workspace_documents)): ?>
                    <section class="drive-file-section" aria-labelledby="file-section-title">
                        <div class="drive-file-heading">
                            <div>
                                <h3 id="file-section-title"><?php echo $showing_recent_documents ? 'Recent files' : 'Files'; ?></h3>
                                <p><?php echo $showing_recent_documents ? 'Your latest authorized records.' : 'Choose a file to preview or download.'; ?></p>
                            </div>

                            <div class="drive-file-heading-tools">
                                <span><?php echo count($workspace_documents); ?> record<?php echo count($workspace_documents) === 1 ? '' : 's'; ?></span>
                            </div>
                        </div>

                        <?php echo form_open('portal/documents/download-selected', array('id' => 'document-batch-form')); ?>
                            <?php if ($role_id === 3): ?>
                                <div class="drive-selection-bar" aria-label="Multiple file download controls">
                                    <label class="drive-select-all">
                                        <input id="select-all-documents" type="checkbox">
                                        <span aria-hidden="true">
                                            <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                                        </span>
                                        <strong>Select all</strong>
                                    </label>

                                    <div class="drive-selection-actions">
                                        <span id="document-selection-count" aria-live="polite">0 selected</span>
                                        <button id="download-selected-documents" type="submit" disabled>
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M12 3v12M7 10l5 5 5-5M5 20h14"/>
                                            </svg>
                                            <span>Download selected</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <div
                            class="drive-file-list <?php echo $showing_recent_documents ? 'is-list-view' : 'is-grid-view'; ?><?php echo $role_id === 3 ? ' has-selection' : ''; ?>"
                            data-file-layout="<?php echo $showing_recent_documents ? 'list' : 'grid'; ?>"
                            data-default-layout="<?php echo $showing_recent_documents ? 'list' : 'grid'; ?>"
                            role="list">
                            <div class="drive-file-list-head" aria-hidden="true">
                                <?php if ($role_id === 3): ?><span></span><?php endif; ?>
                                <span>Name</span>
                                <span>Folder</span>
                                <span>Date modified</span>
                                <span>Access</span>
                            </div>

                            <?php foreach ($workspace_documents as $document): ?>
                                <?php
                                /* Show safe inline thumbnails only for common browser image formats. */
                                $document_extension = strtolower(pathinfo($document['data_name'], PATHINFO_EXTENSION));
                                $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
                                $is_image = in_array($document_extension, $image_extensions, TRUE);
                                $file_type_class = 'file-type-document';
                                if ($is_image) {
                                    $file_type_class = 'file-type-image';
                                } elseif ($document_extension === 'pdf') {
                                    $file_type_class = 'file-type-pdf';
                                } elseif (in_array($document_extension, array('doc', 'docx'), TRUE)) {
                                    $file_type_class = 'file-type-word';
                                } elseif (in_array($document_extension, array('xls', 'xlsx', 'csv'), TRUE)) {
                                    $file_type_class = 'file-type-excel';
                                }
                                $view_url = site_url('portal/documents/view/' . rawurlencode($document['token']));
                                $download_url = site_url('portal/documents/download/' . rawurlencode($document['token']));
                                ?>
                                <article class="drive-file-row" role="listitem">
                                    <?php if ($role_id === 3): ?>
                                        <label class="drive-file-check">
                                            <input
                                                class="document-selector"
                                                type="checkbox"
                                                name="document_tokens[]"
                                                value="<?php echo html_escape($document['token']); ?>"
                                                aria-label="Select <?php echo html_escape($document['data_name']); ?>">
                                            <span aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                                            </span>
                                        </label>
                                    <?php endif; ?>

                                    <button
                                        class="drive-file-name drive-file-open"
                                        type="button"
                                        data-file-view-url="<?php echo $view_url; ?>"
                                        data-file-download-url="<?php echo $role_id === 3 ? $download_url : ''; ?>"
                                        data-file-name="<?php echo html_escape($document['data_name']); ?>"
                                        data-file-page="Page <?php echo (int) $document['page_no']; ?>"
                                        data-file-kind="<?php echo $is_image ? 'image' : 'document'; ?>"
                                        aria-label="View <?php echo html_escape($document['data_name']); ?>">
                                        <span class="drive-file-preview<?php echo $is_image ? ' is-image' : ' is-document'; ?> <?php echo $file_type_class; ?>">
                                            <?php if ($is_image): ?>
                                                <img
                                                    loading="lazy"
                                                    src="<?php echo $view_url; ?>"
                                                    alt="Preview of <?php echo html_escape($document['data_name']); ?>">
                                            <?php endif; ?>
                                            <span class="drive-file-preview-fallback" aria-hidden="true">
                                                <svg viewBox="0 0 24 24">
                                                    <?php if ($is_image): ?>
                                                        <path d="M4 5h16v14H4zM7 16l3.5-4 2.5 3 2-2 3 3M8 9h.01"/>
                                                    <?php else: ?>
                                                        <path d="M6 3h8l4 4v14H6zM14 3v5h4M9 13h6M9 17h6"/>
                                                    <?php endif; ?>
                                                </svg>
                                            </span>
                                        </span>
                                        <span class="drive-file-copy">
                                            <strong><?php echo html_escape($document['data_name']); ?></strong>
                                            <small>Page <?php echo (int) $document['page_no']; ?></small>
                                        </span>
                                    </button>

                                    <span class="drive-file-folder">
                                        <?php echo html_escape(isset($document['path_label']) ? $document['path_label'] : $breadcrumbs[count($breadcrumbs) - 1]['label']); ?>
                                    </span>
                                    <time><?php echo html_escape($document['date_uploaded']); ?></time>
                                    <span class="drive-file-access"><?php echo $role_id === 3 ? 'Download enabled' : 'View only'; ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php echo form_close(); ?>
                    </section>
                <?php endif; ?>

                <?php if (empty($folders) && empty($workspace_documents)): ?>
                    <section class="drive-empty-state">
                        <span aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M3 6h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            </svg>
                        </span>
                        <h3>No records found</h3>
                        <p><?php echo $search !== '' ? 'Try a different folder or file name.' : 'This authorized folder is currently empty.'; ?></p>
                    </section>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>

<div
    id="document-viewer-modal"
    class="document-viewer-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="document-viewer-title"
    aria-describedby="document-viewer-description"
    hidden>
    <div class="document-viewer-backdrop" data-close-document-viewer></div>

    <section class="document-viewer-panel" tabindex="-1">
        <header class="document-viewer-header">
            <div class="document-viewer-file-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M6 3h8l4 4v14H6zM14 3v5h4M9 13h6M9 17h6"/>
                </svg>
            </div>
            <div class="document-viewer-heading">
                <h2 id="document-viewer-title">File preview</h2>
                <p id="document-viewer-description">Authorized document</p>
            </div>

            <div class="document-viewer-actions">
                <div class="document-viewer-zoom" role="group" aria-label="Preview zoom controls">
                    <button id="document-viewer-zoom-out" type="button" aria-label="Zoom out">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 12h10"/></svg>
                    </button>
                    <button id="document-viewer-zoom-reset" type="button" aria-label="Reset zoom">
                        <span id="document-viewer-zoom-level">100%</span>
                    </button>
                    <button id="document-viewer-zoom-in" type="button" aria-label="Zoom in">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v10M7 12h10"/></svg>
                    </button>
                </div>
                <?php if ($role_id === 3): ?>
                    <a id="document-viewer-download" href="#" hidden>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3v12M7 10l5 5 5-5M5 20h14"/>
                        </svg>
                        <span>Download</span>
                    </a>
                <?php endif; ?>
                <button type="button" class="document-viewer-close" data-close-document-viewer aria-label="Close file viewer">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18"/>
                    </svg>
                </button>
            </div>
        </header>

        <div class="document-viewer-stage">
            <div class="document-viewer-loading" aria-live="polite">
                <span></span>
                <p>Loading secure preview...</p>
            </div>
            <img id="document-viewer-image" alt="" hidden>
            <iframe id="document-viewer-frame" title="Secure file preview" hidden></iframe>
        </div>

        <footer class="document-viewer-footer">
            <button id="document-viewer-previous" class="document-viewer-navigation" type="button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg>
                <span>Previous</span>
            </button>
            <span id="document-viewer-position" aria-live="polite">1 of 1</span>
            <button id="document-viewer-next" class="document-viewer-navigation" type="button">
                <span>Next</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
            </button>
        </footer>
    </section>
</div>

<script src="<?php echo base_url('assets/js/rms-document-workspace.js'); ?>"></script>
<?php $this->load->view('user/partials/footer'); ?>
