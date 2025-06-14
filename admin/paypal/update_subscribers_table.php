<?php
// update_subscribers_table.php
// Adds coupon_code field to subscribers table to track exit intent coupons

// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/admin_auth.php'; // Ensure only admins can run this script

// Set header to return JSON
header('Content-Type: application/json');

try {
    // Check if the coupon_code column already exists
    $check_column_query = "SHOW COLUMNS FROM subscribers LIKE 'coupon_code'";
    $check_column_stmt = $pdo->prepare($check_column_query);
    $check_column_stmt->execute();
    
    if ($check_column_stmt->rowCount() === 0) {
        // Add the coupon_code column to the subscribers table
        $alter_table_query = "ALTER TABLE subscribers ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL";
        $pdo->exec($alter_table_query);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Successfully added coupon_code column to subscribers table'
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'The coupon_code column already exists in subscribers table'
        ]);
    }
    
} catch (PDOException $e) {
    // Log the error
    error_log('Database update error: ' . $e->getMessage());
    
    // Return error message
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
