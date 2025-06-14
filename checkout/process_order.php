<?php
// checkout/process_order.php
// This file handles the order processing for the checkout page

require_once '../includes/db.php';
require_once '../includes/functions.php';
session_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'order_id' => null,
    'redirect' => ''
];

// Check if request is AJAX and POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from request body
    $json_data = file_get_contents('php://input');
    $order_data = json_decode($json_data, true);
    
    // Check if JSON was valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Invalid JSON data: ' . json_last_error_msg();
        echo json_encode($response);
        exit;
    }
    
    // Extract data from JSON
    $payment_method = isset($order_data['payment_method']) ? trim($order_data['payment_method']) : '';
    $cart_items = isset($order_data['items']) ? $order_data['items'] : [];
    $subtotal = isset($order_data['total_amount']) ? floatval($order_data['total_amount']) : 0;
    $discount = isset($order_data['discount_amount']) ? floatval($order_data['discount_amount']) : 0;
    $total = $subtotal - $discount;
    
    // Get shipping information from JSON data
    $customer_name = isset($order_data['customer_name']) ? trim($order_data['customer_name']) : '';
    $name_parts = explode(' ', $customer_name, 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
    
    $email = isset($order_data['customer_email']) ? trim($order_data['customer_email']) : '';
    $phone = isset($order_data['customer_phone']) ? trim($order_data['customer_phone']) : '';
    
    // Parse shipping address
    $shipping_address = isset($order_data['shipping_address']) ? trim($order_data['shipping_address']) : '';
    $address_parts = explode(',', $shipping_address);
    
    $address = isset($address_parts[0]) ? trim($address_parts[0]) : '';
    $city = ''; // Default values
    $state = isset($address_parts[1]) ? trim($address_parts[1]) : '';
    $zip = isset($address_parts[2]) ? trim($address_parts[2]) : '';
    
    // Validate cart data
    if (empty($cart_items) || $subtotal <= 0 || $total <= 0) {
        $response['message'] = 'Invalid cart data. Please try again.';
        echo json_encode($response);
        exit;
    }
    
    // Validate shipping information
    if (empty($first_name) || empty($last_name) || empty($email) || 
        empty($address) || empty($city) || empty($state) || empty($zip)) {
        $response['message'] = 'Please fill in all required shipping information.';
        echo json_encode($response);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Create order record
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                first_name, last_name, email, phone, address, city, state, zip,
                subtotal, discount, total, payment_method, order_date, status,
                coupon_code
            ) VALUES (
                :first_name, :last_name, :email, :phone, :address, :city, :state, :zip,
                :subtotal, :discount, :total, :payment_method, NOW(), 'pending',
                :coupon_code
            )
        ");
        
        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':zip' => $zip,
            ':subtotal' => $subtotal,
            ':discount' => $discount,
            ':total' => $total,
            ':payment_method' => $payment_method,
            ':coupon_code' => isset($_SESSION['coupon_code']) ? $_SESSION['coupon_code'] : null
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Insert order items
        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, price, quantity, subtotal
            ) VALUES (
                :order_id, :product_id, :product_name, :price, :quantity, :subtotal
            )
        ");
        
        foreach ($cart_items as $item) {
            // Adapt to the localStorage cart structure
            $product_id = isset($item['id']) ? $item['id'] : 0;
            $product_name = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : 'Unknown Product');
            $price = isset($item['price']) ? floatval($item['price']) : 0;
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
            $item_subtotal = $price * $quantity;
            
            $stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $product_id,
                ':product_name' => $product_name,
                ':price' => $price,
                ':quantity' => $quantity,
                ':subtotal' => $item_subtotal
            ]);
        }
        
        // If a coupon was used, update the coupon usage count
        if (isset($_SESSION['coupon_code']) && !empty($_SESSION['coupon_code'])) {
            $stmt = $pdo->prepare("
                UPDATE coupons 
                SET uses_count = uses_count + 1 
                WHERE code = :code
            ");
            $stmt->execute([':code' => $_SESSION['coupon_code']]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Clear session data
        unset($_SESSION['coupon_code']);
        unset($_SESSION['coupon_discount']);
        unset($_SESSION['coupon_percent']);
        
        // Success response
        $response['success'] = true;
        $response['message'] = 'Order placed successfully!';
        $response['order_id'] = $order_id;
        $response['redirect'] = '/checkout/thank-you.php?order_id=' . $order_id;
        
    } catch (PDOException $e) {
        // Roll back transaction on error
        $pdo->rollBack();
        
        $response['message'] = 'Database error. Please try again later.';
        // Log the error for admin
        error_log('Order processing error: ' . $e->getMessage());
    }
} else {
    $response['message'] = 'Invalid request.';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
