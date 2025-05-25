<?php
// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Enable error logging
ini_set('display_errors', 0);
error_log('AJAX Category Add: Starting request');

header('Content-Type: application/json');

// Ensure user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    error_log('AJAX Category Add: Unauthorized access');
    exit;
}

// Check if request is POST and name is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = trim($_POST['name']);
    error_log('AJAX Category Add: Processing name: ' . $name);
    
    // Validate name
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        error_log('AJAX Category Add: Empty name provided');
        exit;
    }
    
    // Generate slug
    $slug = createSlug($name);
    error_log('AJAX Category Add: Generated slug: ' . $slug);
    
    try {
        // Check if category already exists
        $check_query = "SELECT id FROM blog_categories WHERE name = :name OR slug = :slug LIMIT 1";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->bindParam(':name', $name);
        $check_stmt->bindParam(':slug', $slug);
        $check_stmt->execute();
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Category already exists', 'id' => $existing['id']]);
            error_log('AJAX Category Add: Category already exists with ID: ' . $existing['id']);
            exit;
        }
        
        // Insert new category
        $insert_query = "INSERT INTO blog_categories (name, slug) VALUES (:name, :slug)";
        $insert_stmt = $pdo->prepare($insert_query);
        $insert_stmt->bindParam(':name', $name);
        $insert_stmt->bindParam(':slug', $slug);
        $insert_stmt->execute();
        
        $category_id = $pdo->lastInsertId();
        error_log('AJAX Category Add: Successfully created category with ID: ' . $category_id);
        
        echo json_encode(['success' => true, 'message' => 'Category added successfully', 'id' => $category_id, 'name' => $name, 'slug' => $slug]);
        
    } catch (PDOException $e) {
        error_log('AJAX Category Add: Database error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    error_log('AJAX Category Add: Invalid request. Method: ' . $_SERVER['REQUEST_METHOD'] . ', POST data: ' . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>