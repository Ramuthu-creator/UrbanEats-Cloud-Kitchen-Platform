<?php
// 1. Include required files
require_once __DIR__ . '/../config/db.php';

// Restrict access to Admin and Staff only
require_once __DIR__ . '/../includes/auth.php';
// (Assuming this function exists in auth.php. If not, keep it commented)
// require_role(['admin', 'staff']); 

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();
$message = '';

$edit_dish = null; // Edit 

// Delete data if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delete_id = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        $stmt = $pdo->prepare("DELETE FROM dishes WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $message = "<div class='alert alert-success'>Dish removed successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to delete dish.</div>";
        }
    }
}

//  Edit data if requested
if (isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM dishes WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_dish = $stmt->fetch();
    }
}

// Data and Image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $description = trim($_POST['description']);
    $image_url = '';

    // Image upload process
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../public/uploads/';
        $file_name = $_FILES['image']['name'];
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Generate a unique name for the image to prevent overwriting
            $new_file_name = uniqid('dish_') . '.' . $file_extension;
            $destination = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $image_url = $new_file_name; // Prepare the file name for the database
            } else {
                $message = "<div class='alert alert-danger'>Failed to move uploaded file. Check folder permissions.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Invalid file type. Only JPG, PNG, and WEBP are allowed.</div>";
        }
    }

    // Insert data into the database
    if (empty($message) || strpos($message, 'success') !== false) {
        $stmt = $pdo->prepare("INSERT INTO dishes (category_id, name, description, price, image_url, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        if ($stmt->execute([$category_id, $name, $description, $price, $image_url])) {
            $message = "<div class='alert alert-success'>Dish added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to add dish to database.</div>";
        }
    }
}

// Fetch categories for the dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch existing dishes for the management table
$dishes = $pdo->query("SELECT d.*, c.name as category_name FROM dishes d JOIN categories c ON d.category_id = c.id ORDER BY d.id DESC")->fetchAll();
?>

<!-- Dark Theme CSS -->
<style>
    .manage-wrapper { max-width: 1000px; margin: 40px auto; padding: 20px; color: #fff; font-family: sans-serif; }
    .page-title { color: #f39c12; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 30px; }
    
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
    
    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .alert-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
    .alert-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
</style>

<div class="manage-wrapper">
    <h1 class="page-title">⚙️ Staff: Manage Dishes</h1>
    <a href="manage_categories.php" style="color: #3498db; margin-bottom: 20px; display: block;">👉 Go to Category Management</a>
    
    <?= $message ?>

    <!-- Add New Dish Form -->
    <div class="form-card">
        <h3>Add New Dish</h3><br>
        <form method="POST" action="manage.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label>Dish Name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Chicken Kottu">
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Price (LKR)</label>
                <input type="number" step="0.01" name="price" class="form-control" required placeholder="e.g. 1500.00">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Brief description..."></textarea>
            </div>

            <div class="form-group">
                <label>Upload Image (JPG, PNG, WEBP)</label>
                <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/webp" required>
            </div>

            <button type="submit" class="btn-submit">Add Dish to Menu</button>
        </form>
    </div>

    <!-- Current Menu Items Table -->
    <h3 style="color: #fff;">Current Menu Items</h3>
    <table class="table-dark">
        <thead>
            
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price (LKR)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dishes as $dish): ?>
            <tr>
                <td>
                    <?php if(!empty($dish['image_url'])): ?>
                        <img src="<?= base_url('public/uploads/' . $dish['image_url']) ?>" width="60" style="border-radius: 5px;">
                    <?php else: ?>
                        <span style="color:#777;">No Image</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($dish['name']) ?></td>
                <td><?= htmlspecialchars($dish['category_name']) ?></td>
                <td><?= number_format($dish['price'], 2) ?></td>
                <td>
                    <span style="color: <?= $dish['is_active'] ? '#2ecc71' : '#e74c3c' ?>;">
                        <?= $dish['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                 <!-- Edit Botton -->
                  <a href="manage.php?edit=<?= $dish['id'] ?>" class="btn-edit" style="padding: 6px 12px; background: #3498db; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; margin-right: 5px;">Edit</a>
    
                 <!-- Botton Delete  -->
                  <form method="POST" action="manage.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="dish_id" value="<?= $dish['id'] ?>">
                  <button type="submit" class="btn-delete" style="padding: 6px 12px; background: #e74c3c; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Delete</button>
                </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>