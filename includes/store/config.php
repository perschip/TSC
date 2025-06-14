<?php
/**
 * Store Configuration File
 * Contains all configuration settings for the store and checkout system
 */

// Store settings
$store_config = [
    'store_name' => 'Tristate Cards',
    'store_description' => 'Collectible Trading Cards and Sports Cards',
    'store_logo' => '/assets/images/logo.png',
    'currency' => 'USD',
    'currency_symbol' => '$',
    'tax_rate' => 0.00, // Set to appropriate tax rate if needed
    'shipping_flat_rate' => 4.99,
    'free_shipping_threshold' => 75.00,
    'enable_paypal' => true,
    'enable_credit_card' => true,
    'enable_coupons' => true,
    'products_per_page' => 12,
    'show_related_products' => true,
    'show_recently_viewed' => true,
    'recently_viewed_count' => 4,
    'enable_analytics' => true
];

// PayPal settings - these will be overridden by database values if available
$paypal_settings = [
    'client_id' => '',
    'mode' => 'sandbox', // 'sandbox' or 'production'
    'currency' => 'USD',
    'business_name' => 'Tristate Cards'
];

// Load PayPal settings from database if available
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'paypal_settings'");
    $settings_table_exists = $stmt->rowCount() > 0;
    
    if ($settings_table_exists) {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM paypal_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $paypal_settings[$row['setting_name']] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    error_log('Error loading PayPal settings: ' . $e->getMessage());
}

// Shipping methods
$shipping_methods = [
    'standard' => [
        'name' => 'Standard Shipping',
        'description' => '3-5 business days',
        'price' => 4.99,
        'free_threshold' => 75.00
    ],
    'express' => [
        'name' => 'Express Shipping',
        'description' => '1-2 business days',
        'price' => 9.99,
        'free_threshold' => 150.00
    ]
];

// Payment methods
$payment_methods = [
    'paypal' => [
        'name' => 'PayPal',
        'description' => 'Pay securely using PayPal',
        'enabled' => $paypal_settings['client_id'] !== '',
        'icon' => 'fab fa-paypal'
    ],
    'credit_card' => [
        'name' => 'Credit Card',
        'description' => 'Pay with credit or debit card',
        'enabled' => true,
        'icon' => 'far fa-credit-card'
    ]
];

// Order statuses
$order_statuses = [
    'pending' => 'Pending',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'refunded' => 'Refunded'
];

// Helper functions for the store
function format_price($price, $currency_symbol = '$') {
    return $currency_symbol . number_format((float)$price, 2, '.', ',');
}

function calculate_shipping($subtotal, $shipping_method = 'standard') {
    global $shipping_methods;
    
    if (!isset($shipping_methods[$shipping_method])) {
        $shipping_method = 'standard';
    }
    
    $method = $shipping_methods[$shipping_method];
    
    // Check if order qualifies for free shipping
    if ($subtotal >= $method['free_threshold']) {
        return 0;
    }
    
    return $method['price'];
}

function calculate_tax($subtotal, $tax_rate = null) {
    global $store_config;
    
    if ($tax_rate === null) {
        $tax_rate = $store_config['tax_rate'];
    }
    
    return $subtotal * $tax_rate;
}

function generate_order_id() {
    return 'TSC-' . strtoupper(substr(uniqid(), 0, 8)) . '-' . date('Ymd');
}

function get_coupon_discount($code, $subtotal) {
    global $pdo;
    
    if (empty($code)) {
        return 0;
    }
    
    try {
        // Check if coupons table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'coupons'");
        if ($stmt->rowCount() === 0) {
            return 0;
        }
        
        // Get coupon details
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active' AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coupon) {
            return 0;
        }
        
        // Calculate discount
        if ($coupon['discount_type'] === 'percentage') {
            return $subtotal * ($coupon['discount_value'] / 100);
        } else {
            // Fixed amount discount
            return min($coupon['discount_value'], $subtotal); // Don't allow discount greater than subtotal
        }
    } catch (PDOException $e) {
        error_log('Error checking coupon: ' . $e->getMessage());
        return 0;
    }
}
