<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Administrator Sales & Revenue Reports
 * Primary Owner: Student 3
 *
 * Implements:
 * - Advanced SQL analytics utilizing JOIN, GROUP BY, and Aggregate functions
 * - Date range filtering with quick presets (Today, Week, Month, All Time)
 * - Key Performance Indicators (Gross Revenue, AOV, Orders, Discounts)
 * - Top-Selling Dishes performance report (portions sold, revenue ranking)
 * - Order Status distribution and Payment Method breakdown
 * - Daily sales trend audit with printable report formatting
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Restricted to Administrators
require_role('admin');

$pdo = get_db();

// --------------------------------------------------------------------
// 1. DATE RANGE & FILTER PARAMETERS
// --------------------------------------------------------------------
$preset = trim($_GET['preset'] ?? '');
$orderTypeFilter = trim($_GET['order_type'] ?? 'ALL');

$today = date('Y-m-d');

if ($preset === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($preset === 'yesterday') {
    $startDate = date('Y-m-d', strtotime('-1 day'));
    $endDate = date('Y-m-d', strtotime('-1 day'));
} elseif ($preset === 'week') {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = $today;
} elseif ($preset === 'month') {
    $startDate = date('Y-m-01'); // First day of current month
    $endDate = $today;
} elseif ($preset === 'all') {
    $startDate = '2026-01-01';
    $endDate = date('Y-m-d', strtotime('+1 day'));
} else {
    // Default: Past 30 days
    $startDate = trim($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
    $endDate = trim($_GET['end_date'] ?? $today);
}

// Validation
if (!strtotime($startDate)) $startDate = date('Y-m-d', strtotime('-30 days'));
if (!strtotime($endDate)) $endDate = $today;

// Base Where Clause for Orders
$baseWhere = "WHERE DATE(o.created_at) >= :start_date AND DATE(o.created_at) <= :end_date";
$baseParams = [
    ':start_date' => $startDate,
    ':end_date'   => $endDate
];

if ($orderTypeFilter !== 'ALL' && in_array($orderTypeFilter, ['DELIVERY', 'PICKUP'], true)) {
    $baseWhere .= " AND o.order_type = :order_type";
    $baseParams[':order_type'] = $orderTypeFilter;
}

// --------------------------------------------------------------------
// 2. EXECUTIVE KPI AGGREGATES
// --------------------------------------------------------------------
$kpiSql = "SELECT 
               COUNT(*) AS total_orders,
               SUM(CASE WHEN o.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_orders,
               SUM(CASE WHEN o.status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled_orders,
               SUM(CASE WHEN o.status = 'COMPLETED' THEN o.total_amount ELSE 0 END) AS total_revenue,
               SUM(CASE WHEN o.status = 'COMPLETED' THEN o.subtotal ELSE 0 END) AS gross_subtotal,
               SUM(CASE WHEN o.status = 'COMPLETED' THEN o.discount_amount ELSE 0 END) AS total_discounts,
               SUM(CASE WHEN o.status = 'COMPLETED' THEN o.delivery_fee ELSE 0 END) AS total_delivery_fees,
               ROUND(AVG(CASE WHEN o.status = 'COMPLETED' THEN o.total_amount ELSE NULL END), 2) AS average_order_value
           FROM orders o
           $baseWhere";
$kpiStmt = $pdo->prepare($kpiSql);
$kpiStmt->execute($baseParams);
$kpis = $kpiStmt->fetch() ?: [];

$totalRevenue = (float)($kpis['total_revenue'] ?? 0);
$completedOrders = (int)($kpis['completed_orders'] ?? 0);
$totalOrders = (int)($kpis['total_orders'] ?? 0);
$aov = (float)($kpis['average_order_value'] ?? 0);
$totalDiscounts = (float)($kpis['total_discounts'] ?? 0);

// --------------------------------------------------------------------
// 3. TOP-SELLING DISHES REPORT (JOIN, GROUP BY, AGGREGATE FUNCTIONS)
// Viva Requirement: Explain this SQL in technical viva!
// --------------------------------------------------------------------
$topDishesSql = "SELECT 
                     d.id AS dish_id,
                     d.name AS dish_name,
                     c.name AS category_name,
                     d.price AS catalog_price,
                     COUNT(DISTINCT oi.order_id) AS distinct_orders,
                     SUM(oi.quantity) AS total_units_sold,
                     SUM(oi.subtotal) AS total_dish_revenue,
                     ROUND(AVG(oi.unit_price), 2) AS avg_realized_price
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 JOIN dishes d ON oi.dish_id = d.id
                 JOIN categories c ON d.category_id = c.id
                 $baseWhere AND o.status = 'COMPLETED'
                 GROUP BY d.id, d.name, c.name, d.price
                 ORDER BY total_dish_revenue DESC
                 LIMIT 10";
$topStmt = $pdo->prepare($topDishesSql);
$topStmt->execute($baseParams);
$topDishes = $topStmt->fetchAll();

// --------------------------------------------------------------------
// 4. ORDERS BY STATUS BREAKDOWN (GROUP BY status)
// --------------------------------------------------------------------
$statusSql = "SELECT 
                  o.status,
                  COUNT(*) AS count_status,
                  SUM(o.total_amount) AS status_value
              FROM orders o
              $baseWhere
              GROUP BY o.status
              ORDER BY count_status DESC";
$statusStmt = $pdo->prepare($statusSql);
$statusStmt->execute($baseParams);
$statusBreakdown = $statusStmt->fetchAll();

// --------------------------------------------------------------------
// 5. REVENUE BY PAYMENT METHOD (JOIN payments, GROUP BY payment_method)
// --------------------------------------------------------------------
$paymentSql = "SELECT 
                   COALESCE(p.payment_method, 'UNSPECIFIED') AS payment_method,
                   COUNT(DISTINCT o.id) AS orders_count,
                   SUM(o.total_amount) AS method_revenue
               FROM orders o
               LEFT JOIN payments p ON o.id = p.order_id
               $baseWhere AND o.status = 'COMPLETED'
               GROUP BY p.payment_method
               ORDER BY method_revenue DESC";
$payStmt = $pdo->prepare($paymentSql);
$payStmt->execute($baseParams);
$paymentBreakdown = $payStmt->fetchAll();

// --------------------------------------------------------------------
// 6. DAILY SALES TREND TABLE (GROUP BY DATE)
// --------------------------------------------------------------------
$dailySql = "SELECT 
                 DATE(o.created_at) AS sale_date,
                 COUNT(*) AS order_count,
                 SUM(o.subtotal) AS day_subtotal,
                 SUM(o.discount_amount) AS day_discount,
                 SUM(o.delivery_fee) AS day_delivery_fee,
                 SUM(o.total_amount) AS day_revenue
             FROM orders o
             $baseWhere AND o.status = 'COMPLETED'
             GROUP BY DATE(o.created_at)
             ORDER BY sale_date DESC";
$dailyStmt = $pdo->prepare($dailySql);
$dailyStmt->execute($baseParams);
$dailyTrends = $dailyStmt->fetchAll();

// Centralized audit log: Administrator viewed sales report
log_action('VIEW_SALES_REPORT', "Administrator reviewed sales analytics for {$startDate} to {$endDate}", $staffUserId);

$pageTitle = 'Sales & Revenue Analytics - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin-top: 1rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span class="badge badge-admin">Business Intelligence &bull; Financial Analytics</span>
            <h1 style="margin-top: 0.4rem; font-size: 2.2rem;">Sales & Revenue Reports</h1>
            <p style="margin-bottom: 0;">Comprehensive business intelligence, order lifecycle metrics, and dish sales ranking.</p>
        </div>
        <div class="no-print" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary" onclick="window.print();">
                🖨️ Print Analytics Report
            </button>
            <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-secondary">
                &larr; Admin Dashboard
            </a>
        </div>
    </div>

    <!-- Date Range & Preset Filtering Toolbar (No Print) -->
    <div class="card no-print" style="margin-bottom: 2rem; padding: 1.5rem; background: var(--bg-surface);">
        <form method="GET" action="<?= base_url('admin/reports.php') ?>" style="display: flex; flex-direction: column; gap: 1.25rem;">
            
            <!-- Quick Preset Shortcuts -->
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-right: 0.5rem;">Quick Presets:</span>
                <a href="<?= base_url('admin/reports.php?preset=today') ?>" class="btn btn-sm <?= ($preset === 'today') ? 'btn-primary' : 'btn-secondary' ?>">Today</a>
                <a href="<?= base_url('admin/reports.php?preset=yesterday') ?>" class="btn btn-sm <?= ($preset === 'yesterday') ? 'btn-primary' : 'btn-secondary' ?>">Yesterday</a>
                <a href="<?= base_url('admin/reports.php?preset=week') ?>" class="btn btn-sm <?= ($preset === 'week') ? 'btn-primary' : 'btn-secondary' ?>">Last 7 Days</a>
                <a href="<?= base_url('admin/reports.php?preset=month') ?>" class="btn btn-sm <?= ($preset === 'month') ? 'btn-primary' : 'btn-secondary' ?>">This Month</a>
                <a href="<?= base_url('admin/reports.php?preset=all') ?>" class="btn btn-sm <?= ($preset === 'all') ? 'btn-primary' : 'btn-secondary' ?>">All Time</a>
            </div>

            <!-- Date Range Controls -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.85rem;">From Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.85rem;">To Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.85rem;">Fulfillment Filter</label>
                    <select name="order_type" class="form-control">
                        <option value="ALL" <?= ($orderTypeFilter === 'ALL') ? 'selected' : '' ?>>All Order Types</option>
                        <option value="DELIVERY" <?= ($orderTypeFilter === 'DELIVERY') ? 'selected' : '' ?>>Home Delivery</option>
                        <option value="PICKUP" <?= ($orderTypeFilter === 'PICKUP') ? 'selected' : '' ?>>Counter Pickup</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.65rem 1.25rem;">
                        📊 Generate Analytics
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Active Report Period Banner -->
    <div style="background: rgba(0, 201, 136, 0.08); border-left: 4px solid var(--primary); padding: 0.85rem 1.25rem; border-radius: 4px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            Reporting Period: <strong><?= date('d M Y', strtotime($startDate)) ?></strong> &mdash; <strong><?= date('d M Y', strtotime($endDate)) ?></strong>
            <?php if ($orderTypeFilter !== 'ALL'): ?>
                &bull; Filter: <strong><?= e($orderTypeFilter) ?></strong>
            <?php endif; ?>
        </div>
        <div style="font-size: 0.85rem; color: var(--text-muted);">
            Generated: <?= date('d M Y, h:i A') ?>
        </div>
    </div>

    <!-- 1. Key Performance Indicator Cards (KPIs) -->
    <div class="stats-grid" style="margin-bottom: 2.5rem;">
        <div class="stat-card">
            <div class="stat-icon primary">💰</div>
            <div>
                <div class="stat-value"><?= format_price($totalRevenue) ?></div>
                <div class="stat-label">Net Sales Revenue (Completed)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">📦</div>
            <div>
                <div class="stat-value"><?= $completedOrders ?> / <?= $totalOrders ?></div>
                <div class="stat-label">Completed / Total Orders</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">📈</div>
            <div>
                <div class="stat-value"><?= format_price($aov) ?></div>
                <div class="stat-label">Average Order Value (AOV)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">🎟️</div>
            <div>
                <div class="stat-value"><?= format_price($totalDiscounts) ?></div>
                <div class="stat-label">Total Coupon Discounts Granted</div>
            </div>
        </div>
    </div>

    <!-- 2. Top-Selling Dishes Performance Report -->
    <div class="card" style="margin-bottom: 2.5rem;">
        <div class="card-header">
            <div>
                <h2 class="card-title">🍲 Top-Selling Dishes & Revenue Ranking</h2>
                <p class="card-subtitle">
                    Calculated using SQL <code>JOIN dishes</code>, <code>GROUP BY dishes.id</code>, and aggregate functions <code>SUM(quantity)</code> &amp; <code>SUM(subtotal)</code>
                </p>
            </div>
            <span class="badge badge-primary">Top 10 Menu Items</span>
        </div>

        <div class="table-responsive">
            <table class="table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 35%;">Dish Name & Category</th>
                        <th style="text-align: center; width: 15%;">Catalog Price</th>
                        <th style="text-align: center; width: 15%;">Portions Sold</th>
                        <th style="text-align: center; width: 15%;">Distinct Orders</th>
                        <th style="text-align: right; width: 15%;">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topDishes)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                No completed dish sales found within the selected date range.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rank = 1; foreach ($topDishes as $dish): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--primary);">
                                    <?= $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : '#' . $rank)) ?>
                                </td>
                                <td>
                                    <strong style="color: var(--text-primary); font-size: 1rem;"><?= e($dish['dish_name']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= e($dish['category_name']) ?></div>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <?= format_price((float)$dish['catalog_price']) ?>
                                </td>
                                <td style="text-align: center; font-weight: 700; font-size: 1.05rem; color: var(--text-primary);">
                                    <?= (int)$dish['total_units_sold'] ?>
                                </td>
                                <td style="text-align: center; color: var(--text-secondary);">
                                    <?= (int)$dish['distinct_orders'] ?> orders
                                </td>
                                <td style="text-align: right; font-weight: 800; color: var(--primary); white-space: nowrap; font-size: 1.05rem;">
                                    <?= format_price((float)$dish['total_dish_revenue']) ?>
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Grid: Order Status Distribution + Payment Methods -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;" class="status-grid">
        
        <!-- Order Status Breakdown -->
        <div class="card">
            <h2 class="card-title" style="font-size: 1.25rem; margin-bottom: 0.5rem;">📊 Order Status Distribution</h2>
            <p class="card-subtitle" style="margin-bottom: 1.25rem;">Volume and value across order lifecycle stages</p>

            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Status Stage</th>
                            <th style="text-align: center;">Count</th>
                            <th style="text-align: right;">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($statusBreakdown)): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No orders recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($statusBreakdown as $sb): ?>
                                <?php
                                $badgeColor = match ($sb['status']) {
                                    'COMPLETED'        => 'badge-success',
                                    'PLACED'           => 'badge-primary',
                                    'PREPARING'        => 'badge-warning',
                                    'READY'            => 'badge-info',
                                    'OUT_FOR_DELIVERY',
                                    'COLLECTED'        => 'badge-customer',
                                    'CANCELLED'        => 'badge-danger',
                                    default            => 'badge-secondary'
                                };
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= $badgeColor ?>" style="font-size: 0.75rem;">
                                            <?= e($sb['status']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center; font-weight: 600;">
                                        <?= (int)$sb['count_status'] ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 600; color: var(--primary);">
                                        <?= format_price((float)$sb['status_value']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Method Distribution -->
        <div class="card">
            <h2 class="card-title" style="font-size: 1.25rem; margin-bottom: 0.5rem;">💳 Revenue by Payment Method</h2>
            <p class="card-subtitle" style="margin-bottom: 1.25rem;">Completed transactions across payment channels</p>

            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Payment Channel</th>
                            <th style="text-align: center;">Orders</th>
                            <th style="text-align: right;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paymentBreakdown)): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No completed payments recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($paymentBreakdown as $pb): ?>
                                <tr>
                                    <td>
                                        <strong><?= e(str_replace('_', ' ', $pb['payment_method'])) ?></strong>
                                    </td>
                                    <td style="text-align: center; font-weight: 600;">
                                        <?= (int)$pb['orders_count'] ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                        <?= format_price((float)$pb['method_revenue']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- 4. Daily Sales Breakdown Table -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">📅 Daily Revenue & Sales Breakdown</h2>
                <p class="card-subtitle">Detailed ledger of completed orders, subtotal values, and discounts per date</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th style="text-align: center;">Orders</th>
                        <th style="text-align: right;">Gross Subtotal</th>
                        <th style="text-align: right;">Discounts</th>
                        <th style="text-align: right;">Delivery Fees</th>
                        <th style="text-align: right;">Net Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyTrends)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                No completed sales data available for this date range.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyTrends as $day): ?>
                            <tr>
                                <td>
                                    <strong><?= date('D, d M Y', strtotime($day['sale_date'])) ?></strong>
                                </td>
                                <td style="text-align: center; font-weight: 600;">
                                    <?= (int)$day['order_count'] ?>
                                </td>
                                <td style="text-align: right; color: var(--text-secondary);">
                                    <?= format_price((float)$day['day_subtotal']) ?>
                                </td>
                                <td style="text-align: right; color: var(--success);">
                                    <?= (float)$day['day_discount'] > 0 ? '&minus; ' . format_price((float)$day['day_discount']) : 'Rs. 0.00' ?>
                                </td>
                                <td style="text-align: right; color: var(--text-secondary);">
                                    <?= format_price((float)$day['day_delivery_fee']) ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                    <?= format_price((float)$day['day_revenue']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
@media (max-width: 860px) {
    .status-grid {
        grid-template-columns: 1fr !important;
    }
}

@media print {
    .no-print, .site-header, .site-footer {
        display: none !important;
    }
    body {
        background: #fff !important;
        color: #000 !important;
    }
    .card {
        background: #fff !important;
        border: 1px solid #ccc !important;
        color: #000 !important;
        box-shadow: none !important;
    }
    .table th, .table td {
        color: #000 !important;
        border-color: #ddd !important;
    }
    h1, h2, h3, strong {
        color: #000 !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
