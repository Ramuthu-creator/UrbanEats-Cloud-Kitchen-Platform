<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Cross-Site Request Forgery (CSRF) Protection
 *
 * Provides cryptographic token generation, hidden input rendering,
 * and request verification for all state-changing POST requests.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generates or retrieves existing CSRF token for current session
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden input field containing the current CSRF token
 */
function csrf_input(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifies the submitted CSRF token against the session token
 *
 * @param string|null $token Submitted token, or null to auto-inspect $_POST['csrf_token']
 * @return bool
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    // Timing-attack safe comparison
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validates CSRF token on POST requests; halts execution with a flash error if invalid
 */
function validate_csrf_or_abort(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            if (function_exists('set_flash')) {
                set_flash('error', 'Security check failed (invalid or expired CSRF token). Please try submitting the form again.');
            }
            if (function_exists('log_action')) {
                log_action('CSRF_VERIFICATION_FAILED', 'Invalid CSRF token received in POST request');
            }
            $referer = $_SERVER['HTTP_REFERER'] ?? (function_exists('base_url') ? base_url() : 'index.php');
            header('Location: ' . $referer);
            exit;
        }
    }
}
