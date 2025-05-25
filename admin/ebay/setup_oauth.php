<?php
// admin/ebay/setup_oauth.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Ensure user is an admin
if (!isAdmin()) {
    die('Access denied');
}

echo "<h2>Setting up eBay OAuth Database Fields</h2>";

try {
    // Check if site_settings table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'site_settings'");
    if ($tableCheck->rowCount() == 0) {
        echo "Creating site_settings table...<br>";
        $sql = "CREATE TABLE IF NOT EXISTS `site_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    
    // Add new OAuth-related settings
    $oauth_settings = [
        'ebay_ru_name' => '', // Redirect URI Name from eBay
        'ebay_access_token' => '',
        'ebay_refresh_token' => '',
        'ebay_token_expires' => '0',
        'ebay_oauth_connected' => '0',
        'ebay_oauth_last_verified' => ''
    ];
    
    foreach ($oauth_settings as $key => $default_value) {
        // Check if setting exists
        $check = $pdo->prepare("SELECT COUNT(*) FROM site_settings WHERE setting_key = :key");
        $check->execute([':key' => $key]);
        
        if ($check->fetchColumn() == 0) {
            $insert = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)");
            $insert->execute([':key' => $key, ':value' => $default_value]);
            echo "Added setting: $key<br>";
        } else {
            echo "Setting already exists: $key<br>";
        }
    }
    
    // Add 'is_featured' column to ebay_listings if it doesn't exist
    $columnCheck = $pdo->query("SHOW COLUMNS FROM ebay_listings LIKE 'is_featured'");
    if ($columnCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE ebay_listings ADD COLUMN is_featured tinyint(1) DEFAULT 0 AFTER is_active");
        echo "Added is_featured column to ebay_listings table<br>";
    }
    
    // Create ebay_clicks table if it doesn't exist
    $clicksTableCheck = $pdo->query("SHOW TABLES LIKE 'ebay_clicks'");
    if ($clicksTableCheck->rowCount() == 0) {
        $sql = "CREATE TABLE IF NOT EXISTS `ebay_clicks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `listing_id` varchar(50) NOT NULL,
            `visitor_ip` varchar(45) DEFAULT NULL,
            `user_agent` varchar(255) DEFAULT NULL,
            `referrer` varchar(255) DEFAULT NULL,
            `click_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `listing_id` (`listing_id`),
            KEY `click_date` (`click_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
        echo "Created ebay_clicks table<br>";
    }
    
    echo "<br><strong>OAuth setup completed successfully!</strong><br>";
    echo '<a href="settings.php">Go to eBay Settings</a>';
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>