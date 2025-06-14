<?php
// admin/ebay/import_categories.php
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

// Import categories from listings
$imported = 0;
$skipped = 0;
$errors = [];

try {
    // Get all distinct categories from listings
    $stmt = $pdo->query("SELECT DISTINCT category FROM ebay_listings WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $existing_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all categories already in the categories table
    $stmt = $pdo->query("SELECT name FROM ebay_categories");
    $category_table_entries = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Import categories that don't already exist in the categories table
    foreach ($existing_categories as $category) {
        if (!in_array($category, $category_table_entries)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO ebay_categories (name) VALUES (:name)");
                $stmt->execute([':name' => $category]);
                $imported++;
            } catch (PDOException $e) {
                $errors[] = "Error importing category '$category': " . $e->getMessage();
            }
        } else {
            $skipped++;
        }
    }
    
    $_SESSION['success_message'] = "Category import complete! Imported $imported categories, skipped $skipped existing categories.";
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error importing categories: ' . $e->getMessage();
}

// Redirect back to categories page
header('Location: categories.php');
exit;
?>
