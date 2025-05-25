<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Initialize messages
$success_message = '';
$error_message = '';

// Handle tab switching
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'categories';

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = trim($_POST['category_name'] ?? '');
    
    if (empty($name)) {
        $error_message = 'Category name is required.';
    } else {
        $slug = createSlug($name);
        
        try {
            // Check if category already exists
            $check_query = "SELECT id FROM blog_categories WHERE name = :name OR slug = :slug LIMIT 1";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->bindParam(':name', $name);
            $check_stmt->bindParam(':slug', $slug);
            $check_stmt->execute();
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                $error_message = 'A category with this name already exists.';
            } else {
                // Insert new category
                $insert_query = "INSERT INTO blog_categories (name, slug) VALUES (:name, :slug)";
                $insert_stmt = $pdo->prepare($insert_query);
                $insert_stmt->bindParam(':name', $name);
                $insert_stmt->bindParam(':slug', $slug);
                $insert_stmt->execute();
                
                $success_message = 'Category added successfully!';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Edit category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $category_id = (int)$_POST['category_id'];
    $name = trim($_POST['category_name'] ?? '');
    
    if (empty($name)) {
        $error_message = 'Category name is required.';
    } else {
        $slug = createSlug($name);
        
        try {
            // Check if another category with this name exists
            $check_query = "SELECT id FROM blog_categories WHERE (name = :name OR slug = :slug) AND id != :id LIMIT 1";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->bindParam(':name', $name);
            $check_stmt->bindParam(':slug', $slug);
            $check_stmt->bindParam(':id', $category_id);
            $check_stmt->execute();
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                $error_message = 'Another category with this name already exists.';
            } else {
                // Update category
                $update_query = "UPDATE blog_categories SET name = :name, slug = :slug WHERE id = :id";
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->bindParam(':name', $name);
                $update_stmt->bindParam(':slug', $slug);
                $update_stmt->bindParam(':id', $category_id);
                $update_stmt->execute();
                
                $success_message = 'Category updated successfully!';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Delete category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $category_id = (int)$_POST['category_id'];
    
    try {
        // First count how many posts use this category
        $count_query = "SELECT COUNT(*) FROM blog_post_categories WHERE category_id = :category_id";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->bindParam(':category_id', $category_id);
        $count_stmt->execute();
        $post_count = $count_stmt->fetchColumn();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // If posts are using this category, remove the association
        if ($post_count > 0) {
            $remove_assoc = "DELETE FROM blog_post_categories WHERE category_id = :category_id";
            $remove_stmt = $pdo->prepare($remove_assoc);
            $remove_stmt->bindParam(':category_id', $category_id);
            $remove_stmt->execute();
        }
        
        // Delete the category
        $delete_query = "DELETE FROM blog_categories WHERE id = :id";
        $delete_stmt = $pdo->prepare($delete_query);
        $delete_stmt->bindParam(':id', $category_id);
        $delete_stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = 'Category deleted successfully!';
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// =============================================
// TAGS TAB OPERATIONS
// =============================================

// Add new tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_tag') {
    $name = trim($_POST['tag_name'] ?? '');
    
    if (empty($name)) {
        $error_message = 'Tag name is required.';
    } else {
        $slug = createSlug($name);
        
        try {
            // Check if tag already exists
            $check_query = "SELECT id FROM blog_tags WHERE name = :name OR slug = :slug LIMIT 1";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->bindParam(':name', $name);
            $check_stmt->bindParam(':slug', $slug);
            $check_stmt->execute();
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                $error_message = 'A tag with this name already exists.';
            } else {
                // Insert new tag
                $insert_query = "INSERT INTO blog_tags (name, slug) VALUES (:name, :slug)";
                $insert_stmt = $pdo->prepare($insert_query);
                $insert_stmt->bindParam(':name', $name);
                $insert_stmt->bindParam(':slug', $slug);
                $insert_stmt->execute();
                
                $success_message = 'Tag added successfully!';
                $active_tab = 'tags';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Edit tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_tag') {
    $tag_id = (int)$_POST['tag_id'];
    $name = trim($_POST['tag_name'] ?? '');
    
    if (empty($name)) {
        $error_message = 'Tag name is required.';
    } else {
        $slug = createSlug($name);
        
        try {
            // Check if another tag with this name exists
            $check_query = "SELECT id FROM blog_tags WHERE (name = :name OR slug = :slug) AND id != :id LIMIT 1";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->bindParam(':name', $name);
            $check_stmt->bindParam(':slug', $slug);
            $check_stmt->bindParam(':id', $tag_id);
            $check_stmt->execute();
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                $error_message = 'Another tag with this name already exists.';
            } else {
                // Update tag
                $update_query = "UPDATE blog_tags SET name = :name, slug = :slug WHERE id = :id";
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->bindParam(':name', $name);
                $update_stmt->bindParam(':slug', $slug);
                $update_stmt->bindParam(':id', $tag_id);
                $update_stmt->execute();
                
                $success_message = 'Tag updated successfully!';
                $active_tab = 'tags';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Delete tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_tag') {
    $tag_id = (int)$_POST['tag_id'];
    
    try {
        // First count how many posts use this tag
        $count_query = "SELECT COUNT(*) FROM blog_post_tags WHERE tag_id = :tag_id";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->bindParam(':tag_id', $tag_id);
        $count_stmt->execute();
        $post_count = $count_stmt->fetchColumn();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // If posts are using this tag, remove the association
        if ($post_count > 0) {
            $remove_assoc = "DELETE FROM blog_post_tags WHERE tag_id = :tag_id";
            $remove_stmt = $pdo->prepare($remove_assoc);
            $remove_stmt->bindParam(':tag_id', $tag_id);
            $remove_stmt->execute();
        }
        
        // Delete the tag
        $delete_query = "DELETE FROM blog_tags WHERE id = :id";
        $delete_stmt = $pdo->prepare($delete_query);
        $delete_stmt->bindParam(':id', $tag_id);
        $delete_stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = 'Tag deleted successfully!';
        $active_tab = 'tags';
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// =============================================
// GENERAL SETTINGS OPERATIONS
// =============================================

// Update blog settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_blog_settings') {
    try {
        // Get form data
        $posts_per_page = (int)$_POST['posts_per_page'];
        $excerpt_length = (int)$_POST['excerpt_length'];
        $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
        $moderate_comments = isset($_POST['moderate_comments']) ? 1 : 0;
        $show_author = isset($_POST['show_author']) ? 1 : 0;
        
        // Validate
        if ($posts_per_page < 1) {
            $posts_per_page = 6; // Default
        }
        
        if ($excerpt_length < 1) {
            $excerpt_length = 160; // Default
        }
        
        // Update settings
        $settings = [
            'blog_posts_per_page' => $posts_per_page,
            'blog_excerpt_length' => $excerpt_length,
            'blog_allow_comments' => $allow_comments,
            'blog_moderate_comments' => $moderate_comments,
            'blog_show_author' => $show_author
        ];
        
        foreach ($settings as $key => $value) {
            updateSetting($key, $value);
        }
        
        $success_message = 'Blog settings updated successfully!';
        $active_tab = 'general';
    } catch (Exception $e) {
        $error_message = 'Error updating settings: ' . $e->getMessage();
    }
}

// Get all categories
try {
    $categories_query = "SELECT c.*, 
                         (SELECT COUNT(*) FROM blog_post_categories WHERE category_id = c.id) as post_count
                         FROM blog_categories c
                         ORDER BY c.name ASC";
    $categories_stmt = $pdo->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $error_message = 'Error fetching categories: ' . $e->getMessage();
}

// Get all tags
try {
    $tags_query = "SELECT t.*, 
                  (SELECT COUNT(*) FROM blog_post_tags WHERE tag_id = t.id) as post_count
                  FROM blog_tags t
                  ORDER BY t.name ASC";
    $tags_stmt = $pdo->prepare($tags_query);
    $tags_stmt->execute();
    $tags = $tags_stmt->fetchAll();
} catch (PDOException $e) {
    $tags = [];
    $error_message = 'Error fetching tags: ' . $e->getMessage();
}

// Get blog settings
$blog_settings = [
    'posts_per_page' => (int)getSetting('blog_posts_per_page', 6),
    'excerpt_length' => (int)getSetting('blog_excerpt_length', 160),
    'allow_comments' => (bool)getSetting('blog_allow_comments', 0),
    'moderate_comments' => (bool)getSetting('blog_moderate_comments', 1),
    'show_author' => (bool)getSetting('blog_show_author', 0)
];

// Page variables
$page_title = 'Blog Settings';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'categories';

// Header actions
$header_actions = '
<a href="list.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to Posts
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<div class="card dashboard-card mb-4">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'categories' ? 'active' : ''; ?>" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-content" type="button" role="tab" aria-controls="categories-content" aria-selected="<?php echo $active_tab === 'categories' ? 'true' : 'false'; ?>">
                    <i class="fas fa-tags me-1"></i> Categories
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'tags' ? 'active' : ''; ?>" id="tags-tab" data-bs-toggle="tab" data-bs-target="#tags-content" type="button" role="tab" aria-controls="tags-content" aria-selected="<?php echo $active_tab === 'tags' ? 'true' : 'false'; ?>">
                    <i class="fas fa-hashtag me-1"></i> Tags
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'general' ? 'active' : ''; ?>" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-content" type="button" role="tab" aria-controls="general-content" aria-selected="<?php echo $active_tab === 'general' ? 'true' : 'false'; ?>">
                    <i class="fas fa-cog me-1"></i> General Settings
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="tab-content">
            <!-- Categories Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'categories' ? 'show active' : ''; ?>" id="categories-content" role="tabpanel" aria-labelledby="categories-tab">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="mb-3">Add New Category</h5>
                        <form method="post" action="settings.php?tab=categories">
                            <input type="hidden" name="action" value="add_category">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" name="category_name" placeholder="Category name" required>
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-plus me-1"></i> Add Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <h5 class="mb-3">Existing Categories</h5>
                <?php if (count($categories) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Posts</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($category['slug']); ?></span></td>
                                        <td><?php echo $category['post_count']; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary edit-category-btn" 
                                                        data-id="<?php echo $category['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($category['name']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger delete-category-btn" 
                                                        data-id="<?php echo $category['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                        data-count="<?php echo $category['post_count']; ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No categories found. Add your first category above.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tags Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'tags' ? 'show active' : ''; ?>" id="tags-content" role="tabpanel" aria-labelledby="tags-tab">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="mb-3">Add New Tag</h5>
                        <form method="post" action="settings.php?tab=tags">
                            <input type="hidden" name="action" value="add_tag">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" name="tag_name" placeholder="Tag name" required>
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-plus me-1"></i> Add Tag
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <h5 class="mb-3">Existing Tags</h5>
                <?php if (count($tags) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Posts</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tags as $tag): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tag['name']); ?></td>
                                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($tag['slug']); ?></span></td>
                                        <td><?php echo $tag['post_count']; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary edit-tag-btn" 
                                                        data-id="<?php echo $tag['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($tag['name']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger delete-tag-btn" 
                                                        data-id="<?php echo $tag['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($tag['name']); ?>"
                                                        data-count="<?php echo $tag['post_count']; ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No tags found. Add your first tag above or create tags as you write posts.
                    </div>
                <?php endif; ?>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="mb-3">Tag Cloud Preview</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($tags as $tag): ?>
                                <span class="badge bg-primary px-2 py-1 fs-6">
                                    #<?php echo htmlspecialchars($tag['name']); ?>
                                </span>
                            <?php endforeach; ?>
                            
                            <?php if (count($tags) === 0): ?>
                                <p class="text-muted mb-0">No tags to display.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- General Settings Tab -->
            <div class="tab-pane fade <?php echo $active_tab === 'general' ? 'show active' : ''; ?>" id="general-content" role="tabpanel" aria-labelledby="general-tab">
                <form method="post" action="settings.php?tab=general">
                    <input type="hidden" name="action" value="update_blog_settings">
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Display Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="posts_per_page" class="form-label">Posts Per Page</label>
                                    <input type="number" class="form-control" id="posts_per_page" name="posts_per_page" 
                                           value="<?php echo $blog_settings['posts_per_page']; ?>" min="1" max="50">
                                    <div class="form-text">Number of posts to display on the blog listing page.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="excerpt_length" class="form-label">Excerpt Length</label>
                                    <input type="number" class="form-control" id="excerpt_length" name="excerpt_length" 
                                           value="<?php echo $blog_settings['excerpt_length']; ?>" min="50" max="500">
                                    <div class="form-text">Maximum length of automatically generated excerpts in characters.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="show_author" name="show_author" 
                                       <?php echo $blog_settings['show_author'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_author">Show Author Information</label>
                                <div class="form-text">Display author names and information on blog posts.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Comments Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="allow_comments" name="allow_comments" 
                                       <?php echo $blog_settings['allow_comments'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allow_comments">Enable Comments</label>
                                <div class="form-text">Allow visitors to leave comments on blog posts.</div>
                            </div>
                            
                            <div class="mb-3 form-check comments-setting" <?php echo !$blog_settings['allow_comments'] ? 'style="opacity: 0.6;"' : ''; ?>>
                                <input type="checkbox" class="form-check-input" id="moderate_comments" name="moderate_comments" 
                                       <?php echo $blog_settings['moderate_comments'] ? 'checked' : ''; ?> 
                                       <?php echo !$blog_settings['allow_comments'] ? 'disabled' : ''; ?>>
                                <label class="form-check-label" for="moderate_comments">Moderate Comments</label>
                                <div class="form-text">Require approval before comments are published.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Blog Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <h6>Total Posts</h6>
                                        <p class="mb-0">
                                            <?php
                                            try {
                                                $post_count = $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
                                                echo number_format($post_count);
                                            } catch (PDOException $e) {
                                                echo '0';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <h6>Published Posts</h6>
                                        <p class="mb-0">
                                            <?php
                                            try {
                                                $pub_count = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn();
                                                echo number_format($pub_count);
                                            } catch (PDOException $e) {
                                                echo '0';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="settings.php?tab=categories">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="settings.php?tab=categories">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <p>Are you sure you want to delete the category <strong id="delete_category_name"></strong>?</p>
                    <div id="delete_category_warning" class="alert alert-warning d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i> This category is currently used by <span id="delete_category_count"></span> posts. If you delete it, those posts will no longer be associated with this category.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modals
    var editCategoryModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    var deleteCategoryModal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
    
    // Edit Category buttons
    document.querySelectorAll('.edit-category-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            var categoryId = this.getAttribute('data-id');
            var categoryName = this.getAttribute('data-name');
            
            document.getElementById('edit_category_id').value = categoryId;
            document.getElementById('edit_category_name').value = categoryName;
            
            editCategoryModal.show();
        });
    });
    
    // Delete Category buttons
    document.querySelectorAll('.delete-category-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            var categoryId = this.getAttribute('data-id');
            var categoryName = this.getAttribute('data-name');
            var postCount = parseInt(this.getAttribute('data-count'), 10);
            
            document.getElementById('delete_category_id').value = categoryId;
            document.getElementById('delete_category_name').textContent = categoryName;
            
            var warningEl = document.getElementById('delete_category_warning');
            var countEl = document.getElementById('delete_category_count');
            
            if (postCount > 0) {
                countEl.textContent = postCount + ' ' + (postCount === 1 ? 'post' : 'posts');
                warningEl.classList.remove('d-none');
            } else {
                warningEl.classList.add('d-none');
            }
            
            deleteCategoryModal.show();
        });
    });
    
    // Activate tab based on URL hash
    var hash = window.location.hash;
    if (hash) {
        var tab = new bootstrap.Tab(document.querySelector('button[data-bs-target="' + hash + '"]'));
        tab.show();
    }
    
    // Update URL when tab changes
    var tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEls.forEach(function(tabEl) {
        tabEl.addEventListener('shown.bs.tab', function(event) {
            var hash = event.target.getAttribute('data-bs-target');
            if (history.pushState) {
                history.pushState(null, null, hash);
            } else {
                location.hash = hash;
            }
        });
    });
});
</script>