<?php
// admin/ebay/categories.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Create categories table if it doesn't exist
function createCategoriesTable($pdo) {
    $query = "CREATE TABLE IF NOT EXISTS ebay_categories (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL UNIQUE,
        `description` TEXT,
        `parent_id` INT DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_parent` (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    try {
        $pdo->exec($query);
        return true;
    } catch (PDOException $e) {
        error_log('Error creating categories table: ' . $e->getMessage());
        return false;
    }
}

// Create the table
createCategoriesTable($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                
                if (empty($name)) {
                    $_SESSION['error_message'] = 'Category name is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO ebay_categories (name, description, parent_id) VALUES (:name, :description, :parent_id)");
                        $stmt->execute([
                            ':name' => $name,
                            ':description' => $description,
                            ':parent_id' => $parent_id
                        ]);
                        
                        $_SESSION['success_message'] = 'Category added successfully!';
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Duplicate entry
                            $_SESSION['error_message'] = 'A category with this name already exists.';
                        } else {
                            $_SESSION['error_message'] = 'Error adding category: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'edit_category':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                
                if (empty($name)) {
                    $_SESSION['error_message'] = 'Category name is required.';
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE ebay_categories SET name = :name, description = :description, parent_id = :parent_id WHERE id = :id");
                        $stmt->execute([
                            ':name' => $name,
                            ':description' => $description,
                            ':parent_id' => $parent_id,
                            ':id' => $id
                        ]);
                        
                        $_SESSION['success_message'] = 'Category updated successfully!';
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Duplicate entry
                            $_SESSION['error_message'] = 'A category with this name already exists.';
                        } else {
                            $_SESSION['error_message'] = 'Error updating category: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'delete_category':
                $id = (int)$_POST['id'];
                
                try {
                    // Check if category is in use
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM ebay_listings WHERE category = (SELECT name FROM ebay_categories WHERE id = :id)");
                    $check_stmt->execute([':id' => $id]);
                    $count = $check_stmt->fetchColumn();
                    
                    if ($count > 0) {
                        $_SESSION['error_message'] = "Cannot delete category: it is used by $count listings.";
                    } else {
                        // Delete the category
                        $stmt = $pdo->prepare("DELETE FROM ebay_categories WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        
                        $_SESSION['success_message'] = 'Category deleted successfully!';
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error deleting category: ' . $e->getMessage();
                }
                break;
        }
        
        header('Location: categories.php');
        exit;
    }
}

// Get all categories
try {
    $stmt = $pdo->query("SELECT * FROM ebay_categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $_SESSION['error_message'] = 'Error fetching categories: ' . $e->getMessage();
}

// Page variables
$page_title = 'eBay Categories';

$header_actions = '
<a href="listings.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-list me-1"></i> Listings
</a>
<a href="settings.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-cog me-1"></i> Settings
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Categories Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">eBay Categories</h6>
                <div class="d-flex gap-2">
                    <a href="import_categories.php" class="btn btn-sm btn-success">
                        <i class="fas fa-file-import me-1"></i> Import Existing Categories
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus me-1"></i> Add Category
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-4">
                        <div class="text-muted mb-3"><i class="fas fa-tags fa-3x"></i></div>
                        <p>No categories found. Create your first category to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Listings</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <?php
                                    // Get listing count for this category
                                    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ebay_listings WHERE category = :category");
                                    $count_stmt->execute([':category' => $category['name']]);
                                    $listing_count = $count_stmt->fetchColumn();
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($listing_count > 0): ?>
                                                <a href="listings.php?category=<?php echo urlencode($category['name']); ?>" class="badge bg-primary">
                                                    <?php echo number_format($listing_count); ?> listings
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0 listings</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-category" 
                                                        data-id="<?php echo $category['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                        data-description="<?php echo htmlspecialchars($category['description'] ?? ''); ?>"
                                                        data-parent="<?php echo $category['parent_id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this category?');">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Category Tips</h6>
            </div>
            <div class="card-body">
                <p>Categories help you organize your eBay listings. Here are some tips:</p>
                <ul>
                    <li>Create categories for different types of cards (e.g., Baseball, Football)</li>
                    <li>Use categories for different players or teams</li>
                    <li>Create categories for special types (Autographs, Relics, etc.)</li>
                </ul>
                <p>You can assign categories to listings individually or in bulk from the listings page.</p>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="category_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="category_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="id" id="edit_category_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="edit_category_description" name="description" rows="3"></textarea>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit category button click
    const editButtons = document.querySelectorAll('.edit-category');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_category_description').value = description;
            
            // Show the modal
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        });
    });
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
