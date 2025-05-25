<?php
// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Make sure upload directory exists
$upload_dir = '../../uploads/blog/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Initialize variables
$errors = [];
$title = '';
$content = '';
$excerpt = '';
$meta_description = '';
$status = 'published';
$featured_image = '';
$post_categories = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $excerpt = trim($_POST['excerpt'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $status = $_POST['status'] ?? 'published';
    $featured_image = '';
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = 'Post title is required';
    }
    
    if (empty($content)) {
        $errors[] = 'Post content is required';
    }
    
    if (empty($excerpt)) {
        // Generate excerpt from content if empty
        $excerpt = generateExcerpt($content, 160);
    }
    
    if (empty($meta_description)) {
        // Use excerpt as meta description if empty
        $meta_description = $excerpt;
    }

    // Create slug from title
    $slug = createSlug($title);
    
    // Check if a file was uploaded
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['featured_image']['name'];
        $file_tmp = $_FILES['featured_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check if the file type is allowed
        if (in_array($file_ext, $allowed)) {
            // Generate unique filename
            $new_file_name = uniqid('post_') . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // Move the file to the uploads directory
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $featured_image = '/uploads/blog/' . $new_file_name;
            } else {
                $errors[] = 'Failed to upload image';
            }
        } else {
            $errors[] = 'Only JPG, JPEG, PNG, and GIF files are allowed';
        }
    }
    
    // Check for duplicate slug
    $check_query = "SELECT COUNT(*) as count FROM blog_posts WHERE slug = :slug";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->bindParam(':slug', $slug);
    $check_stmt->execute();
    $row = $check_stmt->fetch();
    
    if ($row['count'] > 0) {
        // Add unique identifier to make slug unique
        $slug = $slug . '-' . date('mdY');
    }
    
    // If no errors, create the post using simpler approach
    if (empty($errors)) {
        try {
            // Use transaction for database consistency
            $pdo->beginTransaction();
            
            // Insert the post with basic information
            $query = "INSERT INTO blog_posts (title, slug, content, excerpt, meta_description, featured_image, status, created_at, updated_at) 
                      VALUES (:title, :slug, :content, :excerpt, :meta_description, :featured_image, :status, NOW(), NOW())";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':slug', $slug);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':excerpt', $excerpt);
            $stmt->bindParam(':meta_description', $meta_description);
            $stmt->bindParam(':featured_image', $featured_image);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            // Get the ID of the inserted post
            $post_id = $pdo->lastInsertId();
            
            // Process categories if provided
            if (isset($_POST['categories']) && is_array($_POST['categories'])) {
                foreach ($_POST['categories'] as $category_id) {
                    $cat_query = "INSERT INTO blog_post_categories (post_id, category_id) VALUES (:post_id, :category_id)";
                    $cat_stmt = $pdo->prepare($cat_query);
                    $cat_stmt->bindParam(':post_id', $post_id);
                    $cat_stmt->bindParam(':category_id', $category_id);
                    $cat_stmt->execute();
                }
            }
            
            // Process tags if provided
            if (!empty($_POST['tags'])) {
                $tags = explode(',', $_POST['tags']);
                
                foreach ($tags as $tag_name) {
                    $tag_name = trim($tag_name);
                    
                    if (!empty($tag_name)) {
                        // Check if tag exists
                        $tag_check_query = "SELECT id FROM blog_tags WHERE name = :name";
                        $tag_check_stmt = $pdo->prepare($tag_check_query);
                        $tag_check_stmt->bindParam(':name', $tag_name);
                        $tag_check_stmt->execute();
                        $tag = $tag_check_stmt->fetch();
                        
                        if ($tag) {
                            $tag_id = $tag['id'];
                        } else {
                            // Create a new tag
                            $tag_slug = createSlug($tag_name);
                            $tag_insert_query = "INSERT INTO blog_tags (name, slug) VALUES (:name, :slug)";
                            $tag_insert_stmt = $pdo->prepare($tag_insert_query);
                            $tag_insert_stmt->bindParam(':name', $tag_name);
                            $tag_insert_stmt->bindParam(':slug', $tag_slug);
                            $tag_insert_stmt->execute();
                            $tag_id = $pdo->lastInsertId();
                        }
                        
                        // Associate tag with post
                        $post_tag_query = "INSERT INTO blog_post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)";
                        $post_tag_stmt = $pdo->prepare($post_tag_query);
                        $post_tag_stmt->bindParam(':post_id', $post_id);
                        $post_tag_stmt->bindParam(':tag_id', $tag_id);
                        $post_tag_stmt->execute();
                    }
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message and redirect
            $_SESSION['success_message'] = 'Blog post created successfully!';
            header('Location: list.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log('Create post error: ' . $e->getMessage());
        }
    }
}

// Get all categories
try {
    $categories_query = "SELECT id, name FROM blog_categories ORDER BY name ASC";
    $categories_stmt = $pdo->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    error_log('Error fetching categories: ' . $e->getMessage());
}

// Page variables
$page_title = 'Create Blog Post';
$use_tinymce = true;

// Header actions
$header_actions = '
<a href="list.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to List
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="create.php" method="post" enctype="multipart/form-data" id="blogPostForm">
            <div class="row">
                <div class="col-md-8">
                    <!-- Main Content Fields -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content *</label>
                        <textarea class="form-control" id="content" name="content" rows="15" required><?php echo htmlspecialchars($content); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="excerpt" class="form-label">Excerpt</label>
                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($excerpt); ?></textarea>
                        <div class="form-text">A short summary of the post. If left empty, it will be generated from the content.</div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Sidebar Fields -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Publishing</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Publish Post</button>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Featured Image</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="featured_image" class="form-label">Upload Image</label>
                                <input class="form-control" type="file" id="featured_image" name="featured_image">
                                <div class="form-text">Recommended size: 1200x630 pixels</div>
                            </div>
                            <div id="imagePreviewContainer" class="mt-2 <?php echo empty($featured_image) ? 'd-none' : ''; ?>">
                                <img id="imagePreview" src="<?php echo htmlspecialchars($featured_image); ?>" alt="Image Preview" class="img-fluid">
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Categories</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($categories) > 0): ?>
                                <div class="mb-3">
                                    <?php foreach ($categories as $category): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category-<?php echo $category['id']; ?>" <?php echo (isset($_POST['categories']) && is_array($_POST['categories']) && in_array($category['id'], $_POST['categories'])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="category-<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="fas fa-plus-circle me-1"></i> Add New Category
                                    </button>
                                    <a href="settings.php?tab=categories" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-cog me-1"></i> Manage Categories
                                    </a>
                                </div>
                            <?php else: ?>
                                <p>No categories found.</p>
                                <div class="d-flex">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="fas fa-plus-circle me-1"></i> Create Category
                                    </button>
                                    <a href="settings.php?tab=categories" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-cog me-1"></i> Manage Categories
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tags</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="tags" name="tags" value="" placeholder="Enter tags separated by commas">
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">SEO Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="meta_description" class="form-label">Meta Description</label>
                                <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($meta_description); ?></textarea>
                                <div class="form-text">If left empty, the excerpt will be used. Recommended length: 150-160 characters.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="category_name" class="form-label">Category Name</label>
                    <input type="text" class="form-control" id="category_name" placeholder="Enter category name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCategory">Save Category</button>
            </div>
        </div>
    </div>
</div>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>

<script>
// Image preview
document.getElementById('featured_image').addEventListener('change', function(event) {
    var preview = document.getElementById('imagePreview');
    var previewContainer = document.getElementById('imagePreviewContainer');
    var file = event.target.files[0];
    
    if (file) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.classList.remove('d-none');
        };
        
        reader.readAsDataURL(file);
    } else {
        previewContainer.classList.add('d-none');
    }
});

// Save Category
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal from Bootstrap properly
    var categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    
    document.getElementById('saveCategory').addEventListener('click', function() {
        var categoryName = document.getElementById('category_name').value.trim();
        
        if (categoryName) {
            // Send AJAX request to save category
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'ajax_add_category.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Add the new category to the list
                            var categoriesContainer = document.querySelector('.card-body .mb-3');
                            var newCategory = document.createElement('div');
                            newCategory.className = 'form-check';
                            newCategory.innerHTML =
                                '<input class="form-check-input" type="checkbox" name="categories[]" value="' + response.id + '" id="category-' + response.id + '" checked>' +
                                '<label class="form-check-label" for="category-' + response.id + '">' +
                                categoryName +
                                '</label>';
                            categoriesContainer.appendChild(newCategory);
                            
                            // Close modal and clear input
                            document.getElementById('category_name').value = '';
                            
                            // Hide the modal
                            categoryModal.hide();
                            
                            // Show success message
                            alert('Category "' + categoryName + '" added successfully!');
                        } else {
                            alert(response.message || 'Failed to add category');
                        }
                    } catch (e) {
                        console.error('JSON parsing error:', e);
                        console.log('Raw response:', xhr.responseText);
                        alert('Error processing server response');
                    }
                } else {
                    alert('Error processing request');
                }
            };
            xhr.onerror = function() {
                console.error('Network error occurred');
                alert('Network error. Please check your connection.');
            };
            xhr.send('name=' + encodeURIComponent(categoryName));
        } else {
            alert('Please enter a category name');
        }
    });
});

// Make sure form is submitted even if TinyMCE fails to load
document.addEventListener('DOMContentLoaded', function() {
    // Just to make sure we have a simple backup submit handler
    document.getElementById('blogPostForm').addEventListener('submit', function(e) {
        // Check if content is empty
        var contentField = document.getElementById('content');
        if (!contentField.value.trim()) {
            e.preventDefault();
            alert('Content is required');
        }
    });
});
</script>