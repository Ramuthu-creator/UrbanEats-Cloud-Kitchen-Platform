<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Authentication & Authorization Guard System
 *
 * Implements session hardening, role-based access control (RBAC),
 * login throttling (5 failed attempts lock), and user session management.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Configure hardened session settings before session starts
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    
    // In HTTPS environments, enable secure cookie
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    
    session_start();
}

// Session Inactivity Timeout Guard (Default: 30 minutes)
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        log_action('SESSION_EXPIRED', 'User session expired due to inactivity', (int)$_SESSION['user_id']);
        logout_user();
        set_flash('warning', 'Your session expired due to inactivity. Please log in again.');
        redirect('auth/login.php');
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Checks if a user is currently authenticated
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/**
 * Returns the currently authenticated user's ID, or null
 */
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Returns the role string of the current user ('admin', 'staff', 'customer'), or null
 */
function current_user_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Returns full profile array of the currently logged-in user
 */
function current_user(): ?array {
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    $userId = current_user_id();
    if (!$userId) {
        return null;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare(
            "SELECT u.id, u.role_id, r.name AS role, r.display_name AS role_display, 
                    u.full_name, u.email, u.phone, u.is_active, u.created_at
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.id = :id AND u.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        $cachedUser = $user ?: null;
        return $cachedUser;
    } catch (Exception $e) {
        log_system_error('CURRENT_USER_FETCH_FAIL', $e->getMessage());
        return null;
    }
}

/**
 * Checks if the current user possesses one of the allowed roles
 *
 * @param string|array $roles Single role name or array of permitted roles
 */
function has_role($roles): bool {
    if (!is_logged_in()) {
        return false;
    }

    $currentRole = current_user_role();
    if (!$currentRole) {
        return false;
    }

    if (is_array($roles)) {
        return in_array($currentRole, $roles, true);
    }

    return $currentRole === $roles;
}

/**
 * Guard function: Requires user to be logged in.
 * If not authenticated, redirects to the login page and preserves target URL.
 */
function require_login(): void {
    if (!is_logged_in()) {
        // Store intended destination to redirect back after successful login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        set_flash('error', 'Please log in to access this page.');
        redirect('auth/login.php');
    }
}

/**
 * Guard function: Requires user to have specific role(s).
 * If unauthorized, logs the attempt in audit trail and routes to access denied page.
 *
 * @param string|array $allowedRoles
 */
function require_role($allowedRoles): void {
    require_login();

    if (!has_role($allowedRoles)) {
        $rolesStr = is_array($allowedRoles) ? implode(', ', $allowedRoles) : $allowedRoles;
        log_action(
            'UNAUTHORIZED_PAGE_ACCESS',
            sprintf("Attempted access to %s requiring [%s]", $_SERVER['REQUEST_URI'], $rolesStr)
        );

        redirect('public/access_denied.php');
    }
}

/**
 * Checks login throttling status for a given email and client IP.
 * Enforces temporary lock after 5 consecutive failed attempts within 15 minutes.
 *
 * @return array ['locked' => bool, 'remaining_minutes' => int, 'failed_count' => int]
 */
function check_login_throttle(string $email, string $ip): array {
    try {
        $db = get_db();
        $lockoutMinutes = LOCKOUT_TIME_MINUTES;
        $cutoffTime = date('Y-m-d H:i:s', time() - ($lockoutMinutes * 60));

        // Count failed attempts within lockout window that occurred AFTER the last successful login
        $sql = "SELECT COUNT(*) AS failed_count, MAX(attempted_at) AS last_failed_at
                FROM login_attempts
                WHERE (email = :email OR ip_address = :ip)
                  AND is_successful = 0
                  AND attempted_at >= :cutoff
                  AND attempted_at > COALESCE(
                      (SELECT MAX(attempted_at) FROM login_attempts WHERE email = :email_sub AND is_successful = 1),
                      '1970-01-01 00:00:00'
                  )";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':email'     => $email,
            ':ip'        => $ip,
            ':cutoff'    => $cutoffTime,
            ':email_sub' => $email
        ]);

        $row = $stmt->fetch();
        $failedCount = (int)($row['failed_count'] ?? 0);

        if ($failedCount >= MAX_LOGIN_ATTEMPTS) {
            $lastFailedTime = strtotime($row['last_failed_at']);
            $unlockTime = $lastFailedTime + ($lockoutMinutes * 60);
            $remainingSeconds = max(0, $unlockTime - time());
            $remainingMinutes = (int)ceil($remainingSeconds / 60);

            if ($remainingSeconds > 0) {
                return [
                    'locked'            => true,
                    'remaining_minutes' => $remainingMinutes ?: 1,
                    'failed_count'      => $failedCount
                ];
            }
        }

        return [
            'locked'            => false,
            'remaining_minutes' => 0,
            'failed_count'      => $failedCount
        ];
    } catch (Exception $e) {
        log_system_error('THROTTLE_CHECK_ERROR', $e->getMessage());
        return ['locked' => false, 'remaining_minutes' => 0, 'failed_count' => 0];
    }
}

/**
 * Records a login attempt in the database
 */
function record_login_attempt(string $email, string $ip, bool $isSuccess): void {
    try {
        $db = get_db();
        $stmt = $db->prepare(
            "INSERT INTO login_attempts (email, ip_address, is_successful, attempted_at)
             VALUES (:email, :ip, :is_success, :attempted_at)"
        );
        $stmt->execute([
            ':email'        => $email,
            ':ip'           => $ip,
            ':is_success'   => $isSuccess ? 1 : 0,
            ':attempted_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        log_system_error('LOGIN_ATTEMPT_RECORD_FAIL', $e->getMessage());
    }
}

/**
 * Authenticates user credentials with password_verify and session regeneration
 *
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user' => ?array]
 */
function authenticate_user(string $email, string $password): array {
    $ip = get_client_ip();

    // 1. Check Login Throttling
    $throttle = check_login_throttle($email, $ip);
    if ($throttle['locked']) {
        log_action('LOGIN_THROTTLED', "Throttled login attempt for {$email} from {$ip}");
        return [
            'success' => false,
            'message' => "Too many failed login attempts. For security, your account access is temporarily locked for {$throttle['remaining_minutes']} minute(s).",
            'user'    => null
        ];
    }

    try {
        $db = get_db();
        $stmt = $db->prepare(
            "SELECT u.id, u.role_id, r.name AS role, r.display_name AS role_display,
                    u.full_name, u.email, u.phone, u.password_hash, u.is_active
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.email = :email
             LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // 2. Validate user existence and password hash
        if (!$user || !password_verify($password, $user['password_hash'])) {
            record_login_attempt($email, $ip, false);
            log_action('LOGIN_FAILED', "Failed login attempt for {$email} (Incorrect credentials)");

            $remainingAttempts = MAX_LOGIN_ATTEMPTS - ($throttle['failed_count'] + 1);
            $warningMsg = $remainingAttempts > 0 
                ? "Invalid email or password. ({$remainingAttempts} attempt(s) remaining before temporary lockout)."
                : "Invalid email or password. Account access is now temporarily locked.";

            return [
                'success' => false,
                'message' => $warningMsg,
                'user'    => null
            ];
        }

        // 3. Verify user active status
        if ((int)$user['is_active'] !== 1) {
            record_login_attempt($email, $ip, false);
            log_action('LOGIN_DEACTIVATED_USER', "Deactivated user {$email} attempted to log in", (int)$user['id']);
            return [
                'success' => false,
                'message' => 'Your account has been deactivated by the administrator. Please contact UrbanEats support.',
                'user'    => null
            ];
        }

        // 4. Successful Authentication: Session Hardening & Regeneration
        record_login_attempt($email, $ip, true);

        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['role_display']  = $user['role_display'];
        $_SESSION['user_name']     = $user['full_name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['last_activity'] = time();

        // Regenerate CSRF token on privilege escalation
        generate_csrf_token();

        log_action('USER_LOGIN', "Successful login for {$user['full_name']} ({$user['role']})", (int)$user['id']);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user'    => $user
        ];
    } catch (Exception $e) {
        log_system_error('AUTH_EXCEPTION', $e->getMessage());
        return [
            'success' => false,
            'message' => 'An unexpected server error occurred during authentication. Please try again.',
            'user'    => null
        ];
    }
}

/**
 * Destroys the current user session completely and logs out
 */
function logout_user(): void {
    if (isset($_SESSION['user_id'])) {
        log_action('USER_LOGOUT', 'User logged out', (int)$_SESSION['user_id']);
    }

    $_SESSION = [];

    // Clear session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
