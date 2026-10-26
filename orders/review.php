<?php
/**
 * UrbanEats Cloud Kitchen Platform - Component C: Customer Ratings & Reviews
 * Enables 1-5 star ratings and comments for food items received in completed orders.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = get_db();
$userId = current_user_id();

// Dynamic table check for SQLite/MySQL compatibility
if (!$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'reviews'")->fetchColumn()) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, dish_id INT NOT NULL, user_id INT NOT NULL,
        rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5), comment TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_rev_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        CONSTRAINT fk_rev_dish FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE,
        CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY uq_rev (order_id, dish_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// 1. Process Review Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    if (!verify_csrf_token()) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect('orders/review.php' . (!empty($_POST['order_id']) ? '?order_id=' . (int)$_POST['order_id'] : ''));
    }
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $dishId  = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
    $rating  = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $comment = trim($_POST['comment'] ?? '');

    if (!$orderId || !$dishId || !$rating || $rating < 1 || $rating > 5) {
        set_flash('error', 'Please choose a rating between 1 and 5 stars.');
        redirect("orders/review.php?order_id={$orderId}");
    }
    // Verify order is completed and belongs to current customer
    $oStmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE id = ? AND user_id = ? AND status = 'COMPLETED'");
    $oStmt->execute([$orderId, $userId]);
    $order = $oStmt->fetch();
    if (!$order) {
        set_flash('error', 'Reviews are only permitted for delivered, completed orders.');
        redirect('orders/history.php');
    }
    // Verify dish belongs to this order
    $dStmt = $pdo->prepare("SELECT d.name FROM order_items oi JOIN dishes d ON oi.dish_id = d.id WHERE oi.order_id = ? AND oi.dish_id = ?");
    $dStmt->execute([$orderId, $dishId]);
    $dishName = $dStmt->fetchColumn();

    if ($dishName) {
        $chk = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ? AND dish_id = ? AND user_id = ?");
        $chk->execute([$orderId, $dishId, $userId]);
        $revId = $chk->fetchColumn();
        if ($revId) {
            $pdo->prepare("UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?")->execute([$rating, $comment ?: null, $revId]);
            set_flash('success', "Updated your {$rating}-star review for " . e($dishName) . ".");
        } else {
            $pdo->prepare("INSERT INTO reviews (order_id, dish_id, user_id, rating, comment) VALUES (?, ?, ?, ?, ?)")->execute([$orderId, $dishId, $userId, $rating, $comment ?: null]);
            set_flash('success', "Thank you! Submitted your {$rating}-star review for " . e($dishName) . ".");
        }
        if (function_exists('log_action')) log_action('REVIEW_SUBMITTED', "User {$userId} rated dish {$dishId} ({$rating} stars)", $userId);
    }
    redirect("orders/review.php?order_id={$orderId}");
}

// 2. Fetch Active Order or Past Reviews Data
$targetOrderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$singleOrder = null;
$reviewableDishes = [];

if ($targetOrderId) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$targetOrderId, $userId]);
    $singleOrder = $stmt->fetch();
    if ($singleOrder) {
        $stmt2 = $pdo->prepare("SELECT oi.*, d.name AS dish_name, d.image_url, c.name AS category_name, r.id AS review_id, r.rating, r.comment, r.created_at AS reviewed_at
            FROM order_items oi JOIN dishes d ON oi.dish_id = d.id LEFT JOIN categories c ON d.category_id = c.id
            LEFT JOIN reviews r ON (r.order_id = oi.order_id AND r.dish_id = oi.dish_id AND r.user_id = ?)
            WHERE oi.order_id = ? ORDER BY d.name ASC");
        $stmt2->execute([$userId, $targetOrderId]);
        $reviewableDishes = $stmt2->fetchAll();
    }
}

// Completed orders list & user's past reviews
$completedOrders = $pdo->prepare("SELECT o.id, o.order_number, COUNT(DISTINCT oi.dish_id) AS total_dishes, COUNT(DISTINCT r.dish_id) AS reviewed_dishes
    FROM orders o JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN reviews r ON (r.order_id = o.id AND r.dish_id = oi.dish_id AND r.user_id = o.user_id)
    WHERE o.user_id = ? AND o.status = 'COMPLETED' GROUP BY o.id ORDER BY o.created_at DESC");
$completedOrders->execute([$userId]);
$completedOrders = $completedOrders->fetchAll();

$pastReviews = $pdo->prepare("SELECT r.*, d.name AS dish_name, d.image_url, o.order_number, c.name AS category_name
    FROM reviews r JOIN dishes d ON r.dish_id = d.id JOIN orders o ON r.order_id = o.id
    LEFT JOIN categories c ON d.category_id = c.id WHERE r.user_id = ? ORDER BY r.created_at DESC");
$pastReviews->execute([$userId]);
$pastReviews = $pastReviews->fetchAll();

$pageTitle = 'Rate & Review Your Meals - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1050px; margin-top: 1.5rem; margin-bottom: 3rem;">
    <!-- Page Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <span class="badge badge-customer">Customer Feedback &bull; Ratings</span>
            <h1 style="margin:0.3rem 0; font-size:2rem;">⭐ Rate &amp; Review Your Dishes</h1>
            <p style="color:var(--text-muted); margin:0;">Share your authentic 1–5 star ratings and reviews for meals you enjoyed.</p>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <a href="<?= base_url('orders/history.php') ?>" class="btn btn-secondary btn-sm">&larr; Order History</a>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary btn-sm">🍽️ Menu</a>
        </div>
    </div>

    <!-- Completed Orders Navigation Pills -->
    <?php if (!empty($completedOrders)): ?>
        <div class="card" style="padding:0.9rem 1.25rem; margin-bottom:1.5rem;">
            <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.4rem;">Select Order to Rate:</div>
            <div style="display:flex; flex-wrap:wrap; gap:0.4rem;">
                <?php foreach ($completedOrders as $co): ?>
                    <a href="<?= base_url('orders/review.php?order_id=' . $co['id']) ?>" class="order-selector-pill <?= ($targetOrderId == $co['id']) ? 'active' : '' ?>">
                        <?= e($co['order_number']) ?> (<?= (int)$co['reviewed_dishes'] ?>/<?= (int)$co['total_dishes'] ?> Rated)
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($targetOrderId && $singleOrder): ?>
        <div class="card" style="padding:1rem 1.5rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <strong style="color:var(--primary); font-family:monospace; font-size:1.15rem;"><?= e($singleOrder['order_number']) ?></strong>
                <span style="color:var(--text-muted); font-size:0.85rem; margin-left:8px;">Delivered on <?= format_datetime($singleOrder['created_at']) ?></span>
            </div>
            <a href="<?= base_url('orders/confirmation.php?id=' . $singleOrder['id']) ?>" class="btn btn-secondary btn-sm">📄 View Invoice</a>
        </div>

        <?php if ($singleOrder['status'] !== 'COMPLETED'): ?>
            <div class="card" style="text-align:center; padding:3rem 1.5rem;">
                <h3>🍳 Order In Progress</h3>
                <p style="color:var(--text-muted);">You can review your food once delivery is completed.</p>
                <a href="<?= base_url('orders/track.php?order_number=' . urlencode($singleOrder['order_number'])) ?>" class="btn btn-primary">📍 Track Order</a>
            </div>
        <?php else: ?>
            <?php foreach ($reviewableDishes as $item): 
                $hasRev = !empty($item['review_id']);
                $rating = $hasRev ? (int)$item['rating'] : 5;
            ?>
                <div class="card" style="padding:1.5rem; margin-bottom:1.25rem;">
                    <div class="dish-review-row">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= base_url('public/uploads/' . htmlspecialchars($item['image_url'])) ?>" alt="<?= e($item['dish_name']) ?>" class="dish-thumb">
                        <?php else: ?>
                            <div class="dish-thumb" style="display:flex; align-items:center; justify-content:center; color:#777; font-size:1.5rem;">🍛</div>
                        <?php endif; ?>
                        <div style="flex:1; min-width:250px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem;">
                                <div>
                                    <h3 style="margin:0 0 0.25rem; font-size:1.25rem;"><?= e($item['dish_name']) ?></h3>
                                    <span style="font-size:0.75rem; color:var(--primary); text-transform:uppercase; font-weight:700;"><?= e($item['category_name'] ?? 'Main Course') ?></span>
                                    <span style="font-size:0.8rem; color:var(--text-muted); margin-left:8px;"><?= (int)$item['quantity'] ?> portion(s) &bull; <?= format_price((float)$item['unit_price']) ?></span>
                                </div>
                                <?php if ($hasRev): ?>
                                    <span class="badge badge-success" style="font-size:0.8rem;">★ <?= $rating ?> / 5 Reviewed</span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" action="<?= base_url('orders/review.php') ?>" style="margin-top:1rem;">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="submit_review">
                                <input type="hidden" name="order_id" value="<?= (int)$targetOrderId ?>">
                                <input type="hidden" name="dish_id" value="<?= (int)$item['dish_id'] ?>">
                                
                                <label style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.25rem;">Rating (1 to 5 Stars):</label>
                                <div class="star-rating-group">
                                    <?php for ($s = 5; $s >= 1; $s--): ?>
                                        <input type="radio" id="star-<?= (int)$item['dish_id'] ?>-<?= $s ?>" name="rating" value="<?= $s ?>" <?= ($rating === $s) ? 'checked' : '' ?> required>
                                        <label for="star-<?= (int)$item['dish_id'] ?>-<?= $s ?>" title="<?= $s ?> Stars">★</label>
                                    <?php endfor; ?>
                                </div>
                                <div style="margin-bottom:0.75rem;">
                                    <textarea name="comment" rows="2" class="review-textarea" placeholder="How was the flavor, temperature and spices?"><?= e($item['comment'] ?? '') ?></textarea>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <button type="submit" class="btn btn-primary btn-sm"><?= $hasRev ? '✏️ Update Review' : '⭐ Submit Review' ?></button>
                                    <a href="<?= base_url('menu/detail.php?id=' . (int)$item['dish_id']) ?>" target="_blank" style="color:var(--primary); font-size:0.8rem;">View on menu &rarr;</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>
        <div class="card" style="text-align:center; padding:2.5rem 1.5rem; margin-bottom:2rem;">
            <div style="font-size:2.8rem; margin-bottom:0.5rem;">🌟</div>
            <h2 style="margin:0 0 0.5rem;">Rate Your Completed Meals</h2>
            <p style="color:var(--text-muted); max-width:500px; margin:0 auto 1.5rem;">Select any order from your history to evaluate the food dishes you received.</p>
            <a href="<?= base_url('orders/history.php?status=COMPLETED') ?>" class="btn btn-primary">📜 Completed Orders &rarr;</a>
        </div>
        <?php if (!empty($pastReviews)): ?>
            <h3 style="color:var(--primary); margin-bottom:1rem; font-size:1.25rem;">Your Past Reviews (<?= count($pastReviews) ?>)</h3>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:1rem;">
                <?php foreach ($pastReviews as $rev): ?>
                    <div class="card" style="padding:1.25rem;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.4rem;">
                            <strong style="color:var(--text-primary); font-size:1.05rem;"><?= e($rev['dish_name']) ?></strong>
                            <span style="color:var(--primary); font-weight:700;">★ <?= (int)$rev['rating'] ?>/5</span>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.6rem;"><?= e($rev['order_number']) ?> &bull; <?= date('M d, Y', strtotime($rev['created_at'])) ?></div>
                        <?php if (!empty($rev['comment'])): ?>
                            <div style="font-size:0.85rem; color:var(--text-secondary); background:var(--bg-surface); padding:0.6rem; border-radius:6px; font-style:italic; margin-bottom:0.75rem;">
                                "<?= e($rev['comment']) ?>"
                            </div>
                        <?php endif; ?>
                        <div style="display:flex; justify-content:space-between;">
                            <a href="<?= base_url('orders/review.php?order_id=' . (int)$rev['order_id']) ?>" class="btn btn-secondary btn-sm" style="font-size:0.75rem;">✏️ Edit</a>
                            <a href="<?= base_url('menu/detail.php?id=' . (int)$rev['dish_id']) ?>" class="btn btn-primary btn-sm" style="font-size:0.75rem;">View Dish</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
