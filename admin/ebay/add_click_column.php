<?php
// add_click_column.php - Adds the click_count column to the ebay_listings table
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    global $pdo;
    
    // Check if the click_count column exists
    $columns = [];
    $columnsQuery = $pdo->query("SHOW COLUMNS FROM ebay_listings");
    while ($column = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $column['Field'];
    }
    
    // Add click_count column if it doesn't exist
    if (!in_array('click_count', $columns)) {
        $pdo->exec("ALTER TABLE ebay_listings ADD COLUMN click_count INT NOT NULL DEFAULT 0 AFTER is_favorite");
        $response = [
            'success' => true, 
            'message' => 'Successfully added click_count column to ebay_listings table'
        ];
        error_log("Added click_count column to ebay_listings table");
    } else {
        $response = [
            'success' => true, 
            'message' => 'click_count column already exists in ebay_listings table'
        ];
    }
} catch (PDOException $e) {
    $response = [
        'success' => false, 
        'message' => 'Error adding click_count column: ' . $e->getMessage()
    ];
    error_log('Error adding click_count column: ' . $e->getMessage());
}

// Output response
echo '<h1>eBay Click Count Column Setup</h1>';
echo '<p>' . $response['message'] . '</p>';

if ($response['success']) {
    echo '<p style="color: green;">✓ Setup complete</p>';
    echo '<p><a href="listings.php" class="btn btn-primary">Return to Listings</a></p>';
} else {
    echo '<p style="color: red;">✗ Setup failed</p>';
    echo '<p><a href="listings.php" class="btn btn-secondary">Return to Listings</a></p>';
}
?>
