# IT3113 Advanced Web Technologies
## Component A: Technical Viva Voce Preparation Guide

**Candidate:** Component A Lead (Identity, Access and Administration)  
**Project:** UrbanEats Cloud Kitchen Platform

---

### Question 1: Walk through what happens from the moment the login form is submitted to the moment the dashboard loads.

**Step-by-Step Execution Flow:**
1. **POST Request & CSRF Verification:**
   - The browser sends a `POST` request to `auth/login.php` with `email`, `password`, and the hidden `csrf_token`.
   - `verify_csrf_token()` performs a timing-attack safe check (`hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])`). If invalid, the request is immediately rejected.
2. **Server-Side Validation:**
   - Inputs are sanitized and validated. Email is validated via `filter_var(..., FILTER_VALIDATE_EMAIL)` and password presence is confirmed.
3. **Throttling Verification (`check_login_throttle`):**
   - The system queries `login_attempts` for failed attempts within the last 15 minutes (`LOCKOUT_TIME_MINUTES`) for that email and client IP.
   - If 5 or more consecutive failed attempts exist without an intervening successful login, authentication is blocked with remaining lock minutes.
4. **User Lookup via Prepared Statement:**
   - PDO prepares `SELECT u.*, r.name AS role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = :email LIMIT 1` using bound parameters to prevent SQL injection.
5. **Cryptographic Verification (`password_verify`):**
   - `password_verify($password, $user['password_hash'])` extracts the salt and cost parameter from the stored Bcrypt hash and verifies whether the plaintext matches.
6. **Account Status Check:**
   - Confirms `is_active == 1`. If deactivated, access is denied.
7. **Session Hardening & Regeneration (`session_regenerate_id(true)`):**
   - The previous session ID is destroyed and replaced with a newly minted ID to prevent Session Fixation attacks.
   - `$_SESSION['user_id']`, `$_SESSION['role']`, `$_SESSION['user_name']`, and `$_SESSION['last_activity']` are set.
8. **Audit Trail Recording (`log_action`):**
   - Inserts an audit entry (`USER_LOGIN`) into `audit_log` with the user ID, timestamp, and IP address.
9. **Role-Based Redirect:**
   - If the authenticated role is `admin`, the browser is redirected via `header("Location: " . base_url('admin/dashboard.php'))`.
   - On `admin/dashboard.php`, the guard `require_role('admin')` validates the role in session and allows the dashboard layout to render.

---

### Question 2: Why `password_hash()` instead of MD5 or SHA-1? What does `session_regenerate_id()` prevent?

**Why `password_hash()` over MD5 or SHA-1?**
- **MD5 and SHA-1 are fast message-digest algorithms:** They are designed to compute hashes rapidly (billions per second on modern GPUs), making them completely vulnerable to offline dictionary and brute-force rainbow table attacks. MD5 and SHA-1 also suffer from known collision vulnerabilities.
- **`password_hash()` uses Bcrypt (or Argon2):**
  - **Adaptive Work Factor (Cost):** The computational cost can be scaled over time as hardware speeds up, forcing brute-force attacks to be prohibitively slow.
  - **Automatic Cryptographic Salting:** It generates a cryptographically secure, unique salt for every single password automatically. Even if two users share the same password, their stored hashes are completely different, defeating precomputed rainbow tables.

**What does `session_regenerate_id()` prevent?**
- It prevents **Session Fixation Attacks**.
- In a session fixation attack, an attacker tricks a victim into using a known session identifier (e.g. by sending a link with `PHPSESSID=attacker_fixed_id`). If the system does not change the session ID upon authentication, the attacker can hijack the victim's session using the fixed ID.
- Calling `session_regenerate_id(true)` replaces the session ID with a new random identifier and deletes the old session data, invalidating any pre-authentication session ID.

---

### Question 3: How does `require_role()` protect a page, and what happens if a user types an admin URL directly?

**Mechanism:**
1. Every protected page begins by declaring its access policy:
   ```php
   require_once __DIR__ . '/../includes/auth.php';
   require_role('admin'); // or require_role(['admin', 'staff']);
   ```
2. `require_role()` first calls `require_login()`. If `$_SESSION['user_id']` is absent or the session has timed out (>30 min inactivity), the destination URL is stored in `$_SESSION['redirect_url']`, a flash message is set, and the user is redirected to `auth/login.php`.
3. If logged in, `has_role($allowedRoles)` checks `$_SESSION['role']`.
4. **What happens if a customer types an admin URL directly?**
   - The customer's role (`customer`) fails the `has_role('admin')` test.
   - The security guard immediately logs an incident:
     `log_action('UNAUTHORIZED_PAGE_ACCESS', 'Attempted access to /admin/users.php requiring [admin]')`.
   - The user is immediately redirected to `public/access_denied.php` (HTTP 403 Forbidden).
   - The execution stops via `exit;`, ensuring zero administrative data is rendered.

---

### Question 4: Database Normalization (3NF) & Foreign Key Justification

1. **3NF Compliance:**
   - **1NF:** All attributes are atomic (no repeating groups, scalar columns).
   - **2NF:** Every non-key attribute is fully functionally dependent on the entire primary key (single-column auto-increment primary keys prevent partial dependencies).
   - **3NF:** No transitive dependencies. For instance, role names and descriptions are separated into a normalized `roles` table. The `users` table contains only `role_id`, eliminating transitive dependency between `user_id -> role_id -> role_name`.
2. **Foreign Key Integrity Rules:**
   - `users -> roles`: `ON DELETE RESTRICT ON UPDATE CASCADE`. System roles cannot be deleted while users are assigned to them, preventing orphaned users.
   - `audit_log -> users`: `ON DELETE SET NULL ON UPDATE CASCADE`. If a user account is deleted, audit history remains intact with `user_id = NULL` rather than corrupting compliance logs.
   - `order_items -> dishes`: `ON DELETE RESTRICT`. A dish cannot be hard-deleted if it was purchased in past orders; dishes must be soft-deleted using `is_active = 0`.

---

### Question 5: How does Component A connect with Component B and Component C?

1. **Identity & Role Context:**
   - Both Component B (`menu/manage.php`, `menu/stock.php`) and Component C (`orders/kitchen.php`, `admin/reports.php`) include `includes/auth.php` and invoke `require_role('staff')` or `require_role('admin')`.
2. **Shared Audit Log:**
   - Whenever Component B adjusts stock (`menu/stock.php`) or adds a dish, it calls `log_action('STOCK_ADJUSTMENT', ...)`.
   - Whenever Component C accepts an order or changes order status (`orders/kitchen.php`), it calls `log_action('ORDER_PLACED', ...)`.
   - All events appear centralized in Component A's `admin/audit_log.php`.
3. **Session Variables Shared Contract:**
   - `$_SESSION['user_id']`: Used by Component C when saving orders in `orders(user_id)`.
   - `$_SESSION['role']`: Used by header navigation to show role-tailored links.
   - `$_SESSION['cart']`: Session-based cart storage agreed in Week 1 contract.

---

### Question 6: Potential Live Modification Tasks in Viva & How to Solve Them

- **Task A: Add a new column to users table and display it in `admin/users.php`:**
  1. Alter table: `ALTER TABLE users ADD COLUMN department VARCHAR(50) DEFAULT 'General';`
  2. In `admin/users.php`, add `<th>Department</th>` in `<thead>` and `<td><?= e($u['department']) ?></td>` in `<tbody>`.
- **Task B: Change lockout threshold from 5 to 3 failed attempts:**
  - Open `config/config.local.php` (or `config/db.php`), locate `define('MAX_LOGIN_ATTEMPTS', 5);`, and update the value to `3`.
- **Task C: Require phone number confirmation during registration:**
  - In `auth/register.php`, add a new input field `phone_confirm` and add `if ($phone !== $phoneConfirm) $errors['phone_confirm'] = 'Phone numbers must match';`.
