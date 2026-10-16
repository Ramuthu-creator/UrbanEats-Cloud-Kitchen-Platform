<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT B: Menu, Catalogue & Inventory
 * Primary Owner: Student 2 (Reserved Space)
 *
 * This file is reserved for Student 2 to implement:
 * - Public menu page with category filter, keyword search, sorting (price, name) and pagination
 * - Dish detail page showing image, description, price and availability
 * - Staff pages to add, edit and delete dishes and categories
 * - Stock dashboard highlighting low-stock and out-of-stock dishes
 */

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="auth-wrapper" style="min-height: calc(100vh - 350px);">
        <div class="card" style="max-width: 650px; text-align: center; padding: 3rem 2rem;">
            <span class="badge badge-staff" style="margin-bottom: 1.25rem;">Component B &bull; Student 2</span>
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🍽️</div>
            <h1 style="font-size: 2rem; margin-bottom: 0.75rem;">Menu, Catalogue & Inventory</h1>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.7;">
                This module is reserved for <strong>Student 2</strong> to build according to the project specifications:
                public menu browsing, search, category filters, dish management, and inventory stock tracking.
            </p>

            <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 1.25rem; text-align: left; margin-bottom: 2rem; font-size: 0.85rem; border: 1px solid var(--border-color);">
                <strong style="color: var(--primary);">Week 1 Integration Contract for Student 2:</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem; color: var(--text-secondary); line-height: 1.6;">
                    <li>Owned Tables: <code>categories</code>, <code>dishes</code>, <code>inventory</code>, <code>stock_movements</code></li>
                    <li>Shared Security Guards: Use <code>require_role(['admin', 'staff'])</code> from <code>includes/auth.php</code></li>
                    <li>Audit Logging: Call <code>log_action('MENU_ITEM_ADDED', ...)</code> on changes</li>
                    <li>Shared Contract: Expose <code>reduce_stock($dish_id, $qty)</code> for Component C checkout</li>
                </ul>
            </div>

            <a href="<?= base_url('public/index.php') ?>" class="btn btn-primary">
                &larr; Return to UrbanEats Home
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
