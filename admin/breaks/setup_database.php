<?php
// admin/breaks/setup_database.php
// Run this file once to create the necessary database tables

require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Ensure user is an admin
if (!isAdmin()) {
    die('Access denied');
}

try {
    // Create breaks table with custom modifier
    $create_breaks_table = "
    CREATE TABLE IF NOT EXISTS `breaks` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `sport` enum('NFL','MLB','NBA','NHL') NOT NULL DEFAULT 'NFL',
        `break_type` enum('team','division','hit_draft','random') NOT NULL DEFAULT 'team',
        `status` enum('draft','active','completed','cancelled') NOT NULL DEFAULT 'draft',
        `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
        `profit_margin` decimal(5,2) NOT NULL DEFAULT 25.00,
        `custom_modifier` decimal(5,2) NOT NULL DEFAULT 0.00,
        `spot_price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `total_spots` int(11) NOT NULL DEFAULT 32,
        `sold_spots` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($create_breaks_table);
    
    // Create break_boxes table
    $create_break_boxes_table = "
    CREATE TABLE IF NOT EXISTS `break_boxes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `break_id` int(11) NOT NULL,
        `box_name` varchar(255) NOT NULL,
        `quantity` int(11) NOT NULL DEFAULT 1,
        `cost_per_box` decimal(10,2) NOT NULL,
        `total_cost` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `cost_per_box`) STORED,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `break_id` (`break_id`),
        FOREIGN KEY (`break_id`) REFERENCES `breaks`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($create_break_boxes_table);
    
    // Create break_spots table
    $create_break_spots_table = "
    CREATE TABLE IF NOT EXISTS `break_spots` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `break_id` int(11) NOT NULL,
        `team_name` varchar(100) NOT NULL,
        `team_code` varchar(10) NOT NULL,
        `price` decimal(10,2) NOT NULL,
        `is_sold` tinyint(1) NOT NULL DEFAULT 0,
        `buyer_name` varchar(255) DEFAULT NULL,
        `buyer_email` varchar(255) DEFAULT NULL,
        `sold_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `break_id` (`break_id`),
        FOREIGN KEY (`break_id`) REFERENCES `breaks`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($create_break_spots_table);
    
    // Create teams reference table with updated popularity multiplier range
    $create_teams_table = "
    CREATE TABLE IF NOT EXISTS `teams` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sport` enum('NFL','MLB','NBA','NHL') NOT NULL,
        `team_name` varchar(100) NOT NULL,
        `team_code` varchar(10) NOT NULL,
        `popularity_multiplier` decimal(4,1) NOT NULL DEFAULT 2.0,
        `notes` text DEFAULT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `sport_code` (`sport`, `team_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($create_teams_table);
    
    // Check if popularity_multiplier column exists, if not add it
    $columnCheck = $pdo->query("SHOW COLUMNS FROM teams LIKE 'popularity_multiplier'");
    if ($columnCheck->rowCount() == 0) {
        // Add the popularity_multiplier column
        $pdo->exec("ALTER TABLE teams ADD COLUMN popularity_multiplier decimal(4,1) NOT NULL DEFAULT 2.0 AFTER team_code");
        echo "Added popularity_multiplier column to teams table<br>";
    } else {
        // Update existing column to support 5.0 max
        $pdo->exec("ALTER TABLE teams MODIFY popularity_multiplier decimal(4,1) NOT NULL DEFAULT 2.0");
        echo "Updated popularity_multiplier column to support 5x scale<br>";
    }
    
    // Check if notes column exists, if not add it
    $notesCheck = $pdo->query("SHOW COLUMNS FROM teams LIKE 'notes'");
    if ($notesCheck->rowCount() == 0) {
        // Add the notes column
        $pdo->exec("ALTER TABLE teams ADD COLUMN notes text DEFAULT NULL AFTER popularity_multiplier");
        echo "Added notes column to teams table<br>";
    }
    
    // Check if custom_modifier column exists in breaks table, if not add it
    $modifierCheck = $pdo->query("SHOW COLUMNS FROM breaks LIKE 'custom_modifier'");
    if ($modifierCheck->rowCount() == 0) {
        // Add the custom_modifier column
        $pdo->exec("ALTER TABLE breaks ADD COLUMN custom_modifier decimal(5,2) NOT NULL DEFAULT 0.00 AFTER profit_margin");
        echo "Added custom_modifier column to breaks table<br>";
    }
    
    // NFL teams with updated 5x popularity multipliers
    $nfl_teams = [
        // 🔥 Tier 1: Premium Teams (4.5-5.0)
        ['Kansas City Chiefs', 'KC', 5.0],
        ['San Francisco 49ers', 'SF', 4.8],
        ['Chicago Bears', 'CHI', 4.7],
        ['Detroit Lions', 'DET', 4.6],
        ['Houston Texans', 'HOU', 4.5],
        
        // 🔥 Tier 2: High-Demand Teams (3.5-4.4)
        ['Philadelphia Eagles', 'PHI', 4.3],
        ['Cincinnati Bengals', 'CIN', 4.2],
        ['Buffalo Bills', 'BUF', 4.0],
        ['Minnesota Vikings', 'MIN', 3.9],
        ['Pittsburgh Steelers', 'PIT', 3.8],
        ['Green Bay Packers', 'GB', 3.7],
        ['Baltimore Ravens', 'BAL', 3.6],
        ['Atlanta Falcons', 'ATL', 3.5],
        
        // 💥 Tier 3: Popular Teams (2.5-3.4)
        ['Dallas Cowboys', 'DAL', 3.3],
        ['Indianapolis Colts', 'IND', 3.2],
        ['Arizona Cardinals', 'ARI', 3.1],
        ['Los Angeles Chargers', 'LAC', 3.0],
        ['New York Jets', 'NYJ', 2.9],
        ['Tennessee Titans', 'TEN', 2.8],
        ['New York Giants', 'NYG', 2.7],
        ['Denver Broncos', 'DEN', 2.6],
        ['Cleveland Browns', 'CLE', 2.5],
        
        // 🧊 Tier 4: Standard Teams (1.5-2.4)
        ['Los Angeles Rams', 'LAR', 2.3],
        ['Seattle Seahawks', 'SEA', 2.2],
        ['Tampa Bay Buccaneers', 'TB', 2.1],
        ['Washington Commanders', 'WAS', 2.0],
        ['New England Patriots', 'NE', 1.9],
        ['Carolina Panthers', 'CAR', 1.8],
        ['Jacksonville Jaguars', 'JAX', 1.7],
        ['Las Vegas Raiders', 'LV', 1.6],
        ['New Orleans Saints', 'NO', 1.5],
        
        // ❄️ Tier 5: Value Teams (1.0-1.4)
        ['Miami Dolphins', 'MIA', 1.2]
    ];
    
    // MLB teams with updated 5x popularity multipliers
    $mlb_teams = [
        // 🔥 Tier 1: Premium Teams (4.5-5.0)
        ['New York Yankees', 'NYY', 5.0],
        ['Los Angeles Dodgers', 'LAD', 4.9],
        ['Baltimore Orioles', 'BAL', 4.8],
        ['Atlanta Braves', 'ATL', 4.7],
        ['Cincinnati Reds', 'CIN', 4.5],
        
        // 🔥 Tier 2: High Hobby Appeal (3.5-4.4)
        ['Seattle Mariners', 'SEA', 4.3],
        ['Texas Rangers', 'TEX', 4.2],
        ['Detroit Tigers', 'DET', 4.1],
        ['San Diego Padres', 'SD', 4.0],
        ['Chicago Cubs', 'CHC', 3.9],
        ['Tampa Bay Rays', 'TB', 3.8],
        ['Boston Red Sox', 'BOS', 3.7],
        ['Pittsburgh Pirates', 'PIT', 3.6],
        ['Arizona Diamondbacks', 'ARI', 3.5],
        
        // 💥 Tier 3: Solid Mid-Tier Teams (2.5-3.4)
        ['New York Mets', 'NYM', 3.3],
        ['St. Louis Cardinals', 'STL', 3.2],
        ['Cleveland Guardians', 'CLE', 3.1],
        ['Toronto Blue Jays', 'TOR', 3.0],
        ['Milwaukee Brewers', 'MIL', 2.9],
        ['Philadelphia Phillies', 'PHI', 2.8],
        ['Chicago White Sox', 'CWS', 2.7],
        ['Houston Astros', 'HOU', 2.6],
        ['Washington Nationals', 'WAS', 2.5],
        
        // 🧊 Tier 4: Standard Teams (1.5-2.4)
        ['San Francisco Giants', 'SF', 2.3],
        ['Miami Marlins', 'MIA', 2.2],
        ['Los Angeles Angels', 'LAA', 2.1],
        ['Kansas City Royals', 'KC', 2.0],
        ['Minnesota Twins', 'MIN', 1.9],
        ['Colorado Rockies', 'COL', 1.8],
        
        // ❄️ Tier 5: Value Teams (1.0-1.4)
        ['Oakland Athletics', 'OAK', 1.5]
    ];
    
    // Insert NFL teams using prepared statements (with REPLACE to handle updates)
    $nfl_insert = "REPLACE INTO teams (sport, team_name, team_code, popularity_multiplier) VALUES (?, ?, ?, ?)";
    $nfl_stmt = $pdo->prepare($nfl_insert);
    foreach ($nfl_teams as $team) {
        $nfl_stmt->execute(['NFL', $team[0], $team[1], $team[2]]);
    }
    
    // Insert MLB teams using prepared statements (with REPLACE to handle updates)
    $mlb_insert = "REPLACE INTO teams (sport, team_name, team_code, popularity_multiplier) VALUES (?, ?, ?, ?)";
    $mlb_stmt = $pdo->prepare($mlb_insert);
    foreach ($mlb_teams as $team) {
        $mlb_stmt->execute(['MLB', $team[0], $team[1], $team[2]]);
    }
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>✅ Database Setup Complete!</h3>";
    echo "Database tables created/updated successfully with 5x multiplier scale!<br>";
    echo "NFL teams: " . count($nfl_teams) . " inserted/updated<br>";
    echo "MLB teams: " . count($mlb_teams) . " inserted/updated<br><br>";
    
    echo "<strong>New Multiplier Ranges:</strong><br>";
    echo "🔥 Premium Tier: 4.5-5.0x (Chiefs, Yankees level)<br>";
    echo "🔥 High-Demand Tier: 3.5-4.4x (Strong popularity)<br>";
    echo "💥 Popular Tier: 2.5-3.4x (Above average demand)<br>";
    echo "🧊 Standard Tier: 1.5-2.4x (Average demand)<br>";
    echo "❄️ Value Tier: 1.0-1.4x (Below average)<br><br>";
    
    echo "<a href='calculator.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Go to Break Calculator</a>";
    echo "<a href='teams.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Manage Team Multipliers</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Database Error</h3>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Setup Complete</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 Break Calculator Database Setup</h1>
        <p>The database setup has been completed. Check the results above and use the navigation links to proceed.</p>
    </div>
</body>
</html>