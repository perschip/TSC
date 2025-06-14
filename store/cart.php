<?php
// store/cart.php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/store/config.php';
require_once '../includes/store/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart if needed
initialize_cart();

// Process cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Handle different cart actions
    switch ($action) {
        case 'add':
            if (isset($_POST['product_id']) && isset($_POST['quantity'])) {
                $product_id = (int)$_POST['product_id'];
                $quantity = (int)$_POST['quantity'];
                $options = isset($_POST['options']) ? $_POST['options'] : [];
                
                // Add item to cart
                $result = add_to_cart($product_id, $quantity, $options);
                
                // If this is an AJAX request, return JSON response
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    
                    $cart = get_cart();
                    $cart_count = 0;
                    foreach ($cart['items'] as $item) {
                        $cart_count += $item['quantity'];
                    }
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => $result['success'],
                        'message' => $result['message'],
                        'cart_count' => $cart_count
                    ]);
                    exit;
                }
                
                // For regular form submissions, set message in session and redirect
                if ($result['success']) {
                    $_SESSION['cart_message'] = [
                        'title' => 'Added to Cart',
                        'message' => $result['message'],
                        'type' => 'success'
                    ];
                } else {
                    $_SESSION['cart_message'] = [
                        'title' => 'Error',
                        'message' => $result['message'],
                        'type' => 'error'
                    ];
                }
            }
            break;
            
        case 'update':
            if (isset($_POST['cart_item']) && is_array($_POST['cart_item'])) {
                foreach ($_POST['cart_item'] as $item_id => $quantity) {
                    update_cart_item($item_id, (int)$quantity);
                }
                $_SESSION['cart_message'] = [
                    'title' => 'Cart Updated',
                    'message' => 'Your cart has been updated successfully.',
                    'type' => 'success'
                ];
            }
            break;
            
        case 'remove':
            if (isset($_POST['item_id'])) {
                $item_id = $_POST['item_id'];
                remove_cart_item($item_id);
                
                // If this is an AJAX request, return JSON response
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    
                    $cart = get_cart();
                    $cart_count = 0;
                    foreach ($cart['items'] as $item) {
                        $cart_count += $item['quantity'];
                    }
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Item removed from cart.',
                        'cart_count' => $cart_count,
                        'cart_total' => format_price($cart['totals']['total'])
                    ]);
                    exit;
                }
                
                $_SESSION['cart_message'] = [
                    'title' => 'Item Removed',
                    'message' => 'Item has been removed from your cart.',
                    'type' => 'success'
                ];
            }
            break;
            
        case 'apply_coupon':
            if (isset($_POST['coupon_code'])) {
                $result = apply_coupon($_POST['coupon_code']);
                
                $_SESSION['cart_message'] = [
                    'title' => $result['success'] ? 'Coupon Applied' : 'Coupon Error',
                    'message' => $result['message'],
                    'type' => $result['success'] ? 'success' : 'error'
                ];
            }
            break;
            
        case 'remove_coupon':
            $result = remove_coupon();
            $_SESSION['cart_message'] = [
                'title' => 'Coupon Removed',
                'message' => $result['message'],
                'type' => 'success'
            ];
            break;
    }
    
    // Redirect back to cart page to prevent form resubmission
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        header('Location: /store/cart.php');
        exit;
    }
}

// Get cart data
$cart = get_cart();

// Ensure cart has the expected structure
if (!isset($cart['items'])) {
    $cart['items'] = [];
}

if (!isset($cart['totals'])) {
    $cart['totals'] = [
        'subtotal' => 0,
        'discount' => 0,
        'shipping' => 0,
        'tax' => 0,
        'total' => 0
    ];
}

// Get PayPal settings
$paypal_settings = get_paypal_settings();

// Set page title
$page_title = 'Shopping Cart';

// Create breadcrumbs
$breadcrumbs = [
    ['title' => 'Home', 'url' => '/'],
    ['title' => 'Store', 'url' => '/store/'],
    ['title' => 'Shopping Cart', 'url' => '']
];

// Get recommended products (products user might be interested in)
$recommended_products = [];

// First try to get products related to items in cart
if (!empty($cart['items'])) {
    $product_ids = array_column($cart['items'], 'product_id');
    if (!empty($product_ids)) {
        // Get related products for the first product in cart
        $recommended_products = get_related_products($product_ids[0], 4);
    }
}

// If no recommendations yet, get featured products
if (empty($recommended_products)) {
    $recommended_products = get_featured_products(4);
}

// Get recently viewed products
$recently_viewed = get_recently_viewed(4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Tristate Cards</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            padding-top: 56px;
            background-color: #f8f9fa;
            color: #495057;
        }
        
        .navbar-brand {
            font-weight: 700;
        }
        
        .btn {
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: var(--gradient-success);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #4e9a2a 0%, #95d4b8 100%);
            transform: translateY(-1px);
        }
        
        .footer {
            background: var(--gradient-primary);
            color: white;
            padding: 40px 0;
            margin-top: 60px;
        }
        
        .footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        
        .footer a:hover {
            color: white;
        }
        
        .cart-item-img {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 0.5rem;
        }
        
        .card {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
        }
        
        /* Form Styling */
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .quantity-input {
            width: 70px;
        }
        
        .cart-summary {
            background-color: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: var(--gradient-primary);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">Tristate Cards</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/store/">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/contact.php">Contact</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="/store/cart.php" class="btn btn-light me-2 active">
                        <i class="fas fa-shopping-cart me-1"></i> Cart <span id="cart-count" class="badge bg-danger">0</span>
                    </a>
                    <a href="/checkout/" class="btn btn-outline-light">
                        <i class="fas fa-credit-card me-1"></i> Checkout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Cart Section -->
    <section class="container py-5">
        <h1 class="mb-4">Shopping Cart</h1>
        
        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Cart Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <form id="update-cart-form" method="post" action="/store/cart.php">
                            <input type="hidden" name="action" value="update">
                            <div class="table-responsive">
                                <table class="table table-borderless mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" class="ps-3">Product</th>
                                            <th scope="col" class="text-center">Price</th>
                                            <th scope="col" class="text-center">Quantity</th>
                                            <th scope="col" class="text-end pe-3">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($cart['items'])): ?>
                                        <?php foreach($cart['items'] as $item_id => $item): ?>
                                        <tr class="cart-item" data-item-id="<?php echo $item_id; ?>">
                                            <td class="ps-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="cart-item-img me-3">
                                                        <?php if (!empty($item['image_url'])): ?>
                                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                                                 class="img-fluid" style="max-width: 80px;">
                                                        <?php else: ?>
                                                            <img src="/assets/images/no-image.png" 
                                                                 alt="No image available" 
                                                                 class="img-fluid" style="max-width: 80px;">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <a href="/store/product.php?id=<?php echo $item['product_id']; ?>" 
                                                               class="text-dark text-decoration-none">
                                                                <?php echo htmlspecialchars($item['title']); ?>
                                                            </a>
                                                        </h6>
                                                        <?php if (!empty($item['options'])): ?>
                                                            <div class="small text-muted">
                                                                <?php foreach($item['options'] as $option_name => $option_value): ?>
                                                                    <div><?php echo htmlspecialchars($option_name); ?>: 
                                                                         <?php echo htmlspecialchars($option_value); ?></div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm text-danger remove-item p-0 mt-2" 
                                                                data-item-id="<?php echo $item_id; ?>">
                                                            <i class="fas fa-trash-alt me-1"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center align-middle">
                                                <?php echo format_price($item['price']); ?>
                                            </td>
                                            <td class="text-center align-middle" style="width: 120px;">
                                                <div class="input-group input-group-sm quantity-control">
                                                    <button type="button" class="btn btn-outline-secondary quantity-btn decrease">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input type="number" name="cart_item[<?php echo $item_id; ?>]" 
                                                           class="form-control text-center quantity-input" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" max="99">
                                                    <button type="button" class="btn btn-outline-secondary quantity-btn increase">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="text-end pe-3 align-middle">
                                                <span class="item-total fw-bold">
                                                    <?php echo format_price($item['price'] * $item['quantity']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <p>Your cart is empty.</p>
                                                <a href="/store/" class="btn btn-primary">Continue Shopping</a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-white d-flex justify-content-between">
                                <a href="/store/" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left me-2"></i> Continue Shopping
                                </a>
                                <button type="submit" class="btn btn-primary update-cart-btn">
                                    <i class="fas fa-sync-alt me-2"></i> Update Cart
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Cart Summary -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="subtotal"><?php echo format_price(isset($cart['totals']['subtotal']) ? $cart['totals']['subtotal'] : 0); ?></span>
                        </div>
                        
                        <?php if (!empty($cart['coupon'])): ?>
                        <div id="coupon-discount">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Discount (<?php echo htmlspecialchars($cart['coupon']['code']); ?>):</span>
                                <span id="discount">-<?php echo format_price(isset($cart['totals']['discount']) ? $cart['totals']['discount'] : 0); ?></span>
                            </div>
                            <div class="mb-2">
                                <form method="post" action="/store/cart.php" class="d-inline">
                                    <input type="hidden" name="action" value="remove_coupon">
                                    <button type="submit" class="btn btn-sm text-danger p-0">
                                        <i class="fas fa-times-circle me-1"></i> Remove coupon
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span id="shipping"><?php echo format_price(isset($cart['totals']['shipping']) ? $cart['totals']['shipping'] : 0); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax:</span>
                            <span id="tax"><?php echo format_price(isset($cart['totals']['tax']) ? $cart['totals']['tax'] : 0); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <span class="fw-bold">Total:</span>
                            <span id="total" class="fw-bold"><?php echo format_price(isset($cart['totals']['total']) ? $cart['totals']['total'] : 0); ?></span>
                        </div>
                        
                        <?php if (empty($cart['coupon'])): ?>
                        <form method="post" action="/store/cart.php" class="mb-3">
                            <input type="hidden" name="action" value="apply_coupon">
                            <label for="coupon" class="form-label">Coupon Code</label>
                            <div class="input-group">
                                <input type="text" id="coupon" name="coupon_code" class="form-control" placeholder="Enter code">
                                <button type="submit" class="btn btn-outline-secondary">Apply</button>
                            </div>
                        </form>
                        <?php endif; ?>
                        
                        <a href="/checkout/" id="checkout-btn" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-lock me-2"></i> Proceed to Checkout
                        </a>
                        
                        <?php if (!empty($paypal_settings['client_id'])): ?>
                        <div id="paypal-button-container"></div>
                        <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Options -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Shipping Method</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="/store/cart.php" id="shipping-form">
                            <input type="hidden" name="action" value="set_shipping">
                            <?php foreach(get_shipping_methods() as $method_id => $method): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input shipping-method" 
                                           type="radio" 
                                           name="shipping_method" 
                                           id="shipping-<?php echo $method_id; ?>" 
                                           value="<?php echo $method_id; ?>"
                                           <?php echo (isset($cart['shipping_method']) && $cart['shipping_method'] == $method_id) ? 'checked' : ''; ?>>
                                    <label class="form-check-label d-flex justify-content-between" for="shipping-<?php echo $method_id; ?>">
                                        <span><?php echo htmlspecialchars($method['name']); ?></span>
                                        <span><?php echo format_price($method['price']); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($recommended_products)): ?>
        <!-- Recommended Products -->
        <section class="recommended-products mt-5">
            <h2 class="mb-4">You May Also Like</h2>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <?php foreach($recommended_products as $product): ?>
                    <div class="col">
                        <?php echo generate_product_card($product); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if (!empty($recently_viewed)): ?>
        <!-- Recently Viewed Products -->
        <section class="recently-viewed mt-5">
            <h2 class="mb-4">Recently Viewed</h2>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <?php foreach($recently_viewed as $product): ?>
                    <div class="col">
                        <?php echo generate_product_card($product); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Tristate Cards</h5>
                    <p>Your trusted source for collectible cards and memorabilia.</p>
                    <div class="d-flex gap-3 mt-3">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-ebay"></i></a>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/">Home</a></li>
                        <li><a href="/store/">Shop</a></li>
                        <li><a href="/about.php">About Us</a></li>
                        <li><a href="/contact.php">Contact</a></li>
                        <li><a href="/privacy-policy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <address>
                        <p><i class="fas fa-map-marker-alt me-2"></i> 123 Main St, Anytown, USA</p>
                        <p><i class="fas fa-phone me-2"></i> (555) 123-4567</p>
                        <p><i class="fas fa-envelope me-2"></i> info@tristatecards.com</p>
                    </address>
                </div>
            </div>
            
            <hr class="mt-4 mb-4" style="border-color: rgba(255, 255, 255, 0.1);">
            
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Tristate Cards. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/pp-acceptance-small.png" alt="PayPal Acceptance">
                </div>
            </div>
        </div>
    </footer>

    <!-- Cart Item Template -->
    <template id="cart-item-template">
        <div class="cart-item mb-3 border-bottom pb-3">
            <div class="row align-items-center">
                <div class="col-3 col-md-2">
                    <img src="" class="cart-item-img" alt="">
                </div>
                <div class="col-9 col-md-4">
                    <h6 class="cart-item-title mb-1"></h6>
                    <div class="cart-item-price text-muted"></div>
                </div>
                <div class="col-6 col-md-3 mt-3 mt-md-0">
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary decrease-quantity" type="button">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="form-control text-center item-quantity" value="1" min="1">
                        <button class="btn btn-outline-secondary increase-quantity" type="button">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="col-3 col-md-2 text-end mt-3 mt-md-0">
                    <div class="cart-item-total"></div>
                </div>
                <div class="col-3 col-md-1 text-end mt-3 mt-md-0">
                    <button class="btn btn-sm btn-outline-danger remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Toast notification function -->
    <script>
    function showToast(title, message, type = 'success') {
        // Remove any existing toasts
        const existingToasts = document.querySelectorAll('.toast-container');
        existingToasts.forEach(toast => toast.remove());
        
        // Create toast container
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '5';
        
        // Set header color based on type
        let headerClass = 'bg-success';
        let icon = 'check-circle';
        
        if (type === 'error') {
            headerClass = 'bg-danger';
            icon = 'exclamation-circle';
        } else if (type === 'warning') {
            headerClass = 'bg-warning';
            icon = 'exclamation-triangle';
        } else if (type === 'info') {
            headerClass = 'bg-info';
            icon = 'info-circle';
        }
        
        // Create toast HTML
        toastContainer.innerHTML = `
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header ${headerClass} text-white">
                    <i class="fas fa-${icon} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;
        
        // Add to document
        document.body.appendChild(toastContainer);
        
        // Remove after 5 seconds
        setTimeout(() => {
            toastContainer.remove();
        }, 5000);
    }
    </script>
    
    <!-- PayPal JS SDK -->
    <?php if (!empty($paypal_settings['client_id'])): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo htmlspecialchars($paypal_settings['client_id']); ?>&currency=<?php echo htmlspecialchars($paypal_settings['currency']); ?>"></script>
    <?php endif; ?>
    
    <!-- Custom JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Cart page loaded, initializing...');
        
        // Cart data is now managed server-side via PHP sessions
        // The cart variable will be used to store the current cart state for client-side operations
        let cart = <?php echo json_encode($cart['items'] ?? []); ?>;
        console.log('Cart data from server:', cart);
        
        // Store coupon data
        let appliedCoupon = {
            code: '',
            discount: 0,
            discountType: ''
        };
        
        // Initialize elements
        const cartCount = document.querySelector('.cart-count');
        const cartItemsContainer = document.getElementById('cart-items-container');
        const emptyCartMessage = document.getElementById('empty-cart-message');
        const cartItemTemplate = document.getElementById('cart-item-template');
        const subtotalElement = document.getElementById('subtotal');
        const shippingElement = document.getElementById('shipping');
        const totalElement = document.getElementById('total');
        const couponCodeInput = document.getElementById('coupon-code');
        const applyCouponButton = document.getElementById('apply-coupon');
        const couponMessage = document.getElementById('coupon-message');
        const checkoutButton = document.getElementById('checkout-button');
        
        // Set up coupon code application if elements exist
        if (couponCodeInput && applyCouponButton) {
            applyCouponButton.addEventListener('click', function(e) {
                e.preventDefault();
                applyCoupon();
            });
            
            couponCodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyCoupon();
                }
            });
        }
        
        function applyCoupon() {
            console.log('Applying coupon...');
            if (!couponCodeInput || !couponMessage) {
                console.error('Coupon elements not found');
                return;
            }
            
            const couponCode = couponCodeInput.value.trim();
            if (!couponCode) {
                couponMessage.textContent = 'Please enter a coupon code';
                couponMessage.className = 'form-text text-danger';
                return;
            }
            
            console.log('Applying coupon code:', couponCode);
            
            // Show loading state
            applyCouponButton.disabled = true;
            applyCouponButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Applying...';
            couponMessage.textContent = 'Checking coupon...';
            couponMessage.className = 'form-text text-muted';
            
            // Send AJAX request to apply coupon using the server-side cart
            fetch('/store/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    'action': 'apply_coupon',
                    'coupon_code': couponCode
                })
            })
            .then(response => response.json())
            .then(data => {
                // Reset button state
                applyCouponButton.disabled = false;
                applyCouponButton.innerHTML = 'Apply';
                
                if (data.success) {
                    // Store coupon data for client-side reference
                    appliedCoupon = {
                        code: couponCode,
                        discount: data.discount,
                        discountType: data.discount_type
                    };
                    
                    // Show success message
                    couponMessage.textContent = data.message;
                    couponMessage.className = 'form-text text-success';
                    
                    // Update cart data from server response
                    cart = data.cart.items;
                    
                    // Update UI with new totals
                    updateCartDisplay(data.cart);
                    
                    // Show toast notification
                    showToast('Coupon Applied', `Coupon ${couponCode} has been applied to your cart.`, 'success');
                } else {
                    // Show error message
                    couponMessage.textContent = data.message;
                    couponMessage.className = 'form-text text-danger';
                    
                    // Clear any previously applied coupon
                    appliedCoupon = {
                        code: '',
                        discount: 0,
                        discountType: ''
                    };
                }
            })
            .catch(error => {
                console.error('Error applying coupon:', error);
                applyCouponButton.disabled = false;
                applyCouponButton.innerHTML = 'Apply';
                couponMessage.textContent = 'Error applying coupon. Please try again.';
                couponMessage.className = 'form-text text-danger';
            });
        }
        
        // If there's a coupon already applied (from PHP session), show it
        <?php if (!empty($cart['coupon'])): ?>
        appliedCoupon = {
            code: '<?php echo htmlspecialchars($cart['coupon']['code']); ?>',
            discount: <?php echo (float)$cart['coupon']['discount']; ?>,
            discountType: '<?php echo htmlspecialchars($cart['coupon']['type']); ?>'
        };
        
        if (couponCodeInput && couponMessage) {
            couponCodeInput.value = appliedCoupon.code;
            couponMessage.textContent = `Coupon ${appliedCoupon.code} applied`;
            couponMessage.className = 'form-text text-success';
        }
        <?php endif; ?>
        
        // Elements for cart manipulation
        const cartItemsContainer = document.getElementById('cart-items-container');
        const emptyCartMessage = document.getElementById('empty-cart-message');
        const cartItemTemplate = document.getElementById('cart-item-template');
        const cartCount = document.querySelector('.cart-count');
        const subtotalElement = document.getElementById('subtotal');
        const shippingElement = document.getElementById('shipping');
        const totalElement = document.getElementById('total');
        const checkoutButton = document.getElementById('checkout-button');
        
        // Debug cart data
        console.log('Cart data on page load:', cart);
        
        // Set up quantity update handlers
        document.addEventListener('click', function(e) {
            // Handle quantity increase button
            if (e.target.closest('.increase-quantity')) {
                const quantityInput = e.target.closest('.input-group').querySelector('.item-quantity');
                const itemId = quantityInput.dataset.itemId;
                const currentQty = parseInt(quantityInput.value) || 1;
                updateItemQuantity(itemId, currentQty + 1);
            }
            
            // Handle quantity decrease button
            if (e.target.closest('.decrease-quantity')) {
                const quantityInput = e.target.closest('.input-group').querySelector('.item-quantity');
                const itemId = quantityInput.dataset.itemId;
                const currentQty = parseInt(quantityInput.value) || 1;
                if (currentQty > 1) {
                    updateItemQuantity(itemId, currentQty - 1);
                }
            }
            
            // Handle remove item button
            if (e.target.closest('.remove-item')) {
                const itemId = e.target.closest('.remove-item').dataset.itemId;
                removeItem(itemId);
            }
        });
        
        // Handle direct quantity input changes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('item-quantity')) {
                const itemId = e.target.dataset.itemId;
                let newQty = parseInt(e.target.value) || 1;
                
                // Ensure quantity is at least 1
                if (newQty < 1) {
                    newQty = 1;
                    e.target.value = 1;
                }
                
                updateItemQuantity(itemId, newQty);
            }
        });
        
        // Set up shipping method selection
        const shippingMethods = document.querySelectorAll('.shipping-method');
        shippingMethods.forEach(method => {
            method.addEventListener('change', function() {
                updateShippingMethod(this.value);
            });
        });
        
        // Function to update item quantity
        function updateItemQuantity(itemId, quantity) {
            console.log(`Updating item ${itemId} quantity to ${quantity}`);
            
            // Show loading overlay
            showLoadingOverlay();
            
            // Send AJAX request to update cart
            fetch('/store/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    'action': 'update',
                    'item_id': itemId,
                    'quantity': quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoadingOverlay();
                
                if (data.success) {
                    // Update local cart data
                    cart = data.cart.items;
                    
                    // Update UI
                    updateCartDisplay(data.cart);
                    
                    // Show success message
                    showToast('Cart Updated', 'Your cart has been updated successfully.', 'success');
                } else {
                    // Show error message
                    showToast('Error', data.message || 'Failed to update cart.', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating cart:', error);
                hideLoadingOverlay();
                showToast('Error', 'An error occurred while updating your cart.', 'error');
            });
        }
        
        // Function to remove item from cart
        function removeItem(itemId) {
            console.log(`Removing item ${itemId} from cart`);
            
            // Show loading overlay
            showLoadingOverlay();
            
            // Send AJAX request to remove item
            fetch('/store/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    'action': 'remove',
                    'item_id': itemId
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoadingOverlay();
                
                if (data.success) {
                    // Update local cart data
                    cart = data.cart.items;
                    
                    // Update UI
                    updateCartDisplay(data.cart);
                    
                    // Show success message
                    showToast('Item Removed', 'The item has been removed from your cart.', 'success');
                } else {
                    // Show error message
                    showToast('Error', data.message || 'Failed to remove item from cart.', 'error');
                }
            })
            .catch(error => {
                console.error('Error removing item from cart:', error);
                hideLoadingOverlay();
                showToast('Error', 'An error occurred while removing the item from your cart.', 'error');
            });
        }
        
        // Function to update shipping method
        function updateShippingMethod(methodId) {
            console.log(`Updating shipping method to ${methodId}`);
            
            // Show loading overlay
            showLoadingOverlay();
            
            // Send AJAX request to update shipping method
            fetch('/store/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    'action': 'set_shipping',
                    'shipping_method': methodId
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoadingOverlay();
                
                if (data.success) {
                    // Update UI with new totals
                    updateCartDisplay(data.cart);
                    
                    // Show success message
                    showToast('Shipping Updated', 'Shipping method has been updated.', 'success');
                } else {
                    // Show error message
                    showToast('Error', data.message || 'Failed to update shipping method.', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating shipping method:', error);
                hideLoadingOverlay();
                showToast('Error', 'An error occurred while updating the shipping method.', 'error');
            });
        }
        
        // Function to update cart display with server data
        function updateCartDisplay(cartData) {
            console.log('Updating cart display with data:', cartData);
            
            // Update cart count in header
            if (cartCount) {
                cartCount.textContent = cartData.item_count || 0;
            }
            
            // Handle empty cart
            if (!cartData.items || cartData.items.length === 0) {
                console.log('Cart is empty');
                
                // Show empty cart message
                if (emptyCartMessage) {
                    emptyCartMessage.style.display = 'block';
                }
                
                // Disable checkout button
                if (checkoutButton) {
                    checkoutButton.disabled = true;
                    checkoutButton.classList.add('disabled');
                }
                
                // Clear any existing items
                if (cartItemsContainer) {
                    cartItemsContainer.innerHTML = '';
                }
                
                // Update totals
                if (subtotalElement) subtotalElement.textContent = '$0.00';
                if (shippingElement) shippingElement.textContent = '$0.00';
                if (totalElement) totalElement.textContent = '$0.00';
                
                return;
            }
            
            // Cart has items
            console.log('Cart has items, updating display');
            
            // Hide empty cart message
            if (emptyCartMessage) {
                emptyCartMessage.style.display = 'none';
            }
            
            // Enable checkout button
            if (checkoutButton) {
                checkoutButton.disabled = false;
                checkoutButton.classList.remove('disabled');
            }
            
            // Update cart items display
            if (cartItemsContainer) {
                // Clear existing items
                cartItemsContainer.innerHTML = '';
                
                // Add each item to the container
                cartData.items.forEach(item => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'cart-item mb-3 border-bottom pb-3';
                    
                    itemElement.innerHTML = `
                        <div class="row align-items-center">
                            <div class="col-3 col-md-2">
                                <img src="${item.image}" class="img-fluid rounded" alt="${item.name}">
                            </div>
                            <div class="col-9 col-md-4">
                                <h6 class="mb-1">${item.name}</h6>
                                <div class="text-muted small">${item.price_formatted}</div>
                                ${item.options ? `<div class="text-muted small">${item.options}</div>` : ''}
                            </div>
                            <div class="col-6 col-md-3 mt-3 mt-md-0">
                                <div class="input-group input-group-sm">
                                    <button class="btn btn-outline-secondary decrease-quantity" type="button">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="form-control text-center item-quantity" 
                                           value="${item.quantity}" min="1" data-item-id="${item.id}">
                                    <button class="btn btn-outline-secondary increase-quantity" type="button">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-3 col-md-2 text-end mt-3 mt-md-0">
                                <div class="fw-bold">${item.total_formatted}</div>
                            </div>
                            <div class="col-3 col-md-1 text-end mt-3 mt-md-0">
                                <button class="btn btn-sm btn-outline-danger remove-item" data-item-id="${item.id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    cartItemsContainer.appendChild(itemElement);
                });
            }
            
            // Update totals
            if (subtotalElement) subtotalElement.textContent = cartData.subtotal_formatted;
            if (shippingElement) shippingElement.textContent = cartData.shipping_cost_formatted;
            if (totalElement) totalElement.textContent = cartData.total_formatted;
            
            // Initialize PayPal button if available
            initPayPalButton(cartData);
        }
        
        // Function to show loading overlay
        function showLoadingOverlay() {
            // Remove any existing overlay
            hideLoadingOverlay();
            
            // Create overlay
            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
            overlay.style.display = 'flex';
            overlay.style.justifyContent = 'center';
            overlay.style.alignItems = 'center';
            overlay.style.zIndex = '9999';
            
            // Create spinner
            overlay.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            `;
            
            // Add to document
            document.body.appendChild(overlay);
        }
        
        // Function to hide loading overlay
        function hideLoadingOverlay() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
        
        // Initialize PayPal button
        function initPayPalButton(cartData) {
            const paypalButtonContainer = document.getElementById('paypal-button-container');
            if (!paypalButtonContainer) return;
            
            // Clear existing buttons
            paypalButtonContainer.innerHTML = '';
            
            // Check if cart is empty
            if (!cartData || !cartData.items || cartData.items.length === 0) return;
            
            // Check if PayPal is available
            if (typeof paypal === 'undefined') {
                console.error('PayPal SDK not loaded');
                return;
            }
            
            // Create PayPal button
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'paypal'
                },
                
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                currency_code: '<?php echo htmlspecialchars($paypal_settings['currency']); ?>',
                                value: cartData.total
                            },
                            description: 'Purchase from Tristate Cards'
                        }]
                    });
                },
                
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(orderData) {
                        // Redirect to checkout with PayPal order ID
                        window.location.href = '/checkout/index.php?paypal_order_id=' + orderData.id;
                    });
                },
                
                onError: function(err) {
                    console.error('PayPal error:', err);
                    showToast('Error', 'There was an error processing your PayPal payment. Please try again.', 'error');
                }
            }).render('#paypal-button-container');
        }
            }
            console.log('Cleared existing cart items');
            
            // Add cart items
            console.log('Adding', cart.length, 'items to DOM');
            
            cart.forEach((item, index) => {
                try {
                    // Create cart item from template
                    const cartItemNode = document.importNode(cartItemTemplate.content, true);
                    const cartItem = cartItemNode.querySelector('.cart-item');
                    
                    if (!cartItem) {
                        console.error('Failed to create cart item from template');
                        return;
                    }
                    
                    // Set item data
                    cartItem.dataset.index = index;
                    
                    // Set item details with null checks
                    const img = cartItem.querySelector('.cart-item-img');
                    if (img) {
                        img.src = item.image || '/assets/images/no-image.png';
                        img.alt = item.title || 'Product';
                    }
                    
                    const titleEl = cartItem.querySelector('.cart-item-title');
                    if (titleEl) titleEl.textContent = item.title || 'Unknown Product';
                    
                    const priceEl = cartItem.querySelector('.cart-item-price');
                    if (priceEl) priceEl.textContent = `$${parseFloat(item.price || 0).toFixed(2)}`;
                    
                    const quantityInput = cartItem.querySelector('.item-quantity');
                    if (quantityInput) {
                        quantityInput.value = item.quantity || 1;
                        quantityInput.dataset.index = index;
                    }
                    
                    const totalEl = cartItem.querySelector('.cart-item-total');
                    if (totalEl) {
                        const itemTotal = parseFloat(item.price || 0) * (parseInt(item.quantity) || 1);
                        totalEl.textContent = `$${itemTotal.toFixed(2)}`;
                    }
                    
                    // Add event listeners
                    const decreaseBtn = cartItem.querySelector('.decrease-quantity');
                    if (decreaseBtn) {
                        decreaseBtn.addEventListener('click', function() {
                            decreaseQuantity(index);
                        });
                    }
                    
                    const increaseBtn = cartItem.querySelector('.increase-quantity');
                    if (increaseBtn) {
                        increaseBtn.addEventListener('click', function() {
                            increaseQuantity(index);
                        });
                    }
                    
                    if (quantityInput) {
                        quantityInput.addEventListener('change', function() {
                            updateQuantity(index, parseInt(this.value) || 1);
                        });
                    }
                    
                    const removeBtn = cartItem.querySelector('.remove-item');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function() {
                            removeItem(index);
                        });
                    }
                    
                    // Add to container
                    cartItemsContainer.appendChild(cartItem);
                    console.log('Added item to cart:', item.title || 'Unknown item');
                } catch (e) {
                    console.error('Error rendering cart item:', e, item);
                }
            });
            
            // Update totals
            updateTotals();
            
            // Initialize PayPal button
            initPayPalButton();
        }
        
        // Initialize PayPal button
        function initPayPalButton() {
            console.log('Initializing PayPal button...');
            
            // Check if PayPal SDK is loaded
            if (typeof paypal === 'undefined') {
                console.warn('PayPal SDK not loaded, possibly blocked by ad blocker');
                return;
            }
            
            // Get PayPal button container
            const paypalButtonContainer = document.getElementById('paypal-button-container');
            if (!paypalButtonContainer) {
                console.error('PayPal button container not found');
                return;
            }
            
            // Clear existing buttons
            paypalButtonContainer.innerHTML = '';
            
            // Get totals
            const totals = updateTotals();
            if (!totals || totals.total <= 0) {
                console.warn('Cart total is zero or negative, not initializing PayPal');
                return;
            }
            
            // Create PayPal button
            paypal.Buttons({
                style: {
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal',
                    layout: 'vertical',
                },
                
                createOrder: function(data, actions) {
                    // Calculate totals
                    const subtotal = totals.subtotal;
                    const shipping = totals.shipping;
                    const discount = totals.discount;
                    const total = totals.total;
                    
                    // Handle zero or negative total (100% discount)
                    const paypalTotal = Math.max(0.01, total);
                    
                    console.log('Creating PayPal order with total:', paypalTotal.toFixed(2));
                    
                    // Create order with item details
                    return actions.order.create({
                        purchase_units: [{
                            description: 'Tristate Cards Order',
                            amount: {
                                value: paypalTotal.toFixed(2),
                                breakdown: {
                                    item_total: {
                                        value: subtotal.toFixed(2),
                                        currency_code: 'USD'
                                    },
                                    shipping: {
                                        value: shipping.toFixed(2),
                                        currency_code: 'USD'
                                    },
                                    discount: {
                                        value: discount.toFixed(2),
                                        currency_code: 'USD'
                                    }
                                }
                            },
                            items: cart.map(item => ({
                                name: item.title || 'Trading Card',
                                unit_amount: {
                                    value: parseFloat(item.price).toFixed(2),
                                    currency_code: 'USD'
                                },
                                quantity: item.quantity || 1
                            }))
                        }]
                    });
                },
                
                onApprove: function(data, actions) {
                    console.log('PayPal payment approved:', data);
                    return actions.order.capture().then(function(details) {
                        console.log('PayPal payment completed:', details);
                        
                        // Redirect to checkout with PayPal data
                        window.location.href = '/checkout/?paypal_order_id=' + data.orderID;
                    });
                },
                
                onError: function(err) {
                    console.error('PayPal error:', err);
                    showToast('error', 'Payment Error', 'There was an error processing your payment. Please try again.');
                }
            }).render('#paypal-button-container');
            
            console.log('PayPal button rendered successfully');
        }
        
        // Update totals function
        function updateTotals() {
            // Get elements
            const subtotalElement = document.getElementById('subtotal');
            const shippingElement = document.getElementById('shipping');
            const discountElement = document.getElementById('discount');
            const totalElement = document.getElementById('total');
            const discountRow = document.getElementById('discount-row');
            
            // Calculate subtotal
            let subtotal = 0;
            cart.forEach(item => {
                const price = parseFloat(item.price) || 0;
                const quantity = parseInt(item.quantity) || 1;
                subtotal += price * quantity;
            });
            
            // Fixed shipping rate
            const shipping = subtotal > 0 ? 5.99 : 0;
            
            // Apply coupon if available
            let discount = 0;
            const appliedCoupon = JSON.parse(localStorage.getItem('tristateAppliedCoupon') || 'null');
            
            if (appliedCoupon) {
                if (appliedCoupon.type === 'percentage') {
                    discount = subtotal * (appliedCoupon.value / 100);
                } else if (appliedCoupon.type === 'fixed') {
                    discount = appliedCoupon.value;
                }
                
                // Don't allow discount to exceed subtotal
                if (discount > subtotal) {
                    discount = subtotal;
                }
                
                // Show discount row
                if (discountRow) {
                    discountRow.style.display = 'table-row';
                }
            } else {
                // Hide discount row if no coupon
                if (discountRow) {
                    discountRow.style.display = 'none';
                }
            }
            
            // Calculate total
            const total = subtotal + shipping - discount;
            
            // Update display
            if (subtotalElement) subtotalElement.textContent = `$${subtotal.toFixed(2)}`;
            if (shippingElement) shippingElement.textContent = `$${shipping.toFixed(2)}`;
            if (discountElement) discountElement.textContent = `-$${discount.toFixed(2)}`;
            if (totalElement) totalElement.textContent = `$${total.toFixed(2)}`;
            
            console.log('Updated totals:', {
                subtotal: subtotal.toFixed(2),
                shipping: shipping.toFixed(2),
                discount: discount.toFixed(2),
                total: total.toFixed(2)
            });
            
            // Return values for PayPal
            return {
                subtotal,
                shipping,
                discount,
                total
            };
        }
        
        // Functions for cart item manipulation
        function decreaseQuantity(index) {
            console.log('Decreasing quantity for item at index:', index);
            if (!Array.isArray(cart) || index < 0 || index >= cart.length) {
                console.error('Invalid cart or index');
                return;
            }
            
            if (cart[index].quantity > 1) {
                cart[index].quantity -= 1;
                console.log('Decreased quantity to:', cart[index].quantity);
            } else {
                // If quantity would be 0, remove the item
                removeItem(index);
                return;
            }
            
            // Save cart and update UI
            localStorage.setItem('tristateCart', JSON.stringify(cart));
            renderCart();
            showToast('success', 'Cart Updated', 'Item quantity updated');
        }
        
        function increaseQuantity(index) {
            console.log('Increasing quantity for item at index:', index);
            if (!Array.isArray(cart) || index < 0 || index >= cart.length) {
                console.error('Invalid cart or index');
                return;
            }
            
            // Increase quantity with a reasonable limit
            if (cart[index].quantity < 99) {
                cart[index].quantity += 1;
                console.log('Increased quantity to:', cart[index].quantity);
            } else {
                console.warn('Maximum quantity reached');
                showToast('warning', 'Maximum Quantity', 'Cannot add more of this item');
                return;
            }
            
            // Save cart and update UI
            localStorage.setItem('tristateCart', JSON.stringify(cart));
            renderCart();
            showToast('success', 'Cart Updated', 'Item quantity updated');
        }
        
        function updateQuantity(index, quantity) {
            console.log('Updating quantity for item at index:', index, 'to:', quantity);
            if (!Array.isArray(cart) || index < 0 || index >= cart.length) {
                console.error('Invalid cart or index');
                return;
            }
            
            // Validate quantity
            if (quantity <= 0) {
                removeItem(index);
                return;
            } else if (quantity > 99) {
                quantity = 99;
                showToast('warning', 'Maximum Quantity', 'Quantity limited to 99');
            }
            
            // Update quantity
            cart[index].quantity = quantity;
            
            // Save cart and update UI
            localStorage.setItem('tristateCart', JSON.stringify(cart));
            renderCart();
            showToast('success', 'Cart Updated', 'Item quantity updated');
        }
        
        function removeItem(index) {
            console.log('Removing item at index:', index);
            if (!Array.isArray(cart) || index < 0 || index >= cart.length) {
                console.error('Invalid cart or index');
                return;
            }
            
            // Get item info for toast message
            const itemTitle = cart[index].title || 'Item';
            
            // Remove item
            cart.splice(index, 1);
            console.log('Item removed, cart now has', cart.length, 'items');
            
            // Save cart and update UI
            localStorage.setItem('tristateCart', JSON.stringify(cart));
            renderCart();
            showToast('info', 'Item Removed', `${itemTitle} has been removed from your cart`);
        }
        
        // Store coupon data
        let appliedCoupon = {
            code: '',
            discount: 0,
            discountType: ''
        };
        
        // Show toast notification function
        function showToast(title, message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(toastContainer);
            }
            
            // Create a unique ID for the toast
            const toastId = 'toast-' + Date.now();
            
            // Create toast element
            const toastHTML = `
                <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-${type} text-white">
                        <strong class="me-auto">${title}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            // Add toast to container
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            // Initialize and show the toast
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
            toast.show();
            
            // Remove toast after it's hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }
        
        // Add checkout button click handler
        document.getElementById('checkout-button').addEventListener('click', function() {
            // Show loading overlay
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center';
            loadingOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
            loadingOverlay.style.zIndex = '9999';
            loadingOverlay.innerHTML = `
                <div class="bg-white p-5 rounded-3 text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5>Preparing Checkout...</h5>
                </div>
            `;
            document.body.appendChild(loadingOverlay);
            
            // Redirect after a short delay to show the loading effect
            setTimeout(() => {
                window.location.href = '/checkout/';
            }, 800);
        });
    });
    </script>
</body>
</html>