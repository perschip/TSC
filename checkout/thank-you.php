<?php
// checkout/thank-you.php
// This file displays a thank you message after a successful order

require_once '../includes/db.php';
require_once '../includes/functions.php';
session_start();

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = null;

// Fetch order details if order ID is provided
if ($order_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM orders 
            WHERE id = :order_id
            LIMIT 1
        ");
        $stmt->execute([':order_id' => $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Fetch order items
        if ($order) {
            $stmt = $pdo->prepare("
                SELECT * FROM order_items
                WHERE order_id = :order_id
            ");
            $stmt->execute([':order_id' => $order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $order['items'] = $order_items;
        }
    } catch (PDOException $e) {
        error_log('Error fetching order details: ' . $e->getMessage());
    }
}

// Page title
$page_title = "Order Confirmation - Thank You";

// Additional CSS for this page
$extra_head = '
<style>
.thank-you-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.order-details {
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.order-items {
    margin-top: 1.5rem;
}

.order-items table {
    width: 100%;
}

.order-summary {
    margin-top: 1.5rem;
    text-align: right;
}

.thank-you-message {
    text-align: center;
    margin-bottom: 2rem;
}

.thank-you-icon {
    font-size: 4rem;
    color: #198754;
    margin-bottom: 1rem;
}

.continue-shopping {
    margin-top: 2rem;
    text-align: center;
}
</style>
';

// Include header
include_once '../includes/header.php';
?>

<div class="container thank-you-container">
    <div class="thank-you-message">
        <div class="thank-you-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Thank You for Your Order!</h1>
        <p class="lead">Your order has been received and is being processed.</p>
        <?php if ($order): ?>
            <p>Order #<?php echo $order_id; ?></p>
        <?php endif; ?>
    </div>
    
    <?php if ($order): ?>
    <div class="order-details">
        <h3>Order Details</h3>
        <div class="row">
            <div class="col-md-6">
                <h5>Shipping Information</h5>
                <p>
                    <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?><br>
                    <?php echo htmlspecialchars($order['address']); ?><br>
                    <?php echo htmlspecialchars($order['city'] . ', ' . $order['state'] . ' ' . $order['zip']); ?><br>
                    <?php echo htmlspecialchars($order['email']); ?><br>
                    <?php if (!empty($order['phone'])): ?>
                        <?php echo htmlspecialchars($order['phone']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-6">
                <h5>Order Summary</h5>
                <p>
                    <strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($order['order_date'])); ?><br>
                    <strong>Payment Method:</strong> <?php echo ucfirst(htmlspecialchars($order['payment_method'])); ?><br>
                    <strong>Order Status:</strong> <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                </p>
            </div>
        </div>
        
        <div class="order-items">
            <h5>Items Ordered</h5>
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($order['items']) && !empty($order['items'])): ?>
                        <?php foreach ($order['items'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td>$<?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No items found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="order-summary">
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-end">$<?php echo number_format($order['subtotal'], 2); ?></td>
                        </tr>
                        <?php if ($order['discount'] > 0): ?>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-end">-$<?php echo number_format($order['discount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Shipping:</td>
                            <td class="text-end">Free</td>
                        </tr>
                        <tr>
                            <th>Total:</th>
                            <th class="text-end">$<?php echo number_format($order['total'], 2); ?></th>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning">
        <p>Order details not found. If you believe this is an error, please contact customer support.</p>
    </div>
    <?php endif; ?>
    
    <div class="continue-shopping">
        <p>A confirmation email has been sent to your email address.</p>
        <a href="/" class="btn btn-primary">Continue Shopping</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clear the cart in localStorage
    localStorage.removeItem('tristateCart');
    
    // Track purchase event for analytics
    if (typeof gtag === 'function') {
        gtag('event', 'purchase', {
            'transaction_id': '<?php echo $order_id; ?>',
            'value': <?php echo $order ? $order['total'] : 0; ?>,
            'currency': 'USD'
        });
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>
