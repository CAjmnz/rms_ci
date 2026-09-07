<?php
/*
 * Edit User modal partial
 *
 * Purpose:
 * - Edits one selected account without leaving the Manage Users page.
 * - Posts to users/edit/{user_id}, where the controller validates every field.
 * - Leaves the existing password unchanged unless a new one is generated.
 *
 * This view performs no query and cannot update the database by itself.
 */
$edit_modal_is_open = isset($open_edit_modal) && $open_edit_modal === TRUE;
$edit_form_is_active = isset($edit_form_active) && $edit_form_active === TRUE;
$edit_user_id = isset($edit_user['user_id']) ? (int) $edit_user['user_id'] : 0;

// Preserve submitted values after validation; otherwise show current record values.
$edit_name_value = $edit_form_is_active
    ? set_value('cname', isset($edit_user['emp_name']) ? $edit_user['emp_name'] : '')
    : (isset($edit_user['emp_name']) ? $edit_user['emp_name'] : '');
$edit_username_value = $edit_form_is_active
    ? set_value('username', isset($edit_user['username']) ? $edit_user['username'] : '')
    : (isset($edit_user['username']) ? $edit_user['username'] : '');
$edit_subsidiary_value = $edit_form_is_active
    ? set_value('subsidiary', isset($edit_user['sub_id']) ? $edit_user['sub_id'] : '')
    : (isset($edit_user['sub_id']) ? $edit_user['sub_id'] : '');
$edit_department_value = $edit_form_is_active
    ? set_value('department', isset($edit_user['dept_id']) ? $edit_user['dept_id'] : '')
    : (isset($edit_user['dept_id']) ? $edit_user['dept_id'] : '');
$edit_role_value = $edit_form_is_active
    ? set_value('role', isset($edit_user['role_id']) ? $edit_user['role_id'] : '')
    : (isset($edit_user['role_id']) ? $edit_user['role_id'] : '');
?>

<?php if ($edit_user_id > 0): ?>
    <div
        class="user-modal<?php echo $edit_modal_is_open ? ' is-open' : ''; ?>"
        id="user-edit-modal"
        aria-hidden="<?php echo $edit_modal_is_open ? 'false' : 'true'; ?>"
    >
        <div class="user-modal-backdrop"></div>

        <section
            class="user-modal-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="edit-user-modal-title"
            tabindex="-1"
        >
            <header class="user-modal-header user-edit-modal-header">
                <div>
                    <p>USERS MODULE · EDIT USER</p>
                    <h2 id="edit-user-modal-title">Edit user account</h2>
                    <span>Update only the selected account.</span>
                </div>
                <button type="button" class="user-modal-close" data-close-edit-modal aria-label="Close Edit User modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </header>

            <div class="user-modal-body">
                <?php if ($edit_error_message !== ''): ?>
                    <div class="form-alert" role="alert"><?php echo html_escape($edit_error_message); ?></div>
                <?php endif; ?>
                <?php if ($edit_form_is_active && validation_errors() !== ''): ?>
                    <div class="form-alert" role="alert"><?php echo validation_errors('<p>', '</p>'); ?></div>
                <?php endif; ?>

                <?php echo form_open(
                    'users/edit/'.$edit_user_id,
                    array('class' => 'user-create-form user-edit-form', 'id' => 'user-edit-form')
                ); ?>
                    <div class="modal-form-heading">
                        <div>
                            <p>ACCOUNT DETAILS</p>
                            <h3><?php echo html_escape($edit_name_value); ?></h3>
                        </div>
                        <span class="required-note"><i>*</i> Required fields</span>
                    </div>

                    <div class="form-grid">
                        <div class="form-field form-field-wide">
                            <label for="edit-cname">Complete name <i>*</i></label>
                            <input
                                type="text"
                                id="edit-cname"
                                name="cname"
                                maxlength="150"
                                value="<?php echo html_escape($edit_name_value); ?>"
                                autocomplete="name"
                                required
                            >
                            <small>Letters, spaces, periods, apostrophes, and hyphens only.</small>
                        </div>

                        <div class="form-field">
                            <label for="edit-username">Username <i>*</i></label>
                            <input
                                type="text"
                                id="edit-username"
                                name="username"
                                maxlength="25"
                                value="<?php echo html_escape($edit_username_value); ?>"
                                autocomplete="off"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="edit-password">New password</label>
                            <div class="password-row">
                                <input
                                    type="text"
                                    id="edit-password"
                                    name="password"
                                    maxlength="50"
                                    value=""
                                    autocomplete="new-password"
                                    readonly
                                    placeholder="Keep current password"
                                >
                                <button type="button" id="generate-edit-password">Generate</button>
                            </div>
                            <small>Leave blank to keep the current password unchanged.</small>
                        </div>

                        <div class="form-field">
                            <label for="edit-subsidiary">Subsidiary <i>*</i></label>
                            <select id="edit-subsidiary" name="subsidiary" required>
                                <option value="">Select subsidiary</option>
                                <?php foreach ($edit_subsidiaries as $subsidiary): ?>
                                    <option
                                        value="<?php echo (int) $subsidiary['sub_id']; ?>"
                                        <?php echo (int) $edit_subsidiary_value === (int) $subsidiary['sub_id'] ? ' selected' : ''; ?>
                                    >
                                        <?php echo html_escape($subsidiary['sub_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="edit-department">Department <i>*</i></label>
                            <select id="edit-department" name="department" required>
                                <option value="">Select department</option>
                                <?php foreach ($edit_departments as $department): ?>
                                    <option
                                        value="<?php echo (int) $department['dept_id']; ?>"
                                        data-sub-id="<?php echo (int) $department['sub_id']; ?>"
                                        <?php echo (int) $edit_department_value === (int) $department['dept_id'] ? ' selected' : ''; ?>
                                    >
                                        <?php echo html_escape($department['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="edit-role">User level <i>*</i></label>
                            <select id="edit-role" name="role" required>
                                <option value="">Select user level</option>
                                <?php foreach ($edit_roles as $role): ?>
                                    <option
                                        value="<?php echo (int) $role['role_id']; ?>"
                                        <?php echo (int) $edit_role_value === (int) $role['role_id'] ? ' selected' : ''; ?>
                                    >
                                        <?php echo html_escape($role['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="form-actions modal-form-actions">
                        <p>Blank password keeps the current login password.</p>
                        <div>
                            <button type="button" class="form-cancel" data-close-edit-modal>Cancel</button>
                            <button type="submit" class="form-save">Save changes</button>
                        </div>
                    </div>
                <?php echo form_close(); ?>
            </div>
        </section>
    </div>
<?php endif; ?>
