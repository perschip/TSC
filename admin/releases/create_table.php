<?php
// Create the product_releases table
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    global $pdo;
    
    // Check if the table already exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'product_releases'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Create the product_releases table
        $pdo->exec("CREATE TABLE product_releases (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            release_date DATE NOT NULL,
            image_url VARCHAR(255),
            product_url VARCHAR(255),
            category VARCHAR(100),
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $response = [
            'success' => true, 
            'message' => 'Successfully created product_releases table'
        ];
        error_log("Created product_releases table");
    } else {
        $response = [
            'success' => true, 
            'message' => 'product_releases table already exists'
        ];
    }
} catch (PDOException $e) {
    $response = [
        'success' => false, 
        'message' => 'Error creating product_releases table: ' . $e->getMessage()
    ];
    error_log('Error creating product_releases table: ' . $e->getMessage());
}

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Product Release Calendar Setup</h1>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Database Setup</h6>
        </div>
        <div class="card-body">
            <?php if ($response['success']): ?>
                <div class="alert alert-success">
                    <?php echo $response['message']; ?>
                </div>
                <p>The product release calendar is now ready to use.</p>
                <a href="index.php" class="btn btn-primary">Go to Release Calendar</a>
            <?php else: ?>
                <div class="alert alert-danger">
                    <?php echo $response['message']; ?>
                </div>
                <p>There was an error setting up the product release calendar. Please check the error logs.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
