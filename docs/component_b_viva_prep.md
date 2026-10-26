# IT3113 Advanced Web Technologies
## Component B: Menu, Catalogue and Inventory — Technical Viva Preparation Guide

**Student Name:** [Student 2 Name / Dineth Kalu Arachchi]  
**Component:** Component B (Menu, Catalogue & Inventory)

---

### Core Architecture & Responsibilities

Component B is responsible for the cloud kitchen's food catalogue, public customer menu exploration, staff inventory adjustments, and transaction-safe stock synchronization.

- **Front-End:** Public responsive menu with keyword search, category filtering, price/rating sorting, pagination, dish detail view, and staff dish/category/stock management consoles.
- **Back-End:** Full CRUD with PDO prepared statements, soft delete (`is_active` flag), secure image upload with MIME validation, stock movement audits, and the `reduce_stock()` transaction bridge.
- **Database Tables (4):** `categories`, `dishes`, `inventory`, `stock_movements`.

---

### Key Viva Questions & Model Answers

#### Q1: Show how the search and pagination query is built. How do you stop SQL injection through the sort parameter?
**Answer:**
In `menu/index.php`, search parameters from `$_GET['search']` and `$_GET['category']` are bound strictly using PDO parameter markers (`?` or named parameters):
```php
$where_sql = "WHERE d.is_active = 1";
if (!empty($search)) {
    $where_sql .= " AND (d.name LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
```
For the `sort` parameter, dynamic values cannot be bound using SQL placeholders because SQL prepared statements only parameterize values, not identifiers or keywords (`ASC`/`DESC`). To completely prevent SQL injection:
We enforce a strict **server-side whitelist map**:
```php
$allowed_sorts = [
    'name_asc'    => 'd.name ASC',
    'price_asc'   => 'd.price ASC',
    'price_desc'  => 'd.price DESC',
    'rating_desc' => 'avg_rating DESC, d.name ASC'
];
$sort_query = $allowed_sorts[$sort] ?? 'd.name ASC';
```
Any arbitrary or malicious SQL injection string (e.g. `?sort=price; DROP TABLE users;`) defaults safely to `'d.name ASC'`, rendering SQL injection impossible.

---

#### Q2: What checks does your image upload perform, and why is the file extension alone not enough?
**Answer:**
In `menu/manage.php`, the image upload function (`process_dish_image_upload`) performs four successive defense-in-depth security checks:
1. **Upload Error Check:** Ensures `UPLOAD_ERR_OK`.
2. **File Size Limit:** Rejects files exceeding 5MB to prevent storage exhaustion Denial-of-Service.
3. **Extension Whitelist:** Checks that the extension is strictly in `['jpg', 'jpeg', 'png', 'webp']`.
4. **MIME Type Content Inspection:** Uses PHP `finfo_file()` or `mime_content_type()` to examine the magic bytes of the file content.
5. **Cryptographic Random Renaming:** Renames files using `dish_` + `bin2hex(random_bytes(8))` to prevent path traversal (`../`), null-byte injection, and overwriting existing assets.

**Why file extension alone is NOT enough:**
An attacker can easily rename a malicious PHP script (e.g. `shell.php`) to `shell.php.jpg` or `shell.jpg`. If the web server or application only inspects the extension, the executable script is stored on the server. If subsequently executed or included, the attacker gains full Remote Code Execution (RCE). Inspecting true MIME magic bytes and storing in a non-executable directory defeats this exploit.

---

#### Q3: Explain why `reduce_stock()` uses a transaction or a conditional UPDATE, and what happens with two simultaneous orders for the last item.
**Answer:**
If two customers attempt to purchase the final remaining portion of a dish at the exact same moment (a race condition):
- A naïve `SELECT stock` followed by `UPDATE stock = stock - 1` would cause both requests to see `stock = 1`, and both updates would execute, resulting in `stock = -1` (overselling food that the cloud kitchen cannot prepare).

In `config/db.php` and `orders/checkout.php`, we solve this in two ways:
1. **Row-Level Locking:** During checkout inside an active database transaction, we query `SELECT current_stock FROM inventory WHERE dish_id = ? FOR UPDATE`. This locks the row in InnoDB until the transaction commits or rolls back.
2. **Conditional Atomic UPDATE:**
```sql
UPDATE inventory 
SET current_stock = current_stock - :qty 
WHERE dish_id = :dish_id AND current_stock >= :qty;
```
If two simultaneous requests arrive:
- Transaction 1 executes the update; `rowCount()` equals 1.
- Transaction 2 attempts the update; because `current_stock` is now 0, the condition `current_stock >= 1` is false, and `rowCount()` returns 0.
- The application detects `rowCount() === 0`, throws an `Insufficient stock` exception, and executes `$pdo->rollBack()`.
The second order is aborted cleanly without data corruption or negative inventory.

---

#### Q4: Why is soft delete (`is_active`) used instead of hard SQL `DELETE` for dishes?
**Answer:**
Dishes are referenced as foreign keys in `order_items` (`CONSTRAINT fk_oi_dish FOREIGN KEY (dish_id) REFERENCES dishes (id)`).
If a staff member hard-deleted a dish using `DELETE FROM dishes WHERE id = ?`:
1. If foreign key constraints are enforced with `RESTRICT` or `NO ACTION`, MySQL will throw an error (`Cannot delete or update a parent row: a foreign key constraint fails`), breaking the staff management panel.
2. If configured with `CASCADE`, deleting the dish would delete the customer's historical order items, distorting financial audit reports and customer invoices.
By utilizing soft delete (`UPDATE dishes SET is_active = 0 WHERE id = ?`), the dish is instantly hidden from customer browsing while preserving full relational integrity for past orders, printable invoices, and sales reports.
