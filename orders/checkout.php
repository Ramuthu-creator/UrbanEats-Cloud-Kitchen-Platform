<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Checkout & Order Processing
 * Primary Owner: Student 3
 *
 * Implements:
 * - Single database transaction enforcing atomicity:
 *   1. Insert orders record
 *   2. Insert order_items records (price snapshot at time of purchase)
 *   3. Insert payments record (simulated gateway)
 *   4. Insert order_status_history record (stage: PLACED)
 *   5. Deduct inventory stock (prevents negative stock, rolls back on contention)
 *   6. Update coupon usage count
 *   7. Rollback completely on any failure
 * - Server-side validation of order type, delivery address, phone, and payment method
 * - Re-verification of prices and availability from live database
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Checkout requires an authenticated customer
require_login();

$pdo = get_db();
$currentUser = current_user();
$userId = current_user_id();

// Ensure cart has items
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    set_flash('error', 'Your shopping cart is empty. Please select dishes before proceeding to checkout.');
    redirect('orders/cart.php');
}

// --------------------------------------------------------------------
// 1. RE-VERIFY LIVE DISH DATA, PRICES & INVENTORY FROM DATABASE
// --------------------------------------------------------------------
$dishIds = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($dishIds), '?'));

$sql = "SELECT d.id, d.name, d.price, d.is_active, c.name AS category_name,
               COALESCE(i.current_stock, 0) AS current_stock
        FROM dishes d
        JOIN categories c ON d.category_id = c.id
        LEFT JOIN inventory i ON d.id = i.dish_id
        WHERE d.id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($dishIds);
$verifiedDishes = $stmt->fetchAll();

$items = [];
$subtotal = 0.00;
$stockErrors = [];

foreach ($verifiedDishes as $dish) {
    $dishId = (int)$dish['id'];
    $qty = (int)$_SESSION['cart'][$dishId];

    if ((int)$dish['is_active'] !== 1) {
        unset($_SESSION['cart'][$dishId]);
        $stockErrors[] = "'{$dish['name']}' is no longer active and has been removed from your cart.";
        continue;
    }

    $stock = (int)$dish['current_stock'];
    if ($stock < $qty) {
        $stockErrors[] = "Insufficient stock for '{$dish['name']}'. Requested: {$qty}, Available: {$stock}.";
    }

    $price = (float)$dish['price'];
    $lineTotal = $price * $qty;
    $subtotal += $lineTotal;

    $items[] = [
        'id'         => $dishId,
        'name'       => $dish['name'],
        'price'      => $price,
        'quantity'   => $qty,
        'stock'      => $stock,
        'line_total' => $lineTotal
    ];
}

if (!empty($stockErrors)) {
    foreach ($stockErrors as $err) {
        set_flash('error', $err);
    }
    redirect('orders/cart.php');
}

if (empty($items)) {
    set_flash('error', 'No valid items found in your cart.');
    redirect('orders/cart.php');
}

// --------------------------------------------------------------------
// 2. COUPON VALIDATION & DISCOUNT RE-CALCULATION
// --------------------------------------------------------------------
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
$couponDiscount = 0.00;
$couponId = null;

if ($appliedCoupon) {
    // Re-verify coupon validity directly from database
    $cStmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ? AND is_active = 1 LIMIT 1");
    $cStmt->execute([$appliedCoupon['id']]);
    $dbCoupon = $cStmt->fetch();

    if ($dbCoupon && strtotime($dbCoupon['expires_at']) >= time() && (int)$dbCoupon['times_used'] < (int)$dbCoupon['usage_limit']) {
        if ($subtotal >= (float)$dbCoupon['min_order_value']) {
            $couponId = (int)$dbCoupon['id'];
            if ($dbCoupon['discount_type'] === 'PERCENTAGE') {
                $couponDiscount = round(($subtotal * (float)$dbCoupon['discount_value']) / 100, 2);
            } else {
                $couponDiscount = min((float)$dbCoupon['discount_value'], $subtotal);
            }
        } else {
            unset($_SESSION['applied_coupon']);
            set_flash('warning', "Coupon '{$dbCoupon['code']}' requires a minimum subtotal of " . format_price($dbCoupon['min_order_value']) . ".");
        }
    } else {
        unset($_SESSION['applied_coupon']);
        set_flash('warning', 'The applied coupon has expired or reached its maximum usage limit.');
    }
}

// --------------------------------------------------------------------
// 3. PROCESS CHECKOUT FORM SUBMISSION (DATABASE TRANSACTION)
// --------------------------------------------------------------------
$errors = [];
$orderType = $_POST['order_type'] ?? 'DELIVERY';
$deliveryAddress = trim($_POST['delivery_address'] ?? '');
$contactPhone = trim($_POST['contact_phone'] ?? ($currentUser['phone'] ?? ''));
$notes = trim($_POST['notes'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? 'CASH_ON_DELIVERY';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();

    // Validation
    if (!in_array($orderType, ['DELIVERY', 'PICKUP'], true)) {
        $errors[] = 'Invalid order fulfillment type selected.';
    }

    if ($orderType === 'DELIVERY' && empty($deliveryAddress)) {
        $errors[] = 'Delivery address is required for home delivery orders.';
    }

    if (empty($contactPhone)) {
        $errors[] = 'A valid contact phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $contactPhone)) {
        $errors[] = 'Please enter a valid phone number (e.g., 0771234567).';
    }

    $validPaymentMethods = ['CASH_ON_DELIVERY', 'CARD', 'BANK_TRANSFER'];
    if (!in_array($paymentMethod, $validPaymentMethods, true)) {
        $errors[] = 'Invalid payment method selected.';
    }

    // Additional checks for card simulation
    $simulatedRef = null;
    $paymentStatus = 'PENDING';
    $paidAt = null;

    if ($paymentMethod === 'CARD') {
        $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $cardName = trim($_POST['card_name'] ?? '');
        $cardExpiry = trim($_POST['card_expiry'] ?? '');
        $cardCvv = trim($_POST['card_cvv'] ?? '');

        if (empty($cardName) || strlen($cardNumber) < 13 || empty($cardExpiry) || strlen($cardCvv) < 3) {
            $errors[] = 'Please complete all required simulated credit/debit card fields.';
        } else {
            $paymentStatus = 'COMPLETED';
            $paidAt = date('Y-m-d H:i:s');
            $simulatedRef = 'TXN-CARD-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
        }
    } elseif ($paymentMethod === 'BANK_TRANSFER') {
        $transferRef = trim($_POST['bank_reference'] ?? '');
        if (empty($transferRef)) {
            $errors[] = 'Please provide your bank transfer reference or transaction number.';
        } else {
            $simulatedRef = 'BT-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $transferRef), 0, 20));
            $paymentStatus = 'PENDING'; // Kitchen staff verifies deposit
        }
    } else {
        // Cash on delivery / pickup
        $simulatedRef = 'COD-' . strtoupper(substr(uniqid(), -6));
        $paymentStatus = 'PENDING';
    }

    // Delivery fee calculation
    $deliveryFee = ($orderType === 'DELIVERY') ? 350.00 : 0.00;
    $totalAmount = max(0, $subtotal - $couponDiscount + $deliveryFee);

    // If validation passed, execute single transaction
    if (empty($errors)) {
        try {
            // Begin single database transaction
            $pdo->beginTransaction();

            // Generate unique human-readable order number (e.g., ORD-202610-009)
            $yearMonth = date('Ym');
            $randomCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $orderNumber = sprintf("ORD-%s-%s", $yearMonth, $randomCode);

            // 1. Insert into orders table
            $orderSql = "INSERT INTO orders (
                            order_number, user_id, order_type, delivery_address, 
                            contact_phone, status, subtotal, discount_amount, 
                            delivery_fee, total_amount, coupon_id, notes, created_at
                         ) VALUES (
                            :order_number, :user_id, :order_type, :delivery_address, 
                            :contact_phone, 'PLACED', :subtotal, :discount_amount, 
                            :delivery_fee, :total_amount, :coupon_id, :notes, NOW()
                         )";
            $orderStmt = $pdo->prepare($orderSql);
            $orderStmt->execute([
                ':order_number'      => $orderNumber,
                ':user_id'           => $userId,
                ':order_type'        => $orderType,
                ':delivery_address'  => ($orderType === 'DELIVERY') ? $deliveryAddress : null,
                ':contact_phone'     => $contactPhone,
                ':subtotal'          => $subtotal,
                ':discount_amount'   => $couponDiscount,
                ':delivery_fee'      => $deliveryFee,
                ':total_amount'      => $totalAmount,
                ':coupon_id'         => $couponId,
                ':notes'             => $notes ?: null
            ]);

            $orderId = (int)$pdo->lastInsertId();

            // 2. Insert order items (recording unit_price at time of purchase)
            $itemSql = "INSERT INTO order_items (order_id, dish_id, quantity, unit_price, subtotal)
                        VALUES (:order_id, :dish_id, :quantity, :unit_price, :subtotal)";
            $itemStmt = $pdo->prepare($itemSql);

            foreach ($items as $item) {
                $itemStmt->execute([
                    ':order_id'   => $orderId,
                    ':dish_id'    => $item['id'],
                    ':quantity'   => $item['quantity'],
                    ':unit_price' => $item['price'],
                    ':subtotal'   => $item['line_total']
                ]);

                // 3. Atomically deduct inventory stock and verify it never falls below zero
                // We use conditional update with row count check
                $stockUpdate = $pdo->prepare(
                    "UPDATE inventory
                     SET current_stock = current_stock - :qty
                     WHERE dish_id = :dish_id AND current_stock >= :min_qty"
                );
                $stockUpdate->execute([
                    ':qty'     => $item['quantity'],
                    ':dish_id' => $item['id'],
                    ':min_qty' => $item['quantity']
                ]);

                if ($stockUpdate->rowCount() === 0) {
                    // Contention failure: dish ran out of stock concurrently
                    throw new Exception("Unable to complete order: '{$item['name']}' has insufficient stock. Please adjust your order.");
                }

                // Record stock movement log
                $smStmt = $pdo->prepare(
                    "INSERT INTO stock_movements (dish_id, user_id, movement_type, quantity, reason, created_at)
                     VALUES (:dish_id, :user_id, 'ORDER_DEDUCT', :qty, :reason, NOW())"
                );
                $smStmt->execute([
                    ':dish_id' => $item['id'],
                    ':user_id' => $userId,
                    ':qty'     => $item['quantity'],
                    ':reason'  => "Deducted for Order #{$orderNumber}"
                ]);
            }

            // 4. Record payment record
            $paySql = "INSERT INTO payments (
                           order_id, payment_method, amount, payment_status, reference_number, paid_at, created_at
                       ) VALUES (
                           :order_id, :payment_method, :amount, :payment_status, :reference_number, :paid_at, NOW()
                       )";
            $payStmt = $pdo->prepare($paySql);
            $payStmt->execute([
                ':order_id'         => $orderId,
                ':payment_method'   => $paymentMethod,
                ':amount'           => $totalAmount,
                ':payment_status'   => $paymentStatus,
                ':reference_number' => $simulatedRef,
                ':paid_at'          => $paidAt
            ]);

            // 5. Record initial order status history
            $histSql = "INSERT INTO order_status_history (
                            order_id, status, notes, changed_by_user_id, changed_at
                        ) VALUES (
                            :order_id, 'PLACED', :notes, :changed_by_user_id, NOW()
                        )";
            $histStmt = $pdo->prepare($histSql);
            $histStmt->execute([
                ':order_id'            => $orderId,
                ':notes'               => "Order submitted by customer ({$orderType}) via {$paymentMethod}",
                ':changed_by_user_id'  => $userId
            ]);

            // 6. Update coupon usage count if applied
            if ($couponId) {
                $cUpdate = $pdo->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE id = ?");
                $cUpdate->execute([$couponId]);
            }

            // Commit the entire transaction atomically
            $pdo->commit();

            // Clear session cart and applied coupon
            $_SESSION['cart'] = [];
            unset($_SESSION['applied_coupon']);

            // Centralized audit logging
            log_action('ORDER_PLACED', [
                'order_id'       => $orderId,
                'order_number'   => $orderNumber,
                'total_amount'   => $totalAmount,
                'payment_method' => $paymentMethod,
                'order_type'     => $orderType
            ], $userId);

            set_flash('success', "🎉 Your order #{$orderNumber} has been successfully placed!");
            redirect('orders/confirmation.php?id=' . $orderId);

        } catch (Exception $e) {
            // Any failure rolls back every change
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_system_error('CHECKOUT_TRANSACTION_FAILED', $e->getMessage());
            $errors[] = $e->getMessage() ?: 'An unexpected error occurred while placing your order. Please try again.';
        }
    }
}

// Estimated totals for initial display
$currentDeliveryFee = ($orderType === 'DELIVERY') ? 350.00 : 0.00;
$currentTotal = max(0, $subtotal - $couponDiscount + $currentDeliveryFee);

$pageTitle = 'Secure Checkout - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1100px; margin-top: 1rem;">
    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
        <span class="badge badge-customer">Component C &bull; Checkout Transaction</span>
        <h1 style="margin-top: 0.4rem; font-size: 2.2rem;">Complete Your Order</h1>
        <p>Please review your order details, select your fulfillment preference, and simulate payment.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert" style="margin-bottom: 2rem;">
            <span class="alert-icon">✕</span>
            <div>
                <strong>Please resolve the following before placing your order:</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem; line-height: 1.5;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('orders/checkout.php') ?>" id="checkoutForm">
        <?= csrf_input() ?>

        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 2rem; align-items: start;">
            
            <!-- Left Column: Fulfillment & Payment Details -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- 1. Fulfillment Preference -->
                <div class="card">
                    <h2 style="font-size: 1.35rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        📍 1. Order Fulfillment
                    </h2>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <label class="card" style="padding: 1.25rem; cursor: pointer; border: 2px solid <?= ($orderType === 'DELIVERY') ? 'var(--primary)' : 'var(--border-color)' ?>; background: <?= ($orderType === 'DELIVERY') ? 'rgba(245, 158, 11, 0.06)' : 'var(--bg-surface)' ?>; transition: var(--transition);" id="optDeliveryCard">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                <input type="radio" name="order_type" value="DELIVERY" id="typeDelivery" <?= ($orderType === 'DELIVERY') ? 'checked' : '' ?> onchange="toggleOrderType();">
                                <strong style="font-size: 1.1rem; color: var(--text-primary);">🛵 Home Delivery</strong>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0;">
                                Delivered to your door in Colombo. Delivery fee: <strong>Rs. 350.00</strong>.
                            </p>
                        </label>

                        <label class="card" style="padding: 1.25rem; cursor: pointer; border: 2px solid <?= ($orderType === 'PICKUP') ? 'var(--primary)' : 'var(--border-color)' ?>; background: <?= ($orderType === 'PICKUP') ? 'rgba(245, 158, 11, 0.06)' : 'var(--bg-surface)' ?>; transition: var(--transition);" id="optPickupCard">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                <input type="radio" name="order_type" value="PICKUP" id="typePickup" <?= ($orderType === 'PICKUP') ? 'checked' : '' ?> onchange="toggleOrderType();">
                                <strong style="font-size: 1.1rem; color: var(--text-primary);">🛍️ Cloud Kitchen Pickup</strong>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0;">
                                Collect fresh from our kitchen counter in Kollupitiya. <strong>Zero Delivery Fee</strong>.
                            </p>
                        </label>
                    </div>

                    <!-- Contact Phone -->
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label for="contact_phone" class="form-label">Contact Phone Number *</label>
                        <input type="text" id="contact_phone" name="contact_phone" class="form-control"
                               value="<?= e($contactPhone) ?>" placeholder="e.g. 0771234567" required>
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Our delivery rider will call this number for location confirmation.</small>
                    </div>

                    <!-- Delivery Address (Collapsible if Pickup) -->
                    <div class="form-group" id="deliveryAddressGroup" style="display: <?= ($orderType === 'DELIVERY') ? 'block' : 'none' ?>;">
                        <label for="delivery_address" class="form-label">Delivery Street Address *</label>
                        <textarea id="delivery_address" name="delivery_address" class="form-control" rows="3"
                                  placeholder="House/Apartment number, Street Name, City (e.g. No. 42, Galle Road, Colombo 03)"><?= e($deliveryAddress) ?></textarea>
                    </div>

                    <!-- Kitchen / Cooking Notes -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="notes" class="form-label">Special Cooking or Delivery Instructions (Optional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"
                                  placeholder="e.g. Please make biryani extra spicy, leave package at security desk, etc."><?= e($notes) ?></textarea>
                    </div>
                </div>

                <!-- 2. Payment Method Simulation -->
                <div class="card">
                    <h2 style="font-size: 1.35rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        💳 2. Payment Method (Simulated)
                    </h2>

                    <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.5rem;">
                        <!-- Cash on Delivery -->
                        <label class="card" style="padding: 1rem 1.25rem; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; gap: 1rem;" id="pmCodLabel">
                            <input type="radio" name="payment_method" value="CASH_ON_DELIVERY" <?= ($paymentMethod === 'CASH_ON_DELIVERY') ? 'checked' : '' ?> onchange="togglePaymentMethod();">
                            <div>
                                <strong style="color: var(--text-primary);">💵 Cash on Delivery / Pay on Pickup</strong>
                                <div style="font-size: 0.825rem; color: var(--text-secondary);">Pay in cash to the rider or at the counter upon collecting your order.</div>
                            </div>
                        </label>

                        <!-- Card Simulation -->
                        <label class="card" style="padding: 1rem 1.25rem; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; gap: 1rem;" id="pmCardLabel">
                            <input type="radio" name="payment_method" value="CARD" <?= ($paymentMethod === 'CARD') ? 'checked' : '' ?> onchange="togglePaymentMethod();">
                            <div>
                                <strong style="color: var(--text-primary);">💳 Credit / Debit Card (Simulated Online Payment)</strong>
                                <div style="font-size: 0.825rem; color: var(--text-secondary);">Simulate an instant online card transaction (Visa, Mastercard).</div>
                            </div>
                        </label>

                        <!-- Bank Transfer Simulation -->
                        <label class="card" style="padding: 1rem 1.25rem; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-surface); display: flex; align-items: center; gap: 1rem;" id="pmBtLabel">
                            <input type="radio" name="payment_method" value="BANK_TRANSFER" <?= ($paymentMethod === 'BANK_TRANSFER') ? 'checked' : '' ?> onchange="togglePaymentMethod();">
                            <div>
                                <strong style="color: var(--text-primary);">🏦 Direct Bank Transfer / Deposit</strong>
                                <div style="font-size: 0.825rem; color: var(--text-secondary);">Transfer directly to our Commercial Bank / BOC kitchen account.</div>
                            </div>
                        </label>
                    </div>

                    <!-- Card Details Fields -->
                    <div id="cardFields" style="display: <?= ($paymentMethod === 'CARD') ? 'block' : 'none' ?>; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1rem;">
                        <div style="font-size: 0.85rem; color: var(--info); font-weight: 600; margin-bottom: 1rem;">
                            🔒 Simulated Sandbox Gateway &bull; No real money will be charged
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label">Cardholder Name</label>
                            <input type="text" name="card_name" class="form-control" placeholder="e.g. Kasun Jayasuriya" value="<?= e($currentUser['full_name'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label">Card Number (16 Digits)</label>
                            <input type="text" name="card_number" class="form-control" placeholder="4111 2222 3333 4444" maxlength="19" value="4111 2222 3333 4444">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Expiry (MM/YY)</label>
                                <input type="text" name="card_expiry" class="form-control" placeholder="12/28" maxlength="5" value="12/28">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">CVV / CVC</label>
                                <input type="password" name="card_cvv" class="form-control" placeholder="123" maxlength="4" value="123">
                            </div>
                        </div>
                    </div>

                    <!-- Bank Transfer Details Fields -->
                    <div id="bankFields" style="display: <?= ($paymentMethod === 'BANK_TRANSFER') ? 'block' : 'none' ?>; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem;">
                        <div style="background: rgba(59, 130, 246, 0.1); border-left: 3px solid var(--info); padding: 0.85rem; border-radius: 4px; font-size: 0.85rem; margin-bottom: 1rem;">
                            <strong>Cloud Kitchen Bank Coordinates:</strong><br>
                            Bank: <strong>Commercial Bank of Ceylon</strong> &bull; Branch: <strong>Colombo Fort</strong><br>
                            Account Name: <strong>UrbanEats Cloud Kitchens (Pvt) Ltd</strong><br>
                            Account Number: <strong>800-459-2810</strong>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Transfer / Deposit Slip Reference *</label>
                            <input type="text" name="bank_reference" class="form-control" placeholder="e.g. COM-TXN-98471" value="COM-TXN-<?= mt_rand(10000, 99999) ?>">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Order Summary & Place Order Button -->
            <div>
                <div class="card" style="position: sticky; top: 90px;">
                    <h3 style="font-size: 1.25rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
                        <span>Order Items</span>
                        <span class="badge badge-primary"><?= count($items) ?> Dish(es)</span>
                    </h3>

                    <!-- Itemized List -->
                    <div style="max-height: 240px; overflow-y: auto; margin-bottom: 1.25rem; padding-right: 0.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($items as $item): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.5rem;">
                                <div>
                                    <strong style="color: var(--text-primary);"><?= e($item['name']) ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                                        <?= $item['quantity'] ?> &times; <?= format_price($item['price']) ?>
                                    </div>
                                </div>
                                <div style="font-weight: 600; color: var(--text-primary); text-align: right;">
                                    <?= format_price($item['line_total']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Financial Summary -->
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem; color: var(--text-secondary); font-size: 0.9rem;">
                        <span>Subtotal</span>
                        <strong style="color: var(--text-primary);"><?= format_price($subtotal) ?></strong>
                    </div>

                    <?php if ($couponDiscount > 0): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem; color: var(--success); font-size: 0.9rem;">
                            <span>Discount (<?= e($appliedCoupon['code']) ?>)</span>
                            <strong>&minus; <?= format_price($couponDiscount) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem; color: var(--text-secondary); font-size: 0.9rem;">
                        <span>Delivery Fee</span>
                        <strong id="summaryDeliveryFee" style="color: var(--text-primary);"><?= format_price($currentDeliveryFee) ?></strong>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1rem 0;">

                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.5rem;">
                        <span style="font-size: 1.15rem; font-weight: 700;">Total Amount</span>
                        <span style="font-size: 1.65rem; font-weight: 800; color: var(--primary);" id="summaryGrandTotal">
                            <?= format_price($currentTotal) ?>
                        </span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; font-size: 1.1rem; padding: 0.85rem;">
                        Place Order 🚀
                    </button>

                    <div style="margin-top: 1rem; text-align: center;">
                        <a href="<?= base_url('orders/cart.php') ?>" style="font-size: 0.85rem; color: var(--text-muted);">
                            &larr; Back to Shopping Cart
                        </a>
                    </div>

                    <div style="margin-top: 1.25rem; font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
                        🛡️ <strong>Single Transaction Guarantee:</strong> Orders, items, payment records, and inventory deductions are processed atomically. If any error occurs, your order will roll back safely.
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
// Interactive UI enhancements for Order Type & Payment Method
function toggleOrderType() {
    const isDelivery = document.getElementById('typeDelivery').checked;
    const addressGroup = document.getElementById('deliveryAddressGroup');
    const addressInput = document.getElementById('delivery_address');
    const deliveryFeeLabel = document.getElementById('summaryDeliveryFee');
    const grandTotalLabel = document.getElementById('summaryGrandTotal');
    const deliveryCard = document.getElementById('optDeliveryCard');
    const pickupCard = document.getElementById('optPickupCard');

    const subtotal = <?= json_encode($subtotal) ?>;
    const discount = <?= json_encode($couponDiscount) ?>;

    if (isDelivery) {
        addressGroup.style.display = 'block';
        addressInput.setAttribute('required', 'required');
        deliveryCard.style.borderColor = 'var(--primary)';
        deliveryCard.style.background = 'rgba(245, 158, 11, 0.06)';
        pickupCard.style.borderColor = 'var(--border-color)';
        pickupCard.style.background = 'var(--bg-surface)';

        const fee = 350.00;
        const total = Math.max(0, subtotal - discount + fee);
        deliveryFeeLabel.textContent = 'Rs. ' + fee.toFixed(2);
        grandTotalLabel.textContent = 'Rs. ' + total.toFixed(2);
    } else {
        addressGroup.style.display = 'none';
        addressInput.removeAttribute('required');
        pickupCard.style.borderColor = 'var(--primary)';
        pickupCard.style.background = 'rgba(245, 158, 11, 0.06)';
        deliveryCard.style.borderColor = 'var(--border-color)';
        deliveryCard.style.background = 'var(--bg-surface)';

        const fee = 0.00;
        const total = Math.max(0, subtotal - discount + fee);
        deliveryFeeLabel.textContent = 'Rs. ' + fee.toFixed(2);
        grandTotalLabel.textContent = 'Rs. ' + total.toFixed(2);
    }
}

function togglePaymentMethod() {
    const cardRadio = document.querySelector('input[name="payment_method"][value="CARD"]').checked;
    const btRadio = document.querySelector('input[name="payment_method"][value="BANK_TRANSFER"]').checked;
    
    document.getElementById('cardFields').style.display = cardRadio ? 'block' : 'none';
    document.getElementById('bankFields').style.display = btRadio ? 'block' : 'none';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleOrderType();
    togglePaymentMethod();
});
</script>

<style>
@media (max-width: 860px) {
    form#checkoutForm > div {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
