<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Password Reset Submission
 *
 * Implements token verification, expiration check, new password hashing,
 * token invalidation, CSRF protection, and audit logging.
 */

require_once __DIR__ . '/../includes/header.php';

$plainToken = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$tokenValid = false;
$resetRecord = null;

if (empty($plainToken)) {
    $errors['token'] = 'No password reset token was provided. Please request a new reset link.';
} else {
    try {
        $db = get_db();
        $tokenHash = hash('sha256', $plainToken);

        $stmt = $db->prepare(
            "SELECT id, email, expires_at, is_used 
             FROM password_resets 
             WHERE token_hash = :hash 
             LIMIT 1"
        );
        $stmt->execute([':hash' => $tokenHash]);
        $resetRecord = $stmt->fetch();

        if (!$resetRecord) {
            $errors['token'] = 'The password reset token is invalid.';
        } elseif ((int)$resetRecord['is_used'] === 1) {
            $errors['token'] = 'This password reset link has already been used. Please request a new one.';
        } elseif (strtotime($resetRecord['expires_at']) < time()) {
            $errors['token'] = 'This password reset link has expired. Reset tokens are valid for 60 minutes only.';
        } else {
            $tokenValid = true;
        }
    } catch (Exception $e) {
        log_system_error('TOKEN_VERIFICATION_FAIL', $e->getMessage());
        $errors['token'] = 'System error verifying reset token.';
    }
}

// Process Password Reset Submission
if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors['csrf'] = 'Invalid session security token. Please try again.';
    }

    $newPassword = $_POST['new_password'] ?? '';
    $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

    // Password Complexity Validation
    if (empty($newPassword)) {
        $errors['new_password'] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $errors['new_password'] = 'Password must contain at least one uppercase letter (A-Z).';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $errors['new_password'] = 'Password must contain at least one lowercase letter (a-z).';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $errors['new_password'] = 'Password must contain at least one number (0-9).';
    }

    if (empty($newPasswordConfirm)) {
        $errors['new_password_confirm'] = 'Please confirm your new password.';
    } elseif ($newPassword !== $newPasswordConfirm) {
        $errors['new_password_confirm'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $db = get_db();
            $db->beginTransaction();

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update user's password
            $updateUserStmt = $db->prepare(
                "UPDATE users 
                 SET password_hash = :hash, updated_at = NOW() 
                 WHERE email = :email"
            );
            $updateUserStmt->execute([
                ':hash'  => $newHash,
                ':email' => $resetRecord['email']
            ]);

            // Mark token as used
            $markUsedStmt = $db->prepare(
                "UPDATE password_resets 
                 SET is_used = 1 
                 WHERE id = :id"
            );
            $markUsedStmt->execute([':id' => $resetRecord['id']]);

            $db->commit();

            log_action('PASSWORD_RESET_COMPLETED', "Password reset completed for email: {$resetRecord['email']}");

            set_flash('success', 'Your password has been reset successfully! You can now sign in with your new password.');
            redirect('auth/login.php');
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            log_system_error('PASSWORD_RESET_SUBMIT_FAIL', $e->getMessage());
            $errors['general'] = 'Failed to reset password due to a server error. Please try again.';
        }
    }
}
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 500px;">
            <div class="auth-header">
                <span class="logo-badge" style="margin: 0 auto 1rem; width: 48px; height: 48px; font-size: 1.6rem;" aria-hidden="true">🔄</span>
                <h1>Create New Password</h1>
                <p>Choose a secure new password for your UrbanEats account</p>
            </div>

            <?php if (!empty($errors['token'])): ?>
                <div class="alert alert-error" role="alert">
                    <span class="alert-icon">✕</span>
                    <span class="alert-text"><?= e($errors['token']) ?></span>
                </div>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="<?= base_url('auth/forgot_password.php') ?>" class="btn btn-primary">
                        Request New Reset Link
                    </a>
                </div>
            <?php elseif ($tokenValid): ?>
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-error" role="alert">
                        <span class="alert-icon">✕</span>
                        <span class="alert-text"><?= e($errors['general']) ?></span>
                    </div>
                <?php endif; ?>

                <form action="<?= base_url('auth/reset_password.php') ?>" method="POST" novalidate>
                    <?= csrf_input() ?>
                    <input type="hidden" name="token" value="<?= e($plainToken) ?>">

                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password <span class="required">*</span></label>
                        <input 
                            type="password" 
                            name="new_password" 
                            id="new_password" 
                            class="form-control" 
                            placeholder="Minimum 8 characters"
                            required 
                            autocomplete="new-password"
                        >
                        <span class="form-hint">Must have at least 8 chars with uppercase, lowercase, and a number</span>
                        <?php if (!empty($errors['new_password'])): ?>
                            <span class="form-error-feedback"><?= e($errors['new_password']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="new_password_confirm" class="form-label">Confirm New Password <span class="required">*</span></label>
                        <input 
                            type="password" 
                            name="new_password_confirm" 
                            id="new_password_confirm" 
                            class="form-control" 
                            placeholder="Re-type new password"
                            required 
                            autocomplete="new-password"
                        >
                        <?php if (!empty($errors['new_password_confirm'])): ?>
                            <span class="form-error-feedback"><?= e($errors['new_password_confirm']) ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                        Save New Password & Sign In
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                Back to 
                <a href="<?= base_url('auth/login.php') ?>" style="font-weight: 600;">Sign In</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
