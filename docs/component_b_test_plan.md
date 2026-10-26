# IT3113 Advanced Web Technologies
## Component B: Menu, Catalogue and Inventory — Test Plan & Results

**Student Name:** [Student 2 Name / Dineth Kalu Arachchi]  
**Component:** Component B (Menu, Catalogue and Inventory)  
**Evaluation Target:** 15+ Comprehensive Test Cases (Functional, Validation, Security, Edge Cases)

---

### Test Case Matrix Summary

| Test ID | Category | Description | Input / Trigger | Expected Result | Status |
|---|---|---|---|---|---|
| **TC-B01** | Functional | Public Menu Display & Category Filter | Select category (e.g., "Biryani & Rice") | Only dishes belonging to that category are retrieved and rendered | **PASSED** |
| **TC-B02** | Functional | Keyword Search in Menu | Enter "Kottu" or "Dum" in search bar | SQL parameterized search matches name/description; results displayed | **PASSED** |
| **TC-B03** | Functional | Sort Dishes by Price (Low to High / High to Low) | Select price sorting parameter | Dishes sorted properly via validated SQL ORDER BY whitelist | **PASSED** |
| **TC-B04** | Functional | Pagination Controls | Click page 2 / next page link | Queries OFFSET/LIMIT correctly and preserves active search/filter parameters | **PASSED** |
| **TC-B05** | Functional | Dish Detail View with Real-time Stock | Navigate to `detail.php?id=1` | Displays dish details, category name, rating, and live inventory portion count | **PASSED** |
| **TC-B06** | Functional | Add New Dish by Staff | Submit valid dish name, price, category, image | Dish inserted with prepared statements, initial inventory created, logged to audit | **PASSED** |
| **TC-B07** | Functional | Edit Existing Dish Details | Update price from 1500 to 1650 | Updates record in `dishes` table without breaking existing references | **PASSED** |
| **TC-B08** | Security | Soft Delete (is_active toggle) | Deactivate dish with active/past orders | Sets `is_active = 0`; dish hidden from public menu while past orders remain intact | **PASSED** |
| **TC-B09** | Security | Secure Image Upload (MIME & Extension) | Upload valid JPEG/PNG image | Extension, MIME type (`image/jpeg`), and size checked; renamed uniquely | **PASSED** |
| **TC-B10** | Security | Malicious Executable Upload Prevention | Upload PHP script disguised as image (`test.php.jpg`) | MIME type / finfo inspection fails; upload rejected; system logs event | **PASSED** |
| **TC-B11** | Security | SQL Injection Prevention in Sort/Search | Inject `' OR 1=1; --` in search or sort | Prepared statements parameter binding and sort whitelist neutralize payload | **PASSED** |
| **TC-B12** | Functional | Category Creation with Automatic Slug | Add category "Sri Lankan Curries" | Generates slug `sri-lankan-curries` and inserts into `categories` | **PASSED** |
| **TC-B13** | Validation | Category Deletion with Active Dishes | Attempt deleting category with linked dishes | Blocked: "Cannot delete category containing dishes. Move or delete dishes first." | **PASSED** |
| **TC-B14** | Functional | Stock Adjustment — Restock | Restock 10 portions of Biryani with reason | Increases `current_stock` by 10, writes record to `stock_movements`, logs to audit | **PASSED** |
| **TC-B15** | Edge Case | Stock Adjustment — Wastage Exceeding Stock | Attempt to deduct 50 portions when only 20 in stock | Transaction aborts; prevents negative inventory stock; friendly error shown | **PASSED** |
| **TC-B16** | Security | RBAC Restriction on Catalogue Management | Customer or Guest navigates to `menu/manage.php` | Access Denied / redirected to login; unauthorized attempt logged to audit | **PASSED** |
| **TC-B17** | Security | CSRF Protection on Dish/Stock Forms | Submit dish/stock form with missing/invalid token | Aborted with 403 Forbidden; state remains unaltered | **PASSED** |
| **TC-B18** | Functional | Transaction-safe `reduce_stock()` Bridge | Component C checkout calls `reduce_stock($id, $qty)` | Executes row locking `FOR UPDATE`, deducts stock, refuses negative stock | **PASSED** |

---

### Detailed Test Execution Steps & Verification

#### TC-B09 & TC-B10: Secure Image Upload & Verification
- **Objective:** Verify that uploaded dish photos are strictly validated against file extensions, genuine MIME types, and file size limits before storing in non-executable `public/uploads/` with unique names.
- **Steps:**
  1. Login as Kitchen Staff (`staff@urbaneats.lk`).
  2. Navigate to `menu/manage.php`.
  3. Upload an authentic image: verification passes, file saved as `dish_[hex].jpg`.
  4. Attempt uploading a disguised `.php` script: `process_dish_image_upload()` inspects MIME type using `finfo_file()` and rejects the request.

#### TC-B15 & TC-B18: Transaction-Safe Stock Control
- **Objective:** Ensure stock never goes below zero during concurrent checkouts or manual wastage adjustments.
- **Steps:**
  1. Call `reduce_stock(1, 999999)` from checkout.
  2. The SQL conditional statement `current_stock >= :qty` yields 0 affected rows.
  3. The transaction immediately rolls back and raises an exception.
