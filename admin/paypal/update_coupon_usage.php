<?php
// admin/paypal/update_coupon_usage.php
// This file contains functions to update coupon usage when an order is processed

/**
 * Apply a coupon to an order and update its usage count
 * 
 * @param PDO $pdo Database connection
 * @param int $order_id The order ID
 * @param array $coupon_data Coupon data from session
 * @return bool Success status
 */
function applyCouponToOrder($pdo, $order_id, $coupon_data) {
    if (empty($order_id) || empty($coupon_data) || empty($coupon_data['id'])) {
        return false;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Add coupon to order
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET 
                coupon_id = :coupon_id,
                coupon_code = :coupon_code,
                discount_amount = :discount_amount,
                discount_type = :discount_type
            WHERE id = :order_id
        ");
        
        $stmt->execute([
            ':coupon_id' => $coupon_data['id'],
            ':coupon_code' => $coupon_data['code'],
            ':discount_amount' => $coupon_data['discount_amount'],
            ':discount_type' => $coupon_data['discount_type'],
            ':order_id' => $order_id
        ]);
        
        // Increment coupon usage count
        $stmt = $pdo->prepare("
            UPDATE coupons
            SET uses_count = uses_count + 1
            WHERE id = :coupon_id
        ");
        
        $stmt->execute([':coupon_id' => $coupon_data['id']]);
        
        // Commit transaction
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log error
        error_log('Error applying coupon to order: ' . $e->getMessage());
        return false;
    }
}

/**
 * Revert coupon usage when an order is canceled or refunded
 * 
 * @param PDO $pdo Database connection
 * @param int $order_id The order ID
 * @return bool Success status
 */
function revertCouponUsage($pdo, $order_id) {
    if (empty($order_id)) {
        return false;
    }
    
    try {
        // Get coupon ID from order
        $stmt = $pdo->prepare("SELECT coupon_id FROM orders WHERE id = :order_id AND coupon_id IS NOT NULL");
        $stmt->execute([':order_id' => $order_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || empty($result['coupon_id'])) {
            // No coupon was used for this order
            return true;
        }
        
        $coupon_id = $result['coupon_id'];
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Decrement coupon usage count
        $stmt = $pdo->prepare("
            UPDATE coupons
            SET uses_count = GREATEST(uses_count - 1, 0)
            WHERE id = :coupon_id
        ");
        
        $stmt->execute([':coupon_id' => $coupon_id]);
        
        // Commit transaction
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log error
        error_log('Error reverting coupon usage: ' . $e->getMessage());
        return false;
    }
}
?>
