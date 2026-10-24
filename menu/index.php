<?php
//  Include required files
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();

//  GET parameters for Search, Filter, Sort, and Pagination
$search = $_GET['search'] ?? '';
$category_id = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$sort = $_GET['sort'] ?? 'name_asc';

// Pagination variables
$limit = 8; // Number of dishes per page
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($page - 1) * $limit;

//  Fetch Categories for the dropdown filter
$stmt_cats = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt_cats->fetchAll();

$where_sql = "WHERE d.is_active = 1"; 
$params = [];

if (!empty($search)) {
    $where_sql .= " AND (d.name LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%";
}

if ($category_id) {
    $where_sql .= " AND d.category_id = ?";
    $params[] = $category_id;
}

//  Get Total number of dishes for Pagination logic
$count_sql = "SELECT COUNT(*) FROM dishes d " . $where_sql;
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_dishes = $stmt_count->fetchColumn();
$total_pages = ceil($total_dishes / $limit);

// Sorting logic
$allowed_sorts = [
    'name_asc' => 'd.name ASC', 
    'price_asc' => 'd.price ASC', 
    'price_desc' => 'd.price DESC'
];
$sort_query = $allowed_sorts[$sort] ?? 'd.name ASC';

// 6. Final Data Query with Limit and Offset
$sql = "SELECT d.*, c.name as category_name 
        FROM dishes d 
        JOIN categories c ON d.category_id = c.id 
        $where_sql 
        ORDER BY $sort_query 
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt_dishes = $pdo->prepare($sql);
$stmt_dishes->execute($params);
$dishes = $stmt_dishes->fetchAll();

// Helper function to build pagination URLs keeping search/filter params
function build_url($new_page, $search, $category_id, $sort) {
    $params = [
        'page' => $new_page,
        'search' => $search,
        'sort' => $sort
    ];
    if ($category_id) $params['category'] = $category_id;
    return '?' . http_build_query($params);
}
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

    .dish-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
    .dish-card { background: #2a2a35; border-radius: 12px; overflow: hidden; text-align: center; transition: all 0.3s ease; border: 1px solid #333; display: flex; flex-direction: column;}
    .dish-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(243, 156, 18, 0.15); border-color: #f39c12; }
    
    .dish-img { width: 100%; height: 200px; object-fit: cover; background: #1a1a20; }
    .dish-info { padding: 20px; display: flex; flex-direction: column; flex-grow: 1; }
    .dish-name { margin: 0 0 5px; font-size: 1.3rem; color: #fff; font-weight: bold; }
    .dish-cat { font-size: 0.8rem; color: #aaa; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
    .dish-price { font-size: 1.5rem; color: #f39c12; font-weight: bold; margin-bottom: 15px; margin-top: auto;}
    
    .btn-view { display: inline-block; padding: 8px 20px; background: transparent; border: 2px solid #f39c12; color: #f39c12; text-decoration: none; border-radius: 20px; transition: 0.3s; font-weight: bold; font-size: 0.9rem;}
    .btn-view:hover { background: #f39c12; color: #fff; }

    /* Pagination CSS */
    .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 30px; flex-wrap: wrap;}
    .page-link { padding: 10px 15px; background: #2a2a35; color: #fff; text-decoration: none; border-radius: 6px; border: 1px solid #444; transition: 0.3s; font-weight: bold;}
    .page-link:hover { background: #f39c12; border-color: #f39c12; }
    .page-link.active { background: #f39c12; border-color: #f39c12; cursor: default; }
    .page-link.disabled { background: #1a1a20; color: #555; border-color: #333; cursor: not-allowed; }
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
                    <!-- Display Image (Using base_url for the correct path resolution) -->
                    <?php if (!empty($dish['image_path'])): ?>
                        <img src="<?= base_url('public/uploads/' . htmlspecialchars($dish['image_path'])) ?>" alt="<?= htmlspecialchars($dish['name']) ?>" class="dish-img">
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

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <a href="<?= build_url($page - 1, $search, $category_id, $sort) ?>" class="page-link">&laquo; Prev</a>
            <?php else: ?>
                <span class="page-link disabled">&laquo; Prev</span>
            <?php endif; ?>

            <!-- Page Numbers -->
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?= build_url($i, $search, $category_id, $sort) ?>" class="page-link <?= ($i == $page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <!-- Next Button -->
            <?php if ($page < $total_pages): ?>
                <a href="<?= build_url($page + 1, $search, $category_id, $sort) ?>" class="page-link">Next &raquo;</a>
            <?php else: ?>
                <span class="page-link disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
// 7. Include Shared Footer
require_once __DIR__ . '/../includes/footer.php'; 
?>