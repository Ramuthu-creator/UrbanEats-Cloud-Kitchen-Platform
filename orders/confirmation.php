<?php
/**
 * UrbanEats Cloud Kitchen Platform - COMPONENT C: Printable Order Confirmation & Invoice
 * Itemized billing invoice, customer details, payment confirmation, and printable receipt.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$pdo = get_db();
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$orderNumber = trim($_GET['order_number'] ?? '');
$currentUserId = current_user_id();
$currentRole = current_user_role();

if ($orderId) {
    $stmt = $pdo->prepare("SELECT o.*, u.full_name AS customer_name, u.email AS customer_email, c.code AS coupon_code,
        p.payment_method, p.payment_status, p.reference_number, p.paid_at
        FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN coupons c ON o.coupon_id = c.id
        LEFT JOIN payments p ON o.id = p.order_id WHERE o.id = ? LIMIT 1");
    $stmt->execute([$orderId]);
} elseif ($orderNumber) {
    $stmt = $pdo->prepare("SELECT o.*, u.full_name AS customer_name, u.email AS customer_email, c.code AS coupon_code,
        p.payment_method, p.payment_status, p.reference_number, p.paid_at
        FROM orders o JOIN users u ON o.user_id = u.id LEFT JOIN coupons c ON o.coupon_id = c.id
        LEFT JOIN payments p ON o.id = p.order_id WHERE o.order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
} else {
    set_flash('error', 'No order specified.');
    redirect('orders/history.php');
}

$order = $stmt->fetch();
if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('orders/history.php');
}

// Access Control: Customer can only view their own invoice
if ($currentRole === 'customer' && (int)$order['user_id'] !== $currentUserId) {
    set_flash('error', 'Unauthorized access.');
    redirect('orders/history.php');
}

// Fetch Items
$itemStmt = $pdo->prepare("SELECT oi.*, d.name AS dish_name, c.name AS category_name
    FROM order_items oi JOIN dishes d ON oi.dish_id = d.id LEFT JOIN categories c ON d.category_id = c.id
    WHERE oi.order_id = ? ORDER BY oi.id ASC");
$itemStmt->execute([$order['id']]);
$items = $itemStmt->fetchAll();

$statusBadge = match ($order['status']) {
    'PLACED' => 'badge-primary', 'PREPARING' => 'badge-warning', 'READY' => 'badge-info',
    'COMPLETED' => 'badge-success', 'CANCELLED' => 'badge-danger', default => 'badge-secondary'
};

$pageTitle = 'Invoice ' . $order['order_number'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 900px; margin-top: 1rem; margin-bottom: 3rem;">
    <!-- Action Bar -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <a href="<?= base_url('orders/history.php') ?>" class="btn btn-secondary btn-sm">&larr; Back to Order History</a>
        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= base_url('orders/track.php?order_number=' . urlencode($order['order_number'])) ?>" class="btn btn-primary btn-sm">📍 Track Order Live &rarr;</a>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print();">🖨️ Print Invoice</button>
        </div>
    </div>

    <!-- Printable Invoice Card -->
    <div class="card invoice-card" style="padding: 2.5rem; border: 1px solid var(--border-color);">
        <!-- Invoice Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.4rem;">
                    <span style="font-size: 2rem;">🍽️</span>
                    <div>
                        <h1 style="font-size: 1.75rem; margin: 0; line-height: 1;">Urban<span style="color: var(--primary);">Eats</span></h1>
                        <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Cloud Kitchens Colombo</span>
                    </div>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.5;">
                    No. 120, Galle Road, Colombo 03, Sri Lanka<br>Hotline: +94 11 234 5678 &bull; orders@urbaneats.lk
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Official Tax Invoice</div>
                <h2 style="font-size: 1.5rem; color: var(--primary); font-family: monospace; margin: 0.2rem 0;"><?= e($order['order_number']) ?></h2>
                <div style="font-size: 0.8rem; color: var(--text-secondary);">Placed: <?= format_datetime($order['created_at']) ?></div>
                <div style="margin-top: 0.4rem;"><span class="badge <?= $statusBadge ?>"><?= e($order['status']) ?></span></div>
            </div>
        </div>

        <!-- Customer & Fulfillment Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; background: var(--bg-surface); padding: 1.25rem; border-radius: var(--radius-md);">
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Customer Information</div>
                <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0.2rem 0;"><?= e($order['customer_name']) ?></div>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">📧 <?= e($order['customer_email']) ?><br>📞 <?= e($order['contact_phone']) ?></div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Fulfillment &amp; Payment</div>
                <div style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin: 0.2rem 0;"><?= $order['order_type'] === 'DELIVERY' ? '🛵 Home Delivery' : '🛍️ Self Pickup' ?></div>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    <?php if ($order['order_type'] === 'DELIVERY'): ?><strong>Address:</strong> <?= nl2br(e($order['delivery_address'])) ?><br><?php endif; ?>
                    <strong>Payment:</strong> <?= e(str_replace('_', ' ', $order['payment_method'] ?? 'COD')) ?>
                    <span class="badge badge-<?= ($order['payment_status'] === 'COMPLETED') ? 'success' : 'warning' ?>" style="font-size: 0.65rem;"><?= e($order['payment_status'] ?? 'PENDING') ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
            <div style="background: rgba(0, 201, 136, 0.08); border-left: 3px solid var(--primary); padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.85rem;">
                <strong>Special Instructions:</strong> <?= e($order['notes']) ?>
            </div>
        <?php endif; ?>

        <!-- Itemized Table -->
        <div class="table-responsive" style="margin-bottom: 1.5rem;">
            <table class="table invoice-table" style="margin-bottom: 0;">
                <thead>
                    <tr><th>Dish Description</th><th style="text-align:center;">Unit Price</th><th style="text-align:center;">Quantity</th><th style="text-align:right;">Total (LKR)</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><strong><?= e($it['dish_name']) ?></strong><?php if (!empty($it['category_name'])): ?><div style="font-size:0.75rem; color:var(--text-muted);"><?= e($it['category_name']) ?></div><?php endif; ?></td>
                            <td style="text-align:center;"><?= format_price((float)$it['unit_price']) ?></td>
                            <td style="text-align:center; font-weight:600;"><?= (int)$it['quantity'] ?></td>
                            <td style="text-align:right; font-weight:600;"><?= format_price((float)$it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Calculation -->
        <div style="display: flex; justify-content: flex-end;">
            <div style="width: 100%; max-width: 320px;">
                <div style="display:flex; justify-content:space-between; padding:0.25rem 0; font-size:0.9rem;"><span>Items Subtotal:</span><strong><?= format_price((float)$order['subtotal']) ?></strong></div>
                <?php if ((float)$order['discount_amount'] > 0): ?>
                    <div style="display:flex; justify-content:space-between; padding:0.25rem 0; font-size:0.9rem; color:var(--success);">
                        <span>Coupon Discount (<?= e($order['coupon_code'] ?? 'PROMO') ?>):</span><strong>&minus; <?= format_price((float)$order['discount_amount']) ?></strong>
                    </div>
                <?php endif; ?>
                <div style="display:flex; justify-content:space-between; padding:0.25rem 0; font-size:0.9rem;"><span>Delivery Fee:</span><strong><?= format_price((float)$order['delivery_fee']) ?></strong></div>
                <div style="border-top: 2px solid var(--border-color); margin: 0.5rem 0; padding-top: 0.5rem; display: flex; justify-content: space-between; align-items: baseline;">
                    <span style="font-size: 1.1rem; font-weight: 700;">Grand Total:</span>
                    <span style="font-size: 1.5rem; font-weight: 800; color: var(--primary);"><?= format_price((float)$order['total_amount']) ?></span>
                </div>
            </div>
        </div>

        <div style="border-top: 1px dashed var(--border-color); margin-top: 2rem; padding-top: 1rem; text-align: center; color: var(--text-muted); font-size: 0.75rem;">
            Thank you for ordering with <strong>UrbanEats Cloud Kitchen</strong>! Hotline: +94 11 234 5678
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
