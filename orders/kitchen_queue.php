<?php
/**
 * UrbanEats Cloud Kitchen Platform - COMPONENT C: Kitchen Order Queue & Workflow Engine
 * Finite State Machine enforcing: PLACED -> PREPARING -> READY -> DISPATCHED -> COMPLETED
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_role(['staff', 'admin']);
$pdo = get_db();
$staffUserId = current_user_id();

// 1. Finite State Machine (FSM) Workflow Rules
$allowedTransitions = [
    'PLACED'           => ['PREPARING', 'CANCELLED'],
    'PREPARING'        => ['READY', 'CANCELLED'],
    'READY'            => ['OUT_FOR_DELIVERY', 'COLLECTED', 'CANCELLED'],
    'OUT_FOR_DELIVERY' => ['COMPLETED', 'CANCELLED'],
    'COLLECTED'        => ['COMPLETED'],
    'COMPLETED'        => [], 'CANCELLED' => []
];

// 2. Process Status Transition (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    validate_csrf_or_abort();
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $targetStatus = strtoupper(trim($_POST['target_status'] ?? ''));
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $pdo->prepare("SELECT id, order_number, status, order_type FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || !in_array($targetStatus, $allowedTransitions[$order['status']] ?? [], true)) {
        set_flash('error', "Invalid workflow transition for Order #{$order['order_number']}.");
        redirect('orders/kitchen_queue.php');
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$targetStatus, $orderId]);
        if ($targetStatus === 'COMPLETED') {
            $pdo->prepare("UPDATE payments SET payment_status = 'COMPLETED', paid_at = COALESCE(paid_at, NOW()) WHERE order_id = ? AND payment_status = 'PENDING'")->execute([$orderId]);
        }
        $histNotes = $notes ?: match ($targetStatus) {
            'PREPARING' => 'Kitchen commenced preparation', 'READY' => 'Packed and ready for dispatch',
            'OUT_FOR_DELIVERY' => 'Dispatched to delivery rider', 'COLLECTED' => 'Customer collected at counter',
            'COMPLETED' => 'Order fulfilled', 'CANCELLED' => 'Cancelled by kitchen staff', default => "Status: {$targetStatus}"
        };
        $pdo->prepare("INSERT INTO order_status_history (order_id, status, notes, changed_by_user_id, changed_at) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$orderId, $targetStatus, $histNotes, $staffUserId]);
        $pdo->commit();
        if (function_exists('log_action')) log_action('ORDER_STATUS_CHANGED', ['order_id' => $orderId, 'new_status' => $targetStatus], $staffUserId);
        set_flash('success', "Order #{$order['order_number']} is now {$targetStatus}.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', 'Status update failed.');
    }
    redirect('orders/kitchen_queue.php');
}

// 3. Queue Data Fetching & Filter Logic
$filter = trim($_GET['status'] ?? 'ALL_ACTIVE');
$where = match ($filter) {
    'PLACED'           => "WHERE o.status = 'PLACED'",
    'PREPARING'        => "WHERE o.status = 'PREPARING'",
    'READY'            => "WHERE o.status = 'READY'",
    'DISPATCHED'       => "WHERE o.status IN ('OUT_FOR_DELIVERY', 'COLLECTED')",
    default            => "WHERE o.status IN ('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED')"
};

$queueOrders = $pdo->query("SELECT o.*, u.full_name AS customer_name, p.payment_method, p.payment_status
    FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN payments p ON o.id = p.order_id $where ORDER BY o.created_at ASC")->fetchAll();

// Group items by order
$orderIds = array_column($queueOrders, 'id');
$itemsByOrder = [];
if (!empty($orderIds)) {
    $in = implode(',', $orderIds);
    $itemRows = $pdo->query("SELECT oi.*, d.name AS dish_name FROM order_items oi JOIN dishes d ON oi.dish_id = d.id WHERE oi.order_id IN ($in)")->fetchAll();
    foreach ($itemRows as $row) $itemsByOrder[$row['order_id']][] = $row;
}

// Queue summary statistics
$stats = $pdo->query("SELECT
    SUM(CASE WHEN status IN ('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED') THEN 1 ELSE 0 END) AS active_total,
    SUM(CASE WHEN status = 'PLACED' THEN 1 ELSE 0 END) AS placed_count,
    SUM(CASE WHEN status = 'PREPARING' THEN 1 ELSE 0 END) AS cooking_count,
    SUM(CASE WHEN status IN ('READY', 'OUT_FOR_DELIVERY', 'COLLECTED') THEN 1 ELSE 0 END) AS ready_count
    FROM orders")->fetch() ?: ['active_total' => 0, 'placed_count' => 0, 'cooking_count' => 0, 'ready_count' => 0];

$pageTitle = 'Kitchen Order Queue - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin-top: 1rem; margin-bottom: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-staff">Component C &bull; Workflow Engine</span>
            <h1 style="margin: 0.3rem 0; font-size: 2.2rem;">Kitchen Dispatch Queue 🍳</h1>
            <p style="color:var(--text-muted); margin:0;">Live finite state machine workflow for kitchen preparation and dispatch.</p>
        </div>
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem; cursor:pointer;">
                <input type="checkbox" id="autoRefreshToggle" checked> Auto-Refresh (30s)
            </label>
            <a href="<?= base_url('orders/kitchen_queue.php') ?>" class="btn btn-secondary btn-sm">🔄 Refresh Now</a>
        </div>
    </div>

    <!-- Metrics Stat Cards -->
    <div class="stats-grid" style="margin-bottom: 1.5rem;">
        <div class="stat-card"><div class="stat-icon primary">📦</div><div><div class="stat-value"><?= (int)$stats['active_total'] ?></div><div class="stat-label">Active Orders</div></div></div>
        <div class="stat-card"><div class="stat-icon info">📝</div><div><div class="stat-value"><?= (int)$stats['placed_count'] ?></div><div class="stat-label">New Orders</div></div></div>
        <div class="stat-card"><div class="stat-icon warning">🔥</div><div><div class="stat-value"><?= (int)$stats['cooking_count'] ?></div><div class="stat-label">In Kitchen</div></div></div>
        <div class="stat-card"><div class="stat-icon success">✓</div><div><div class="stat-value"><?= (int)$stats['ready_count'] ?></div><div class="stat-label">Ready / Dispatched</div></div></div>
    </div>

    <!-- Status Filters -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <a href="?status=ALL_ACTIVE" class="btn btn-sm <?= ($filter === 'ALL_ACTIVE') ? 'btn-primary' : 'btn-secondary' ?>">All Active (<?= (int)$stats['active_total'] ?>)</a>
        <a href="?status=PLACED" class="btn btn-sm <?= ($filter === 'PLACED') ? 'btn-primary' : 'btn-secondary' ?>">New (<?= (int)$stats['placed_count'] ?>)</a>
        <a href="?status=PREPARING" class="btn btn-sm <?= ($filter === 'PREPARING') ? 'btn-primary' : 'btn-secondary' ?>">Cooking (<?= (int)$stats['cooking_count'] ?>)</a>
        <a href="?status=READY" class="btn btn-sm <?= ($filter === 'READY') ? 'btn-primary' : 'btn-secondary' ?>">Ready (<?= (int)$stats['ready_count'] ?>)</a>
    </div>

    <?php if (empty($queueOrders)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">✨</div>
            <h2>Kitchen Queue is Clear!</h2>
            <p style="color: var(--text-secondary);">No orders pending in this stage. New customer orders will appear automatically.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.25rem;">
            <?php foreach ($queueOrders as $ord): 
                $badge = match ($ord['status']) { 'PLACED' => 'badge-primary', 'PREPARING' => 'badge-warning', 'READY' => 'badge-info', default => 'badge-success' };
                $elapsedMins = round((time() - strtotime($ord['created_at'])) / 60);
                $nextSteps = $allowedTransitions[$ord['status']] ?? [];
            ?>
                <div class="card" style="padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid var(--primary);">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                            <div>
                                <strong style="font-family: monospace; font-size: 1.15rem; color: var(--primary);"><?= e($ord['order_number']) ?></strong>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= e($ord['customer_name']) ?> &bull; <?= $ord['order_type'] === 'DELIVERY' ? '🛵 Delivery' : '🛍️ Pickup' ?></div>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge <?= $badge ?>"><?= e($ord['status']) ?></span>
                                <div style="font-size: 0.75rem; color: <?= $elapsedMins > 30 ? 'var(--error)' : 'var(--text-muted)' ?>; margin-top: 2px;">⏱️ <?= $elapsedMins ?>m ago</div>
                            </div>
                        </div>

                        <!-- Dishes List -->
                        <div style="background: var(--bg-surface); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.75rem;">
                            <?php foreach ($itemsByOrder[$ord['id']] ?? [] as $it): ?>
                                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; padding: 2px 0;">
                                    <span><strong><?= (int)$it['quantity'] ?>x</strong> <?= e($it['dish_name']) ?></span>
                                    <span style="color: var(--text-muted);"><?= format_price((float)$it['subtotal']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($ord['notes'])): ?>
                            <div style="font-size: 0.8rem; color: var(--warning); background: rgba(245, 158, 11, 0.08); padding: 0.4rem 0.6rem; border-radius: 4px; margin-bottom: 0.75rem;">
                                ⚠️ Note: <?= e($ord['notes']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Workflow Actions -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php foreach ($nextSteps as $target): 
                            $btnClass = match ($target) { 'PREPARING' => 'btn-primary', 'READY' => 'btn-primary', 'COMPLETED' => 'btn-success', 'CANCELLED' => 'btn-secondary', default => 'btn-primary' };
                            $btnLabel = match ($target) { 'PREPARING' => '🔥 Start Cooking', 'READY' => '📦 Mark Ready', 'OUT_FOR_DELIVERY' => '🛵 Dispatch Rider', 'COLLECTED' => '🛍️ Collected', 'COMPLETED' => '✓ Complete', 'CANCELLED' => 'Cancel', default => $target };
                        ?>
                            <form method="POST" action="<?= base_url('orders/kitchen_queue.php') ?>" style="display: inline;">
                                <?= csrf_input() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?= $ord['id'] ?>"><input type="hidden" name="target_status" value="<?= $target ?>">
                                <button type="submit" class="btn btn-sm <?= $btnClass ?>" <?= ($target === 'CANCELLED') ? 'onclick="return confirm(\'Cancel this order?\');"' : '' ?>><?= $btnLabel ?></button>
                            </form>
                        <?php endforeach; ?>
                        <a href="<?= base_url('orders/track.php?order_number=' . urlencode($ord['order_number'])) ?>" target="_blank" class="btn btn-sm btn-secondary" style="margin-left: auto;">📍 Track</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
let refreshTimer = null;
function setupAutoRefresh() {
    if (document.getElementById('autoRefreshToggle').checked) {
        refreshTimer = setTimeout(() => { window.location.reload(); }, 30000);
    } else if (refreshTimer) { clearTimeout(refreshTimer); }
}
document.getElementById('autoRefreshToggle').addEventListener('change', setupAutoRefresh);
setupAutoRefresh();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
