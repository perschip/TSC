<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Page variables
$page_title = 'To-Do List';

// Check if tables exist, redirect to setup if they don't
function checkTablesExist($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'todo_categories'");
        $categoriesExist = $stmt->rowCount() > 0;
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'todo_items'");
        $itemsExist = $stmt->rowCount() > 0;
        
        if (!$categoriesExist || !$itemsExist) {
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
        $stmt = $pdo->query("SELECT * FROM todo_categories ORDER BY sort_order, name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error fetching categories: ' . $e->getMessage());
        return [];
    }
}

// Get all todo items, optionally filtered by category and status
function getTodoItems($pdo, $categoryId = null, $status = null) {
    try {
        $sql = "SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon 
                FROM todo_items t
                JOIN todo_categories c ON t.category_id = c.id";
        
        $params = [];
        $whereClauses = [];
        
        if ($categoryId !== null && $categoryId != 0) {
            $whereClauses[] = "t.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        
        if ($status !== null && $status != 'all') {
            $whereClauses[] = "t.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $sql .= " ORDER BY 
                  CASE t.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'in_progress' THEN 2 
                    WHEN 'completed' THEN 3 
                  END,
                  CASE t.priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                  END,
                  CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
                  t.due_date ASC,
                  t.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error fetching todo items: ' . $e->getMessage());
        return [];
    }
}

// Handle form submissions
$message = '';
$messageType = '';

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $itemId = $_POST['item_id'];
        $newStatus = $_POST['status'];
        
        $sql = "UPDATE todo_items SET status = :status";
        
        // If status is completed, set completed_at timestamp
        if ($newStatus === 'completed') {
            $sql .= ", completed_at = NOW()";
        } else {
            $sql .= ", completed_at = NULL";
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $itemId
        ]);
        
        $message = 'Task status updated successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error updating task status: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle task deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    try {
        $itemId = $_POST['item_id'];
        
        $stmt = $pdo->prepare("DELETE FROM todo_items WHERE id = :id");
        $stmt->execute([':id' => $itemId]);
        
        $message = 'Task deleted successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error deleting task: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check if tables exist
checkTablesExist($pdo);

// Get filter values
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get data
$categories = getCategories($pdo);
$todoItems = getTodoItems($pdo, $categoryFilter, $statusFilter !== 'all' ? $statusFilter : null);

// Header actions for adding new task
$header_actions = '
<div class="btn-toolbar mb-2 mb-md-0">
    <a href="/admin/todo/add.php" class="btn btn-sm btn-primary">
        <i class="fas fa-plus me-1"></i> Add Task
    </a>
    <a href="/admin/todo/categories.php" class="btn btn-sm btn-outline-secondary ms-2">
        <i class="fas fa-tags me-1"></i> Manage Categories
    </a>
</div>
';

// Include admin header
include_once '../includes/header.php';
?>

<!-- Filter Row -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category" onchange="this.form.submit()">
                            <option value="0" <?php echo $categoryFilter === 0 ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter === (int)$category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <a href="/admin/todo/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset Filters
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- To-Do List -->
<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">
                    <?php 
                    if ($categoryFilter !== 0) {
                        foreach ($categories as $category) {
                            if ($category['id'] == $categoryFilter) {
                                echo '<i class="' . htmlspecialchars($category['icon']) . ' me-2"></i> ';
                                echo htmlspecialchars($category['name']) . ' Tasks';
                                break;
                            }
                        }
                    } else {
                        echo '<i class="fas fa-tasks me-2"></i> All Tasks';
                    }
                    ?>
                </h6>
                <span class="badge bg-primary rounded-pill"><?php echo count($todoItems); ?> Tasks</span>
            </div>
            <div class="card-body">
                <?php if (empty($todoItems)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No tasks found with the current filters.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%">Status</th>
                                <th style="width: 15%">Category</th>
                                <th style="width: 35%">Task</th>
                                <th style="width: 10%">Priority</th>
                                <th style="width: 15%">Due Date</th>
                                <th style="width: 20%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todoItems as $item): ?>
                            <tr class="<?php 
                                if ($item['status'] === 'completed') {
                                    echo 'table-success';
                                } elseif ($item['status'] === 'in_progress') {
                                    echo 'table-warning';
                                } elseif ($item['status'] === 'pending') {
                                    echo 'table-light';
                                }
                            ?>">
                                <td>
                                    <form method="post" class="status-form d-inline">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <?php if ($item['status'] === 'pending'): ?>
                                            <span class="badge bg-secondary">Pending</span>
                                            <button type="submit" name="status" value="in_progress" class="btn btn-sm btn-outline-warning ms-1" title="Mark as In Progress">
                                                <i class="fas fa-arrow-right"></i>
                                            </button>
                                            <button type="submit" name="status" value="completed" class="btn btn-sm btn-outline-success ms-1" title="Mark as Completed">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php elseif ($item['status'] === 'in_progress'): ?>
                                            <span class="badge bg-warning text-dark">In Progress</span>
                                            <button type="submit" name="status" value="pending" class="btn btn-sm btn-outline-secondary ms-1" title="Mark as Pending">
                                                <i class="fas fa-arrow-left"></i>
                                            </button>
                                            <button type="submit" name="status" value="completed" class="btn btn-sm btn-outline-success ms-1" title="Mark as Completed">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php elseif ($item['status'] === 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                            <button type="submit" name="status" value="pending" class="btn btn-sm btn-outline-secondary ms-1" title="Mark as Pending">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($item['category_color']); ?>">
                                        <i class="<?php echo htmlspecialchars($item['category_icon']); ?> me-1"></i>
                                        <?php echo htmlspecialchars($item['category_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                    <?php if (!empty($item['description'])): ?>
                                    <p class="small text-muted mb-0"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['priority'] === 'high'): ?>
                                    <span class="badge bg-danger">High</span>
                                    <?php elseif ($item['priority'] === 'medium'): ?>
                                    <span class="badge bg-warning text-dark">Medium</span>
                                    <?php else: ?>
                                    <span class="badge bg-info text-dark">Low</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['due_date'])): ?>
                                    <?php echo date('M j, Y', strtotime($item['due_date'])); ?>
                                    <?php else: ?>
                                    <span class="text-muted">No due date</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/admin/todo/edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $item['id']; ?>">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete the task: <strong><?php echo htmlspecialchars($item['title']); ?></strong>?
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <form method="post">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" name="delete_item" class="btn btn-danger">Delete</button>
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
// Extra scripts
$extra_scripts = '
<script>
    // Add any custom JavaScript here
    document.addEventListener("DOMContentLoaded", function() {
        // Auto-submit status forms when changed
        const statusSelects = document.querySelectorAll(".status-select");
        statusSelects.forEach(select => {
            select.addEventListener("change", function() {
                this.closest("form").submit();
            });
        });
    });
</script>
';

// Include admin footer
include_once '../includes/footer.php';
?>
