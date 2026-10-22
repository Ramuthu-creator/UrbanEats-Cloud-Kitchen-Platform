<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: User Management Console
 *
 * Implements user listing with keyword search, filter by role, filter by status,
 * activate/deactivate toggle, role changes, new user creation, and full audit logging.
 */

require_once __DIR__ . '/../includes/header.php';
require_role('admin');

$db = get_db();
$currentAdminId = current_user_id();

// Handle POST State Changes (Toggle Status, Change Role, Add User)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('error', 'Security check failed. Please submit the form again.');
        redirect('admin/users.php');
    }

    $postAction = $_POST['action'] ?? '';

    // Action: Toggle Active / Inactive Status
    if ($postAction === 'toggle_status') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newStatus = (int)($_POST['status'] ?? 0);

        if ($targetUserId === $currentAdminId) {
            set_flash('error', 'Security Violation: You cannot deactivate your own administrative account.');
            redirect('admin/users.php');
        }

        try {
            $stmt = $db->prepare("UPDATE users SET is_active = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $targetUserId]);

            $actionLabel = $newStatus === 1 ? 'activated' : 'deactivated';
            log_action('USER_STATUS_CHANGE', "Administrator {$actionLabel} user account ID {$targetUserId}");
            set_flash('success', "User account has been {$actionLabel} successfully.");
        } catch (Exception $e) {
            log_system_error('USER_STATUS_TOGGLE_FAIL', $e->getMessage());
            set_flash('error', 'Failed to update user status.');
        }
        redirect('admin/users.php');
    }

    // Action: Change User Role
    if ($postAction === 'change_role') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newRoleId = (int)($_POST['role_id'] ?? 0);

        if ($targetUserId === $currentAdminId && $newRoleId !== 1) {
            set_flash('error', 'Security Violation: You cannot demote yourself from Administrator role.');
            redirect('admin/users.php');
        }

        if (!in_array($newRoleId, [1, 2, 3], true)) {
            set_flash('error', 'Invalid role selected.');
            redirect('admin/users.php');
        }

        try {
            $stmt = $db->prepare("UPDATE users SET role_id = :role_id WHERE id = :id");
            $stmt->execute([':role_id' => $newRoleId, ':id' => $targetUserId]);

            $roleNames = [1 => 'Administrator', 2 => 'Kitchen Staff', 3 => 'Customer'];
            $roleLabel = $roleNames[$newRoleId] ?? 'Unknown';

            log_action('ROLE_CHANGE', "Administrator changed user ID {$targetUserId} role to {$roleLabel}");
            set_flash('success', "User role has been updated to {$roleLabel}.");
        } catch (Exception $e) {
            log_system_error('ROLE_CHANGE_FAIL', $e->getMessage());
            set_flash('error', 'Failed to change user role.');
        }
        redirect('admin/users.php');
    }

    // Action: Create New User (Admin, Staff, or Customer)
    if ($postAction === 'create_user') {
        $name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 3);
        $password = $_POST['password'] ?? '';

        $createErrors = [];

        if (empty($name) || strlen($name) < 3) $createErrors[] = 'Full name must be at least 3 characters.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $createErrors[] = 'A valid email is required.';
        if (empty($phone)) $createErrors[] = 'Phone number is required.';
        if (empty($password) || strlen($password) < 8) $createErrors[] = 'Password must be at least 8 characters.';
        if (!in_array($roleId, [1, 2, 3], true)) $createErrors[] = 'Invalid role specified.';

        // Check email uniqueness
        if (empty($createErrors)) {
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetch()) {
                $createErrors[] = 'An account with this email address already exists.';
            }
        }

        if (!empty($createErrors)) {
            set_flash('error', implode(' ', $createErrors));
            redirect('admin/users.php');
        }

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare(
                "INSERT INTO users (role_id, full_name, email, phone, password_hash, is_active, created_at)
                 VALUES (:role_id, :name, :email, :phone, :hash, 1, NOW())"
            );
            $stmt->execute([
                ':role_id' => $roleId,
                ':name'    => $name,
                ':email'   => $email,
                ':phone'   => $phone,
                ':hash'    => $hash
            ]);

            $newId = (int)$db->lastInsertId();
            log_action('USER_CREATED', "Admin created user account {$email} with role ID {$roleId}", $newId);
            set_flash('success', "New user account for '{$name}' created successfully.");
        } catch (Exception $e) {
            log_system_error('USER_CREATE_FAIL', $e->getMessage());
            set_flash('error', 'Failed to create user account.');
        }
        redirect('admin/users.php');
    }
}

// Search and Filter GET Parameters
$search = trim($_GET['search'] ?? '');
$filterRole = trim($_GET['role'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build Query with Parameter Binding
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(u.full_name LIKE :search_name OR u.email LIKE :search_email OR u.phone LIKE :search_phone)";
    $params[':search_name'] = "%{$search}%";
    $params[':search_email'] = "%{$search}%";
    $params[':search_phone'] = "%{$search}%";
}

if (!empty($filterRole) && in_array($filterRole, ['admin', 'staff', 'customer'], true)) {
    $whereClauses[] = "r.name = :role_name";
    $params[':role_name'] = $filterRole;
}

if ($filterStatus !== '') {
    if ($filterStatus === 'active') {
        $whereClauses[] = "u.is_active = 1";
    } elseif ($filterStatus === 'inactive') {
        $whereClauses[] = "u.is_active = 0";
    }
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count Total Rows
$countSql = "SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id {$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

// Fetch Filtered Records
$sql = "SELECT u.id, u.role_id, r.name AS role, r.display_name AS role_display,
               u.full_name, u.email, u.phone, u.is_active, u.created_at
        FROM users u
        JOIN roles r ON u.role_id = r.id
        {$whereSql}
        ORDER BY u.id ASC
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-admin">Component A Core</span>
            <h1 style="margin-top: 0.4rem;">User Accounts Management</h1>
            <p style="margin-bottom: 0;">Search, filter, toggle account active states, and manage user roles</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('createUserModal').style.display='flex';">
                ➕ Create New User
            </button>
        </div>
    </div>

    <!-- Filter & Search Control Bar -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form action="<?= base_url('admin/users.php') ?>" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label for="search" class="form-label" style="font-size: 0.825rem;">Search Users</label>
                <input 
                    type="text" 
                    name="search" 
                    id="search" 
                    class="form-control" 
                    placeholder="Search by name, email, or phone..." 
                    value="<?= e($search) ?>"
                >
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label for="role" class="form-label" style="font-size: 0.825rem;">Filter by Role</label>
                <select name="role" id="role" class="form-control form-select">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Kitchen Staff</option>
                    <option value="customer" <?= $filterRole === 'customer' ? 'selected' : '' ?>>Customer</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label for="status" class="form-label" style="font-size: 0.825rem;">Filter by Status</label>
                <select name="status" id="status" class="form-control form-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Deactivated Only</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= base_url('admin/users.php') ?>" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Name & Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th style="text-align: right;">Administrative Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                                No user accounts matched your search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <code>#<?= e($u['id']) ?></code>
                                </td>
                                <td>
                                    <strong><?= e($u['full_name']) ?></strong>
                                    <?php if ($u['id'] == $currentAdminId): ?>
                                        <span class="badge badge-admin" style="font-size: 0.65rem; margin-left: 0.25rem;">You</span>
                                    <?php endif; ?>
                                    <br>
                                    <span style="font-size: 0.825rem; color: var(--text-secondary);"><?= e($u['email']) ?></span>
                                    &bull; 
                                    <span style="font-size: 0.825rem; color: var(--text-muted);"><?= e($u['phone']) ?></span>
                                </td>
                                <td>
                                    <!-- Role Change Form -->
                                    <form action="<?= base_url('admin/users.php') ?>" method="POST" style="display: inline-block;">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                        <select 
                                            name="role_id" 
                                            class="form-control form-select" 
                                            style="padding: 0.3rem 1.8rem 0.3rem 0.6rem; font-size: 0.8rem; width: auto;"
                                            onchange="this.form.submit()"
                                            <?= $u['id'] == $currentAdminId ? 'disabled title="You cannot demote your own account"' : '' ?>
                                        >
                                            <option value="1" <?= $u['role_id'] == 1 ? 'selected' : '' ?>>Admin</option>
                                            <option value="2" <?= $u['role_id'] == 2 ? 'selected' : '' ?>>Staff</option>
                                            <option value="3" <?= $u['role_id'] == 3 ? 'selected' : '' ?>>Customer</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Deactivated</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.825rem; color: var(--text-secondary); white-space: nowrap;">
                                    <?= format_datetime($u['created_at']) ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <!-- Toggle Status Form -->
                                    <?php if ($u['id'] != $currentAdminId): ?>
                                        <form action="<?= base_url('admin/users.php') ?>" method="POST" style="display: inline;">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                            <?php if ($u['is_active']): ?>
                                                <input type="hidden" name="status" value="0">
                                                <button 
                                                    type="submit" 
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Are you sure you want to DEACTIVATE <?= e(addslashes($u['full_name'])) ?>? The user will be unable to log in.');"
                                                >
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="status" value="1">
                                                <button 
                                                    type="submit" 
                                                    class="btn btn-success btn-sm"
                                                    onclick="return confirm('Activate <?= e(addslashes($u['full_name'])) ?>?');"
                                                >
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    Showing page <?= e($page) ?> of <?= e($totalPages) ?> (<?= e($totalRows) ?> total users)
                </div>
                <div style="display: flex; gap: 0.4rem;">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a 
                            href="<?= base_url('admin/users.php?page=' . $p . '&search=' . urlencode($search) . '&role=' . urlencode($filterRole) . '&status=' . urlencode($filterStatus)) ?>" 
                            class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
                        >
                            <?= e($p) ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Create New User (Hidden by default) -->
<div id="createUserModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto;">
        <div class="card-header">
            <h2 class="card-title">➕ Add New System User</h2>
            <button type="button" class="alert-close" onclick="document.getElementById('createUserModal').style.display='none';">&times;</button>
        </div>

        <form action="<?= base_url('admin/users.php') ?>" method="POST" novalidate>
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create_user">

            <div class="form-group">
                <label for="create_full_name" class="form-label">Full Name <span class="required">*</span></label>
                <input type="text" name="full_name" id="create_full_name" class="form-control" required placeholder="e.g. Sanjeewa Silva">
            </div>

            <div class="form-group">
                <label for="create_email" class="form-label">Email Address <span class="required">*</span></label>
                <input type="email" name="email" id="create_email" class="form-control" required placeholder="e.g. sanjeewa@urbaneats.lk">
            </div>

            <div class="form-group">
                <label for="create_phone" class="form-label">Phone Number <span class="required">*</span></label>
                <input type="tel" name="phone" id="create_phone" class="form-control" required placeholder="0771234567">
            </div>

            <div class="form-group">
                <label for="create_role_id" class="form-label">Account Role <span class="required">*</span></label>
                <select name="role_id" id="create_role_id" class="form-control form-select" required>
                    <option value="2">Kitchen Staff</option>
                    <option value="3">Customer</option>
                    <option value="1">Administrator</option>
                </select>
            </div>

            <div class="form-group">
                <label for="create_password" class="form-label">Temporary Password <span class="required">*</span></label>
                <input type="password" name="password" id="create_password" class="form-control" required placeholder="Minimum 8 characters">
                <span class="form-hint">Must contain at least 8 characters. The user can change this later.</span>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createUserModal').style.display='none';">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
