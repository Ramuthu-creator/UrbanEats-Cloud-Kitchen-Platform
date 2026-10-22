<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Change Password
 *
 * Implements current password verification with password_verify(),
 * complexity validation, password_hash() storage, CSRF, and audit logging.
 */

require_once __DIR__ . '/../includes/header.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors['csrf'] = 'Invalid session security token. Please try again.';
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

    // 1. Current Password Validation
    if (empty($currentPassword)) {
        $errors['current_password'] = 'Current password is required.';
    }

    // 2. New Password Validation
    if (empty($newPassword)) {
        $errors['new_password'] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $errors['new_password'] = 'New password must contain at least one uppercase letter (A-Z).';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $errors['new_password'] = 'New password must contain at least one lowercase letter (a-z).';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $errors['new_password'] = 'New password must contain at least one number (0-9).';
    } elseif ($currentPassword === $newPassword) {
        $errors['new_password'] = 'New password must be different from your current password.';
    }

    // 3. Confirm New Password
    if (empty($newPasswordConfirm)) {
        $errors['new_password_confirm'] = 'Please confirm your new password.';
    } elseif ($newPassword !== $newPasswordConfirm) {
        $errors['new_password_confirm'] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $db = get_db();
            $userId = current_user_id();

            // Fetch current password hash from DB
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
                $errors['current_password'] = 'The current password you entered is incorrect.';
                log_action('PASSWORD_CHANGE_FAILED', 'Failed password change: Incorrect current password');
            } else {
                // Hash new password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $updateStmt->execute([
                    ':hash' => $newHash,
                    ':id'   => $userId
                ]);

                log_action('PASSWORD_CHANGED', 'User successfully changed account password');
                set_flash('success', 'Your password has been changed successfully.');
                redirect('auth/profile.php');
            }
        } catch (Exception $e) {
            log_system_error('PASSWORD_UPDATE_FAIL', $e->getMessage());
            $errors['general'] = 'An unexpected error occurred while updating your password.';
        }
    }
}
?>

<div class="container">
    <div style="max-width: 540px; margin: 2rem auto;">
        <div class="card">
            <div class="card-header">
                <div>
                    <h1 class="card-title" style="font-size: 1.5rem;">🔒 Change Password</h1>
                    <p class="card-subtitle">Ensure your account remains safe with a strong, unique password</p>
                </div>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-error" role="alert">
                    <span class="alert-icon">✕</span>
                    <span class="alert-text"><?= e($errors['general']) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/change_password.php') ?>" method="POST" novalidate>
                <?= csrf_input() ?>

                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        name="current_password" 
                        id="current_password" 
                        class="form-control" 
                        required 
                        autocomplete="current-password"
                    >
                    <?php if (!empty($errors['current_password'])): ?>
                        <span class="form-error-feedback"><?= e($errors['current_password']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="new_password" class="form-label">New Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        name="new_password" 
                        id="new_password" 
                        class="form-control" 
                        required 
                        autocomplete="new-password"
                    >
                    <span class="form-hint">At least 8 characters with uppercase, lowercase, and numeric digits</span>
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
                        required 
                        autocomplete="new-password"
                    >
                    <?php if (!empty($errors['new_password_confirm'])): ?>
                        <span class="form-error-feedback"><?= e($errors['new_password_confirm']) ?></span>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <a href="<?= base_url('auth/profile.php') ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
