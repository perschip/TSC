<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Page variables
$page_title = 'Add New Task';

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

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    try {
        // Validate inputs
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
        $priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        
        // Basic validation
        if (empty($title)) {
            throw new Exception('Task title is required');
        }
        
        if ($categoryId <= 0) {
            throw new Exception('Please select a valid category');
        }
        
        // Prepare SQL statement
        $sql = "INSERT INTO todo_items (category_id, title, description, status, priority, due_date) 
                VALUES (:category_id, :title, :description, :status, :priority, :due_date)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':category_id' => $categoryId,
            ':title' => $title,
            ':description' => $description,
            ':status' => $status,
            ':priority' => $priority,
            ':due_date' => $dueDate
        ]);
        
        // Set success message and redirect
        $_SESSION['success_message'] = 'Task added successfully!';
        header('Location: /admin/todo/index.php');
        exit;
    } catch (Exception $e) {
        $message = 'Error adding task: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check if tables exist
checkTablesExist($pdo);

// Get categories
$categories = getCategories($pdo);

// Set default values
$task = [
    'category_id' => isset($_GET['category']) ? (int)$_GET['category'] : 0,
    'title' => '',
    'description' => '',
    'status' => 'pending',
    'priority' => 'medium',
    'due_date' => ''
];

// Include admin header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Add New Task</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $task['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Task Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($task['description']); ?></textarea>
                        <div class="form-text">Optional. Provide any additional details about the task.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?php echo $task['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $task['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $task['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo htmlspecialchars($task['due_date']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="/admin/todo/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to List
                        </a>
                        <button type="submit" name="add_task" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Add Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
