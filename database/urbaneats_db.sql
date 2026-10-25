-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 25, 2026 at 10:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `urbaneats_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'SYSTEM_INIT', 'Initial database schema and default roles established', '127.0.0.1', 'System Installer', '2026-09-01 02:30:00'),
(2, 1, 'USER_LOGIN', 'Admin login successful', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-01 02:35:00'),
(3, 1, 'USER_CREATED', 'Created new admin user: admin2@urbaneats.lk', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-02 03:45:00'),
(4, 1, 'USER_CREATED', 'Created kitchen staff: staff@urbaneats.lk', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-03 05:00:00'),
(5, 3, 'USER_LOGIN', 'Staff member Chamara Silva logged in', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-03 05:05:00'),
(6, 6, 'USER_REGISTERED', 'Customer Kasun Jayasuriya self-registered', '112.134.20.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '2026-09-10 06:30:00'),
(7, 6, 'USER_LOGIN', 'Customer login successful', '112.134.20.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '2026-09-10 06:31:00'),
(8, NULL, 'LOGIN_FAILED', 'Failed login attempt for unknown user: test@unknown.com', '123.231.10.4', 'Mozilla/5.0 (Windows NT 10.0)', '2026-09-10 06:45:00'),
(9, 7, 'USER_REGISTERED', 'Customer Malik Fernando registered', '112.134.22.40', 'Mozilla/5.0 (Android 14; Mobile)', '2026-09-11 07:40:00'),
(10, 7, 'PROFILE_UPDATED', 'Updated contact phone number to 0762223344', '112.134.22.40', 'Mozilla/5.0 (Android 14; Mobile)', '2026-09-11 07:55:00'),
(11, 8, 'PASSWORD_RESET_REQUESTED', 'Requested password reset token', '112.134.33.12', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-12 08:52:00'),
(12, 8, 'PASSWORD_RESET_COMPLETED', 'Password reset successfully with secure token', '112.134.33.12', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-12 08:58:00'),
(13, 1, 'USER_STATUS_CHANGE', 'Deactivated user account id: 22 (Harshana Weerakkody)', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-26 10:00:00'),
(14, 1, 'ROLE_CHANGE', 'Promoted staff user id: 4 to supervisor role', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-09-27 04:30:00'),
(15, 3, 'MENU_ITEM_ADDED', 'Added new dish: Jaffna Crab Curry (Rs. 2,400.00)', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-01 04:00:00'),
(16, 3, 'STOCK_RESTOCKED', 'Restocked 50 portions of Chicken Dum Biryani', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-02 03:15:00'),
(17, 6, 'ORDER_PLACED', 'Order #ORD-202610-001 placed for LKR 3,850.00', '112.134.20.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '2026-10-05 13:40:00'),
(18, 3, 'ORDER_STATUS_UPDATED', 'Order #ORD-202610-001 updated to PREPARING', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-05 13:45:00'),
(19, 3, 'ORDER_STATUS_UPDATED', 'Order #ORD-202610-001 updated to READY', '192.168.1.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-10-05 14:05:00'),
(20, 1, 'VIEW_SALES_REPORT', 'Administrator viewed monthly sales report', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-10-10 10:30:00'),
(21, 6, 'PASSWORD_CHANGED', 'User Kasun Jayasuriya changed password via profile page', '112.134.20.15', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)', '2026-10-12 05:50:00'),
(22, 1, 'USER_LOGIN', 'Admin login from Colombo HQ terminal', '192.168.1.10', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', '2026-10-16 03:00:00'),
(23, 1, 'USER_LOGIN', 'Successful login for Nuwan Perera (admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-24 04:37:20'),
(24, 1, 'USER_LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-24 04:38:04'),
(25, 3, 'USER_LOGIN', 'Successful login for Chamara Silva (staff)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-24 04:38:26'),
(26, 3, 'SESSION_EXPIRED', 'User session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-24 05:16:18'),
(27, 3, 'USER_LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-24 05:16:18'),
(28, 3, 'USER_LOGIN', 'Successful login for Chamara Silva (staff)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-24 05:16:45'),
(29, 1, 'USER_LOGIN', 'Successful login for Nuwan Perera (admin)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 03:29:49'),
(30, 1, 'USER_LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 03:29:57'),
(31, 3, 'USER_LOGIN', 'Successful login for Chamara Silva (staff)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 03:30:19'),
(32, 3, 'UNAUTHORIZED_PAGE_ACCESS', 'Attempted access to /UrbanEats-Cloud-Kitchen-Platform/admin/dashboard.php requiring [admin]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 03:30:21'),
(33, 3, 'SESSION_EXPIRED', 'User session expired due to inactivity', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 04:25:12'),
(34, 3, 'USER_LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-25 04:25:12');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `is_active`, `created_at`) VALUES
(1, 'Signature Biryani', 'signature-biryani', 'Aromatic dum-cooked basmati rice with exotic spices and tender meats', 1, '2026-10-23 18:12:05'),
(2, 'Kottu & Roti', 'kottu-roti', 'Authentic street-style chopped flatbread with vegetables, eggs, and gravy', 1, '2026-10-23 18:12:05'),
(3, 'Rice & Curry Specialties', 'rice-and-curry', 'Traditional Sri Lankan village-style curries and fragrant samba rice', 1, '2026-10-23 18:12:05'),
(4, 'Appetizers & Bites', 'appetizers-bites', 'Crispy hot bites, devilled dishes, and savory treats', 1, '2026-10-23 18:12:05'),
(5, 'Desserts & Beverages', 'desserts-beverages', 'Traditional watalappan, caramel pudding, and fresh tropical fruit juices', 1, '2026-10-23 18:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('PERCENTAGE','FIXED') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `usage_limit` int(11) NOT NULL DEFAULT 100,
  `times_used` int(11) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order_value`, `usage_limit`, `times_used`, `expires_at`, `is_active`, `created_at`) VALUES
(1, 'WELCOME10', 'PERCENTAGE', 10.00, 1500.00, 500, 24, '2026-12-31 23:59:59', 1, '2026-10-23 18:12:05'),
(2, 'FLAT500', 'FIXED', 500.00, 3000.00, 100, 12, '2026-11-30 23:59:59', 1, '2026-10-23 18:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `dishes`
--

CREATE TABLE `dishes` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dishes`
--

INSERT INTO `dishes` (`id`, `category_id`, `name`, `description`, `price`, `image_url`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Royal Chicken Dum Biryani', 'Slow-cooked fragrant basmati rice with tender spiced chicken, boiled egg, mint raita, and spicy gravy', 1650.00, 'Royal Chicken Dum Biryani.jpeg', 1, '2026-10-23 18:12:05', '2026-10-23 18:13:34'),
(2, 1, 'Hyderabadi Mutton Biryani', 'Premium Australian mutton layered with saffron rice and authentic caramelized shallots', 2450.00, 'Hyderabadi Mutton Biryani.jpg', 1, '2026-10-23 18:12:05', '2026-10-23 18:13:59'),
(3, 2, 'Cheese Chicken Kottu', 'Sri Lankan Godamba roti chopped with chicken, melted mozzarella cheese, vegetables, and creamy curry sauce', 1550.00, 'chees kottu.jpg', 1, '2026-10-23 18:12:05', '2026-10-23 18:14:22'),
(4, 2, 'Spicy Seafood Dolphin Kottu', 'Parotta chopped with fresh prawns, cuttlefish, chili paste, and leeks in thick devilled sauce', 1850.00, 'sea food .jpeg', 1, '2026-10-23 18:12:05', '2026-10-23 18:14:44'),
(5, 3, 'Lamprais Special (Chicken)', 'Dutch-Burgher recipe wrapped in banana leaf: yellow rice, chicken curry, frikkadel meatball, blachan, seeni sambol, and ash plantain', 1750.00, 'Lamprais Special (Chicken).jpeg', 1, '2026-10-23 18:12:05', '2026-10-23 18:15:07'),
(6, 4, 'Hot Butter Cuttlefish', 'Crisp battered cuttlefish tossed in seasoned butter, chili flakes, capsicum, and spring onions', 1950.00, 'het butter .jpeg', 1, '2026-10-23 18:12:05', '2026-10-23 18:15:26'),
(7, 4, 'Devilled Chicken Bites', 'Crispy chicken tenders tossed in sweet & spicy Sri Lankan devilled chili sauce', 1250.00, 'devel chicken.jpg', 1, '2026-10-23 18:12:05', '2026-10-23 18:15:52'),
(8, 5, 'Traditional Jaggery Watalappan', 'Steamed coconut custard with pure Kithul jaggery, cardamom, nutmeg, and cashew nuts', 550.00, 'Traditional Jaggery Watalappan.jpeg', 1, '2026-10-23 18:12:05', '2026-10-23 18:16:08'),
(9, 5, 'Mango Mojito', '', 850.00, 'dish_6adc56f3af037.jpeg', 1, '2026-10-24 06:57:55', '2026-10-24 06:57:55');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `dish_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reorder_level` int(11) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `dish_id`, `quantity`, `last_updated`, `reorder_level`) VALUES
(1, 1, 15, '2026-10-24 07:08:17', 5),
(2, 2, 20, '2026-10-24 07:08:17', 5),
(3, 3, 10, '2026-10-24 07:08:17', 5),
(4, 4, 5, '2026-10-24 07:08:17', 5),
(5, 5, 30, '2026-10-24 07:08:17', 5),
(6, 6, 12, '2026-10-24 07:08:17', 5),
(7, 7, 8, '2026-10-24 07:08:17', 5),
(8, 8, 25, '2026-10-24 07:08:17', 5);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `is_successful` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `is_successful`, `attempted_at`) VALUES
(1, 'admin@urbaneats.lk', '192.168.1.10', 1, '2026-10-14 02:30:00'),
(2, 'staff@urbaneats.lk', '192.168.1.25', 1, '2026-10-14 02:45:00'),
(3, 'customer@urbaneats.lk', '112.134.20.15', 1, '2026-10-14 07:00:00'),
(4, 'malik.fernando@gmail.com', '112.134.22.40', 1, '2026-10-14 07:30:00'),
(5, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 08:31:00'),
(6, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 08:31:30'),
(7, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 08:32:00'),
(8, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 08:32:40'),
(9, 'attacker@suspicious.net', '203.0.113.45', 0, '2026-10-14 08:33:15'),
(10, 'admin@urbaneats.lk', '192.168.1.10', 1, '2026-10-15 03:00:00'),
(11, 'staff2@urbaneats.lk', '192.168.1.30', 1, '2026-10-15 03:30:00'),
(12, 'nadeesha.senaratne@yahoo.com', '112.134.33.12', 0, '2026-10-15 05:30:00'),
(13, 'nadeesha.senaratne@yahoo.com', '112.134.33.12', 1, '2026-10-15 05:32:00'),
(14, 'rohan.desilva@hotmail.com', '112.134.44.18', 1, '2026-10-15 07:40:00'),
(15, 'tharushi.wickramasinghe@gmail.com', '112.134.55.21', 1, '2026-10-15 09:10:00'),
(16, 'harshana.weerakkody@gmail.com', '112.134.99.30', 0, '2026-10-15 09:30:00'),
(17, 'admin2@urbaneats.lk', '192.168.1.12', 1, '2026-10-16 02:30:00'),
(18, 'staff3@urbaneats.lk', '192.168.1.35', 1, '2026-10-16 02:40:00'),
(19, 'customer@urbaneats.lk', '112.134.20.15', 1, '2026-10-16 02:55:00'),
(20, 'admin@urbaneats.lk', '192.168.1.10', 1, '2026-10-16 03:00:00'),
(21, 'admin@urbaneats.lk', '::1', 1, '2026-10-24 04:37:20'),
(22, 'staff@urbaneats.lk', '::1', 1, '2026-10-24 04:38:26'),
(23, 'staff@urbaneats.lk', '::1', 1, '2026-10-24 05:16:45'),
(24, 'admin@urbaneats.lk', '::1', 1, '2026-10-25 03:29:49'),
(25, 'staff@urbaneats.lk', '::1', 1, '2026-10-25 03:30:19');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_type` enum('DELIVERY','PICKUP') NOT NULL DEFAULT 'DELIVERY',
  `delivery_address` text DEFAULT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `status` enum('PLACED','PREPARING','READY','OUT_FOR_DELIVERY','COLLECTED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PLACED',
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `order_type`, `delivery_address`, `contact_phone`, `status`, `subtotal`, `discount_amount`, `delivery_fee`, `total_amount`, `coupon_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'ORD-202610-001', 6, 'DELIVERY', 'No. 42, Galle Road, Colombo 03', '0761112233', 'COMPLETED', 3200.00, 320.00, 350.00, 3230.00, 1, 'Please make it extra spicy', '2026-10-15 07:00:00', '2026-10-23 18:12:05'),
(2, 'ORD-202610-002', 7, 'PICKUP', NULL, '0762223344', 'PREPARING', 2450.00, 0.00, 0.00, 2450.00, NULL, 'Will pick up in 30 minutes', '2026-10-16 03:30:00', '2026-10-23 18:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `dish_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `dish_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 1, 1, 1650.00, 1650.00),
(2, 1, 3, 1, 1550.00, 1550.00),
(3, 2, 2, 1, 2450.00, 2450.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('PLACED','PREPARING','READY','OUT_FOR_DELIVERY','COLLECTED','COMPLETED','CANCELLED') NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by_user_id` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `status`, `notes`, `changed_by_user_id`, `changed_at`) VALUES
(1, 1, 'PLACED', 'Customer placed order online', 6, '2026-10-15 07:00:00'),
(2, 1, 'PREPARING', 'Kitchen started order preparation', 3, '2026-10-15 07:05:00'),
(3, 1, 'READY', 'Order packed and handed to courier', 3, '2026-10-15 07:25:00'),
(4, 1, 'OUT_FOR_DELIVERY', 'Rider departed for delivery', 3, '2026-10-15 07:30:00'),
(5, 1, 'COMPLETED', 'Delivered to customer', 3, '2026-10-15 07:55:00'),
(6, 2, 'PLACED', 'Customer placed order for pickup', 7, '2026-10-16 03:30:00'),
(7, 2, 'PREPARING', 'Kitchen preparing order', 3, '2026-10-16 03:35:00');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token_hash`, `expires_at`, `is_used`, `created_at`) VALUES
(1, 'nadeesha.senaratne@yahoo.com', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', '2026-09-12 15:22:00', 1, '2026-09-12 08:52:00');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_method` enum('CASH_ON_DELIVERY','CARD','BANK_TRANSFER') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('PENDING','COMPLETED','FAILED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `reference_number` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `payment_method`, `amount`, `payment_status`, `reference_number`, `paid_at`, `created_at`) VALUES
(1, 1, 'CARD', 3230.00, 'COMPLETED', 'TXN-CARD-992144', '2026-10-15 12:32:00', '2026-10-23 18:12:05'),
(2, 2, 'CASH_ON_DELIVERY', 2450.00, 'PENDING', NULL, NULL, '2026-10-23 18:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `created_at`) VALUES
(1, 'admin', 'Administrator', 'Full system access, user management, audit logs, and analytics', '2026-10-23 18:12:05'),
(2, 'staff', 'Kitchen Staff', 'Menu, inventory management, and kitchen order processing', '2026-10-23 18:12:05'),
(3, 'customer', 'Customer', 'Browse menu, order food, simulate payments, and track order progress', '2026-10-23 18:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `dish_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `movement_type` enum('RESTOCK','ORDER_DEDUCT','WASTAGE','CORRECTION') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `dish_id`, `user_id`, `movement_type`, `quantity`, `reason`, `created_at`) VALUES
(1, 1, 3, 'RESTOCK', 50, 'Morning batch prepared', '2026-10-15 03:00:00'),
(2, 1, 3, 'ORDER_DEDUCT', 5, 'Deducted for orders #ORD-202610-001 to 003', '2026-10-15 07:15:00'),
(3, 7, 4, 'WASTAGE', 5, 'Discarded due to prep standard rejection', '2026-10-15 12:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `phone`, `password_hash`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Nuwan Perera', 'admin@urbaneats.lk', '0771234567', '$2y$12$JXNsqvOqc.IyBaoiqdEoveiLlDSa6rtIcC8fCpISFnHffSPkE2M6C', 1, '2026-09-01 02:30:00', '2026-10-23 18:12:05'),
(2, 1, 'Anura Bandara', 'admin2@urbaneats.lk', '0779876543', '$2y$12$JXNsqvOqc.IyBaoiqdEoveiLlDSa6rtIcC8fCpISFnHffSPkE2M6C', 1, '2026-09-02 03:45:00', '2026-10-23 18:12:05'),
(3, 2, 'Chamara Silva', 'staff@urbaneats.lk', '0712345678', '$2y$12$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-03 05:00:00', '2026-10-23 18:12:05'),
(4, 2, 'Kusal Mendis', 'staff2@urbaneats.lk', '0713456789', '$2y$12$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-04 05:30:00', '2026-10-23 18:12:05'),
(5, 2, 'Dilani Fernando', 'staff3@urbaneats.lk', '0714567890', '$2y$12$PvxUZ4Uu8hqkYrHy97SJreltpDMjb19uRGO3jA8M04yhQQ3kuus96', 1, '2026-09-05 06:15:00', '2026-10-23 18:12:05'),
(6, 3, 'Kasun Jayasuriya', 'customer@urbaneats.lk', '0761112233', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-10 06:30:00', '2026-10-23 18:12:05'),
(7, 3, 'Malik Fernando', 'malik.fernando@gmail.com', '0762223344', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-11 07:40:00', '2026-10-23 18:12:05'),
(8, 3, 'Nadeesha Senaratne', 'nadeesha.senaratne@yahoo.com', '0763334455', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-12 08:50:00', '2026-10-23 18:12:05'),
(9, 3, 'Rohan De Silva', 'rohan.desilva@hotmail.com', '0764445566', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-13 10:00:00', '2026-10-23 18:12:05'),
(10, 3, 'Tharushi Wickramasinghe', 'tharushi.wickramasinghe@gmail.com', '0765556677', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-14 11:10:00', '2026-10-23 18:12:05'),
(11, 3, 'Sanjaya Abeykoon', 'sanjaya.abeykoon@outlook.com', '0766667788', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-15 12:20:00', '2026-10-23 18:12:05'),
(12, 3, 'Ishani Gunawardena', 'ishani.gunawardena@gmail.com', '0767778899', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-16 12:30:00', '2026-10-23 18:12:05'),
(13, 3, 'Priyantha Lokuge', 'priyantha.lokuge@gmail.com', '0768889900', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-17 13:45:00', '2026-10-23 18:12:05'),
(14, 3, 'Fathima Rizwan', 'fathima.rizwan@gmail.com', '0751112244', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-18 14:55:00', '2026-10-23 18:12:05'),
(15, 3, 'Devinda Samaraweera', 'devinda.samaraweera@gmail.com', '0752223355', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-19 15:35:00', '2026-10-23 18:12:05'),
(16, 3, 'Sanduni Rathnayake', 'sanduni.rathnayake@yahoo.com', '0753334466', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-20 04:10:00', '2026-10-23 18:12:05'),
(17, 3, 'Akila Dassanayake', 'akila.dassanayake@gmail.com', '0754445577', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-21 05:20:00', '2026-10-23 18:12:05'),
(18, 3, 'Chathurika Gamage', 'chathurika.gamage@gmail.com', '0755556688', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-22 06:05:00', '2026-10-23 18:12:05'),
(19, 3, 'Dinuka Hettiarachchi', 'dinuka.hettiarachchi@gmail.com', '0756667799', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-23 07:15:00', '2026-10-23 18:12:05'),
(20, 3, 'Mahesh Karunaratne', 'mahesh.karunaratne@gmail.com', '0757778800', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-24 08:25:00', '2026-10-23 18:12:05'),
(21, 3, 'Niluka Jayawardena', 'niluka.jayawardena@gmail.com', '0758889911', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 1, '2026-09-25 08:40:00', '2026-10-23 18:12:05'),
(22, 3, 'Harshana Weerakkody', 'harshana.weerakkody@gmail.com', '0759990022', '$2y$12$gn9.d97yCLp3fBali9pTnehAJVDd2xl7tzfh6GRMltP72Uk13vEBK', 0, '2026-09-26 09:50:00', '2026-10-23 18:12:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_date` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `dishes`
--
ALTER TABLE `dishes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dishes_category` (`category_id`),
  ADD KEY `idx_dishes_active` (`is_active`),
  ADD KEY `idx_dishes_price` (`price`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_throttle` (`email`,`attempted_at`),
  ADD KEY `idx_login_attempts_ip` (`ip_address`,`attempted_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `fk_orders_coupon` (`coupon_id`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_created` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_oi_dish` (`dish_id`),
  ADD KEY `idx_oi_order` (`order_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_osh_user` (`changed_by_user_id`),
  ADD KEY `idx_osh_order` (`order_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pw_reset_lookup` (`token_hash`,`expires_at`),
  ADD KEY `idx_pw_reset_email` (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_order` (`order_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sm_user` (`user_id`),
  ADD KEY `idx_sm_dish` (`dish_id`),
  ADD KEY `idx_sm_created` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dishes`
--
ALTER TABLE `dishes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `dishes`
--
ALTER TABLE `dishes`
  ADD CONSTRAINT `fk_dishes_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_oi_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_osh_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_sm_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
