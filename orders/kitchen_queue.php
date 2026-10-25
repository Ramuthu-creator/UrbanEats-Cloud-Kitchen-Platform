<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Kitchen Order Queue & Workflow Engine
 * Primary Owner: Student 3
 *
 * Implements:
 * - Live kitchen dispatch queue for kitchen staff and administrators
 * - Finite State Machine (FSM) enforcing strict workflow transitions:
 *   PLACED -> PREPARING -> READY -> OUT_FOR_DELIVERY / COLLECTED -> COMPLETED
 * - Prevents illegal state jumps (e.g., PLACED jumping directly to COMPLETED)
 * - Complete audit tracking for each transition in order_status_history and audit_log
 * - Auto-refresh toggle and elapsed-time alerts for long-pending orders
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Restricted to Kitchen Staff and Administrators
require_role(['staff', 'admin']);

$pdo = get_db();
$staffUserId = current_user_id();

// --------------------------------------------------------------------
// 1. FINITE STATE MACHINE (FSM) WORKFLOW RULES
// --------------------------------------------------------------------
$allowedTransitions = [
    'PLACED'           => ['PREPARING', 'CANCELLED'],
    'PREPARING'        => ['READY', 'CANCELLED'],
    'READY'            => ['OUT_FOR_DELIVERY', 'COLLECTED', 'CANCELLED'],
    'OUT_FOR_DELIVERY' => ['COMPLETED', 'CANCELLED'],
    'COLLECTED'        => ['COMPLETED'],
    'COMPLETED'        => [], // Terminal state
    'CANCELLED'        => []  // Terminal state
];

// --------------------------------------------------------------------
// 2. PROCESS WORKFLOW STATUS TRANSITION (POST)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    validate_csrf_or_abort();

    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $targetStatus = strtoupper(trim($_POST['target_status'] ?? ''));
    $notes = trim($_POST['notes'] ?? '');

    if (!$orderId || empty($targetStatus)) {
        set_flash('error', 'Missing order identification or target status.');
        redirect('orders/kitchen_queue.php');
    }

    // Fetch current order state
    $stmt = $pdo->prepare("SELECT id, order_number, status, order_type FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        set_flash('error', 'Order not found.');
        redirect('orders/kitchen_queue.php');
    }

    $currentStatus = $order['status'];
    $allowedNext = $allowedTransitions[$currentStatus] ?? [];

    // Enforce workflow validation rule (Direct Viva requirement)
    if (!in_array($targetStatus, $allowedNext, true)) {
        log_action('ILLEGAL_WORKFLOW_ATTEMPT', [
            'order_id'       => $orderId,
            'current_status' => $currentStatus,
            'target_status'  => $targetStatus
        ], $staffUserId);

        set_flash('error', "Invalid workflow transition: Cannot move order #{$order['order_number']} from '{$currentStatus}' to '{$targetStatus}'. Allowed next steps: " . implode(', ', $allowedNext));
        redirect('orders/kitchen_queue.php');
    }

    try {
        $pdo->beginTransaction();

        // 1. Update order status
        $updateStmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$targetStatus, $orderId]);

        // 2. If completed, mark pending cash/bank payments as completed
        if ($targetStatus === 'COMPLETED') {
            $pdo->prepare("UPDATE payments SET payment_status = 'COMPLETED', paid_at = COALESCE(paid_at, NOW()) WHERE order_id = ? AND payment_status = 'PENDING'")
                ->execute([$orderId]);
        }

        // 3. Record in order_status_history
        $histNotes = $notes ?: match ($targetStatus) {
            'PREPARING'        => 'Kitchen staff commenced preparation',
            'READY'            => 'Dishes packed and ready for dispatch',
            'OUT_FOR_DELIVERY' => 'Handed over to delivery rider',
            'COLLECTED'        => 'Customer collected food at cloud kitchen counter',
            'COMPLETED'        => 'Order successfully fulfilled and completed',
            'CANCELLED'        => 'Order cancelled by kitchen supervisor',
            default            => 'Status updated by kitchen staff'
        };

        $histStmt = $pdo->prepare(
            "INSERT INTO order_status_history (order_id, status, notes, changed_by_user_id, changed_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $histStmt->execute([$orderId, $targetStatus, $histNotes, $staffUserId]);

        $pdo->commit();

        // Centralized audit log
        log_action('ORDER_STATUS_UPDATED', "Order #{$order['order_number']} moved from {$currentStatus} to {$targetStatus}: {$histNotes}", $staffUserId);

        set_flash('success', "Order #{$order['order_number']} transitioned to {$targetStatus} successfully.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_system_error('WORKFLOW_TRANSITION_FAILED', $e->getMessage());
        set_flash('error', 'Database error updating order status: ' . $e->getMessage());
    }

    redirect('orders/kitchen_queue.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : ''));
}

// --------------------------------------------------------------------
// 3. FETCH ORDERS FOR DISPLAY BASED ON TAB / FILTER
// --------------------------------------------------------------------
$tab = trim($_GET['tab'] ?? 'ACTIVE');
$search = trim($_GET['search'] ?? '');

$whereSql = "WHERE 1=1";
$params = [];

if ($tab === 'PLACED') {
    $whereSql .= " AND o.status = 'PLACED'";
} elseif ($tab === 'PREPARING') {
    $whereSql .= " AND o.status = 'PREPARING'";
} elseif ($tab === 'READY') {
    $whereSql .= " AND o.status = 'READY'";
} elseif ($tab === 'DISPATCH') {
    $whereSql .= " AND o.status IN ('OUT_FOR_DELIVERY', 'COLLECTED')";
} elseif ($tab === 'COMPLETED') {
    $whereSql .= " AND o.status = 'COMPLETED'";
} elseif ($tab === 'CANCELLED') {
    $whereSql .= " AND o.status = 'CANCELLED'";
} else {
    // Default 'ACTIVE'
    $tab = 'ACTIVE';
    $whereSql .= " AND o.status IN ('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED')";
}

if (!empty($search)) {
    $whereSql .= " AND (o.order_number LIKE ? OR u.full_name LIKE ? OR o.contact_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT o.*, u.full_name AS customer_name, u.email AS customer_email,
               p.payment_method, p.payment_status,
               TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS elapsed_minutes
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        $whereSql
        ORDER BY 
            CASE o.status
                WHEN 'PLACED' THEN 1
                WHEN 'PREPARING' THEN 2
                WHEN 'READY' THEN 3
                WHEN 'OUT_FOR_DELIVERY' THEN 4
                WHEN 'COLLECTED' THEN 5
                ELSE 6
            END,
            o.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$queueOrders = $stmt->fetchAll();

// Pre-fetch items for each order
$orderItemsMap = [];
if (!empty($queueOrders)) {
    $orderIds = array_column($queueOrders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    
    $itemStmt = $pdo->prepare(
        "SELECT oi.*, d.name AS dish_name 
         FROM order_items oi 
         JOIN dishes d ON oi.dish_id = d.id 
         WHERE oi.order_id IN ($placeholders)
         ORDER BY oi.id ASC"
    );
    $itemStmt->execute($orderIds);
    $allItems = $itemStmt->fetchAll();

    foreach ($allItems as $it) {
        $orderItemsMap[$it['order_id']][] = $it;
    }
}

// Stage counts for badges
$countStmt = $pdo->query(
    "SELECT 
        SUM(CASE WHEN status IN ('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED') THEN 1 ELSE 0 END) AS count_active,
        SUM(CASE WHEN status = 'PLACED' THEN 1 ELSE 0 END) AS count_placed,
        SUM(CASE WHEN status = 'PREPARING' THEN 1 ELSE 0 END) AS count_prep,
        SUM(CASE WHEN status = 'READY' THEN 1 ELSE 0 END) AS count_ready,
        SUM(CASE WHEN status IN ('OUT_FOR_DELIVERY', 'COLLECTED') THEN 1 ELSE 0 END) AS count_dispatch,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS count_completed
     FROM orders"
);
$tabCounts = $countStmt->fetch() ?: [];

$pageTitle = 'Kitchen Order Queue - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin-top: 1rem;">
    <!-- Queue Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-staff">Component C &bull; Kitchen Fulfillment</span>
            <h1 style="margin-top: 0.4rem; font-size: 2.2rem;">Kitchen Order Queue</h1>
            <p style="margin-bottom: 0;">Live order processing pipeline for cloud kitchen chefs and dispatch staff.</p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="location.reload();">
                🔄 Refresh Queue
            </button>
            <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: var(--text-secondary); cursor: pointer; background: var(--bg-surface); padding: 0.4rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <input type="checkbox" id="autoRefreshToggle" checked onchange="toggleAutoRefresh(this);">
                <span>Auto-refresh (30s)</span>
            </label>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
        <form method="GET" action="<?= base_url('orders/kitchen_queue.php') ?>" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div style="flex: 1; min-width: 260px;">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search by order number, customer name, or phone..." 
                       value="<?= e($search) ?>" style="padding: 0.6rem 0.9rem;">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.25rem;">
                Filter
            </button>
            <?php if (!empty($search)): ?>
                <a href="<?= base_url('orders/kitchen_queue.php?tab=' . urlencode($tab)) ?>" class="btn btn-secondary" style="padding: 0.6rem 1rem;">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tab Navigation Pills -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 2rem; overflow-x: auto; padding-bottom: 0.5rem;">
        <a href="<?= base_url('orders/kitchen_queue.php?tab=ACTIVE') ?>" 
           class="btn btn-sm <?= ($tab === 'ACTIVE') ? 'btn-primary' : 'btn-secondary' ?>">
            ⚡ All Active (<?= (int)($tabCounts['count_active'] ?? 0) ?>)
        </a>
        <a href="<?= base_url('orders/kitchen_queue.php?tab=PLACED') ?>" 
           class="btn btn-sm <?= ($tab === 'PLACED') ? 'btn-primary' : 'btn-secondary' ?>">
            📝 Placed (<?= (int)($tabCounts['count_placed'] ?? 0) ?>)
        </a>
        <a href="<?= base_url('orders/kitchen_queue.php?tab=PREPARING') ?>" 
           class="btn btn-sm <?= ($tab === 'PREPARING') ? 'btn-primary' : 'btn-secondary' ?>">
            🍳 Preparing (<?= (int)($tabCounts['count_prep'] ?? 0) ?>)
        </a>
        <a href="<?= base_url('orders/kitchen_queue.php?tab=READY') ?>" 
           class="btn btn-sm <?= ($tab === 'READY') ? 'btn-primary' : 'btn-secondary' ?>">
            📦 Ready (<?= (int)($tabCounts['count_ready'] ?? 0) ?>)
        </a>
        <a href="<?= base_url('orders/kitchen_queue.php?tab=DISPATCH') ?>" 
           class="btn btn-sm <?= ($tab === 'DISPATCH') ? 'btn-primary' : 'btn-secondary' ?>">
            🛵 Out / Collected (<?= (int)($tabCounts['count_dispatch'] ?? 0) ?>)
        </a>
        <a href="<?= base_url('orders/kitchen_queue.php?tab=COMPLETED') ?>" 
           class="btn btn-sm <?= ($tab === 'COMPLETED') ? 'btn-primary' : 'btn-secondary' ?>">
            ✓ Completed (<?= (int)($tabCounts['count_completed'] ?? 0) ?>)
        </a>
        <a href="<?= base_url('orders/kitchen_queue.php?tab=CANCELLED') ?>" 
           class="btn btn-sm <?= ($tab === 'CANCELLED') ? 'btn-primary' : 'btn-secondary' ?>">
            ✕ Cancelled
        </a>
    </div>

    <!-- Queue Order Cards Grid -->
    <?php if (empty($queueOrders)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem; border: 1px dashed var(--border-color);">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🍳</div>
            <h2>Kitchen Queue is Clear</h2>
            <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto;">
                There are currently no orders in the <strong><?= e($tab) ?></strong> state. New orders will appear here automatically.
            </p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
            <?php foreach ($queueOrders as $qOrder): ?>
                <?php
                $orderId = (int)$qOrder['id'];
                $elapsedMins = (int)$qOrder['elapsed_minutes'];
                $isDelivery = ($qOrder['order_type'] === 'DELIVERY');
                $currStatus = $qOrder['status'];
                $items = $orderItemsMap[$orderId] ?? [];

                // Status badge
                $badgeClass = match ($currStatus) {
                    'PLACED'           => 'badge-primary',
                    'PREPARING'        => 'badge-warning',
                    'READY'            => 'badge-info',
                    'OUT_FOR_DELIVERY',
                    'COLLECTED'        => 'badge-customer',
                    'COMPLETED'        => 'badge-success',
                    'CANCELLED'        => 'badge-danger',
                    default            => 'badge-secondary'
                };

                // Elapsed time styling
                $elapsedClass = ($elapsedMins > 35 && in_array($currStatus, ['PLACED', 'PREPARING'])) ? 'color: var(--error); font-weight: 700;' : 'color: var(--text-muted);';
                ?>
                <div class="card order-queue-card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid <?= $currStatus === 'PLACED' ? 'var(--primary)' : ($currStatus === 'PREPARING' ? 'var(--warning)' : ($currStatus === 'READY' ? 'var(--info)' : 'var(--success)')) ?>;">
                    
                    <!-- Card Header -->
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                            <div>
                                <a href="<?= base_url('orders/confirmation.php?id=' . $orderId) ?>" style="font-weight: 800; font-family: monospace; font-size: 1.25rem; color: var(--primary);">
                                    <?= e($qOrder['order_number']) ?>
                                </a>
                                <div style="font-size: 0.8rem; <?= $elapsedClass ?> margin-top: 0.15rem;">
                                    ⏱️ <?= $elapsedMins ?> min(s) ago &bull; <?= date('h:i A', strtotime($qOrder['created_at'])) ?>
                                </div>
                            </div>

                            <span class="badge <?= $badgeClass ?>" style="font-size: 0.78rem;">
                                <?= e($currStatus) ?>
                            </span>
                        </div>

                        <!-- Customer & Fulfillment Metadata -->
                        <div style="background: var(--bg-surface); padding: 0.85rem; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.85rem; line-height: 1.5; border: 1px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <strong style="color: var(--text-primary);"><?= e($qOrder['customer_name']) ?></strong>
                                <span class="badge <?= $isDelivery ? 'badge-primary' : 'badge-info' ?>" style="font-size: 0.7rem;">
                                    <?= $isDelivery ? '🛵 Delivery' : '🛍️ Counter Pickup' ?>
                                </span>
                            </div>
                            <div>
                                📞 <a href="tel:<?= e($qOrder['contact_phone']) ?>" style="color: var(--text-secondary); font-family: monospace;"><?= e($qOrder['contact_phone']) ?></a>
                            </div>
                            <?php if ($isDelivery && !empty($qOrder['delivery_address'])): ?>
                                <div style="margin-top: 0.35rem; font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">
                                    📍 <?= e($qOrder['delivery_address']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($qOrder['notes'])): ?>
                            <div style="background: rgba(245, 158, 11, 0.1); border-left: 3px solid var(--primary); padding: 0.6rem 0.75rem; border-radius: 4px; font-size: 0.8rem; color: var(--primary); margin-bottom: 1rem;">
                                ⚠️ <strong>Customer Note:</strong> <?= e($qOrder['notes']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Items List -->
                        <div style="margin-bottom: 1.25rem;">
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; font-weight: 600;">
                                Kitchen Order Items (<?= count($items) ?>):
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                                <?php foreach ($items as $item): ?>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; border-bottom: 1px dashed rgba(255,255,255,0.05); padding-bottom: 0.3rem;">
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <span style="color: var(--primary); margin-right: 0.3rem;"><?= (int)$item['quantity'] ?>&times;</span>
                                            <?= e($item['dish_name']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Card Actions & State Transition Engine -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= e(str_replace('_', ' ', $qOrder['payment_method'] ?? 'COD')) ?> &bull; <strong><?= format_price((float)$qOrder['total_amount']) ?></strong>
                            </span>
                            <span class="badge badge-<?= ($qOrder['payment_status'] === 'COMPLETED') ? 'success' : 'warning' ?>" style="font-size: 0.65rem;">
                                <?= e($qOrder['payment_status'] ?? 'PENDING') ?>
                            </span>
                        </div>

                        <!-- Action Buttons based on FSM -->
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php if ($currStatus === 'PLACED'): ?>
                                <!-- PLACED -> PREPARING -->
                                <form method="POST" action="<?= base_url('orders/kitchen_queue.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '')) ?>" style="flex: 1;">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="target_status" value="PREPARING">
                                    <button type="submit" class="btn btn-warning btn-sm" style="width: 100%;">
                                        🍳 Start Cooking
                                    </button>
                                </form>

                                <!-- PLACED -> CANCELLED -->
                                <form method="POST" action="<?= base_url('orders/kitchen_queue.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '')) ?>" onsubmit="return confirm('Cancel Order #<?= e(addslashes($qOrder['order_number'])) ?>?');">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="target_status" value="CANCELLED">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Cancel Order">
                                        ✕
                                    </button>
                                </form>

                            <?php elseif ($currStatus === 'PREPARING'): ?>
                                <!-- PREPARING -> READY -->
                                <form method="POST" action="<?= base_url('orders/kitchen_queue.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '')) ?>" style="flex: 1;">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="target_status" value="READY">
                                    <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
                                        📦 Food Ready & Packed
                                    </button>
                                </form>

                            <?php elseif ($currStatus === 'READY'): ?>
                                <?php if ($isDelivery): ?>
                                    <!-- READY -> OUT_FOR_DELIVERY -->
                                    <form method="POST" action="<?= base_url('orders/kitchen_queue.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '')) ?>" style="flex: 1;">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                        <input type="hidden" name="target_status" value="OUT_FOR_DELIVERY">
                                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
                                            🛵 Dispatch Courier
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- READY -> COLLECTED -->
                                    <form method="POST" action="<?= base_url('orders/kitchen_queue.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '')) ?>" style="flex: 1;">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                        <input type="hidden" name="target_status" value="COLLECTED">
                                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">
                                            🛍️ Customer Picked Up
                                        </button>
                                    </form>
                                <?php endif; ?>

                            <?php elseif (in_array($currStatus, ['OUT_FOR_DELIVERY', 'COLLECTED'])): ?>
                                <!-- OUT_FOR_DELIVERY / COLLECTED -> COMPLETED -->
                                <form method="POST" action="<?= base_url('orders/kitchen_queue.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : '')) ?>" style="flex: 1;">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="target_status" value="COMPLETED">
                                    <button type="submit" class="btn btn-success btn-sm" style="width: 100%;">
                                        ✓ Mark Delivered & Completed
                                    </button>
                                </form>

                            <?php else: ?>
                                <!-- Terminal States (COMPLETED or CANCELLED) -->
                                <div style="display: flex; gap: 0.5rem; width: 100%;">
                                    <a href="<?= base_url('orders/confirmation.php?id=' . $orderId) ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center;">
                                        📄 View Invoice
                                    </a>
                                    <a href="<?= base_url('orders/track.php?id=' . $orderId) ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center;">
                                        📍 Track
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
let autoRefreshTimer = null;

function setupAutoRefresh() {
    const isChecked = document.getElementById('autoRefreshToggle').checked;
    if (isChecked) {
        autoRefreshTimer = setTimeout(function() {
            window.location.reload();
        }, 30000); // 30 seconds
    } else if (autoRefreshTimer) {
        clearTimeout(autoRefreshTimer);
    }
}

function toggleAutoRefresh(cb) {
    if (!cb.checked && autoRefreshTimer) {
        clearTimeout(autoRefreshTimer);
    } else {
        setupAutoRefresh();
    }
}

document.addEventListener('DOMContentLoaded', setupAutoRefresh);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
