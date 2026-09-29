<?php
/*
 * New User modal partial
 *
 * Purpose:
 * - Keeps account creation on the Manage Users page.
 * - Reuses the existing users/create POST handler and validation process.
 * - Reads dropdown values already supplied by the Users controller.
 *
 * This view contains no database query and does not change any record itself.
 */
$account_was_created = isset($created_account) && is_array($created_account);
$modal_is_open = isset($open_create_modal)
    && $open_create_modal === TRUE
    && !$account_was_created;
$create_form_active = isset($create_form_active) && $create_form_active === TRUE;
?>

<!-- The backdrop and dialog are opened by assets/js/rms-users.js. -->
<div
    class="user-modal<?php echo $modal_is_open ? ' is-open' : ''; ?>"
    id="user-create-modal"
    aria-hidden="<?php echo $modal_is_open ? 'false' : 'true'; ?>"
>
    <div class="user-modal-backdrop"></div>

    <section
        class="user-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="new-user-modal-title"
        tabindex="-1"
    >
        <!-- Compact modal header replaces the separate New User page banner. -->
        <header class="user-modal-header">
            <div>
                <p>USERS MODULE · NEW USER</p>
                <h2 id="new-user-modal-title">Create a user account</h2>
                <span>Add an account without leaving the Users directory.</span>
            </div>
            <button type="button" class="user-modal-close" data-close-user-modal aria-label="Close New User modal">
                <span aria-hidden="true">&times;</span>
            </button>
        </header>

        <div class="user-modal-body">
            <!-- Server-side business-rule and field-validation messages. -->
            <?php if ($error_message !== ''): ?>
                <div class="form-alert" role="alert"><?php echo html_escape($error_message); ?></div>
            <?php endif; ?>
            <?php if ($create_form_active && validation_errors() !== ''): ?>
                <div class="form-alert" role="alert"><?php echo validation_errors('<p>', '</p>'); ?></div>
            <?php endif; ?>

            <!-- CI3 form helper keeps the configured action and CSRF protection. -->
            <?php echo form_open('users/create', array('class' => 'user-create-form', 'id' => 'user-create-form')); ?>
                <div class="modal-form-heading">
                    <div>
                        <p>ACCOUNT DETAILS</p>
                        <h3>Add new user</h3>
                    </div>
                    <span class="required-note"><i>*</i> Required fields</span>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field-wide">
                        <label for="cname">Complete name <i>*</i></label>
                        <input type="text" id="cname" name="cname" maxlength="150"
                            value="<?php echo $create_form_active ? set_value('cname') : ''; ?>" autocomplete="name" required>
                        <small>Letters, spaces, periods, apostrophes, and hyphens only.</small>
                    </div>

                    <div class="form-field">
                        <label for="username">Username <i>*</i></label>
                        <input type="text" id="username" name="username" maxlength="25"
                            value="<?php echo $create_form_active ? set_value('username') : ''; ?>" autocomplete="off" required>
                    </div>

                    <div class="form-field">
                        <label for="password">Password <i>*</i></label>
                        <div class="password-row">
                            <input type="text" id="password" name="password" maxlength="50"
                                value="<?php echo $create_form_active ? set_value('password') : ''; ?>"
                                autocomplete="new-password" readonly required>
                            <button type="button" id="generate-password">Generate</button>
                        </div>
                        <small>Generate a password before saving.</small>
                    </div>

                    <div class="form-field">
                        <label for="subsidiary">Subsidiary <i>*</i></label>
                        <!-- Options come from the existing subsidiaries table. -->
                        <select id="subsidiary" name="subsidiary" required>
                            <option value="">Select subsidiary</option>
                            <?php foreach ($subsidiaries as $subsidiary): ?>
                                <option value="<?php echo (int) $subsidiary['sub_id']; ?>"
                                    <?php echo $create_form_active ? set_select('subsidiary', $subsidiary['sub_id']) : ''; ?>>
                                    <?php echo html_escape($subsidiary['sub_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="department">Department <i>*</i></label>
                        <!-- JavaScript filters departments by their existing subsidiary ID. -->
                        <select id="department" name="department" required disabled>
                            <option value="">Select subsidiary first</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo (int) $department['dept_id']; ?>"
                                    data-sub-id="<?php echo (int) $department['sub_id']; ?>"
                                    <?php echo $create_form_active ? set_select('department', $department['dept_id']) : ''; ?>>
                                    <?php echo html_escape($department['dept_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field form-field-wide">
                        <label for="role">User level <i>*</i></label>
                        <!-- Only roles permitted by the existing manager rules are listed. -->
                        <div class="user-level-control">
                            <span class="user-level-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M12 2.5 20 6v5.3c0 4.9-3.4 8.8-8 10.2-4.6-1.4-8-5.3-8-10.2V6l8-3.5Zm0 3L7 7.7v3.6c0 3.2 2 5.9 5 7.1 3-1.2 5-3.9 5-7.1V7.7L12 5.5Zm-1 3h2v4h-2v-4Zm0 5.5h2v2h-2v-2Z"></path>
                                </svg>
                            </span>
                            <select id="role" name="role" required>
                                <option value="">Select user level</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo (int) $role['role_id']; ?>"
                                        <?php echo $create_form_active ? set_select('role', $role['role_id']) : ''; ?>>
                                        <?php echo html_escape($role['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="user-level-summary" id="user-level-summary" aria-live="polite">
                            <span class="user-level-summary-mark" aria-hidden="true">i</span>
                            <span id="user-level-summary-text">Choose the access level this user should receive.</span>
                        </div>
                    </div>

                </div>

                <!-- Cancel closes the modal; Save uses the unchanged server handler. -->
                <div class="form-actions modal-form-actions">
                    <p>This step creates the account only. Viewer permissions remain locked.</p>
                    <div>
                        <button type="button" class="form-cancel" data-close-user-modal>Cancel</button>
                        <button type="submit" class="form-save">Save user</button>
                    </div>
                </div>
            <?php echo form_close(); ?>
        </div>
    </section>
</div>

<!--
    Custom confirmation replaces the browser's window.confirm alert.
    It does not submit until the manager chooses the green confirmation button.
-->
<div class="user-modal create-confirm-modal" id="user-create-confirm-modal" aria-hidden="true">
    <div class="user-modal-backdrop"></div>
    <section
        class="user-modal-dialog create-message-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="create-confirm-title"
        aria-describedby="create-confirm-description"
        tabindex="-1"
    >
        <span class="create-message-icon create-message-icon-confirm" aria-hidden="true">?</span>
        <p class="create-message-kicker">CONFIRM NEW USER</p>
        <h2 id="create-confirm-title">Save this user account?</h2>
        <p id="create-confirm-description">
            Please confirm that the account details are correct before saving.
        </p>
        <div class="create-message-actions">
            <button type="button" class="form-cancel" data-close-create-confirm>Cancel</button>
            <button type="button" class="form-save" id="confirm-create-user">Yes, save user</button>
        </div>
    </section>
</div>

<?php if ($account_was_created): ?>
    <!--
        The controller supplies these credentials once through existing flash data.
        The form modal stays closed while this separate success modal is displayed.
    -->
    <div class="user-modal create-success-modal is-open" id="user-create-success-modal" aria-hidden="false">
        <div class="user-modal-backdrop"></div>
        <section
            class="user-modal-dialog create-message-dialog create-success-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="create-success-title"
            aria-describedby="create-success-description"
            tabindex="-1"
        >
            <span class="create-message-icon create-message-icon-success" aria-hidden="true">&#10003;</span>
            <p class="create-message-kicker">ACCOUNT CREATED</p>
            <h2 id="create-success-title">User saved successfully</h2>
            <p id="create-success-description">
                The New User form is now closed. Give these details only to the new user.
            </p>
            <dl class="created-account-details">
                <div>
                    <dt>Username</dt>
                    <dd id="created-account-username"><?php echo html_escape($created_account['username']); ?></dd>
                </div>
                <div>
                    <dt>Password</dt>
                    <dd id="created-account-password"><?php echo html_escape($created_account['password']); ?></dd>
                </div>
            </dl>
            <button type="button" class="copy-created-account" id="copy-created-account"
                title="Copy username and password" aria-label="Copy username and password">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M8 7V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h3zm2 0h5a2 2 0 0 1 2 2v5h2V5h-9v2zm5 2H5v10h10V9z"></path>
                </svg>
                <span>Copy</span>
            </button>
            <p class="copy-created-account-status" id="copy-created-account-status" role="status" aria-live="polite"></p>
            <div class="create-message-actions create-success-actions">
                <button type="button" class="form-save" data-close-create-success>Done</button>
            </div>
        </section>
    </div>
    <script>
        (function () {
            'use strict';

            var copyButton = document.getElementById('copy-created-account');
            var usernameNode = document.getElementById('created-account-username');
            var passwordNode = document.getElementById('created-account-password');
            var statusNode = document.getElementById('copy-created-account-status');

            if (!copyButton || !usernameNode || !passwordNode) {
                return;
            }

            function showCopied() {
                copyButton.className = 'copy-created-account is-copied';
                copyButton.querySelector('span').textContent = 'Copied!';
                statusNode.textContent = 'Username and password copied to the clipboard.';
            }

            function fallbackCopy(text) {
                var temporary = document.createElement('textarea');
                temporary.value = text;
                temporary.setAttribute('readonly', 'readonly');
                temporary.style.position = 'fixed';
                temporary.style.opacity = '0';
                document.body.appendChild(temporary);
                temporary.select();

                try {
                    if (document.execCommand('copy')) {
                        showCopied();
                    } else {
                        statusNode.textContent = 'Copy failed. Please copy the details manually.';
                    }
                } catch (copyError) {
                    statusNode.textContent = 'Copy failed. Please copy the details manually.';
                }

                document.body.removeChild(temporary);
            }

            copyButton.addEventListener('click', function () {
                var credentials = 'Username: ' + usernameNode.textContent.replace(/^\s+|\s+$/g, '') + '\n' +
                    'Password: ' + passwordNode.textContent.replace(/^\s+|\s+$/g, '');

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(credentials).then(showCopied, function () {
                        fallbackCopy(credentials);
                    });
                } else {
                    fallbackCopy(credentials);
                }
            });
        }());
    </script>
<?php endif; ?>

<script>
    (function () {
        'use strict';

        var roleSelect = document.getElementById('role');
        var summary = document.getElementById('user-level-summary');
        var summaryText = document.getElementById('user-level-summary-text');

        if (!roleSelect || !summary || !summaryText) {
            return;
        }

        function updateRoleSummary() {
            var selectedText = roleSelect.options[roleSelect.selectedIndex].text.replace(/^\s+|\s+$/g, '');
            var lowerText = selectedText.toLowerCase();
            var description = 'Choose the access level this user should receive.';

            if (roleSelect.value !== '') {
                if (lowerText.indexOf('administrator') !== -1) {
                    description = 'Administrator access: can manage users and system records.';
                } else if (lowerText.indexOf('download') !== -1) {
                    description = 'Standard access: can view and download permitted records.';
                } else if (lowerText.indexOf('view') !== -1) {
                    description = 'Viewer access: can view permitted records only.';
                } else {
                    description = selectedText + ' access will be assigned to this account.';
                }
            }

            summaryText.textContent = description;
            summary.className = roleSelect.value === '' ? 'user-level-summary' : 'user-level-summary is-selected';
        }

        roleSelect.addEventListener('change', updateRoleSummary);
        updateRoleSummary();
    }());
</script>
