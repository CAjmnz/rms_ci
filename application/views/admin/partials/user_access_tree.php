<?php
/*
 * Searchable Edit Access tree
 *
 * The model already loaded and validated this hierarchy from filename and
 * subfolder1..subfolder10. Values contain numeric database IDs only; labels
 * are escaped before display. Checked nodes mirror user_allowed_data rows.
 */
$access_user_id = isset($access_user['user_id']) ? (int) $access_user['user_id'] : 0;
$tree_nodes = isset($access_tree) && is_array($access_tree) ? $access_tree : array();
$total_nodes = 0;
$selected_nodes = 0;

// Count rows for the initial search summary without running another query.
$count_nodes = function ($nodes) use (&$count_nodes, &$total_nodes, &$selected_nodes) {
    foreach ($nodes as $node) {
        $total_nodes++;
        if (!empty($node['checked'])) {
            $selected_nodes++;
        }
        if (!empty($node['children'])) {
            $count_nodes($node['children']);
        }
    }
};
$count_nodes($tree_nodes);

// Render nested lists while keeping each checkbox's complete old RMS ID path.
$render_nodes = function ($nodes) use (&$render_nodes) {
    if (empty($nodes)) {
        return;
    }

    echo '<ul class="access-tree-level">';
    foreach ($nodes as $node) {
        $has_children = !empty($node['children']);
        echo '<li class="access-tree-node" data-access-node data-node-name="'.
            html_escape(strtolower($node['name'])).'">';
        echo '<div class="access-tree-row">';
        echo '<span class="access-tree-guide" aria-hidden="true"></span>';
        echo '<label>';
        echo '<input type="checkbox" name="access_path[]" value="'.
            html_escape($node['token']).'"'.(!empty($node['checked']) ? ' checked' : '').'>';
        echo '<span class="access-checkbox" aria-hidden="true"></span>';
        echo '<span class="access-node-icon '.($has_children ? 'is-folder' : 'is-file').'" aria-hidden="true"></span>';
        echo '<span class="access-node-name">'.html_escape($node['name']).'</span>';
        echo '</label>';
        echo '</div>';
        if ($has_children) {
            $render_nodes($node['children']);
        }
        echo '</li>';
    }
    echo '</ul>';
};
?>
<?php echo form_open('users/access/'.$access_user_id, array('id' => 'user-access-form')); ?>
    <div class="access-search-bar">
        <div class="access-search-field">
            <span aria-hidden="true"></span>
            <input
                type="text"
                id="access-tree-search"
                placeholder="Search a file or folder"
                autocomplete="off"
                aria-label="Search files and folders"
            >
            <button type="button" id="clear-access-search" aria-label="Clear search" hidden>&times;</button>
        </div>
        <button type="button" class="access-search-button" id="run-access-search">Search</button>
    </div>

    <div class="access-tree-summary" aria-live="polite">
        <span id="access-search-summary">
            Showing <?php echo number_format($total_nodes); ?> files and folders
        </span>
        <strong id="access-selected-count"><?php echo number_format($selected_nodes); ?> selected</strong>
    </div>

    <div class="access-tree-panel" id="access-tree-panel">
        <?php if (empty($tree_nodes)): ?>
            <div class="access-empty-state">
                <strong>No files are available</strong>
                <span>The existing filename and subfolder tables returned no records.</span>
            </div>
        <?php else: ?>
            <?php $render_nodes($tree_nodes); ?>
            <div class="access-empty-state" id="access-no-results" hidden>
                <strong>No matching file or folder</strong>
                <span>Try a shorter or more general search.</span>
            </div>
        <?php endif; ?>
    </div>

    <footer class="access-modal-footer">
        <p>Only the selected user's access will be changed.</p>
        <div>
            <button type="button" class="modal-button modal-button-secondary" data-close-access-modal>Cancel</button>
            <button type="submit" class="modal-button modal-button-primary"<?php echo $access_user_id < 1 ? ' disabled' : ''; ?>>
                Save access
            </button>
        </div>
    </footer>
<?php echo form_close(); ?>
            