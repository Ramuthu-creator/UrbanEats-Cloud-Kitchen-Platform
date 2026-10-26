<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Customer Registration
 *
 * Implements server-side validation, password hashing with password_hash(),
 * unique email constraint checking, CSRF protection, and audit logging.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect
if (is_logged_in()) {
    redirect('public/index.php');
}

$fullName = '';
$email = '';
$phone = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token()) {
        $errors['csrf'] = 'Invalid session security token. Please resubmit.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // 2. Server-side validation
    // Full Name
    if (empty($fullName)) {
        $errors['full_name'] = 'Full name is required.';
    } elseif (strlen($fullName) < 3 || strlen($fullName) > 100) {
        $errors['full_name'] = 'Full name must be between 3 and 100 characters.';
    } elseif (!preg_match("/^[a-zA-Z\s\.\'-]+$/", $fullName)) {
        $errors['full_name'] = 'Full name can only contain letters, spaces, and hyphens.';
    }

    // Email
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please provide a valid email address.';
    } else {
        // Check uniqueness in database
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'An account with this email address already exists. Please sign in instead.';
            }
        } catch (Exception $e) {
            $errors['general'] = 'Database error verifying email uniqueness.';
        }
    }

    // Phone (Sri Lankan format: e.g., 07XXXXXXXX or +94...)
    if (empty($phone)) {
        $errors['phone'] = 'Contact phone number is required.';
    } elseif (!preg_match("/^(?:\+94|0)[0-9]{9}$/", preg_replace('/[\s\-]/', '', $phone))) {
        $errors['phone'] = 'Please enter a valid 10-digit Sri Lankan phone number (e.g., 0771234567).';
    }

    // Password Complexity: min 8 chars, 1 uppercase, 1 lowercase, 1 number
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password must include at least one uppercase letter (A-Z).';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Password must include at least one lowercase letter (a-z).';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must include at least one number (0-9).';
    }

    // Password Confirmation
    if (empty($passwordConfirm)) {
        $errors['password_confirm'] = 'Please confirm your password.';
    } elseif ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    // 3. Register Customer if validation passes
    if (empty($errors)) {
        try {
            $db = get_db();

            // Hash password with standard bcrypt
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Customer role ID = 3
            $stmt = $db->prepare(
                "INSERT INTO users (role_id, full_name, email, phone, password_hash, is_active, created_at)
                 VALUES (3, :name, :email, :phone, :hash, 1, NOW())"
            );

            $stmt->execute([
                ':name'  => $fullName,
                ':email' => $email,
                ':phone' => $phone,
                ':hash'  => $passwordHash
            ]);

            $newUserId = (int)$db->lastInsertId();

            // Log event in audit trail
            log_action('USER_REGISTERED', "Customer {$fullName} registered with email {$email}", $newUserId);

            // Automatically log in the user
            session_regenerate_id(true);
            $_SESSION['user_id']       = $newUserId;
            $_SESSION['role']          = 'customer';
            $_SESSION['role_display']  = 'Customer';
            $_SESSION['user_name']     = $fullName;
            $_SESSION['user_email']    = $email;
            $_SESSION['last_activity'] = time();

            set_flash('success', 'Your UrbanEats account has been created successfully! Welcome aboard.');
            redirect('public/index.php');
        } catch (Exception $e) {
            log_system_error('REGISTRATION_FAILURE', $e->getMessage());
            $errors['general'] = 'Registration failed due to a server error. Please try again.';
        }
    }
}

$pageTitle = 'Create Account - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 520px;">
            <div class="auth-header">
                <span class="logo-badge" style="margin: 0 auto 1rem; width: 48px; height: 48px; font-size: 1.6rem;" aria-hidden="true">📝</span>
                <h1>Create Account</h1>
                <p>Register as an UrbanEats customer to order delicious food</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-error" role="alert">
                    <span class="alert-icon">✕</span>
                    <span class="alert-text"><?= e($errors['general']) ?></span>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/register.php') ?>" method="POST" novalidate>
                <?= csrf_input() ?>

                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name <span class="required">*</span></label>
                    <input 
                        type="text" 
                        name="full_name" 
                        id="full_name" 
                        class="form-control" 
                        placeholder="e.g. Kasun Jayasuriya"
                        value="<?= e($fullName) ?>" 
                        required 
                        autocomplete="name"
                    >
                    <?php if (!empty($errors['full_name'])): ?>
                        <span class="form-error-feedback"><?= e($errors['full_name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        class="form-control" 
                        placeholder="e.g. kasun@example.lk"
                        value="<?= e($email) ?>" 
                        required 
                        autocomplete="email"
                    >
                    <?php if (!empty($errors['email'])): ?>
                        <span class="form-error-feedback"><?= e($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number <span class="required">*</span></label>
                    <input 
                        type="tel" 
                        name="phone" 
                        id="phone" 
                        class="form-control" 
                        placeholder="0771234567"
                        value="<?= e($phone) ?>" 
                        required 
                        autocomplete="tel"
                    >
                    <span class="form-hint">Used for order delivery and driver notifications</span>
                    <?php if (!empty($errors['phone'])): ?>
                        <span class="form-error-feedback"><?= e($errors['phone']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        class="form-control" 
                        placeholder="Minimum 8 characters"
                        required 
                        autocomplete="new-password"
                    >
                    <span class="form-hint">At least 8 characters with uppercase, lowercase, and a number</span>
                    <?php if (!empty($errors['password'])): ?>
                        <span class="form-error-feedback"><?= e($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirm Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        name="password_confirm" 
                        id="password_confirm" 
                        class="form-control" 
                        placeholder="Repeat your password"
                        required 
                        autocomplete="new-password"
                    >
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <span class="form-error-feedback"><?= e($errors['password_confirm']) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1.5rem;">
                    Create Account
                </button>
            </form>

            <div class="auth-footer">
                Already registered? 
                <a href="<?= base_url('auth/login.php') ?>" style="font-weight: 600;">Sign In here</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
