<?php
// Database configuration
$db_host = 'localhost';
$db_name = 'tristatecards_2'; // Your database name
$db_user = 'tscadmin_2'; // Your database username
$db_pass = '$Yankees100'; // Your database password
// Establish database connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Set charset to utf8mb4 for emoji support
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}