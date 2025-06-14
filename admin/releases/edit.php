<?php
// Product Release Calendar - Edit Release
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get release ID
$release_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($release_id <= 0) {
    $_SESSION['error_message'] = 'Invalid release ID';
    header('Location: index.php');
    exit;
}

// Get release data
try {
    $query = "SELECT * FROM product_releases WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $release_id]);
    $release = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$release) {
        $_SESSION['error_message'] = 'Release not found';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error fetching release: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $release_date = trim($_POST['release_date'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $product_url = trim($_POST['product_url'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validate form data
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    
    if (empty($release_date)) {
        $errors[] = 'Release date is required';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $release_date)) {
        $errors[] = 'Release date must be in YYYY-MM-DD format';
    }
    
    // If no errors, update the release
    if (empty($errors)) {
        try {
            $query = "UPDATE product_releases SET 
                      title = :title, 
                      description = :description, 
                      release_date = :release_date, 
                      image_url = :image_url, 
                      product_url = :product_url, 
                      category = :category, 
                      is_featured = :is_featured
                      WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':release_date' => $release_date,
                ':image_url' => $image_url,
                ':product_url' => $product_url,
                ':category' => $category,
                ':is_featured' => $is_featured,
                ':id' => $release_id
            ]);
            
            $_SESSION['success_message'] = 'Product release updated successfully!';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get categories from the product_releases table for the dropdown
try {
    $categories = [];
    $cat_query = "SELECT DISTINCT category FROM product_releases WHERE category IS NOT NULL AND category != '' ORDER BY category";
    $cat_stmt = $pdo->query($cat_query);
    while ($row = $cat_stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['category'];
    }
} catch (PDOException $e) {
    $categories = [];
}

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Product Release</h1>
        <a href="index.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 me-1"></i> Back to Releases
        </a>
    </div>
    
    <!-- Content Row -->
    <div class="row">
        <div class="col-12">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Release Details</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="edit.php?id=<?php echo $release_id; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? $release['title']); ?>" required>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="release_date" class="form-label">Release Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="release_date" name="release_date" value="<?php echo htmlspecialchars($_POST['release_date'] ?? $release['release_date']); ?>" required>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($_POST['category'] ?? $release['category']); ?>" list="category-list">
                                    <datalist id="category-list">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text">Enter an existing category or create a new one</div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="image_url" class="form-label">Image URL</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="image_url" name="image_url" value="<?php echo htmlspecialchars($_POST['image_url'] ?? $release['image_url']); ?>">
                                        <button class="btn btn-outline-secondary" type="button" id="upload-image-btn">Browse</button>
                                    </div>
                                    <div class="form-text">Enter a URL or upload an image</div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="product_url" class="form-label">Product URL</label>
                                    <input type="url" class="form-control" id="product_url" name="product_url" value="<?php echo htmlspecialchars($_POST['product_url'] ?? $release['product_url']); ?>">
                                    <div class="form-text">Link to the product page or where to buy</div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" 
                                        <?php echo (isset($_POST['is_featured']) ? $_POST['is_featured'] : $release['is_featured']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_featured">
                                        Feature this release (will be highlighted on the calendar)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="10"><?php echo htmlspecialchars($_POST['description'] ?? $release['description']); ?></textarea>
                                </div>
                                
                                <div class="image-preview-container mt-3 text-center">
                                    <div id="image-preview">
                                        <?php if (!empty($_POST['image_url'] ?? $release['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($_POST['image_url'] ?? $release['image_url']); ?>" alt="Preview" class="img-fluid img-thumbnail" style="max-height: 200px;">
                                        <?php else: ?>
                                            <div class="no-image-placeholder">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                                <p class="mt-2">Image preview will appear here</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Release
                            </button>
                            <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Image preview functionality
document.getElementById('image_url').addEventListener('input', function() {
    updateImagePreview(this.value);
});

function updateImagePreview(url) {
    const previewContainer = document.getElementById('image-preview');
    
    if (url && url.trim() !== '') {
        previewContainer.innerHTML = `<img src="${url}" alt="Preview" class="img-fluid img-thumbnail" style="max-height: 200px;">`;
    } else {
        previewContainer.innerHTML = `
            <div class="no-image-placeholder">
                <i class="fas fa-image fa-3x text-muted"></i>
                <p class="mt-2">Image preview will appear here</p>
            </div>
        `;
    }
}

// Leverage the existing eBay image uploader
document.getElementById('upload-image-btn').addEventListener('click', function() {
    // Open the eBay image uploader in a new window
    window.open('../ebay/image_uploader.php', 'imageUploader', 'width=800,height=600');
});

// Function to receive the image URL from the uploader
window.receiveImageUrl = function(url) {
    document.getElementById('image_url').value = url;
    updateImagePreview(url);
};
</script>

<style>
.no-image-placeholder {
    border: 2px dashed #ddd;
    border-radius: 5px;
    padding: 30px;
    background-color: #f8f9fc;
}
</style>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
