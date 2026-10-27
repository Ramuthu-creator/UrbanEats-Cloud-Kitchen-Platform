<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Administrator Dashboard
 *
 * Implements high-level user metrics, quick navigation, recent security audit
 * trail view, and team integration health monitoring.
 */

require_once __DIR__ . '/../includes/header.php';
require_role('admin');

$db = get_db();

// Fetch System Metrics
$totalUsers = 0;
$activeUsers = 0;
$staffCount = 0;
$customerCount = 0;
$recentAudits = [];

try {
    // Total Users
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Active Users
    $activeUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

    // Staff Count
    $staffCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role_id = 2 AND is_active = 1")->fetchColumn();

    // Customer Count
    $customerCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role_id = 3")->fetchColumn();

    // Recent 6 Audit Logs
    $auditStmt = $db->query(
        "SELECT a.*, u.full_name, r.name AS role
         FROM audit_log a
         LEFT JOIN users u ON a.user_id = u.id
         LEFT JOIN roles r ON u.role_id = r.id
         ORDER BY a.created_at DESC
         LIMIT 6"
    );
    $recentAudits = $auditStmt->fetchAll();
} catch (Exception $e) {
    log_system_error('ADMIN_DASHBOARD_FETCH_FAIL', $e->getMessage());
}
?>

<div class="container">
    <!-- Dashboard Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-admin">Administrator Control Panel</span>
            <h1 style="margin-top: 0.4rem;">System Overview</h1>
            <p style="margin-bottom: 0;">Welcome, <strong><?= e($_SESSION['user_name']) ?></strong>. Monitor user activities and platform operations.</p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="<?= base_url('admin/users.php') ?>" class="btn btn-primary">
                👥 Manage Users
            </a>
            <a href="<?= base_url('admin/audit_log.php') ?>" class="btn btn-secondary">
                📋 View Audit Logs
            </a>
        </div>
    </div>

    <!-- Metric Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">👥</div>
            <div>
                <div class="stat-value"><?= e($totalUsers) ?></div>
                <div class="stat-label">Total Registered Users</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">🟢</div>
            <div>
                <div class="stat-value"><?= e($activeUsers) ?></div>
                <div class="stat-label">Active Accounts</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">👨‍🍳</div>
            <div>
                <div class="stat-value"><?= e($staffCount) ?></div>
                <div class="stat-label">Kitchen Staff Members</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">🛍️</div>
            <div>
                <div class="stat-value"><?= e($customerCount) ?></div>
                <div class="stat-label">Registered Customers</div>
            </div>
        </div>
    </div>

    <!-- Quick Action Launchpad -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Quick Administration Actions</h2>
                <p class="card-subtitle">Direct shortcuts to platform governance and kitchen modules</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
            <a href="<?= base_url('admin/users.php') ?>" class="card" style="background: var(--bg-card); padding: 1.25rem; display: block; border-color: rgba(255,255,255,0.05);">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">👤 User Management</div>
                <p style="font-size: 0.85rem; margin-bottom: 0.5rem;">Search, filter by role, toggle active/inactive status, and change user roles.</p>
                <span style="font-size: 0.825rem; color: var(--primary); font-weight: 600;">Manage accounts &rarr;</span>
            </a>

            <a href="<?= base_url('admin/audit_log.php') ?>" class="card" style="background: var(--bg-card); padding: 1.25rem; display: block; border-color: rgba(255,255,255,0.05);">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🛡️ Security Audit Log</div>
                <p style="font-size: 0.85rem; margin-bottom: 0.5rem;">Trace logins, registration, password resets, stock changes, and system events.</p>
                <span style="font-size: 0.825rem; color: var(--primary); font-weight: 600;">Inspect logs &rarr;</span>
            </a>

            <a href="<?= base_url('admin/reports.php') ?>" class="card" style="background: var(--bg-card); padding: 1.25rem; display: block; border-color: rgba(255,255,255,0.05);">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">📊 Sales & Revenue Reports</div>
                <p style="font-size: 0.85rem; margin-bottom: 0.5rem;">Financial intelligence: Daily/monthly sales, order stage metrics, and top-selling dishes.</p>
                <span style="font-size: 0.825rem; color: var(--primary); font-weight: 600;">View sales reports &rarr;</span>
            </a>

            <a href="<?= base_url('menu/manage.php') ?>" class="card" style="background: var(--bg-card); padding: 1.25rem; display: block; border-color: rgba(255,255,255,0.05);">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🍲 Dishes & Catalogue</div>
                <p style="font-size: 0.85rem; margin-bottom: 0.5rem;">Menu operations: Browse dishes, adjust pricing, images, categories, and stock.</p>
                <span style="font-size: 0.825rem; color: var(--primary); font-weight: 600;">Review catalogue &rarr;</span>
            </a>
        </div>
    </div>

    <!-- Recent Audit Activity -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent Security & System Events</h2>
                <p class="card-subtitle">Latest actions recorded by the centralized audit log</p>
            </div>
            <div>
                <a href="<?= base_url('admin/audit_log.php') ?>" class="btn btn-secondary btn-sm">
                    View All Logs &rarr;
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Client IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentAudits)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No audit records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentAudits as $log): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.825rem; color: var(--text-secondary);">
                                    <?= format_datetime($log['created_at']) ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <?php if ($log['full_name']): ?>
                                        <strong><?= e($log['full_name']) ?></strong>
                                        <br>
                                        <span class="badge badge-<?= e($log['role'] ?? 'customer') ?>" style="font-size: 0.65rem;">
                                            <?= e(strtoupper($log['role'] ?? 'USER')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">Guest / System</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <code style="background: rgba(255,255,255,0.08); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; color: var(--primary);">
                                        <?= e($log['action']) ?>
                                    </code>
                                </td>
                                <td style="font-size: 0.875rem; color: var(--text-secondary);">
                                    <?= e($log['details'] ?: 'No additional details') ?>
                                </td>
                                <td style="white-space: nowrap; font-size: 0.825rem; font-family: monospace; color: var(--text-muted);">
                                    <?= e($log['ip_address'] ?? 'N/A') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
