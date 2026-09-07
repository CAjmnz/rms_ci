<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/* Page settings consumed by the reusable header and sidebar partials. */
$active_page = 'dashboard';
$topbar_title = 'My Workspace';
$this->load->view('user/partials/header');
?>

<section class="portal-page-content">
    <div class="portal-content-container">
        <!-- Dashboard welcome banner. -->
        <section class="portal-subhero portal-dashboard-hero" aria-labelledby="dashboard-title">
            <div class="portal-hero-copy">
                <span class="portal-eyebrow"><i></i> USER WORKSPACE</span>
                <h1 id="dashboard-title">Welcome, <?php echo html_escape($display_name); ?></h1>
                <p>Find your assigned folders and records in one secure workspace.</p>
                <a class="portal-primary-link" href="<?php echo site_url('portal/documents'); ?>">
                    <span>Browse my documents</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </a>
            </div>

            <div class="hero-stat" aria-label="Tagged locations">
                <span class="hero-stat-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h6l2 2h8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/></svg>
                </span>
                <strong><?php echo (int) $permission_count; ?></strong>
                <span>Tagged locations</span>
            </div>
        </section>

        <!-- Section title follows one left-aligned content column. -->
        <header class="portal-section-heading">
            <div>
                <span>QUICK ACCESS</span>
                <h2>Your records workspace</h2>
                <p>Everything assigned to your account is organized here.</p>
            </div>
        </header>

        <!-- Dashboard cards use the same icon, text, and action alignment. -->
        <div class="portal-grid">
            <article class="portal-feature-card">
                <span class="card-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                </span>
                <div class="portal-card-copy">
                    <small>AUTHORIZED CONTENT</small>
                    <h3>My Documents</h3>
                    <p>Browse the folders and files the administrator assigned to your account.</p>
                </div>
                <a class="portal-card-action" href="<?php echo site_url('portal/documents'); ?>">
                    <span>Open workspace</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </a>
            </article>

            <article class="portal-feature-card portal-feature-secondary">
                <span class="card-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.9 8.2 7 10 4.1-1.8 7-5.4 7-10V6zM9.5 12l1.7 1.7 3.8-4"/></svg>
                </span>
                <div class="portal-card-copy">
                    <small>ACCOUNT ACCESS</small>
                    <h3>Secure and controlled</h3>
                    <p>Your Viewer level and folder permissions are checked whenever you open a record.</p>
                </div>
                <span class="portal-status-pill"><i></i> Access active</span>
            </article>
        </div>

        <!-- Current organization details from the authenticated portal session. -->
        <section class="portal-info-strip" aria-label="Assigned organization">
            <span class="portal-info-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.2 7-12A7 7 0 1 0 5 9c0 6.8 7 12 7 12zM9.5 9a2.5 2.5 0 1 0 5 0 2.5 2.5 0 0 0-5 0z"/></svg>
            </span>
            <div class="portal-info-copy">
                <strong><?php echo html_escape($subsidiary !== '' ? $subsidiary : 'Assigned subsidiary'); ?></strong>
                <small><?php echo html_escape($department !== '' ? $department : 'Assigned department'); ?></small>
            </div>
            <p>Document access follows the permissions assigned by your administrator.</p>
        </section>
    </div>
</section>

<?php $this->load->view('user/partials/footer'); ?>
