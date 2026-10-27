</main>

<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="<?= base_url('public/index.php') ?>" class="brand-logo" style="margin-bottom: 1rem;">
                    <span class="logo-badge" aria-hidden="true">🍽️</span>
                    <span class="brand-name">Urban<span class="brand-highlight">Eats</span></span>
                </a>
                <p>
                    Colombo's state-of-the-art cloud kitchen platform. Delivering restaurant-quality dishes,
                    freshly prepared in dedicated fulfillment kitchens with real-time order tracking.
                </p>
                <p style="font-size: 0.8rem; color: var(--text-muted);">
                    Prices in LKR. Simulated payment gateway (Cash on Delivery, Card, Bank Transfer).
                </p>
            </div>

            <div class="footer-col">
                <h4>Account &amp; Security</h4>
                <ul class="footer-links">
                    <li><a href="<?= base_url('auth/login.php') ?>">Sign In</a></li>
                    <li><a href="<?= base_url('auth/register.php') ?>">Customer Register</a></li>
                    <li><a href="<?= base_url('auth/forgot_password.php') ?>">Password Reset</a></li>
                    <li><a href="<?= base_url('admin/dashboard.php') ?>">Admin Dashboard</a></li>
                    <li><a href="<?= base_url('admin/audit_log.php') ?>">Security Audit Log</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Menu &amp; Kitchen</h4>
                <ul class="footer-links">
                    <li><a href="<?= base_url('menu/index.php') ?>">Browse Food Menu</a></li>
                    <li><a href="<?= base_url('menu/manage.php') ?>">Dish Management (Staff)</a></li>
                    <li><a href="<?= base_url('menu/stock_dashboard.php') ?>">Stock &amp; Wastage Dashboard</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Orders &amp; Dispatch</h4>
                <ul class="footer-links">
                    <li><a href="<?= base_url('orders/cart.php') ?>">Shopping Cart</a></li>
                    <li><a href="<?= base_url('orders/track.php') ?>">Live Order Tracker</a></li>
                    <li><a href="<?= base_url('orders/kitchen_queue.php') ?>">Kitchen Order Queue</a></li>
                    <li><a href="<?= base_url('admin/reports.php') ?>">Sales &amp; Revenue Reports</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>
                &copy; <?= date('Y') ?> UrbanEats Cloud Kitchen Platform. All rights reserved.
            </div>
            <div>
                Artisanal Cloud Kitchens &bull; Colombo, Sri Lanka
            </div>
        </div>
    </div>
</footer>

<!-- Minimal Vanilla JS Enhancement for Responsive Mobile Menu -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('mobileNavToggle');
    const nav = document.getElementById('mainNav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            const isExpanded = nav.classList.toggle('active');
            toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        });
    }
});
</script>

</body>
</html>
