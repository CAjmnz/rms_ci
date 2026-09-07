<?php
/*
 * Edit Access modal shell
 *
 * The tree is still loaded through the existing Users/access endpoint. These
 * extra message dialogs change presentation only; they do not update records.
 */
$access_success_message = $this->session->flashdata('rms_access_success');
?>
<div class="user-modal access-modal" id="user-access-modal" aria-hidden="true">
    <div class="user-modal-backdrop"></div>
    <section
        class="user-modal-dialog access-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="access-modal-title"
        tabindex="-1"
    >
        <header class="access-modal-header">
            <div class="access-modal-heading">
                <span class="access-modal-icon" aria-hidden="true">&#128274;</span>
                <div>
                    <p>USER PERMISSIONS</p>
                    <h2 id="access-modal-title">Edit Access</h2>
                    <span id="access-user-name">Selected user</span>
                </div>
            </div>
            <button type="button" class="user-modal-close" data-close-access-modal aria-label="Close">&times;</button>
        </header>

        <!-- JavaScript replaces this body with the searchable permission tree. -->
        <div class="access-modal-content" id="access-modal-content">
            <div class="access-loader" id="access-loader" role="status" aria-live="polite">
                <div class="access-loader-mark" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
                <h3>Preparing file access</h3>
                <p>Organizing files and related folders. Please wait&hellip;</p>
                <div class="access-loader-lines" aria-hidden="true">
                    <i></i><i></i><i></i><i></i>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Custom confirmation replaces the browser window.confirm alert. -->
<div class="user-modal access-confirm-modal" id="access-save-confirm-modal" aria-hidden="true">
    <div class="user-modal-backdrop"></div>
    <section
        class="user-modal-dialog create-message-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="access-confirm-title"
        aria-describedby="access-confirm-description"
        tabindex="-1"
    >
        <span class="create-message-icon create-message-icon-confirm" aria-hidden="true">?</span>
        <p class="create-message-kicker">EDIT ACCESS</p>
        <h2 id="access-confirm-title">Save these access changes?</h2>
        <p id="access-confirm-description">
            Please confirm the selected file and folder permissions before saving.
        </p>
        <div class="create-message-actions">
            <button type="button" class="form-cancel" data-close-access-confirm>Cancel</button>
            <button type="button" class="form-save" id="confirm-save-access">Yes, save access</button>
        </div>
    </section>
</div>

<?php if ($access_success_message): ?>
    <!-- Displayed only after the existing controller confirms a successful save. -->
    <div
        class="user-modal access-success-modal is-open"
        id="access-save-success-modal"
        aria-hidden="false"
        data-success-message="<?php echo html_escape($access_success_message); ?>"
    >
        <div class="user-modal-backdrop"></div>
        <section
            class="user-modal-dialog create-message-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="access-success-title"
            aria-describedby="access-success-description"
            tabindex="-1"
        >
            <span class="create-message-icon create-message-icon-success" aria-hidden="true">&#10003;</span>
            <p class="create-message-kicker">EDIT ACCESS</p>
            <h2 id="access-success-title">Access saved successfully</h2>
            <p id="access-success-description">
                <?php echo html_escape($access_success_message); ?>
            </p>
            <div class="create-message-actions create-success-actions">
                <button type="button" class="form-save" data-close-access-success>Done</button>
            </div>
        </section>
    </div>
<?php endif; ?>
