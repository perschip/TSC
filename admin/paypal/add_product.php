<?php
// admin/paypal/add_product.php
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    if (!$table_exists) {
        $_SESSION['error_message'] = 'Products table does not exist. Please set up the database first.';
    } else {
        try {
            // Process image upload if present
            $image_url = '';
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/products/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . basename($_FILES['product_image']['name']);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                    $image_url = '/uploads/products/' . $file_name;
                }
            }
            
            // Insert product into database
            $stmt = $pdo->prepare("INSERT INTO products (
                title, description, price, image_url, inventory, category, status, sku, weight, dimensions
            ) VALUES (
                :title, :description, :price, :image_url, :inventory, :category, :status, :sku, :weight, :dimensions
            )");
            
            $stmt->execute([
                ':title' => $_POST['title'],
                ':description' => $_POST['description'],
                ':price' => $_POST['price'],
                ':image_url' => $image_url,
                ':inventory' => $_POST['inventory'],
                ':category' => $_POST['category'],
                ':status' => $_POST['status'],
                ':sku' => $_POST['sku'],
                ':weight' => $_POST['weight'],
                ':dimensions' => $_POST['dimensions']
            ]);
            
            $_SESSION['success_message'] = 'Product added successfully!';
            header('Location: products.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Error adding product: ' . $e->getMessage();
        }
    }
}

// Set page title
$page_title = 'Add New Product';

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
                        <h5><i class="fas fa-plus me-2"></i> Add New Product</h5>
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Products
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="post" action="add_product.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_product">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Product Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="5"></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="price" class="form-label">Price ($) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="inventory" class="form-label">Inventory</label>
                                                <input type="number" min="0" class="form-control" id="inventory" name="inventory" value="1">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="category" class="form-label">Category</label>
                                                <input type="text" class="form-control" id="category" name="category">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="draft">Draft</option>
                                                    <option value="active">Active</option>
                                                    <option value="archived">Archived</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Product Image</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="product_image" class="form-label">Upload Image</label>
                                                <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                                            </div>
                                            <div id="image_preview" class="mt-3 text-center d-none">
                                                <img src="" alt="Product Preview" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Additional Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="sku" class="form-label">SKU</label>
                                                <input type="text" class="form-control" id="sku" name="sku">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="weight" class="form-label">Weight (lbs)</label>
                                                <input type="number" step="0.01" min="0" class="form-control" id="weight" name="weight" value="0">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="dimensions" class="form-label">Dimensions (L x W x H inches)</label>
                                                <input type="text" class="form-control" id="dimensions" name="dimensions" placeholder="e.g. 10 x 8 x 2">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 text-end">
                                <a href="products.php" class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Save Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview
    const imageInput = document.getElementById('product_image');
    const imagePreview = document.getElementById('image_preview');
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    imagePreview.querySelector('img').src = e.target.result;
                    imagePreview.classList.remove('d-none');
                }
                
                reader.readAsDataURL(this.files[0]);
            } else {
                imagePreview.classList.add('d-none');
            }
        });
    }
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
