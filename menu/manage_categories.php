<?php
// 1. Include required files
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Restrict access to Admin and Staff only
require_role(['admin', 'staff']);

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();
$message = '';
$edit_category = null;

// 2. Handle Delete Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    validate_csrf_or_abort();
    $delete_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        // Before deleting the category, check if there are any dishes associated with it
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM dishes WHERE category_id = ?");
        $stmt_check->execute([$delete_id]);
        $dish_count = $stmt_check->fetchColumn();

        if ($dish_count > 0) {
            $message = "<div class='alert alert-danger'>Cannot delete this category because it contains $dish_count dish(es). Please delete or move the dishes first.</div>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                if (function_exists('log_action')) {
                    log_action('CATEGORY_DELETE', "Deleted category ID #{$delete_id}");
                }
                $message = "<div class='alert alert-success'>Category removed successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to delete category.</div>";
            }
        }
    }
}

// 3. Handle Add / Edit Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
    validate_csrf_or_abort();
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);

    if (!empty($name)) {
        // Generate URL-friendly slug
        $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        if (empty($baseSlug)) {
            $baseSlug = 'cat-' . substr(md5($name), 0, 6);
        }

        if ($_POST['action'] === 'add') {
            // Ensure unique slug
            $slug = $baseSlug;
            $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
            $slugCheck->execute([$slug]);
            if ($slugCheck->fetchColumn() > 0) {
                $slug = $baseSlug . '-' . substr(uniqid(), -4);
            }

            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, is_active) VALUES (?, ?, 1)");
            if ($stmt->execute([$name, $slug])) {
                if (function_exists('log_action')) {
                    log_action('CATEGORY_CREATE', "Created category '{$name}'");
                }
                $message = "<div class='alert alert-success'>Category added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to add category.</div>";
            }
        } elseif ($_POST['action'] === 'edit' && $category_id) {
            $slug = $baseSlug;
            $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
            $slugCheck->execute([$slug, $category_id]);
            if ($slugCheck->fetchColumn() > 0) {
                $slug = $baseSlug . '-' . substr(uniqid(), -4);
            }

            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
            if ($stmt->execute([$name, $slug, $category_id])) {
                if (function_exists('log_action')) {
                    log_action('CATEGORY_UPDATE', "Updated category ID #{$category_id} to '{$name}'");
                }
                $message = "<div class='alert alert-success'>Category updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to update category.</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-danger'>Category name cannot be empty.</div>";
    }
}

// 4. Handle Edit Fetch Request (GET)
if (isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_category = $stmt->fetch();
    }
}

// Fetch all categories for the list
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<!-- Dark Theme CSS -->
<style>
    .manage-wrapper { max-width: 800px; margin: 40px auto; padding: 20px; color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .page-title { color: #f39c12; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;}
    
    .form-card { background: #1e1e24; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); margin-bottom: 40px; border: 1px solid #333;}
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;}
    .form-control { width: 100%; padding: 12px; border-radius: 6px; border: 1px solid #444; background: #2a2a35; color: #fff; box-sizing: border-box; }
    .form-control:focus { outline: none; border-color: #f39c12; }
    
    .btn-submit { background: #f39c12; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; font-size: 1.1rem;}
    .btn-submit:hover { background: #e67e22; }

    .table-dark { width: 100%; border-collapse: collapse; margin-top: 20px; background: #1e1e24; border-radius: 10px; overflow: hidden;}
    .table-dark th, .table-dark td { padding: 15px; text-align: left; border-bottom: 1px solid #333; }
    .table-dark th { background: #2a2a35; color: #f39c12; }
    .table-dark tr:hover { background: #2a2a35; }
    
    .btn-edit { display: inline-block; padding: 6px 12px; background: #3498db; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.9rem; font-weight: bold;}
    .btn-edit:hover { background: #2980b9; }
    
    .btn-delete { display: inline-block; padding: 6px 12px; background: #e74c3c; color: #fff; border: none; border-radius: 4px; font-size: 0.9rem; font-weight: bold; cursor: pointer;}
    .btn-delete:hover { background: #c0392b; }

    .btn-back { display: inline-block; padding: 8px 15px; background: #555; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.9rem; font-weight: bold;}
    .btn-back:hover { background: #777; }

    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .alert-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
    .alert-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
</style>

<div class="manage-wrapper">
    <div class="page-title">
        <h1 style="margin: 0;">📁 Manage Categories</h1>
        <!-- අර Dishes Manage කරන පිටුවට ලේසියෙන් යන්න තියෙන ලින්ක් එක -->
        <a href="manage.php" class="btn-back">⬅ Back to Dishes</a>
    </div>
    
    <?= $message ?>

    <!-- Form for Adding / Editing Categories -->
    <div class="form-card">
        <h3><?= $edit_category ? '✏️ Edit Category' : '➕ Add New Category' ?></h3><br>
        <form method="POST" action="manage_categories.php">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="<?= $edit_category ? 'edit' : 'add' ?>">
            
            <?php if ($edit_category): ?>
                <input type="hidden" name="category_id" value="<?= $edit_category['id'] ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="name" class="form-control" required value="<?= $edit_category ? htmlspecialchars($edit_category['name']) : '' ?>" placeholder="e.g. Beverages">
            </div>

            <button type="submit" class="btn-submit"><?= $edit_category ? 'Update Category' : 'Add Category' ?></button>
            
            <?php if ($edit_category): ?>
                <a href="manage_categories.php" style="display: block; text-align: center; margin-top: 15px; color: #ccc; text-decoration: none;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Current Categories Table -->
    <h3 style="color: #fff;">Current Category List</h3>
    <table class="table-dark">
        <thead>
            <tr>
                <th>ID</th>
                <th>Category Name</th>
                <th style="width: 150px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= $cat['id'] ?></td>
                <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                <td>
                    <!-- Edit Button -->
                    <a href="manage_categories.php?edit=<?= $cat['id'] ?>" class="btn-edit">Edit</a>
                    
                    <!-- Delete Form & Button -->
                    <form method="POST" action="manage_categories.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn-delete">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>