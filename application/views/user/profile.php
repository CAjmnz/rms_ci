<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/* Configure the reusable User Portal shell for account settings. */
$active_page = 'profile';
$topbar_title = '';
$profile_initials = '';
$profile_name_parts = preg_split('/\s+/', trim($profile['emp_name']));
foreach ($profile_name_parts as $profile_name_part) {
    if ($profile_name_part !== '' && strlen($profile_initials) < 2) {
        $profile_initials .= strtoupper(substr($profile_name_part, 0, 1));
    }
}
if ($profile_initials === '') {
    $profile_initials = 'U';
}

$this->load->view('user/partials/header');
?>

<section class="portal-page-content portal-profile-page">
    <div class="portal-content-container">
        <section class="portal-subhero portal-profile-hero" aria-labelledby="profile-title">
            <span class="portal-profile-hero-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21c.7-4.2 3.3-6 8-6s7.3 1.8 8 6"/>
                </svg>
            </span>
            <div>
                <small>ACCOUNT SETTINGS</small>
                <h1 id="profile-title">Edit Profile</h1>
                <p>Manage your personal details and account security.</p>
            </div>
            <span class="profile-security-badge" aria-label="Account secure">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-2.5 7.7-7 10-4.5-2.3-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
                <span>Account secure</span><i aria-hidden="true"></i>
            </span>
        </section>

        <?php if ($success_message !== ''): ?>
            <div class="profile-alert is-success" role="status">
                <?php echo html_escape($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="profile-alert is-error" role="alert">
                <?php echo html_escape($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-workspace">
            <aside class="profile-summary-card" aria-label="Profile summary">
                <div class="profile-photo-shell">
                    <span class="profile-summary-avatar" aria-hidden="true">
                        <?php echo html_escape($profile_initials); ?>
                    </span>
                    <span class="profile-photo-camera" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 7h3l1.5-2h7L17 7h3v12H4z"/>
                            <circle cx="12" cy="13" r="3.5"/>
                        </svg>
                    </span>
                </div>
                <button class="profile-photo-button" type="button" disabled aria-disabled="true" title="Profile photo upload will be available soon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 7h3l1.5-2h7L17 7h3v12H4z"/>
                        <circle cx="12" cy="13" r="3.5"/>
                    </svg>
                    <span>Change photo</span>
                </button>
                <small class="profile-photo-note">Photo upload will be available soon.</small>
                <h2><?php echo html_escape($profile['emp_name']); ?></h2>
                <p><?php echo html_escape($profile['dept_name']); ?></p>

                <dl>
                    <div>
                        <span class="profile-detail-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M5 20c.6-4 2.9-6 7-6s6.4 2 7 6"/></svg>
                        </span>
                        <span><dt>Username</dt><dd><?php echo html_escape($profile['username']); ?></dd></span>
                    </div>
                    <div>
                        <span class="profile-detail-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 3l7 3v5c0 4.5-2.5 7.7-7 10-4.5-2.3-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
                        </span>
                        <span><dt>Access level</dt><dd><?php echo html_escape($profile['access_label']); ?></dd></span>
                    </div>
                    <div>
                        <span class="profile-detail-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg>
                        </span>
                        <span><dt>Account status</dt><dd><em><?php echo html_escape($profile['status_label']); ?></em></dd></span>
                    </div>
                </dl>
                <div class="profile-completeness" data-profile-completeness data-profile-user="<?php echo html_escape($profile['username']); ?>" aria-label="Profile completeness 80 percent">
                    <span class="profile-completeness-ring" aria-hidden="true"><b data-profile-completeness-value>80%</b></span>
                    <span class="profile-completeness-copy"><strong>Profile completeness</strong><small data-profile-completeness-message>Almost there! Keep your profile up to date for a better experience.</small></span>
                </div>
            </aside>

            <section class="profile-form-card">
                <?php echo form_open('portal/profile', array('id' => 'portal-profile-form', 'autocomplete' => 'off')); ?>
                    <section class="profile-form-section" aria-labelledby="personal-information-title">
                        <header class="profile-section-heading">
                            <span class="profile-section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3"/><path d="M5 20c.6-4 2.9-6 7-6s6.4 2 7 6"/></svg>
                            </span>
                            <span><h2 id="personal-information-title">Personal information</h2><p>Update the name displayed throughout your User Portal.</p></span>
                        </header>

                        <div class="profile-field-grid">
                            <label class="profile-field">
                                <span>Complete name <strong aria-hidden="true">*</strong></span>
                                <span class="profile-input-shell">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 20c.6-4 2.9-6 7-6s6.4 2 7 6"/></svg>
                                    <input id="profile-complete-name" type="text" name="complete_name" value="<?php echo html_escape(set_value('complete_name', $profile['emp_name'])); ?>" maxlength="150" autocomplete="name" aria-label="Complete name" readonly required>
                                </span>
                                <?php echo form_error('complete_name', '<small class="profile-field-error">', '</small>'); ?>
                            </label>

                            <label class="profile-field">
                                <span>Department</span>
                                <span class="profile-input-shell">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V4h10v17M9 8h2M9 12h2M9 16h2M15 10h4v11M3 21h18"/></svg>
                                    <input type="text" value="<?php echo html_escape($profile['dept_name']); ?>" readonly>
                                </span>
                            </label>
                        </div>
                    </section>

                    <section class="profile-form-section" aria-labelledby="account-security-title">
                        <header class="profile-section-heading">
                            <span class="profile-section-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3M12 14v3"/></svg>
                            </span>
                            <span><h2 id="account-security-title">Account security</h2><p>Leave both password fields blank to keep your current password.</p></span>
                        </header>

                        <div class="profile-security-layout">
                            <div class="profile-field-grid">
                                <label class="profile-field">
                                    <span>Username <strong aria-hidden="true">*</strong></span>
                                    <span class="profile-input-shell">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M5 20c.6-4 2.9-6 7-6s6.4 2 7 6"/></svg>
                                        <input type="text" name="username" value="<?php echo html_escape(set_value('username', $profile['username'])); ?>" maxlength="25" required>
                                    </span>
                                    <?php echo form_error('username', '<small class="profile-field-error">', '</small>'); ?>
                                </label>

                                <label class="profile-field">
                                    <span>New password</span>
                                    <span class="profile-password-field">
                                        <svg class="profile-input-leading-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg>
                                        <input
                                            id="profile-new-password"
                                            type="password"
                                            name="new_password"
                                            minlength="8"
                                            maxlength="50"
                                            placeholder="Leave blank to keep current password">
                                        <button type="button" data-profile-password-toggle="profile-new-password" aria-label="Show new password">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/>
                                                <circle cx="12" cy="12" r="2.5"/>
                                            </svg>
                                        </button>
                                    </span>
                                    <?php echo form_error('new_password', '<small class="profile-field-error">', '</small>'); ?>
                                    <span class="profile-password-strength" data-profile-strength aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                                    <small class="profile-password-strength-label" data-profile-strength-label>Password strength: —</small>
                                </label>

                                <label class="profile-field">
                                    <span>Confirm new password</span>
                                    <span class="profile-password-field">
                                        <svg class="profile-input-leading-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg>
                                        <input
                                            id="profile-confirm-password"
                                            type="password"
                                            name="confirm_password"
                                            minlength="8"
                                            maxlength="50"
                                            placeholder="Repeat the new password">
                                        <button type="button" data-profile-password-toggle="profile-confirm-password" aria-label="Show confirmed password">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/>
                                                <circle cx="12" cy="12" r="2.5"/>
                                            </svg>
                                        </button>
                                    </span>
                                    <?php echo form_error('confirm_password', '<small class="profile-field-error">', '</small>'); ?>
                                </label>

                                <label class="profile-field">
                                    <span>Date registered</span>
                                    <span class="profile-input-shell">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>
                                        <input type="text" value="<?php echo html_escape($profile['date_registered']); ?>" readonly>
                                    </span>
                                </label>
                            </div>

                            <aside class="profile-password-guidance">
                                <h3>Password guidance</h3>
                                <p>For a stronger account, use:</p>
                                <ul data-profile-password-rules>
                                    <li data-password-rule="length">At least 8 characters</li>
                                    <li data-password-rule="case">Uppercase and lowercase letters</li>
                                    <li data-password-rule="number">At least one number</li>
                                    <li data-password-rule="special">At least one special character</li>
                                </ul>
                                <small>Leave blank to keep your current password.</small>
                            </aside>
                        </div>
                    </section>

                    <footer class="profile-form-actions">
                        <button type="reset" class="profile-reset-button">Reset changes</button>
                        <button type="submit" class="profile-save-button">Save changes</button>
                    </footer>
                <?php echo form_close(); ?>
            </section>
        </div>
    </div>
</section>

<script src="<?php echo base_url('assets/js/rms-profile.js?v=52'); ?>"></script>
<?php $this->load->view('user/partials/footer'); ?>