<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared Stubs & Integration Contract (Week 1 Contract)
 *
 * This file provides function contracts and fallback stubs agreed between
 * Component A, Component B, and Component C so that any student can test
 * their features before other components are completed.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Standard Order Status Constants (Agreed with Student 3 - Component C)
if (!defined('ORDER_STATUS_PLACED')) define('ORDER_STATUS_PLACED', 'PLACED');
if (!defined('ORDER_STATUS_PREPARING')) define('ORDER_STATUS_PREPARING', 'PREPARING');
if (!defined('ORDER_STATUS_READY')) define('ORDER_STATUS_READY', 'READY');
if (!defined('ORDER_STATUS_OUT_FOR_DELIVERY')) define('ORDER_STATUS_OUT_FOR_DELIVERY', 'OUT_FOR_DELIVERY');
if (!defined('ORDER_STATUS_COLLECTED')) define('ORDER_STATUS_COLLECTED', 'COLLECTED');
if (!defined('ORDER_STATUS_COMPLETED')) define('ORDER_STATUS_COMPLETED', 'COMPLETED');
if (!defined('ORDER_STATUS_CANCELLED')) define('ORDER_STATUS_CANCELLED', 'CANCELLED');

/**
 * CONTRACT: Component B (Menu & Inventory)
 * Function called by Component C during checkout to atomically reduce stock.
 *
 * @param int $dishId
 * @param int $quantity
 * @return bool True if stock was successfully reduced, False if insufficient stock
 */
if (!function_exists('reduce_stock')) {
    function reduce_stock(int $dishId, int $quantity): bool {
        try {
            $db = get_db();
            
            // Check current stock and ensure it never goes below zero
            $stmt = $db->prepare(
                "UPDATE inventory
                 SET current_stock = current_stock - :qty
                 WHERE dish_id = :dish_id AND current_stock >= :min_qty"
            );
            $stmt->execute([
                ':qty'     => $quantity,
                ':dish_id' => $dishId,
                ':min_qty' => $quantity
            ]);

            $success = ($stmt->rowCount() > 0);

            if ($success) {
                // Record stock movement
                $smStmt = $db->prepare(
                    "INSERT INTO stock_movements (dish_id, user_id, movement_type, quantity, reason, created_at)
                     VALUES (:dish_id, :user_id, 'ORDER_DEDUCT', :qty, 'Stock deducted by checkout order', NOW())"
                );
                $smStmt->execute([
                    ':dish_id' => $dishId,
                    ':user_id' => $_SESSION['user_id'] ?? null,
                    ':qty'     => $quantity
                ]);

                log_action('STOCK_DEDUCTED', "Reduced stock for dish ID {$dishId} by {$quantity}");
            }

            return $success;
        } catch (Exception $e) {
            log_system_error('REDUCE_STOCK_FAILURE', $e->getMessage());
            return false;
        }
    }
}

/**
 * CONTRACT: Component B (Menu & Inventory)
 * Helper to retrieve dish details
 */
if (!function_exists('get_dish_by_id')) {
    function get_dish_by_id(int $dishId): ?array {
        try {
            $db = get_db();
            $stmt = $db->prepare(
                "SELECT d.*, c.name AS category_name, i.current_stock
                 FROM dishes d
                 JOIN categories c ON d.category_id = c.id
                 LEFT JOIN inventory i ON d.id = i.dish_id
                 WHERE d.id = :id AND d.is_active = 1
                 LIMIT 1"
            );
            $stmt->execute([':id' => $dishId]);
            $dish = $stmt->fetch();
            return $dish ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}
