<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Public Dining Homepage & Multi-Brand Showcase
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = get_db();

// 1. Fetch Active Menu Categories
$categories = [];
try {
    $catStmt = $pdo->query("
        SELECT c.*, COUNT(d.id) AS dish_count 
        FROM categories c 
        LEFT JOIN dishes d ON c.id = d.category_id AND d.is_active = 1 
        WHERE c.is_active = 1 
        GROUP BY c.id 
        ORDER BY c.id ASC
    ");
    $categories = $catStmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// 2. Fetch Featured Dishes
$featuredDishes = [];
try {
    $dishStmt = $pdo->query("
        SELECT d.*, c.name AS category_name 
        FROM dishes d 
        JOIN categories c ON d.category_id = c.id 
        WHERE d.is_active = 1 
        ORDER BY d.id ASC 
        LIMIT 4
    ");
    $featuredDishes = $dishStmt->fetchAll();
} catch (Exception $e) {
    $featuredDishes = [];
}

$categoryIcons = [
    'signature-biryani'       => '🍛',
    'kottu-roti'              => '🥘',
    'rice-and-curry'          => '🍚',
    'appetizers-bites'        => '🍤',
    'desserts-beverages'      => '🍨'
];
?>

<div class="container">
    <!-- Hero Section -->
    <section style="padding: 4rem 0 3rem; text-align: center; position: relative;">
        <span class="contract-badge" style="margin-bottom: 1.25rem;">
            Fresh Daily &bull; 30-Min Express Delivery in Colombo
        </span>
        <h1 style="font-size: 3.25rem; font-weight: 800; letter-spacing: -0.025em; margin-bottom: 1.25rem; line-height: 1.15;">
            Artisanal Dining, <br>
            <span style="color: var(--primary);">Delivered Fresh to Your Door</span>
        </h1>
        <p style="max-width: 640px; margin: 0 auto 2.25rem; font-size: 1.1rem; color: var(--text-secondary); line-height: 1.7;">
            Colombo's multi-brand cloud kitchen delivering authentic Sri Lankan specialties, slow-cooked biryanis, and street food classics directly from dedicated hygienic fulfillment kitchens.
        </p>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary btn-lg">
                Explore Full Menu 🍽️
            </a>
            <?php if (!is_logged_in()): ?>
                <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline btn-lg">
                    Sign In
                </a>
                <a href="<?= base_url('auth/register.php') ?>" class="btn btn-secondary btn-lg">
                    Create Account
                </a>
            <?php elseif (has_role('admin')): ?>
                <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-outline btn-lg">
                    Admin Dashboard &rarr;
                </a>
            <?php elseif (has_role('staff')): ?>
                <a href="<?= base_url('orders/kitchen_queue.php') ?>" class="btn btn-outline btn-lg">
                    Kitchen Queue &rarr;
                </a>
            <?php else: ?>
                <a href="<?= base_url('orders/history.php') ?>" class="btn btn-outline btn-lg">
                    My Order History &rarr;
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Cuisine Categories Showcase -->
    <?php if (!empty($categories)): ?>
        <section style="margin: 2rem 0 3.5rem;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <span class="contract-badge" style="margin-bottom: 0.75rem;">Multi-Brand Kitchens</span>
                <h2 style="font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; margin-top: 0.35rem;">Explore by Cuisine</h2>
                <p style="max-width: 580px; margin: 0 auto; color: var(--text-secondary); font-size: 0.95rem;">
                    Prepared by specialized kitchen stations using traditional slow-cooking methods
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem;">
                <?php foreach ($categories as $cat): 
                    $icon = $categoryIcons[$cat['slug']] ?? '🍽️';
                ?>
                    <a href="<?= base_url('menu/index.php?category=' . (int)$cat['id']) ?>" class="card" style="text-align: center; padding: 1.5rem; text-decoration: none; color: inherit; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: var(--transition);">
                        <div style="font-size: 2.25rem; margin-bottom: 0.75rem;"><?= $icon ?></div>
                        <h3 style="font-size: 1.1rem; margin-bottom: 0.25rem; color: var(--text-primary);"><?= htmlspecialchars($cat['name']) ?></h3>
                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?= (int)$cat['dish_count'] ?> Specialties</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Featured Chef Specials -->
    <?php if (!empty($featuredDishes)): ?>
        <section style="margin: 3.5rem 0 3rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span class="contract-badge" style="margin-bottom: 0.5rem;">Chef's Recommendations</span>
                    <h2 style="font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; margin-top: 0.35rem;">Featured Culinary Creations</h2>
                    <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 0;">Popular favorites freshly prepared to order</p>
                </div>
                <a href="<?= base_url('menu/index.php') ?>" class="btn btn-outline btn-sm">View Complete Menu &rarr;</a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <?php foreach ($featuredDishes as $dish): 
                    $imgName = basename($dish['image_url'] ?? '');
                    $imgPath = __DIR__ . '/uploads/' . $imgName;
                    $displayImg = (!empty($imgName) && file_exists($imgPath)) 
                        ? base_url('public/uploads/' . $imgName) 
                        : base_url('public/uploads/no-image.svg');
                ?>
                    <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
                        <img src="<?= $displayImg ?>" alt="<?= htmlspecialchars($dish['name']) ?>" style="width: 100%; height: 180px; object-fit: cover; background: var(--bg-surface);">
                        <div style="padding: 1.25rem; display: flex; flex-direction: column; flex: 1;">
                            <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.35rem;">
                                <?= htmlspecialchars($dish['category_name']) ?>
                            </span>
                            <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem; color: var(--text-primary); font-family: var(--font-heading);">
                                <?= htmlspecialchars($dish['name']) ?>
                            </h3>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 1.25rem; flex: 1;">
                                <?= htmlspecialchars(mb_strimwidth($dish['description'] ?? '', 0, 85, '...')) ?>
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                                <span style="font-size: 1.25rem; font-weight: 800; color: var(--primary);">
                                    <?= format_price((float)$dish['price']) ?>
                                </span>
                                <a href="<?= base_url('menu/detail.php?id=' . (int)$dish['id']) ?>" class="btn btn-primary btn-sm">
                                    Order Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Why Choose UrbanEats (Customer Value Proposition) -->
    <section style="margin: 4rem 0 3rem;">
        <div style="text-align: center; margin-bottom: 2.25rem;">
            <span class="contract-badge" style="margin-bottom: 0.75rem;">The Cloud Kitchen Standard</span>
            <h2 style="font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; margin-top: 0.35rem;">Why Dine with UrbanEats</h2>
            <p style="max-width: 580px; margin: 0 auto; color: var(--text-secondary); font-size: 0.95rem;">
                Crafted for food lovers who demand culinary authenticity, absolute hygiene, and speedy delivery
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div class="card" style="padding: 1.75rem; text-align: left;">
                <div style="font-size: 2rem; margin-bottom: 0.75rem;">⚡</div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem; color: var(--text-primary);">30-Min Fast Dispatch</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 0;">
                    Centrally located fulfillment kitchens in Colombo ensure your meal reaches your table steaming hot.
                </p>
            </div>

            <div class="card" style="padding: 1.75rem; text-align: left;">
                <div style="font-size: 2rem; margin-bottom: 0.75rem;">🍳</div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem; color: var(--text-primary);">Freshly Cooked to Order</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 0;">
                    Zero pre-packaged food. Every order is prepared by culinary experts right as your ticket enters the kitchen.
                </p>
            </div>

            <div class="card" style="padding: 1.75rem; text-align: left;">
                <div style="font-size: 2rem; margin-bottom: 0.75rem;">🛡️</div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem; color: var(--text-primary);">100% Hygienic Packaging</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 0;">
                    Commercial kitchen food safety guidelines with tamper-evident sealing and eco-friendly insulated boxes.
                </p>
            </div>

            <div class="card" style="padding: 1.75rem; text-align: left;">
                <div style="font-size: 2rem; margin-bottom: 0.75rem;">📍</div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem; color: var(--text-primary);">Live Order Tracking</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 0;">
                    Follow your food's progress in real-time from prep station to delivery rider dispatch.
                </p>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section style="margin: 4rem 0 3.5rem; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 3rem 2rem; text-align: center;">
        <span class="contract-badge" style="margin-bottom: 0.75rem;">Simple &bull; Seamless &bull; Delicious</span>
        <h2 style="font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; margin: 0.35rem 0 2rem;">How UrbanEats Works</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem;">
            <div>
                <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-card); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; color: var(--primary); margin: 0 auto 1rem;">1</div>
                <h4 style="margin-bottom: 0.35rem; color: var(--text-primary);">Choose Your Dishes</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">
                    Explore multi-cuisine menus from biryanis to devilled bites and customize portion quantities.
                </p>
            </div>

            <div>
                <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-card); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; color: var(--primary); margin: 0 auto 1rem;">2</div>
                <h4 style="margin-bottom: 0.35rem; color: var(--text-primary);">Cooked Fresh</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">
                    Master chefs begin crafting your dishes in specialized stations using authentic ingredients.
                </p>
            </div>

            <div>
                <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-card); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; color: var(--primary); margin: 0 auto 1rem;">3</div>
                <h4 style="margin-bottom: 0.35rem; color: var(--text-primary);">Express Delivery</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">
                    Sealed in thermal containers and handed over to express delivery riders directly to your door.
                </p>
            </div>
        </div>

        <div style="margin-top: 2.5rem;">
            <a href="<?= base_url('menu/index.php') ?>" class="btn btn-primary btn-lg">Browse Menu &amp; Start Order &rarr;</a>
        </div>
    </section>

    <!-- Administrator Quick Launchpad (Visible ONLY to logged-in Admins) -->
    <?php if (has_role('admin')): ?>
        <section style="margin: 3rem 0; padding: 1.5rem; background: var(--bg-card); border: 1px dashed var(--primary); border-radius: var(--radius-lg);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <span class="badge badge-admin">Admin Access Bar</span>
                    <h3 style="margin: 0.3rem 0 0; font-size: 1.15rem; color: var(--text-primary);">Administrator Quick Shortcuts</h3>
                    <p style="margin: 0; font-size: 0.825rem; color: var(--text-secondary);">Visible only to your administrator account</p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-primary btn-sm">Dashboard</a>
                    <a href="<?= base_url('admin/users.php') ?>" class="btn btn-secondary btn-sm">User Console</a>
                    <a href="<?= base_url('admin/audit_log.php') ?>" class="btn btn-secondary btn-sm">Audit Log</a>
                    <a href="<?= base_url('admin/reports.php') ?>" class="btn btn-secondary btn-sm">Sales Reports</a>
                    <a href="<?= base_url('menu/manage.php') ?>" class="btn btn-secondary btn-sm">Manage Dishes</a>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
