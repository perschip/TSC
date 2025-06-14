<?php
// checkout/apply_coupon.php
// This file handles the AJAX request to apply a coupon code during checkout

require_once '../includes/db.php';
require_once '../includes/functions.php';
session_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'discount' => 0,
    'discount_type' => '',
    'new_total' => 0
];

// Check if request is AJAX and POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coupon_code'])) {
    $coupon_code = trim($_POST['coupon_code']);
    $cart_total = isset($_POST['cart_total']) ? floatval($_POST['cart_total']) : 0;
    $cart_items = isset($_POST['cart_items']) ? json_decode($_POST['cart_items'], true) : [];
    
    // Validate cart total and items
    if ($cart_total <= 0 || empty($cart_items)) {
        $response['message'] = 'Invalid cart or empty cart.';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Check if coupon exists and is valid
        $stmt = $pdo->prepare("
            SELECT * FROM coupons 
            WHERE code = :code 
            AND active = 1
            AND (start_date IS NULL OR start_date <= NOW())
            AND (end_date IS NULL OR end_date >= NOW())
        ");
        $stmt->execute([':code' => $coupon_code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coupon) {
            $response['message'] = 'Invalid or expired coupon code.';
            echo json_encode($response);
            exit;
        }
        
        // Check if coupon has reached maximum uses
        if ($coupon['max_uses'] !== null && $coupon['uses_count'] >= $coupon['max_uses']) {
            $response['message'] = 'This coupon has reached its maximum number of uses.';
            echo json_encode($response);
            exit;
        }
        
        // Check minimum purchase requirement
        if ($coupon['min_purchase'] > 0 && $cart_total < $coupon['min_purchase']) {
            $response['message'] = 'This coupon requires a minimum purchase of $' . number_format($coupon['min_purchase'], 2) . '.';
            echo json_encode($response);
            exit;
        }
        
        // Calculate discount
        $discount_amount = 0;
        if ($coupon['discount_type'] === 'percentage') {
            $discount_amount = $cart_total * ($coupon['discount_value'] / 100);
        } else {
            $discount_amount = min($coupon['discount_value'], $cart_total); // Don't allow discount greater than cart total
        }
        
        // Calculate new total
        $new_total = $cart_total - $discount_amount;
        
        // Store coupon in session for use during order processing
        $_SESSION['coupon_code'] = $coupon['code'];
        $_SESSION['coupon_discount'] = $discount_amount;
        $_SESSION['coupon_percent'] = ($coupon['discount_type'] === 'percentage') ? $coupon['discount_value'] : 0;
        
        // Success response
        $response['success'] = true;
        $response['message'] = 'Coupon applied successfully!';
        $response['discount'] = $discount_amount;
        $response['discount_type'] = $coupon['discount_type'];
        $response['new_total'] = $new_total;
        
        // Format for display
        $discount_display = $coupon['discount_type'] === 'percentage' 
            ? $coupon['discount_value'] . '%' 
            : '$' . number_format($coupon['discount_value'], 2);
            
        $response['discount_display'] = $discount_display;
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error. Please try again later.';
        // Log the error for admin
        error_log('Coupon application error: ' . $e->getMessage());
    }
} else {
    $response['message'] = 'Invalid request.';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
