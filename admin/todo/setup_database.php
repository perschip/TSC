<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Page variables
$page_title = 'Setup To-Do Database';

// Check if tables already exist
function tablesExist($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'todo_categories'");
        $categoriesExist = $stmt->rowCount() > 0;
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'todo_items'");
        $itemsExist = $stmt->rowCount() > 0;
        
        return $categoriesExist && $itemsExist;
    } catch (PDOException $e) {
        return false;
    }
}

// Create tables if they don't exist
function createTables($pdo) {
    try {
        // Create todo_categories table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `todo_categories` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `color` VARCHAR(20) DEFAULT '#6c757d',
            `icon` VARCHAR(50) DEFAULT 'fas fa-tasks',
            `sort_order` INT(11) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Create todo_items table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `todo_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `category_id` INT(11) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `status` ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
            `due_date` DATE DEFAULT NULL,
            `completed_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`category_id`) REFERENCES `todo_categories`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        
        // Insert default categories
        $pdo->exec("INSERT INTO `todo_categories` (`name`, `color`, `icon`, `sort_order`) VALUES 
            ('eBay', '#e53935', 'fab fa-ebay', 1),
            ('Whatnot', '#7e57c2', 'fas fa-video', 2),
            ('General', '#26a69a', 'fas fa-clipboard-list', 3);");
            
        // Insert default tasks for each category
        $pdo->exec("INSERT INTO `todo_items` (`category_id`, `title`, `description`, `status`, `priority`) VALUES 
            (1, 'Scan Cards', 'Scan new cards for eBay listings', 'pending', 'medium'),
            (1, 'Create Listings', 'Create new eBay listings for scanned cards', 'pending', 'high'),
            (1, 'Edit Listings', 'Update existing eBay listings', 'pending', 'low'),
            (2, 'Package Cards', 'Package cards for Whatnot shipments', 'pending', 'medium'),
            (2, 'Ship Cards', 'Ship packaged cards to customers', 'pending', 'high'),
            (2, 'Create Break', 'Set up new Whatnot break', 'pending', 'medium'),
            (2, 'Order Supplies', 'Order packaging supplies for Whatnot', 'pending', 'low'),
            (3, 'Website Updates', 'Make updates to the website', 'pending', 'medium');");
            
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    if (tablesExist($pdo)) {
        $message = 'To-Do tables already exist. Setup skipped.';
        $messageType = 'warning';
    } else {
        $result = createTables($pdo);
        if ($result === true) {
            $message = 'To-Do database tables created successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error creating tables: ' . $result;
            $messageType = 'danger';
        }
    }
}

// Include admin header
include_once '../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">To-Do Database Setup</h6>
    </div>
    <div class="card-body">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <p>This utility will set up the necessary database tables for the To-Do list feature:</p>
        <ul>
            <li><code>todo_categories</code> - For storing To-Do categories</li>
            <li><code>todo_items</code> - For storing To-Do items</li>
        </ul>
        
        <p>The setup will also create default categories for eBay, Whatnot, and General tasks.</p>
        
        <?php if (tablesExist($pdo)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> To-Do database tables already exist.
            </div>
            <a href="/admin/todo/index.php" class="btn btn-primary">
                <i class="fas fa-tasks me-2"></i> Go to To-Do List
            </a>
        <?php else: ?>
            <form method="post">
                <button type="submit" name="setup" class="btn btn-primary">
                    <i class="fas fa-database me-2"></i> Set Up To-Do Database
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
