<?php
// Include database connection
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
        echo "remember_tokens table exists.\n";
    } else {
        echo "remember_tokens table does not exist. Creating it now...\n";
        
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
        
        echo "remember_tokens table created successfully.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check if the login.php file has the correct remember me implementation
$loginFile = file_get_contents('../admin/login.php');

if (strpos($loginFile, 'remember_token') !== false) {
    echo "Login file has remember_token code implemented.\n";
} else {
    echo "Login file might be missing remember_token implementation.\n";
}

// Check for existing remember tokens
try {
    if ($tableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM remember_tokens");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Current remember tokens in database: " . $result['count'] . "\n";
    }
} catch (PDOException $e) {
    echo "Error checking tokens: " . $e->getMessage() . "\n";
}

echo "Remember me functionality check complete.\n";
?>
