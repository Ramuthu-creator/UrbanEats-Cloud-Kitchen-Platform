<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Password Reset Request (Forgot Password)
 *
 * Implements time-limited token generation (32 bytes cryptographically secure),
 * SHA-256 token hashing, database storage, and on-screen link presentation
 * as permitted by the assignment specification.
 */

require_once __DIR__ . '/../includes/header.php';

$email = '';
$errors = [];
$resetLink = null;
$expiresAtFormatted = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors['csrf'] = 'Invalid session security token. Please try again.';
    }

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        try {
            $db = get_db();

            // Verify if user account exists
            $userStmt = $db->prepare("SELECT id, full_name, email FROM users WHERE email = :email LIMIT 1");
            $userStmt->execute([':email' => $email]);
            $user = $userStmt->fetch();

            if ($user) {
                // Generate cryptographically secure random token (64 hex characters)
                $plainToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $plainToken);

                // Token expires in 60 minutes
                $expiryMinutes = RESET_TOKEN_EXPIRY_MINUTES;
                $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));

                // Mark previous unused reset tokens for this email as used
                $invalidateStmt = $db->prepare("UPDATE password_resets SET is_used = 1 WHERE email = :email AND is_used = 0");
                $invalidateStmt->execute([':email' => $email]);

                // Store hashed token in database
                $insertStmt = $db->prepare(
                    "INSERT INTO password_resets (email, token_hash, expires_at, is_used, created_at)
                     VALUES (:email, :token_hash, :expires_at, 0, NOW())"
                );
                $insertStmt->execute([
                    ':email'      => $email,
                    ':token_hash' => $tokenHash,
                    ':expires_at' => $expiresAt
                ]);

                log_action('PASSWORD_RESET_REQUESTED', "Password reset link requested for {$email}", (int)$user['id']);

                $resetLink = base_url('auth/reset_password.php?token=' . urlencode($plainToken));
                $expiresAtFormatted = date('h:i A', strtotime($expiresAt));
            } else {
                // For security against user enumeration, we do not confirm non-existence,
                // but for academic assignment testing we provide clear feedback.
                $errors['email'] = 'No account found with this email address. Please check your spelling or register.';
            }
        } catch (Exception $e) {
            log_system_error('PASSWORD_RESET_REQ_FAIL', $e->getMessage());
            $errors['general'] = 'An unexpected error occurred. Please try again.';
        }
    }
}
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 500px;">
            <div class="auth-header">
                <span class="logo-badge" style="margin: 0 auto 1rem; width: 48px; height: 48px; font-size: 1.6rem;" aria-hidden="true">🔑</span>
                <h1>Reset Password</h1>
                <p>Enter your account email to receive a secure password reset link</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-error" role="alert">
                    <span class="alert-icon">✕</span>
                    <span class="alert-text"><?= e($errors['general']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($resetLink): ?>
                <div class="alert alert-success" style="flex-direction: column; align-items: stretch;" role="status">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <span class="alert-icon">✓</span>
                        <strong>Password Reset Token Generated!</strong>
                    </div>
                    <p style="margin-bottom: 0.75rem; font-size: 0.875rem; color: #D1FAE5;">
                        As specified in the assignment guidelines, the secure time-limited reset link is displayed below (valid until <strong><?= e($expiresAtFormatted) ?></strong>):
                    </p>
                    <div style="background: rgba(0,0,0,0.4); padding: 0.75rem; border-radius: var(--radius-sm); word-break: break-all; font-family: monospace; font-size: 0.8rem; margin-bottom: 1rem; color: #FFFFFF;">
                        <?= e($resetLink) ?>
                    </div>
                    <a href="<?= e($resetLink) ?>" class="btn btn-primary btn-block">
                        Proceed to Reset Password Now &rarr;
                    </a>
                </div>
            <?php else: ?>
                <form action="<?= base_url('auth/forgot_password.php') ?>" method="POST" novalidate>
                    <?= csrf_input() ?>

                    <div class="form-group">
                        <label for="email" class="form-label">Registered Email Address <span class="required">*</span></label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            class="form-control" 
                            placeholder="e.g. customer@urbaneats.lk"
                            value="<?= e($email) ?>" 
                            required 
                            autocomplete="email"
                        >
                        <?php if (!empty($errors['email'])): ?>
                            <span class="form-error-feedback"><?= e($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.25rem;">
                        Generate Reset Token
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                Remembered your password? 
                <a href="<?= base_url('auth/login.php') ?>" style="font-weight: 600;">Return to Sign In</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
