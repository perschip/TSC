<?php
/**
 * Update eBay Listings Table Structure
 * 
 * This script adds the is_active column to the ebay_listings table if it doesn't exist.
 */

require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Function to check if a column exists in a table
function columnExists($pdo, $table, $column) {
    try {
        $sql = "SHOW COLUMNS FROM {$table} LIKE '{$column}'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking if column exists: " . $e->getMessage());
        return false;
    }
}

// Function to add the is_active column if it doesn't exist
function addIsActiveColumn($pdo) {
    try {
        // Check if the column exists
        if (!columnExists($pdo, 'ebay_listings', 'is_active')) {
            // Add the column
            $sql = "ALTER TABLE ebay_listings ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1";
            $pdo->exec($sql);
            echo "Successfully added is_active column to ebay_listings table.<br>";
            error_log("Added is_active column to ebay_listings table");
            return true;
        } else {
            echo "The is_active column already exists in the ebay_listings table.<br>";
            error_log("is_active column already exists in ebay_listings table");
            return true;
        }
    } catch (PDOException $e) {
        echo "Error adding is_active column: " . $e->getMessage() . "<br>";
        error_log("Error adding is_active column: " . $e->getMessage());
        return false;
    }
}

// Add the is_active column
$result = addIsActiveColumn($pdo);

// Add an index for the is_active column
if ($result) {
    try {
        // Check if the index exists
        $sql = "SHOW INDEX FROM ebay_listings WHERE Key_name = 'idx_is_active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            // Add the index
            $sql = "ALTER TABLE ebay_listings ADD INDEX idx_is_active (is_active)";
            $pdo->exec($sql);
            echo "Successfully added index on is_active column.<br>";
            error_log("Added index on is_active column");
        } else {
            echo "The index on is_active column already exists.<br>";
            error_log("Index on is_active column already exists");
        }
    } catch (PDOException $e) {
        echo "Error adding index: " . $e->getMessage() . "<br>";
        error_log("Error adding index: " . $e->getMessage());
    }
}

echo "Table update process completed.<br>";
echo "<a href='settings.php'>Return to eBay Settings</a>";
