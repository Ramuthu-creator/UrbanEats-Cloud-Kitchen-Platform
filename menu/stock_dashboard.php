<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Restrict to Staff and Admin only
require_role(['admin', 'staff']);

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();
$message = '';

// Handle Stock Adjustments (Restock / Wastage)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    validate_csrf_or_abort();

    $dish_id = filter_input(INPUT_POST, 'dish_id', FILTER_VALIDATE_INT);
    $adj_type = $_POST['adj_type'] ?? ''; // 'restock' or 'wastage'
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $reason = trim($_POST['reason'] ?? '');
    $user_id = current_user_id() ?? 1;

    if ($dish_id && $quantity > 0 && !empty($reason) && in_array($adj_type, ['restock', 'wastage'], true)) {
        $movement_type = ($adj_type === 'restock') ? 'RESTOCK' : 'WASTAGE';
        $qty_change = ($adj_type === 'wastage') ? -$quantity : $quantity;

        try {
            // Transaction-safe
            $pdo->beginTransaction();

            // Fetch current stock
            $checkStmt = $pdo->prepare("SELECT current_stock FROM inventory WHERE dish_id = ? FOR UPDATE");
            $checkStmt->execute([$dish_id]);
            $currentStock = $checkStmt->fetchColumn();

            if ($currentStock === false) {
                // Initialize inventory row if missing
                $initStmt = $pdo->prepare("INSERT INTO inventory (dish_id, current_stock, reorder_level) VALUES (?, 0, 5)");
                $initStmt->execute([$dish_id]);
                $currentStock = 0;
            }

            if ($adj_type === 'wastage' && (int)$currentStock < $quantity) {
                throw new Exception("Cannot record wastage of {$quantity} portions: only {$currentStock} portion(s) available in stock.");
            }

            // Inventory Update
            $stmt_inv = $pdo->prepare("UPDATE inventory SET current_stock = current_stock + ? WHERE dish_id = ? AND (current_stock + ?) >= 0");
            $stmt_inv->execute([$qty_change, $dish_id, $qty_change]);

            // Stock Movement Log
            $stmt_log = $pdo->prepare("INSERT INTO stock_movements (dish_id, user_id, movement_type, quantity, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt_log->execute([$dish_id, $user_id, $movement_type, $quantity, $reason]);

            // Audit log tracking
            if (function_exists('log_action')) {
                $dishNameStmt = $pdo->prepare("SELECT name FROM dishes WHERE id = ?");
                $dishNameStmt->execute([$dish_id]);
                $dishName = $dishNameStmt->fetchColumn() ?: "Dish #{$dish_id}";
                log_action('STOCK_ADJUSTMENT', "{$movement_type} of {$quantity} units for '{$dishName}'. Reason: {$reason}");
            }

            $pdo->commit();
            $message = "<div class='alert alert-success'>Stock updated successfully!</div>";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "<div class='alert alert-danger'>Error updating stock: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Invalid input. Please provide a valid quantity and reason.</div>";
    }
}

// 2. Fetch Dashboard Data (JOIN dishes with inventory)
$sql = "SELECT d.id as dish_id, d.name, COALESCE(i.current_stock, 0) AS current_stock, COALESCE(i.reorder_level, 5) AS reorder_level 
        FROM dishes d 
        LEFT JOIN inventory i ON d.id = i.dish_id 
        WHERE d.is_active = 1 
        ORDER BY COALESCE(i.current_stock, 0) ASC";
$dishes = $pdo->query($sql)->fetchAll();
?>

<style>
    .dashboard-wrapper { max-width: 1100px; margin: 40px auto; padding: 20px; color: #fff; font-family: sans-serif; }
    .page-title { color: #f39c12; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 30px; }
    
    .panel-box { background: #1e1e24; padding: 25px; border-radius: 10px; border: 1px solid #333; margin-bottom: 30px;}
    .form-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;}
    .form-group { flex: 1; min-width: 200px; display: flex; flex-direction: column;}
    .form-group label { margin-bottom: 8px; color: #ccc; font-weight: bold;}
    .form-control { padding: 10px; border-radius: 6px; border: 1px solid #444; background: #2a2a35; color: #fff; }
    .btn-submit { padding: 10px 20px; background: #3498db; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s;}
    .btn-submit:hover { background: #2980b9; }

    .table-dark { width: 100%; border-collapse: collapse; background: #1e1e24; border-radius: 10px; overflow: hidden;}
    .table-dark th, .table-dark td { padding: 15px; text-align: left; border-bottom: 1px solid #333; }
    .table-dark th { background: #2a2a35; color: #f39c12; }
    
    .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
    .badge-good { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
    .badge-low { background: rgba(243, 156, 18, 0.2); color: #f39c12; border: 1px solid #f39c12; }
    .badge-out { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
    
    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .alert-success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
    .alert-danger { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
</style>

<div class="dashboard-wrapper">
    <h1 class="page-title">📦 Inventory & Stock Dashboard</h1>
    
    <?= $message ?>

    <!-- Stock Adjustment Form -->
    <div class="panel-box">
        <h3 style="margin-top: 0; color: #3498db;">➕ Make a Stock Adjustment</h3>
        <form method="POST" action="stock_dashboard.php" class="form-row">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="adjust_stock">
            
            <div class="form-group">
                <label>Select Dish</label>
                <select name="dish_id" class="form-control" required>
                    <option value="">-- Choose a dish --</option>
                    <?php foreach ($dishes as $d): ?>
                        <option value="<?= $d['dish_id'] ?>"><?= htmlspecialchars($d['name']) ?> (Current: <?= $d['current_stock'] ?? 0 ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="flex: 0.5;">
                <label>Type</label>
                <select name="adj_type" class="form-control" required>
                    <option value="restock">Restock (Add)</option>
                    <option value="wastage">Wastage (Remove)</option>
                </select>
            </div>

            <div class="form-group" style="flex: 0.5;">
                <label>Quantity</label>
                <input type="number" name="quantity" class="form-control" min="1" required placeholder="e.g. 10">
            </div>

            <div class="form-group" style="flex: 1.5;">
                <label>Reason (Who & Why)</label>
                <input type="text" name="reason" class="form-control" required placeholder="e.g. Restocked - Fresh batch prepared">
            </div>

            <button type="submit" class="btn-submit" style="margin-bottom: 2px;">Update Stock</button>
        </form>
    </div>

    <!-- Inventory Status Table -->
    <h3 style="color: #fff;">Current Stock Levels</h3>
    <table class="table-dark">
        <thead>
            <tr>
                <th>Dish Name</th>
                <th>Current Stock</th>
                <th>Reorder Level</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dishes as $dish): 
                $qty = (int)$dish['current_stock'];
                $reorder = (int)($dish['reorder_level'] ?? 5);
                
                // check stock status and assign badge
                if ($qty <= 0) {
                    $badge = "<span class='badge badge-out'>Out of Stock</span>";
                } elseif ($qty <= $reorder) {
                    $badge = "<span class='badge badge-low'>Low Stock</span>";
                } else {
                    $badge = "<span class='badge badge-good'>Good</span>";
                }
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($dish['name']) ?></strong></td>
                <td style="font-size: 1.2rem; font-weight: bold;"><?= $qty ?></td>
                <td><?= $reorder ?></td>
                <td><?= $badge ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>