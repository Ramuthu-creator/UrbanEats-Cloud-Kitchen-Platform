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
            <!-- Component C add to cart  -->
            <button class="btn-cart" onclick="alert('Component C Hook: Proceed to Checkout or Add to Cart!')">Add to Order 🛒</button>
        </div>
    </div>
</div>

<?php 
// 5. Include Shared Footer
require_once __DIR__ . '/../includes/footer.php'; 
?>