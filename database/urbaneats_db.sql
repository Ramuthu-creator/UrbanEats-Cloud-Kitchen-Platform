-- ====================================================================
-- IT3113 Advanced Web Technologies - Group Assignment
-- Project: UrbanEats Cloud Kitchen Platform
-- Shared Database Schema & Seed Data (MySQL 8.x / MariaDB)
--
-- Component A: Identity, Access & Administration (Primary Owner)
-- Component B: Menu, Catalogue & Inventory
-- Component C: Ordering, Payment & Tracking
-- ====================================================================

-- Ensure database exists and is selected
CREATE DATABASE IF NOT EXISTS `urbaneats_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `urbaneats_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS dishes;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------
-- COMPONENT A: IDENTITY, ACCESS & ADMINISTRATION TABLES
-- --------------------------------------------------------------------

-- Table: roles
-- Defines system privileges: admin, staff, customer
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users
-- Core user accounts with role reference, hashed password, and activation status
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_users_email (email),
    INDEX idx_users_role (role_id),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: login_attempts
-- Used for login throttling (temporary lock after 5 consecutive failed attempts)
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    is_successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_throttle (email, attempted_at),
    INDEX idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: audit_log
-- Centralized audit trail for security, user changes, and kitchen actions
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: password_resets
-- Time-limited token management for secure password resets
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pw_reset_lookup (token_hash, expires_at),
    INDEX idx_pw_reset_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- COMPONENT B: MENU, CATALOGUE & INVENTORY TABLES (Contract Shared)
-- --------------------------------------------------------------------

-- Table: categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dishes
CREATE TABLE dishes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dishes_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_dishes_category (category_id),
    INDEX idx_dishes_active (is_active),
    INDEX idx_dishes_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: inventory
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dish_id INT NOT NULL UNIQUE,
    current_stock INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_dish FOREIGN KEY (dish_id) REFERENCES dishes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_inventory_stock (current_stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: stock_movements
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dish_id INT NOT NULL,
    user_id INT NULL,
    movement_type ENUM('RESTOCK', 'ORDER_DEDUCT', 'WASTAGE', 'CORRECTION') NOT NULL,
    quantity INT NOT NULL,
    reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sm_dish FOREIGN KEY (dish_id) REFERENCES dishes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_sm_dish (dish_id),
    INDEX idx_sm_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- COMPONENT C: ORDERING, PAYMENT & TRACKING TABLES (Contract Shared)
-- --------------------------------------------------------------------

-- Table: coupons
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_type ENUM('PERCENTAGE', 'FIXED') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_order_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    usage_limit INT NOT NULL DEFAULT 100,
    times_used INT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    order_type ENUM('DELIVERY', 'PICKUP') NOT NULL DEFAULT 'DELIVERY',
    delivery_address TEXT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    status ENUM('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PLACED',
    subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    coupon_id INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: order_items
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    dish_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_oi_dish FOREIGN KEY (dish_id) REFERENCES dishes (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_oi_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: payments
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('CASH_ON_DELIVERY', 'CARD', 'BANK_TRANSFER') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('PENDING', 'COMPLETED', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
    reference_number VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_payments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: order_status_history
CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status ENUM('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED', 'COMPLETED', 'CANCELLED') NOT NULL,
    notes TEXT NULL,
    changed_by_user_id INT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_osh_user FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_osh_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: reviews
-- Customer Ratings & Reviews (1 to 5 Stars + Comments for ordered dishes)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    dish_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_dish FOREIGN KEY (dish_id) REFERENCES dishes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_order_dish_user (order_id, dish_id, user_id),
    INDEX idx_reviews_dish (dish_id),
    INDEX idx_reviews_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- SEED DATA (MEETING REQUIREMENT: >=20 REALISTIC ROWS IN MAIN TABLES)
-- ====================================================================

-- 1. ROLES
INSERT INTO roles (id, name, display_name, description) VALUES
(1, 'admin', 'Administrator', 'Full system access, user management, audit logs, and analytics'),
(2, 'staff', 'Kitchen Staff', 'Menu, inventory management, and kitchen order processing'),
(3, 'customer', 'Customer', 'Browse menu, order food, simulate payments, and track order progress');

-- 2. USERS (22 realistic accounts across roles, with Bcrypt hashed passwords)
-- Passwords:
-- Admin accounts: Admin@12345   ($2y$12$JXNsqvOqc.IyBaoiqdEoveiLlDSa6rtIcC8fCpISFnHffSPkE2M6C)
-- Staff accounts: Staff@12345   ($2y$12$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96)
-- Customer accounts: Customer@12345 ($2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK)
INSERT INTO users (id, role_id, full_name, email, phone, password_hash, is_active, created_at) VALUES
(1, 1, 'Nuwan Perera', 'admin@urbaneats.lk', '0771234567', '$2y$12$JXNsqvOqc.IyBaoiqdEoveiLlDSa6rtIcC8fCpISFnHffSPkE2M6C', 1, '2026-09-01 08:00:00'),
(2, 1, 'Anura Bandara', 'admin2@urbaneats.lk', '0779876543', '$2y$12$JXNsqvOqc.IyBaoiqdEoveiLlDSa6rtIcC8fCpISFnHffSPkE2M6C', 1, '2026-09-02 09:15:00'),
(3, 2, 'Chamara Silva', 'staff@urbaneats.lk', '0712345678', '$2y$12$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-03 10:30:00'),
(4, 2, 'Kusal Mendis', 'staff2@urbaneats.lk', '0713456789', '$2y$12$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-04 11:00:00'),
(5, 2, 'Dilani Fernando', 'staff3@urbaneats.lk', '0714567890', '$2y$12$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-05 11:45:00'),
(6, 3, 'Kasun Jayasuriya', 'customer@urbaneats.lk', '0761112233', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-10 12:00:00'),
(7, 3, 'Malik Fernando', 'malik.fernando@gmail.com', '0762223344', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-11 13:10:00'),
(8, 3, 'Nadeesha Senaratne', 'nadeesha.senaratne@yahoo.com', '0763334455', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-12 14:20:00'),
(9, 3, 'Rohan De Silva', 'rohan.desilva@hotmail.com', '0764445566', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-13 15:30:00'),
(10, 3, 'Tharushi Wickramasinghe', 'tharushi.wickramasinghe@gmail.com', '0765556677', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-14 16:40:00'),
(11, 3, 'Sanjaya Abeykoon', 'sanjaya.abeykoon@outlook.com', '0766667788', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-15 17:50:00'),
(12, 3, 'Ishani Gunawardena', 'ishani.gunawardena@gmail.com', '0767778899', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-16 18:00:00'),
(13, 3, 'Priyantha Lokuge', 'priyantha.lokuge@gmail.com', '0768889900', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-17 19:15:00'),
(14, 3, 'Fathima Rizwan', 'fathima.rizwan@gmail.com', '0751112244', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-18 20:25:00'),
(15, 3, 'Devinda Samaraweera', 'devinda.samaraweera@gmail.com', '0752223355', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-19 21:05:00'),
(16, 3, 'Sanduni Rathnayake', 'sanduni.rathnayake@yahoo.com', '0753334466', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-20 09:40:00'),
(17, 3, 'Akila Dassanayake', 'akila.dassanayake@gmail.com', '0754445577', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-21 10:50:00'),
(18, 3, 'Chathurika Gamage', 'chathurika.gamage@gmail.com', '0755556688', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-22 11:35:00'),
(19, 3, 'Dinuka Hettiarachchi', 'dinuka.hettiarachchi@gmail.com', '0756667799', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-23 12:45:00'),
(20, 3, 'Mahesh Karunaratne', 'mahesh.karunaratne@gmail.com', '0757778800', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-24 13:55:00'),
(21, 3, 'Niluka Jayawardena', 'niluka.jayawardena@gmail.com', '0758889911', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-25 14:10:00'),
(22, 3, 'Harshana Weerakkody', 'harshana.weerakkody@gmail.com', '0759990022', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 0, '2026-09-26 15:20:00');

-- 3. AUDIT LOG (22 realistic records)
INSERT INTO audit_log (id, user_id, action, details, ip_address, user_agent, created_at) VALUES
(1, 1, 'SYSTEM_INIT', 'Initial database schema and default roles established', '127.0.0.1', 'System Installer', '2026-09-01 08:00:00'),
(2, 1, 'USER_LOGIN', 'Admin login successful', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-01 08:05:00'),
(3, 1, 'USER_CREATED', 'Created new admin user: admin2@urbaneats.lk', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-02 09:15:00'),
(4, 1, 'USER_CREATED', 'Created kitchen staff: staff@urbaneats.lk', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-03 10:30:00'),
(5, 3, 'USER_LOGIN', 'Staff member Chamara Silva logged in', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-03 10:35:00'),
(6, 6, 'USER_REGISTERED', 'Customer Kasun Jayasuriya self-registered', '112.134.20.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '2026-09-10 12:00:00'),
(7, 6, 'USER_LOGIN', 'Customer login successful', '112.134.20.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '2026-09-10 12:01:00'),
(8, NULL, 'LOGIN_FAILED', 'Failed login attempt for unknown user: test@unknown.com', '123.231.10.4', 'Mozilla/5.0 (Windows NT 10.0)', '2026-09-10 12:15:00'),
(9, 7, 'USER_REGISTERED', 'Customer Malik Fernando registered', '112.134.22.40', 'Mozilla/5.0 (Android 14; Mobile)', '2026-09-11 13:10:00'),
(10, 7, 'PROFILE_UPDATED', 'Updated contact phone number to 0762223344', '112.134.22.40', 'Mozilla/5.0 (Android 14; Mobile)', '2026-09-11 13:25:00'),
(11, 8, 'PASSWORD_RESET_REQUESTED', 'Requested password reset token', '112.134.33.12', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-12 14:22:00'),
(12, 8, 'PASSWORD_RESET_COMPLETED', 'Password reset successfully with secure token', '112.134.33.12', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-12 14:28:00'),
(13, 1, 'USER_STATUS_CHANGE', 'Deactivated user account id: 22 (Harshana Weerakkody)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-26 15:30:00'),
(14, 1, 'ROLE_CHANGE', 'Promoted staff user id: 4 to supervisor role', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-27 10:00:00'),
(15, 3, 'MENU_ITEM_ADDED', 'Added new dish: Jaffna Crab Curry (Rs. 2,400.00)', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-01 09:30:00'),
(16, 3, 'STOCK_RESTOCKED', 'Restocked 50 portions of Chicken Dum Biryani', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-02 08:45:00'),
(17, 6, 'ORDER_PLACED', 'Order #ORD-202610-001 placed for LKR 3,850.00', '112.134.20.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '2026-10-05 19:10:00'),
(18, 3, 'ORDER_STATUS_UPDATED', 'Order #ORD-202610-001 updated to PREPARING', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-05 19:15:00'),
(19, 3, 'ORDER_STATUS_UPDATED', 'Order #ORD-202610-001 updated to READY', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-05 19:35:00'),
(20, 1, 'VIEW_SALES_REPORT', 'Administrator viewed monthly sales report', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-10-10 16:00:00'),
(21, 6, 'PASSWORD_CHANGED', 'User Kasun Jayasuriya changed password via profile page', '112.134.20.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '2026-10-12 11:20:00'),
(22, 1, 'USER_LOGIN', 'Admin login from Colombo HQ terminal', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-10-16 08:30:00');

-- 4. LOGIN ATTEMPTS (20 realistic records demonstrating normal & throttled activity)
INSERT INTO login_attempts (id, email, ip_address, is_successful, attempted_at) VALUES
(1, 'admin@urbaneats.lk', '192.168.1.10', 1, '2026-10-14 08:00:00'),
(2, 'staff@urbaneats.lk', '192.168.1.25', 1, '2026-10-14 08:15:00'),
(3, 'customer@urbaneats.lk', '112.134.20.15', 1, '2026-10-14 12:30:00'),
(4, 'malik.fernando@gmail.com', '112.134.22.40', 1, '2026-10-14 13:00:00'),
(5, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 14:01:00'),
(6, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 14:01:30'),
(7, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 14:02:00'),
(8, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 14:02:40'),
(9, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 14:03:15'),
(10, 'admin@urbaneats.lk', '192.168.1.10', 1, '2026-10-15 08:30:00'),
(11, 'staff2@urbaneats.lk', '192.168.1.30', 1, '2026-10-15 09:00:00'),
(12, 'nadeesha.senaratne@yahoo.com', '112.134.33.12', 0, '2026-10-15 11:00:00'),
(13, 'nadeesha.senaratne@yahoo.com', '112.134.33.12', 1, '2026-10-15 11:02:00'),
(14, 'rohan.desilva@hotmail.com', '112.134.44.18', 1, '2026-10-15 13:10:00'),
(15, 'tharushi.wickramasinghe@gmail.com', '112.134.55.21', 1, '2026-10-15 14:40:00'),
(16, 'harshana.weerakkody@gmail.com', '112.134.99.30', 0, '2026-10-15 15:00:00'),
(17, 'admin2@urbaneats.lk', '192.168.1.12', 1, '2026-10-16 08:00:00'),
(18, 'staff3@urbaneats.lk', '192.168.1.35', 1, '2026-10-16 08:10:00'),
(19, 'customer@urbaneats.lk', '112.134.20.15', 1, '2026-10-16 08:25:00'),
(20, 'admin@urbaneats.lk', '192.168.1.10', 1, '2026-10-16 08:30:00');

-- 5. PASSWORD RESETS
INSERT INTO password_resets (id, email, token_hash, expires_at, is_used, created_at) VALUES
(1, 'nadeesha.senaratne@yahoo.com', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', '2026-09-12 15:22:00', 1, '2026-09-12 14:22:00');

-- 6. CATEGORIES (Component B baseline)
INSERT INTO categories (id, name, slug, description, is_active) VALUES
(1, 'Signature Biryani', 'signature-biryani', 'Aromatic dum-cooked basmati rice with exotic spices and tender meats', 1),
(2, 'Kottu & Roti', 'kottu-roti', 'Authentic street-style chopped flatbread with vegetables, eggs, and gravy', 1),
(3, 'Rice & Curry Specialties', 'rice-and-curry', 'Traditional Sri Lankan village-style curries and fragrant samba rice', 1),
(4, 'Appetizers & Bites', 'appetizers-bites', 'Crispy hot bites, devilled dishes, and savory treats', 1),
(5, 'Desserts & Beverages', 'desserts-beverages', 'Traditional watalappan, caramel pudding, and fresh tropical fruit juices', 1);

-- 7. DISHES (Component B: >= 20 realistic rows with actual image uploads)
INSERT INTO dishes (id, category_id, name, description, price, image_url, is_active) VALUES
(1, 1, 'Royal Chicken Dum Biryani', 'Slow-cooked fragrant basmati rice with tender spiced chicken, boiled egg, mint raita, and spicy gravy', 1650.00, 'Royal Chicken Dum Biryani.jpeg', 1),
(2, 1, 'Hyderabadi Mutton Biryani', 'Premium Australian mutton layered with saffron rice and authentic caramelized shallots', 2450.00, 'Hyderabadi Mutton Biryani.jpg', 1),
(3, 2, 'Cheese Chicken Kottu', 'Sri Lankan Godamba roti chopped with chicken, melted mozzarella cheese, vegetables, and creamy curry sauce', 1550.00, 'chees kottu.jpg', 1),
(4, 2, 'Spicy Seafood Dolphin Kottu', 'Parotta chopped with fresh prawns, cuttlefish, chili paste, and leeks in thick devilled sauce', 1850.00, 'sea food .jpeg', 1),
(5, 3, 'Lamprais Special (Chicken)', 'Dutch-Burgher recipe wrapped in banana leaf: yellow rice, chicken curry, frikkadel meatball, blachan, seeni sambol, and ash plantain', 1750.00, 'Lamprais Special (Chicken).jpeg', 1),
(6, 4, 'Hot Butter Cuttlefish', 'Crisp battered cuttlefish tossed in seasoned butter, chili flakes, capsicum, and spring onions', 1950.00, 'het butter .jpeg', 1),
(7, 4, 'Devilled Chicken Bites', 'Crispy chicken tenders tossed in sweet & spicy Sri Lankan devilled chili sauce', 1250.00, 'devel chicken.jpg', 1),
(8, 5, 'Traditional Jaggery Watalappan', 'Steamed coconut custard with pure Kithul jaggery, cardamom, nutmeg, and cashew nuts', 550.00, 'Traditional Jaggery Watalappan.jpeg', 1),
(9, 5, 'Fresh Mango Mojito Cooler', 'Refreshing crushed mint, fresh ripe mango puree, lime wedges, and sparkling soda', 650.00, 'dish_6adc56f3af037.jpeg', 1),
(10, 2, 'Classic Roast Chicken Kottu', 'Wok-tossed Godamba flatbread shredded with tender spiced roast chicken, crisp cabbage, and egg', 1350.00, 'chees kottu.jpg', 1),
(11, 1, 'String Hopper Biryani Special', 'Fine steamed idiyappam layered with chicken curry, saffron infused onions, and hard-boiled egg', 1450.00, 'Royal Chicken Dum Biryani.jpeg', 1),
(12, 3, 'Dutch Mutton Lamprais', 'Authentic slow-baked banana leaf parcel packed with mutton frikkadels, seeni sambol, and ash plantain', 2650.00, 'Lamprais Special (Chicken).jpeg', 1),
(13, 4, 'Jaffna Spicy Prawn Bites', 'Deep-fried jumbo prawns tossed in roasted Jaffna curry powder, curry leaves, and green chillies', 2150.00, 'sea food .jpeg', 1),
(14, 4, 'Crispy Devilled Pork Belly', 'Twice-cooked succulent pork belly glazed in tamarind, tomato, and crushed black pepper sauce', 1850.00, 'devel chicken.jpg', 1),
(15, 1, 'Vegetable Dum Biryani', 'Aromatic basmati rice cooked with garden peas, paneer cubes, cashews, raisins, and mint raita', 1200.00, 'Royal Chicken Dum Biryani.jpeg', 1),
(16, 2, 'Spicy Vegetable Egg Kottu', 'Shredded godamba flatbread sautéed with fresh leeks, carrots, scrambled farm eggs, and chili sauce', 950.00, 'chees kottu.jpg', 1),
(17, 3, 'Seafood Claypot Rice & Curry', 'Red samba rice served with fresh yellowfin tuna ambul thiyal, devilled prawns, and dhal curry', 1950.00, 'sea food .jpeg', 1),
(18, 4, 'Sri Lankan Fish Cutlets (5 pcs)', 'Golden crumbed mackerel and spiced potato croquettes served with spicy sweet chili relish', 650.00, 'het butter .jpeg', 1),
(19, 5, 'Royal Faluda with Vanilla Ice Cream', 'Fragrant rose syrup infused with chilled milk, basil seeds, grass jelly cubes, and rich vanilla ice cream', 750.00, 'dish_6adc56f3af037.jpeg', 1),
(20, 5, 'Caramel Cream Pudding', 'Silky baked egg and condensed milk custard topped with dark golden caramel glaze', 600.00, 'Traditional Jaggery Watalappan.jpeg', 1),
(21, 5, 'Tropical Woodapple Nectar', 'Traditional woodapple pulp blended with pure coconut milk and kithul treacle', 550.00, 'dish_6adc56f3af037.jpeg', 1),
(22, 4, 'Batter Fried Crispy Mushrooms', 'Golden oyster mushrooms fried crisp and dusted with chili salt and toasted cumin', 850.00, 'het butter .jpeg', 1);

-- 8. INVENTORY (Component B: >= 20 realistic rows)
INSERT INTO inventory (id, dish_id, current_stock, reorder_level) VALUES
(1, 1, 45, 10),
(2, 2, 20, 5),
(3, 3, 35, 10),
(4, 4, 15, 5),
(5, 5, 25, 8),
(6, 6, 8, 10),   -- Low stock indicator test
(7, 7, 0, 10),   -- Out of stock indicator test
(8, 8, 50, 15),
(9, 9, 30, 8),
(10, 10, 28, 8),
(11, 11, 22, 6),
(12, 12, 18, 5),
(13, 13, 14, 5),
(14, 14, 16, 5),
(15, 15, 40, 10),
(16, 16, 32, 8),
(17, 17, 19, 6),
(18, 18, 60, 15),
(19, 19, 45, 10),
(20, 20, 38, 8),
(21, 21, 25, 6),
(22, 22, 20, 5);

-- 9. STOCK MOVEMENTS (Component B: >= 20 realistic historical movements)
INSERT INTO stock_movements (id, dish_id, user_id, movement_type, quantity, reason, created_at) VALUES
(1, 1, 3, 'RESTOCK', 50, 'Morning kitchen batch prepared', '2026-10-15 08:30:00'),
(2, 1, 3, 'ORDER_DEDUCT', 5, 'Deducted for orders #ORD-202610-001 to 003', '2026-10-15 12:45:00'),
(3, 7, 4, 'WASTAGE', 5, 'Discarded due to prep standard rejection', '2026-10-15 18:00:00'),
(4, 2, 3, 'RESTOCK', 25, 'Imported Australian mutton batch prepped', '2026-10-16 09:00:00'),
(5, 2, 3, 'ORDER_DEDUCT', 3, 'Deducted for weekend dinner orders', '2026-10-16 20:30:00'),
(6, 3, 4, 'RESTOCK', 40, 'Fresh godamba roti batch chopped', '2026-10-17 11:00:00'),
(7, 3, 4, 'ORDER_DEDUCT', 5, 'Order fulfillment checkout', '2026-10-17 13:15:00'),
(8, 4, 3, 'RESTOCK', 20, 'Negombo seafood market fresh catch delivery', '2026-10-18 08:15:00'),
(9, 4, 3, 'ORDER_DEDUCT', 4, 'Lunch rush seafood orders', '2026-10-18 13:00:00'),
(10, 5, 4, 'RESTOCK', 30, 'Banana leaf lamprais parcel baking batch', '2026-10-19 09:30:00'),
(11, 6, 3, 'RESTOCK', 25, 'Fresh cuttlefish cleaned and batter seasoned', '2026-10-20 10:00:00'),
(12, 6, 3, 'ORDER_DEDUCT', 8, 'Corporate dinner order batch deduction', '2026-10-20 21:00:00'),
(13, 8, 4, 'RESTOCK', 60, 'Traditional claypot jaggery watalappan steaming', '2026-10-21 07:45:00'),
(14, 8, 4, 'ORDER_DEDUCT', 10, 'Dessert combo promotion checkout', '2026-10-21 14:20:00'),
(15, 9, 3, 'RESTOCK', 35, 'Fresh mango crush and mint syrup prep', '2026-10-22 10:30:00'),
(16, 10, 4, 'RESTOCK', 30, 'Roast chicken portion prep for kottu station', '2026-10-23 11:15:00'),
(17, 12, 3, 'RESTOCK', 20, 'Dutch burgher recipe slow steam batch', '2026-10-24 09:00:00'),
(18, 13, 4, 'WASTAGE', 2, 'Spicy prawns discarded due to shell defect', '2026-10-24 16:30:00'),
(19, 15, 3, 'RESTOCK', 45, 'Fresh vegetable and paneer biryani pot', '2026-10-25 08:00:00'),
(20, 18, 4, 'RESTOCK', 70, 'Mackerel fish cutlet crumb prep and blast freeze', '2026-10-25 15:00:00');

-- 10. COUPONS (Component C baseline & test coupons)
INSERT INTO coupons (id, code, discount_type, discount_value, min_order_value, usage_limit, times_used, expires_at, is_active) VALUES
(1, 'WELCOME10', 'PERCENTAGE', 10.00, 1500.00, 500, 24, '2026-12-31 23:59:59', 1),
(2, 'FLAT500', 'FIXED', 500.00, 3000.00, 100, 12, '2026-11-30 23:59:59', 1),
(3, 'BIRYANI15', 'PERCENTAGE', 15.00, 2000.00, 200, 18, '2026-12-31 23:59:59', 1),
(4, 'FREESHIP', 'FIXED', 350.00, 2500.00, 300, 45, '2026-12-15 23:59:59', 1),
(5, 'WEEKEND20', 'PERCENTAGE', 20.00, 4000.00, 50, 8, '2026-11-15 23:59:59', 1);

-- 11. ORDERS (Component C: >= 20 realistic orders across various dates, statuses, and customers)
INSERT INTO orders (id, order_number, user_id, order_type, delivery_address, contact_phone, status, subtotal, discount_amount, delivery_fee, total_amount, coupon_id, notes, created_at) VALUES
(1, 'ORD-202610-001', 6, 'DELIVERY', 'No. 42, Galle Road, Colombo 03', '0761112233', 'COMPLETED', 3200.00, 320.00, 350.00, 3230.00, 1, 'Please make it extra spicy', '2026-10-15 12:30:00'),
(2, 'ORD-202610-002', 7, 'PICKUP', NULL, '0762223344', 'PREPARING', 2450.00, 0.00, 0.00, 2450.00, NULL, 'Will pick up in 30 minutes', '2026-10-16 09:00:00'),
(3, 'ORD-202610-003', 8, 'DELIVERY', '78/2, Havelock Road, Colombo 05', '0763334455', 'COMPLETED', 4950.00, 500.00, 350.00, 4800.00, 2, 'Ring bell twice', '2026-10-16 13:15:00'),
(4, 'ORD-202610-004', 9, 'DELIVERY', '15, Dharmapala Mawatha, Colombo 07', '0764445566', 'READY', 3500.00, 350.00, 350.00, 3500.00, 1, 'Leave with building security', '2026-10-17 11:45:00'),
(5, 'ORD-202610-005', 10, 'PICKUP', NULL, '0765556677', 'COMPLETED', 1750.00, 0.00, 0.00, 1750.00, NULL, 'Extra seeni sambol please', '2026-10-17 18:20:00'),
(6, 'ORD-202610-006', 11, 'DELIVERY', '104, High Level Road, Nugegoda', '0766667788', 'COMPLETED', 5400.00, 500.00, 350.00, 5250.00, 2, 'Call before arriving', '2026-10-18 12:10:00'),
(7, 'ORD-202610-007', 12, 'DELIVERY', '23, Duplication Road, Colombo 04', '0767778899', 'OUT_FOR_DELIVERY', 2200.00, 0.00, 350.00, 2550.00, NULL, 'Near Majestic City', '2026-10-18 19:30:00'),
(8, 'ORD-202610-008', 13, 'PICKUP', NULL, '0768889900', 'COMPLETED', 3900.00, 390.00, 0.00, 3510.00, 1, 'Packed in paper bag please', '2026-10-19 13:00:00'),
(9, 'ORD-202610-009', 14, 'DELIVERY', '55, Jawatte Road, Colombo 05', '0751112244', 'COMPLETED', 4100.00, 0.00, 350.00, 4450.00, NULL, 'Cut into small portions', '2026-10-19 19:45:00'),
(10, 'ORD-202610-010', 15, 'DELIVERY', '12, Ward Place, Colombo 07', '0752223355', 'PLACED', 1650.00, 0.00, 350.00, 2000.00, NULL, 'First time trying cloud kitchen', '2026-10-20 11:30:00'),
(11, 'ORD-202610-011', 16, 'PICKUP', NULL, '0753334466', 'COLLECTED', 2100.00, 0.00, 0.00, 2100.00, NULL, 'Will arrive at 1:15pm', '2026-10-20 12:45:00'),
(12, 'ORD-202610-012', 17, 'DELIVERY', '88, Baseline Road, Colombo 09', '0754445577', 'COMPLETED', 6100.00, 500.00, 350.00, 5950.00, 2, 'Office team lunch order', '2026-10-21 12:00:00'),
(13, 'ORD-202610-013', 18, 'DELIVERY', '34, Deanston Place, Colombo 03', '0755556688', 'COMPLETED', 2900.00, 290.00, 350.00, 2960.00, 1, 'Less oil please', '2026-10-21 18:30:00'),
(14, 'ORD-202610-014', 19, 'PICKUP', NULL, '0756667799', 'CANCELLED', 1550.00, 0.00, 0.00, 1550.00, NULL, 'Customer called to cancel', '2026-10-22 10:15:00'),
(15, 'ORD-202610-015', 20, 'DELIVERY', '9, Cambridge Place, Colombo 07', '0757778800', 'COMPLETED', 4700.00, 0.00, 350.00, 5050.00, NULL, 'Gate code #4412', '2026-10-22 13:40:00'),
(16, 'ORD-202610-016', 21, 'DELIVERY', '62, Thimbirigasyaya Road, Colombo 05', '0758889911', 'COMPLETED', 3300.00, 330.00, 350.00, 3320.00, 1, 'Make sure watalappan is chilled', '2026-10-23 12:15:00'),
(17, 'ORD-202610-017', 6, 'PICKUP', NULL, '0761112233', 'COMPLETED', 1950.00, 0.00, 0.00, 1950.00, NULL, 'Hot butter cuttlefish snack', '2026-10-23 17:00:00'),
(18, 'ORD-202610-018', 7, 'DELIVERY', '11, Marine Drive, Bambalapitiya', '0762223344', 'PREPARING', 3800.00, 380.00, 350.00, 3770.00, 1, 'Deliver to 4th floor apartment', '2026-10-24 11:50:00'),
(19, 'ORD-202610-019', 8, 'DELIVERY', '45, Bagatalle Road, Colombo 03', '0763334455', 'READY', 2750.00, 0.00, 350.00, 3100.00, NULL, 'Extra gravy container', '2026-10-24 12:30:00'),
(20, 'ORD-202610-020', 9, 'PICKUP', NULL, '0764445566', 'PLACED', 4200.00, 500.00, 0.00, 3700.00, 2, 'Picking up after work at 6pm', '2026-10-24 17:15:00'),
(21, 'ORD-202610-021', 10, 'DELIVERY', '7, Alfred Place, Colombo 03', '0765556677', 'COMPLETED', 5150.00, 500.00, 350.00, 5000.00, 2, 'Family dinner weekend special', '2026-10-25 13:00:00'),
(22, 'ORD-202610-022', 11, 'DELIVERY', '18, Flower Road, Colombo 07', '0766667788', 'PLACED', 3200.00, 320.00, 350.00, 3230.00, 1, 'Weekend biryani craving', '2026-10-25 18:30:00');

-- 12. ORDER ITEMS (Component C: >= 20 realistic rows mapping dishes and preserving unit_price)
INSERT INTO order_items (id, order_id, dish_id, quantity, unit_price, subtotal) VALUES
(1, 1, 1, 1, 1650.00, 1650.00),
(2, 1, 3, 1, 1550.00, 1550.00),
(3, 2, 2, 1, 2450.00, 2450.00),
(4, 3, 2, 1, 2450.00, 2450.00),
(5, 3, 6, 1, 1950.00, 1950.00),
(6, 3, 8, 1, 550.00, 550.00),
(7, 4, 1, 1, 1650.00, 1650.00),
(8, 4, 4, 1, 1850.00, 1850.00),
(9, 5, 5, 1, 1750.00, 1750.00),
(10, 6, 1, 2, 1650.00, 3300.00),
(11, 6, 6, 1, 1950.00, 1950.00),
(12, 6, 8, 3, 550.00, 1650.00),
(13, 7, 3, 1, 1550.00, 1550.00),
(14, 7, 8, 1, 550.00, 550.00),
(15, 8, 6, 2, 1950.00, 3900.00),
(16, 9, 2, 1, 2450.00, 2450.00),
(17, 9, 1, 1, 1650.00, 1650.00),
(18, 10, 1, 1, 1650.00, 1650.00),
(19, 11, 3, 1, 1550.00, 1550.00),
(20, 11, 8, 1, 550.00, 550.00),
(21, 12, 1, 2, 1650.00, 3300.00),
(22, 12, 2, 1, 2450.00, 2450.00),
(23, 12, 8, 2, 550.00, 1100.00),
(24, 13, 5, 1, 1750.00, 1750.00),
(25, 13, 7, 1, 1250.00, 1250.00),
(26, 14, 3, 1, 1550.00, 1550.00),
(27, 15, 2, 1, 2450.00, 2450.00),
(28, 15, 6, 1, 1950.00, 1950.00),
(29, 15, 8, 1, 550.00, 550.00),
(30, 16, 1, 2, 1650.00, 3300.00),
(31, 17, 6, 1, 1950.00, 1950.00),
(32, 18, 4, 1, 1850.00, 1850.00),
(33, 18, 6, 1, 1950.00, 1950.00),
(34, 19, 5, 1, 1750.00, 1750.00),
(35, 19, 8, 1, 550.00, 550.00),
(36, 20, 2, 1, 2450.00, 2450.00),
(37, 20, 5, 1, 1750.00, 1750.00),
(38, 21, 1, 1, 1650.00, 1650.00),
(39, 21, 2, 1, 2450.00, 2450.00),
(40, 21, 8, 2, 550.00, 1100.00),
(41, 22, 1, 1, 1650.00, 1650.00),
(42, 22, 3, 1, 1550.00, 1550.00);

-- 13. PAYMENTS (Component C: >= 20 realistic payment transactions)
INSERT INTO payments (id, order_id, payment_method, amount, payment_status, reference_number, paid_at, created_at) VALUES
(1, 1, 'CARD', 3230.00, 'COMPLETED', 'TXN-CARD-992144', '2026-10-15 12:32:00', '2026-10-15 12:30:00'),
(2, 2, 'CASH_ON_DELIVERY', 2450.00, 'PENDING', 'COD-202610-002', NULL, '2026-10-16 09:00:00'),
(3, 3, 'CARD', 4800.00, 'COMPLETED', 'TXN-CARD-119842', '2026-10-16 13:17:00', '2026-10-16 13:15:00'),
(4, 4, 'BANK_TRANSFER', 3500.00, 'COMPLETED', 'BT-COM-88231', '2026-10-17 11:50:00', '2026-10-17 11:45:00'),
(5, 5, 'CASH_ON_DELIVERY', 1750.00, 'COMPLETED', 'COD-202610-005', '2026-10-17 18:45:00', '2026-10-17 18:20:00'),
(6, 6, 'CARD', 5250.00, 'COMPLETED', 'TXN-CARD-449102', '2026-10-18 12:12:00', '2026-10-18 12:10:00'),
(7, 7, 'CASH_ON_DELIVERY', 2550.00, 'PENDING', 'COD-202610-007', NULL, '2026-10-18 19:30:00'),
(8, 8, 'CARD', 3510.00, 'COMPLETED', 'TXN-CARD-771239', '2026-10-19 13:02:00', '2026-10-19 13:00:00'),
(9, 9, 'BANK_TRANSFER', 4450.00, 'COMPLETED', 'BT-BOC-55129', '2026-10-19 19:50:00', '2026-10-19 19:45:00'),
(10, 10, 'CASH_ON_DELIVERY', 2000.00, 'PENDING', 'COD-202610-010', NULL, '2026-10-20 11:30:00'),
(11, 11, 'CARD', 2100.00, 'COMPLETED', 'TXN-CARD-883419', '2026-10-20 12:47:00', '2026-10-20 12:45:00'),
(12, 12, 'CARD', 5950.00, 'COMPLETED', 'TXN-CARD-662301', '2026-10-21 12:02:00', '2026-10-21 12:00:00'),
(13, 13, 'CASH_ON_DELIVERY', 2960.00, 'COMPLETED', 'COD-202610-013', '2026-10-21 19:10:00', '2026-10-21 18:30:00'),
(14, 14, 'CASH_ON_DELIVERY', 1550.00, 'FAILED', 'COD-CANCELLED', NULL, '2026-10-22 10:15:00'),
(15, 15, 'CARD', 5050.00, 'COMPLETED', 'TXN-CARD-331904', '2026-10-22 13:42:00', '2026-10-22 13:40:00'),
(16, 16, 'CARD', 3320.00, 'COMPLETED', 'TXN-CARD-552190', '2026-10-23 12:17:00', '2026-10-23 12:15:00'),
(17, 17, 'CASH_ON_DELIVERY', 1950.00, 'COMPLETED', 'COD-202610-017', '2026-10-23 17:20:00', '2026-10-23 17:00:00'),
(18, 18, 'BANK_TRANSFER', 3770.00, 'PENDING', 'BT-COM-77192', NULL, '2026-10-24 11:50:00'),
(19, 19, 'CARD', 3100.00, 'COMPLETED', 'TXN-CARD-119045', '2026-10-24 12:32:00', '2026-10-24 12:30:00'),
(20, 20, 'CASH_ON_DELIVERY', 3700.00, 'PENDING', 'COD-202610-020', NULL, '2026-10-24 17:15:00'),
(21, 21, 'CARD', 5000.00, 'COMPLETED', 'TXN-CARD-990145', '2026-10-25 13:02:00', '2026-10-25 13:00:00'),
(22, 22, 'CARD', 3230.00, 'COMPLETED', 'TXN-CARD-449190', '2026-10-25 18:32:00', '2026-10-25 18:30:00');

-- 14. ORDER STATUS HISTORY (Component C: Full lifecycle tracking audit records)
INSERT INTO order_status_history (id, order_id, status, notes, changed_by_user_id, changed_at) VALUES
(1, 1, 'PLACED', 'Customer placed order online', 6, '2026-10-15 12:30:00'),
(2, 1, 'PREPARING', 'Kitchen started order preparation', 3, '2026-10-15 12:35:00'),
(3, 1, 'READY', 'Order packed and handed to courier', 3, '2026-10-15 12:55:00'),
(4, 1, 'OUT_FOR_DELIVERY', 'Rider departed for delivery', 3, '2026-10-15 13:00:00'),
(5, 1, 'COMPLETED', 'Delivered to customer', 3, '2026-10-15 13:25:00'),
(6, 2, 'PLACED', 'Customer placed order for pickup', 7, '2026-10-16 09:00:00'),
(7, 2, 'PREPARING', 'Kitchen preparing order', 3, '2026-10-16 09:05:00'),
(8, 3, 'PLACED', 'Order submitted via website', 8, '2026-10-16 13:15:00'),
(9, 3, 'PREPARING', 'Biryani dum pot opened', 4, '2026-10-16 13:20:00'),
(10, 3, 'READY', 'Packed in thermal container', 4, '2026-10-16 13:40:00'),
(11, 3, 'OUT_FOR_DELIVERY', 'Courier rider Chamara dispatched', 3, '2026-10-16 13:45:00'),
(12, 3, 'COMPLETED', 'Successfully delivered', 3, '2026-10-16 14:10:00'),
(13, 4, 'PLACED', 'Order placed by customer', 9, '2026-10-17 11:45:00'),
(14, 4, 'PREPARING', 'Kottu prep started', 5, '2026-10-17 11:50:00'),
(15, 4, 'READY', 'Awaiting courier rider', 5, '2026-10-17 12:10:00'),
(16, 5, 'PLACED', 'Pickup order placed', 10, '2026-10-17 18:20:00'),
(17, 5, 'PREPARING', 'Lamprais leaf steamed', 4, '2026-10-17 18:22:00'),
(18, 5, 'READY', 'Packed at pickup counter', 4, '2026-10-17 18:35:00'),
(19, 5, 'COLLECTED', 'Customer arrived at counter', 3, '2026-10-17 18:40:00'),
(20, 5, 'COMPLETED', 'Order handed over', 3, '2026-10-17 18:45:00'),
(21, 6, 'PLACED', 'Online delivery order placed', 11, '2026-10-18 12:10:00'),
(22, 6, 'PREPARING', 'Kitchen preparing multiple portions', 3, '2026-10-18 12:15:00'),
(23, 6, 'READY', 'Food packed and quality approved', 3, '2026-10-18 12:40:00'),
(24, 6, 'OUT_FOR_DELIVERY', 'Rider departed to Nugegoda', 4, '2026-10-18 12:45:00'),
(25, 6, 'COMPLETED', 'Delivered to recipient', 4, '2026-10-18 13:20:00'),
(26, 7, 'PLACED', 'Order placed with COD', 12, '2026-10-18 19:30:00'),
(27, 7, 'PREPARING', 'Cheese kottu preparation underway', 5, '2026-10-18 19:35:00'),
(28, 7, 'READY', 'Ready for pickup by courier', 5, '2026-10-18 19:55:00'),
(29, 7, 'OUT_FOR_DELIVERY', 'Courier on the way to Duplication Road', 3, '2026-10-18 20:00:00'),
(30, 8, 'PLACED', 'Pickup order placed', 13, '2026-10-19 13:00:00'),
(31, 8, 'PREPARING', 'Hot butter cuttlefish tossed fresh', 3, '2026-10-19 13:05:00'),
(32, 8, 'READY', 'Ready for pickup at counter', 3, '2026-10-19 13:18:00'),
(33, 8, 'COMPLETED', 'Collected by customer', 3, '2026-10-19 13:25:00'),
(34, 12, 'PLACED', 'Large group delivery order', 17, '2026-10-21 12:00:00'),
(35, 12, 'PREPARING', 'Multiple chefs assigned', 3, '2026-10-21 12:05:00'),
(36, 12, 'READY', 'Corporate order boxed', 4, '2026-10-21 12:35:00'),
(37, 12, 'OUT_FOR_DELIVERY', 'Van dispatch for corporate order', 4, '2026-10-21 12:40:00'),
(38, 12, 'COMPLETED', 'Delivered to office reception', 4, '2026-10-21 13:10:00'),
(39, 14, 'PLACED', 'Order placed by customer', 19, '2026-10-22 10:15:00'),
(40, 14, 'CANCELLED', 'Cancelled upon customer telephone request', 3, '2026-10-22 10:20:00'),
(41, 18, 'PLACED', 'Order placed with bank transfer', 7, '2026-10-24 11:50:00'),
(42, 18, 'PREPARING', 'Slip verified by admin, kitchen cooking', 3, '2026-10-24 11:55:00'),
(43, 21, 'PLACED', 'Weekend order placed online', 10, '2026-10-25 13:00:00'),
(44, 21, 'PREPARING', 'Preparation commenced', 4, '2026-10-25 13:05:00'),
(45, 21, 'READY', 'Packed in thermal carriers', 4, '2026-10-25 13:30:00'),
(46, 21, 'OUT_FOR_DELIVERY', 'Rider left kitchen', 3, '2026-10-25 13:35:00'),
(47, 21, 'COMPLETED', 'Customer received order and confirmed', 3, '2026-10-25 14:05:00');

