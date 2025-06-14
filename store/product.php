<?php
// store/product.php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/store/config.php';
require_once '../includes/store/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get product details using our new function
$product = get_product($product_id);

// Track page view for analytics
if ($product) {
    track_page_view('product', $product_id);
    
    // Track this product in recently viewed
    track_recently_viewed($product_id);
    
    // Get related products
    $related_products = get_related_products($product_id, 4);
    
    // Get recently viewed products (excluding current product)
    $recently_viewed = get_recently_viewed(4);
    // Filter out current product from recently viewed
    $recently_viewed = array_filter($recently_viewed, function($item) use ($product_id) {
        return $item['id'] != $product_id;
    });
}

// Load PayPal settings
$paypal_settings = get_paypal_settings();

// Set page title
$page_title = $product ? $product['title'] : 'Product Not Found';

// Create breadcrumbs
$breadcrumbs = [
    ['title' => 'Home', 'url' => '/'],
    ['title' => 'Store', 'url' => '/store/'],
];

// Add category to breadcrumbs if available
if ($product && !empty($product['categories'])) {
    $breadcrumbs[] = [
        'title' => $product['categories'][0]['name'],
        'url' => '/store/category.php?id=' . $product['categories'][0]['id']
    ];
}

// Add product title to breadcrumbs
if ($product) {
    $breadcrumbs[] = ['title' => $product['title'], 'url' => ''];
}
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
        
        .product-img-container {
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .product-img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        
        .product-price {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--primary-color);
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
        
        .section-title {
            position: relative;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--gradient-primary);
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
        
        .badge-new {
            background: linear-gradient(135deg, #ff6b6b 0%, #e74c3c 100%);
            color: white;
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
        }
        
        .quantity-input {
            width: 70px;
        }
        
        .product-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .product-description {
            white-space: pre-line;
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
                        <a class="nav-link active" href="/store/">Shop</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/contact.php">Contact</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="/store/cart.php" class="btn btn-light me-2">
                        <i class="fas fa-shopping-cart me-1"></i> Cart <span id="cart-count" class="badge bg-danger">0</span>
                    </a>
                    <a href="/checkout/" class="btn btn-outline-light">
                        <i class="fas fa-credit-card me-1"></i> Checkout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Product Detail Section -->
    <section class="container py-5">
        <!-- Breadcrumbs -->
        <?php echo generate_breadcrumbs($breadcrumbs); ?>
        
        <?php if (!$product): ?>
            <div class="text-center py-5">
                <h4>Product Not Found</h4>
                <p>Sorry, the product you're looking for doesn't exist or has been removed.</p>
                <a href="/store/" class="btn btn-primary mt-3">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="mb-4">
                <a href="/store/" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i> Back to Shop
                </a>
            </div>
            
            <div class="row">
                <!-- Product Image -->
                <div class="col-md-6 mb-4">
                    <div class="product-img-container p-3 shadow-sm">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="product-img" alt="<?php echo htmlspecialchars($product['title']); ?>">
                        <?php else: ?>
                            <img src="/assets/images/no-image.png" class="product-img" alt="No image available">
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Product Details -->
                <div class="col-md-6">
                    <h1 class="mb-3"><?php echo htmlspecialchars($product['title']); ?></h1>
                    
                    <?php if (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                        <span class="badge-new mb-3 d-inline-block">NEW</span>
                    <?php endif; ?>
                    
                    <div class="product-meta mb-3">
                        <?php if (!empty($product['sku'])): ?>
                            <p><strong>SKU:</strong> <?php echo htmlspecialchars($product['sku']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($product['categories'])): ?>
                            <p><strong>Category:</strong> 
                                <?php foreach ($product['categories'] as $index => $category): ?>
                                    <a href="/store/category.php?id=<?php echo $category['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </a><?php echo ($index < count($product['categories']) - 1) ? ', ' : ''; ?>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        
                        <p><strong>Availability:</strong> 
                            <?php if ($product['inventory'] > 0): ?>
                                <span class="text-success">In Stock (<?php echo $product['inventory']; ?> available)</span>
                            <?php else: ?>
                                <span class="text-danger">Out of Stock</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="product-price mb-4"><?php echo format_product_price($product); ?></div>
                    
                    <div class="product-description mb-4">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                    
                    <?php if ($product['inventory'] > 0): ?>
                        <form id="add-to-cart-form" action="/store/cart.php" method="post" class="mb-4">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <div class="d-flex align-items-center mb-4">
                                <label for="quantity" class="me-3">Quantity:</label>
                                <input type="number" id="quantity" name="quantity" class="form-control quantity-input me-3" value="1" min="1" max="<?php echo $product['inventory']; ?>">
                            </div>
                            
                            <?php if (!empty($product['options'])): ?>
                                <div class="mb-4">
                                    <h5>Options</h5>
                                    <?php foreach(json_decode($product['options'], true) as $option_name => $option_values): ?>
                                        <div class="mb-3">
                                            <label for="option-<?php echo sanitize_id($option_name); ?>" class="form-label">
                                                <?php echo htmlspecialchars($option_name); ?>:
                                            </label>
                                            <select name="options[<?php echo htmlspecialchars($option_name); ?>]" 
                                                    id="option-<?php echo sanitize_id($option_name); ?>" 
                                                    class="form-select">
                                                <?php foreach($option_values as $value): ?>
                                                    <option value="<?php echo htmlspecialchars($value); ?>">
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 d-md-block">
                                <button type="submit" class="btn btn-primary btn-lg me-md-2">
                                    <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                </button>
                                
                                <a href="/store/cart.php" class="btn btn-outline-primary btn-lg me-md-2">
                                    <i class="fas fa-shopping-cart me-2"></i> View Cart
                                </a>
                                
                                <div id="paypal-button-container" class="mt-3"></div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary">
                            <p class="mb-0">This item is currently out of stock. Please check back later.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($related_products)): ?>
            <!-- Related Products Section -->
            <section class="related-products mt-5">
                <h2 class="mb-4">Related Products</h2>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                    <?php foreach($related_products as $related_product): ?>
                        <div class="col">
                            <?php echo generate_product_card($related_product); ?>
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
                    <?php foreach($recently_viewed as $viewed_product): ?>
                        <div class="col">
                            <?php echo generate_product_card($viewed_product); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- PayPal JS SDK -->
    <?php if (!empty($paypal_settings['client_id']) && $product && $product['inventory'] > 0): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo htmlspecialchars($paypal_settings['client_id']); ?>&currency=<?php echo htmlspecialchars($paypal_settings['currency']); ?>"></script>
    <?php endif; ?>
    
    <!-- Custom JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toast notification for successful form submission
        <?php if (isset($_SESSION['cart_message'])): ?>
            showToast('<?php echo $_SESSION['cart_message']['title']; ?>', 
                     '<?php echo $_SESSION['cart_message']['message']; ?>', 
                     '<?php echo $_SESSION['cart_message']['type']; ?>');
            <?php unset($_SESSION['cart_message']); ?>
        <?php endif; ?>
        
        // Handle form submission via AJAX for better UX
        const addToCartForm = document.getElementById('add-to-cart-form');
        if (addToCartForm) {
            addToCartForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(addToCartForm);
                
                fetch('/store/cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update cart count in navbar
                        if (document.getElementById('cart-count')) {
                            document.getElementById('cart-count').textContent = data.cart_count;
                        }
                        
                        // Show success message
                        showToast('Added to Cart', data.message, 'success');
                    } else {
                        showToast('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error adding to cart:', error);
                    showToast('Error', 'There was a problem adding this item to your cart.', 'error');
                });
            });
        }
        
        // Initialize PayPal button
        if (typeof paypal !== 'undefined') {
            
            paypal.Buttons({
                style: {
                    layout: 'horizontal',
                    color: 'gold',
                    shape: 'pill',
                    label: 'pay'
                },
                createOrder: function(data, actions) {
                    // Get form data for product options
                    const formData = new FormData(document.getElementById('add-to-cart-form'));
                    const quantity = parseInt(formData.get('quantity')) || 1;
                    
                    // Get product price from our PHP function (handles sales pricing)
                    const productPrice = <?php echo get_actual_price($product); ?>;
                    const amount = productPrice * quantity;
                    
                    // Collect options if any
                    let description = '<?php echo addslashes($product['title']); ?>';
                    const options = {};
                    
                    for (const pair of formData.entries()) {
                        if (pair[0].startsWith('options[')) {
                            const optionName = pair[0].replace('options[', '').replace(']', '');
                            options[optionName] = pair[1];
                        }
                    }
                    
                    // Add options to description if any
                    if (Object.keys(options).length > 0) {
                        description += ' - ' + Object.entries(options)
                            .map(([key, value]) => `${key}: ${value}`)
                            .join(', ');
                    }
                    
                    return actions.order.create({
                        purchase_units: [{
                            description: description,
                            amount: {
                                value: amount.toFixed(2)
                            }
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        // Send order to server
                        const formData = new FormData(document.getElementById('add-to-cart-form'));
                        formData.append('action', 'paypal_purchase');
                        formData.append('paypal_order_id', data.orderID);
                        formData.append('paypal_details', JSON.stringify(details));
                        
                        fetch('/checkout/process.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success message
                                showToast('Order Complete', 'Thank you for your purchase! Your transaction has been completed.', 'success');
                                
                                // Redirect to thank you page
                                setTimeout(function() {
                                    window.location.href = '/checkout/thank-you.php?order_id=' + data.order_id;
                                }, 2000);
                            } else {
                                showToast('Order Error', data.message || 'There was a problem processing your order.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error processing order:', error);
                            showToast('Order Error', 'There was a problem processing your order.', 'error');
                        });
                    });
                },
                onError: function(err) {
                    console.error('PayPal error:', err);
                    showToast('Payment Error', 'There was an error processing your payment. Please try again.', 'error');
                },
                onCancel: function() {
                    showToast('Payment Cancelled', 'Your payment was cancelled. You can continue shopping.', 'warning');
                }
            }).render('#paypal-button-container');
        }
        <?php endif; ?>
        
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
                        <div class="mt-2 pt-2 border-top">
                            <a href="/store/cart.php" class="btn btn-primary btn-sm">View Cart</a>
                            <a href="/checkout/" class="btn btn-success btn-sm">Checkout</a>
                        </div>
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
    });
    </script>
</body>
</html>