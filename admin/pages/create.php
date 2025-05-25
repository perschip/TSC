<?php
// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Log access for debugging
error_log("Pages create.php accessed at " . date('Y-m-d H:i:s'));

// Initialize variables
$errors = [];
$title = '';
$slug = '';
$content = '';
$meta_description = '';
$status = 'published';
$featured_image = '';

// Check if the pages table exists
try {
    // Check if table exists
    $tableExists = false;
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('pages', $tables)) {
        $tableExists = true;
    }
    
    // Create table if it doesn't exist
    if (!$tableExists) {
        error_log("Creating pages table");
        $sql = "CREATE TABLE `pages` (
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
        
        $pdo->exec($sql);
        error_log("Pages table created successfully");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errors[] = "Database error: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Form submitted via POST");
    
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'published';
    
    error_log("Title: " . $title);
    error_log("Content length: " . strlen($content));
    error_log("Status: " . $status);
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = 'Page title is required';
        error_log("Validation error: Title is required");
    }
    
    if (empty($content)) {
        $errors[] = 'Page content is required';
        error_log("Validation error: Content is required");
    }
    
    // If no validation errors, proceed
    if (empty($errors)) {
        // Generate slug from title if not provided
        $slug = isset($_POST['slug']) && !empty($_POST['slug']) ? createSlug($_POST['slug']) : createSlug($title);
        error_log("Generated slug: " . $slug);
        
        // Generate meta description if not provided
        $meta_description = !empty($_POST['meta_description']) ? trim($_POST['meta_description']) : generateExcerpt($content, 160);
        
        // Check for duplicate slug
        try {
            $check_query = "SELECT COUNT(*) as count FROM pages WHERE slug = :slug";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->bindParam(':slug', $slug);
            $check_stmt->execute();
            $row = $check_stmt->fetch();
            
            if ($row['count'] > 0) {
                // Add unique identifier to make slug unique
                $slug = $slug . '-' . date('mdY');
                error_log("Modified slug for uniqueness: " . $slug);
            }
        } catch (PDOException $e) {
            error_log("Error checking for duplicate slug: " . $e->getMessage());
            $errors[] = "Error checking for duplicate slug: " . $e->getMessage();
        }
        
        // If still no errors, insert the page
        if (empty($errors)) {
            try {
                error_log("Attempting to create page");
                
                // Very basic insert query - stripped down to essentials
                $query = "INSERT INTO pages (title, slug, content, meta_description, status, created_at, updated_at) 
                          VALUES (:title, :slug, :content, :meta_description, :status, NOW(), NOW())";
                
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':slug', $slug);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':meta_description', $meta_description);
                $stmt->bindParam(':status', $status);
                
                error_log("Executing INSERT query");
                $stmt->execute();
                
                $page_id = $pdo->lastInsertId();
                error_log("Page created successfully with ID: " . $page_id);
                
                // Add to navigation menu if requested
                if (isset($_POST['add_to_navigation']) && $_POST['add_to_navigation'] == '1') {
                    try {
                        $location = isset($_POST['nav_location']) ? $_POST['nav_location'] : 'header';
                        error_log("Adding page to navigation menu. Location: " . $location);
                        
                        // Check if navigation table exists
                        if (in_array('navigation', $tables)) {
                            // Get highest display order
                            $order_query = "SELECT MAX(display_order) as max_order FROM navigation";
                            $order_stmt = $pdo->prepare($order_query);
                            $order_stmt->execute();
                            $order_result = $order_stmt->fetch();
                            $display_order = ($order_result && isset($order_result['max_order'])) ? $order_result['max_order'] + 1 : 5;
                            
                            // Add to navigation
                            $nav_query = "INSERT INTO navigation (title, url, page_id, display_order, location, is_active) 
                                          VALUES (:title, :url, :page_id, :display_order, :location, 1)";
                            $nav_stmt = $pdo->prepare($nav_query);
                            $nav_stmt->bindParam(':title', $title);
                            $nav_stmt->bindParam(':url', $slug);
                            $nav_stmt->bindParam(':page_id', $page_id);
                            $nav_stmt->bindParam(':display_order', $display_order);
                            $nav_stmt->bindParam(':location', $location);
                            $nav_stmt->execute();
                            
                            error_log("Page added to navigation menu");
                        } else {
                            error_log("Navigation table doesn't exist");
                        }
                    } catch (PDOException $e) {
                        error_log("Error adding page to navigation: " . $e->getMessage());
                        // Don't stop the process if navigation fails
                    }
                }
                
                // Set success message and redirect
                $_SESSION['success_message'] = 'Page created successfully!';
                error_log("Redirecting to list.php");
                header('Location: list.php');
                exit;
                
            } catch (PDOException $e) {
                error_log("Error creating page: " . $e->getMessage());
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Page variables
$page_title = 'Create Page';
$use_tinymce = false; // Don't use TinyMCE for now to simplify

// Header actions
$header_actions = '
<a href="list.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to List
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<!-- Simplified form based on direct-submit.php -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Create New Page</h6>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="create.php" method="post">
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
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="add_to_navigation" name="add_to_navigation" value="1" checked>
                                    <label class="form-check-label" for="add_to_navigation">Add to Navigation Menu</label>
                                </div>
                                
                                <div class="mt-2 ps-4 nav-location-options">
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" id="nav_location_header" name="nav_location" value="header" checked>
                                        <label class="form-check-label" for="nav_location_header">Header Only</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" id="nav_location_footer" name="nav_location" value="footer">
                                        <label class="form-check-label" for="nav_location_footer">Footer Only</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" id="nav_location_both" name="nav_location" value="both">
                                        <label class="form-check-label" for="nav_location_both">Both Header & Footer</label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Create Page</button>
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
                                <div class="form-text">If left empty, an excerpt will be generated automatically.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>


<script>
// Toggle navigation options based on checkbox
document.addEventListener('DOMContentLoaded', function() {
    const navCheckbox = document.getElementById('add_to_navigation');
    const navOptions = document.querySelector('.nav-location-options');
    
    function toggleNavOptions() {
        if (navCheckbox.checked) {
            navOptions.style.display = 'block';
        } else {
            navOptions.style.display = 'none';
        }
    }
    
    // Set initial state
    toggleNavOptions();
    
    // Add event listener
    navCheckbox.addEventListener('change', toggleNavOptions);
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>