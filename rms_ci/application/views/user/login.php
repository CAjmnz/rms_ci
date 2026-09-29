<!DOCTYPE html>
<html lang="en">
<head>
    <!-- User Portal page metadata and shared stylesheet. -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($page_title); ?> | <?php echo html_escape($system_name); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-user-portal.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/rms-portal-routing.css'); ?>">
</head>
<body class="portal-login-body">
    <!-- Two-panel login shell with the same brand mark as the Admin Portal. -->
    <main class="portal-login-page">
        <section class="portal-login-shell" aria-labelledby="portal-login-title">
            <aside class="portal-brand-panel">
                <!-- RMS brand and portal purpose. -->
                <div class="portal-brand-lockup">
                    <span class="rms-brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
                    <div><p>RMS USER PORTAL</p><h1><?php echo html_escape($system_name); ?></h1></div>
                </div>
                <div class="portal-brand-message">
                    <span>YOUR SECURE WORKSPACE</span>
                    <h2>Find the records<br>assigned to you.</h2>
                    <p>Search, view, and download authorized documents from one focused workspace.</p>
                </div>
                <div class="portal-security-note"><span aria-hidden="true">&#128274;</span><div><strong>Permission-protected</strong><small>Only your tagged files and folders will appear.</small></div></div>
                <i class="brand-orb orb-one" aria-hidden="true"></i><i class="brand-orb orb-two" aria-hidden="true"></i>
            </aside>

            <section class="portal-form-panel">
                <!-- Authentication form and server-side validation feedback. -->
                <div class="portal-form-wrap">
                    <div class="mobile-portal-brand"><span class="rms-brand-mark small" aria-hidden="true"><i></i><i></i><i></i></span><strong>ALTURAS RMS</strong></div>
                    <header class="portal-form-heading"><p>WELCOME BACK</p><h2 id="portal-login-title">Sign in to your workspace</h2><span>Use your existing RMS user account.</span></header>

                    <?php if (($login_error !== ''
                        && (!isset($admin_account_restricted) || $admin_account_restricted !== TRUE))
                        || validation_errors() !== ''): ?>
                        <div class="portal-alert" role="alert"><strong>!</strong><div><?php echo $login_error !== '' ? html_escape($login_error) : validation_errors(); ?></div></div>
                    <?php endif; ?>

                    <?php echo form_open('portal', array('class' => 'portal-login-form', 'id' => 'portal-login-form')); ?>
                        <!-- Existing RMS username. -->
                        <label for="portal-username">Username</label>
                        <div class="portal-input-wrap"><span class="field-icon" aria-hidden="true">&#128100;</span><input id="portal-username" name="username" type="text" maxlength="25" value="<?php echo html_escape(set_value('username')); ?>" placeholder="Enter your username" autocomplete="username" required autofocus></div>
                        <!-- Existing RMS password with an accessible visibility control. -->
                        <label for="portal-password">Password</label>
                        <div class="portal-input-wrap"><span class="field-icon" aria-hidden="true">&#128274;</span><input id="portal-password" name="password" type="password" maxlength="50" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="password-toggle" id="portal-password-toggle" aria-label="Show password" aria-pressed="false">&#128065;</button></div>
                        <!-- Login and reset actions. -->
                        <div class="portal-button-row"><button type="submit" class="portal-primary-button"><span>Sign in</span><b aria-hidden="true">&rarr;</b></button><button type="reset" class="portal-secondary-button">Clear</button></div>
                    <?php echo form_close(); ?>
                    <p class="portal-help">Need account assistance? Contact your system administrator.</p>
                </div>
                <footer><span><?php echo html_escape($company_name); ?></span><span><i></i> Internal system</span></footer>
            </section>
        </section>
    </main>

    <?php if (isset($admin_account_restricted) && $admin_account_restricted === TRUE): ?>
        <!-- Valid Admin account entered at the User Portal: guide it safely. -->
        <div class="portal-routing-backdrop is-visible" id="portal-routing-modal">
            <section
                class="portal-routing-modal"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="portal-routing-title"
                aria-describedby="portal-routing-message">
                <span class="portal-routing-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.6 2.9 8.2 7 10 4.1-1.8 7-5.4 7-10V6zM9.5 12l1.7 1.7 3.8-4"/></svg>
                </span>
                <p class="portal-routing-kicker">ACCOUNT ROUTING</p>
                <h2 id="portal-routing-title">Admin Portal required</h2>
                <p id="portal-routing-message">
                    Administrator accounts must use the Admin Portal.
                    Continue to the correct secure login page below.
                </p>
                <div class="portal-routing-actions">
                    <a href="<?php echo site_url('administrator'); ?>">Login to Admin Portal</a>
                    <button type="button" data-portal-routing-close>Close</button>
                </div>
            </section>
        </div>
    <?php endif; ?>
    <!-- Portal-only interactions. -->
    <script src="<?php echo base_url('assets/js/rms-user-portal.js'); ?>"></script>
</body>
</html>
