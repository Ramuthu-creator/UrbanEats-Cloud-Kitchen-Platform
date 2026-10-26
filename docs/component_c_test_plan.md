# IT3113 Advanced Web Technologies
## Component C: Ordering, Payment and Tracking — Test Plan & Results

**Student Name:** [Student 3 / Component C Lead]  
**Component:** Component C (Ordering, Payment & Tracking)  
**Evaluation Target:** 20 Comprehensive Test Cases (Functional, Validation, Security, Edge Cases)

---

### Test Case Matrix Summary

| Test ID | Category | Description | Input / Trigger | Expected Result | Status |
|---|---|---|---|---|---|
| **TC-C01** | Functional | Add Dish to Shopping Cart | Click "Add to Order" with quantity 2 on dish detail | Item added to `$_SESSION['cart']`, running totals update, flash notification shown | **PASSED** |
| **TC-C02** | Functional | Update Quantity in Cart Stepper | Increment (+) and decrement (-) buttons on cart | Line total and subtotal recomputed immediately; min quantity clamped to 1 | **PASSED** |
| **TC-C03** | Functional | Remove Item from Cart | Click trash can 🗑️ icon on line item | Item removed from session cart; subtotal and totals updated; flash confirmation | **PASSED** |
| **TC-C04** | Functional | Clear Entire Shopping Cart | Click "Clear Cart" button | All items removed; coupon reset; empty cart visual state displayed | **PASSED** |
| **TC-C05** | Security | Live Price Re-Verification | Tampered client price payload | Checkout recalculates totals strictly from database `dishes.price`; ignores client tampering | **PASSED** |
| **TC-C06** | Validation | Stock Level Enforcement in Cart | Request quantity greater than `inventory.current_stock` | Cart warns user; clamps quantity to maximum available portions | **PASSED** |
| **TC-C07** | Functional | Valid Percentage Coupon Code | Apply code `WELCOME10` on subtotal >= Rs. 1500 | 10% discount computed and deducted from gross subtotal | **PASSED** |
| **TC-C08** | Validation | Expired / Non-Existent Coupon | Apply invalid code `EXPIRED99` | Rejected with error flash: "Coupon code is invalid or has expired" | **PASSED** |
| **TC-C09** | Validation | Coupon Minimum Order Value | Apply `FLAT500` (min Rs. 3000) on Rs. 1650 subtotal | System notifies: "Minimum order value of Rs. 3,000.00 required" | **PASSED** |
| **TC-C10** | Security | Unauthenticated Checkout Guard | Guest visits `/orders/checkout.php` | Redirected to `/auth/login.php` with flash: "Please log in to access this page" | **PASSED** |
| **TC-C11** | Validation | Delivery Address Validation | Choose Delivery with empty street address | Form rejected with server-side validation error requiring address | **PASSED** |
| **TC-C12** | Functional | Pickup Mode Fee Exclusion | Select "Cloud Kitchen Pickup" on checkout | Delivery fee set to Rs. 0.00; delivery address field hidden | **PASSED** |
| **TC-C13** | Security | Single Database Transaction Atomicity | Complete checkout form and submit | Orders, order items, payments, history, stock deduction processed in 1 transaction | **PASSED** |
| **TC-C14** | Edge Case | Stock Exhaustion Rollback | Simultaneous order for dish with 0 stock | Transaction rolls back completely; no order, payment, or corrupt records saved | **PASSED** |
| **TC-C15** | Functional | Printable Tax Invoice & Print CSS | Open invoice and trigger `window.print()` | `@media print` cleanly removes navbar, footer, buttons; outputs formatted invoice | **PASSED** |
| **TC-C16** | Functional | Live Order Tracking Visual Timeline | Enter order number on `/orders/track.php` | Visual timeline highlights active stage with animated pulse and timestamped history | **PASSED** |
| **TC-C17** | Security | Order Tracking Access Authorization | Customer attempts tracking another user's order | Denied access with error flash; logged to centralized `audit_log` | **PASSED** |
| **TC-C18** | Security | Workflow State Machine Jump Prevention | Staff tries to jump order from PLACED to COMPLETED | Illegal transition rejected; state remains PLACED; error flash shown | **PASSED** |
| **TC-C19** | Functional | Kitchen Queue Order Stage Progression | Staff clicks "Start Cooking" then "Food Ready" | Order status advances PLACED -> PREPARING -> READY; logged to history and audit | **PASSED** |
| **TC-C20** | Functional | Admin Top-Selling Dishes Query | Admin loads `/admin/reports.php` | SQL with `JOIN`, `GROUP BY`, and `SUM()` aggregates top dishes by revenue | **PASSED** |

---

### Detailed Test Execution Steps & Verification

#### TC-C13: Single Database Transaction Atomicity
- **Objective:** Verify that checkout operations (order header, items, payments, stock deduction, and status history) execute within a single atomic database transaction.
- **Steps:**
  1. Add 2 portions of "Royal Chicken Dum Biryani" to cart.
  2. Navigate to `/orders/checkout.php` as customer Kasun Jayasuriya.
  3. Fill phone `0761112233`, address `No. 42, Galle Road, Colombo 03`, select `CARD` payment.
  4. Submit order.
- **Result:**
  - Order row created in `orders` with unique number (e.g. `ORD-202610-023`).
  - Items recorded in `order_items` with unit price at time of purchase.
  - Payment record created in `payments` with transaction reference.
  - `order_status_history` record created with initial stage `PLACED`.
  - Stock deducted in `inventory` and recorded in `stock_movements`.
  - Session cart emptied; user redirected to official confirmation invoice.

#### TC-C14: Stock Exhaustion & Contention Rollback
- **Objective:** Ensure that if any dish in the cart has insufficient stock during the transaction, the entire checkout rolls back completely without leaving orphaned records.
- **Steps:**
  1. Set stock of dish ID 7 ("Devilled Chicken Bites") to 0 in `inventory`.
  2. Attempt checkout with dish ID 7 in cart.
- **Result:**
  - The conditional `UPDATE inventory SET current_stock = current_stock - qty WHERE current_stock >= qty` returns 0 affected rows.
  - An exception is thrown and `$pdo->rollBack()` executes.
  - No row is inserted in `orders`, `order_items`, or `payments`.
  - User receives error notification: *"Unable to complete order: Devilled Chicken Bites has insufficient stock."*

#### TC-C18: Workflow State Machine Jump Prevention
- **Objective:** Defend against unauthorized order state transitions (e.g., jumping from `PLACED` directly to `COMPLETED`).
- **Steps:**
  1. Log in as Kitchen Staff (`staff@urbaneats.lk`).
  2. Locate an order in `PLACED` stage.
  3. Attempt to forge a POST request with `target_status = 'COMPLETED'`.
- **Result:**
  - The Finite State Machine in `kitchen_queue.php` inspects `$allowedTransitions['PLACED']` which only permits `['PREPARING', 'CANCELLED']`.
  - The request is immediately rejected.
  - Security event logged to `audit_log`: `ILLEGAL_WORKFLOW_ATTEMPT`.
  - Flash notification: *"Invalid workflow transition: Cannot move order from PLACED to COMPLETED."*
