<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Security & Action Audit Trail
 *
 * Implements inspection of audit_log records, keyword search,
 * action type filtering, pagination, and user trace.
 */

require_once __DIR__ . '/../includes/header.php';
require_role('admin');

$db = get_db();

$search = trim($_GET['search'] ?? '');
$filterAction = trim($_GET['action_filter'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(a.action LIKE :s1 OR a.details LIKE :s2 OR a.ip_address LIKE :s3 OR u.full_name LIKE :s4 OR u.email LIKE :s5)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
    $params[':s4'] = "%{$search}%";
    $params[':s5'] = "%{$search}%";
}

if (!empty($filterAction)) {
    $whereClauses[] = "a.action = :action";
    $params[':action'] = $filterAction;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total records
$countSql = "SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON a.user_id = u.id {$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

// Fetch distinct actions for the filter dropdown
$actionsList = [];
try {
    $actionsList = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Fetch logs
$sql = "SELECT a.*, u.full_name, u.email, r.name AS role
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        {$whereSql}
        ORDER BY a.created_at DESC
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-admin">Security Compliance</span>
            <h1 style="margin-top: 0.4rem;">System Audit Trail</h1>
            <p style="margin-bottom: 0;">Comprehensive log of authentication, user actions, inventory, and order events</p>
        </div>
        <div>
            <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-secondary">
                &larr; Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form action="<?= base_url('admin/audit_log.php') ?>" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 220px;">
                <label for="search" class="form-label" style="font-size: 0.825rem;">Search Logs</label>
                <input 
                    type="text" 
                    name="search" 
                    id="search" 
                    class="form-control" 
                    placeholder="Search by action, details, user, or IP address..." 
                    value="<?= e($search) ?>"
                >
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label for="action_filter" class="form-label" style="font-size: 0.825rem;">Filter by Action Event</label>
                <select name="action_filter" id="action_filter" class="form-control form-select">
                    <option value="">All Action Types</option>
                    <?php foreach ($actionsList as $act): ?>
                        <option value="<?= e($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>><?= e($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Filter Logs</button>
                <a href="<?= base_url('admin/audit_log.php') ?>" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time (UTC/Local)</th>
                        <th>User Account</th>
                        <th>Action Event</th>
                        <th>Event Description & Metadata</th>
                        <th>IP & Client Info</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                                No audit log records found matching your filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
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
                                        <span style="color: var(--text-muted);">Unauthenticated / System</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <?php 
                                        $act = $log['action'];
                                        $badgeStyle = 'background: rgba(245, 158, 11, 0.15); color: #FCD34D;';
                                        if (str_contains($act, 'FAIL') || str_contains($act, 'THROTTLE') || str_contains($act, 'DENIED') || str_contains($act, 'UNAUTHORIZED')) {
                                            $badgeStyle = 'background: rgba(239, 68, 68, 0.2); color: #FCA5A5;';
                                        } elseif (str_contains($act, 'LOGIN') || str_contains($act, 'REGISTER') || str_contains($act, 'COMPLETED')) {
                                            $badgeStyle = 'background: rgba(16, 185, 129, 0.18); color: #6EE7B7;';
                                        }
                                    ?>
                                    <span style="display: inline-block; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.775rem; font-family: monospace; font-weight: 600; <?= $badgeStyle ?>">
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.875rem; color: var(--text-primary); max-width: 380px; word-break: break-word;">
                                    <?= e($log['details'] ?: 'N/A') ?>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                    <code style="color: var(--text-secondary);"><?= e($log['ip_address'] ?? 'N/A') ?></code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    Showing page <?= e($page) ?> of <?= e($totalPages) ?> (<?= e($totalRows) ?> total log records)
                </div>
                <div style="display: flex; gap: 0.4rem;">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a 
                            href="<?= base_url('admin/audit_log.php?page=' . $p . '&search=' . urlencode($search) . '&action_filter=' . urlencode($filterAction)) ?>" 
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
