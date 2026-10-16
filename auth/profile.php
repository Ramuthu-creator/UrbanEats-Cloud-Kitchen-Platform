<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Profile & Account Management
 *
 * Implements view and update of personal profile details with
 * server-side validation, CSRF protection, and audit logging.
 */

require_once __DIR__ . '/../includes/header.php';
require_login();

$user = current_user();
if (!$user) {
    set_flash('error', 'User profile could not be loaded.');
    redirect('auth/logout.php');
}

$errors = [];
$fullName = $user['full_name'];
$phone = $user['phone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors['csrf'] = 'Invalid session security token. Please try again.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($fullName)) {
        $errors['full_name'] = 'Full name is required.';
    } elseif (strlen($fullName) < 3 || strlen($fullName) > 100) {
        $errors['full_name'] = 'Full name must be between 3 and 100 characters.';
    } elseif (!preg_match("/^[a-zA-Z\s\.\'-]+$/", $fullName)) {
        $errors['full_name'] = 'Full name can only contain letters, spaces, and hyphens.';
    }

    if (empty($phone)) {
        $errors['phone'] = 'Contact phone number is required.';
    } elseif (!preg_match("/^(?:\+94|0)[0-9]{9}$/", preg_replace('/[\s\-]/', '', $phone))) {
        $errors['phone'] = 'Please enter a valid 10-digit Sri Lankan phone number.';
    }

    if (empty($errors)) {
        try {
            $db = get_db();
            $stmt = $db->prepare(
                "UPDATE users 
                 SET full_name = :name, phone = :phone
                 WHERE id = :id"
            );
            $stmt->execute([
                ':name'  => $fullName,
                ':phone' => $phone,
                ':id'    => $user['id']
            ]);

            $_SESSION['user_name'] = $fullName;
            log_action('PROFILE_UPDATED', "User updated profile name to '{$fullName}' and phone to '{$phone}'");

            set_flash('success', 'Your profile details have been successfully updated.');
            redirect('auth/profile.php');
        } catch (Exception $e) {
            log_system_error('PROFILE_UPDATE_FAIL', $e->getMessage());
            $errors['general'] = 'Failed to update profile. Please try again.';
        }
    }
}
?>

<div class="container">
    <div style="max-width: 760px; margin: 2rem auto;">
        <!-- Header Banner -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="badge badge-<?= e($user['role']) ?>"><?= e(strtoupper($user['role_display'])) ?></span>
                <h1 style="margin-top: 0.5rem; font-size: 2rem;">My Account Profile</h1>
                <p style="margin-bottom: 0;">Manage your personal account credentials and contact details</p>
            </div>
            <div>
                <a href="<?= base_url('auth/change_password.php') ?>" class="btn btn-secondary">
                    🔒 Change Password
                </a>
            </div>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error" role="alert">
                <span class="alert-icon">✕</span>
                <span class="alert-text"><?= e($errors['general']) ?></span>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Personal Details</h2>
                    <p class="card-subtitle">Keep your contact information up-to-date for delivery notifications</p>
                </div>
                <div>
                    <span class="badge badge-active">Active Account</span>
                </div>
            </div>

            <form action="<?= base_url('auth/profile.php') ?>" method="POST" novalidate>
                <?= csrf_input() ?>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address (Read Only)</label>
                    <input 
                        type="email" 
                        id="email" 
                        class="form-control" 
                        value="<?= e($user['email']) ?>" 
                        disabled
                        style="opacity: 0.7; cursor: not-allowed;"
                    >
                    <span class="form-hint">Email address is linked to your account identity and cannot be altered directly.</span>
                </div>

                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name <span class="required">*</span></label>
                    <input 
                        type="text" 
                        name="full_name" 
                        id="full_name" 
                        class="form-control" 
                        value="<?= e($fullName) ?>" 
                        required
                    >
                    <?php if (!empty($errors['full_name'])): ?>
                        <span class="form-error-feedback"><?= e($errors['full_name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number <span class="required">*</span></label>
                    <input 
                        type="tel" 
                        name="phone" 
                        id="phone" 
                        class="form-control" 
                        value="<?= e($phone) ?>" 
                        required
                    >
                    <?php if (!empty($errors['phone'])): ?>
                        <span class="form-error-feedback"><?= e($errors['phone']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group" style="padding-top: 0.5rem; border-top: 1px solid var(--border-color);">
                    <label class="form-label" style="color: var(--text-muted); font-size: 0.825rem;">Account Registration Date</label>
                    <div style="font-size: 0.9rem; color: var(--text-secondary);">
                        <?= format_datetime($user['created_at']) ?>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        Save Profile Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
