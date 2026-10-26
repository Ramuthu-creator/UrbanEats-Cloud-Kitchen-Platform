# UrbanEats Cloud Kitchen Platform 

UrbanEats is a complete, database-driven, cloud kitchen web platform built with **HTML5, CSS3, PHP 8.x (back end), and MySQL/MariaDB**. The architecture enforces Third Normal Form (3NF), prepared statements via PDO, robust role-based security, CSRF protection, and a centralized audit trail.

---

## 👥 Team Structure & Component Ownership

| Component | Member | Role & Module Scope | Status |
|---|---|---|---|
| **Component A** | **Student 1** | **Identity, Access & Administration:** Registration, login, roles, session hardening, throttling, user management, audit logs. | ✅ **100% Complete & Fully Implemented** |
| **Component B** | **Student 2** | **Menu, Catalogue & Inventory:** Categories, dishes, secure image uploads, whitelisted search/sort, stock movements, and `reduce_stock()` contract. | ✅ **100% Complete & Fully Implemented** |
| **Component C** | **Student 3** | **Ordering, Payment & Tracking:** Session cart, ACID checkout transaction, order stages workflow, kitchen queue, and sales reports. | ✅ **100% Complete & Fully Implemented** |

---

## 🚀 Setup & Installation Instructions

### Option 1: Running with XAMPP / WAMP / LAMP (Recommended for Submission Evaluation)
1. **Clone the repository into your web server root:**
   - On XAMPP (Windows/Mac): Place in `htdocs/urbaneats` or keep in your workspace.
2. **Import Database in phpMyAdmin:**
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Create a new database named `urbaneats_db` with `utf8mb4_unicode_ci` collation.
   - Go to **Import** and select `database/urbaneats_db.sql`.
   - Click **Go** to import structure, foreign keys, indexes, and seed data (>20 realistic rows in each main table).
3. **Configure Database Connection:**
   - Copy `config/config.sample.php` to `config/config.local.php`:
     ```bash
     cp config/config.sample.php config/config.local.php
     ```
   - Verify connection settings in `config/config.local.php` (default XAMPP host `127.0.0.1`, user `root`, blank password).
4. **Access Platform in Browser:**
   - Navigate to `http://localhost/urbaneats/public/index.php` (or your local directory URL).

---

### Option 2: Running with PHP Built-in Server (Zero-Configuration Testing)
1. **Initialize the database:**
   ```bash
   php database/setup.php
   ```
2. **Start PHP server from project root:**
   ```bash
   php -S localhost:8000
   ```
3. **Open browser:**
   - `http://localhost:8000/public/index.php`

---

## 🔑 Pre-Seeded Test Accounts (For Viva & Evaluation)

All accounts come pre-configured in `database/urbaneats_db.sql` with secure Bcrypt password hashing (`password_hash`):

| Role | Email Address | Password | Permissions & Dashboard |
|---|---|---|---|
| **Administrator** | `admin@urbaneats.lk` | `Admin@12345` | User management, role elevation, status toggle, audit logs |
| **Administrator 2** | `admin2@urbaneats.lk` | `Admin@12345` | Secondary administrator account |
| **Kitchen Staff** | `staff@urbaneats.lk` | `Staff@12345` | Kitchen order queue, dish catalog, stock adjustments |
| **Kitchen Staff 2**| `staff2@urbaneats.lk` | `Staff@12345` | Sous Chef fulfillment account |
| **Customer** | `customer@urbaneats.lk` | `Customer@12345`| Browse menu, cart, checkout, live tracking, order history |
| **Customer (Deactivated)** | `harshana.weerakkody@gmail.com` | `Customer@12345` | Test account with `is_active=0` to demonstrate login blocking |

*Plus 17 additional realistic customer accounts seeded in the database.*

---

## 🛠️ Features Implemented in Component A

### Front End
- **Semantic HTML5 & Accessible Navigation:** Dynamic navigation that changes automatically depending on the authenticated role (`Guest`, `Customer`, `Staff`, `Admin`).
- **Registration Page (`auth/register.php`):** Customer self-registration with server-side validation, password strength checks, and unique email constraints.
- **Login Page (`auth/login.php`):** Secure login with CSRF token verification, throttling countdown timer on lockout, and handy demo credentials panel.
- **Profile Edit Page (`auth/profile.php`):** View and update full name and phone number with audit logging.
- **Change Password Page (`auth/change_password.php`):** Validates existing password with `password_verify` before updating to a new hash.
- **Time-Limited Password Reset (`auth/forgot_password.php` & `auth/reset_password.php`):** Generates 32-byte cryptographically secure random token, stores SHA-256 hash in `password_resets` with 1-hour expiry, and displays link on screen as permitted by the specification.
- **Administrator Dashboard (`admin/dashboard.php`):** Key metrics (total users, active accounts, staff, customers), module shortcuts, and recent audit activity.
- **User Management Console (`admin/users.php`):** Search users by name/email/phone, filter by role and status, toggle active/inactive state, change roles, and add new users. Prevents self-deactivation and self-demotion!
- **Security Audit Trail (`admin/audit_log.php`):** Full chronological log of logins, failures, registrations, status updates, and kitchen events with search, action filtering, and client IP tracking.
- **Shared Access Denied Page (`public/access_denied.php`):** 403 Forbidden page used across the entire platform.

### Back End & Security Architecture
- **Strict Server-Side Validation:** All form inputs validated on server; client hints provided without relying on JS.
- **Password Security:** Hashes passwords via `password_hash($pass, PASSWORD_DEFAULT)` and authenticates via `password_verify()`. MD5 and SHA-1 strictly avoided.
- **Session Hardening:** `session_regenerate_id(true)` called upon login; `HttpOnly`, `SameSite`, and `use_only_cookies` flags enforced; 30-minute inactivity timeout.
- **Login Throttling:** Temporary lockout after 5 consecutive failed attempts within 15 minutes, recorded in `login_attempts` table.
- **Reusable Guards (`includes/auth.php`):** `require_login()`, `require_role('admin')`, and `require_role(['admin', 'staff'])` protecting pages across the platform.
- **Centralized Audit Logger (`log_action()`):** Shared function called by all components to record critical actions in `audit_log`.
- **Pure CSS3 Design System:** Zero external CSS frameworks (no Bootstrap, no Tailwind). Hand-written CSS variables, responsive across 360px, 768px, and 1200px.

---

## 🍲 Features Implemented in Component B (Student 2)

### Front End
- **Public Menu Page (`menu/index.php`):** Responsive menu with category filtering, live keyword search, price/rating sorting, pagination, and dish ratings.
- **Dish Detail Page (`menu/detail.php`):** High-resolution food presentation with ingredients description, real-time portions stock indicator, customer ratings breakdown, and direct "Add to Order" integration.
- **Staff Dish Management Console (`menu/manage.php`):** Full CRUD for dishes with Add/Edit modes, image upload preview, soft-delete toggle, and catalogue overview.
- **Category Management (`menu/manage_categories.php`):** Staff interface to create, edit, and delete food categories with automatic URL slug generation and dependency checks.
- **Stock & Inventory Dashboard (`menu/stock_dashboard.php`):** Staff control center highlighting low-stock and out-of-stock dishes with Restock and Wastage adjustment tools.

### Back End & Database Integration
- **Full CRUD with Prepared Statements:** All dish and category queries parameterized via PDO.
- **Soft Delete (`is_active` flag):** Deactivating a dish hides it from customer browsing while strictly preserving past orders and relational integrity in `order_items`.
- **Whitelisted Search, Filter & Sort:** All `$_GET` search and filter parameters sanitized; sort order strictly validated against an approved whitelist array, neutralizing SQL injection vectors.
- **Secure Image Upload:** Multi-level file verification checking upload errors, 5MB file size limit, extension whitelist (`jpg`, `jpeg`, `png`, `webp`), and true MIME type magic-byte inspection via `finfo_file()`, followed by cryptographic renaming.
- **Transaction-Safe `reduce_stock()` Bridge:** Callable by Component C at checkout; uses row-level locking (`FOR UPDATE`) and conditional updates preventing negative inventory.
- **Stock Movements Audit Trail:** Every restock or wastage adjustment recorded in `stock_movements` with user attribution, timestamp, quantity, and reason.

---

## 🛒 Features Implemented in Component C (Student 3)

### Front End
- **Shopping Cart (`orders/cart.php`):** Session-based cart with dynamic running totals (item subtotal, delivery fee, coupon discounts, net grand total), interactive quantity increments/decrements (+/-), single-item removal, cart clearing, and live promo code application.
- **Checkout Processing (`orders/checkout.php`):** Choice of fulfillment (Home Delivery with validated address vs Counter Pickup with zero delivery fee), contact phone validation, and simulated payment gateways (Cash on Delivery, 16-digit Card simulation, and Bank Transfer with deposit reference).
- **Printable Confirmation & Invoice (`orders/confirmation.php`):** Official tax invoice with print stylesheet (`@media print`) that cleanly isolates the bill from site navigation, itemized dish breakdowns, customer details, and payment confirmation.
- **Customer Order History (`orders/history.php`):** Customer dashboard showing past dining orders, order status badges, spend totals, and direct links to live tracking and invoices.
- **Live Order Tracking (`orders/track.php`):** Step-by-step visual progression tracker (Placed -> Preparing -> Ready -> Out for Delivery/Pickup -> Completed) with pulsing active stage animations and full audit trail from `order_status_history`.
- **Kitchen Order Queue (`orders/kitchen_queue.php`):** Real-time queue for kitchen staff to transition orders through their lifecycle with elapsed-time alerts, search filtering, and auto-refresh toggle.
- **Sales & Revenue Reports (`admin/reports.php`):** Executive reporting console with date-range filters, quick presets (Today, Yesterday, Week, Month, All Time), KPI cards, top-selling dishes ranking, order status distribution, and daily ledger.

### Back End & Transaction Architecture
- **Single Database Transaction:** Checkout runs inside a single atomic transaction: inserts `orders`, inserts `order_items` (recording `unit_price` at time of purchase), inserts `payments`, inserts initial `order_status_history`, increments coupon `times_used`, and deducts inventory stock with rollback on failure.
- **Live Database Price Re-Verification:** Never trusts client prices or availability; re-queries active dishes directly from `dishes` table.
- **Finite State Machine (FSM):** Strict server-side validation preventing illegal workflow jumps (e.g., jumping from `PLACED` directly to `COMPLETED`).
- **Atomic Stock Deduction:** Uses conditional SQL updates (`WHERE current_stock >= :qty`) preventing race conditions and negative inventory.
- **Advanced SQL Analytics:** Advanced reports utilizing `JOIN`, `GROUP BY`, and aggregate functions (`SUM`, `COUNT`, `AVG`, `ROUND`).

---

## 📁 Repository Directory Structure

```text
urbaneats/
├── config/
│   ├── config.sample.php       # Configuration template without passwords
│   └── db.php                  # Shared PDO singleton connection
├── includes/
│   ├── auth.php                # Reusable security guards (require_login, require_role)
│   ├── csrf.php                # Cryptographic CSRF protection
│   ├── errors.php              # Custom error/exception handlers & logging
│   ├── functions.php           # Shared helpers, URL resolver, audit logger (log_action)
│   ├── header.php              # Shared semantic header with role-adaptive nav
│   ├── footer.php              # Shared semantic footer & mobile menu script
│   └── stubs.php               # Shared integration stubs (reduce_stock, etc.)
├── public/
│   ├── index.php               # Platform landing page & integration hub
│   ├── access_denied.php       # Shared 403 Forbidden page
│   ├── error.php               # Friendly 500 error page
│   ├── css/
│   │   └── style.css           # Master hand-written responsive CSS stylesheet
│   └── uploads/                # Dish image uploads directory
├── auth/                       # [Component A - Student 1]
│   ├── login.php               # Login with throttling & CSRF
│   ├── register.php            # Customer registration
│   ├── logout.php              # Secure logout & session destruction
│   ├── profile.php             # Profile viewer & editor
│   ├── change_password.php     # Change password
│   ├── forgot_password.php     # Password reset token generator
│   └── reset_password.php      # Password reset submission
├── admin/                      # [Component A & C Administration]
│   ├── dashboard.php           # Admin overview & metrics (Comp A)
│   ├── users.php               # User CRUD, role changer, toggle status (Comp A)
│   ├── audit_log.php           # Central audit trail inspector (Comp A)
│   └── reports.php             # Sales & revenue reports (Comp C)
├── menu/                       # [Component B - Student 2]
│   ├── index.php               # Public menu, categories, whitelisted sort
│   ├── detail.php              # Dish detail view & Add to Cart hook
│   ├── manage.php              # Staff dish CRUD & soft-delete
│   └── stock_dashboard.php     # Stock dashboard, restock & wastage
├── orders/                     # [Component C - Student 3]
│   ├── cart.php                # Session cart with live DB price checks & coupons
│   ├── checkout.php            # Single DB transaction order placement & stock deduction
│   ├── confirmation.php        # Printable tax invoice & confirmation
│   ├── history.php             # Customer past orders & spend statistics
│   ├── track.php               # Live order status timeline & audit trail
│   └── kitchen_queue.php       # Kitchen order queue with FSM state machine validation
├── database/
│   ├── urbaneats_db.sql        # Unified MySQL schema & seed data (>20 rows/table)
│   └── setup.php               # Automated CLI database importer & setup
├── docs/
│   ├── component_a_test_plan.md# Component A: 20 comprehensive test cases & results
│   ├── component_a_viva_prep.md# Component A: Viva voce technical question guide & answers
│   ├── component_c_test_plan.md# Component C: 20 comprehensive test cases & results
│   ├── component_c_viva_prep.md# Component C: Viva voce technical question guide & answers
│   └── team_integration_guide.md# Guide for Teammates to build B & C
└── logs/
    └── error.log               # Server error & exception log
```

---

## 🧪 Testing Evidence & Viva Preparation
- Component A Test Plan: [`docs/component_a_test_plan.md`](file:///docs/component_a_test_plan.md) (20 test cases covering authentication, RBAC, throttling, and session hardening)
- Component A Viva Voce Guide: [`docs/component_a_viva_prep.md`](file:///docs/component_a_viva_prep.md) (technical questions, architecture justification, and code walkthroughs)
- Component B Test Plan: [`docs/component_b_test_plan.md`](file:///docs/component_b_test_plan.md) (18 test cases covering menu search, pagination, soft delete, image security, and stock transactions)
- Component B Viva Voce Guide: [`docs/component_b_viva_prep.md`](file:///docs/component_b_viva_prep.md) (whitelisted sorting, secure image upload MIME validation, and reduce_stock race conditions)
- Component C Test Plan: [`docs/component_c_test_plan.md`](file:///docs/component_c_test_plan.md) (20 test cases covering cart, checkout atomicity, stock deduction, FSM, and SQL reports)
- Component C Viva Voce Guide: [`docs/component_c_viva_prep.md`](file:///docs/component_c_viva_prep.md) (walkthroughs of price re-verification, checkout transaction rollback, state jump prevention, and top dishes query)
- Team Integration Contract: [`docs/team_integration_guide.md`](file:///docs/team_integration_guide.md)

