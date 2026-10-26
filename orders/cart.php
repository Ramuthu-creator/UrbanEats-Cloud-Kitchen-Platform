<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Shopping Cart System
 * Primary Owner: Student 3
 *
 * Implements:
 * - Session-based cart with running totals and database price re-verification
 * - Real-time stock validation (never trusts prices or availability from browser)
 * - Coupon validation & discount computation
 * - Quantity increments, decrements, item deletion, and cart clearing
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db();

// Initialize cart in session if not present
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


// 1. PROCESS CART ACTIONS (POST & GET)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;

        if ($dishId && $qty > 0) {
            // Verify dish exists and is active
            $stmt = $pdo->prepare("SELECT d.*, i.current_stock FROM dishes d LEFT JOIN inventory i ON d.id = i.dish_id WHERE d.id = ? AND d.is_active = 1");
            $stmt->execute([$dishId]);
            $dish = $stmt->fetch();

            if (!$dish) {
                set_flash('error', 'Dish is currently unavailable or does not exist.');
            } else {
                $stock = isset($dish['current_stock']) ? (int) $dish['current_stock'] : 999;
                $currentCartQty = $_SESSION['cart'][$dishId] ?? 0;
                $newQty = $currentCartQty + $qty;

                if ($stock <= 0) {
                    set_flash('error', "Sorry, '{$dish['name']}' is currently out of stock.");
                } elseif ($newQty > $stock) {
                    $_SESSION['cart'][$dishId] = $stock;
                    set_flash('warning', "Only {$stock} portion(s) available for '{$dish['name']}'. Quantity adjusted to maximum available.");
                } else {
                    $_SESSION['cart'][$dishId] = $newQty;
                    set_flash('success', "Added {$qty}x '{$dish['name']}' to your cart.");
                }
            }
        }
        redirect('orders/cart.php');
    }

    if ($action === 'update') {
        $dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

        if ($dishId !== null && isset($_SESSION['cart'][$dishId])) {
            if ($qty === null || $qty <= 0) {
                unset($_SESSION['cart'][$dishId]);
                set_flash('info', 'Item removed from your cart.');
            } else {
                // Check stock
                $stmt = $pdo->prepare("SELECT d.name, i.current_stock FROM dishes d LEFT JOIN inventory i ON d.id = i.dish_id WHERE d.id = ?");
                $stmt->execute([$dishId]);
                $dish = $stmt->fetch();
                $stock = isset($dish['current_stock']) ? (int) $dish['current_stock'] : 999;

                if ($qty > $stock) {
                    $_SESSION['cart'][$dishId] = max(1, $stock);
                    set_flash('warning', "Only {$stock} portion(s) of '{$dish['name']}' in stock. Quantity updated to {$stock}.");
                } else {
                    $_SESSION['cart'][$dishId] = $qty;
                    set_flash('success', "Cart updated.");
                }
            }
        }
        redirect('orders/cart.php');
    }

    if ($action === 'remove') {
        $dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
        if ($dishId && isset($_SESSION['cart'][$dishId])) {
            unset($_SESSION['cart'][$dishId]);
            set_flash('info', 'Item removed from your cart.');
        }
        redirect('orders/cart.php');
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        unset($_SESSION['applied_coupon']);
        set_flash('info', 'Your cart has been cleared.');
        redirect('orders/cart.php');
    }

    if ($action === 'apply_coupon') {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        if (empty($code)) {
            set_flash('error', 'Please enter a valid coupon code.');
            redirect('orders/cart.php');
        }

        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();

        if (!$coupon) {
            set_flash('error', "Coupon code '{$code}' is invalid or has expired.");
        } elseif (strtotime($coupon['expires_at']) < time()) {
            set_flash('error', "Coupon code '{$code}' expired on " . date('d M Y', strtotime($coupon['expires_at'])) . ".");
        } elseif ((int) $coupon['times_used'] >= (int) $coupon['usage_limit']) {
            set_flash('error', "Coupon code '{$code}' has reached its maximum usage limit.");
        } else {
            $_SESSION['applied_coupon'] = [
                'id' => (int) $coupon['id'],
                'code' => $coupon['code'],
                'discount_type' => $coupon['discount_type'],
                'discount_value' => (float) $coupon['discount_value'],
                'min_order_value' => (float) $coupon['min_order_value']
            ];
            set_flash('success', "Coupon '{$coupon['code']}' applied successfully!");
        }
        redirect('orders/cart.php');
    }

    if ($action === 'remove_coupon') {
        unset($_SESSION['applied_coupon']);
        set_flash('info', 'Coupon removed.');
        redirect('orders/cart.php');
    }
}

// Support GET quick-remove
if (isset($_GET['remove_id'])) {
    $dishId = filter_input(INPUT_GET, 'remove_id', FILTER_VALIDATE_INT);
    if ($dishId && isset($_SESSION['cart'][$dishId])) {
        unset($_SESSION['cart'][$dishId]);
        set_flash('info', 'Item removed from cart.');
    }
    redirect('orders/cart.php');
}
// 2. RE-VERIFY CART ITEMS DIRECTLY FROM DATABASE (BACKEND INTEGRITY)
$cartItems = [];
$subtotal = 0.00;
$hasStockIssue = false;

if (!empty($_SESSION['cart'])) {
    $dishIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($dishIds), '?'));

    $sql = "SELECT d.id, d.name, d.price, d.image_url, d.is_active, c.name AS category_name,
                   COALESCE(i.current_stock, 0) AS current_stock
            FROM dishes d
            JOIN categories c ON d.category_id = c.id
            LEFT JOIN inventory i ON d.id = i.dish_id
            WHERE d.id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dishIds);
    $dishes = $stmt->fetchAll();

    $foundIds = [];
    foreach ($dishes as $dish) {
        $dishId = (int) $dish['id'];
        $foundIds[] = $dishId;
        $qty = (int) $_SESSION['cart'][$dishId];

        // If dish was deactivated, prune from cart
        if ((int) $dish['is_active'] !== 1) {
            unset($_SESSION['cart'][$dishId]);
            set_flash('warning', "'{$dish['name']}' is no longer available and was removed from your cart.");
            continue;
        }

        $stock = (int) $dish['current_stock'];
        $isStockOut = ($stock <= 0);
        $isStockLimited = ($qty > $stock && $stock > 0);

        if ($isStockOut || $isStockLimited) {
            $hasStockIssue = true;
        }

        $price = (float) $dish['price'];
        $lineTotal = $price * $qty;
        $subtotal += $lineTotal;

        $cartItems[] = [
            'id' => $dishId,
            'name' => $dish['name'],
            'category_name' => $dish['category_name'],
            'price' => $price,
            'image_url' => $dish['image_url'],
            'quantity' => $qty,
            'stock' => $stock,
            'line_total' => $lineTotal,
            'stock_error' => $isStockOut ? 'Out of stock' : ($isStockLimited ? "Only {$stock} available" : null)
        ];
    }

    // Prune any dishes that no longer exist in database
    foreach ($dishIds as $id) {
        if (!in_array($id, $foundIds)) {
            unset($_SESSION['cart'][$id]);
        }
    }
}
// 3. COUPON & TOTALS CALCULATION
$couponDiscount = 0.00;
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
$couponError = null;

if ($appliedCoupon && $subtotal > 0) {
    if ($subtotal < $appliedCoupon['min_order_value']) {
        $couponError = "Minimum order value of " . format_price($appliedCoupon['min_order_value']) . " required for coupon {$appliedCoupon['code']}.";
    } else {
        if ($appliedCoupon['discount_type'] === 'PERCENTAGE') {
            $couponDiscount = round(($subtotal * $appliedCoupon['discount_value']) / 100, 2);
        } else {
            $couponDiscount = min((float) $appliedCoupon['discount_value'], $subtotal);
        }
    }
}

$estimatedDeliveryFee = ($subtotal > 0) ? 350.00 : 0.00;
$finalTotal = max(0, $subtotal - $couponDiscount + $estimatedDeliveryFee);

$pageTitle = 'Shopping Cart - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1150px; margin-top: 1rem;">
    <!-- Page Header -->
    <div
        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-customer">Component C &bull; Ordering System</span>
            <h1 style="margin-top: 0.4rem; font-size: 2.2rem;">Your Shopping Cart</h1>
            <p style="margin-bottom: 0;">Review your selected dishes, apply discount coupons, and proceed to checkout.
            </p>
        </div>
        <div>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-secondary">
                🍽️ Browse More Dishes
            </a>
        </div>
    </div>

    <?php if (empty($cartItems)): ?>
        <!-- Empty Cart State -->
        <div class="card"
            style="text-align: center; padding: 4rem 2rem; margin: 2rem 0; border: 1px dashed var(--border-color);">
            <div style="font-size: 4.5rem; margin-bottom: 1rem; opacity: 0.85;">🛒</div>
            <h2 style="margin-bottom: 0.75rem;">Your Cart is Hungry!</h2>
            <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 2rem; font-size: 1.05rem;">
                Looks like you haven't added any of our authentic biryanis, kottus, or signature village curries to your
                cart yet.
            </p>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary btn-lg">
                Explore UrbanEats Menu &rarr;
            </a>
        </div>
    <?php else: ?>
        <!-- Active Cart Layout (Grid: Items Table + Summary Card) -->
        <div style="display: grid; grid-template-columns: 1fr 360px; gap: 2rem; align-items: start;">

            <!-- Left Column: Cart Items -->
            <div>
                <?php if ($hasStockIssue): ?>
                    <div class="alert alert-warning" role="alert" style="margin-bottom: 1.25rem;">
                        <span class="alert-icon">⚠️</span>
                        <span class="alert-text">One or more items in your cart exceed our current kitchen stock. Please adjust
                            quantities before checkout.</span>
                    </div>
                <?php endif; ?>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div class="table-responsive">
                        <table class="table" style="margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Dish</th>
                                    <th style="text-align: center;">Price</th>
                                    <th style="text-align: center; width: 140px;">Quantity</th>
                                    <th style="text-align: right;">Total</th>
                                    <th style="text-align: center; width: 60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 1rem;">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?= asset('uploads/' . htmlspecialchars(basename($item['image_url']))) ?>"
                                                        alt="<?= e($item['name']) ?>"
                                                        style="width: 58px; height: 58px; border-radius: var(--radius-md); object-fit: cover; border: 1px solid var(--border-color); flex-shrink: 0;"
                                                        onerror="this.style.display='none';">
                                                <?php else: ?>
                                                    <div
                                                        style="width: 58px; height: 58px; border-radius: var(--radius-md); background: var(--bg-surface); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                                                        🍲
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="<?= base_url('menu/detail.php?id=' . $item['id']) ?>"
                                                        style="font-weight: 600; color: var(--text-primary); font-size: 1.05rem;">
                                                        <?= e($item['name']) ?>
                                                    </a>
                                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                        <?= e($item['category_name']) ?></div>
                                                    <?php if ($item['stock_error']): ?>
                                                        <span class="badge badge-danger"
                                                            style="font-size: 0.7rem; margin-top: 0.25rem;">
                                                            <?= e($item['stock_error']) ?>
                                                        </span>
                                                    <?php elseif ($item['stock'] <= 5): ?>
                                                        <span class="badge badge-warning"
                                                            style="font-size: 0.7rem; margin-top: 0.25rem;">
                                                            Only <?= $item['stock'] ?> left in stock
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center; font-weight: 500; white-space: nowrap;">
                                            <?= format_price($item['price']) ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <form method="POST" action="<?= base_url('orders/cart.php') ?>"
                                                style="display: inline-flex; align-items: center; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-sm); overflow: hidden;">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="dish_id" value="<?= $item['id'] ?>">

                                                <!-- Decrement button -->
                                                <button type="submit" name="quantity"
                                                    value="<?= max(0, $item['quantity'] - 1) ?>" class="btn"
                                                    style="padding: 0.35rem 0.65rem; background: transparent; border: none; font-size: 1rem; color: var(--text-secondary); cursor: pointer;"
                                                    title="Decrease quantity">
                                                    &minus;
                                                </button>

                                                <span
                                                    style="min-width: 32px; text-align: center; font-weight: 600; font-size: 0.95rem;">
                                                    <?= $item['quantity'] ?>
                                                </span>

                                                <!-- Increment button -->
                                                <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>"
                                                    class="btn"
                                                    style="padding: 0.35rem 0.65rem; background: transparent; border: none; font-size: 1rem; color: var(--primary); cursor: pointer;"
                                                    title="Increase quantity">
                                                    +
                                                </button>
                                            </form>
                                        </td>
                                        <td
                                            style="text-align: right; font-weight: 700; color: var(--primary); white-space: nowrap;">
                                            <?= format_price($item['line_total']) ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <form method="POST" action="<?= base_url('orders/cart.php') ?>"
                                                onsubmit="return confirm('Remove <?= e(addslashes($item['name'])) ?> from cart?');">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="dish_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-sm"
                                                    style="background: transparent; border: none; color: var(--error); cursor: pointer; font-size: 1.1rem; padding: 0.2rem;"
                                                    title="Remove item">
                                                    🗑️
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div
                        style="padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); background: rgba(0,0,0,0.1); flex-wrap: wrap; gap: 1rem;">
                        <form method="POST" action="<?= base_url('orders/cart.php') ?>"
                            onsubmit="return confirm('Clear all items from your cart?');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-danger btn-sm">
                                🗑️ Clear Cart
                            </button>
                        </form>
                        <a href="<?= base_url('menu/index.php') ?>" class="btn btn-secondary btn-sm">
                            &larr; Continue Ordering
                        </a>
                    </div>
                </div>

                <!-- Kitchen Prep Note Banner -->
                <div
                    style="margin-top: 1.5rem; background: var(--bg-card); border-radius: var(--radius-md); padding: 1.25rem; border: 1px solid var(--border-color); display: flex; gap: 1rem; align-items: center;">
                    <div style="font-size: 2rem;">👨‍🍳</div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
                        <strong>Fresh Cloud Kitchen Preparation:</strong> Every dish is prepared fresh upon order placement
                        by our specialized chefs in Colombo. Prices are verified directly against our live inventory.
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Summary & Coupon -->
            <div>
                <!-- Coupon Redemption Card -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.15rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        🎟️ Promo / Coupon Code
                    </h3>

                    <?php if ($appliedCoupon): ?>
                        <div
                            style="background: rgba(16, 185, 129, 0.12); border: 1px solid var(--success); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong
                                        style="color: var(--success); font-size: 1.1rem; letter-spacing: 0.05em;"><?= e($appliedCoupon['code']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                        <?= $appliedCoupon['discount_type'] === 'PERCENTAGE' ? "{$appliedCoupon['discount_value']}% Off Order" : "Rs. " . number_format($appliedCoupon['discount_value'], 2) . " Flat Discount" ?>
                                    </div>
                                </div>
                                <form method="POST" action="<?= base_url('orders/cart.php') ?>">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="remove_coupon">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">
                                        Remove ✕
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($couponError): ?>
                        <div class="alert alert-warning"
                            style="margin-bottom: 1rem; font-size: 0.825rem; padding: 0.6rem 0.75rem;">
                            <span class="alert-icon">⚠️</span>
                            <span class="alert-text"><?= e($couponError) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$appliedCoupon): ?>
                        <form method="POST" action="<?= base_url('orders/cart.php') ?>" style="display: flex; gap: 0.5rem;">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="apply_coupon">
                            <input type="text" name="coupon_code" placeholder="e.g. WELCOME10" required
                                style="text-transform: uppercase; font-family: monospace; font-weight: 600; flex: 1; padding: 0.65rem 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-primary);">
                            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1rem;">
                                Apply
                            </button>
                        </form>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">
                            Try <code>WELCOME10</code> (10% off min Rs. 1500) or <code>FLAT500</code> (Rs. 500 off min Rs. 3000)
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Order Totals Card -->
                <div class="card">
                    <h3 style="font-size: 1.25rem; margin-bottom: 1.25rem;">Order Summary</h3>

                    <div
                        style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: var(--text-secondary);">
                        <span>Items Subtotal</span>
                        <strong style="color: var(--text-primary);"><?= format_price($subtotal) ?></strong>
                    </div>

                    <?php if ($couponDiscount > 0): ?>
                        <div
                            style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: var(--success);">
                            <span>Coupon Discount (<?= e($appliedCoupon['code']) ?>)</span>
                            <strong>&minus; <?= format_price($couponDiscount) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div
                        style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: var(--text-secondary);">
                        <span>Estimated Delivery</span>
                        <strong style="color: var(--text-primary);"><?= format_price($estimatedDeliveryFee) ?></strong>
                    </div>

                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem;">
                        * Select Delivery or Pickup on checkout. Pickup has zero delivery charge.
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1rem 0;">

                    <div
                        style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.75rem;">
                        <span style="font-size: 1.15rem; font-weight: 600;">Estimated Total</span>
                        <span style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">
                            <?= format_price($finalTotal) ?>
                        </span>
                    </div>

                    <?php if ($hasStockIssue): ?>
                        <button type="button" class="btn btn-secondary btn-lg"
                            style="width: 100%; cursor: not-allowed; opacity: 0.6;" disabled>
                            Adjust Quantities to Proceed
                        </button>
                    <?php else: ?>
                        <a href="<?= base_url('orders/checkout.php') ?>" class="btn btn-primary btn-lg"
                            style="width: 100%; text-align: center;">
                            Proceed to Checkout 💳 &rarr;
                        </a>
                    <?php endif; ?>

                    <div
                        style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.78rem; color: var(--text-muted);">
                        <div>🔒 256-bit Secure Session Processing</div>
                        <div>⚡ Real-time Kitchen Order Routing</div>
                        <div>💵 Cash on Delivery, Card & Bank Transfer</div>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>
</div>

<style>
    /* Responsive layout adjustments */
    @media (max-width: 860px) {
        div[style*="grid-template-columns: 1fr 360px"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>