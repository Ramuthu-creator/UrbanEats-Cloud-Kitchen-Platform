# IT3113 UrbanEats Cloud Kitchen Platform
## Team Integration Guide & Week 1 Contract (For Teammates)

Welcome team members! This document defines the shared architecture, database contract, session standards, and API functions established in **Week 1** so that **Student 1 (Component A)**, **Student 2 (Component B)**, and **Student 3 (Component C)** can develop in parallel with zero merge conflicts.

---

### 1. Team Ownership Breakdown

| Component | Lead Member | Scope & Directory Location | Primary Tables Owned |
|---|---|---|---|
| **Component A** | Student 1 | `auth/`, `admin/users.php`, `admin/audit_log.php`, `includes/auth.php` | `users`, `roles`, `login_attempts`, `audit_log`, `password_resets` |
| **Component B** | Student 2 | `menu/` (`index.php`, `detail.php`, `manage.php`, `stock.php`) | `categories`, `dishes`, `inventory`, `stock_movements` |
| **Component C** | Student 3 | `orders/` (`cart.php`, `checkout.php`, `history.php`, `track.php`, `kitchen.php`), `admin/reports.php` | `orders`, `order_items`, `payments`, `order_status_history`, `coupons` |

---

### 2. Week 1 Database Contract & Schema

The unified database script is located at:
📁 `database/urbaneats_db.sql`

#### Users Table Contract (Owned by Component A, referenced by B & C)
```sql
users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,            -- 1: admin, 2: staff, 3: customer
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

#### Dishes Table Contract (Owned by Component B, referenced by C)
```sql
dishes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,  -- Soft delete flag
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

#### Orders Table Contract (Owned by Component C)
```sql
orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) UNIQUE,
    user_id INT NOT NULL,            -- References users(id)
    order_type ENUM('DELIVERY', 'PICKUP'),
    status ENUM('PLACED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY', 'COLLECTED', 'COMPLETED', 'CANCELLED'),
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

---

### 3. Session Variables Standard

Every authenticated page has access to these session variables:
- `$_SESSION['user_id']`: Integer ID of the authenticated user.
- `$_SESSION['role']`: String role name (`'admin'`, `'staff'`, `'customer'`).
- `$_SESSION['user_name']`: Full name string of user.
- `$_SESSION['user_email']`: Email string.
- `$_SESSION['cart']`: Associative array `[dish_id => quantity]`.

---

### 4. Shared Functions Exposed by Component A (Ready to Use)

#### Authentication Guards:
```php
require_once __DIR__ . '/../includes/auth.php';

// Protect any page requiring a logged-in user
require_login();

// Protect pages requiring specific roles
require_role('staff');
require_role(['admin', 'staff']);
```

#### Centralized Audit Logging:
Whenever an important event occurs in your component, call:
```php
// Component B Example:
log_action('MENU_ITEM_ADDED', "Added new dish: {$dishName}");
log_action('STOCK_ADJUSTMENT', "Restocked {$qty} units of dish {$dishId}");

// Component C Example:
log_action('ORDER_PLACED', "Order #{$orderNumber} placed for " . format_price($total));
log_action('ORDER_STATUS_UPDATED', "Order #{$orderNumber} moved to PREPARING");
```

#### Security & Output Escaping:
```php
// Escaping HTML
echo e($userInput);

// CSRF tokens in forms
echo csrf_input();
if (!verify_csrf_token()) { /* reject */ }

// Flash Notifications
set_flash('success', 'Your dish was added successfully!');
set_flash('error', 'Could not process order.');
```

#### Database Access (PDO):
```php
$db = get_db();
$stmt = $db->prepare("SELECT * FROM dishes WHERE category_id = :cat");
$stmt->execute([':cat' => $categoryId]);
$results = $stmt->fetchAll();
```

---

### 5. Stubs & Cross-Component Contracts

- **Component B exposes to Component C:**
  `reduce_stock(int $dishId, int $quantity): bool`
  Atomically deducts stock from `inventory` and returns `false` if stock is insufficient.
- **Component C exposes to Component A:**
  Order status transitions:
  `PLACED -> PREPARING -> READY -> OUT_FOR_DELIVERY / COLLECTED -> COMPLETED`

---

### 6. Git Workflow

- Clone repository:
  `git clone https://github.com/Ramuthu-creator/UrbanEats-Cloud-Kitchen-Platform.git`
- Component B student edits only files inside `menu/` and uses `includes/`.
- Component C student edits only files inside `orders/`, `admin/reports.php`, and uses `includes/`.
- Commit frequently with descriptive messages to provide evidence of individual contribution for your viva!
