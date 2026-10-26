<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT B: Dish & Catalogue Management Console
 * Primary Owner: Student 2
 *
 * Implements:
 * - Full CRUD with prepared statements
 * - Soft delete (is_active toggle) preserving order history and relational constraints
 * - Dish creation and editing with secure image upload (extension, MIME, size check, unique rename)
 * - Automatic baseline inventory provisioning
 * - Role protection (Staff & Admin) and CSRF security
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Restrict access to Admin and Staff only
require_role(['admin', 'staff']);

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();
$message = '';
$edit_dish = null;

// Helper: Secure Image Upload Processor
function process_dish_image_upload(array $file, ?string &$errorMsg): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadDir = __DIR__ . '/../public/uploads/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $maxSizeBytes = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxSizeBytes) {
        $errorMsg = 'Image file exceeds maximum allowable size of 5 MB.';
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExtensions, true)) {
        $errorMsg = 'Invalid file extension. Only JPG, JPEG, PNG, and WEBP formats are accepted.';
        return null;
    }

    // Verify MIME type using finfo or getimagesize
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    }

    if (!empty($mimeType) && !in_array($mimeType, $allowedMimes, true)) {
        $errorMsg = 'Invalid file format. Uploaded file is not a valid image.';
        return null;
    }

    // Unique filename to prevent overwriting
    $newFileName = 'dish_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destination = $uploadDir . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $errorMsg = 'Failed to save uploaded image. Please verify directory permissions.';
        return null;
    }

    return $newFileName;
}

// 1. Handle Soft Delete / Toggle Active Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['delete', 'toggle_status'])) {
    validate_csrf_or_abort();
    $target_id = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
    $newStatus = isset($_POST['new_status']) ? (int)$_POST['new_status'] : 0;

    if ($target_id) {
        // Soft delete (sets is_active = 0 or toggles) so past orders are never broken
        $stmt = $pdo->prepare("UPDATE dishes SET is_active = ? WHERE id = ?");
        if ($stmt->execute([$newStatus, $target_id])) {
            $actionLabel = $newStatus === 1 ? 'activated' : 'deactivated (soft-deleted)';
            if (function_exists('log_action')) {
                log_action('DISH_STATUS_TOGGLE', "Dish ID #{$target_id} was {$actionLabel}");
            }
            $message = "<div class='alert alert-success'>Dish was {$actionLabel} successfully. Past orders remain intact!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to update dish status.</div>";
        }
    }
}

// 2. Handle Edit Fetch Request (GET)
if (isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM dishes WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_dish = $stmt->fetch();
    }
}

// 3. Handle Add / Edit Dish Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
    validate_csrf_or_abort();

    $name = trim($_POST['name'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : (isset($edit_dish['is_active']) ? (int)$edit_dish['is_active'] : 1);
    $dish_id = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);

    if (empty($name) || !$category_id || $price === false || $price <= 0) {
        $message = "<div class='alert alert-danger'>Please provide a valid dish name, category, and positive price.</div>";
    } else {
        $uploadedFileName = null;
        $uploadError = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedFileName = process_dish_image_upload($_FILES['image'], $uploadError);
            if ($uploadError) {
                $message = "<div class='alert alert-danger'>" . htmlspecialchars($uploadError) . "</div>";
            }
        }

        if (empty($message)) {
            if ($_POST['action'] === 'add') {
                $image_url = $uploadedFileName ?: 'no-image.svg';

                $stmt = $pdo->prepare("INSERT INTO dishes (category_id, name, description, price, image_url, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                if ($stmt->execute([$category_id, $name, $description, $price, $image_url])) {
                    $newDishId = (int)$pdo->lastInsertId();

                    // Automatically provision initial inventory
                    $invStmt = $pdo->prepare("INSERT INTO inventory (dish_id, current_stock, reorder_level) VALUES (?, 25, 5)");
                    $invStmt->execute([$newDishId]);

                    if (function_exists('log_action')) {
                        log_action('DISH_CREATE', "Created dish '{$name}' (ID #{$newDishId}) priced at LKR {$price}");
                    }
                    $message = "<div class='alert alert-success'>Dish '{$name}' added to catalogue with 25 portions initial stock!</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Failed to insert dish into database.</div>";
                }
            } elseif ($_POST['action'] === 'edit' && $dish_id) {
                if ($uploadedFileName !== null) {
                    $stmt = $pdo->prepare("UPDATE dishes SET category_id = ?, name = ?, description = ?, price = ?, image_url = ?, is_active = ? WHERE id = ?");
                    $success = $stmt->execute([$category_id, $name, $description, $price, $uploadedFileName, $is_active, $dish_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE dishes SET category_id = ?, name = ?, description = ?, price = ?, is_active = ? WHERE id = ?");
                    $success = $stmt->execute([$category_id, $name, $description, $price, $is_active, $dish_id]);
                }

                if ($success) {
                    if (function_exists('log_action')) {
                        log_action('DISH_UPDATE', "Updated dish '{$name}' (ID #{$dish_id})");
                    }
                    $message = "<div class='alert alert-success'>Dish '{$name}' updated successfully!</div>";
                    $edit_dish = null; // Clear edit mode after successful update
                } else {
                    $message = "<div class='alert alert-danger'>Failed to update dish details.</div>";
                }
            }
        }
    }
}

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch all dishes including category and inventory stock
$dishes = $pdo->query("
    SELECT d.*, c.name AS category_name, COALESCE(i.current_stock, 0) AS current_stock 
    FROM dishes d 
    JOIN categories c ON d.category_id = c.id 
    LEFT JOIN inventory i ON d.id = i.dish_id 
    ORDER BY d.id DESC
")->fetchAll();
?>

<!-- Dark Theme CSS -->
<style>
    .manage-wrapper { max-width: 1100px; margin: 40px auto; padding: 20px; color: #fff; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .page-title { color: #f39c12; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;}
    
    .form-card { background: #1e1e24; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); margin-bottom: 40px; border: 1px solid #333;}
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; color: #ccc; font-weight: bold;}
    .form-control { width: 100%; padding: 12px; border-radius: 6px; border: 1px solid #444; background: #2a2a35; color: #fff; box-sizing: border-box; }
    .form-control:focus { outline: none; border-color: #f39c12; }
    
    .btn-submit { background: #f39c12; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; font-size: 1.1rem;}
    .btn-submit:hover { background: #e67e22; }

    .table-dark { width: 100%; border-collapse: collapse; margin-top: 20px; background: #1e1e24; border-radius: 10px; overflow: hidden;}
    .table-dark th, .table-dark td { padding: 14px; text-align: left; border-bottom: 1px solid #333; vertical-align: middle;}
    .table-dark th { background: #2a2a35; color: #f39c12; }
    .table-dark tr:hover { background: #2a2a35; }
    
    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .alert-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
    .alert-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }

    .badge-active { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
    .badge-inactive { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
</style>

<div class="manage-wrapper">
    <div class="page-title">
        <h1 style="margin: 0;">⚙️ Staff: Manage Dishes & Catalogue</h1>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="manage_categories.php" class="btn btn-secondary btn-sm" style="background: #3498db; color: #fff; text-decoration: none; padding: 8px 14px; border-radius: 4px; font-weight: bold;">📁 Category Management</a>
            <a href="stock_dashboard.php" class="btn btn-primary btn-sm" style="background: #e67e22; color: #fff; text-decoration: none; padding: 8px 14px; border-radius: 4px; font-weight: bold;">📦 Stock Control</a>
        </div>
    </div>
    
    <?= $message ?>

    <!-- Add / Edit Dish Form -->
    <div class="form-card">
        <h3 style="margin-top: 0; color: #f39c12;"><?= $edit_dish ? '✏️ Edit Dish #' . $edit_dish['id'] : '➕ Add New Dish to Menu' ?></h3>
        <form method="POST" action="manage.php" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="<?= $edit_dish ? 'edit' : 'add' ?>">
            <?php if ($edit_dish): ?>
                <input type="hidden" name="dish_id" value="<?= (int)$edit_dish['id'] ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label>Dish Name *</label>
                    <input type="text" name="name" class="form-control" required value="<?= $edit_dish ? htmlspecialchars($edit_dish['name']) : '' ?>" placeholder="e.g. Cheese Chicken Kottu">
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($edit_dish && (int)$edit_dish['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Price (LKR) *</label>
                    <input type="number" step="0.01" min="1" name="price" class="form-control" required value="<?= $edit_dish ? htmlspecialchars($edit_dish['price']) : '' ?>" placeholder="e.g. 1550.00">
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Ingredients, recipe details, spice level..."><?= $edit_dish ? htmlspecialchars($edit_dish['description']) : '' ?></textarea>
            </div>

            <div class="form-group">
                <label>Dish Image (JPG, PNG, WEBP - Max 5MB) <?= $edit_dish ? '(Leave blank to keep existing image)' : '*' ?></label>
                <?php if ($edit_dish && !empty($edit_dish['image_url'])): ?>
                    <div style="margin-bottom: 8px; font-size: 0.85rem; color: #aaa;">
                        Current image: <strong><?= htmlspecialchars(basename($edit_dish['image_url'])) ?></strong>
                    </div>
                <?php endif; ?>
                <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/webp" <?= $edit_dish ? '' : 'required' ?>>
            </div>

            <?php if ($edit_dish): ?>
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="is_active_chk" name="is_active" value="1" <?= (!empty($edit_dish['is_active'])) ? 'checked' : '' ?>>
                    <label for="is_active_chk" style="margin: 0; color: #fff; cursor: pointer;">Active on Public Menu (Visible to customers)</label>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 15px; margin-top: 10px;">
                <button type="submit" class="btn-submit" style="flex: 2;"><?= $edit_dish ? 'Update Dish Details' : 'Add Dish to Menu' ?></button>
                <?php if ($edit_dish): ?>
                    <a href="manage.php" class="btn" style="flex: 1; background: #555; color: #fff; text-decoration: none; display: flex; align-items: center; justify-content: center; border-radius: 6px; font-weight: bold;">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Current Menu Items Table -->
    <h3 style="color: #fff;">Current Dishes & Catalogue (<?= count($dishes) ?> items)</h3>
    <table class="table-dark">
        <thead>
            <tr>
                <th style="width: 70px;">Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price (LKR)</th>
                <th>Stock</th>
                <th>Status</th>
                <th style="width: 170px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dishes as $dish): ?>
            <?php 
                $imgName = basename($dish['image_url']);
                $imgPath = __DIR__ . '/../public/uploads/' . $imgName;
                $displayImg = (!empty($imgName) && file_exists($imgPath)) 
                    ? base_url('public/uploads/' . $imgName) 
                    : base_url('public/uploads/no-image.svg');
            ?>
            <tr style="<?= empty($dish['is_active']) ? 'opacity: 0.65;' : '' ?>">
                <td>
                    <img src="<?= $displayImg ?>" alt="<?= htmlspecialchars($dish['name']) ?>" width="55" height="55" style="border-radius: 6px; object-fit: cover; background: #111;">
                </td>
                <td>
                    <strong><?= htmlspecialchars($dish['name']) ?></strong>
                    <div style="font-size: 0.78rem; color: #aaa;"><?= htmlspecialchars(substr($dish['description'] ?? '', 0, 45)) ?><?= strlen($dish['description'] ?? '') > 45 ? '...' : '' ?></div>
                </td>
                <td><?= htmlspecialchars($dish['category_name']) ?></td>
                <td style="font-weight: bold; color: #2ecc71;">Rs. <?= number_format($dish['price'], 2) ?></td>
                <td>
                    <span style="font-weight: bold; color: <?= (int)$dish['current_stock'] > 5 ? '#2ecc71' : ((int)$dish['current_stock'] > 0 ? '#f39c12' : '#e74c3c') ?>;">
                        <?= (int)$dish['current_stock'] ?>
                    </span>
                </td>
                <td>
                    <?php if (!empty($dish['is_active'])): ?>
                        <span class="badge-active">Active</span>
                    <?php else: ?>
                        <span class="badge-inactive">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display: flex; gap: 5px; align-items: center;">
                        <!-- Edit Button -->
                        <a href="manage.php?edit=<?= $dish['id'] ?>" class="btn-edit" style="padding: 6px 10px; background: #3498db; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">Edit</a>
        
                        <!-- Soft Delete / Toggle Active (Preserves Past Orders) -->
                        <form method="POST" action="manage.php" style="display:inline-block;" onsubmit="return confirm('<?= $dish['is_active'] ? 'Soft delete this dish? It will be hidden from the menu while preserving all past orders.' : 'Re-activate this dish on the menu?' ?>');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="dish_id" value="<?= $dish['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $dish['is_active'] ? '0' : '1' ?>">
                            <?php if ($dish['is_active']): ?>
                                <button type="submit" class="btn-delete" style="padding: 6px 10px; background: #e74c3c; color: #fff; border: none; border-radius: 4px; font-weight: bold; font-size: 0.85rem; cursor: pointer;">Deactivate</button>
                            <?php else: ?>
                                <button type="submit" style="padding: 6px 10px; background: #2ecc71; color: #fff; border: none; border-radius: 4px; font-weight: bold; font-size: 0.85rem; cursor: pointer;">Activate</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>