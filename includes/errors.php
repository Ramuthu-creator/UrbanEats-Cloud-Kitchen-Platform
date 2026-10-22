<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Centralized Error & Exception Handling
 * 
 * Requirement:
 * Custom error/exception handler, friendly error pages, errors written to a log file,
 * and no raw PHP or SQL errors shown to the user.
 */

if (!defined('ERROR_HANDLER_INIT')) {
    define('ERROR_HANDLER_INIT', true);

    // Ensure error reporting is captured
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // Hide raw errors from browser output
    ini_set('log_errors', '1');

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/error.log');

    /**
     * Logs custom system error to logs/error.log
     */
    function log_system_error(string $type, string $message, string $file = '', int $line = 0): void {
        $logFile = dirname(__DIR__) . '/logs/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $url = $_SERVER['REQUEST_URI'] ?? 'N/A';
        
        $entry = sprintf(
            "[%s] [%s] %s | File: %s:%d | IP: %s | URL: %s\n",
            $timestamp,
            $type,
            $message,
            $file ?: 'N/A',
            $line,
            $ip,
            $url
        );

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Custom PHP Error Handler
     */
    function custom_error_handler($severity, $message, $file, $line): bool {
        if (!(error_reporting() & $severity)) {
            // Error not included in current error_reporting setting
            return false;
        }

        $type = match ($severity) {
            E_ERROR, E_USER_ERROR => 'FATAL_ERROR',
            E_WARNING, E_USER_WARNING => 'WARNING',
            E_NOTICE, E_USER_NOTICE => 'NOTICE',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            default => 'UNKNOWN_ERROR'
        };

        log_system_error($type, $message, $file, $line);

        // If it's a fatal error, render friendly error page
        if ($severity === E_ERROR || $severity === E_USER_ERROR) {
            display_friendly_error_page('A temporary application error occurred. Our technical staff has been notified.');
            exit;
        }

        return true;
    }

    /**
     * Custom PHP Exception Handler
     */
    function custom_exception_handler(Throwable $exception): void {
        $message = sprintf(
            "%s: %s in %s:%d\nStack Trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        log_system_error('UNCAUGHT_EXCEPTION', $message, $exception->getFile(), $exception->getLine());
        display_friendly_error_page('A unexpected system issue occurred. Please try again shortly.');
        exit;
    }

    /**
     * Displays a friendly error page without exposing raw technical or SQL details
     */
    function display_friendly_error_page(string $userFriendlyMessage): void {
        // Clear any previous output if possible
        if (ob_get_level() > 0) {
            ob_clean();
        }

        http_response_code(500);

        // Check if public/error.php exists
        $errorPagePath = dirname(__DIR__) . '/public/error.php';
        if (file_exists($errorPagePath)) {
            $errorTitle = '500 - Server Error';
            $errorMessage = $userFriendlyMessage;
            include $errorPagePath;
        } else {
            // Fallback inline HTML
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>500 - Server Error | UrbanEats</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0F172A; color: #F8FAFC; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
                    .card { background: #1E293B; padding: 2.5rem; border-radius: 1rem; border: 1px solid #334155; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
                    h1 { color: #EF4444; margin-top: 0; }
                    a { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.5rem; background: #F59E0B; color: #0F172A; text-decoration: none; font-weight: bold; border-radius: 0.5rem; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h1>Something went wrong</h1>
                    <p>' . htmlspecialchars($userFriendlyMessage, ENT_QUOTES, 'UTF-8') . '</p>
                    <p style="color: #94A3B8; font-size: 0.85rem;">The issue has been logged to the server logs.</p>
                    <a href="javascript:history.back()">Go Back</a>
                </div>
            </body>
            </html>';
        }
    }

    set_error_handler('custom_error_handler');
    set_exception_handler('custom_exception_handler');
}
