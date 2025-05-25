<?php
// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if post ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid post ID';
    header('Location: list.php');
    exit;
}

$post_id = (int)$_GET['id'];

// Get post data
try {
    $query = "SELECT * FROM blog_posts WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $post_id);
    $stmt->execute();
    $post = $stmt->fetch();
    
    if (!$post) {
        $_SESSION['error_message'] = 'Post not found';
        header('Location: list.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header('Location: list.php');
    exit;
}

// Get post categories
try {
    $cat_query = "SELECT c.id FROM blog_categories c 
                  JOIN blog_post_categories pc ON c.id = pc.category_id 
                  WHERE pc.post_id = :post_id";
    $cat_stmt = $pdo->prepare($cat_query);
    $cat_stmt->bindParam(':post_id', $post_id);
    $cat_stmt->execute();
    $post_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $post_categories = [];
}

// Get post tags
try {
    $tag_query = "SELECT t.name FROM blog_tags t 
                  JOIN blog_post_tags pt ON t.id = pt.tag_id 
                  WHERE pt.post_id = :post_id";
    $tag_stmt = $pdo->prepare($tag_query);
    $tag_stmt->bindParam(':post_id', $post_id);
    $tag_stmt->execute();
    $tag_names = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);
    $post_tags = implode(', ', $tag_names);
} catch (PDOException $e) {
    $post_tags = '';
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title']);
    $slug = isset($_POST['slug']) && !empty($_POST['slug']) ? createSlug($_POST['slug']) : createSlug($title);
    $content = $_POST['content'];
    $excerpt = trim($_POST['excerpt']);
    $meta_description = trim($_POST['meta_description']);
    $status = $_POST['status'];
    $featured_image = $post['featured_image']; // Default to existing
    
    // Validate inputs
    $errors = [];
    
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
            $upload_path = '../../uploads/blog/' . $new_file_name;
            
            // Create directory if it doesn't exist
            if (!file_exists('../../uploads/blog/')) {
                mkdir('../../uploads/blog/', 0777, true);
            }
            
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
    if ($slug !== $post['slug']) {
        $check_query = "SELECT COUNT(*) as count FROM blog_posts WHERE slug = :slug AND id != :id";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->bindParam(':slug', $slug);
        $check_stmt->bindParam(':id', $post_id);
        $check_stmt->execute();
        $row = $check_stmt->fetch();
        
        if ($row['count'] > 0) {
            // Add unique identifier to make slug unique
            $slug = $slug . '-' . date('mdY');
        }
    }
    
    // If no errors, update post
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update blog_posts table
            $query = "UPDATE blog_posts 
                      SET title = :title, 
                          slug = :slug, 
                          content = :content, 
                          excerpt = :excerpt, 
                          meta_description = :meta_description, 
                          featured_image = :featured_image,
                          status = :status,
                          updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':slug', $slug);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':excerpt', $excerpt);
            $stmt->bindParam(':meta_description', $meta_description);
            $stmt->bindParam(':featured_image', $featured_image);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $post_id);
            $stmt->execute();
            
            // Delete existing categories
            $delete_categories = "DELETE FROM blog_post_categories WHERE post_id = :post_id";
            $delete_stmt = $pdo->prepare($delete_categories);
            $delete_stmt->bindParam(':post_id', $post_id);
            $delete_stmt->execute();
            
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
            
            // Delete existing tags
            $delete_tags = "DELETE FROM blog_post_tags WHERE post_id = :post_id";
            $delete_stmt = $pdo->prepare($delete_tags);
            $delete_stmt->bindParam(':post_id', $post_id);
            $delete_stmt->execute();
            
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
            
            // Redirect to the blog post list with success message
            $_SESSION['success_message'] = 'Blog post updated successfully!';
            header('Location: list.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get all categories
$categories_query = "SELECT id, name FROM blog_categories ORDER BY name ASC";
$categories_stmt = $pdo->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll();

// Page variables
$page_title = 'Edit Blog Post';
$use_tinymce = true;
$extra_head = '<style>
    #featured-image-preview {
        max-width: 100%;
        height: auto;
        max-height: 200px;
        border-radius: 0.25rem;
    }
</style>';

$header_actions = '
<a href="list.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to List
</a>
';

$extra_scripts = '
<script>
// Image preview
document.getElementById("featured_image").addEventListener("change", function(event) {
    const preview = document.getElementById("featured-image-preview");
    const file = event.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
            } else {
                const newPreview = document.createElement("img");
                newPreview.src = e.target.result;
                newPreview.id = "featured-image-preview";
                newPreview.alt = "Featured Image";
                newPreview.classList.add("img-fluid");
                document.querySelector("#featured_image").parentNode.prepend(newPreview);
            }
        };
        
        reader.readAsDataURL(file);
    }
});

// Slug generator
document.getElementById("title").addEventListener("blur", function() {
    const slugField = document.getElementById("slug");
    if (slugField.value === "") {
        // Create a simple slug from the title
        slugField.value = this.value
            .toLowerCase()
            .replace(/[^\w\s-]/g, "")
            .replace(/\s+/g, "-")
            .replace(/-+/g, "-")
            .trim();
    }
});
</script>';

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
        <form action="edit.php?id=<?php echo $post_id; ?>" method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <!-- Main Content Fields -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" class="form-control" id="slug" name="slug" value="<?php echo htmlspecialchars($post['slug']); ?>">
                        <div class="form-text">Leave empty to generate from title. Current URL: <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>" target="_blank">/blog/<?php echo htmlspecialchars($post['slug']); ?></a></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content *</label>
                        <textarea class="form-control editor" id="content" name="content" rows="15" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="excerpt" class="form-label">Excerpt</label>
                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
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
                                    <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Post</button>
                                <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>" target="_blank" class="btn btn-outline-secondary">
                                    <i class="fas fa-eye me-1"></i> View Post
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Featured Image</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($post['featured_image'])): ?>
                                <div class="mb-3 text-center">
                                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="Featured Image" id="featured-image-preview">
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="featured_image" class="form-label">Upload New Image</label>
                                <input class="form-control" type="file" id="featured_image" name="featured_image">
                                <div class="form-text">Recommended size: 1200x630 pixels</div>
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
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category-<?php echo $category['id']; ?>" <?php echo in_array($category['id'], $post_categories) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="category-<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No categories found. <a href="../categories/create.php">Create one</a>.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tags</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="tags" name="tags" value="<?php echo htmlspecialchars($post_tags); ?>" placeholder="Enter tags separated by commas">
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
                                <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($post['meta_description']); ?></textarea>
                                <div class="form-text">If left empty, the excerpt will be used. Recommended length: 150-160 characters.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>