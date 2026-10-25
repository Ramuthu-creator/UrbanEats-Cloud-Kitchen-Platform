# IT3113 Advanced Web Technologies
## Component C: Ordering, Payment & Tracking — Technical Viva Voce Defense Guide

**Student Name:** [Student 3 / Component C Lead]  
**Component:** Component C (Ordering, Payment & Tracking)  
**Module Coordinator:** Ms. Nethmi Mohotti  
**Assessment Target:** Individual Questioning (10 to 12 minutes) & Live Modification (30 Marks)

---

### 1. Appendix B Official Viva Questions & Model Answers

#### Q1: "Why are prices re-read from the database at checkout? Show the checkout transaction and describe the rollback case."

**Model Answer:**
> "In web applications, the client environment (the browser) is fundamentally untrusted. If we accepted item prices sent from browser hidden inputs or JavaScript variables, a malicious user could inspect the DOM and modify a Biryani priced at Rs. 1,650.00 down to Rs. 1.00 before submitting the form.
> 
> To guarantee financial integrity, our session cart only stores dish identifiers and quantities: `$_SESSION['cart'][$dishId] = $qty`. At checkout, we execute a query directly against the database:
> ```php
> SELECT d.id, d.name, d.price, i.current_stock 
> FROM dishes d 
> LEFT JOIN inventory i ON d.id = i.dish_id 
> WHERE d.id IN (...) AND d.is_active = 1
> ```
> We re-compute the subtotal, coupon discounts, and delivery fees on the server.
> 
> **The Checkout Transaction:**
> The checkout is wrapped inside a single PDO database transaction:
> ```php
> $pdo->beginTransaction();
> try {
>     // 1. Insert into orders
>     // 2. Insert into order_items (snapshotting unit_price at time of purchase)
>     // 3. Insert into payments
>     // 4. Insert into order_status_history (initial 'PLACED' record)
>     // 5. Update coupons (increment times_used)
>     // 6. Deduct stock atomically:
>     //    UPDATE inventory SET current_stock = current_stock - :qty 
>     //    WHERE dish_id = :dish_id AND current_stock >= :qty
>     if ($stockStmt->rowCount() === 0) {
>         throw new Exception('Insufficient stock');
>     }
>     $pdo->commit();
> } catch (Exception $e) {
>     $pdo->rollBack();
>     // Reverts all inserted records
> }
> ```
> 
> **The Rollback Case:**
> If a dish runs out of stock concurrently between cart view and order placement, or if the database connectivity drops during item insertion, the `catch` block calls `$pdo->rollBack()`. This ensures ACID atomicity—either the order, items, payment, and stock deduction all succeed together, or no partial or corrupt records persist in the database."

---

#### Q2: "How do you prevent an order jumping from Placed straight to Completed?"

**Model Answer:**
> "We implement a server-side **Finite State Machine (FSM)** in `orders/kitchen_queue.php`. Client-side UI disables illegal buttons, but more importantly, the server validates every requested state transition before executing the SQL update:
> 
> ```php
> $allowedTransitions = [
>     'PLACED'           => ['PREPARING', 'CANCELLED'],
>     'PREPARING'        => ['READY', 'CANCELLED'],
>     'READY'            => ['OUT_FOR_DELIVERY', 'COLLECTED', 'CANCELLED'],
>     'OUT_FOR_DELIVERY' => ['COMPLETED', 'CANCELLED'],
>     'COLLECTED'        => ['COMPLETED'],
>     'COMPLETED'        => [], // Terminal state
>     'CANCELLED'        => []  // Terminal state
> ];
> 
> $currentStatus = $order['status'];
> if (!in_array($targetStatus, $allowedTransitions[$currentStatus], true)) {
>     log_action('ILLEGAL_WORKFLOW_ATTEMPT', ...);
>     set_flash('error', 'Invalid workflow transition.');
>     redirect('orders/kitchen_queue.php');
> }
> ```
> If an attacker or rogue staff member submits a POST request attempting to jump from `PLACED` directly to `COMPLETED`, the server checks `$allowedTransitions['PLACED']`. Because `COMPLETED` is not in that list, the transition is aborted, logged in the centralized security audit log, and an error is returned."

---

#### Q3: "Explain the SQL behind the top-selling dishes report (joins, GROUP BY, ORDER BY)."

**Model Answer:**
> "The query located in `admin/reports.php` generates our dish ranking:
> ```sql
> SELECT 
>     d.id AS dish_id,
>     d.name AS dish_name,
>     c.name AS category_name,
>     d.price AS catalog_price,
>     COUNT(DISTINCT oi.order_id) AS distinct_orders,
>     SUM(oi.quantity) AS total_units_sold,
>     SUM(oi.subtotal) AS total_dish_revenue,
>     ROUND(AVG(oi.unit_price), 2) AS avg_realized_price
> FROM order_items oi
> JOIN orders o ON oi.order_id = o.id
> JOIN dishes d ON oi.dish_id = d.id
> JOIN categories c ON d.category_id = c.id
> WHERE o.status = 'COMPLETED'
>   AND DATE(o.created_at) >= :start_date 
>   AND DATE(o.created_at) <= :end_date
> GROUP BY d.id, d.name, c.name, d.price
> ORDER BY total_dish_revenue DESC
> LIMIT 10;
> ```
> 
> **Breakdown:**
> 1. **JOINs:** We join `order_items` to `orders` to filter by order status (`COMPLETED`) and date range; we join `dishes` to get the dish title and price; and `categories` to get the category label.
> 2. **GROUP BY:** Because `order_items` contains multiple rows across many orders for the same dish, `GROUP BY d.id, d.name, c.name, d.price` groups all individual sales lines of a specific dish together.
> 3. **Aggregates:**
>    - `SUM(oi.quantity)` calculates the total number of portions sold.
>    - `SUM(oi.subtotal)` calculates the total revenue generated by that dish.
>    - `COUNT(DISTINCT oi.order_id)` determines how many separate orders included this item.
> 4. **ORDER BY:** `ORDER BY total_dish_revenue DESC` ranks the highest earning dishes at the top of the report."

---

#### Q4: "Where does your component depend on a teammate's component, and how did you test it before they finished?"

**Model Answer:**
> "Component C has two key dependencies established in our **Week 1 Integration Contract**:
> 1. **Component A (Identity & Access):** Session variables `$_SESSION['user_id']`, `$_SESSION['role']`, authentication guard `require_login()`, and `log_action()`.
> 2. **Component B (Menu & Inventory):** Tables `dishes` and `inventory`, plus the shared stock deduction function `reduce_stock($dishId, $qty)`.
> 
> **How we tested before teammates finished:**
> In accordance with Section 7 of the assignment brief, we created `includes/stubs.php`. If Component B was not yet ready, `stubs.php` provided fallback implementations of `reduce_stock()` and `get_dish_by_id()` that matched the agreed signatures. This allowed Component C to develop and test the cart and checkout transaction independently without waiting for teammate code."

---

#### Q5: "Describe one bug you found late and how you fixed it."

**Model Answer:**
> "During checkout testing, we noticed that if two customers simultaneously ordered the last remaining portion of 'Hot Butter Cuttlefish', both checkouts were passing because the initial check saw `current_stock = 1`.
> 
> **The Fix:**
> We eliminated this race condition by implementing a conditional atomic update directly inside the checkout transaction:
> ```sql
> UPDATE inventory 
> SET current_stock = current_stock - :qty 
> WHERE dish_id = :dish_id AND current_stock >= :qty
> ```
> If another transaction deducted the last stock first, `rowCount()` returned `0`. Our code detects this, throws an Exception, and rolls back the entire transaction, alerting the second customer that stock has just run out."

---

### 2. Live Modification Scenarios (Likely Viva Tasks)

#### Task 1: "Add a discount percentage badge on the cart page."
- Open `orders/cart.php`.
- In the item row, calculate discount: `round((($original - $price) / $original) * 100)`.
- Output: `<span class="badge badge-success"><?= $pct ?>% OFF</span>`.

#### Task 2: "Add an extra column to the Top-Selling Dishes table showing average portions per order."
- Open `admin/reports.php`.
- In the SQL SELECT clause, add:
  `ROUND(SUM(oi.quantity) * 1.0 / COUNT(DISTINCT oi.order_id), 1) AS avg_portions_per_order`
- In the table header, add: `<th>Avg Portions / Order</th>`.
- In the table body loop, output: `<td><?= $dish['avg_portions_per_order'] ?></td>`.

#### Task 3: "Add a quick-action button in Kitchen Queue to mark an order Cancelled with a custom note."
- In `orders/kitchen_queue.php`, the form already targets `action="update_status"` with `target_status="CANCELLED"` and passes `notes`.
- To add a predefined note like 'Ingredients ran out':
  Pass `<input type="hidden" name="notes" value="Cancelled: kitchen ran out of ingredients">`.
