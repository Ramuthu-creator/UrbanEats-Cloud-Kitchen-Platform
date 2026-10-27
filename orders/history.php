<?php
/**
 * UrbanEats Cloud Kitchen Platform - COMPONENT C: Customer Order History
 * Complete history of orders, status filtering, customer metrics, tracking & invoices.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$pdo = get_db();
$userId = current_user_id();

$statusFilter = trim($_GET['status'] ?? 'ALL');
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

$stmt = $pdo->prepare("SELECT o.*, COUNT(oi.id) AS total_items, COALESCE(SUM(oi.quantity), 0) AS total_portions,
    MAX(p.payment_method) AS payment_method, MAX(p.payment_status) AS payment_status, MAX(p.reference_number) AS reference_number
    FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id LEFT JOIN payments p ON o.id = p.order_id
    $whereSql GROUP BY o.id ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Customer summary statistics
$statsStmt = $pdo->prepare("SELECT COUNT(*) AS total_orders,
    SUM(CASE WHEN status IN ('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED') THEN 1 ELSE 0 END) AS active_orders,
    SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_orders,
    SUM(CASE WHEN status = 'COMPLETED' THEN total_amount ELSE 0 END) AS total_spent
    FROM orders WHERE user_id = ?");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch() ?: ['total_orders' => 0, 'active_orders' => 0, 'completed_orders' => 0, 'total_spent' => 0.00];

$pageTitle = 'My Order History - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1100px; margin-top: 1rem; margin-bottom: 3rem;">
    <!-- Page Header -->
    <div
        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-customer">Customer Portal &bull; Dining History</span>
            <h1 style="margin: 0.3rem 0; font-size: 2.2rem;">My Order History</h1>
            <p style="margin: 0; color: var(--text-muted);">Track ongoing food preparations and review past dining
                invoices.</p>
        </div>
        <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary">🍽️ Order More Food</a>
    </div>

    <!-- Metrics Stat Cards -->
    <div class="stats-grid" style="margin-bottom: 1.5rem;">
        <div class="stat-card">
            <div class="stat-icon primary">📦</div>
            <div>
                <div class="stat-value"><?= (int) $stats['total_orders'] ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">🔥</div>
            <div>
                <div class="stat-value"><?= (int) $stats['active_orders'] ?></div>
                <div class="stat-label">Active Orders</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">✓</div>
            <div>
                <div class="stat-value"><?= (int) $stats['completed_orders'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">💳</div>
            <div>
                <div class="stat-value"><?= format_price((float) $stats['total_spent']) ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>
    </div>

    <!-- Status Filter Pills -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center;">
        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Filter:</span>
        <a href="?status=ALL" class="btn btn-sm <?= ($statusFilter === 'ALL') ? 'btn-primary' : 'btn-secondary' ?>">All
            (<?= (int) $stats['total_orders'] ?>)</a>
        <a href="?status=ACTIVE"
            class="btn btn-sm <?= ($statusFilter === 'ACTIVE') ? 'btn-primary' : 'btn-secondary' ?>">Active
            (<?= (int) $stats['active_orders'] ?>)</a>
        <a href="?status=COMPLETED"
            class="btn btn-sm <?= ($statusFilter === 'COMPLETED') ? 'btn-primary' : 'btn-secondary' ?>">Completed
            (<?= (int) $stats['completed_orders'] ?>)</a>
        <a href="?status=CANCELLED"
            class="btn btn-sm <?= ($statusFilter === 'CANCELLED') ? 'btn-primary' : 'btn-secondary' ?>">Cancelled</a>
    </div>

    <?php if (empty($orders)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">📜</div>
            <h2>No Orders Found</h2>
            <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 1.5rem;">
                <?= ($statusFilter !== 'ALL') ? "No orders matching '{$statusFilter}'." : "You haven't placed any food orders yet." ?>
            </p>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary">Browse Menu &rarr;</a>
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
                        <?php foreach ($orders as $ord):
                            $statusBadge = match ($ord['status']) {
                                'PLACED' => 'badge-primary', 'PREPARING' => 'badge-warning', 'READY' => 'badge-info',
                                'COMPLETED' => 'badge-success', 'CANCELLED' => 'badge-danger', default => 'badge-secondary'
                            };
                            $isActive = in_array($ord['status'], ['PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED'], true);
                            ?>
                            <tr>
                                <td>
                                    <div
                                        style="font-weight: 700; font-family: monospace; font-size: 1.05rem; color: var(--primary);">
                                        <?= e($ord['order_number']) ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                        <?= format_datetime($ord['created_at']) ?> &bull; <?= (int) $ord['total_items'] ?>
                                        item(s)
                                    </div>
                                </td>
                                <td>
                                    <span
                                        style="font-weight: 600; font-size: 0.9rem;"><?= $ord['order_type'] === 'DELIVERY' ? '🛵 Delivery' : '🛍️ Pickup' ?></span>
                                    <?php if ($ord['order_type'] === 'DELIVERY' && !empty($ord['delivery_address'])): ?>
                                        <div
                                            style="font-size: 0.75rem; color: var(--text-muted); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= e($ord['delivery_address']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; font-weight: 500;">
                                        <?= e(str_replace('_', ' ', $ord['payment_method'] ?? 'COD')) ?></div>
                                    <span
                                        class="badge badge-<?= ($ord['payment_status'] === 'COMPLETED') ? 'success' : 'warning' ?>"
                                        style="font-size: 0.65rem;">
                                        <?= e($ord['payment_status'] ?? 'PENDING') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $statusBadge ?>" style="font-size: 0.8rem; padding: 0.25rem 0.6rem;">
                                        <?= ($isActive ? '⚡ ' : '') . e($ord['status']) ?>
                                    </span>
                                </td>
                                <td
                                    style="text-align: right; font-weight: 700; font-size: 1.05rem; color: var(--primary); white-space: nowrap;">
                                    <?= format_price((float) $ord['total_amount']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.4rem; justify-content: center; flex-wrap: wrap;">
                                        <a href="<?= base_url('orders/track.php?order_number=' . urlencode($ord['order_number'])) ?>"
                                            class="btn btn-primary btn-sm" style="font-size: 0.8rem;">📍 Track</a>
                                        <a href="<?= base_url('orders/confirmation.php?id=' . $ord['id']) ?>"
                                            class="btn btn-secondary btn-sm" style="font-size: 0.8rem;">📄 Invoice</a>
                                        <?php if ($ord['status'] === 'COMPLETED'): ?>
                                            <a href="<?= base_url('orders/review.php?order_id=' . (int) $ord['id']) ?>"
                                                class="btn btn-sm"
                                                style="font-size: 0.8rem; background: var(--primary); color: #070B0A; font-weight: 700;">⭐
                                                Rate Food</a>
                                        <?php endif; ?>
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