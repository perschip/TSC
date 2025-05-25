<?php
// admin/navigation/categories.php - New file for managing categories

// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Ensure user is an admin
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delete category
    if (isset($_POST['action']) && $_POST['action'] === 'delete_category') {
        $category_name = trim($_POST['category_name']);
        $replacement_category = trim($_POST['replacement_category']);
        
        if (empty($category_name)) {
            $_SESSION['error_message'] = 'Category name is required.';
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Update all navigation items with this category to use the replacement
                $update_query = "UPDATE navigation SET category = :replacement WHERE category = :current";
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->bindParam(':replacement', $replacement_category);
                $update_stmt->bindParam(':current', $category_name);
                $update_stmt->execute();
                
                // Commit transaction
                $pdo->commit();
                
                $_SESSION['success_message'] = 'Category "' . htmlspecialchars($category_name) . '" deleted and items moved to "' . htmlspecialchars($replacement_category) . '"';
                header('Location: categories.php');
                exit;
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
            }
        }
    }
    
    // Handle rename category
    if (isset($_POST['action']) && $_POST['action'] === 'rename_category') {
        $old_name = trim($_POST['old_name']);
        $new_name = trim($_POST['new_name']);
        
        if (empty($old_name) || empty($new_name)) {
            $_SESSION['error_message'] = 'Both current and new category names are required.';
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Update all navigation items with this category
                $update_query = "UPDATE navigation SET category = :new_name WHERE category = :old_name";
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->bindParam(':new_name', $new_name);
                $update_stmt->bindParam(':old_name', $old_name);
                $update_stmt->execute();
                
                // Commit transaction
                $pdo->commit();
                
                $_SESSION['success_message'] = 'Category renamed from "' . htmlspecialchars($old_name) . '" to "' . htmlspecialchars($new_name) . '"';
                header('Location: categories.php');
                exit;
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get all categories and count items
try {
    $categories_query = "SELECT category, COUNT(*) as count 
                      FROM navigation 
                      GROUP BY category 
                      ORDER BY CASE WHEN category = 'Main' THEN 0 ELSE 1 END, category";
    $categories_stmt = $pdo->prepare($categories_query);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
}

// Page variables
$page_title = 'Manage Categories';

// Header actions
$header_actions = '
<a href="manage.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to Navigation
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Categories List -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Navigation Categories</h6>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="alert alert-info">
                        No categories found. Add categories by creating navigation items.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Category Name</th>
                                    <th style="width: 100px;">Items</th>
                                    <th style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category']); ?></td>
                                        <td class="text-center"><?php echo $category['count']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary rename-btn" 
                                                    data-name="<?php echo htmlspecialchars($category['category']); ?>"
                                                    data-bs-toggle="modal" data-bs-target="#renameModal">
                                                <i class="fas fa-edit"></i> Rename
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger delete-btn" 
                                                    data-name="<?php echo htmlspecialchars($category['category']); ?>"
                                                    data-count="<?php echo $category['count']; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
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
        <!-- Help Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Category Management Help</h6>
            </div>
            <div class="card-body">
                <h5>Managing Categories</h5>
                <p>Categories help organize your navigation links, especially in the footer. Here's how to manage them:</p>
                
                <h6 class="mt-3 mb-2">Available Actions:</h6>
                <ul>
                    <li><strong>Rename Category:</strong> Change a category name across all navigation items</li>
                    <li><strong>Delete Category:</strong> Remove a category and move its items to another category</li>
                </ul>
                
                <h6 class="mt-3 mb-2">Tips:</h6>
                <ul>
                    <li>The "Main" category is recommended for primary navigation items</li>
                    <li>Keep category names short and descriptive</li>
                    <li>Use consistent naming conventions</li>
                    <li>Create new categories when adding navigation items</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i> Changes affect how links are organized in your website footer.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rename Category Modal -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rename Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="rename_category">
                    <input type="hidden" name="old_name" id="old_category_name">
                    
                    <div class="mb-3">
                        <label for="current_name" class="form-label">Current Name</label>
                        <input type="text" class="form-control" id="current_name" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_name" class="form-label">New Category Name *</label>
                        <input type="text" class="form-control" id="new_name" name="new_name" required>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> This will update all navigation items currently using this category.
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
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_name" id="delete_category_name">
                    
                    <p>Are you sure you want to delete the category <strong id="display_category_name"></strong>?</p>
                    
                    <div id="item_count_warning" class="mb-3">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> This category contains <span id="item_count"></span> navigation items. Please select a replacement category:
                        </div>
                        
                        <div class="mb-3">
                            <label for="replacement_category" class="form-label">Move items to:</label>
                            <select class="form-select" id="replacement_category" name="replacement_category" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['category']); ?>"><?php echo htmlspecialchars($category['category']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle rename button clicks
    const renameButtons = document.querySelectorAll('.rename-btn');
    renameButtons.forEach(button => {
        button.addEventListener('click', function() {
            const categoryName = this.dataset.name;
            document.getElementById('old_category_name').value = categoryName;
            document.getElementById('current_name').value = categoryName;
            document.getElementById('new_name').value = categoryName;
        });
    });
    
    // Handle delete button clicks
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const categoryName = this.dataset.name;
            const itemCount = parseInt(this.dataset.count, 10);
            
            document.getElementById('delete_category_name').value = categoryName;
            document.getElementById('display_category_name').textContent = categoryName;
            document.getElementById('item_count').textContent = itemCount;
            
            // Update replacement dropdown to exclude current category
            const replacementSelect = document.getElementById('replacement_category');
            for (let i = 0; i < replacementSelect.options.length; i++) {
                if (replacementSelect.options[i].value === categoryName) {
                    replacementSelect.options[i].disabled = true;
                } else {
                    replacementSelect.options[i].disabled = false;
                }
            }
            
            // Set first enabled option as selected
            for (let i = 0; i < replacementSelect.options.length; i++) {
                if (!replacementSelect.options[i].disabled) {
                    replacementSelect.selectedIndex = i;
                    break;
                }
            }
            
            // Show/hide item count warning based on count
            const itemCountWarning = document.getElementById('item_count_warning');
            if (itemCount > 0) {
                itemCountWarning.style.display = 'block';
            } else {
                itemCountWarning.style.display = 'none';
            }
        });
    });
});
</script>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>