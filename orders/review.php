<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Customer Dish Ratings & Reviews
 *
 * Implements:
 * - 1 to 5 Star Ratings for dishes received in completed orders
 * - Customer comments & culinary feedback
 * - Updates existing review or inserts new review
 * - Visual rating summary with interactive star selection
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pdo = get_db();
$userId = current_user_id();
$currentUser = current_user();

// Ensure reviews table exists dynamically
function ensure_reviews_table(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                dish_id INT NOT NULL,
                user_id INT NOT NULL,
                rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
                comment TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
                CONSTRAINT fk_reviews_dish FOREIGN KEY (dish_id) REFERENCES dishes (id) ON DELETE CASCADE,
                CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                UNIQUE KEY uq_order_dish_user (order_id, dish_id, user_id),
                INDEX idx_reviews_dish (dish_id),
                INDEX idx_reviews_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                dish_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                rating INTEGER NOT NULL,
                comment TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders (id),
                FOREIGN KEY (dish_id) REFERENCES dishes (id),
                FOREIGN KEY (user_id) REFERENCES users (id),
                UNIQUE(order_id, dish_id, user_id)
            );
        ");
    }
    $checked = true;
}

ensure_reviews_table($pdo);

// -------------------------------------------------------------
// Handle POST: Submit or Update Review
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    if (!verify_csrf()) {
        set_flash('error', 'Security token expired. Please try submitting your review again.');
        redirect('orders/review.php' . (!empty($_POST['order_id']) ? '?order_id=' . (int)$_POST['order_id'] : ''));
    }

    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $dishId  = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
    $rating  = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $comment = trim($_POST['comment'] ?? '');

    if (!$orderId || !$dishId || !$rating || $rating < 1 || $rating > 5) {
        set_flash('error', 'Please select a valid star rating between 1 and 5 stars.');
        redirect("orders/review.php?order_id={$orderId}");
    }

    if (mb_strlen($comment) > 1000) {
        set_flash('error', 'Review comments must be under 1,000 characters.');
        redirect("orders/review.php?order_id={$orderId}");
    }

    // Verify order exists, belongs to this user, and is COMPLETED
    $checkOrderStmt = $pdo->prepare("
        SELECT id, order_number, status 
        FROM orders 
        WHERE id = ? AND user_id = ?
    ");
    $checkOrderStmt->execute([$orderId, $userId]);
    $order = $checkOrderStmt->fetch();

    if (!$order) {
        set_flash('error', 'Order not found or access denied.');
        redirect('orders/history.php');
    }

    if ($order['status'] !== 'COMPLETED') {
        set_flash('error', 'You can only rate dishes once the order has been delivered and completed.');
        redirect("orders/review.php?order_id={$orderId}");
    }

    // Verify dish was actually in this order
    $checkItemStmt = $pdo->prepare("
        SELECT oi.id, d.name AS dish_name 
        FROM order_items oi
        JOIN dishes d ON oi.dish_id = d.id
        WHERE oi.order_id = ? AND oi.dish_id = ?
    ");
    $checkItemStmt->execute([$orderId, $dishId]);
    $item = $checkItemStmt->fetch();

    if (!$item) {
        set_flash('error', 'This dish was not part of the specified order.');
        redirect("orders/review.php?order_id={$orderId}");
    }

    // Insert or update review
    $existingStmt = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ? AND dish_id = ? AND user_id = ?");
    $existingStmt->execute([$orderId, $dishId, $userId]);
    $existingId = $existingStmt->fetchColumn();

    if ($existingId) {
        $updateStmt = $pdo->prepare("
            UPDATE reviews 
            SET rating = ?, comment = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $updateStmt->execute([$rating, $comment ?: null, $existingId]);
        $message = "Your review for " . e($item['dish_name']) . " was updated successfully! Thank you for your feedback.";
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO reviews (order_id, dish_id, user_id, rating, comment, created_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $insertStmt->execute([$orderId, $dishId, $userId, $rating, $comment ?: null]);
        $message = "Your " . $rating . "-star review for " . e($item['dish_name']) . " was submitted successfully! Thank you.";
    }

    if (function_exists('audit_log')) {
        audit_log('REVIEW_SUBMITTED', "User {$userId} rated dish {$dishId} ({$rating} stars) for order {$orderId}");
    }

    set_flash('success', $message);
    redirect("orders/review.php?order_id={$orderId}");
}

// -------------------------------------------------------------
// Fetch Data for Display
// -------------------------------------------------------------
$targetOrderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$singleOrder = null;
$reviewableDishes = [];
$pastReviews = [];

if ($targetOrderId) {
    // Specific order view
    $orderStmt = $pdo->prepare("
        SELECT o.*, p.payment_method, p.payment_status 
        FROM orders o 
        LEFT JOIN payments p ON o.id = p.order_id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $orderStmt->execute([$targetOrderId, $userId]);
    $singleOrder = $orderStmt->fetch();

    if ($singleOrder) {
        // Fetch dishes in this order + existing reviews
        $dishesStmt = $pdo->prepare("
            SELECT oi.id AS order_item_id, oi.dish_id, oi.quantity, oi.unit_price, oi.subtotal,
                   d.name AS dish_name, d.image_url, d.description AS dish_desc, c.name AS category_name,
                   r.id AS review_id, r.rating, r.comment, r.created_at AS reviewed_at, r.updated_at AS review_updated_at
            FROM order_items oi
            JOIN dishes d ON oi.dish_id = d.id
            LEFT JOIN categories c ON d.category_id = c.id
            LEFT JOIN reviews r ON (r.order_id = oi.order_id AND r.dish_id = oi.dish_id AND r.user_id = ?)
            WHERE oi.order_id = ?
            ORDER BY d.name ASC
        ");
        $dishesStmt->execute([$userId, $targetOrderId]);
        $reviewableDishes = $dishesStmt->fetchAll();
    }
}

// Also fetch customer's overall completed orders that contain dishes for review
$completedOrdersStmt = $pdo->prepare("
    SELECT o.id, o.order_number, o.created_at, o.total_amount,
           COUNT(DISTINCT oi.dish_id) AS total_dishes,
           COUNT(DISTINCT r.dish_id) AS reviewed_dishes
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN reviews r ON (r.order_id = o.id AND r.dish_id = oi.dish_id AND r.user_id = o.user_id)
    WHERE o.user_id = ? AND o.status = 'COMPLETED'
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$completedOrdersStmt->execute([$userId]);
$completedOrders = $completedOrdersStmt->fetchAll();

// All past reviews by this user
$allReviewsStmt = $pdo->prepare("
    SELECT r.*, d.name AS dish_name, d.image_url, o.order_number, c.name AS category_name
    FROM reviews r
    JOIN dishes d ON r.dish_id = d.id
    JOIN orders o ON r.order_id = o.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$allReviewsStmt->execute([$userId]);
$pastReviews = $allReviewsStmt->fetchAll();

$pageTitle = 'Rate & Review Your Meals - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .review-container {
        max-width: 1050px;
        margin: 2rem auto 4rem;
        padding: 0 1rem;
        color: #f1f5f9;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .review-card {
        background: #1e1e24;
        border: 1px solid #33333f;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.75rem;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .review-card:hover {
        border-color: #f39c12;
    }
    .dish-review-row {
        display: flex;
        gap: 1.5rem;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .dish-thumb {
        width: 120px;
        height: 120px;
        border-radius: 10px;
        object-fit: cover;
        background: #2a2a35;
        border: 1px solid #444;
        flex-shrink: 0;
    }
    .dish-details {
        flex: 1;
        min-width: 260px;
    }
    .dish-title {
        font-size: 1.35rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 0.35rem 0;
    }
    .dish-tag {
        display: inline-block;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #f39c12;
        font-weight: 600;
        margin-bottom: 0.75rem;
    }
    /* Star Rating Widget */
    .star-rating-group {
        display: inline-flex;
        flex-direction: row-reverse;
        gap: 4px;
        margin: 0.5rem 0 1rem;
    }
    .star-rating-group input {
        display: none;
    }
    .star-rating-group label {
        font-size: 2rem;
        color: #4b5563;
        cursor: pointer;
        transition: color 0.2s ease, transform 0.15s ease;
    }
    .star-rating-group label:hover,
    .star-rating-group label:hover ~ label,
    .star-rating-group input:checked ~ label {
        color: #f59e0b;
        text-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
    }
    .star-rating-group label:active {
        transform: scale(1.2);
    }
    .review-textarea {
        width: 100%;
        background: #2a2a35;
        border: 1px solid #444;
        border-radius: 8px;
        color: #fff;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        resize: vertical;
        outline: none;
        transition: border-color 0.2s ease;
        box-sizing: border-box;
    }
    .review-textarea:focus {
        border-color: #f39c12;
        box-shadow: 0 0 0 2px rgba(243, 156, 18, 0.2);
    }
    .rating-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(243, 156, 18, 0.15);
        color: #f39c12;
        border: 1px solid rgba(243, 156, 18, 0.3);
        padding: 0.3rem 0.75rem;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .btn-gold {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: #1e1e24;
        font-weight: 700;
        border: none;
        padding: 0.65rem 1.4rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-gold:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(243, 156, 18, 0.35);
        color: #000;
    }
    .order-selector-pill {
        display: inline-block;
        padding: 0.5rem 1rem;
        background: #2a2a35;
        color: #cbd5e1;
        border: 1px solid #444;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.2s ease;
        margin: 0.25rem;
    }
    .order-selector-pill:hover,
    .order-selector-pill.active {
        background: #f39c12;
        color: #1e1e24;
        border-color: #f39c12;
    }
</style>

<div class="review-container">
    <!-- Header -->
    <div class="review-header">
        <div>
            <span class="badge badge-customer" style="font-size: 0.8rem; letter-spacing: 1px;">Customer Feedback &amp; Ratings</span>
            <h1 style="margin: 0.4rem 0 0.2rem 0; font-size: 2.1rem; color: #fff;">
                ⭐ Rate &amp; Review Your Dishes
            </h1>
            <p style="margin: 0; color: #94a3b8; font-size: 0.95rem;">
                Help other customers discover delicious food by sharing your honest 1–5 star ratings and reviews.
            </p>
        </div>
        <div>
            <a href="<?= base_url('orders/history.php') ?>" class="btn btn-secondary">
                &larr; Back to Order History
            </a>
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary" style="margin-left: 0.5rem;">
                🍽️ Browse Menu
            </a>
        </div>
    </div>

    <!-- Flash message alerts -->
    <?php if (has_flash('success')): ?>
        <?php foreach (get_flash('success') as $msg): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; color: #86efac;">
                ✓ <?= e($msg) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (has_flash('error')): ?>
        <?php foreach (get_flash('error') as $msg): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5;">
                ⚠️ <?= e($msg) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Completed Orders Navigation Bar -->
    <?php if (!empty($completedOrders)): ?>
        <div class="review-card" style="padding: 1rem 1.25rem; margin-bottom: 2rem;">
            <div style="font-size: 0.85rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.6rem;">
                Select a Completed Order to Rate:
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                <?php foreach ($completedOrders as $co): ?>
                    <?php 
                    $isSelected = ($targetOrderId && (int)$targetOrderId === (int)$co['id']);
                    $allReviewed = ($co['reviewed_dishes'] >= $co['total_dishes']);
                    ?>
                    <a href="<?= base_url('orders/review.php?order_id=' . $co['id']) ?>" 
                       class="order-selector-pill <?= $isSelected ? 'active' : '' ?>">
                        <?= e($co['order_number']) ?>
                        <span style="font-size: 0.75rem; opacity: 0.85; margin-left: 4px;">
                            (<?= (int)$co['reviewed_dishes'] ?>/<?= (int)$co['total_dishes'] ?> <?= $allReviewed ? '✓ Rated' : 'Rated' ?>)
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($targetOrderId && $singleOrder): ?>
        <!-- Specific Order Details Banner -->
        <div style="display: flex; justify-content: space-between; align-items: center; background: #2a2a35; border: 1px solid #444; border-radius: 10px; padding: 1rem 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: 0.8rem; color: #f39c12; font-weight: 700;">ACTIVE ORDER EVALUATION</span>
                <div style="font-size: 1.3rem; font-weight: 700; color: #fff; margin-top: 0.2rem;">
                    Order: <span style="font-family: monospace; color: #f39c12;"><?= e($singleOrder['order_number']) ?></span>
                </div>
                <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 0.2rem;">
                    Delivered on: <?= format_datetime($singleOrder['created_at']) ?> &bull; Total: <?= format_price((float)$singleOrder['total_amount']) ?>
                </div>
            </div>
            <div>
                <a href="<?= base_url('orders/confirmation.php?id=' . $singleOrder['id']) ?>" class="btn btn-sm btn-secondary">
                    📄 View Order Invoice
                </a>
            </div>
        </div>

        <?php if ($singleOrder['status'] !== 'COMPLETED'): ?>
            <div class="review-card" style="text-align: center; padding: 3rem 1.5rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🍳</div>
                <h3 style="color: #f39c12; margin-bottom: 0.5rem;">Order In Progress</h3>
                <p style="color: #94a3b8; max-width: 480px; margin: 0 auto 1.5rem;">
                    This order is currently <strong><?= e($singleOrder['status']) ?></strong>. You can submit ratings and reviews once the delivery has arrived and the order is marked COMPLETED!
                </p>
                <a href="<?= base_url('orders/track.php?order_number=' . urlencode($singleOrder['order_number'])) ?>" class="btn btn-primary">
                    📍 Live Order Tracking
                </a>
            </div>
        <?php elseif (empty($reviewableDishes)): ?>
            <div class="review-card" style="text-align: center; padding: 3rem 1.5rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🍽️</div>
                <h3>No Items Found</h3>
                <p style="color: #94a3b8;">No food items were found in this order to review.</p>
            </div>
        <?php else: ?>
            <!-- Review Forms for each dish in the order -->
            <?php foreach ($reviewableDishes as $item): ?>
                <?php 
                $hasReview = !empty($item['review_id']);
                $currentRating = $hasReview ? (int)$item['rating'] : 5;
                $currentComment = $hasReview ? $item['comment'] : '';
                ?>
                <div class="review-card" id="dish-card-<?= (int)$item['dish_id'] ?>">
                    <div class="dish-review-row">
                        <!-- Dish Image -->
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= base_url('public/uploads/' . htmlspecialchars($item['image_url'])) ?>" alt="<?= e($item['dish_name']) ?>" class="dish-thumb">
                        <?php else: ?>
                            <div class="dish-thumb" style="display:flex; align-items:center; justify-content:center; color:#777; font-size:0.8rem; text-align:center; padding:5px;">
                                No Image
                            </div>
                        <?php endif; ?>

                        <!-- Dish Info and Form -->
                        <div class="dish-details">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
                                <div>
                                    <h3 class="dish-title"><?= e($item['dish_name']) ?></h3>
                                    <span class="dish-tag"><?= e($item['category_name'] ?? 'Main Course') ?></span>
                                    <div style="font-size: 0.85rem; color: #94a3b8;">
                                        Ordered: <?= (int)$item['quantity'] ?> portion(s) &bull; <?= format_price((float)$item['unit_price']) ?> each
                                    </div>
                                </div>
                                <?php if ($hasReview): ?>
                                    <div class="rating-badge">
                                        <span>★ <?= $currentRating ?> / 5</span>
                                        <span style="font-size: 0.75rem; font-weight: normal; color: #94a3b8;">
                                            Reviewed on <?= date('M d, Y', strtotime($item['reviewed_at'])) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Review Submission Form -->
                            <form method="POST" action="<?= base_url('orders/review.php') ?>" style="margin-top: 1.25rem;">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="submit_review">
                                <input type="hidden" name="order_id" value="<?= (int)$targetOrderId ?>">
                                <input type="hidden" name="dish_id" value="<?= (int)$item['dish_id'] ?>">

                                <label style="display: block; font-weight: 600; font-size: 0.9rem; color: #cbd5e1; margin-bottom: 0.25rem;">
                                    Your Star Rating (තරු ලකුණු 1 සිට 5 දක්වා):
                                </label>

                                <!-- Accessible 5-star selector with dynamic hover -->
                                <div class="star-rating-group" id="stars-group-<?= (int)$item['dish_id'] ?>">
                                    <?php for ($s = 5; $s >= 1; $s--): ?>
                                        <input type="radio" 
                                               id="star-<?= (int)$item['dish_id'] ?>-<?= $s ?>" 
                                               name="rating" 
                                               value="<?= $s ?>" 
                                               <?= ($currentRating === $s) ? 'checked' : '' ?>
                                               required>
                                        <label for="star-<?= (int)$item['dish_id'] ?>-<?= $s ?>" title="<?= $s ?> Star<?= $s > 1 ? 's' : '' ?>">
                                            ★
                                        </label>
                                    <?php endfor; ?>
                                </div>

                                <div style="margin-bottom: 1rem;">
                                    <label for="comment-<?= (int)$item['dish_id'] ?>" style="display: block; font-weight: 600; font-size: 0.9rem; color: #cbd5e1; margin-bottom: 0.4rem;">
                                        Your Review &amp; Comments (අදහස් දැක්වීම):
                                    </label>
                                    <textarea id="comment-<?= (int)$item['dish_id'] ?>" 
                                              name="comment" 
                                              rows="3" 
                                              class="review-textarea" 
                                              placeholder="What did you think of the flavor, spices, freshness, and presentation? Would you recommend this to others?"><?= e($currentComment) ?></textarea>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                                    <button type="submit" class="btn-gold">
                                        <?= $hasReview ? '✏️ Update My Review' : '⭐ Submit Review' ?>
                                    </button>
                                    <a href="<?= base_url('menu/detail.php?id=' . (int)$item['dish_id']) ?>" target="_blank" style="color: #f39c12; font-size: 0.85rem; text-decoration: underline;">
                                        View dish on menu &rarr;
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>
        <!-- No specific order chosen: Show Overview of past reviews & invite to review -->
        <div class="review-card" style="text-align: center; padding: 2.5rem 1.5rem; margin-bottom: 2.5rem;">
            <div style="font-size: 3rem; margin-bottom: 0.75rem;">🌟</div>
            <h2 style="color: #fff; margin-bottom: 0.5rem;">Share Your Dining Experiences</h2>
            <p style="color: #94a3b8; max-width: 580px; margin: 0 auto 1.5rem; font-size: 1rem;">
                Select any completed order above or from your order history to submit star ratings and write comments for the food items you enjoyed!
            </p>
            <a href="<?= base_url('orders/history.php?status=COMPLETED') ?>" class="btn btn-primary" style="padding: 0.75rem 1.75rem; font-size: 1rem;">
                📜 View Completed Orders &rarr;
            </a>
        </div>

        <?php if (!empty($pastReviews)): ?>
            <h3 style="color: #f39c12; margin-bottom: 1.25rem; font-size: 1.4rem;">
                Your Past Food Reviews (<?= count($pastReviews) ?>)
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
                <?php foreach ($pastReviews as $rev): ?>
                    <div class="review-card" style="margin-bottom: 0;">
                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 0.75rem;">
                            <?php if (!empty($rev['image_url'])): ?>
                                <img src="<?= base_url('public/uploads/' . htmlspecialchars($rev['image_url'])) ?>" alt="<?= e($rev['dish_name']) ?>" style="width: 55px; height: 55px; border-radius: 8px; object-fit: cover; border: 1px solid #444;">
                            <?php else: ?>
                                <div style="width: 55px; height: 55px; border-radius: 8px; background: #2a2a35; display:flex; align-items:center; justify-content:center; font-size: 1.5rem;">🍛</div>
                            <?php endif; ?>
                            <div>
                                <h4 style="margin: 0; color: #fff; font-size: 1.05rem;"><?= e($rev['dish_name']) ?></h4>
                                <div style="font-size: 0.75rem; color: #f39c12; text-transform: uppercase;">
                                    <?= e($rev['category_name'] ?? 'Main Course') ?> &bull; Order <?= e($rev['order_number']) ?>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <div style="color: #f59e0b; font-size: 1.15rem; letter-spacing: 2px;">
                                <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                            </div>
                            <span style="font-size: 0.75rem; color: #94a3b8;">
                                <?= date('M d, Y', strtotime($rev['created_at'])) ?>
                            </span>
                        </div>

                        <?php if (!empty($rev['comment'])): ?>
                            <div style="font-size: 0.9rem; color: #cbd5e1; background: #2a2a35; padding: 0.75rem; border-radius: 6px; line-height: 1.5; font-style: italic; margin-bottom: 1rem; border-left: 3px solid #f39c12;">
                                "<?= e($rev['comment']) ?>"
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <a href="<?= base_url('orders/review.php?order_id=' . (int)$rev['order_id']) ?>" class="btn btn-sm btn-secondary" style="font-size: 0.78rem;">
                                ✏️ Edit Review
                            </a>
                            <a href="<?= base_url('menu/detail.php?id=' . (int)$rev['dish_id']) ?>" class="btn btn-sm btn-primary" style="font-size: 0.78rem;">
                                View Dish
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
