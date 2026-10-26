<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Printable Order Confirmation & Invoice
 * Primary Owner: Student 3
 *
 * Implements:
 * - Printable invoice/receipt view with dedicated @media print stylesheet
 * - Customer & delivery details, order summary, and itemized billing table
 * - Payment confirmation with transaction reference number
 * - Direct navigation to live tracking and order history
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

// Fetch order either by ID or by Order Number
if ($orderId) {
    $stmt = $pdo->prepare(
        "SELECT o.*, u.full_name AS customer_name, u.email AS customer_email,
                c.code AS coupon_code, c.discount_type, c.discount_value,
                p.payment_method, p.payment_status, p.reference_number, p.paid_at
         FROM orders o
         JOIN users u ON o.user_id = u.id
         LEFT JOIN coupons c ON o.coupon_id = c.id
         LEFT JOIN payments p ON o.id = p.order_id
         WHERE o.id = ?
         LIMIT 1"
    );
    $stmt->execute([$orderId]);
} elseif ($orderNumber) {
    $stmt = $pdo->prepare(
        "SELECT o.*, u.full_name AS customer_name, u.email AS customer_email,
                c.code AS coupon_code, c.discount_type, c.discount_value,
                p.payment_method, p.payment_status, p.reference_number, p.paid_at
         FROM orders o
         JOIN users u ON o.user_id = u.id
         LEFT JOIN coupons c ON o.coupon_id = c.id
         LEFT JOIN payments p ON o.id = p.order_id
         WHERE o.order_number = ?
         LIMIT 1"
    );
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

// Access Control: Customer can only view their own order; Staff & Admin can view any order
if ($currentRole === 'customer' && (int)$order['user_id'] !== $currentUserId) {
    log_action('UNAUTHORIZED_ORDER_VIEW', "User {$currentUserId} attempted to access Order #{$order['order_number']}");
    redirect('public/access_denied.php');
}

// Fetch Order Items
$itemStmt = $pdo->prepare(
    "SELECT oi.*, d.name AS dish_name, d.image_url, c.name AS category_name
     FROM order_items oi
     JOIN dishes d ON oi.dish_id = d.id
     LEFT JOIN categories c ON d.category_id = c.id
     WHERE oi.order_id = ?
     ORDER BY oi.id ASC"
);
$itemStmt->execute([$order['id']]);
$items = $itemStmt->fetchAll();

// Fetch status badge styling
$statusBadgeClass = match ($order['status']) {
    'PLACED'           => 'badge-primary',
    'PREPARING'        => 'badge-warning',
    'READY'            => 'badge-info',
    'OUT_FOR_DELIVERY',
    'COLLECTED'        => 'badge-customer',
    'COMPLETED'        => 'badge-success',
    'CANCELLED'        => 'badge-danger',
    default            => 'badge-secondary'
};

$pageTitle = 'Invoice ' . $order['order_number'] . ' - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 900px; margin-top: 1rem;">
    <!-- Non-print Action Bar -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="<?= base_url('orders/history.php') ?>" class="btn btn-secondary btn-sm">
                &larr; Back to Order History
            </a>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="<?= base_url('orders/track.php?order_number=' . urlencode($order['order_number'])) ?>" class="btn btn-primary btn-sm">
                📍 Track Order Live &rarr;
            </a>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print();">
                🖨️ Print Invoice
            </button>
        </div>
    </div>

    <!-- Printable Invoice Card -->
    <div class="card invoice-card" style="padding: 2.5rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-md);">
        
        <!-- Invoice Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--border-color); padding-bottom: 1.75rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                    <span style="font-size: 2.2rem;">🍛</span>
                    <div>
                        <h1 style="font-size: 1.85rem; margin-bottom: 0; line-height: 1;">Urban<span style="color: var(--primary);">Eats</span></h1>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em;">Cloud Kitchens Colombo</span>
                    </div>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
                    No. 120, Galle Road, Colombo 03, Sri Lanka<br>
                    Hotline: +94 11 234 5678 &bull; orders@urbaneats.lk
                </div>
            </div>

            <div style="text-align: right;">
                <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.25rem;">Official Tax Invoice</div>
                <h2 style="font-size: 1.65rem; color: var(--primary); margin-bottom: 0.4rem; font-family: monospace;">
                    <?= e($order['order_number']) ?>
                </h2>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                    Placed: <strong><?= format_datetime($order['created_at']) ?></strong>
                </div>
                <div style="margin-top: 0.5rem;">
                    <span class="badge <?= $statusBadgeClass ?>" style="font-size: 0.85rem; padding: 0.3rem 0.8rem;">
                        Status: <?= e($order['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Bill To / Fulfillment Info Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">Customer Information</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                    <?= e($order['customer_name']) ?>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
                    📧 <?= e($order['customer_email']) ?><br>
                    📞 <?= e($order['contact_phone']) ?>
                </div>
            </div>

            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">Order Fulfillment & Payment</div>
                <div style="font-size: 1.05rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.35rem;">
                    <?= $order['order_type'] === 'DELIVERY' ? '🛵 Home Delivery' : '🛍️ Self Pickup (Kitchen Counter)' ?>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
                    <?php if ($order['order_type'] === 'DELIVERY'): ?>
                        <strong>Address:</strong> <?= nl2br(e($order['delivery_address'])) ?><br>
                    <?php endif; ?>
                    <strong>Payment:</strong> <?= e(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')) ?>
                    <span class="badge badge-<?= ($order['payment_status'] === 'COMPLETED') ? 'success' : 'warning' ?>" style="font-size: 0.7rem; margin-left: 0.4rem;">
                        <?= e($order['payment_status'] ?? 'PENDING') ?>
                    </span>
                    <?php if (!empty($order['reference_number'])): ?>
                        <br><span style="font-size: 0.8rem; font-family: monospace;">Ref: <?= e($order['reference_number']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
            <div style="background: rgba(245, 158, 11, 0.08); border-left: 3px solid var(--primary); padding: 0.85rem 1.25rem; border-radius: 4px; margin-bottom: 1.75rem; font-size: 0.875rem; color: var(--text-secondary);">
                <strong style="color: var(--primary);">Special Customer Instructions:</strong> <?= e($order['notes']) ?>
            </div>
        <?php endif; ?>

        <!-- Itemized Table -->
        <div class="table-responsive" style="margin-bottom: 2rem;">
            <table class="table invoice-table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width: 50%;">Dish Description</th>
                        <th style="text-align: center; width: 15%;">Unit Price</th>
                        <th style="text-align: center; width: 15%;">Quantity</th>
                        <th style="text-align: right; width: 20%;">Total (LKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-primary);"><?= e($item['dish_name']) ?></strong>
                                <?php if (!empty($item['category_name'])): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= e($item['category_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <?= format_price((float)$item['unit_price']) ?>
                            </td>
                            <td style="text-align: center; font-weight: 600;">
                                <?= (int)$item['quantity'] ?>
                            </td>
                            <td style="text-align: right; font-weight: 600; color: var(--text-primary); white-space: nowrap;">
                                <?= format_price((float)$item['subtotal']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Calculation Breakdown -->
        <div style="display: flex; justify-content: flex-end;">
            <div style="width: 100%; max-width: 360px;">
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.95rem; color: var(--text-secondary);">
                    <span>Items Subtotal:</span>
                    <strong style="color: var(--text-primary);"><?= format_price((float)$order['subtotal']) ?></strong>
                </div>

                <?php if ((float)$order['discount_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.95rem; color: var(--success);">
                        <span>Coupon Discount (<?= e($order['coupon_code'] ?? 'PROMO') ?>):</span>
                        <strong>&minus; <?= format_price((float)$order['discount_amount']) ?></strong>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.95rem; color: var(--text-secondary);">
                    <span>Delivery Fee:</span>
                    <strong style="color: var(--text-primary);"><?= format_price((float)$order['delivery_fee']) ?></strong>
                </div>

                <div style="border-top: 2px solid var(--border-color); margin: 0.75rem 0 0.5rem; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: baseline;">
                    <span style="font-size: 1.15rem; font-weight: 700;">Grand Total:</span>
                    <span style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">
                        <?= format_price((float)$order['total_amount']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Invoice Footer Note -->
        <div style="border-top: 1px dashed var(--border-color); margin-top: 2.5rem; padding-top: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.8rem; line-height: 1.6;">
            Thank you for dining with <strong>UrbanEats Cloud Kitchen</strong>!<br>
            For customer support or order inquiries, quote your Order Number <strong><?= e($order['order_number']) ?></strong>.
        </div>

    </div>
</div>

<!-- Print Stylesheet -->
<style>
@media print {
    /* Hide all web chrome during print */
    .site-header, .site-footer, .no-print, .flash-messages-container {
        display: none !important;
    }

    body {
        background: #fff !important;
        color: #000 !important;
        font-size: 12pt;
    }

    .container {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .card.invoice-card {
        background: #fff !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        color: #000 !important;
    }

    h1, h2, h3, strong {
        color: #000 !important;
    }

    .invoice-table th, .invoice-table td {
        border-bottom: 1px solid #ccc !important;
        color: #000 !important;
    }

    .invoice-table th {
        background: #f4f4f4 !important;
    }

    div[style*="background: var(--bg-surface)"] {
        background: #f9f9f9 !important;
        border: 1px solid #ddd !important;
    }

    .badge {
        border: 1px solid #333 !important;
        color: #000 !important;
        background: transparent !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
