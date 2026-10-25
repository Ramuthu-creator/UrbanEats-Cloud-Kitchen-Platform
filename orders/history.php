<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Customer Order History
 * Primary Owner: Student 3
 *
 * Implements:
 * - Complete history of orders placed by the authenticated customer
 * - Filter by status (All, Active/In-Progress, Completed, Cancelled)
 * - Metrics summary (Total orders, Active orders, Total spent)
 * - Direct links to Live Order Tracking and Printable Invoices
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pdo = get_db();
$userId = current_user_id();
$currentUser = current_user();

// Status filter parameter
$statusFilter = trim($_GET['status'] ?? 'ALL');

// Build query
$whereSql = "WHERE o.user_id = :user_id";
$params = [':user_id' => $userId];

if ($statusFilter === 'ACTIVE') {
    $whereSql .= " AND o.status IN ('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED')";
} elseif ($statusFilter === 'COMPLETED') {
    $whereSql .= " AND o.status = 'COMPLETED'";
} elseif ($statusFilter === 'CANCELLED') {
    $whereSql .= " AND o.status = 'CANCELLED'";
} elseif ($statusFilter !== 'ALL' && !empty($statusFilter)) {
    $whereSql .= " AND o.status = :status";
    $params[':status'] = $statusFilter;
}

$sql = "SELECT o.*, 
               COUNT(oi.id) AS total_items, 
               SUM(oi.quantity) AS total_portions,
               p.payment_method, 
               p.payment_status,
               p.reference_number
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN payments p ON o.id = p.order_id
        $whereSql
        GROUP BY o.id
        ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Customer summary statistics
$statsStmt = $pdo->prepare(
    "SELECT 
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status IN ('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED') THEN 1 ELSE 0 END) AS active_orders,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN status = 'COMPLETED' THEN total_amount ELSE 0 END) AS total_spent
     FROM orders
     WHERE user_id = ?"
);
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch() ?: [
    'total_orders'     => 0,
    'active_orders'    => 0,
    'completed_orders' => 0,
    'total_spent'      => 0.00
];

$pageTitle = 'My Order History - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1100px; margin-top: 1rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-customer">Component C &bull; Customer Portal</span>
            <h1 style="margin-top: 0.4rem; font-size: 2.2rem;">My Order History</h1>
            <p style="margin-bottom: 0;">Track ongoing food preparations and review past dining invoices.</p>
        </div>
        <div>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary">
                🍽️ Order More Food
            </a>
        </div>
    </div>

    <!-- Metrics Stat Cards -->
    <div class="stats-grid" style="margin-bottom: 2.5rem;">
        <div class="stat-card">
            <div class="stat-icon primary">📦</div>
            <div>
                <div class="stat-value"><?= (int)$stats['total_orders'] ?></div>
                <div class="stat-label">Total Orders Placed</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">🔥</div>
            <div>
                <div class="stat-value"><?= (int)$stats['active_orders'] ?></div>
                <div class="stat-label">Active Kitchen Orders</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">✓</div>
            <div>
                <div class="stat-value"><?= (int)$stats['completed_orders'] ?></div>
                <div class="stat-label">Completed Deliveries</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">💳</div>
            <div>
                <div class="stat-value"><?= format_price((float)$stats['total_spent']) ?></div>
                <div class="stat-label">Total Spent on Meals</div>
            </div>
        </div>
    </div>

    <!-- Status Filter Pills -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center;">
        <span style="font-size: 0.85rem; color: var(--text-muted); margin-right: 0.5rem; font-weight: 600;">Filter by:</span>
        <a href="<?= base_url('orders/history.php?status=ALL') ?>" 
           class="btn btn-sm <?= ($statusFilter === 'ALL') ? 'btn-primary' : 'btn-secondary' ?>">
            All (<?= (int)$stats['total_orders'] ?>)
        </a>
        <a href="<?= base_url('orders/history.php?status=ACTIVE') ?>" 
           class="btn btn-sm <?= ($statusFilter === 'ACTIVE') ? 'btn-primary' : 'btn-secondary' ?>">
            Active Orders (<?= (int)$stats['active_orders'] ?>)
        </a>
        <a href="<?= base_url('orders/history.php?status=COMPLETED') ?>" 
           class="btn btn-sm <?= ($statusFilter === 'COMPLETED') ? 'btn-primary' : 'btn-secondary' ?>">
            Completed (<?= (int)$stats['completed_orders'] ?>)
        </a>
        <a href="<?= base_url('orders/history.php?status=CANCELLED') ?>" 
           class="btn btn-sm <?= ($statusFilter === 'CANCELLED') ? 'btn-primary' : 'btn-secondary' ?>">
            Cancelled
        </a>
    </div>

    <?php if (empty($orders)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem; border: 1px dashed var(--border-color);">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">📜</div>
            <h2>No Orders Found</h2>
            <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 1.5rem;">
                <?= ($statusFilter !== 'ALL') ? "You don't have any orders matching the '{$statusFilter}' filter." : "You haven't placed any food orders yet. Ready for authentic Sri Lankan specialties?" ?>
            </p>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary">
                Browse Menu &rarr;
            </a>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Order Details</th>
                            <th>Fulfillment</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th style="text-align: right;">Total Amount</th>
                            <th style="text-align: center; width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord): ?>
                            <?php
                            $statusBadge = match ($ord['status']) {
                                'PLACED'           => 'badge-primary',
                                'PREPARING'        => 'badge-warning',
                                'READY'            => 'badge-info',
                                'OUT_FOR_DELIVERY',
                                'COLLECTED'        => 'badge-customer',
                                'COMPLETED'        => 'badge-success',
                                'CANCELLED'        => 'badge-danger',
                                default            => 'badge-secondary'
                            };

                            $isActive = in_array($ord['status'], ['PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED'], true);
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; font-family: monospace; font-size: 1.05rem; color: var(--primary);">
                                        <?= e($ord['order_number']) ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                        <?= format_datetime($ord['created_at']) ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                                        <?= (int)$ord['total_items'] ?> item(s) &bull; <?= (int)$ord['total_portions'] ?> portion(s)
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; font-size: 0.9rem;">
                                        <?= $ord['order_type'] === 'DELIVERY' ? '🛵 Delivery' : '🛍️ Pickup' ?>
                                    </span>
                                    <?php if ($ord['order_type'] === 'DELIVERY' && !empty($ord['delivery_address'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($ord['delivery_address']) ?>">
                                            <?= e($ord['delivery_address']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; font-weight: 500;">
                                        <?= e(str_replace('_', ' ', $ord['payment_method'] ?? 'COD')) ?>
                                    </div>
                                    <span class="badge badge-<?= ($ord['payment_status'] === 'COMPLETED') ? 'success' : 'warning' ?>" style="font-size: 0.65rem; margin-top: 0.2rem;">
                                        <?= e($ord['payment_status'] ?? 'PENDING') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $statusBadge ?>" style="font-size: 0.8rem; padding: 0.25rem 0.6rem;">
                                        <?= ($isActive ? '⚡ ' : '') . e($ord['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 700; font-size: 1.05rem; color: var(--primary); white-space: nowrap;">
                                    <?= format_price((float)$ord['total_amount']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.4rem; justify-content: center; flex-wrap: wrap;">
                                        <a href="<?= base_url('orders/track.php?order_number=' . urlencode($ord['order_number'])) ?>" 
                                           class="btn btn-primary btn-sm" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                            📍 Track
                                        </a>
                                        <a href="<?= base_url('orders/confirmation.php?id=' . $ord['id']) ?>" 
                                           class="btn btn-secondary btn-sm" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                            📄 Invoice
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
