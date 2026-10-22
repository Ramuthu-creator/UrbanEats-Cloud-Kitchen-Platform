<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * COMPONENT C: Sales & Revenue Reports
 * Primary Owner: Student 3 (Reserved Space)
 *
 * This file is reserved for Student 3 to implement:
 * - Administrator sales report page (date range, total sales, orders per status, top-selling dishes)
 * - Report queries using JOIN, GROUP BY and aggregate functions
 */

require_once __DIR__ . '/../includes/header.php';
require_role('admin');
?>

<div class="container">
    <div style="margin-bottom: 2rem;">
        <span class="badge badge-customer">Component C &bull; Student 3</span>
        <h1 style="margin-top: 0.4rem;">Sales & Revenue Reports</h1>
        <p>This module is reserved for Student 3 to implement administrator sales analytics.</p>
    </div>

    <div class="card" style="text-align: center; padding: 3rem 2rem;">
        <div style="font-size: 3.5rem; margin-bottom: 1rem;">📊</div>
        <h2>Reports Module Reserved for Student 3</h2>
        <p style="color: var(--text-secondary); max-width: 540px; margin: 0 auto 1.5rem;">
            Student 3 will implement SQL queries with <code>JOIN</code>, <code>GROUP BY</code>, and aggregate functions 
            (<code>SUM()</code>, <code>COUNT()</code>) to show daily/monthly sales, order breakdown, and top-selling dishes.
        </p>
        <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-secondary">
            &larr; Return to Admin Dashboard
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
