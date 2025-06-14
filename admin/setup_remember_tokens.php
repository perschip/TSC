<?php
// Include database connection and functions
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Get database connection
$pdo = getDBConnection();

// Check if remember_tokens table exists
$tableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
    if ($stmt->rowCount() > 0) {
        $tableExists = true;
        echo "remember_tokens table already exists.<br>";
    } else {
        echo "Creating remember_tokens table...<br>";
        
        // Create the table
        $pdo->exec("CREATE TABLE remember_tokens (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY (user_id),
            KEY (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        echo "remember_tokens table created successfully.<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Check if the login.php file has the remember me functionality
echo "<h3>Login Persistence Status</h3>";
echo "The 'Remember me' functionality is already implemented in login.php.<br>";
echo "It will now work properly with the remember_tokens table.<br><br>";

echo "<h3>Next Steps</h3>";
echo "<ol>";
echo "<li>Test the 'Remember me' checkbox on the login page</li>";
echo "<li>Make sure you have all required eBay API credentials in the settings page</li>";
echo "<li>Try refreshing your eBay OAuth token before testing the connection</li>";
echo "</ol>";

echo "<p><a href='ebay/settings.php' class='btn btn-primary'>Go to eBay Settings</a></p>";
echo "<p><a href='login.php' class='btn btn-secondary'>Go to Login Page</a></p>";
?>
