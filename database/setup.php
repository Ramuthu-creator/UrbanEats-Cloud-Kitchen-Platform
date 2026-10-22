<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Database Setup & Seeder Utility Script
 *
 * Can be run from the command line:
 * php database/setup.php
 *
 * Initializes tables and loads realistic seed data (>20 rows in main tables).
 */

require_once __DIR__ . '/../config/db.php';

echo "========================================================\n";
echo "UrbanEats Cloud Kitchen - Database Setup & Seeder\n";
echo "========================================================\n\n";

$sqlFile = __DIR__ . '/urbaneats_db.sql';
if (!file_exists($sqlFile)) {
    die("Error: SQL file '{$sqlFile}' not found.\n");
}

$sqlContent = file_get_contents($sqlFile);

try {
    // Ensure MySQL database exists before attempting dbname-specific connection
    try {
        $dsnNoDb = sprintf("mysql:host=%s;port=%s;charset=%s", DB_HOST, DB_PORT, DB_CHARSET);
        $serverPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        echo "✓ Database '" . DB_NAME . "' verified/created on MySQL server.\n";
    } catch (PDOException $preEx) {
        // If MySQL server is unreachable, proceed to get_db (which reports or falls back)
    }

    $db = get_db();
    $driverName = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "Connected using PDO driver: {$driverName}\n";

    if ($driverName === 'mysql') {
        echo "Executing MySQL schema and seed data import...\n";
        $db->exec($sqlContent);
        echo "✓ Successfully imported database/urbaneats_db.sql into MySQL!\n";
    } else {
        echo "Detected SQLite development fallback mode.\n";
        echo "Creating tables for local testing...\n";
        
        // Execute statements split by semicolon (handling basic DDL)
        $db->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                display_name TEXT NOT NULL,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INTEGER NOT NULL,
                full_name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                phone TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles (id)
            );
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                is_successful INTEGER NOT NULL DEFAULT 0,
                attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                details TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            );
            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token_hash TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                is_used INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                slug TEXT NOT NULL UNIQUE,
                description TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS dishes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                price REAL NOT NULL,
                image_url TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories (id)
            );
            CREATE TABLE IF NOT EXISTS inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                dish_id INTEGER NOT NULL UNIQUE,
                current_stock INTEGER NOT NULL DEFAULT 0,
                reorder_level INTEGER NOT NULL DEFAULT 10,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (dish_id) REFERENCES dishes (id)
            );
            CREATE TABLE IF NOT EXISTS stock_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                dish_id INTEGER NOT NULL,
                user_id INTEGER,
                movement_type TEXT NOT NULL,
                quantity INTEGER NOT NULL,
                reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (dish_id) REFERENCES dishes (id),
                FOREIGN KEY (user_id) REFERENCES users (id)
            );
            CREATE TABLE IF NOT EXISTS coupons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                discount_type TEXT NOT NULL,
                discount_value REAL NOT NULL,
                min_order_value REAL NOT NULL DEFAULT 0.00,
                usage_limit INTEGER NOT NULL DEFAULT 100,
                times_used INTEGER NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_number TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                order_type TEXT NOT NULL DEFAULT 'DELIVERY',
                delivery_address TEXT,
                contact_phone TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'PLACED',
                subtotal REAL NOT NULL,
                discount_amount REAL NOT NULL DEFAULT 0.00,
                delivery_fee REAL NOT NULL DEFAULT 0.00,
                total_amount REAL NOT NULL,
                coupon_id INTEGER,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id),
                FOREIGN KEY (coupon_id) REFERENCES coupons (id)
            );
            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                dish_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price REAL NOT NULL,
                subtotal REAL NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders (id),
                FOREIGN KEY (dish_id) REFERENCES dishes (id)
            );
            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                payment_method TEXT NOT NULL,
                amount REAL NOT NULL,
                payment_status TEXT NOT NULL DEFAULT 'PENDING',
                reference_number TEXT,
                paid_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders (id)
            );
            CREATE TABLE IF NOT EXISTS order_status_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                notes TEXT,
                changed_by_user_id INTEGER,
                changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders (id),
                FOREIGN KEY (changed_by_user_id) REFERENCES users (id)
            );
        ");

        // Seed default roles if empty
        $roleCheck = (int)$db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        if ($roleCheck === 0) {
            $db->exec("
                INSERT INTO roles (id, name, display_name, description) VALUES
                (1, 'admin', 'Administrator', 'Full system access, user management, audit logs, and analytics'),
                (2, 'staff', 'Kitchen Staff', 'Menu, inventory management, and kitchen order processing'),
                (3, 'customer', 'Customer', 'Browse menu, order food, simulate payments, and track order progress');
            ");
        }

        // Seed users if empty
        $userCheck = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($userCheck === 0) {
            $db->exec("
                INSERT INTO users (id, role_id, full_name, email, phone, password_hash, is_active, created_at) VALUES
                (1, 1, 'Nuwan Perera', 'admin@urbaneats.lk', '0771234567', '\$2y\$12\$JXNsqvOqc.IyBaoiqdEoveiLlDSa6rtIcC8fCpISFnHffSPkE2M6C', 1, '2026-09-01 08:00:00'),
                (2, 1, 'Anura Bandara', 'admin2@urbaneats.lk', '0779876543', '\$2y\$12\$JXNsqvOqc.IyBaoiqdEoveiLlDSa6rtIcC8fCpISFnHffSPkE2M6C', 1, '2026-09-02 09:15:00'),
                (3, 2, 'Chamara Silva', 'staff@urbaneats.lk', '0712345678', '\$2y\$12\$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-03 10:30:00'),
                (4, 2, 'Kusal Mendis', 'staff2@urbaneats.lk', '0713456789', '\$2y\$12\$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-04 11:00:00'),
                (5, 2, 'Dilani Fernando', 'staff3@urbaneats.lk', '0714567890', '\$2y\$12\$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-05 11:45:00'),
                (6, 3, 'Kasun Jayasuriya', 'customer@urbaneats.lk', '0761112233', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-10 12:00:00'),
                (7, 3, 'Malik Fernando', 'malik.fernando@gmail.com', '0762223344', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-11 13:10:00'),
                (8, 3, 'Nadeesha Senaratne', 'nadeesha.senaratne@yahoo.com', '0763334455', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-12 14:20:00'),
                (9, 3, 'Rohan De Silva', 'rohan.desilva@hotmail.com', '0764445566', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-13 15:30:00'),
                (10, 3, 'Tharushi Wickramasinghe', 'tharushi.wickramasinghe@gmail.com', '0765556677', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-14 16:40:00'),
                (11, 3, 'Sanjaya Abeykoon', 'sanjaya.abeykoon@outlook.com', '0766667788', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-15 17:50:00'),
                (12, 3, 'Ishani Gunawardena', 'ishani.gunawardena@gmail.com', '0767778899', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-16 18:00:00'),
                (13, 3, 'Priyantha Lokuge', 'priyantha.lokuge@gmail.com', '0768889900', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-17 19:15:00'),
                (14, 3, 'Fathima Rizwan', 'fathima.rizwan@gmail.com', '0751112244', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-18 20:25:00'),
                (15, 3, 'Devinda Samaraweera', 'devinda.samaraweera@gmail.com', '0752223355', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-19 21:05:00'),
                (16, 3, 'Sanduni Rathnayake', 'sanduni.rathnayake@yahoo.com', '0753334466', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-20 09:40:00'),
                (17, 3, 'Akila Dassanayake', 'akila.dassanayake@gmail.com', '0754445577', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-21 10:50:00'),
                (18, 3, 'Chathurika Gamage', 'chathurika.gamage@gmail.com', '0755556688', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-22 11:35:00'),
                (19, 3, 'Dinuka Hettiarachchi', 'dinuka.hettiarachchi@gmail.com', '0756667799', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-23 12:45:00'),
                (20, 3, 'Mahesh Karunaratne', 'mahesh.karunaratne@gmail.com', '0757778800', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-24 13:55:00'),
                (21, 3, 'Niluka Jayawardena', 'niluka.jayawardena@gmail.com', '0758889911', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-25 14:10:00'),
                (22, 3, 'Harshana Weerakkody', 'harshana.weerakkody@gmail.com', '0759990022', '\$2y\$12\$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 0, '2026-09-26 15:20:00');
            ");
        }

        // Seed dishes and categories if empty
        $catCheck = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        if ($catCheck === 0) {
            $db->exec("
                INSERT INTO categories (id, name, slug, description, is_active) VALUES
                (1, 'Signature Biryani', 'signature-biryani', 'Aromatic dum-cooked basmati rice with exotic spices and tender meats', 1),
                (2, 'Kottu & Roti', 'kottu-roti', 'Authentic street-style chopped flatbread with vegetables, eggs, and gravy', 1),
                (3, 'Rice & Curry Specialties', 'rice-and-curry', 'Traditional Sri Lankan village-style curries and fragrant samba rice', 1),
                (4, 'Appetizers & Bites', 'appetizers-bites', 'Crispy hot bites, devilled dishes, and savory treats', 1),
                (5, 'Desserts & Beverages', 'desserts-beverages', 'Traditional watalappan, caramel pudding, and fresh tropical fruit juices', 1);

                INSERT INTO dishes (id, category_id, name, description, price, image_url, is_active) VALUES
                (1, 1, 'Royal Chicken Dum Biryani', 'Slow-cooked fragrant basmati rice with tender spiced chicken, boiled egg, mint raita, and spicy gravy', 1650.00, 'uploads/biryani_chicken.jpg', 1),
                (2, 1, 'Hyderabadi Mutton Biryani', 'Premium Australian mutton layered with saffron rice and authentic caramelized shallots', 2450.00, 'uploads/biryani_mutton.jpg', 1),
                (3, 2, 'Cheese Chicken Kottu', 'Sri Lankan Godamba roti chopped with chicken, melted mozzarella cheese, vegetables, and creamy curry sauce', 1550.00, 'uploads/kottu_cheese_chicken.jpg', 1),
                (4, 2, 'Spicy Seafood Dolphin Kottu', 'Parotta chopped with fresh prawns, cuttlefish, chili paste, and leeks in thick devilled sauce', 1850.00, 'uploads/kottu_seafood.jpg', 1),
                (5, 3, 'Lamprais Special (Chicken)', 'Dutch-Burgher recipe wrapped in banana leaf: yellow rice, chicken curry, frikkadel meatball, blachan, seeni sambol, and ash plantain', 1750.00, 'uploads/lamprais.jpg', 1),
                (6, 4, 'Hot Butter Cuttlefish', 'Crisp battered cuttlefish tossed in seasoned butter, chili flakes, capsicum, and spring onions', 1950.00, 'uploads/hot_butter_cuttlefish.jpg', 1),
                (7, 4, 'Devilled Chicken Bites', 'Crispy chicken tenders tossed in sweet & spicy Sri Lankan devilled chili sauce', 1250.00, 'uploads/devilled_chicken.jpg', 1),
                (8, 5, 'Traditional Jaggery Watalappan', 'Steamed coconut custard with pure Kithul jaggery, cardamom, nutmeg, and cashew nuts', 550.00, 'uploads/watalappan.jpg', 1);

                INSERT INTO inventory (id, dish_id, current_stock, reorder_level) VALUES
                (1, 1, 45, 10),
                (2, 2, 20, 5),
                (3, 3, 35, 10),
                (4, 4, 15, 5),
                (5, 5, 25, 8),
                (6, 6, 8, 10),
                (7, 7, 0, 10),
                (8, 8, 50, 15);
            ");
        }

        // Seed sample orders & audit
        $auditCheck = (int)$db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
        if ($auditCheck === 0) {
            $db->exec("
                INSERT INTO audit_log (id, user_id, action, details, ip_address, user_agent, created_at) VALUES
                (1, 1, 'SYSTEM_INIT', 'Initial database schema and default roles established', '127.0.0.1', 'CLI Setup', '2026-09-01 08:00:00'),
                (2, 1, 'USER_LOGIN', 'Admin login successful', '192.168.1.10', 'System Test', '2026-09-01 08:05:00');
            ");
        }

        echo "✓ Successfully initialized SQLite database!\n";
    }

    echo "\nDatabase setup finished without errors.\n";
    echo "Ready for Component A, B, and C.\n";
} catch (Exception $e) {
    echo "Setup encountered an exception: " . $e->getMessage() . "\n";
    exit(1);
}
