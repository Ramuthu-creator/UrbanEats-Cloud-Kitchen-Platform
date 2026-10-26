<?php
/**
 * UrbanEats Cloud Kitchen Platform - COMPONENT C: Checkout & Order Processing
 * Single atomic database transaction processing order, items, payments & inventory.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();
$pdo = get_db();
$currentUser = current_user();
$userId = current_user_id();

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    set_flash('error', 'Your shopping cart is empty.');
    redirect('orders/cart.php');
}

// 1. Re-verify Live Dish Data & Stock Directly from DB
$dishIds = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($dishIds), '?'));
$stmt = $pdo->prepare("SELECT d.id, d.name, d.price, d.is_active, COALESCE(i.current_stock, 0) AS current_stock
    FROM dishes d LEFT JOIN inventory i ON d.id = i.dish_id WHERE d.id IN ($placeholders)");
$stmt->execute($dishIds);
$verifiedDishes = $stmt->fetchAll();

$items = []; $subtotal = 0.00; $stockErrors = [];
foreach ($verifiedDishes as $d) {
    $dishId = (int)$d['id']; $qty = (int)$_SESSION['cart'][$dishId];
    if ((int)$d['is_active'] !== 1) { unset($_SESSION['cart'][$dishId]); $stockErrors[] = "'{$d['name']}' is no longer available."; continue; }
    if ((int)$d['current_stock'] < $qty) $stockErrors[] = "Insufficient stock for '{$d['name']}' (Available: {$d['current_stock']}).";
    $lineTotal = (float)$d['price'] * $qty; $subtotal += $lineTotal;
    $items[] = ['id' => $dishId, 'name' => $d['name'], 'price' => (float)$d['price'], 'quantity' => $qty, 'line_total' => $lineTotal];
}
if (!empty($stockErrors)) { foreach ($stockErrors as $e) set_flash('error', $e); redirect('orders/cart.php'); }

// 2. Re-verify Applied Coupon
$couponDiscount = 0.00;
$couponId = null;
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
if ($appliedCoupon) {
    $cStmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ? AND is_active = 1 LIMIT 1");
    $cStmt->execute([$appliedCoupon['id']]);
    $dbCoupon = $cStmt->fetch();
    if ($dbCoupon && strtotime($dbCoupon['expires_at']) >= time() && (int)$dbCoupon['times_used'] < (int)$dbCoupon['usage_limit'] && $subtotal >= (float)$dbCoupon['min_order_value']) {
        $couponId = (int)$dbCoupon['id'];
        $couponDiscount = ($dbCoupon['discount_type'] === 'PERCENTAGE') ? round(($subtotal * (float)$dbCoupon['discount_value']) / 100, 2) : min((float)$dbCoupon['discount_value'], $subtotal);
    } else {
        unset($_SESSION['applied_coupon']);
    }
}

// 3. Process Checkout Form Submission (Database Transaction)
$errors = [];
$orderType = $_POST['order_type'] ?? 'DELIVERY';
$deliveryAddress = trim($_POST['delivery_address'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? ($currentUser['phone'] ?? ''));
$notes = trim($_POST['notes'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? 'CASH_ON_DELIVERY';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    if (!in_array($orderType, ['DELIVERY', 'PICKUP'], true)) $errors[] = 'Invalid fulfillment type.';
    if ($orderType === 'DELIVERY' && empty($deliveryAddress)) $errors[] = 'Delivery address is required for delivery.';
    if (empty($contactPhone) || !preg_match('/^[0-9+\-\s()]{7,20}$/', $contactPhone)) $errors[] = 'Valid phone number is required.';
    if (!in_array($paymentMethod, ['CASH_ON_DELIVERY', 'CARD', 'BANK_TRANSFER'], true)) $errors[] = 'Invalid payment method.';

    $simulatedRef = null;
    $paymentStatus = 'PENDING';
    $paidAt = null;

    if ($paymentMethod === 'CARD') {
        if (empty(trim($_POST['card_name'] ?? '')) || strlen(preg_replace('/\s+/', '', $_POST['card_number'] ?? '')) < 13) {
            $errors[] = 'Please complete all required simulated card details.';
        } else {
            $paymentStatus = 'COMPLETED';
            $paidAt = date('Y-m-d H:i:s');
            $simulatedRef = 'TXN-CARD-' . strtoupper(substr(md5((string)mt_rand()), 0, 8));
        }
    } elseif ($paymentMethod === 'BANK_TRANSFER') {
        $ref = trim($_POST['bank_reference'] ?? '');
        if (empty($ref)) $errors[] = 'Please enter your bank transfer reference.';
        else $simulatedRef = 'BT-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $ref), 0, 20));
    } else {
        $simulatedRef = 'COD-' . strtoupper(substr(uniqid(), -6));
    }

    $deliveryFee = ($orderType === 'DELIVERY') ? 350.00 : 0.00;
    $totalAmount = max(0, $subtotal - $couponDiscount + $deliveryFee);

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $orderNumber = sprintf("ORD-%s-%s", date('Ym'), strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)));

            // 1. Insert Order
            $pdo->prepare("INSERT INTO orders (order_number, user_id, order_type, delivery_address, contact_phone, status, subtotal, discount_amount, delivery_fee, total_amount, coupon_id, notes, created_at)
                VALUES (?, ?, ?, ?, ?, 'PLACED', ?, ?, ?, ?, ?, ?, NOW())")
                ->execute([$orderNumber, $userId, $orderType, ($orderType === 'DELIVERY') ? $deliveryAddress : null, $contactPhone, $subtotal, $couponDiscount, $deliveryFee, $totalAmount, $couponId, $notes ?: null]);
            $orderId = (int)$pdo->lastInsertId();

            // 2. Insert Items & 3. Deduct Stock atomically
            $oiStmt = $pdo->prepare("INSERT INTO order_items (order_id, dish_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stockStmt = $pdo->prepare("UPDATE inventory SET current_stock = current_stock - ? WHERE dish_id = ? AND current_stock >= ?");
            $moveStmt = $pdo->prepare("INSERT INTO stock_movements (dish_id, user_id, movement_type, quantity, reason, created_at) VALUES (?, ?, 'ORDER_DEDUCT', ?, ?, NOW())");

            foreach ($items as $it) {
                $oiStmt->execute([$orderId, $it['id'], $it['quantity'], $it['price'], $it['line_total']]);
                $stockStmt->execute([$it['quantity'], $it['id'], $it['quantity']]);
                if ($stockStmt->rowCount() === 0) throw new Exception("Stock contention: '{$it['name']}' just ran out of stock.");
                $moveStmt->execute([$it['id'], $userId, $it['quantity'], "Deducted for Order #{$orderNumber}"]);
            }

            // 4. Payment & 5. Status History
            $pdo->prepare("INSERT INTO payments (order_id, payment_method, amount, payment_status, reference_number, paid_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")->execute([$orderId, $paymentMethod, $totalAmount, $paymentStatus, $simulatedRef, $paidAt]);
            $pdo->prepare("INSERT INTO order_status_history (order_id, status, notes, changed_by_user_id, changed_at) VALUES (?, 'PLACED', ?, ?, NOW())")->execute([$orderId, "Placed ({$orderType}) via {$paymentMethod}", $userId]);
            if ($couponId) $pdo->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE id = ?")->execute([$couponId]);

            $pdo->commit();
            $_SESSION['cart'] = [];
            unset($_SESSION['applied_coupon']);
            if (function_exists('log_action')) log_action('ORDER_PLACED', ['order_id' => $orderId, 'total' => $totalAmount], $userId);

            set_flash('success', "🎉 Your order #{$orderNumber} has been successfully placed!");
            redirect('orders/confirmation.php?id=' . $orderId);
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex->getMessage() ?: 'Database error occurred. Order rolled back safely.';
        }
    }
}

$currentDeliveryFee = ($orderType === 'DELIVERY') ? 350.00 : 0.00;
$currentTotal = max(0, $subtotal - $couponDiscount + $currentDeliveryFee);
$pageTitle = 'Secure Checkout - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1100px; margin-top: 1rem; margin-bottom: 3rem;">
    <div style="margin-bottom: 1.5rem;">
        <span class="badge badge-customer">Component C &bull; Checkout Transaction</span>
        <h1 style="margin: 0.3rem 0; font-size: 2.2rem;">Complete Your Order</h1>
        <p style="color:var(--text-muted); margin:0;">Select fulfillment, provide details, and submit your order.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" style="margin-bottom: 1.5rem;">
            <strong>Please resolve the following:</strong>
            <ul style="margin: 0.3rem 0 0 1.2rem;"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('orders/checkout.php') ?>" id="checkoutForm">
        <?= csrf_input() ?>
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="card">
                    <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">📍 1. Order Fulfillment</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <label class="card" style="padding:1rem; cursor:pointer; border:2px solid <?= ($orderType === 'DELIVERY') ? 'var(--primary)' : 'var(--border-color)' ?>;" id="optDeliveryCard">
                            <input type="radio" name="order_type" value="DELIVERY" id="typeDelivery" <?= ($orderType === 'DELIVERY') ? 'checked' : '' ?> onchange="toggleOrderType();"> <strong>🛵 Home Delivery</strong>
                            <p style="font-size:0.8rem; color:var(--text-secondary); margin:4px 0 0;">Delivery in Colombo (Rs. 350.00)</p>
                        </label>
                        <label class="card" style="padding:1rem; cursor:pointer; border:2px solid <?= ($orderType === 'PICKUP') ? 'var(--primary)' : 'var(--border-color)' ?>;" id="optPickupCard">
                            <input type="radio" name="order_type" value="PICKUP" id="typePickup" <?= ($orderType === 'PICKUP') ? 'checked' : '' ?> onchange="toggleOrderType();"> <strong>🛍️ Self Pickup</strong>
                            <p style="font-size:0.8rem; color:var(--text-secondary); margin:4px 0 0;">Pickup at kitchen counter (Free)</p>
                        </label>
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Contact Phone Number *</label>
                        <input type="text" name="contact_phone" class="form-control" value="<?= e($contactPhone) ?>" placeholder="0771234567" required>
                    </div>
                    <div class="form-group" id="deliveryAddressGroup" style="display: <?= ($orderType === 'DELIVERY') ? 'block' : 'none' ?>; margin-bottom:1rem;">
                        <label class="form-label">Delivery Street Address *</label>
                        <textarea name="delivery_address" class="form-control" rows="2" placeholder="Street Name, City"><?= e($deliveryAddress) ?></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Special Cooking Instructions (Optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Extra spicy" value="<?= e($notes) ?>">
                    </div>
                </div>

                <div class="card">
                    <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">💳 2. Payment Method (Simulated)</h2>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1rem;">
                        <label class="card" style="padding:0.75rem 1rem; cursor:pointer; display:flex; align-items:center; gap:0.75rem;">
                            <input type="radio" name="payment_method" value="CASH_ON_DELIVERY" <?= ($paymentMethod === 'CASH_ON_DELIVERY') ? 'checked' : '' ?> onchange="togglePaymentMethod();"> <strong>💵 Cash on Delivery / Pay on Pickup</strong>
                        </label>
                        <label class="card" style="padding:0.75rem 1rem; cursor:pointer; display:flex; align-items:center; gap:0.75rem;">
                            <input type="radio" name="payment_method" value="CARD" <?= ($paymentMethod === 'CARD') ? 'checked' : '' ?> onchange="togglePaymentMethod();"> <strong>💳 Credit / Debit Card (Online Simulation)</strong>
                        </label>
                        <label class="card" style="padding:0.75rem 1rem; cursor:pointer; display:flex; align-items:center; gap:0.75rem;">
                            <input type="radio" name="payment_method" value="BANK_TRANSFER" <?= ($paymentMethod === 'BANK_TRANSFER') ? 'checked' : '' ?> onchange="togglePaymentMethod();"> <strong>🏦 Direct Bank Transfer / Deposit</strong>
                        </label>
                    </div>
                    <div id="cardFields" style="display: <?= ($paymentMethod === 'CARD') ? 'block' : 'none' ?>; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 0.5rem;">
                        <div style="font-size:0.8rem; color:var(--info); font-weight:600; margin-bottom:0.75rem;">🔒 Sandbox Gateway &bull; No real money charged</div>
                        <input type="text" name="card_name" class="form-control" placeholder="Cardholder Name" value="<?= e($currentUser['full_name'] ?? '') ?>" style="margin-bottom:0.5rem;">
                        <input type="text" name="card_number" class="form-control" placeholder="4111 2222 3333 4444" value="4111 2222 3333 4444" style="margin-bottom:0.5rem;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;"><input type="text" name="card_expiry" class="form-control" placeholder="12/28" value="12/28"><input type="password" name="card_cvv" class="form-control" placeholder="CVV" value="123"></div>
                    </div>
                    <div id="bankFields" style="display: <?= ($paymentMethod === 'BANK_TRANSFER') ? 'block' : 'none' ?>; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem;">
                        <div style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:0.5rem;">Commercial Bank &bull; A/C: <strong>800-459-2810</strong> (UrbanEats Ltd)</div>
                        <input type="text" name="bank_reference" class="form-control" placeholder="Bank Reference (e.g. COM-TXN-98471)" value="COM-TXN-<?= mt_rand(10000, 99999) ?>">
                    </div>
                </div>
            </div>

            <!-- Right: Order Summary Sidebar -->
            <div class="card" style="position: sticky; top: 90px;">
                <h3 style="font-size: 1.15rem; margin-bottom: 1rem; display: flex; justify-content: space-between;"><span>Order Summary</span><span class="badge badge-primary"><?= count($items) ?> Dish(es)</span></h3>
                <div style="max-height: 200px; overflow-y: auto; margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php foreach ($items as $it): ?>
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem; border-bottom:1px dashed var(--border-color); padding-bottom:4px;">
                            <div><strong><?= e($it['name']) ?></strong> <span style="color:var(--text-muted); font-size:0.75rem;">&times;<?= $it['quantity'] ?></span></div>
                            <div><?= format_price($it['line_total']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-bottom:0.4rem;"><span>Subtotal</span><strong><?= format_price($subtotal) ?></strong></div>
                <?php if ($couponDiscount > 0): ?><div style="display:flex; justify-content:space-between; font-size:0.9rem; color:var(--success); margin-bottom:0.4rem;"><span>Discount (<?= e($appliedCoupon['code']) ?>)</span><strong>&minus; <?= format_price($couponDiscount) ?></strong></div><?php endif; ?>
                <div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-bottom:0.8rem;"><span>Delivery Fee</span><strong id="summaryDeliveryFee"><?= format_price($currentDeliveryFee) ?></strong></div>
                <hr style="border:0; border-top:1px solid var(--border-color); margin:0.5rem 0 0.8rem;">
                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:1.25rem;"><span style="font-size:1.1rem; font-weight:700;">Total Amount</span><span style="font-size:1.5rem; font-weight:800; color:var(--primary);" id="summaryGrandTotal"><?= format_price($currentTotal) ?></span></div>
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Place Order 🚀</button>
                <div style="margin-top:0.75rem; text-align:center;"><a href="<?= base_url('orders/cart.php') ?>" style="font-size:0.8rem; color:var(--text-muted);">&larr; Back to Cart</a></div>
                <div style="margin-top:1rem; font-size:0.72rem; color:var(--text-muted); line-height:1.4; border-top:1px solid var(--border-color); padding-top:0.6rem;">🛡️ <strong>Single Transaction Guarantee:</strong> All database changes commit atomically or roll back completely.</div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleOrderType() {
    const isDelivery = document.getElementById('typeDelivery').checked;
    document.getElementById('deliveryAddressGroup').style.display = isDelivery ? 'block' : 'none';
    const subtotal = <?= json_encode($subtotal) ?>, discount = <?= json_encode($couponDiscount) ?>, fee = isDelivery ? 350.00 : 0.00;
    document.getElementById('summaryDeliveryFee').textContent = 'Rs. ' + fee.toFixed(2);
    document.getElementById('summaryGrandTotal').textContent = 'Rs. ' + Math.max(0, subtotal - discount + fee).toFixed(2);
}
function togglePaymentMethod() {
    const pm = document.querySelector('input[name="payment_method"]:checked').value;
    document.getElementById('cardFields').style.display = (pm === 'CARD') ? 'block' : 'none';
    document.getElementById('bankFields').style.display = (pm === 'BANK_TRANSFER') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', function() { toggleOrderType(); togglePaymentMethod(); });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
