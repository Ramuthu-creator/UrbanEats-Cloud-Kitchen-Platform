<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared 403 Forbidden / Access Denied Page
 * 
 * Requirement:
 * Shared page layout and an "access denied" page used by the whole team.
 */

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="card" style="max-width: 540px; text-align: center; padding: 3rem 2rem;">
            <div style="font-size: 4rem; line-height: 1; margin-bottom: 1rem;">⛔</div>
            <span class="badge badge-inactive" style="margin-bottom: 1rem;">HTTP 403 Forbidden</span>
            <h1 style="font-size: 2rem; margin-bottom: 0.75rem;">Access Denied</h1>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                You do not have the required permissions or role privileges to view this page.
                <?php if (is_logged_in()): ?>
                    Your current account is authenticated as <strong style="color: var(--text-primary);"><?= e(strtoupper(current_user_role())) ?></strong>.
                <?php else: ?>
                    You are not logged in.
                <?php endif; ?>
            </p>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <?php if (!is_logged_in()): ?>
                    <a href="<?= base_url('auth/login.php') ?>" class="btn btn-primary">Sign In</a>
                <?php elseif (has_role('admin')): ?>
                    <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-primary">Return to Admin Dashboard</a>
                <?php elseif (has_role('staff')): ?>
                    <a href="<?= base_url('orders/kitchen.php') ?>" class="btn btn-primary">Go to Kitchen Queue</a>
                <?php else: ?>
                    <a href="<?= base_url('public/index.php') ?>" class="btn btn-primary">Return to UrbanEats Home</a>
                <?php endif; ?>
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
            </div>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); font-size: 0.8rem; color: var(--text-muted);">
                Security Notice: All unauthorized page access attempts are recorded in the security audit trail with your IP address and timestamp.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
