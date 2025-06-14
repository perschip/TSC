<?php
// Ultra-simple cart handler with minimal dependencies

// Start session
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Basic validation
if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Get product ID and quantity
$product_id = (int)$_POST['product_id'];
$quantity = isset($_POST['quantity']) && is_numeric($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Ensure positive quantity
if ($quantity < 1) {
    $quantity = 1;
}

// Connect to database
try {
    // Manual database connection with correct credentials
    $db_host = 'localhost';
    $db_name = 'tristatecards_2';
    $db_user = 'tscadmin_2';
    $db_pass = '$Yankees100';
    $db_charset = 'utf8mb4';
    
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // Enable error logging
    error_log("Cart: Attempting to add product ID: {$product_id}");
    
    // Get product details with minimal fields and ensure status is active
    $stmt = $pdo->prepare("SELECT id, title, price FROM products WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    error_log("Cart: Product query result: " . ($product ? json_encode($product) : 'not found'));
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Initialize cart if needed
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add to cart (simple version)
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $product_id) {
            $item['quantity'] += $quantity;
            $found = true;
            break;
        }
    }
    unset($item); // Unset reference
    
    if (!$found) {
        $_SESSION['cart'][] = [
            'id' => $product_id,
            'title' => $product['title'],
            'price' => (float)$product['price'],
            'quantity' => $quantity
        ];
    }
    
    // Count items
    $cart_count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart',
        'cart_count' => $cart_count,
        'product_name' => $product['title']
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Cart error: ' . $e->getMessage());
    
    // Return generic error
    echo json_encode([
        'success' => false, 
        'message' => 'Could not add item to cart. Database error.'
    ]);
}
