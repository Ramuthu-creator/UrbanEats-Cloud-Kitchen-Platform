<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared Helper Functions & Audit Logging
 * 
 * Provides HTML escaping, URL generation, flash messages,
 * and centralized audit logging for all three components.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escapes HTML output safely to prevent Cross-Site Scripting (XSS)
 *
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Returns the dynamic project root URL, adapting automatically
 * whether running via PHP built-in server or subfolder in XAMPP/WAMP.
 *
 * @param string $path Optional relative path from project root
 * @return string
 */
function base_url(string $path = ''): string {
    // Detect protocol
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    $protocol = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Calculate project root path relative to document root
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $projectRoot = dirname(dirname($scriptName));
    
    // Normalize root path
    if ($projectRoot === '.' || $projectRoot === '/' || $projectRoot === '\\') {
        $base = '';
    } else {
        $base = rtrim($projectRoot, '/');
    }

    $cleanPath = ltrim($path, '/');
    return $protocol . $host . ($base ? $base . '/' : '/') . $cleanPath;
}

/**
 * Returns URL to a static asset (CSS, images) inside public/
 */
function asset(string $path): string {
    return base_url('public/' . ltrim($path, '/'));
}

/**
 * Redirects user to specified URL and terminates execution
 */
function redirect(string $url): void {
    // If not an absolute http URL, resolve with base_url
    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
        $url = base_url($url);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Sets a flash message to display on the next rendered page
 *
 * @param string $type 'success', 'error', 'warning', 'info'
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * Checks if flash messages exist
 */
function has_flash(?string $type = null): bool {
    if ($type === null) {
        return !empty($_SESSION['flash_messages']);
    }
    return !empty($_SESSION['flash_messages'][$type]);
}

/**
 * Retrieves and clears flash messages
 *
 * @param string|null $type Specific category or null for all
 * @return array
 */
function get_flash(?string $type = null): array {
    if (!isset($_SESSION['flash_messages'])) {
        return [];
    }

    if ($type !== null) {
        $messages = $_SESSION['flash_messages'][$type] ?? [];
        unset($_SESSION['flash_messages'][$type]);
        return $messages;
    }

    $messages = $_SESSION['flash_messages'];
    $_SESSION['flash_messages'] = [];
    return $messages;
}

/**
 * Renders HTML markup for all pending flash messages
 */
function render_flash_messages(): string {
    $allMessages = get_flash();
    if (empty($allMessages)) {
        return '';
    }

    $html = '<div class="flash-messages-container" aria-live="polite">';
    foreach ($allMessages as $type => $messages) {
        $alertClass = match ($type) {
            'success' => 'alert-success',
            'error'   => 'alert-error',
            'warning' => 'alert-warning',
            default   => 'alert-info'
        };

        foreach ($messages as $msg) {
            $html .= sprintf(
                '<div class="alert %s" role="alert">' .
                '  <span class="alert-icon">%s</span>' .
                '  <span class="alert-text">%s</span>' .
                '  <button type="button" class="alert-close" onclick="this.parentElement.remove();" aria-label="Close message">&times;</button>' .
                '</div>',
                $alertClass,
                $type === 'success' ? '✓' : ($type === 'error' ? '✕' : 'ℹ'),
                e($msg)
            );
        }
    }
    $html .= '</div>';
    return $html;
}

/**
 * Returns sanitized client IP address
 */
function get_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Central Audit Logging Function
 * Component A, B, and C call this function to record high-value events.
 *
 * @param string $action Standard action key (e.g., USER_LOGIN, ORDER_PLACED, STOCK_ADJUSTED)
 * @param string|array|null $details Additional context or serialized metadata
 * @param int|null $userId Optional specific user ID; defaults to current logged-in user
 * @return bool
 */
function log_action(string $action, $details = null, ?int $userId = null): bool {
    try {
        $db = get_db();

        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        if (is_array($details)) {
            $details = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $ipAddress = get_client_ip();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

        $stmt = $db->prepare(
            "INSERT INTO audit_log (user_id, action, details, ip_address, user_agent, created_at)
             VALUES (:user_id, :action, :details, :ip_address, :user_agent, :created_at)"
        );

        return $stmt->execute([
            ':user_id'    => $userId,
            ':action'     => $action,
            ':details'    => $details,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        log_system_error('AUDIT_LOG_FAILURE', 'Failed to write audit log: ' . $e->getMessage());
        return false;
    }
}

/**
 * Formats a numeric price to Sri Lankan Rupees
 */
function format_price(float $amount): string {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Formats a timestamp into human-readable format
 */
function format_datetime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime);
    if (!$time) return 'N/A';
    return date('d M Y, h:i A', $time);
}
