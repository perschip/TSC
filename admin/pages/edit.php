<?php
// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Initialize variables
$errors = [];
$title = '';
$slug = '';
$content = '';
$meta_description = '';
$status = 'published';

// Make sure upload directory exists
$upload_dir = '../../uploads/pages/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if the pages table exists and has the right columns
try {
    // Check if pages table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'pages'");
    if ($tableCheck->rowCount() == 0) {
        // Create pages table with all required columns
        $createTable = "CREATE TABLE IF NOT EXISTS `pages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `slug` varchar(255) NOT NULL,
            `content` text NOT NULL,
            `meta_description` varchar(255) DEFAULT NULL,
            `featured_image` varchar(255) DEFAULT NULL,
            `status` enum('published','draft') NOT NULL DEFAULT 'published',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($createTable);
    } else {
        // Table exists, check if featured_image column exists
        $columnCheck = $pdo->query("SHOW COLUMNS FROM pages LIKE 'featured_image'");
        if ($columnCheck->rowCount() == 0) {
            // Add featured_image column
            $pdo->exec("ALTER TABLE pages ADD COLUMN featured_image varchar(255) DEFAULT NULL AFTER meta_description");
        }
    }
} catch (PDOException $e) {
    $errors[] = 'Database setup error: ' . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title']);
    $slug = isset($_POST['slug']) && !empty($_POST['slug']) ? createSlug($_POST['slug']) : createSlug($title);
    $content = $_POST['content'];
    $meta_description = trim($_POST['meta_description']);
    $status = $_POST['status'];
    $featured_image = null; // Default to null instead of empty string
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = 'Page title is required';
    }
    
    if (empty($content)) {
        $errors[] = 'Page content is required';
    }
    
    if (empty($meta_description)) {
        // Use excerpt as meta description if empty
        $meta_description = generateExcerpt($content, 160);
    }

    // Check for duplicate slug
    $check_query = "SELECT COUNT(*) as count FROM pages WHERE slug = :slug";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->bindParam(':slug', $slug);
    $check_stmt->execute();
    $row = $check_stmt->fetch();
    
    if ($row['count'] > 0) {
        // Add unique identifier to make slug unique
        $slug = $slug . '-' . date('mdY');
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
            $new_file_name = uniqid('page_') . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // Move the file to the uploads directory
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $featured_image = '/uploads/pages/' . $new_file_name;
            } else {
                $errors[] = 'Failed to upload image';
            }
        } else {
            $errors[] = 'Only JPG, JPEG, PNG, and GIF files are allowed';
        }
    }
    
    // If no errors, create the page
    if (empty($errors)) {
        try {
            // Check if the featured_image column exists before including it in the query
            $columnCheck = $pdo->query("SHOW COLUMNS FROM pages LIKE 'featured_image'");
            if ($columnCheck->rowCount() > 0) {
                // Insert with featured_image
                $query = "INSERT INTO pages (title, slug, content, meta_description, featured_image, status, created_at, updated_at) 
                          VALUES (:title, :slug, :content, :meta_description, :featured_image, :status, NOW(), NOW())";
                
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':featured_image', $featured_image);
            } else {
                // Insert without featured_image
                $query = "INSERT INTO pages (title, slug, content, meta_description, status, created_at, updated_at) 
                          VALUES (:title, :slug, :content, :meta_description, :status, NOW(), NOW())";
                
                $stmt = $pdo->prepare($query);
            }
            
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':slug', $slug);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':meta_description', $meta_description);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            // Get the ID of the inserted page
            $page_id = $pdo->lastInsertId();
            
            // Add to navigation if checkbox was checked
            if (isset($_POST['add_to_navigation']) && $_POST['add_to_navigation'] == '1') {
                // Check if navigation table exists
                $nav_check = $pdo->query("SHOW TABLES LIKE 'navigation'");
                if ($nav_check->rowCount() > 0) {
                    // Get highest display_order
                    $order_query = "SELECT MAX(display_order) as max_order FROM navigation";
                    $order_stmt = $pdo->prepare($order_query);
                    $order_stmt->execute();
                    $order_result = $order_stmt->fetch();
                    $display_order = ($order_result && isset($order_result['max_order'])) ? $order_result['max_order'] + 1 : 5;
                    
                    // Get location preference
                    $location = isset($_POST['nav_location']) ? $_POST['nav_location'] : 'header';
                    
                    // Add to navigation table
                    $nav_query = "INSERT INTO navigation (title, url, page_id, display_order, location, is_active) 
                                VALUES (:title, :url, :page_id, :display_order, :location, 1)";
                    $nav_stmt = $pdo->prepare($nav_query);
                    $nav_stmt->bindParam(':title', $title);
                    $nav_stmt->bindParam(':url', $slug);
                    $nav_stmt->bindParam(':page_id', $page_id);
                    $nav_stmt->bindParam(':display_order', $display_order);
                    $nav_stmt->bindParam(':location', $location);
                    $nav_stmt->execute();
                }
            }
            
            // Redirect to the page list with success message
            $_SESSION['success_message'] = 'Page created successfully!';
            header('Location: list.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Page variables
$page_title = 'Create Page';
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
    const previewContainer = document.getElementById("image-preview-container");
    const file = event.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
                previewContainer.classList.remove("d-none");
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

// Toggle navigation location options
document.getElementById("add_to_navigation").addEventListener("change", function() {
    const navLocationOptions = document.querySelector(".nav-location-options");
    if (this.checked) {
        navLocationOptions.style.display = "block";
    } else {
        navLocationOptions.style.display = "none";
    }
});

// Initial state
document.addEventListener("DOMContentLoaded", function() {
    const addToNav = document.getElementById("add_to_navigation");
    const navLocationOptions = document.querySelector(".nav-location-options");
    
    if (addToNav && navLocationOptions) {
        navLocationOptions.style.display = addToNav.checked ? "block" : "none";
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
        <form action="edit.php?id=<?php echo $page_id; ?>" method="post">
            <div class="row">
                <div class="col-md-8">
                    <!-- Main Content Fields -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Page Title *</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($page_data['title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" class="form-control" id="slug" name="slug" value="<?php echo htmlspecialchars($page_data['slug'] ?? ''); ?>">
                        <div class="form-text">Leave empty to generate from title. Current URL: <a href="/<?php echo htmlspecialchars($page_data['slug'] ?? ''); ?>" target="_blank">/<?php echo htmlspecialchars($page_data['slug'] ?? ''); ?></a></div>
                    </div>
                                        
                    <div class="mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea class="form-control editor" id="content" name="content" rows="12"><?php echo htmlspecialchars($page_data['content'] ?? ''); ?></textarea>
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
                                    <option value="published" <?php echo ($page_data['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo ($page_data['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Page</button>
                                <a href="/<?php echo htmlspecialchars($page_data['slug'] ?? ''); ?>" target="_blank" class="btn btn-outline-secondary">
                                    <i class="fas fa-eye me-1"></i> View Page
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Navigation Options -->
                    <div class="mb-3">
                        <?php
                        // Check if page is in navigation
                        $in_nav_query = "SELECT * FROM navigation WHERE page_id = :page_id LIMIT 1";
                        $in_nav_stmt = $pdo->prepare($in_nav_query);
                        $in_nav_stmt->bindParam(':page_id', $post_id);
                        $in_nav_stmt->execute();
                        $nav_item = $in_nav_stmt->fetch();
                        $in_navigation = $nav_item ? true : false;
                        $nav_location = $in_navigation ? $nav_item['location'] : 'header';
                        ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="add_to_navigation" name="add_to_navigation" value="1" <?php echo $in_navigation ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="add_to_navigation">
                                <?php echo $in_navigation ? 'Keep in Navigation Menu' : 'Add to Navigation Menu'; ?>
                            </label>
                        </div>
                        
                        <div class="mt-2 ps-4 nav-location-options">
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="nav_location_header" name="nav_location" value="header" <?php echo $nav_location === 'header' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="nav_location_header">Header Only</label>
                            </div>
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="nav_location_footer" name="nav_location" value="footer" <?php echo $nav_location === 'footer' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="nav_location_footer">Footer Only</label>
                            </div>
                            <div class="form-check">
                                <input type="radio" class="form-check-input" id="nav_location_both" name="nav_location" value="both" <?php echo $nav_location === 'both' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="nav_location_both">Both Header & Footer</label>
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
                                <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($page_data['meta_description'] ?? ''); ?></textarea>
                                <div class="form-text">Recommended length: 150-160 characters.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Page Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Created:</strong> <?php echo date('F j, Y', strtotime($page_data['created_at'] ?? 'now')); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($page_data['updated_at'] ?? 'now')); ?></p>
                            <p><strong>Page ID:</strong> <?php echo $page_id; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
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
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>