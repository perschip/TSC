<?php
// store/process_order.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set headers to handle AJAX requests
header('Content-Type: application/json');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate required data
if (!$data || !isset($data['orderID']) || !isset($data['cart'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

// Get PayPal settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM paypal_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log('Failed to load PayPal settings: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit;
}

// Check if we have client ID and secret
if (empty($settings['client_id']) || empty($settings['client_secret'])) {
    error_log('PayPal API credentials not configured');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'PayPal not configured']);
    exit;
}

// Extract order details
$paypal_order_id = $data['orderID'];
$cart_items = $data['cart'];
$payer_info = $data['payerInfo'] ?? [];
$shipping_info = $data['shippingInfo'] ?? [];
$amount_info = $data['amountInfo'] ?? [];

// Create a unique order reference
$order_reference = 'TSC-' . date('Ymd') . '-' . substr(uniqid(), -6);

// Calculate order totals
$subtotal = 0;
$shipping = isset($amount_info['shipping']) ? floatval($amount_info['shipping']) : 5.00; // Default to $5 if not provided
$tax = isset($amount_info['tax']) ? floatval($amount_info['tax']) : 0;

// Prepare customer info
$customer_name = isset($payer_info['name']) ? $payer_info['name'] : '';
$customer_email = isset($payer_info['email']) ? $payer_info['email'] : '';

// Prepare shipping address
$shipping_address = isset($shipping_info['address']) ? json_encode($shipping_info['address']) : '';

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Create order record
    $stmt = $pdo->prepare("INSERT INTO orders (
        order_reference, paypal_order_id, paypal_payer_id, 
        customer_name, customer_email, shipping_address,
        shipping_cost, tax_amount, total_amount, status, payment_status
    ) VALUES (
        :order_reference, :paypal_order_id, :paypal_payer_id,
        :customer_name, :customer_email, :shipping_address,
        :shipping_cost, :tax_amount, :total_amount, 'pending', 'completed'
    )");
    
    $total_amount = isset($amount_info['total']) ? floatval($amount_info['total']) : 0;
    
    $stmt->execute([
        ':order_reference' => $order_reference,
        ':paypal_order_id' => $paypal_order_id,
        ':paypal_payer_id' => $payer_info['payerID'] ?? '',
        ':customer_name' => $customer_name,
        ':customer_email' => $customer_email,
        ':shipping_address' => $shipping_address,
        ':shipping_cost' => $shipping,
        ':tax_amount' => $tax,
        ':total_amount' => $total_amount
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    // Process cart items
    foreach ($cart_items as $item) {
        $product_id = isset($item['id']) ? intval($item['id']) : 0;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $unit_price = isset($item['price']) ? floatval($item['price']) : 0;
        $total_price = $unit_price * $quantity;
        
        // Add to subtotal
        $subtotal += $total_price;
        
        // Get product details from database
        $product_title = $item['title'] ?? '';
        $product_sku = $item['sku'] ?? '';
        
        if ($product_id > 0) {
            $stmt = $pdo->prepare("SELECT title, sku FROM products WHERE id = :id");
            $stmt->execute([':id' => $product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $product_title = $product['title'];
                $product_sku = $product['sku'];
            }
            
            // Update inventory
            $stmt = $pdo->prepare("UPDATE products SET inventory = inventory - :quantity WHERE id = :id AND inventory >= :quantity");
            $stmt->execute([
                ':id' => $product_id,
                ':quantity' => $quantity
            ]);
        }
        
        // Insert order item
        $stmt = $pdo->prepare("INSERT INTO order_items (
            order_id, product_id, product_title, product_sku, quantity, unit_price, total_price
        ) VALUES (
            :order_id, :product_id, :product_title, :product_sku, :quantity, :unit_price, :total_price
        )");
        
        $stmt->execute([
            ':order_id' => $order_id,
            ':product_id' => $product_id,
            ':product_title' => $product_title,
            ':product_sku' => $product_sku,
            ':quantity' => $quantity,
            ':unit_price' => $unit_price,
            ':total_price' => $total_price
        ]);
    }
    
    // If total amount wasn't provided, calculate it
    if ($total_amount == 0) {
        $total_amount = $subtotal + $shipping + $tax;
        
        // Update the order with the calculated total
        $stmt = $pdo->prepare("UPDATE orders SET total_amount = :total_amount WHERE id = :id");
        $stmt->execute([
            ':id' => $order_id,
            ':total_amount' => $total_amount
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Order processed successfully', 
        'order_reference' => $order_reference,
        'redirect' => '/store/thank-you.php?order_id=' . urlencode($order_reference)
    ]);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $pdo->rollBack();
    
    error_log('Order processing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to process order: ' . $e->getMessage()]);
}
?>
