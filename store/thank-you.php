<?php
// store/thank-you.php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : '';

// Page title
$page_title = 'Thank You for Your Order';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 56px;
            background-color: #f8f9fa;
        }
        
        .navbar-brand {
            font-weight: 700;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .footer {
            background-color: var(--secondary-color);
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
        
        .thank-you-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/">Tristate Cards</a>
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
                    <a href="/store/cart.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-shopping-cart"></i> Cart <span id="cart-count" class="badge bg-danger">0</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Thank You Section -->
    <section class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-check-circle thank-you-icon"></i>
                        <h1 class="mb-4">Thank You for Your Order!</h1>
                        
                        <p class="lead mb-4">Your order has been successfully placed and is being processed.</p>
                        
                        <?php if (!empty($order_id)): ?>
                            <p class="mb-4">Order Reference: <strong><?php echo $order_id; ?></strong></p>
                        <?php endif; ?>
                        
                        <p>A confirmation email will be sent to you shortly with the details of your purchase.</p>
                        
                        <div class="mt-5">
                            <a href="/store/" class="btn btn-primary">Continue Shopping</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
    
    <!-- Custom JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Clear cart after successful purchase
        localStorage.setItem('tristateCart', JSON.stringify([]));
        
        // Update cart count
        document.getElementById('cart-count').textContent = '0';
    });
    </script>
</body>
</html>
