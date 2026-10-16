<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Authentication (Login)
 *
 * Implements login throttling (5 failed attempts lock), password_verify(),
 * session hardening, CSRF verification, and role-based post-login routing.
 */

require_once __DIR__ . '/../includes/header.php';

// If already authenticated, redirect to appropriate destination
if (is_logged_in()) {
    $role = current_user_role();
    if ($role === 'admin') redirect('admin/dashboard.php');
    if ($role === 'staff') redirect('orders/kitchen.php');
    redirect('public/index.php');
}

$email = '';
$errors = [];
$throttleInfo = check_login_throttle('', get_client_ip());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token()) {
        $errors['csrf'] = 'Invalid session token. Please try again.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // 2. Server-side validation
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }

    // 3. Process Authentication if validation passes
    if (empty($errors)) {
        $authResult = authenticate_user($email, $password);

        if ($authResult['success']) {
            set_flash('success', 'Welcome back, ' . e($authResult['user']['full_name']) . '!');

            // Check if redirected from a protected page
            if (!empty($_SESSION['redirect_url'])) {
                $target = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                header('Location: ' . $target);
                exit;
            }

            // Route by Role
            $role = $authResult['user']['role'];
            if ($role === 'admin') {
                redirect('admin/dashboard.php');
            } elseif ($role === 'staff') {
                redirect('orders/kitchen.php');
            } else {
                redirect('public/index.php');
            }
        } else {
            $errors['login'] = $authResult['message'];
            // Re-evaluate throttle state
            $throttleInfo = check_login_throttle($email, get_client_ip());
        }
    }
}
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <span class="logo-badge" style="margin: 0 auto 1rem; width: 48px; height: 48px; font-size: 1.6rem;" aria-hidden="true">🔐</span>
                <h1>Sign In</h1>
                <p>Access your UrbanEats cloud kitchen account</p>
            </div>

            <?php if (!empty($errors['login'])): ?>
                <div class="alert alert-error" role="alert">
                    <span class="alert-icon">✕</span>
                    <span class="alert-text"><?= e($errors['login']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($throttleInfo['locked']): ?>
                <div class="alert alert-warning" role="alert">
                    <span class="alert-icon">⏳</span>
                    <span class="alert-text">
                        Account login locked due to 5 consecutive failed attempts.
                        Please wait <strong><?= e($throttleInfo['remaining_minutes']) ?> minute(s)</strong> before retrying.
                    </span>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/login.php') ?>" method="POST" novalidate>
                <?= csrf_input() ?>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        class="form-control" 
                        placeholder="e.g. nuwan@urbaneats.lk"
                        value="<?= e($email) ?>" 
                        required 
                        autocomplete="email"
                        <?= $throttleInfo['locked'] ? 'disabled' : '' ?>
                    >
                    <?php if (!empty($errors['email'])): ?>
                        <span class="form-error-feedback"><?= e($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                        <label for="password" class="form-label" style="margin-bottom: 0;">Password <span class="required">*</span></label>
                        <a href="<?= base_url('auth/forgot_password.php') ?>" style="font-size: 0.825rem;">Forgot password?</a>
                    </div>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        class="form-control" 
                        placeholder="••••••••••••"
                        required 
                        autocomplete="current-password"
                        <?= $throttleInfo['locked'] ? 'disabled' : '' ?>
                    >
                    <?php if (!empty($errors['password'])): ?>
                        <span class="form-error-feedback"><?= e($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <button 
                    type="submit" 
                    class="btn btn-primary btn-block btn-lg" 
                    <?= $throttleInfo['locked'] ? 'disabled' : '' ?>
                >
                    Sign In
                </button>
            </form>

            <div class="auth-footer">
                Don't have an account yet? 
                <a href="<?= base_url('auth/register.php') ?>" style="font-weight: 600;">Create Account</a>
            </div>

            <!-- Viva & Testing Credentials Box -->
            <div class="demo-box">
                <div class="demo-box-title">
                    <span>💡</span> Demo & Testing Credentials
                </div>
                <div class="demo-account-item">
                    <span>Administrator:</span>
                    <code>admin@urbaneats.lk / Admin@12345</code>
                </div>
                <div class="demo-account-item">
                    <span>Kitchen Staff:</span>
                    <code>staff@urbaneats.lk / Staff@12345</code>
                </div>
                <div class="demo-account-item">
                    <span>Customer:</span>
                    <code>customer@urbaneats.lk / Customer@12345</code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
