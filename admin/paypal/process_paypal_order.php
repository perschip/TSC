<?php
// admin/paypal/process_paypal_order.php
// This file processes PayPal orders and handles coupon application

require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'update_coupon_usage.php';
session_start();

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'order_id' => null
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get PayPal order data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['orderID'])) {
        $response['message'] = 'Invalid order data received';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get order details from session or request
        $customer_name = $data['payer']['name']['given_name'] . ' ' . $data['payer']['name']['surname'];
        $customer_email = $data['payer']['email_address'];
        $shipping_address = json_encode($data['purchase_units'][0]['shipping'] ?? null);
        $total_amount = $data['purchase_units'][0]['amount']['value'];
        
        // Generate unique order reference
        $order_reference = 'TSC-' . date('Ymd') . '-' . substr(uniqid(), -6);
        
        // Prepare coupon data if available
        $coupon_id = null;
        $coupon_code = null;
        $discount_amount = 0;
        $discount_type = null;
        
        if (isset($_SESSION['coupon'])) {
            $coupon_id = $_SESSION['coupon']['id'];
            $coupon_code = $_SESSION['coupon']['code'];
            $discount_amount = $_SESSION['coupon']['discount_amount'];
            $discount_type = $_SESSION['coupon']['discount_type'];
        }
        
        // Create order record
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_reference, 
                paypal_order_id, 
                paypal_payer_id, 
                customer_name, 
                customer_email, 
                shipping_address, 
                total_amount, 
                coupon_id,
                coupon_code,
                discount_amount,
                discount_type,
                status, 
                payment_status
            ) VALUES (
                :order_reference, 
                :paypal_order_id, 
                :paypal_payer_id, 
                :customer_name, 
                :customer_email, 
                :shipping_address, 
                :total_amount,
                :coupon_id,
                :coupon_code,
                :discount_amount,
                :discount_type,
                'processing', 
                'completed'
            )
        ");
        
        $stmt->execute([
            ':order_reference' => $order_reference,
            ':paypal_order_id' => $data['orderID'],
            ':paypal_payer_id' => $data['payerID'],
            ':customer_name' => $customer_name,
            ':customer_email' => $customer_email,
            ':shipping_address' => $shipping_address,
            ':total_amount' => $total_amount,
            ':coupon_id' => $coupon_id,
            ':coupon_code' => $coupon_code,
            ':discount_amount' => $discount_amount,
            ':discount_type' => $discount_type
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Process cart items
        if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (
                        order_id, 
                        product_id, 
                        product_title, 
                        product_sku, 
                        quantity, 
                        unit_price, 
                        total_price
                    ) VALUES (
                        :order_id, 
                        :product_id, 
                        :product_title, 
                        :product_sku, 
                        :quantity, 
                        :unit_price, 
                        :total_price
                    )
                ");
                
                $stmt->execute([
                    ':order_id' => $order_id,
                    ':product_id' => $item['id'],
                    ':product_title' => $item['title'],
                    ':product_sku' => $item['sku'] ?? '',
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['price'],
                    ':total_price' => $item['price'] * $item['quantity']
                ]);
            }
        }
        
        // Update coupon usage if a coupon was applied
        if (isset($_SESSION['coupon']) && !empty($_SESSION['coupon'])) {
            applyCouponToOrder($pdo, $order_id, $_SESSION['coupon']);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Clear cart and coupon from session
        unset($_SESSION['cart']);
        unset($_SESSION['coupon']);
        
        // Return success response
        $response['success'] = true;
        $response['message'] = 'Order processed successfully';
        $response['order_id'] = $order_id;
        $response['order_reference'] = $order_reference;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log('PayPal order processing error: ' . $e->getMessage());
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
