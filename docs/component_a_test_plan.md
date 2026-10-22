# IT3113 Advanced Web Technologies
## Component A: Identity, Access and Administration — Test Plan & Results

**Student Name:** [Student 1 Name / Registration No]  
**Component:** Component A (Identity, Access & Administration)  
**Evaluation Target:** 15+ Comprehensive Test Cases (Functional, Validation, Security, Edge Cases)

---

### Test Case Matrix Summary

| Test ID | Category | Description | Input / Trigger | Expected Result | Status |
|---|---|---|---|---|---|
| **TC-01** | Functional | New Customer Registration | Valid Name, Email, Phone, Password | User created in `users` (role_id=3), logged in, redirected to home | **PASSED** |
| **TC-02** | Validation | Duplicate Email on Registration | Existing email address | Form rejected, shows "An account with this email address already exists" | **PASSED** |
| **TC-03** | Validation | Weak Password on Registration | Passwords lacking uppercase/digits | Rejected with specific message requiring uppercase, lowercase & digits | **PASSED** |
| **TC-04** | Validation | Sri Lankan Phone Number Format | Invalid phone number (e.g., `12345`) | Form rejected with requirement for 10-digit Sri Lankan phone number | **PASSED** |
| **TC-05** | Functional | Administrator Login & Routing | `admin@urbaneats.lk` / `Admin@12345` | Authenticated, redirected to `admin/dashboard.php` | **PASSED** |
| **TC-06** | Functional | Kitchen Staff Login & Routing | `staff@urbaneats.lk` / `Staff@12345` | Authenticated, redirected to `orders/kitchen.php` | **PASSED** |
| **TC-07** | Functional | Customer Login & Routing | `customer@urbaneats.lk` / `Customer@12345` | Authenticated, redirected to `public/index.php` | **PASSED** |
| **TC-08** | Security | Login Throttling (Temporary Lock) | 5 consecutive wrong passwords | 5th failed attempt triggers 15-minute lock; further attempts blocked | **PASSED** |
| **TC-09** | Security | Session Fixation Protection | Submit valid credentials on login | `session_regenerate_id(true)` issues new session ID, invalidating old | **PASSED** |
| **TC-10** | Security | RBAC Customer Accessing Admin Page | Customer navigates to `admin/users.php` | Access Denied (403), logged in `audit_log`, unauthorized warning shown | **PASSED** |
| **TC-11** | Security | Unauthenticated User Accessing Protected Page | Guest navigates to `auth/profile.php` | Redirected to `auth/login.php`, target preserved in session for return | **PASSED** |
| **TC-12** | Security | CSRF Protection on POST Forms | POST request with forged/missing CSRF token | Verification fails, changes rejected, security incident recorded | **PASSED** |
| **TC-13** | Security | Deactivated Account Login Attempt | User with `is_active = 0` attempts login | Rejected: "Your account has been deactivated by administrator" | **PASSED** |
| **TC-14** | Edge Case | Admin Self-Deactivation Prevention | Admin attempts to deactivate own account | Action aborted: "Security Violation: You cannot deactivate yourself" | **PASSED** |
| **TC-15** | Edge Case | Admin Self-Demotion Prevention | Admin attempts to change own role to Staff | Action aborted: "Security Violation: You cannot demote yourself" | **PASSED** |
| **TC-16** | Functional | User Profile Update | Edit name and phone number | Database updated, session `user_name` updated, `audit_log` entry added | **PASSED** |
| **TC-17** | Security | Change Password with Incorrect Current Password | Wrong current password entered | Update rejected, password remains unchanged, logged to audit | **PASSED** |
| **TC-18** | Functional | Time-Limited Token Password Reset | Forgot password requested | Cryptographic 32-byte token stored with 1h expiry; valid reset updates hash | **PASSED** |
| **TC-19** | Edge Case | Expired / Reused Reset Token | Submitting reset with already-used token | Rejected: "This password reset link has already been used" | **PASSED** |
| **TC-20** | Functional | Centralized Audit Logging (`log_action`) | Cross-component event (e.g. login, stock) | Record created in `audit_log` with user_id, action, IP, timestamp | **PASSED** |

---

### Detailed Test Execution Steps & Verification

#### TC-08: Login Throttling & Temporary Lockout
- **Objective:** Verify that 5 consecutive failed login attempts lock out the user/IP address for 15 minutes to defeat brute-force attacks.
- **Steps:**
  1. Navigate to `/auth/login.php`.
  2. Enter email `admin@urbaneats.lk` with wrong password `WrongPass1`.
  3. Submit 4 more times (5 total failed attempts).
- **Result:** On the 5th failed attempt, the application outputs:
  *"Too many failed login attempts. For security, your account access is temporarily locked for 15 minute(s)."*
  Form inputs are disabled.

#### TC-09: Session Fixation Prevention
- **Objective:** Confirm session ID is regenerated upon authentication.
- **Steps:**
  1. Inspect PHPSESSID cookie prior to login.
  2. Log in with valid credentials.
  3. Inspect PHPSESSID cookie immediately after login.
- **Result:** Cookie value changed completely; old session destroyed via `session_regenerate_id(true)`.

#### TC-10: Role-Based Access Control (RBAC)
- **Objective:** Ensure customers cannot view or modify administrative user management.
- **Steps:**
  1. Log in as `customer@urbaneats.lk`.
  2. Directly type URL: `http://localhost:8000/admin/users.php`.
- **Result:**
  1. Application intercepts request inside `require_role('admin')`.
  2. Event logged: `log_action('UNAUTHORIZED_PAGE_ACCESS', ...)`.
  3. Customer redirected to `/public/access_denied.php` (403 Forbidden).

#### TC-14 & TC-15: Administrator Self-Protection Edge Cases
- **Objective:** Prevent an administrator from locking out the entire system by deactivating or demoting themselves.
- **Steps:**
  1. Log in as `admin@urbaneats.lk`.
  2. Navigate to `admin/users.php`.
  3. Attempt to submit status change or role change targeting admin's own ID.
- **Result:** Prevented server-side with error message: *"Security Violation: You cannot deactivate your own administrative account."*
