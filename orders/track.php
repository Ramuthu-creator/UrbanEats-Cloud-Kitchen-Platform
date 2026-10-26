<?php
/**
 * UrbanEats Cloud Kitchen Platform - COMPONENT C: Live Order Tracking & Visual Status Timeline
 * Live order progress tracker with animated stages & complete audit history trail.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = get_db();
$orderNumber = strtoupper(trim($_GET['order_number'] ?? ''));
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$currentUserId = current_user_id();
$currentRole = current_user_role();

$order = null;
$statusHistory = [];
$orderItems = [];
$searchAttempted = !empty($orderNumber) || !empty($orderId);

if ($searchAttempted) {
    $where = $orderId ? "o.id = ?" : "UPPER(o.order_number) = ?";
    $param = $orderId ?: $orderNumber;
    $stmt = $pdo->prepare("SELECT o.*, u.full_name AS customer_name, u.email AS customer_email, p.payment_method, p.payment_status
        FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN payments p ON o.id = p.order_id WHERE $where LIMIT 1");
    $stmt->execute([$param]);
    $order = $stmt->fetch();

    if ($order) {
        if (is_logged_in() && $currentRole === 'customer' && (int)$order['user_id'] !== $currentUserId) {
            set_flash('error', 'You are not authorized to track this order.');
            redirect('orders/history.php');
        }
        $hStmt = $pdo->prepare("SELECT osh.*, u.full_name AS staff_name FROM order_status_history osh LEFT JOIN users u ON osh.changed_by_user_id = u.id WHERE osh.order_id = ? ORDER BY osh.changed_at ASC");
        $hStmt->execute([$order['id']]);
        $statusHistory = $hStmt->fetchAll();

        $iStmt = $pdo->prepare("SELECT oi.*, d.name AS dish_name FROM order_items oi JOIN dishes d ON oi.dish_id = d.id WHERE oi.order_id = ?");
        $iStmt->execute([$order['id']]);
        $orderItems = $iStmt->fetchAll();
    }
}

$isDelivery = ($order['order_type'] ?? 'DELIVERY') === 'DELIVERY';
$stages = [
    'PLACED'           => ['step' => 1, 'title' => 'Order Placed', 'desc' => 'Order received by kitchen', 'icon' => '📝'],
    'PREPARING'        => ['step' => 2, 'title' => 'In Preparation', 'desc' => 'Chefs are cooking fresh', 'icon' => '🍳'],
    'READY'            => ['step' => 3, 'title' => 'Packed & Ready', 'desc' => 'Dishes packed & ready', 'icon' => '📦'],
    $isDelivery ? 'OUT_FOR_DELIVERY' : 'COLLECTED' => [
        'step' => 4, 'title' => $isDelivery ? 'Out for Delivery' : 'Ready at Counter',
        'desc' => $isDelivery ? 'Rider dispatched to address' : 'Awaiting counter pickup', 'icon' => $isDelivery ? '🛵' : '🛍️'
    ],
    'COMPLETED'        => ['step' => 5, 'title' => 'Completed', 'desc' => 'Delivered. Enjoy your meal!', 'icon' => '😋']
];

$stepMap = ['PLACED' => 1, 'PREPARING' => 2, 'READY' => 3, 'OUT_FOR_DELIVERY' => 4, 'COLLECTED' => 4, 'COMPLETED' => 5, 'CANCELLED' => -1];
$currentStep = $stepMap[$order['status'] ?? ''] ?? 0;
$isCancelled = (($order['status'] ?? '') === 'CANCELLED');

$pageTitle = ($order ? 'Track ' . $order['order_number'] : 'Track Your Order') . ' - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 950px; margin-top: 1rem; margin-bottom: 3rem;">
    <!-- Search Box -->
    <div class="card" style="margin-bottom: 2rem; padding: 1.25rem;">
        <form method="GET" action="<?= base_url('orders/track.php') ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <input type="text" name="order_number" class="form-control" placeholder="Enter Order Number (e.g. ORD-202610-001)"
                   value="<?= e($order['order_number'] ?? $orderNumber) ?>" style="flex:1; text-transform:uppercase; font-family:monospace; font-weight:700;" required>
            <button type="submit" class="btn btn-primary">🔍 Track Progress</button>
            <?php if (is_logged_in()): ?><a href="<?= base_url('orders/history.php') ?>" class="btn btn-secondary">My Orders</a><?php endif; ?>
        </form>
    </div>

    <?php if ($searchAttempted && !$order): ?>
        <div class="card" style="text-align: center; padding: 3rem 2rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
            <h2>Order Not Found</h2>
            <p style="color: var(--text-secondary);">We could not find an order matching "<strong><?= e($orderNumber) ?></strong>".</p>
            <a href="<?= base_url('orders/history.php') ?>" class="btn btn-secondary">View Past Orders &rarr;</a>
        </div>
    <?php elseif ($order): ?>
        <!-- Order Header Badge Card -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem; border-left: 4px solid var(--primary); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Tracking Order</span>
                <h2 style="font-size: 1.6rem; color: var(--primary); font-family: monospace; margin: 0.2rem 0;"><?= e($order['order_number']) ?></h2>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">Placed on <?= format_datetime($order['created_at']) ?> &bull; <strong><?= $order['order_type'] === 'DELIVERY' ? '🛵 Delivery' : '🛍️ Pickup' ?></strong></div>
            </div>
            <div style="text-align: right;">
                <span class="badge <?= $isCancelled ? 'badge-danger' : 'badge-primary' ?>" style="font-size: 0.95rem; padding: 0.35rem 0.8rem;"><?= e($order['status']) ?></span>
                <div style="margin-top: 0.4rem;"><a href="<?= base_url('orders/confirmation.php?id=' . $order['id']) ?>" class="btn btn-secondary btn-sm">📄 View Invoice</a></div>
            </div>
        </div>

        <?php if ($isCancelled): ?>
            <div class="alert alert-error" style="margin-bottom: 2rem; padding: 1rem;"><strong>This Order Was Cancelled:</strong> Please contact support if you require assistance.</div>
        <?php else: ?>
            <!-- Stepper Progress Bar -->
            <div class="card" style="margin-bottom: 1.5rem; padding: 2rem 1.5rem;">
                <h3 style="font-size: 1.15rem; margin-bottom: 1.75rem;">⏱️ Order Progress Timeline</h3>
                <div class="timeline-stepper">
                    <?php $sIdx = 1; foreach ($stages as $sk => $sInfo): 
                        $isDone = ($currentStep > $sIdx); $isCurr = ($currentStep === $sIdx);
                    ?>
                        <div class="step-item <?= $isDone ? 'step-completed' : ($isCurr ? 'step-current' : 'step-pending') ?>">
                            <div class="step-circle"><?= $isDone ? '✓' : $sInfo['icon'] ?></div>
                            <div class="step-title"><?= e($sInfo['title']) ?></div>
                            <div class="step-desc"><?= e($sInfo['desc']) ?></div>
                        </div>
                    <?php $sIdx++; endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Two Column Grid: Status Trail + Order Items -->
        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start;">
            <!-- Left: Audit Trail -->
            <div class="card">
                <h3 style="font-size: 1.15rem; margin-bottom: 1rem;">📋 Status Audit Trail</h3>
                <?php if (empty($statusHistory)): ?>
                    <p style="color: var(--text-muted);">No status updates recorded yet.</p>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($statusHistory as $h): ?>
                            <div class="history-item">
                                <div class="history-marker"></div>
                                <div class="history-content">
                                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                                        <span class="badge badge-primary" style="font-size:0.75rem;"><?= e($h['status']) ?></span>
                                        <span style="font-size:0.75rem; color:var(--text-muted);"><?= format_datetime($h['changed_at']) ?></span>
                                    </div>
                                    <div style="margin-top:0.3rem; font-size:0.85rem; color:var(--text-secondary);"><?= e($h['notes'] ?: 'Status updated') ?></div>
                                    <?php if (!empty($h['staff_name'])): ?><div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;">By: <?= e($h['staff_name']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Dishes & Details -->
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="card">
                    <h3 style="font-size: 1.1rem; margin-bottom: 0.75rem;">Dishes in Order</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($orderItems as $it): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 4px;">
                                <div><strong><?= e($it['dish_name']) ?></strong> <span style="color:var(--text-muted); font-size:0.75rem;">&times;<?= (int)$it['quantity'] ?></span></div>
                                <div style="font-weight: 600; color: var(--primary);"><?= format_price((float)$it['subtotal']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:0.75rem; border-top:1px solid var(--border-color); padding-top:0.5rem; display:flex; justify-content:space-between; font-weight:700;">
                        <span>Total Paid:</span><span style="color:var(--primary);"><?= format_price((float)$order['total_amount']) ?></span>
                    </div>
                </div>

                <div class="card" style="font-size: 0.85rem; line-height: 1.5;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">Delivery Info</h3>
                    <div><strong>Customer:</strong> <?= e($order['customer_name']) ?></div>
                    <div><strong>Contact:</strong> <?= e($order['contact_phone']) ?></div>
                    <?php if ($order['order_type'] === 'DELIVERY'): ?>
                        <div style="margin-top:0.25rem;"><strong>Address:</strong> <?= nl2br(e($order['delivery_address'])) ?></div>
                    <?php else: ?>
                        <div style="margin-top:0.25rem; color:var(--info);">📍 <strong>Counter Pickup:</strong> No. 120, Galle Road, Colombo 03</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
