<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared Database Connection & Base Configuration File
 *
 * Implements PDO connection with prepared statements, strict error handling,
 * and seamless fallback options.
 */

// Load local configuration overrides if present
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Default Configuration Constants (Sensible XAMPP / Local defaults)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'urbaneats_db');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

if (!defined('APP_NAME')) define('APP_NAME', 'UrbanEats Cloud Kitchen');
if (!defined('APP_ENV')) define('APP_ENV', 'development');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 1800); // 30 minutes
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOCKOUT_TIME_MINUTES')) define('LOCKOUT_TIME_MINUTES', 15);
if (!defined('RESET_TOKEN_EXPIRY_MINUTES')) define('RESET_TOKEN_EXPIRY_MINUTES', 60);
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'Rs. ');
if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', 'LKR');

/**
 * Returns the active PDO database connection instance (Singleton).
 *
 * @return PDO
 * @throws PDOException
 */
function get_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $mysqlInitAttr = defined('Pdo\Mysql::ATTR_INIT_COMMAND') 
        ? \Pdo\Mysql::ATTR_INIT_COMMAND 
        : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? @constant('PDO::MYSQL_ATTR_INIT_COMMAND') : 1002);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        $mysqlInitAttr               => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Fallback for standalone demo/testing environments where MySQL server isn't running yet
        $sqlitePath = dirname(__DIR__) . '/database/urbaneats_dev.sqlite';
        if (getenv('DB_DRIVER') === 'sqlite' || !defined('DB_STRICT_MYSQL')) {
            try {
                $sqlitePdo = new PDO('sqlite:' . $sqlitePath, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                ]);
                $sqlitePdo->exec("PRAGMA foreign_keys = ON;");
                $pdo = $sqlitePdo;
                return $pdo;
            } catch (Exception $sqliteEx) {
                // Ignore and throw original MySQL error
            }
        }

        // If error logging function is available, record connection failure
        if (function_exists('log_system_error')) {
            log_system_error('Database Connection Error', $e->getMessage());
        }

        throw $e;
    }
}

// Component C Checkout 
function reduce_stock($pdo, $dish_id, $qty) {
    try {
        $pdo->beginTransaction();
        
        // view stock
        $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE dish_id = ? FOR UPDATE");
        $stmt->execute([$dish_id]);
        $current_qty = $stmt->fetchColumn();

        if ($current_qty !== false && $current_qty >= $qty) {
            
            $update_stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE dish_id = ?");
            $update_stmt->execute([$qty, $dish_id]);
            
        
            $log_stmt = $pdo->prepare("INSERT INTO stock_movements (dish_id, user_id, quantity_change, reason) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([$dish_id, 0, -$qty, 'System - Sold via Checkout']);
            
            $pdo->commit();
            return true; 
        } else {
            
            $pdo->rollBack();
            return false; 
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
