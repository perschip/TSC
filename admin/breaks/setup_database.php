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
    
    // NFL teams with popularity multipliers (Updated based on current hobby market)
    $nfl_teams = [
        // 🔥 Tier 1: Premium Teams (1.8-2.0)
        ['Kansas City Chiefs', 'KC', 2.0],   // Mahomes is the face of the hobby. Rashee Rice, Xavier Worthy = bonus
        ['San Francisco 49ers', 'SF', 2.0],  // Purdy market is hot; deep team, lots of chase
        ['Chicago Bears', 'CHI', 1.9],       // Caleb Williams mania + DJ Moore + large market
        ['Detroit Lions', 'DET', 1.9],       // Gibbs, Amon-Ra, LaPorta, and rising team success
        ['Philadelphia Eagles', 'PHI', 1.8], // Jalen Hurts, DeVonta, AJ Brown, strong fanbase
        ['Houston Texans', 'HOU', 1.8],      // CJ Stroud, Tank Dell, Will Anderson = hobby gold
        
        // 🔥 Tier 2: High-Demand Teams (1.4–1.75)
        ['Cincinnati Bengals', 'CIN', 1.7],  // Burrow + Chase = perennial chasers
        ['Pittsburgh Steelers', 'PIT', 1.7], // Iconic brand, strong rookie classes, fan loyalty
        ['Green Bay Packers', 'GB', 1.6],    // Jordan Love + history = sustained demand
        ['Buffalo Bills', 'BUF', 1.6],       // Allen still popular, Kincaid gaining traction
        ['Minnesota Vikings', 'MIN', 1.5],   // JJ + McCarthy rookie hype
        ['Dallas Cowboys', 'DAL', 1.5],      // National team, Parsons, CeeDee; consistent appeal
        ['Atlanta Falcons', 'ATL', 1.5],     // Bijan + Penix + London = intriguing chase
        
        // 💥 Tier 3: Mid-Popularity (1.1–1.4)
        ['Baltimore Ravens', 'BAL', 1.4],        // Lamar Jackson + Zay Flowers, playoff team
        ['Indianapolis Colts', 'IND', 1.3],      // Anthony Richardson return = hobby rebound
        ['Los Angeles Chargers', 'LAC', 1.3],    // Herbert still relevant, high upside
        ['Arizona Cardinals', 'ARI', 1.3],       // Marvin Harrison Jr. chase = massive boost
        ['Tennessee Titans', 'TEN', 1.2],        // Will Levis, solid RBs
        ['New York Jets', 'NYJ', 1.2],           // Rodgers + Wilson + TV time = steady demand
        ['New York Giants', 'NYG', 1.2],         // Large market but few hot rookies recently
        ['Denver Broncos', 'DEN', 1.1],          // Nix/rookie QB bump, but market varies
        ['Cleveland Browns', 'CLE', 1.1],        // Strong defense; card market just okay
        
        // 🧊 Tier 4: Low-Mid Market (0.8–1.0)
        ['Los Angeles Rams', 'LAR', 1.0],        // Puka + Stafford, but hobby isn't dominant
        ['Seattle Seahawks', 'SEA', 1.0],        // JSN, Geno, and strong following
        ['Tampa Bay Buccaneers', 'TB', 1.0],     // Mayfield + Evans/Godwin, moderate interest
        ['New England Patriots', 'NE', 0.9],     // No Brady = steep decline; Maye might help
        ['Carolina Panthers', 'CAR', 0.9],       // Bryce Young struggled, weak hobby pull
        ['Washington Commanders', 'WAS', 0.9],   // Jayden Daniels could boost this in 2024
        
        // ❄️ Tier 5: Low-Demand Teams (0.5–0.8)
        ['Las Vegas Raiders', 'LV', 0.8],        // No real hobby draw unless a QB pops
        ['Jacksonville Jaguars', 'JAX', 0.8],    // Lawrence's value cooled; Etienne helps
        ['New Orleans Saints', 'NO', 0.7],       // Aging roster, small card market
        ['Miami Dolphins', 'MIA', 0.7]           // Tua/Waddle/Hill are great, but hobby demand lags
    ];
    
    // MLB teams with popularity multipliers (Updated based on current hobby market)
    $mlb_teams = [
        // 🔥 Tier 1: Premium Teams (1.8-2.0)
        ['New York Yankees', 'NYY', 2.0],     // Perennial top seller. Huge fanbase, legends, Jasson Domínguez hype
        ['Los Angeles Dodgers', 'LAD', 2.0],  // Ohtani, Mookie, Yamamoto, and elite prospects. National chase
        ['Atlanta Braves', 'ATL', 1.9],       // Acuña, Harris, Strider, and top-tier farm = hobby gold
        ['Baltimore Orioles', 'BAL', 1.9],    // Gunnar, Adley, Holliday = major heat across Bowman + Flagship
        ['Cincinnati Reds', 'CIN', 1.8],      // Elly De La Cruz, CES, Marte, Cam Collier — deep young core
        
        // 🔥 Tier 2: High Hobby Appeal (1.4–1.75)
        ['Seattle Mariners', 'SEA', 1.7],     // Julio Rodríguez = top-tier pull. Strong Bowman rookies
        ['Texas Rangers', 'TEX', 1.7],        // World Series champs + Carter, Langford, and big bats
        ['Chicago Cubs', 'CHC', 1.6],         // Crow-Armstrong + huge market = steady demand
        ['Detroit Tigers', 'DET', 1.6],       // Jobe, Jung, Keith, Meadows — big Bowman presence
        ['Tampa Bay Rays', 'TB', 1.5],        // Wander + tons of prospect hits; sneaky good in breaks
        ['San Diego Padres', 'SD', 1.5],      // Tatis, Jackson Merrill, big west coast market
        ['Boston Red Sox', 'BOS', 1.5],       // Strong hobby base + Marcelo Mayer, Roman Anthony
        ['Pittsburgh Pirates', 'PIT', 1.4],   // Paul Skenes + Termarr Johnson = major prospect chase
        
        // 💥 Tier 3: Solid Mid-Tier Teams (1.1–1.4)
        ['Arizona Diamondbacks', 'ARI', 1.4], // Carroll + Lawlar + NL champs boost
        ['New York Mets', 'NYM', 1.3],        // Strong fanbase, inconsistent hobby returns
        ['St. Louis Cardinals', 'STL', 1.3],  // Classic brand, Walker, Winn, and big legacy
        ['Cleveland Guardians', 'CLE', 1.2],  // Espino, Manzardo, and depth in prospects
        ['Toronto Blue Jays', 'TOR', 1.2],    // Vladdy Jr., Schneider, and upside youth
        ['Milwaukee Brewers', 'MIL', 1.2],    // Chourio hobby heat, Lauer, Quero, etc
        ['Chicago White Sox', 'CWS', 1.1],    // Colson Montgomery + Oscar Colás = prospect appeal
        ['Philadelphia Phillies', 'PHI', 1.1], // Harper, Bohm, Painter = occasional spike
        
        // 🧊 Tier 4: Low-Mid Market (0.8–1.0)
        ['Miami Marlins', 'MIA', 1.0],        // Pérez & prospects help, but hobby base small
        ['Los Angeles Angels', 'LAA', 1.0],   // No more Ohtani = hobby pull drop. Neto + O'Hoppe help
        ['Houston Astros', 'HOU', 0.9],       // Aging stars, few rookies; strong in past, now cooling
        ['San Francisco Giants', 'SF', 0.9],  // Weak rookie presence lately, solid legacy
        ['Washington Nationals', 'WAS', 0.9], // Dylan Crews might help this long-term
        ['Kansas City Royals', 'KC', 0.9],    // Witt is strong, but not much hobby love overall
        
        // ❄️ Tier 5: Low-Demand Teams (0.5–0.8)
        ['Colorado Rockies', 'COL', 0.8],     // Weak market, few hot rookies
        ['Minnesota Twins', 'MIN', 0.8],      // Brooks Lee and Lewis offer some upside
        ['Oakland Athletics', 'OAK', 0.7]     // Very small hobby audience, almost no current chase
    ];
    
    // Create teams reference table with popularity multiplier and notes
    $create_teams_table = "
    CREATE TABLE IF NOT EXISTS `teams` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sport` enum('NFL','MLB','NBA','NHL') NOT NULL,
        `team_name` varchar(100) NOT NULL,
        `team_code` varchar(10) NOT NULL,
        `popularity_multiplier` decimal(4,2) NOT NULL DEFAULT 1.00,
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
        $pdo->exec("ALTER TABLE teams ADD COLUMN popularity_multiplier decimal(4,2) NOT NULL DEFAULT 1.00 AFTER team_code");
        echo "Added popularity_multiplier column to teams table<br>";
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
    
    echo "Database tables created successfully!<br>";
    echo "NFL teams: " . count($nfl_teams) . " inserted<br>";
    echo "MLB teams: " . count($mlb_teams) . " inserted<br>";
    echo "<a href='calculator.php'>Go to Break Calculator</a>";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>