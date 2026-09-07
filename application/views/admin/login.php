<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-login.css'); ?>">
</head>
<body>
    <main class="login-page">
        <section class="login-shell" aria-labelledby="login-title">
            <aside class="brand-panel">
                <div class="brand-content">
                    <div class="brand-lockup">
                        <div class="brand-mark" aria-hidden="true">
                            <span class="brand-page brand-page-one"></span>
                            <span class="brand-page brand-page-two"></span>
                            <span class="brand-page brand-page-three"></span>
                            <span class="brand-spine"></span>
                        </div>
                        <div>
                            <p class="brand-kicker">RMS ADMIN PORTAL</p>
                            <h1><?php echo html_escape($system_name); ?></h1>
                        </div>
                    </div>

                    <div class="brand-message">
                        <span class="message-line"></span>
                        <p>Organized records.<br>Clearer decisions.</p>
                    </div>

                    <div class="security-note">
                        <span class="security-icon" aria-hidden="true"></span>
                        <div>
                            <strong>Authorized staff access</strong>
                            <span>Use your existing RMS account.</span>
                        </div>
                    </div>
                </div>

                <div class="brand-orb brand-orb-one" aria-hidden="true"></div>
                <div class="brand-orb brand-orb-two" aria-hidden="true"></div>
                <div class="brand-grid" aria-hidden="true"></div>
            </aside>

            <div class="form-panel">
                <div class="form-wrap">
                    <div class="mobile-brand">
                        <div class="mobile-mark" aria-hidden="true">R</div>
                        <span>RMS Admin</span>
                    </div>

                    <div class="form-heading">
                        <p class="eyebrow">WELCOME BACK</p>
                        <h2 id="login-title">Sign in to continue</h2>
                        <p>Enter your current administrator credentials.</p>
                    </div>

                    <?php if (isset($login_error) && $login_error !== ''
                        && (!isset($access_restricted) || $access_restricted !== TRUE)): ?>
                        <div class="alert alert-error" role="alert">
                            <span class="alert-icon" aria-hidden="true">!</span>
                            <span><?php echo html_escape($login_error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (validation_errors() !== ''): ?>
                        <div class="alert alert-error" role="alert">
                            <span class="alert-icon" aria-hidden="true">!</span>
                            <div><?php echo validation_errors(); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php echo form_open('administrator', array(
                        'class' => 'login-form',
                        'id' => 'login-form',
                        'autocomplete' => 'off',
                        'novalidate' => 'novalidate'
                    )); ?>
                        <div class="field-group">
                            <label for="username">Username</label>
                            <div class="input-wrap">
                                <span class="input-icon user-icon" aria-hidden="true"></span>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    value="<?php echo html_escape(set_value('username')); ?>"
                                    maxlength="25"
                                    autocomplete="off"
                                    autocapitalize="none"
                                    autocorrect="off"
                                    spellcheck="false"
                                    data-lpignore="true"
                                    data-1p-ignore="true"
                                    readonly
                                    onfocus="this.removeAttribute('readonly');"
                                    placeholder="Enter your username"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>

                        <div class="field-group">
                            <label for="password">Password</label>
                            <div class="input-wrap">
                                <span class="input-icon password-icon" aria-hidden="true"></span>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    maxlength="50"
                                    autocomplete="new-password"
                                    data-lpignore="true"
                                    data-1p-ignore="true"
                                    readonly
                                    onfocus="this.removeAttribute('readonly');"
                                    placeholder="Enter your password"
                                    required
                                >
                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="password-toggle"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >
                                    <span class="eye-icon" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>

                        <div class="button-row">
                            <button type="submit" class="button button-primary">
                                <span>Sign in</span>
                                <span class="arrow-icon" aria-hidden="true">&rarr;</span>
                            </button>
                            <button type="reset" class="button button-secondary">Clear</button>
                        </div>
                    <?php echo form_close(); ?>

                    <p class="support-text">Having trouble signing in? Contact your system administrator.</p>
                </div>

                <footer class="login-footer">
                    <span><?php echo html_escape($company_name); ?></span>
                    <span class="footer-status"><i aria-hidden="true"></i> Internal system</span>
                </footer>
            </div>
        </section>
    </main>

    <?php if (isset($access_restricted) && $access_restricted === TRUE): ?>
        <!-- Server-confirmed role restriction: no Administrator session exists. -->
        <div class="access-modal-backdrop is-visible" id="access-restricted-modal">
            <section
                class="access-modal"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="access-modal-title"
                aria-describedby="access-modal-message">
                <span class="access-modal-icon" aria-hidden="true">
                    <i></i>
                </span>
                <p class="access-modal-kicker">ADMINISTRATOR PORTAL</p>
                <h2 id="access-modal-title">Access restricted</h2>
                <p id="access-modal-message">
                    This account cannot access the Administrator Portal.
                    Please sign in through the RMS User Portal instead.
                </p>
                <div class="access-modal-actions">
                    <a class="access-modal-primary" href="<?php echo site_url(); ?>">
                        Login to User Portal
                    </a>
                    <button class="access-modal-secondary" type="button" data-access-modal-close>
                        Close
                    </button>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <script src="<?php echo base_url('assets/js/rms-login.js'); ?>"></script>
</body>
</html>
