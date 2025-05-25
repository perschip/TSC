<?php
// This script will convert your database and tables to support emojis
// Place this file in your website root directory and run it once

// Database configuration - update with your actual values
$db_host = 'localhost';
$db_name = 'tristatecards_2'; // Your database name
$db_user = 'tscadmin_2'; // Your database username
$db_pass = '$Yankees100'; // Your database password

// Start output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Emoji Support Converter</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
        }
        .success {
            color: #198754;
        }
        .error {
            color: #dc3545;
        }
        .warning {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Database Emoji Support Converter</h1>
        
        <div class="alert alert-info">
            <p><strong>What this does:</strong> This tool converts your database and tables to use the utf8mb4 character set, which supports emojis and all Unicode characters.</p>
            <p><strong>IMPORTANT:</strong> Delete this file after running it successfully.</p>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Conversion Log</h5>
            </div>
            <div class="card-body">
                <pre id="log"><?php
// Try to connect to the database
echo "Connecting to database...\n";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<span class='success'>✓ Successfully connected to database '$db_name'</span>\n\n";
    
    // Step 1: Change database character set
    echo "Converting database to utf8mb4...\n";
    $stmt = $pdo->prepare("ALTER DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt->execute();
    echo "<span class='success'>✓ Database converted to utf8mb4</span>\n\n";
    
    // Step 2: Get all tables
    echo "Retrieving tables...\n";
    $tables = [];
    $stmt = $pdo->prepare("SHOW TABLES");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    echo "<span class='success'>✓ Found " . count($tables) . " tables</span>\n\n";
    
    // Step 3: Convert each table
    echo "Converting tables to utf8mb4...\n";
    foreach ($tables as $table) {
        echo "  • Converting table '$table'... ";
        try {
            $stmt = $pdo->prepare("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmt->execute();
            echo "<span class='success'>✓ Success</span>\n";
        } catch (PDOException $e) {
            echo "<span class='error'>✗ Error: " . $e->getMessage() . "</span>\n";
        }
    }
    echo "\n";
    
    // Step 4: Verify conversion
    echo "Verifying table character sets...\n";
    $stmt = $pdo->prepare("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = :dbname");
    $stmt->bindParam(':dbname', $db_name);
    $stmt->execute();
    $tableInfo = $stmt->fetchAll();
    
    $allConverted = true;
    foreach ($tableInfo as $info) {
        $tableName = $info['TABLE_NAME'];
        $collation = $info['TABLE_COLLATION'];
        
        echo "  • Table '$tableName': ";
        if (strpos($collation, 'utf8mb4') === 0) {
            echo "<span class='success'>✓ Using $collation</span>\n";
        } else {
            echo "<span class='warning'>⚠ Still using $collation</span>\n";
            $allConverted = false;
        }
    }
    echo "\n";
    
    // Step 5: Update db.php file with utf8mb4 charset
    echo "Updating db.php settings...\n";
    $dbFilePath = __DIR__ . '/includes/db.php';
    
    if (file_exists($dbFilePath)) {
        $dbFileContent = file_get_contents($dbFilePath);
        
        // Check if the file already has utf8mb4
        if (strpos($dbFileContent, 'charset=utf8mb4') !== false) {
            echo "<span class='success'>✓ db.php already configured for utf8mb4</span>\n";
        } else {
            // Replace charset=utf8 with charset=utf8mb4
            $updatedContent = str_replace('charset=utf8', 'charset=utf8mb4', $dbFileContent);
            
            // If that didn't work, try to add the charset parameter
            if ($updatedContent === $dbFileContent) {
                $updatedContent = preg_replace('/(dbname=\$db_name)/', '$1;charset=utf8mb4', $dbFileContent);
            }
            
            // Add the SET NAMES line if it doesn't exist
            if (strpos($updatedContent, 'SET NAMES utf8mb4') === false) {
                $updatedContent = preg_replace(
                    '/(PDO::ATTR_EMULATE_PREPARES, false);/', 
                    '$1;' . PHP_EOL . PHP_EOL . '    // Set charset to utf8mb4 for emoji support' . PHP_EOL . '    $pdo->exec("SET NAMES utf8mb4");', 
                    $updatedContent
                );
            }
            
            if (file_put_contents($dbFilePath, $updatedContent)) {
                echo "<span class='success'>✓ Successfully updated db.php to use utf8mb4</span>\n";
            } else {
                echo "<span class='error'>✗ Failed to update db.php file. Manual update required.</span>\n";
                echo "<span class='warning'>⚠ Please add this line after creating the PDO connection:</span>\n";
                echo "<span class='warning'>   \$pdo->exec(\"SET NAMES utf8mb4\");</span>\n";
            }
        }
    } else {
        echo "<span class='error'>✗ Could not find db.php file at $dbFilePath</span>\n";
    }
    
    echo "\n";
    
    // Final status
    if ($allConverted) {
        echo "<span class='success'>✅ CONVERSION COMPLETED SUCCESSFULLY! Your database now supports emojis.</span>\n";
        echo "<span class='success'>Try using emojis in your Whatnot stream titles!</span>\n";
    } else {
        echo "<span class='warning'>⚠ CONVERSION PARTIALLY COMPLETED. Some tables may not fully support emojis.</span>\n";
    }
    
    echo "\n<span class='warning'>⚠ IMPORTANT: Delete this converter script now!</span>\n";
    
} catch (PDOException $e) {
    echo "<span class='error'>✗ Database connection failed: " . $e->getMessage() . "</span>\n";
}
?></pre>
            </div>
        </div>
        
        <div class="alert alert-warning">
            <h5>Next Steps:</h5>
            <ol>
                <li>Verify that your database has been successfully converted</li>
                <li>Test using emoji characters in your Whatnot stream titles</li>
                <li>Delete this converter script for security</li>
            </ol>
        </div>
        
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary">Return to Homepage</a>
            <a href="admin/index.php" class="btn btn-secondary">Go to Admin Dashboard</a>
        </div>
    </div>
</body>
</html>