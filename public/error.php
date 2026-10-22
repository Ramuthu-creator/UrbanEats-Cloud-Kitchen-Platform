<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared 500 Internal Server Error Page
 *
 * Requirement:
 * Friendly error pages, errors written to a log file, and no raw PHP or SQL errors shown to user.
 */

require_once __DIR__ . '/../includes/header.php';
$errorMessage = $errorMessage ?? 'An unexpected system error occurred while processing your request.';
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="card" style="max-width: 540px; text-align: center; padding: 3rem 2rem;">
            <div style="font-size: 4rem; line-height: 1; margin-bottom: 1rem;">⚠️</div>
            <span class="badge badge-warning" style="margin-bottom: 1rem;">HTTP 500 Server Error</span>
            <h1 style="font-size: 2rem; margin-bottom: 0.75rem;">Something Went Wrong</h1>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                <?= e($errorMessage) ?>
            </p>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 2rem;">
                This incident has been securely recorded to the server error log for technical review.
            </p>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?= base_url('public/index.php') ?>" class="btn btn-primary">Return to Homepage</a>
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
