<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Ordering, Payment & Tracking
 * Primary Owner: Student 3 (Reserved Space)
 *
 * This file is reserved for Student 3 to implement:
 * - Session-based cart with running totals and database re-verification
 * - Checkout form with single database transaction
 * - Order status workflow: Placed -> Preparing -> Ready -> Out for Delivery -> Completed
 * - Kitchen order queue for staff
 * - Sales and revenue reports for administrator
 */

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="auth-wrapper" style="min-height: calc(100vh - 350px);">
        <div class="card" style="max-width: 650px; text-align: center; padding: 3rem 2rem;">
            <span class="badge badge-customer" style="margin-bottom: 1.25rem;">Component C &bull; Student 3</span>
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🛒</div>
            <h1 style="font-size: 2rem; margin-bottom: 0.75rem;">Ordering, Payment & Tracking</h1>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem; line-height: 1.7;">
                This module is reserved for <strong>Student 3</strong> to build according to the project specifications:
                shopping cart, checkout transaction, order tracking timeline, kitchen fulfillment queue, and sales reporting.
            </p>

            <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 1.25rem; text-align: left; margin-bottom: 2rem; font-size: 0.85rem; border: 1px solid var(--border-color);">
                <strong style="color: var(--info);">Week 1 Integration Contract for Student 3:</strong>
                <ul style="margin: 0.5rem 0 0 1.25rem; color: var(--text-secondary); line-height: 1.6;">
                    <li>Owned Tables: <code>orders</code>, <code>order_items</code>, <code>payments</code>, <code>order_status_history</code>, <code>coupons</code></li>
                    <li>Shared Security Guards: Use <code>require_login()</code> and <code>require_role('staff')</code></li>
                    <li>User Session: Read <code>$_SESSION['user_id']</code> and <code>$_SESSION['role']</code></li>
                    <li>Audit Logging: Call <code>log_action('ORDER_PLACED', ...)</code></li>
                    <li>Stock Deduction: Call <code>reduce_stock($dish_id, $qty)</code> inside checkout transaction</li>
                </ul>
            </div>

            <a href="<?= base_url('public/index.php') ?>" class="btn btn-primary">
                &larr; Return to UrbanEats Home
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
