<?php
// Use absolute paths to avoid open_basedir restrictions
$site_root = $_SERVER['DOCUMENT_ROOT'];
require_once $site_root . '/includes/db.php';
require_once $site_root . '/includes/auth.php';
require_once $site_root . '/includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

// If form submitted, update product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    try {
        // Get form data
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $category = trim($_POST['category']);
        $status = $_POST['status'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        $inventory = (int)$_POST['inventory'];
        $sku = trim($_POST['sku']);
        $weight = (float)$_POST['weight'];
        $dimensions = trim($_POST['dimensions']);
        
        // Validate required fields
        if (empty($title)) {
            throw new Exception('Product title is required');
        }
        
        if ($price < 0) {
            throw new Exception('Price cannot be negative');
        }
        
        if ($inventory < 0) {
            throw new Exception('Inventory cannot be negative');
        }
        
        // Update product in database
        $stmt = $pdo->prepare("
            UPDATE products SET 
                title = :title,
                description = :description,
                price = :price,
                category = :category,
                status = :status,
                featured = :featured,
                inventory = :inventory,
                sku = :sku,
                weight = :weight,
                dimensions = :dimensions,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'category' => $category,
            'status' => $status,
            'featured' => $featured,
            'inventory' => $inventory,
            'sku' => $sku,
            'weight' => $weight,
            'dimensions' => $dimensions,
            'id' => $product_id
        ]);
        
        // Handle image upload if provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = $site_root . '/assets/images/products/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'product_' . $product_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Update product with new image URL
                $image_url = '/assets/images/products/' . $new_filename;
                $stmt = $pdo->prepare("UPDATE products SET image_url = :image_url WHERE id = :id");
                $stmt->execute(['image_url' => $image_url, 'id' => $product_id]);
            }
        }
        
        $_SESSION['success_message'] = 'Product updated successfully';
        header('Location: products.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error updating product: ' . $e->getMessage();
    }
}

// Get product data
if ($product_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $_SESSION['error_message'] = 'Product not found';
            header('Location: products.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error retrieving product: ' . $e->getMessage();
        header('Location: products.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = 'Invalid product ID';
    header('Location: products.php');
    exit;
}

// Get all categories for dropdown
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Page title
$page_title = 'Edit Product: ' . htmlspecialchars($product['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            background-color: #212529;
            min-height: 100vh;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
        }
        .sidebar .nav-link:hover {
            color: #fff;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        .product-image-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse p-0">
                <?php include $site_root . '/admin/includes/sidebar.php'; ?>
            </div>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                            echo $_SESSION['error_message']; 
                            unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label required-field">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($product['title']); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="price" class="form-label required-field">Price ($)</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="inventory" class="form-label required-field">Inventory</label>
                                    <input type="number" class="form-control" id="inventory" name="inventory" min="0" value="<?php echo htmlspecialchars($product['inventory']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" list="category-list" value="<?php echo htmlspecialchars($product['category']); ?>">
                                    <datalist id="category-list">
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="draft" <?php echo $product['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="archived" <?php echo $product['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="featured" class="form-label">Featured</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="featured" name="featured" <?php echo $product['featured'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="featured">
                                            Show on homepage
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="sku" class="form-label">SKU</label>
                                    <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="weight" class="form-label">Weight (lbs)</label>
                                    <input type="number" class="form-control" id="weight" name="weight" step="0.01" min="0" value="<?php echo htmlspecialchars($product['weight']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="dimensions" class="form-label">Dimensions</label>
                                    <input type="text" class="form-control" id="dimensions" name="dimensions" placeholder="L x W x H" value="<?php echo htmlspecialchars($product['dimensions']); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">Product Image</label>
                                <?php if (!empty($product['image_url'])): ?>
                                    <div>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="Product image" class="product-image-preview">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <div class="form-text">Leave empty to keep current image</div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
