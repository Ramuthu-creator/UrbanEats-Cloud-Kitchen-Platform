<?php
// 1. Include required files
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();

// 2. Get the Dish ID from the URL (e.g., detail.php?id=3)
$dish_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// If no valid ID is provided, show an error
if (!$dish_id) {
    echo "<div style='color: white; text-align: center; padding: 100px;'><h3>Invalid Dish ID!</h3><br><a href='index.php' style='color: #f39c12;'>Go back to menu</a></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// 3. Fetch dish details along with the category name
$stmt = $pdo->prepare("
    SELECT d.*, c.name as category_name 
    FROM dishes d 
    LEFT JOIN categories c ON d.category_id = c.id 
    WHERE d.id = ? AND d.is_active = 1
");
$stmt->execute([$dish_id]);
$dish = $stmt->fetch();

// If the dish doesn't exist or is inactive
if (!$dish) {
    echo "<div style='color: white; text-align: center; padding: 100px;'><h3>Dish not found or currently unavailable!</h3><br><a href='index.php' style='color: #f39c12;'>Go back to menu</a></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// 4. Fetch inventory stock (Component B Requirement)
// We try to get the stock from the 'inventory' table. If not set, we show a default value.
$stock = 0;
try {
    $stmt_stock = $pdo->prepare("SELECT quantity FROM inventory WHERE dish_id = ?");
    $stmt_stock->execute([$dish_id]);
    $stock_data = $stmt_stock->fetch();
    
    if ($stock_data) {
        $stock = (int)$stock_data['quantity'];
    } else {
        $stock = 15; // Placeholder mock stock if inventory row isn't created yet
    }
} catch (PDOException $e) {
    $stock = "N/A"; // Fallback if inventory table doesn't exist yet
}

// 5. Fetch dish rating statistics and customer reviews
$ratingStats = ['avg_rating' => 0.0, 'review_count' => 0];
$dishReviews = [];
$userCompletedOrderId = null;

try {
    $stmt_rating = $pdo->prepare("SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(id) AS review_count FROM reviews WHERE dish_id = ?");
    $stmt_rating->execute([$dish_id]);
    $ratingStats = $stmt_rating->fetch() ?: $ratingStats;

    $stmt_revs = $pdo->prepare("
        SELECT r.*, u.full_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.dish_id = ? 
        ORDER BY r.created_at DESC 
        LIMIT 10
    ");
    $stmt_revs->execute([$dish_id]);
    $dishReviews = $stmt_revs->fetchAll();

    // Check if logged-in customer ordered this dish in a completed order
    if (function_exists('is_logged_in') && is_logged_in()) {
        $checkOrderStmt = $pdo->prepare("
            SELECT o.id 
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE o.user_id = ? AND oi.dish_id = ? AND o.status = 'COMPLETED'
            ORDER BY o.created_at DESC 
            LIMIT 1
        ");
        $checkOrderStmt->execute([current_user_id(), $dish_id]);
        $userCompletedOrderId = $checkOrderStmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Graceful fallback if reviews table not yet migrated
}
?>

<!-- Dark Theme CSS for Detail Page -->
<style>
    .detail-wrapper { max-width: 1000px; margin: 60px auto; padding: 30px; color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; gap: 50px; flex-wrap: wrap; background: #1e1e24; border-radius: 12px; border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5);}
    .detail-img-box { flex: 1; min-width: 300px; }
    .detail-img { width: 100%; height: 100%; max-height: 400px; border-radius: 10px; object-fit: cover; border: 2px solid #333; }
    
    .detail-info { flex: 1.5; min-width: 300px; display: flex; flex-direction: column; justify-content: center; }
    .detail-title { font-size: 2.5rem; color: #fff; margin-bottom: 5px; margin-top: 0; font-weight: bold;}
    .detail-cat { font-size: 1rem; color: #f39c12; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 25px; font-weight: bold; }
    
    .detail-price { font-size: 2.5rem; color: #2ecc71; font-weight: bold; margin-bottom: 25px; }
    .detail-desc { font-size: 1.1rem; color: #ccc; line-height: 1.7; margin-bottom: 35px; background: #2a2a35; padding: 20px; border-radius: 8px; border-left: 4px solid #f39c12;}
    
    .detail-stock { font-size: 1.1rem; margin-bottom: 35px; padding: 15px 20px; background: #2a2a35; border-radius: 6px; display: inline-block; border: 1px solid #444; }
    
    .action-buttons { display: flex; gap: 15px; flex-wrap: wrap;}
    .btn-back { display: inline-block; padding: 12px 30px; background: #555; color: #fff; text-decoration: none; border-radius: 6px; transition: 0.3s; font-weight: bold; font-size: 1.1rem;}
    .btn-back:hover { background: #777; }
    
    .btn-cart { display: inline-block; padding: 12px 30px; background: #f39c12; color: #fff; text-decoration: none; border-radius: 6px; transition: 0.3s; font-weight: bold; border: none; cursor: pointer; font-size: 1.1rem;}
    .btn-cart:hover { background: #e67e22; box-shadow: 0 4px 10px rgba(243, 156, 18, 0.3); transform: translateY(-2px);}
</style>

<div class="detail-wrapper">
    <div class="detail-img-box">
        <?php if (!empty($dish['image_url'])): ?>
            <img src="<?= base_url('public/uploads/' . htmlspecialchars($dish['image_url'])) ?>" alt="<?= htmlspecialchars($dish['name']) ?>" class="detail-img">
        <?php else: ?>
            <div style="width: 100%; height: 400px; background: #2a2a35; display:flex; align-items:center; justify-content:center; color:#777; border-radius: 10px; border: 2px dashed #444;">No Image Available</div>
        <?php endif; ?>
    </div>
    
    <div class="detail-info">
        <h1 class="detail-title"><?= htmlspecialchars($dish['name']) ?></h1>
        <div class="detail-cat"><?= htmlspecialchars($dish['category_name']) ?></div>
        
        <!-- Dish Star Rating Display -->
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
            <?php if (!empty($ratingStats['review_count']) && (int)$ratingStats['review_count'] > 0): ?>
                <span style="color: #f59e0b; font-size: 1.25rem; letter-spacing: 2px;">
                    <?= str_repeat('★', (int)round($ratingStats['avg_rating'])) . str_repeat('☆', 5 - (int)round($ratingStats['avg_rating'])) ?>
                </span>
                <span style="font-size: 1.15rem; font-weight: 700; color: #fff;"><?= number_format($ratingStats['avg_rating'], 1) ?> / 5.0</span>
                <span style="color: #94a3b8; font-size: 0.95rem;">(<?= (int)$ratingStats['review_count'] ?> <?= $ratingStats['review_count'] == 1 ? 'review' : 'reviews' ?>)</span>
            <?php else: ?>
                <span style="color: #94a3b8; font-size: 0.95rem;">⭐ New Dish &bull; No customer ratings yet</span>
            <?php endif; ?>

            <?php if (!empty($userCompletedOrderId)): ?>
                <a href="<?= base_url('orders/review.php?order_id=' . (int)$userCompletedOrderId) ?>" 
                   style="margin-left: 8px; padding: 3px 10px; background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #f59e0b; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-decoration: none;">
                    ✍️ Rate this dish
                </a>
            <?php endif; ?>
        </div>
        
        <div class="detail-price">LKR <?= number_format($dish['price'], 2) ?></div>
        
        <div class="detail-desc">
            <?= nl2br(htmlspecialchars($dish['description'])) ?>
        </div>
        
        <div class="detail-stock">
            📦 <strong>Inventory Status:</strong> 
            <span style="color: <?= ($stock > 0 || $stock === 'N/A') ? '#2ecc71' : '#e74c3c' ?>; font-weight: bold; margin-left: 10px;">
                <?php 
                    if ($stock === 'N/A') echo 'Stock Data Unavailable';
                    elseif ($stock > 0) echo $stock . ' Portions Available';
                    else echo 'Out of Stock';
                ?>
            </span>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn-back">&larr; Back to Menu</a>
            <!-- Component C Add to Cart Integration -->
            <form method="POST" action="<?= base_url('orders/cart.php') ?>" style="display:inline-flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="dish_id" value="<?= (int)$dish['id'] ?>">
                <div style="display:inline-flex; align-items:center; background:#2a2a35; border:1px solid #444; border-radius:6px; overflow:hidden;">
                    <label for="order_qty" style="padding:0 0.5rem; font-size:0.85rem; color:#aaa;">Qty:</label>
                    <input type="number" id="order_qty" name="quantity" value="1" min="1" max="99" style="width:60px; padding:8px; background:transparent; border:none; color:#fff; font-weight:bold; text-align:center;">
                </div>
                <button type="submit" class="btn-cart">Add to Order 🛒</button>
            </form>
        </div>
    </div>
</div>

<!-- Customer Ratings & Reviews Section (පාරිභෝගික ඇගයීම් සහ විචාර) -->
<div style="max-width: 1000px; margin: 0 auto 60px; padding: 30px; background: #1e1e24; border-radius: 12px; border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5); color: #fff;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; border-bottom: 1px solid #333; padding-bottom: 15px;">
        <div>
            <h2 style="margin: 0 0 5px 0; font-size: 1.8rem; color: #f39c12;">
                ⭐ Customer Ratings &amp; Reviews
            </h2>
            <div style="color: #94a3b8; font-size: 0.95rem;">
                Real feedback from verified diners who ordered this meal.
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="text-align: right;">
                <div style="font-size: 1.7rem; font-weight: bold; color: #fff;">
                    <?= number_format($ratingStats['avg_rating'], 1) ?> <span style="font-size: 1rem; color: #aaa;">/ 5.0</span>
                </div>
                <div style="color: #f59e0b; font-size: 1rem;">
                    <?= str_repeat('★', (int)round($ratingStats['avg_rating'])) . str_repeat('☆', 5 - (int)round($ratingStats['avg_rating'])) ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($dishReviews)): ?>
        <div style="text-align: center; padding: 40px 20px; background: #2a2a35; border-radius: 8px; border: 1px dashed #444;">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">💬</div>
            <h3 style="margin: 0 0 8px 0; color: #fff;">No Reviews Yet</h3>
            <p style="color: #aaa; margin: 0 0 15px 0;">Be among the first to taste this delicious creation and share your review!</p>
            <?php if (!empty($userCompletedOrderId)): ?>
                <a href="<?= base_url('orders/review.php?order_id=' . (int)$userCompletedOrderId) ?>" class="btn-cart" style="padding: 10px 20px; font-size: 0.95rem;">
                    Write a Review &rarr;
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 15px;">
            <?php foreach ($dishReviews as $rev): ?>
                <div style="background: #2a2a35; padding: 20px; border-radius: 8px; border: 1px solid #3a3a48;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 5px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 38px; height: 38px; border-radius: 50%; background: #f39c12; color: #1e1e24; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem;">
                                <?= strtoupper(substr($rev['full_name'] ?? 'C', 0, 1)) ?>
                            </div>
                            <div>
                                <strong style="color: #fff; font-size: 1.05rem;"><?= htmlspecialchars($rev['full_name'] ?? 'Customer') ?></strong>
                                <span style="font-size: 0.75rem; color: #2ecc71; margin-left: 6px; background: rgba(46, 204, 113, 0.15); padding: 2px 6px; border-radius: 4px;">✓ Verified Order</span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="color: #f59e0b; font-size: 1rem; letter-spacing: 1px;">
                                <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                            </div>
                            <span style="font-size: 0.75rem; color: #888;">
                                <?= date('M d, Y', strtotime($rev['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($rev['comment'])): ?>
                        <div style="color: #ddd; font-size: 0.95rem; line-height: 1.6; margin-top: 8px; padding-left: 48px;">
                            <?= nl2br(htmlspecialchars($rev['comment'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
// 5. Include Shared Footer
require_once __DIR__ . '/../includes/footer.php'; 
?>