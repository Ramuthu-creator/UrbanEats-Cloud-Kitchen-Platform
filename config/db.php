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

// Component C Checkout / Component B Inventory Bridge
if (!function_exists('reduce_stock')) {
    function reduce_stock($arg1, $arg2 = null, $arg3 = null): bool {
        // Support both reduce_stock($dishId, $qty) and reduce_stock($pdo, $dishId, $qty)
        if ($arg3 !== null) {
            $pdo = $arg1;
            $dishId = (int)$arg2;
            $qty = (int)$arg3;
        } else {
            $pdo = get_db();
            $dishId = (int)$arg1;
            $qty = (int)$arg2;
        }

        if ($dishId <= 0 || $qty <= 0) {
            return false;
        }

        $manageTransaction = !$pdo->inTransaction();

        try {
            if ($manageTransaction) {
                $pdo->beginTransaction();
            }

            // Check current stock with row locking (FOR UPDATE)
            // Handle both column naming conventions (current_stock vs quantity)
            $colName = 'current_stock';
            try {
                $checkStmt = $pdo->prepare("SELECT current_stock FROM inventory WHERE dish_id = ? FOR UPDATE");
                $checkStmt->execute([$dishId]);
                $currentStock = $checkStmt->fetchColumn();
            } catch (Exception $e) {
                $colName = 'quantity';
                $checkStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE dish_id = ? FOR UPDATE");
                $checkStmt->execute([$dishId]);
                $currentStock = $checkStmt->fetchColumn();
            }

            if ($currentStock === false || (int)$currentStock < $qty) {
                if ($manageTransaction) {
                    $pdo->rollBack();
                }
                return false;
            }

            // Update inventory deducting the quantity
            $updateSql = "UPDATE inventory SET {$colName} = {$colName} - ? WHERE dish_id = ? AND {$colName} >= ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$qty, $dishId, $qty]);

            if ($updateStmt->rowCount() <= 0) {
                if ($manageTransaction) {
                    $pdo->rollBack();
                }
                return false;
            }

            // Record in stock_movements
            $userId = $_SESSION['user_id'] ?? null;
            try {
                $logStmt = $pdo->prepare(
                    "INSERT INTO stock_movements (dish_id, user_id, movement_type, quantity, reason, created_at) 
                     VALUES (?, ?, 'ORDER_DEDUCT', ?, 'Order checkout deduction', NOW())"
                );
                $logStmt->execute([$dishId, $userId, $qty]);
            } catch (Exception $logEx) {
                // Fallback for alternate schema if present
                try {
                    $logStmtAlt = $pdo->prepare(
                        "INSERT INTO stock_movements (dish_id, user_id, quantity_change, reason) 
                         VALUES (?, ?, ?, 'Order checkout deduction')"
                    );
                    $logStmtAlt->execute([$dishId, $userId ?: 0, -$qty]);
                } catch (Exception $e2) {
                    // Non-fatal for stock movement log
                }
            }

            if ($manageTransaction) {
                $pdo->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($manageTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (function_exists('log_system_error')) {
                log_system_error('REDUCE_STOCK_FAILURE', $e->getMessage());
            }
            return false;
        }
    }
}

