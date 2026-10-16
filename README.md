# UrbanEats Cloud Kitchen Platform
**IT3113 Advanced Web Technologies — Group Project (Year 3, Semester 1)**

UrbanEats is a complete, database-driven, cloud kitchen web platform built with **HTML5, CSS3, PHP 8.x (back end), and MySQL/MariaDB**. The architecture enforces Third Normal Form (3NF), prepared statements via PDO, robust role-based security, CSRF protection, and a centralized audit trail.

---

## 👥 Team Structure & Component Ownership

| Component | Member | Role & Module Scope | Status |
|---|---|---|---|
| **Component A** | **Student 1** | **Identity, Access & Administration:** Registration, login, roles, session hardening, throttling, user management, audit logs. | ✅ **100% Complete & Fully Implemented** |
| **Component B** | Student 2 | **Menu, Catalogue & Inventory:** Categories, dishes, image uploads, whitelisted search/sort, stock movements, and `reduce_stock()` contract. | 🏗️ **Scaffolded & Space Reserved** |
| **Component C** | Student 3 | **Ordering, Payment & Tracking:** Session cart, ACID checkout transaction, order stages workflow, kitchen queue, and sales reports. | 🏗️ **Scaffolded & Space Reserved** |

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
│   └── reports.php             # Sales & revenue reports [Comp C Scaffold]
├── menu/                       # [Component B Scaffold - Student 2]
│   ├── index.php               # Public menu, categories, whitelisted sort
│   ├── detail.php              # Dish detail view
│   ├── manage.php              # Staff dish CRUD & soft-delete
│   └── stock.php               # Stock dashboard, restock & wastage
├── orders/                     # [Component C Scaffold - Student 3]
│   ├── cart.php                # Session-based cart with live DB price checks
│   ├── checkout.php            # Single DB transaction order placement
│   ├── history.php             # Customer past orders
│   ├── track.php               # Live order status timeline
│   └── kitchen.php             # Kitchen order queue with state validation
├── database/
│   ├── urbaneats_db.sql        # Unified MySQL schema & seed data (>20 rows/table)
│   └── setup.php               # Automated CLI database importer & setup
├── docs/
│   ├── component_a_test_plan.md# 20 comprehensive test cases & results
│   ├── component_a_viva_prep.md# Viva voce technical question guide & answers
│   └── team_integration_guide.md# Guide for Teammates to build B & C
└── logs/
    └── error.log               # Server error & exception log
```

---

## 🧪 Testing Evidence & Viva Preparation
- Comprehensive Test Plan: [`docs/component_a_test_plan.md`](file:///Users/ramuthutheniya/Documents/GitHub/UrbanEats%20Cloud%20Kitchen%20Platform/docs/component_a_test_plan.md) (20 test cases covering functional, validation, security, and edge cases).
- Viva Voce Guide: [`docs/component_a_viva_prep.md`](file:///Users/ramuthutheniya/Documents/GitHub/UrbanEats%20Cloud%20Kitchen%20Platform/docs/component_a_viva_prep.md) (walkthroughs of login execution flow, `password_hash`, `session_regenerate_id`, 3NF justification, and live task modifications).
- Team Integration Contract: [`docs/team_integration_guide.md`](file:///Users/ramuthutheniya/Documents/GitHub/UrbanEats%20Cloud%20Kitchen%20Platform/docs/team_integration_guide.md).
