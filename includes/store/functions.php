<?php
/**
 * Store Functions
 * Contains all core functionality for the store and checkout system
 */

/**
 * Utility Functions
 */

// Note: format_price() is defined in config.php

/**
 * Get available shipping methods
 * @return array Array of shipping methods with their details
 */
function get_shipping_methods() {
    global $shipping_methods;
    return $shipping_methods;
}

/**
 * Payment Gateway Functions
 */

/**
 * Get PayPal settings from the database
 * @return array PayPal settings including client_id, secret, and currency
 */
function get_paypal_settings() {
    global $pdo;
    
    $settings = [
        'client_id' => '',
        'secret' => '',
        'currency' => 'USD',
        'environment' => 'sandbox' // sandbox or production
    ];
    
    try {
        // Check if settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->rowCount() > 0) {
            // Get PayPal settings
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'paypal_%'");
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = str_replace('paypal_', '', $row['setting_key']);
                $settings[$key] = $row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        error_log('Error fetching PayPal settings: ' . $e->getMessage());
    }
    
    return $settings;
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cart Functions
 */

// Initialize the cart in the session if it doesn't exist
function initialize_cart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [
            'items' => [],
            'item_count' => 0,
            'totals' => [
                'subtotal' => 0,
                'discount' => 0,
                'shipping' => 0,
                'tax' => 0,
                'total' => 0
            ],
            'shipping_method' => '',
            'coupon' => null,
            'coupon_code' => '',
            'coupon_type' => '',
            'coupon_value' => 0,
            'shipping_method' => 'standard',
            'last_updated' => time()
        ];
    }
    
    return $_SESSION['cart'];
}

// Get the current cart
function get_cart() {
    return initialize_cart();
}

// Add an item to the cart
function add_to_cart($product_id, $quantity = 1, $options = []) {
    global $pdo;
    
    // Initialize cart if needed
    $cart = initialize_cart();
    
    // Validate product exists
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'message' => 'Product not found or unavailable'];
        }
        
        // Check if product is already in cart
        $item_key = null;
        foreach ($cart['items'] as $key => $item) {
            if ($item['id'] == $product_id) {
                // Check if options match
                $options_match = true;
                if (!empty($options) && !empty($item['options'])) {
                    foreach ($options as $option_key => $option_value) {
                        if (!isset($item['options'][$option_key]) || $item['options'][$option_key] != $option_value) {
                            $options_match = false;
                            break;
                        }
                    }
                } elseif (!empty($options) || !empty($item['options'])) {
                    $options_match = false;
                }
                
                if ($options_match) {
                    $item_key = $key;
                    break;
                }
            }
        }
        
        // Update quantity if item exists, otherwise add new item
        if ($item_key !== null) {
            $cart['items'][$item_key]['quantity'] += $quantity;
        } else {
            $cart['items'][] = [
                'id' => $product['id'],
                'title' => $product['title'],
                'price' => $product['price'],
                'image' => $product['image_url'] ?? '',
                'quantity' => $quantity,
                'options' => $options
            ];
        }
        
        // Update cart totals
        update_cart_totals();
        
        return ['success' => true, 'message' => 'Product added to cart'];
    } catch (PDOException $e) {
        error_log('Error adding to cart: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error adding product to cart'];
    }
}

// Update cart item quantity
function update_cart_item($item_index, $quantity) {
    // Initialize cart if needed
    $cart = initialize_cart();
    
    // Validate item exists in cart
    if (!isset($cart['items'][$item_index])) {
        return ['success' => false, 'message' => 'Item not found in cart'];
    }
    
    // Update quantity or remove if quantity is 0
    if ($quantity <= 0) {
        return remove_cart_item($item_index);
    } else {
        $cart['items'][$item_index]['quantity'] = $quantity;
        $_SESSION['cart'] = $cart;
        
        // Update cart totals
        update_cart_totals();
        
        return ['success' => true, 'message' => 'Cart updated'];
    }
}

// Remove an item from the cart
function remove_cart_item($item_index) {
    // Initialize cart if needed
    $cart = initialize_cart();
    
    // Validate item exists in cart
    if (!isset($cart['items'][$item_index])) {
        return ['success' => false, 'message' => 'Item not found in cart'];
    }
    
    // Remove item
    array_splice($cart['items'], $item_index, 1);
    $_SESSION['cart'] = $cart;
    
    // Update cart totals
    update_cart_totals();
    
    return ['success' => true, 'message' => 'Item removed from cart'];
}

// Clear the entire cart
function clear_cart() {
    $_SESSION['cart'] = [
        'items' => [],
        'subtotal' => 0,
        'discount' => 0,
        'shipping' => 0,
        'tax' => 0,
        'total' => 0,
        'coupon_code' => '',
        'coupon_type' => '',
        'coupon_value' => 0,
        'shipping_method' => 'standard',
        'last_updated' => time()
    ];
    
    return ['success' => true, 'message' => 'Cart cleared'];
}

// Update cart totals
function update_cart_totals() {
    global $store_config;
    
    // Initialize cart if needed
    $cart = initialize_cart();
    
    // Calculate subtotal
    $subtotal = 0;
    foreach ($cart['items'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // Apply coupon discount if available
    $discount = 0;
    if (!empty($cart['coupon_code'])) {
        if ($cart['coupon_type'] === 'percentage') {
            $discount = $subtotal * ($cart['coupon_value'] / 100);
        } else {
            $discount = min($cart['coupon_value'], $subtotal); // Don't allow discount greater than subtotal
        }
    }
    
    // Calculate shipping
    $shipping = calculate_shipping($subtotal, $cart['shipping_method']);
    
    // Calculate tax on post-discount amount
    $taxable_amount = $subtotal - $discount;
    $tax = calculate_tax($taxable_amount);
    
    // Calculate total
    $total = $subtotal - $discount + $shipping + $tax;
    
    // Update cart values
    $cart['subtotal'] = $subtotal;
    $cart['discount'] = $discount;
    $cart['shipping'] = $shipping;
    $cart['tax'] = $tax;
    $cart['total'] = $total;
    $cart['last_updated'] = time();
    
    // Save updated cart to session
    $_SESSION['cart'] = $cart;
    
    return $cart;
}

// Apply a coupon code to the cart
function apply_coupon($code) {
    global $pdo;
    
    if (empty($code)) {
        return ['success' => false, 'message' => 'Please enter a coupon code'];
    }
    
    // Initialize cart if needed
    $cart = initialize_cart();
    
    try {
        // Check if coupons table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'coupons'");
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Invalid coupon code'];
        }
        
        // Get coupon details
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coupon) {
            return ['success' => false, 'message' => 'Invalid or expired coupon code'];
        }
        
        // Check minimum order value if set
        if (!empty($coupon['min_order_value']) && $cart['subtotal'] < $coupon['min_order_value']) {
            return [
                'success' => false, 
                'message' => 'This coupon requires a minimum order of ' . format_price($coupon['min_order_value'])
            ];
        }
        
        // Apply coupon to cart
        $cart['coupon_code'] = $code;
        $cart['coupon_type'] = $coupon['discount_type'];
        $cart['coupon_value'] = $coupon['discount_value'];
        $_SESSION['cart'] = $cart;
        
        // Update cart totals
        update_cart_totals();
        
        // Format message based on discount type
        $message = 'Coupon applied: ';
        if ($coupon['discount_type'] === 'percentage') {
            $message .= $coupon['discount_value'] . '% discount';
        } else {
            $message .= format_price($coupon['discount_value']) . ' discount';
        }
        
        return ['success' => true, 'message' => $message];
    } catch (PDOException $e) {
        error_log('Error applying coupon: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error processing coupon'];
    }
}

// Remove coupon from cart
function remove_coupon() {
    // Initialize cart if needed
    $cart = initialize_cart();
    
    // Remove coupon data
    $cart['coupon_code'] = '';
    $cart['coupon_type'] = '';
    $cart['coupon_value'] = 0;
    $_SESSION['cart'] = $cart;
    
    // Update cart totals
    update_cart_totals();
    
    return ['success' => true, 'message' => 'Coupon removed'];
}

// Set shipping method
function set_shipping_method($method) {
    global $shipping_methods;
    
    // Initialize cart if needed
    $cart = initialize_cart();
    
    // Validate shipping method
    if (!isset($shipping_methods[$method])) {
        return ['success' => false, 'message' => 'Invalid shipping method'];
    }
    
    // Update shipping method
    $cart['shipping_method'] = $method;
    $_SESSION['cart'] = $cart;
    
    // Update cart totals
    update_cart_totals();
    
    return ['success' => true, 'message' => 'Shipping method updated'];
}

/**
 * Product Functions
 */

// Get product by ID
function get_product($product_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return null;
        }
        
        // Get product categories
        $stmt = $pdo->prepare("SELECT c.* FROM product_categories pc 
                              JOIN categories c ON pc.category_id = c.id 
                              WHERE pc.product_id = ?");
        $stmt->execute([$product_id]);
        $product['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get product images
        $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
        $stmt->execute([$product_id]);
        $product['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no images found, ensure at least the main image is set
        if (empty($product['images']) && !empty($product['image_url'])) {
            $product['images'] = [[
                'id' => 0,
                'product_id' => $product_id,
                'image_url' => $product['image_url'],
                'sort_order' => 0
            ]];
        }
        
        return $product;
    } catch (PDOException $e) {
        error_log('Error getting product: ' . $e->getMessage());
        return null;
    }
}

// Get featured products
function get_featured_products($limit = 8) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE status = 'active' AND featured = 1 ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting featured products: ' . $e->getMessage());
        return [];
    }
}

// Get latest products
function get_latest_products($limit = 8) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE status = 'active' ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting latest products: ' . $e->getMessage());
        return [];
    }
}

// Get related products
function get_related_products($product_id, $limit = 4) {
    global $pdo;
    
    try {
        // Get products in the same categories
        $stmt = $pdo->prepare("SELECT p.* FROM products p 
                              JOIN product_categories pc1 ON p.id = pc1.product_id 
                              JOIN product_categories pc2 ON pc1.category_id = pc2.category_id 
                              WHERE pc2.product_id = ? AND p.id != ? AND p.status = 'active' 
                              GROUP BY p.id 
                              ORDER BY RAND() 
                              LIMIT ?");
        $stmt->execute([$product_id, $product_id, $limit]);
        $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If not enough related products, fill with featured products
        if (count($related) < $limit) {
            $needed = $limit - count($related);
            $existing_ids = array_column($related, 'id');
            $existing_ids[] = $product_id;
            
            $placeholders = implode(',', array_fill(0, count($existing_ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM products 
                                  WHERE id NOT IN ($placeholders) AND status = 'active' 
                                  ORDER BY featured DESC, RAND() 
                                  LIMIT ?");
            
            $params = $existing_ids;
            $params[] = $needed;
            $stmt->execute($params);
            
            $additional = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $related = array_merge($related, $additional);
        }
        
        return $related;
    } catch (PDOException $e) {
        error_log('Error getting related products: ' . $e->getMessage());
        return [];
    }
}

// Get products by category
function get_products_by_category($category_id, $page = 1, $per_page = 12) {
    global $pdo;
    
    try {
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Get products
        $stmt = $pdo->prepare("SELECT p.* FROM products p 
                              JOIN product_categories pc ON p.id = pc.product_id 
                              WHERE pc.category_id = ? AND p.status = 'active' 
                              ORDER BY p.featured DESC, p.id DESC 
                              LIMIT ? OFFSET ?");
        $stmt->execute([$category_id, $per_page, $offset]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products p 
                              JOIN product_categories pc ON p.id = pc.product_id 
                              WHERE pc.category_id = ? AND p.status = 'active'");
        $stmt->execute([$category_id]);
        $total = $stmt->fetchColumn();
        
        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    } catch (PDOException $e) {
        error_log('Error getting products by category: ' . $e->getMessage());
        return [
            'products' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => 0
        ];
    }
}

// Search products
function search_products($query, $page = 1, $per_page = 12) {
    global $pdo;
    
    try {
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Prepare search terms
        $search_term = "%{$query}%";
        
        // Get products
        $stmt = $pdo->prepare("SELECT * FROM products 
                              WHERE (title LIKE ? OR description LIKE ? OR sku LIKE ?) 
                              AND status = 'active' 
                              ORDER BY featured DESC, id DESC 
                              LIMIT ? OFFSET ?");
        $stmt->execute([$search_term, $search_term, $search_term, $per_page, $offset]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products 
                              WHERE (title LIKE ? OR description LIKE ? OR sku LIKE ?) 
                              AND status = 'active'");
        $stmt->execute([$search_term, $search_term, $search_term]);
        $total = $stmt->fetchColumn();
        
        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ];
    } catch (PDOException $e) {
        error_log('Error searching products: ' . $e->getMessage());
        return [
            'products' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => 0
        ];
    }
}

/**
 * Order Management Functions
 */

// Create a new order from cart
function create_order($customer_data) {
    global $pdo;
    
    // Get current cart
    $cart = get_cart();
    
    // Validate cart has items
    if (empty($cart['items'])) {
        return ['success' => false, 'message' => 'Your cart is empty'];
    }
    
    // Validate customer data
    $required_fields = ['first_name', 'last_name', 'email', 'address', 'city', 'state', 'zip', 'country'];
    foreach ($required_fields as $field) {
        if (empty($customer_data[$field])) {
            return ['success' => false, 'message' => 'Please fill in all required fields'];
        }
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Generate order ID
        $order_id = generate_order_id();
        
        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders 
                              (order_id, first_name, last_name, email, address, address2, 
                               city, state, zip, country, phone, subtotal, discount, 
                               shipping, tax, total, coupon_code, shipping_method, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $order_id,
            $customer_data['first_name'],
            $customer_data['last_name'],
            $customer_data['email'],
            $customer_data['address'],
            $customer_data['address2'] ?? '',
            $customer_data['city'],
            $customer_data['state'],
            $customer_data['zip'],
            $customer_data['country'],
            $customer_data['phone'] ?? '',
            $cart['subtotal'],
            $cart['discount'],
            $cart['shipping'],
            $cart['tax'],
            $cart['total'],
            $cart['coupon_code'],
            $cart['shipping_method'],
            'pending'
        ]);
        
        $db_order_id = $pdo->lastInsertId();
        
        // Insert order items
        $stmt = $pdo->prepare("INSERT INTO order_items 
                              (order_id, product_id, title, price, quantity, options) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($cart['items'] as $item) {
            $stmt->execute([
                $db_order_id,
                $item['id'],
                $item['title'],
                $item['price'],
                $item['quantity'],
                !empty($item['options']) ? json_encode($item['options']) : null
            ]);
        }
        
        // If coupon was used, update coupon usage
        if (!empty($cart['coupon_code'])) {
            $stmt = $pdo->prepare("UPDATE coupons SET uses = uses + 1 WHERE code = ?");
            $stmt->execute([$cart['coupon_code']]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Clear the cart
        clear_cart();
        
        // Store order ID in session for confirmation page
        $_SESSION['last_order_id'] = $order_id;
        
        return [
            'success' => true,
            'message' => 'Order created successfully',
            'order_id' => $order_id,
            'db_order_id' => $db_order_id
        ];
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log('Error creating order: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error creating order'];
    }
}

// Get order by ID
function get_order($order_id) {
    global $pdo;
    
    try {
        // Get order details
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return null;
        }
        
        // Get order items
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $order;
    } catch (PDOException $e) {
        error_log('Error getting order: ' . $e->getMessage());
        return null;
    }
}

// Update order status
function update_order_status($order_id, $status) {
    global $pdo, $order_statuses;
    
    // Validate status
    if (!isset($order_statuses[$status])) {
        return ['success' => false, 'message' => 'Invalid order status'];
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->execute([$status, $order_id]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        return ['success' => true, 'message' => 'Order status updated'];
    } catch (PDOException $e) {
        error_log('Error updating order status: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error updating order status'];
    }
}

/**
 * User Experience Functions
 */

// Track recently viewed products
function track_recently_viewed($product_id) {
    // Initialize recently viewed array if it doesn't exist
    if (!isset($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }
    
    // Remove product if it's already in the list
    $_SESSION['recently_viewed'] = array_filter($_SESSION['recently_viewed'], function($id) use ($product_id) {
        return $id != $product_id;
    });
    
    // Add product to the beginning of the array
    array_unshift($_SESSION['recently_viewed'], $product_id);
    
    // Keep only the last 10 products
    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 10);
    
    return true;
}

// Get recently viewed products
function get_recently_viewed($limit = 4) {
    global $pdo;
    
    if (!isset($_SESSION['recently_viewed']) || empty($_SESSION['recently_viewed'])) {
        return [];
    }
    
    // Get only the requested number of products
    $product_ids = array_slice($_SESSION['recently_viewed'], 0, $limit);
    
    try {
        // Create placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        
        $stmt = $pdo->prepare("SELECT * FROM products 
                              WHERE id IN ($placeholders) AND status = 'active'");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Sort products in the same order as the recently viewed array
        $sorted_products = [];
        foreach ($product_ids as $id) {
            foreach ($products as $product) {
                if ($product['id'] == $id) {
                    $sorted_products[] = $product;
                    break;
                }
            }
        }
        
        return $sorted_products;
    } catch (PDOException $e) {
        error_log('Error getting recently viewed products: ' . $e->getMessage());
        return [];
    }
}

// Track page view for analytics
function track_page_view($page_type, $page_id = null) {
    global $pdo;
    
    // Get visitor information
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $session_id = session_id();
    
    try {
        $stmt = $pdo->prepare("INSERT INTO page_views 
                              (page_type, page_id, session_id, ip_address, user_agent, referrer, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$page_type, $page_id, $session_id, $ip_address, $user_agent, $referrer]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Error tracking page view: ' . $e->getMessage());
        return false;
    }
}

/**
 * UI Helper Functions
 */

// Generate breadcrumbs
function generate_breadcrumbs($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    foreach ($items as $index => $item) {
        if ($index === array_key_last($items)) {
            // Last item (current page)
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['title']) . '</li>';
        } else {
            // Previous items
            $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['title']) . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    
    return $html;
}

// Format product price with sale price if available
function format_product_price($product) {
    if (!empty($product['sale_price']) && $product['sale_price'] < $product['price']) {
        return '<span class="product-price-sale">' . format_price($product['sale_price']) . '</span> ' . 
               '<span class="product-price-regular text-decoration-line-through text-muted">' . format_price($product['price']) . '</span>';
    } else {
        return '<span class="product-price">' . format_price($product['price']) . '</span>';
    }
}

// Get actual product price (considering sale price if available)
function get_actual_price($product) {
    if (!empty($product['sale_price']) && $product['sale_price'] < $product['price']) {
        return $product['sale_price'];
    } else {
        return $product['price'];
    }
}

// Generate product card HTML
function generate_product_card($product, $class = '') {
    $image = !empty($product['image_url']) ? $product['image_url'] : '/assets/img/placeholder.jpg';
    $price_html = format_product_price($product);
    $url = '/store/product.php?id=' . $product['id'];
    
    return <<<HTML
    <div class="card product-card h-100 {$class}">
        <a href="{$url}" class="text-decoration-none">
            <img src="{$image}" class="card-img-top product-img" alt="{$product['title']}">
        </a>
        <div class="card-body d-flex flex-column">
            <h5 class="card-title"><a href="{$url}" class="text-decoration-none text-dark">{$product['title']}</a></h5>
            <div class="mt-auto">
                <div class="product-price mb-2">{$price_html}</div>
                <button class="btn btn-primary btn-sm add-to-cart" data-product-id="{$product['id']}">Add to Cart</button>
            </div>
        </div>
    </div>
    HTML;
}

// Generate pagination links
function generate_pagination($base_url, $current_page, $total_pages) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $prev_url = $base_url . ($current_page - 1);
        $html .= '<li class="page-item"><a class="page-link" href="' . $prev_url . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    // Always show first page
    if ($start_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '1">1</a></li>';
        if ($start_page > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    // Page numbers
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Always show last page
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . $total_pages . '">' . $total_pages . '</a></li>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_url = $base_url . ($current_page + 1);
        $html .= '<li class="page-item"><a class="page-link" href="' . $next_url . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}