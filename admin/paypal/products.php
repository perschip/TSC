<?php
// admin/paypal/products.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Check if products table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error checking database tables: ' . $e->getMessage();
}

// Get products if table exists
$products = [];
if ($table_exists) {
    try {
        $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error fetching products: ' . $e->getMessage();
    }
}

// Set page title
$page_title = 'PayPal Products';

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
                        <h5><i class="fas fa-box me-2"></i> Products</h5>
                        <div>
                            <a href="add_product.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i> Add New Product
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
                                        <th>ID</th>
                                        <th>Image</th>
                                        <th>Title</th>
                                        <th>Price</th>
                                        <th>Inventory</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <p class="text-muted mb-0">No products found. <a href="add_product.php">Add your first product</a>.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                                        <td>
                                            <?php if (!empty($product['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" width="50" height="50" class="img-thumbnail">
                                            <?php else: ?>
                                            <span class="badge bg-light text-dark">No Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['title']); ?></td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <?php if ($product['inventory'] > 10): ?>
                                            <span class="badge bg-success"><?php echo htmlspecialchars($product['inventory']); ?></span>
                                            <?php elseif ($product['inventory'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($product['inventory']); ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                                        <td>
                                            <?php if ($product['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                            <?php elseif ($product['status'] == 'draft'): ?>
                                            <span class="badge bg-secondary">Draft</span>
                                            <?php else: ?>
                                            <span class="badge bg-dark">Archived</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger delete-product" data-id="<?php echo $product['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProductModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this product? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteProductForm" method="post" action="delete_product.php">
                    <input type="hidden" name="product_id" id="delete_product_id" value="">
                    <button type="submit" class="btn btn-danger">Delete Product</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete product buttons
    const deleteButtons = document.querySelectorAll('.delete-product');
    const deleteProductIdInput = document.getElementById('delete_product_id');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            deleteProductIdInput.value = productId;
            deleteModal.show();
        });
    });
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
