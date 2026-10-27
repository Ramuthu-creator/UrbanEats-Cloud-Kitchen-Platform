<?php
/**
 * UrbanEats Cloud Kitchen Platform - COMPONENT C: Shopping Cart System
 * Session-based cart with live database price/stock verification & coupon processing.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$pdo = get_db();
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];

// 1. Process Cart Actions (POST & GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;
        if ($dishId && $qty > 0) {
            $stmt = $pdo->prepare("SELECT d.*, i.current_stock FROM dishes d LEFT JOIN inventory i ON d.id = i.dish_id WHERE d.id = ? AND d.is_active = 1");
            $stmt->execute([$dishId]);
            $dish = $stmt->fetch();
            if (!$dish) set_flash('error', 'Dish is currently unavailable.');
            else {
                $stock = isset($dish['current_stock']) ? (int)$dish['current_stock'] : 999;
                $newQty = ($_SESSION['cart'][$dishId] ?? 0) + $qty;
                if ($stock <= 0) set_flash('error', "Sorry, '{$dish['name']}' is out of stock.");
                elseif ($newQty > $stock) { $_SESSION['cart'][$dishId] = $stock; set_flash('warning', "Adjusted to maximum stock ({$stock})."); }
                else { $_SESSION['cart'][$dishId] = $newQty; set_flash('success', "Added {$qty}x '{$dish['name']}' to cart."); }
            }
        }
        redirect('orders/cart.php');
    }

    if ($action === 'update') {
        $dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        if ($dishId !== null && isset($_SESSION['cart'][$dishId])) {
            if ($qty === null || $qty <= 0) { unset($_SESSION['cart'][$dishId]); set_flash('info', 'Item removed.'); }
            else {
                $stmt = $pdo->prepare("SELECT d.name, i.current_stock FROM dishes d LEFT JOIN inventory i ON d.id = i.dish_id WHERE d.id = ?");
                $stmt->execute([$dishId]);
                $dish = $stmt->fetch();
                $stock = isset($dish['current_stock']) ? (int)$dish['current_stock'] : 999;
                $_SESSION['cart'][$dishId] = ($qty > $stock) ? max(1, $stock) : $qty;
                set_flash('success', 'Cart updated.');
            }
        }
        redirect('orders/cart.php');
    }

    if ($action === 'remove') {
        $dishId = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
        if ($dishId) unset($_SESSION['cart'][$dishId]);
        redirect('orders/cart.php');
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = []; unset($_SESSION['applied_coupon']);
        set_flash('info', 'Cart cleared.'); redirect('orders/cart.php');
    }
    if ($action === 'apply_coupon') {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE UPPER(code) = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();
        if (!$coupon) set_flash('error', "Coupon '{$code}' is invalid.");
        elseif (strtotime($coupon['expires_at']) < time()) set_flash('error', "Coupon '{$code}' has expired.");
        elseif ((int)$coupon['times_used'] >= (int)$coupon['usage_limit']) set_flash('error', "Coupon limit reached.");
        else {
            $_SESSION['applied_coupon'] = ['id' => (int)$coupon['id'], 'code' => $coupon['code'], 'discount_type' => $coupon['discount_type'],
                'discount_value' => (float)$coupon['discount_value'], 'min_order_value' => (float)$coupon['min_order_value']];
            set_flash('success', "Coupon '{$coupon['code']}' applied!");
        }
        redirect('orders/cart.php');
    }
    if ($action === 'remove_coupon') {
        unset($_SESSION['applied_coupon']); set_flash('info', 'Coupon removed.'); redirect('orders/cart.php');
    }
}
if (isset($_GET['remove_id'])) {
    $dishId = filter_input(INPUT_GET, 'remove_id', FILTER_VALIDATE_INT);
    if ($dishId) unset($_SESSION['cart'][$dishId]);
    redirect('orders/cart.php');
}

// 2. Re-verify Live Cart Items Directly from Database
$cartItems = []; $subtotal = 0.00;
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT d.*, c.name AS category_name, COALESCE(i.current_stock, 0) AS current_stock
        FROM dishes d JOIN categories c ON d.category_id = c.id LEFT JOIN inventory i ON d.id = i.dish_id WHERE d.id IN ($in)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $d) {
        $id = (int)$d['id']; $qty = (int)$_SESSION['cart'][$id];
        if ((int)$d['is_active'] !== 1) { unset($_SESSION['cart'][$id]); continue; }
        $lineTotal = (float)$d['price'] * $qty; $subtotal += $lineTotal;
        $cartItems[] = ['dish' => $d, 'quantity' => $qty, 'line_total' => $lineTotal, 'stock' => (int)$d['current_stock']];
    }
}

// 3. Re-verify Coupon Discount
$couponDiscount = 0.00; $appliedCoupon = $_SESSION['applied_coupon'] ?? null;
if ($appliedCoupon) {
    if ($subtotal >= (float)$appliedCoupon['min_order_value']) {
        $couponDiscount = ($appliedCoupon['discount_type'] === 'PERCENTAGE')
            ? round(($subtotal * (float)$appliedCoupon['discount_value']) / 100, 2) : min((float)$appliedCoupon['discount_value'], $subtotal);
    } else {
        unset($_SESSION['applied_coupon']);
        set_flash('warning', "Coupon removed: Minimum subtotal required is " . format_price($appliedCoupon['min_order_value']) . ".");
    }
}
$estimatedTotal = max(0, $subtotal - $couponDiscount);
$pageTitle = 'Shopping Cart - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1100px; margin-top: 1rem; margin-bottom: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-customer">Fresh Orders &bull; Dining Cart</span>
            <h1 style="margin: 0.3rem 0; font-size: 2.2rem;">Your Dining Cart 🛒</h1>
            <p style="color:var(--text-muted); margin:0;">Review chosen dishes, adjust quantities, or enter promotional coupons.</p>
        </div>
        <a href="<?= base_url('menu/index.php') ?>" class="btn btn-secondary btn-sm">&larr; Back to Menu</a>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🍽️</div>
            <h2>Your Cart is Currently Empty</h2>
            <p style="color: var(--text-secondary); max-width: 450px; margin: 0 auto 1.5rem;">Explore authentic biryanis, hot kottu, and chef specials on our menu.</p>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary">Browse Menu &rarr;</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 2rem; align-items: start;">
            <!-- Left: Items Table -->
            <div>
                <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 1rem;">
                    <table class="table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Dish</th><th style="text-align:center;">Price</th><th style="text-align:center; width:130px;">Quantity</th>
                                <th style="text-align:right;">Subtotal</th><th style="text-align:center; width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $it): $dish = $it['dish']; ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:0.75rem;">
                                            <?php if (!empty($dish['image_url'])): ?>
                                                <img src="<?= base_url('public/uploads/' . htmlspecialchars($dish['image_url'])) ?>" alt="<?= e($dish['name']) ?>" style="width:50px; height:50px; border-radius:6px; object-fit:cover;">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= e($dish['name']) ?></strong>
                                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= e($dish['category_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align:center;"><?= format_price((float)$dish['price']) ?></td>
                                    <td style="text-align:center;">
                                        <form method="POST" action="<?= base_url('orders/cart.php') ?>" style="display:inline-flex; align-items:center; gap:4px;">
                                            <?= csrf_input() ?><input type="hidden" name="action" value="update"><input type="hidden" name="dish_id" value="<?= $dish['id'] ?>">
                                            <input type="number" name="quantity" value="<?= $it['quantity'] ?>" min="1" max="<?= $it['stock'] ?>" class="form-control" style="width:60px; padding:4px 6px; text-align:center;" onchange="this.form.submit();">
                                        </form>
                                    </td>
                                    <td style="text-align:right; font-weight:700; color:var(--primary);"><?= format_price($it['line_total']) ?></td>
                                    <td style="text-align:center;">
                                        <form method="POST" action="<?= base_url('orders/cart.php') ?>" style="display:inline;">
                                            <?= csrf_input() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="dish_id" value="<?= $dish['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding:2px 8px;" title="Remove">&times;</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <form method="POST" action="<?= base_url('orders/cart.php') ?>">
                        <?= csrf_input() ?><input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Clear all items from your cart?');">🗑️ Clear Cart</button>
                    </form>
                    <a href="<?= base_url('menu/index.php') ?>" class="btn btn-secondary btn-sm">➕ Add More Dishes</a>
                </div>
            </div>

            <!-- Right: Coupon & Checkout Summary -->
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Coupon Box -->
                <div class="card" style="padding:1.25rem;">
                    <h3 style="font-size:1.05rem; margin-bottom:0.75rem;">🏷️ Have a Coupon?</h3>
                    <?php if ($appliedCoupon): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface); padding:0.6rem 0.85rem; border-radius:6px; border:1px solid var(--border-color);">
                            <div>
                                <span class="badge badge-success"><?= e($appliedCoupon['code']) ?></span>
                                <span style="font-size:0.8rem; color:var(--success); margin-left:6px;">Applied</span>
                            </div>
                            <form method="POST" action="<?= base_url('orders/cart.php') ?>" style="display:inline;">
                                <?= csrf_input() ?><input type="hidden" name="action" value="remove_coupon">
                                <button type="submit" class="btn btn-secondary btn-sm" style="padding:2px 6px; font-size:0.75rem;">Remove</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="<?= base_url('orders/cart.php') ?>" style="display:flex; gap:0.5rem;">
                            <?= csrf_input() ?><input type="hidden" name="action" value="apply_coupon">
                            <input type="text" name="coupon_code" class="form-control" placeholder="Promo code (e.g. WELCOME10)" style="text-transform:uppercase; font-family:monospace;" required>
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Totals Card -->
                <div class="card" style="padding:1.5rem;">
                    <h3 style="font-size:1.15rem; margin-bottom:1rem;">Order Summary</h3>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem; color:var(--text-secondary); font-size:0.9rem;">
                        <span>Subtotal</span><strong><?= format_price($subtotal) ?></strong>
                    </div>
                    <?php if ($couponDiscount > 0): ?>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem; color:var(--success); font-size:0.9rem;">
                            <span>Discount (<?= e($appliedCoupon['code']) ?>)</span><strong>&minus; <?= format_price($couponDiscount) ?></strong>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.8rem; color:var(--text-muted); font-size:0.8rem;">
                        <span>Delivery Fee</span><span>Calculated at checkout</span>
                    </div>
                    <hr style="border:0; border-top:1px solid var(--border-color); margin:0.5rem 0 0.8rem;">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:1.25rem;">
                        <span style="font-size:1.1rem; font-weight:700;">Subtotal Total</span>
                        <span style="font-size:1.5rem; font-weight:800; color:var(--primary);"><?= format_price($estimatedTotal) ?></span>
                    </div>
                    <a href="<?= base_url('orders/checkout.php') ?>" class="btn btn-primary btn-lg" style="width:100%; text-align:center;">Proceed to Checkout 🚀</a>
                    <div style="font-size:0.75rem; color:var(--text-muted); text-align:center; margin-top:0.75rem;">🔒 Secure checkout guaranteed &bull; Cash, Card, Bank Deposit</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>