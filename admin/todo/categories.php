<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Page variables
$page_title = 'Manage Task Categories';

// Check if tables exist, redirect to setup if they don't
function checkTablesExist($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'todo_categories'");
        $categoriesExist = $stmt->rowCount() > 0;
        
        if (!$categoriesExist) {
            header('Location: /admin/todo/setup_database.php');
            exit;
        }
    } catch (PDOException $e) {
        // Log error and redirect to setup
        error_log('Error checking todo tables: ' . $e->getMessage());
        header('Location: /admin/todo/setup_database.php');
        exit;
    }
}

// Get all categories
function getCategories($pdo) {
    try {
        $stmt = $pdo->query("SELECT c.*, COUNT(t.id) as task_count 
                            FROM todo_categories c
                            LEFT JOIN todo_items t ON c.id = t.category_id
                            GROUP BY c.id
                            ORDER BY c.sort_order, c.name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error fetching categories: ' . $e->getMessage());
        return [];
    }
}

// Get category by ID
function getCategoryById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM todo_categories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error fetching category: ' . $e->getMessage());
        return null;
    }
}

// Check if tables exist
checkTablesExist($pdo);

// Handle form submissions
$message = '';
$messageType = '';

// Handle category addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    try {
        // Validate inputs
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $color = isset($_POST['color']) ? trim($_POST['color']) : '#6c757d';
        $icon = isset($_POST['icon']) ? trim($_POST['icon']) : 'fas fa-tasks';
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        
        // Basic validation
        if (empty($name)) {
            throw new Exception('Category name is required');
        }
        
        // Prepare SQL statement
        $sql = "INSERT INTO todo_categories (name, color, icon, sort_order) 
                VALUES (:name, :color, :icon, :sort_order)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':color' => $color,
            ':icon' => $icon,
            ':sort_order' => $sortOrder
        ]);
        
        $message = 'Category added successfully!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error adding category: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle category update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    try {
        // Validate inputs
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $color = isset($_POST['color']) ? trim($_POST['color']) : '#6c757d';
        $icon = isset($_POST['icon']) ? trim($_POST['icon']) : 'fas fa-tasks';
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        
        // Basic validation
        if (empty($name)) {
            throw new Exception('Category name is required');
        }
        
        if ($id <= 0) {
            throw new Exception('Invalid category ID');
        }
        
        // Prepare SQL statement
        $sql = "UPDATE todo_categories 
                SET name = :name, color = :color, icon = :icon, sort_order = :sort_order 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':color' => $color,
            ':icon' => $icon,
            ':sort_order' => $sortOrder,
            ':id' => $id
        ]);
        
        $message = 'Category updated successfully!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error updating category: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        // Check if category has tasks
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM todo_items WHERE category_id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception('Cannot delete category with existing tasks. Please move or delete the tasks first.');
        }
        
        // Delete category
        $stmt = $pdo->prepare("DELETE FROM todo_categories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        $message = 'Category deleted successfully!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error deleting category: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get categories
$categories = getCategories($pdo);

// Get category for editing if ID is provided
$editCategory = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editCategory = getCategoryById($pdo, $editId);
}

// Header actions
$header_actions = '
<div class="btn-toolbar mb-2 mb-md-0">
    <a href="/admin/todo/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Tasks
    </a>
</div>
';

// Include admin header
include_once '../includes/header.php';
?>

<div class="row">
    <!-- Category Form -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold"><?php echo $editCategory ? 'Edit Category' : 'Add New Category'; ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="post">
                    <?php if ($editCategory): ?>
                    <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="color" class="form-label">Color</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="color" name="color" value="<?php echo $editCategory ? htmlspecialchars($editCategory['color']) : '#6c757d'; ?>">
                            <input type="text" class="form-control" id="color_text" value="<?php echo $editCategory ? htmlspecialchars($editCategory['color']) : '#6c757d'; ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="icon" class="form-label">Icon</label>
                        <div class="input-group">
                            <span class="input-group-text"><i id="icon_preview" class="<?php echo $editCategory ? htmlspecialchars($editCategory['icon']) : 'fas fa-tasks'; ?>"></i></span>
                            <input type="text" class="form-control" id="icon" name="icon" value="<?php echo $editCategory ? htmlspecialchars($editCategory['icon']) : 'fas fa-tasks'; ?>">
                        </div>
                        <div class="form-text">Use Font Awesome icon classes (e.g., fas fa-tasks, fab fa-ebay)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo $editCategory ? (int)$editCategory['sort_order'] : 0; ?>" min="0">
                        <div class="form-text">Lower numbers appear first</div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <?php if ($editCategory): ?>
                        <a href="/admin/todo/categories.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" name="update_category" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Category
                        </button>
                        <?php else: ?>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Reset
                        </button>
                        <button type="submit" name="add_category" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add Category
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Common Icons Reference -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Common Icons</h6>
            </div>
            <div class="card-body">
                <div class="row row-cols-4 g-2 text-center">
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-tasks">
                            <i class="fas fa-tasks"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-clipboard-list">
                            <i class="fas fa-clipboard-list"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-list-check">
                            <i class="fas fa-list-check"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-check-square">
                            <i class="fas fa-check-square"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fab fa-ebay">
                            <i class="fab fa-ebay"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-tag">
                            <i class="fas fa-tag"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-shopping-cart">
                            <i class="fas fa-shopping-cart"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-truck">
                            <i class="fas fa-truck"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-video">
                            <i class="fas fa-video"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-camera">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-box">
                            <i class="fas fa-box"></i>
                        </button>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-outline-secondary icon-btn" data-icon="fas fa-credit-card">
                            <i class="fas fa-credit-card"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Categories List -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Categories</h6>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No categories found. Add your first category using the form.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%">ID</th>
                                <th style="width: 15%">Color</th>
                                <th style="width: 30%">Name</th>
                                <th style="width: 15%">Icon</th>
                                <th style="width: 10%">Order</th>
                                <th style="width: 10%">Tasks</th>
                                <th style="width: 15%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="color-swatch me-2" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></div>
                                        <small><?php echo htmlspecialchars($category['color']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($category['color']); ?>">
                                        <i class="<?php echo htmlspecialchars($category['icon']); ?> me-1"></i>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </span>
                                </td>
                                <td><i class="<?php echo htmlspecialchars($category['icon']); ?>"></i> <?php echo htmlspecialchars($category['icon']); ?></td>
                                <td><?php echo $category['sort_order']; ?></td>
                                <td>
                                    <a href="/admin/todo/index.php?category=<?php echo $category['id']; ?>" class="badge bg-primary">
                                        <?php echo $category['task_count']; ?> tasks
                                    </a>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/admin/todo/categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $category['id']; ?>" <?php echo $category['task_count'] > 0 ? 'disabled' : ''; ?>>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $category['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $category['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $category['id']; ?>">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete the category: <strong><?php echo htmlspecialchars($category['name']); ?></strong>?
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <form method="post">
                                                        <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                        <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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
</div>

<?php
// Extra styles
$extra_head = '
<style>
.color-swatch {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}
.icon-btn {
    width: 100%;
    height: 40px;
    margin-bottom: 8px;
}
</style>
';

// Extra scripts
$extra_scripts = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Update color text when color picker changes
        const colorPicker = document.getElementById("color");
        const colorText = document.getElementById("color_text");
        
        if (colorPicker && colorText) {
            colorPicker.addEventListener("input", function() {
                colorText.value = this.value;
            });
        }
        
        // Update icon preview when icon input changes
        const iconInput = document.getElementById("icon");
        const iconPreview = document.getElementById("icon_preview");
        
        if (iconInput && iconPreview) {
            iconInput.addEventListener("input", function() {
                iconPreview.className = this.value;
            });
        }
        
        // Icon buttons functionality
        const iconButtons = document.querySelectorAll(".icon-btn");
        iconButtons.forEach(button => {
            button.addEventListener("click", function() {
                const icon = this.getAttribute("data-icon");
                if (iconInput && iconPreview) {
                    iconInput.value = icon;
                    iconPreview.className = icon;
                }
            });
        });
    });
</script>
';

// Include admin footer
include_once '../includes/footer.php';
?>
