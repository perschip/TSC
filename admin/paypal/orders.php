<?php
// admin/paypal/orders.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Process status update if submitted
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $status = $_POST['status'];
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    
    if (in_array($status, $valid_statuses)) {
        try {
            // Update the order status
            $stmt = $pdo->prepare("UPDATE orders SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $order_id]);
            
            // Add notes if provided
            if (!empty($notes)) {
                $stmt = $pdo->prepare("UPDATE orders SET notes = CONCAT(IFNULL(notes, ''), :notes) WHERE id = :id");
                $stmt->execute([':notes' => "\n" . date('Y-m-d H:i:s') . " - Status changed to {$status}: {$notes}", ':id' => $order_id]);
            }
            
            $_SESSION['success_message'] = "Order #{$order_id} status updated to {$status}";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Error updating order: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Invalid status provided';
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle order deletion (for testing purposes)
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    
    try {
        // Delete order items first (foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = :id");
        $stmt->execute([':id' => $order_id]);
        
        // Then delete the order
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = :id");
        $stmt->execute([':id' => $order_id]);
        
        $_SESSION['success_message'] = "Order #{$order_id} has been deleted";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting order: ' . $e->getMessage();
    }
    
    // Redirect to prevent resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check if orders table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error checking database tables: ' . $e->getMessage();
}

// Get orders if table exists
$orders = [];
if ($table_exists) {
    try {
        // Get orders with pagination
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // Get total count for pagination
        $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
        $total_orders = $stmt->fetchColumn();
        $total_pages = ceil($total_orders / $per_page);
        
        // Get orders for current page
        $stmt = $pdo->prepare("SELECT * FROM orders ORDER BY created_at DESC LIMIT :offset, :per_page");
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error fetching orders: ' . $e->getMessage();
    }
}

// Set page title
$page_title = 'PayPal Orders';

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <?php if (!$table_exists): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i> Database Setup Required</h5>
                    <p>The PayPal integration requires database tables that have not been set up yet.</p>
                    <p>Please visit the <a href="settings.php">PayPal Settings</a> page to set up the database.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-shopping-cart me-2"></i> Orders</h5>
                        <div>
                            <a href="products.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-box me-1"></i> Manage Products
                            </a>
                            <a href="settings.php" class="btn btn-sm btn-outline-secondary ms-2">
                                <i class="fas fa-cog me-1"></i> Settings
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Order Ref</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <p class="text-muted mb-0">No orders found. Orders will appear here once customers make purchases.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $order['id']; ?>">
                                                    <?php echo htmlspecialchars($order['order_reference']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo match($order['status']) {
                                                        'pending' => 'bg-warning',
                                                        'processing' => 'bg-info',
                                                        'shipped' => 'bg-primary',
                                                        'delivered' => 'bg-success',
                                                        'cancelled' => 'bg-danger',
                                                        'refunded' => 'bg-secondary',
                                                        default => 'bg-light text-dark'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo match($order['payment_status']) {
                                                        'pending' => 'bg-warning',
                                                        'completed' => 'bg-success',
                                                        'refunded' => 'bg-secondary',
                                                        'failed' => 'bg-danger',
                                                        default => 'bg-light text-dark'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#orderModal<?php echo $order['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $order['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="?delete=1&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (!empty($orders) && $total_pages > 1): ?>
                        <!-- Pagination -->
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($orders)): ?>
    <!-- Order Detail Modals -->
    <?php foreach ($orders as $order): ?>
        <!-- View Order Modal -->
        <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="orderModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderModalLabel<?php echo $order['id']; ?>">
                            Order Details: <?php echo htmlspecialchars($order['order_reference']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Order Information</h6>
                                <p class="mb-1"><strong>Order Reference:</strong> <?php echo htmlspecialchars($order['order_reference']); ?></p>
                                <p class="mb-1"><strong>PayPal Order ID:</strong> <?php echo htmlspecialchars($order['paypal_order_id']); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                                <p class="mb-1">
                                    <strong>Status:</strong> 
                                    <span class="badge <?php 
                                        echo match($order['status']) {
                                            'pending' => 'bg-warning',
                                            'processing' => 'bg-info',
                                            'shipped' => 'bg-primary',
                                            'delivered' => 'bg-success',
                                            'cancelled' => 'bg-danger',
                                            'refunded' => 'bg-secondary',
                                            default => 'bg-light text-dark'
                                        };
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                    </span>
                                </p>
                                <p class="mb-1">
                                    <strong>Payment Status:</strong>
                                    <span class="badge <?php 
                                        echo match($order['payment_status']) {
                                            'pending' => 'bg-warning',
                                            'completed' => 'bg-success',
                                            'refunded' => 'bg-secondary',
                                            'failed' => 'bg-danger',
                                            default => 'bg-light text-dark'
                                        };
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Customer Information</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                <?php if (!empty($order['shipping_address'])): 
                                    $address = json_decode($order['shipping_address'], true);
                                    if ($address): ?>
                                    <h6 class="fw-bold mt-3">Shipping Address</h6>
                                    <p class="mb-1">
                                        <?php echo htmlspecialchars($address['address_line_1'] ?? ''); ?><br>
                                        <?php if (!empty($address['address_line_2'])): ?>
                                            <?php echo htmlspecialchars($address['address_line_2']); ?><br>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars(($address['admin_area_2'] ?? '') . ', ' . ($address['admin_area_1'] ?? '') . ' ' . ($address['postal_code'] ?? '')); ?><br>
                                        <?php echo htmlspecialchars($address['country_code'] ?? ''); ?>
                                    </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <h6 class="fw-bold">Order Items</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Fetch order items
                                    try {
                                        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
                                        $stmt->execute([':order_id' => $order['id']]);
                                        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (empty($items)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No items found for this order.</td>
                                            </tr>
                                        <?php else: 
                                            foreach ($items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['product_title']); ?></td>
                                                <td><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td>$<?php echo number_format($item['total_price'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; 
                                        endif;
                                    } catch (PDOException $e) {
                                        echo '<tr><td colspan="5" class="text-danger">Error loading order items: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                    }
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                        <td>$<?php echo number_format($order['total_amount'] - $order['shipping_cost'] - $order['tax_amount'], 2); ?></td>
                                    </tr>
                                    <?php if ($order['shipping_cost'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Shipping:</strong></td>
                                        <td>$<?php echo number_format($order['shipping_cost'], 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($order['tax_amount'] > 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Tax:</strong></td>
                                        <td>$<?php echo number_format($order['tax_amount'], 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                        <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <?php if (!empty($order['notes'])): ?>
                        <div class="mt-3">
                            <h6 class="fw-bold">Order Notes</h6>
                            <pre class="border rounded p-2" style="white-space: pre-wrap;"><?php echo htmlspecialchars($order['notes']); ?></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $order['id']; ?>" data-bs-dismiss="modal">
                            Update Status
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Update Order Status Modal -->
        <div class="modal fade" id="updateModal<?php echo $order['id']; ?>" tabindex="-1" aria-labelledby="updateModalLabel<?php echo $order['id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateModalLabel<?php echo $order['id']; ?>">
                            Update Order Status: <?php echo htmlspecialchars($order['order_reference']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                        <div class="modal-body">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            
                            <div class="mb-3">
                                <label for="status<?php echo $order['id']; ?>" class="form-label">Order Status</label>
                                <select class="form-select" id="status<?php echo $order['id']; ?>" name="status" required>
                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="refunded" <?php echo $order['status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes<?php echo $order['id']; ?>" class="form-label">Add Notes</label>
                                <textarea class="form-control" id="notes<?php echo $order['id']; ?>" name="notes" rows="3" placeholder="Add any notes about this status change (optional)"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
