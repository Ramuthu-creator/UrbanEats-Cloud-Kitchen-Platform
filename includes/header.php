<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared Semantic Header & Role-Adaptive Navigation
 * 
 * Used by all components across the entire platform.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? APP_NAME . ' - Premium Cloud Kitchen';
$currentRole = current_user_role();
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="UrbanEats - Colombo's premier multi-cuisine cloud kitchen delivering authentic Sri Lankan, Biryani, and Street Food.">
    <!-- Master Shared Stylesheet -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🍛</text></svg>">
</head>
<body>

<header class="site-header" role="banner">
    <div class="container">
        <div class="header-inner">
            <!-- Brand Identity -->
            <a href="<?= base_url('public/index.php') ?>" class="brand-logo" aria-label="UrbanEats Cloud Kitchen Home">
                <span class="logo-badge" aria-hidden="true">🍛</span>
                <span class="brand-name">Urban<span class="brand-highlight">Eats</span></span>
            </a>

            <!-- Mobile Navigation Toggle -->
            <button type="button" class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle navigation menu" aria-expanded="false">
                ☰
            </button>

            <!-- Role-Aware Navigation Bar -->
            <nav class="main-nav" id="mainNav" role="navigation" aria-label="Primary Navigation">
                <ul class="nav-links">
                    <li class="nav-item">
                        <a href="<?= base_url('public/index.php') ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= base_url('menu/index.php') ?>">Menu</a>
                    </li>

                    <?php if ($currentRole === 'customer'): ?>
                        <li class="nav-item">
                            <a href="<?= base_url('orders/cart.php') ?>">Cart 🛒</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('orders/history.php') ?>">My Orders</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('orders/track.php') ?>">Track Order</a>
                        </li>

                    <?php elseif ($currentRole === 'staff'): ?>
                        
                        <li class="nav-item">
                            <a href="<?= base_url('menu/manage.php') ?>">Manage Dishes</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('menu/stock_dashboard.php') ?>">Stock Control</a>
                        </li>

                    <?php elseif ($currentRole === 'admin'): ?>
                        <li class="nav-item">
                            <a href="<?= base_url('admin/dashboard.php') ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('admin/users.php') ?>">Users</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('admin/audit_log.php') ?>">Audit Log</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('admin/reports.php') ?>">Sales Reports</a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Authentication Actions -->
                <div class="nav-auth">
                    <?php if (is_logged_in()): ?>
                        <div class="user-pill" title="Logged in as <?= e($currentUser['full_name'] ?? 'User') ?>">
                            <span class="badge badge-<?= e($currentRole) ?>"><?= e(strtoupper($currentRole)) ?></span>
                            <span class="user-name"><?= e($currentUser['full_name'] ?? $_SESSION['user_name'] ?? 'User') ?></span>
                        </div>
                        <a href="<?= base_url('auth/profile.php') ?>" class="btn btn-secondary btn-sm" aria-label="My Profile">Profile</a>
                        <a href="<?= base_url('auth/logout.php') ?>" class="btn btn-danger btn-sm" aria-label="Log Out">Logout</a>
                    <?php else: ?>
                        <a href="<?= base_url('auth/login.php') ?>" class="btn btn-secondary btn-sm">Sign In</a>
                        <a href="<?= base_url('auth/register.php') ?>" class="btn btn-primary btn-sm">Register</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </div>
</header>

<!-- Global Flash Notifications -->
<?= render_flash_messages() ?>

<main class="main-content" role="main">
