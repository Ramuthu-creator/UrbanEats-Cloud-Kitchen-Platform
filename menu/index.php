<?php
// 1. Include required files (Using __DIR__ for accurate relative paths)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();

// 2. GET parameters for Search, Filter, and Sort
$search = $_GET['search'] ?? '';
$category_id = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$sort = $_GET['sort'] ?? 'name_asc';

// 3. Fetch Categories for the dropdown filter
$stmt_cats = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt_cats->fetchAll();

// 4. SQL Query to fetch dishes dynamically
$sql = "SELECT d.*, c.name as category_name 
        FROM dishes d 
        JOIN categories c ON d.category_id = c.id 
        WHERE d.is_active = 1"; // Fetch only active dishes (not soft-deleted)
$params = [];

// If a search keyword is provided
if (!empty($search)) {
    $sql .= " AND (d.name LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%";
}

// If a category is selected
if ($category_id) {
    $sql .= " AND d.category_id = ?";
    $params[] = $category_id;
}

// Sorting logic
$allowed_sorts = [
    'name_asc' => 'd.name ASC', 
    'price_asc' => 'd.price ASC', 
    'price_desc' => 'd.price DESC'
];
$sort_query = $allowed_sorts[$sort] ?? 'd.name ASC';
$sql .= " ORDER BY $sort_query";

// 5. Execute the Query using Prepared Statements
$stmt_dishes = $pdo->prepare($sql);
$stmt_dishes->execute($params);
$dishes = $stmt_dishes->fetchAll();
?>

<!-- Custom CSS for Component B (Dark Theme) -->
<style>
    .menu-wrapper { max-width: 1200px; margin: 0 auto; padding: 40px 20px; color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .menu-title { text-align: center; color: #f39c12; font-size: 2.5rem; margin-bottom: 30px; font-weight: bold; }
    
    .filter-bar { display: flex; gap: 15px; flex-wrap: wrap; background: #1e1e24; padding: 20px; border-radius: 10px; margin-bottom: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); justify-content: center; align-items: center; border: 1px solid #333;}
    .filter-bar input, .filter-bar select { padding: 10px 15px; border-radius: 6px; border: 1px solid #444; background: #2a2a35; color: #fff; outline: none; font-size: 1rem; min-width: 200px;}
    .btn-search { padding: 10px 20px; background: #f39c12; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 1rem; }
    .btn-search:hover { background: #e67e22; }
    .btn-clear { padding: 10px 20px; background: #555; color: #fff; text-decoration: none; border-radius: 6px; transition: 0.3s; font-weight: bold; }
    .btn-clear:hover { background: #777; }

    .dish-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px; }
    .dish-card { background: #2a2a35; border-radius: 12px; overflow: hidden; text-align: center; transition: all 0.3s ease; border: 1px solid #333; display: flex; flex-direction: column;}
    .dish-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(243, 156, 18, 0.15); border-color: #f39c12; }
    
    .dish-img { width: 100%; height: 200px; object-fit: cover; background: #1a1a20; }
    .dish-info { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
    .dish-name { margin: 0 0 5px; font-size: 1.3rem; color: #fff; font-weight: bold; }
    .dish-cat { font-size: 0.8rem; color: #aaa; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
    .dish-price { font-size: 1.5rem; color: #f39c12; font-weight: bold; margin-bottom: 15px; margin-top: auto;}
    
    .btn-view { display: inline-block; padding: 8px 20px; background: transparent; border: 2px solid #f39c12; color: #f39c12; text-decoration: none; border-radius: 20px; transition: 0.3s; font-weight: bold; font-size: 0.9rem;}
    .btn-view:hover { background: #f39c12; color: #fff; }
</style>

<div class="menu-wrapper">
    <h1 class="menu-title">🍽️ Explore Our Menu</h1>

    <!-- Search / Filter Form -->
    <form method="GET" action="index.php" class="filter-bar">
        <input type="text" name="search" placeholder="Search dishes..." value="<?= htmlspecialchars($search) ?>">
        
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort">
            <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
            <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
            <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
        </select>

        <button type="submit" class="btn-search">Search</button>
        <a href="index.php" class="btn-clear">Clear</a>
    </form>

    <!-- Dishes Display Grid -->
    <div class="dish-grid">
        <?php if (count($dishes) > 0): ?>
            <?php foreach ($dishes as $dish): ?>
                <div class="dish-card">
                    <!-- Display Image (Using image_url column from the database) -->
                    <?php if (!empty($dish['image_url'])): ?>
                        <img src="../public/uploads/<?= htmlspecialchars($dish['image_url']) ?>" alt="<?= htmlspecialchars($dish['name']) ?>" class="dish-img">
                    <?php else: ?>
                        <div class="dish-img" style="display:flex; align-items:center; justify-content:center; color:#777; font-size: 1rem;">No Image</div>
                    <?php endif; ?>
                    
                    <div class="dish-info">
                        <h3 class="dish-name"><?= htmlspecialchars($dish['name']) ?></h3>
                        <p class="dish-cat"><?= htmlspecialchars($dish['category_name']) ?></p>
                        <p class="dish-price">LKR <?= number_format($dish['price'], 2) ?></p>
                        <a href="detail.php?id=<?= $dish['id'] ?>" class="btn-view">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: #1e1e24; border-radius: 10px; border: 1px dashed #444;">
                <h3 style="color: #aaa; margin: 0;">No dishes found. Please try a different search!</h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// 6. Include Shared Footer
require_once __DIR__ . '/../includes/footer.php'; 
?>