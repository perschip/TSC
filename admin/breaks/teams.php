<?php
// admin/breaks/teams.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_multipliers':
                $sport = $_POST['sport'];
                $updated_count = 0;
                
                try {
                    foreach ($_POST['multipliers'] as $team_id => $multiplier) {
                        $multiplier = (float)$multiplier;
                        
                        // Validate multiplier range
                        if ($multiplier >= 0.5 && $multiplier <= 2.0) {
                            $query = "UPDATE teams SET popularity_multiplier = :multiplier WHERE id = :team_id";
                            $stmt = $pdo->prepare($query);
                            $stmt->execute([
                                ':multiplier' => $multiplier,
                                ':team_id' => (int)$team_id
                            ]);
                            $updated_count++;
                        }
                    }
                    
                    $_SESSION['success_message'] = "Updated {$updated_count} team multipliers successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error updating multipliers: ' . $e->getMessage();
                }
                break;
                
            case 'reset_to_defaults':
                $sport = $_POST['sport'];
                
                // Default multipliers for quick reset - Updated based on current hobby market
                $default_multipliers = [
                    'NFL' => [
                        // 🔥 Tier 1: Premium Teams (1.8-2.0)
                        'KC' => 2.0,   // Mahomes is the face of the hobby. Rashee Rice, Xavier Worthy = bonus
                        'SF' => 2.0,   // Purdy market is hot; deep team, lots of chase
                        'CHI' => 1.9,  // Caleb Williams mania + DJ Moore + large market
                        'DET' => 1.9,  // Gibbs, Amon-Ra, LaPorta, and rising team success
                        'PHI' => 1.8,  // Jalen Hurts, DeVonta, AJ Brown, strong fanbase
                        'HOU' => 1.8,  // CJ Stroud, Tank Dell, Will Anderson = hobby gold
                        
                        // 🔥 Tier 2: High-Demand Teams (1.4–1.75)
                        'CIN' => 1.7,  // Burrow + Chase = perennial chasers
                        'PIT' => 1.7,  // Iconic brand, strong rookie classes, fan loyalty
                        'GB' => 1.6,   // Jordan Love + history = sustained demand
                        'BUF' => 1.6,  // Allen still popular, Kincaid gaining traction
                        'MIN' => 1.5,  // JJ + McCarthy rookie hype
                        'DAL' => 1.5,  // National team, Parsons, CeeDee; consistent appeal
                        'ATL' => 1.5,  // Bijan + Penix + London = intriguing chase
                        
                        // 💥 Tier 3: Mid-Popularity (1.1–1.4)
                        'BAL' => 1.4,  // Lamar Jackson + Zay Flowers, playoff team
                        'IND' => 1.3,  // Anthony Richardson return = hobby rebound
                        'LAC' => 1.3,  // Herbert still relevant, high upside
                        'ARI' => 1.3,  // Marvin Harrison Jr. chase = massive boost
                        'TEN' => 1.2,  // Will Levis, solid RBs
                        'NYJ' => 1.2,  // Rodgers + Wilson + TV time = steady demand
                        'NYG' => 1.2,  // Large market but few hot rookies recently
                        'DEN' => 1.1,  // Nix/rookie QB bump, but market varies
                        'CLE' => 1.1,  // Strong defense; card market just okay
                        
                        // 🧊 Tier 4: Low-Mid Market (0.8–1.0)
                        'LAR' => 1.0,  // Puka + Stafford, but hobby isn't dominant
                        'SEA' => 1.0,  // JSN, Geno, and strong following
                        'TB' => 1.0,   // Mayfield + Evans/Godwin, moderate interest
                        'NE' => 0.9,   // No Brady = steep decline; Maye might help
                        'CAR' => 0.9,  // Bryce Young struggled, weak hobby pull
                        'WAS' => 0.9,  // Jayden Daniels could boost this in 2024
                        
                        // ❄️ Tier 5: Low-Demand Teams (0.5–0.8)
                        'LV' => 0.8,   // No real hobby draw unless a QB pops
                        'JAX' => 0.8,  // Lawrence's value cooled; Etienne helps
                        'NO' => 0.7,   // Aging roster, small card market
                        'MIA' => 0.7   // Tua/Waddle/Hill are great, but hobby demand lags
                    ],
                    'MLB' => [
                        // 🔥 Tier 1: Premium Teams (1.8-2.0)
                        'NYY' => 2.0,  // Perennial top seller. Huge fanbase, legends, Jasson Domínguez hype
                        'LAD' => 2.0,  // Ohtani, Mookie, Yamamoto, and elite prospects. National chase
                        'ATL' => 1.9,  // Acuña, Harris, Strider, and top-tier farm = hobby gold
                        'BAL' => 1.9,  // Gunnar, Adley, Holliday = major heat across Bowman + Flagship
                        'CIN' => 1.8,  // Elly De La Cruz, CES, Marte, Cam Collier — deep young core
                        
                        // 🔥 Tier 2: High Hobby Appeal (1.4–1.75)
                        'SEA' => 1.7,  // Julio Rodríguez = top-tier pull. Strong Bowman rookies
                        'TEX' => 1.7,  // World Series champs + Carter, Langford, and big bats
                        'CHC' => 1.6,  // Crow-Armstrong + huge market = steady demand
                        'DET' => 1.6,  // Jobe, Jung, Keith, Meadows — big Bowman presence
                        'TB' => 1.5,   // Wander + tons of prospect hits; sneaky good in breaks
                        'SD' => 1.5,   // Tatis, Jackson Merrill, big west coast market
                        'BOS' => 1.5,  // Strong hobby base + Marcelo Mayer, Roman Anthony
                        'PIT' => 1.4,  // Paul Skenes + Termarr Johnson = major prospect chase
                        
                        // 💥 Tier 3: Solid Mid-Tier Teams (1.1–1.4)
                        'ARI' => 1.4,  // Carroll + Lawlar + NL champs boost
                        'NYM' => 1.3,  // Strong fanbase, inconsistent hobby returns
                        'STL' => 1.3,  // Classic brand, Walker, Winn, and big legacy
                        'CLE' => 1.2,  // Espino, Manzardo, and depth in prospects
                        'TOR' => 1.2,  // Vladdy Jr., Schneider, and upside youth
                        'MIL' => 1.2,  // Chourio hobby heat, Lauer, Quero, etc
                        'CWS' => 1.1,  // Colson Montgomery + Oscar Colás = prospect appeal
                        'PHI' => 1.1,  // Harper, Bohm, Painter = occasional spike
                        
                        // 🧊 Tier 4: Low-Mid Market (0.8–1.0)
                        'MIA' => 1.0,  // Pérez & prospects help, but hobby base small
                        'LAA' => 1.0,  // No more Ohtani = hobby pull drop. Neto + O'Hoppe help
                        'HOU' => 0.9,  // Aging stars, few rookies; strong in past, now cooling
                        'SF' => 0.9,   // Weak rookie presence lately, solid legacy
                        'WAS' => 0.9,  // Dylan Crews might help this long-term
                        'KC' => 0.9,   // Witt is strong, but not much hobby love overall
                        
                        // ❄️ Tier 5: Low-Demand Teams (0.5–0.8)
                        'COL' => 0.8,  // Weak market, few hot rookies
                        'MIN' => 0.8,  // Brooks Lee and Lewis offer some upside
                        'OAK' => 0.7   // Very small hobby audience, almost no current chase
                    ]
                ];
                
                try {
                    $updated_count = 0;
                    if (isset($default_multipliers[$sport])) {
                        foreach ($default_multipliers[$sport] as $team_code => $multiplier) {
                            $query = "UPDATE teams SET popularity_multiplier = :multiplier WHERE sport = :sport AND team_code = :team_code";
                            $stmt = $pdo->prepare($query);
                            $stmt->execute([
                                ':multiplier' => $multiplier,
                                ':sport' => $sport,
                                ':team_code' => $team_code
                            ]);
                            $updated_count++;
                        }
                    }
                    
                    $_SESSION['success_message'] = "Reset {$updated_count} teams to default multipliers!";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error resetting multipliers: ' . $e->getMessage();
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        $redirect_url = "teams.php" . (isset($_GET['sport']) ? "?sport=" . $_GET['sport'] : "");
        header("Location: " . $redirect_url);
        exit;
    }
}

// Get selected sport
$selected_sport = isset($_GET['sport']) ? $_GET['sport'] : 'NFL';
$valid_sports = ['NFL', 'MLB'];
if (!in_array($selected_sport, $valid_sports)) {
    $selected_sport = 'NFL';
}

// Get teams for selected sport
try {
    $teams_query = "SELECT * FROM teams WHERE sport = :sport AND is_active = 1 ORDER BY popularity_multiplier DESC, team_name ASC";
    $teams_stmt = $pdo->prepare($teams_query);
    $teams_stmt->execute([':sport' => $selected_sport]);
    $teams = $teams_stmt->fetchAll();
} catch (PDOException $e) {
    $teams = [];
    $_SESSION['error_message'] = 'Error fetching teams: ' . $e->getMessage();
}

// Calculate stats
$total_teams = count($teams);
$avg_multiplier = $total_teams > 0 ? array_sum(array_column($teams, 'popularity_multiplier')) / $total_teams : 1.0;
$min_multiplier = $total_teams > 0 ? min(array_column($teams, 'popularity_multiplier')) : 1.0;
$max_multiplier = $total_teams > 0 ? max(array_column($teams, 'popularity_multiplier')) : 1.0;

// Page variables
$page_title = 'Team Popularity Management';
$extra_scripts = '
<script>
function updateAllMultipliers(value) {
    const inputs = document.querySelectorAll("input[name^=\"multipliers[\"]");
    inputs.forEach(input => {
        input.value = parseFloat(value).toFixed(2);
    });
}

function resetToDefaults() {
    if (confirm("Are you sure you want to reset all teams to default multipliers? This will overwrite your current settings.")) {
        document.getElementById("resetForm").submit();
    }
}

function validateMultiplier(input) {
    let value = parseFloat(input.value);
    if (isNaN(value) || value < 0.5) {
        input.value = "0.50";
    } else if (value > 2.0) {
        input.value = "2.00";
    } else {
        input.value = value.toFixed(2);
    }
    
    // Update row styling based on value
    const row = input.closest("tr");
    row.classList.remove("table-danger", "table-warning", "table-info", "table-primary", "table-success");
    
    if (value >= 1.8) {
        row.classList.add("table-danger"); // 🔥 Tier 1
    } else if (value >= 1.4) {
        row.classList.add("table-warning"); // 🔥 Tier 2
    } else if (value >= 1.1) {
        row.classList.add("table-info"); // 💥 Tier 3
    } else if (value >= 0.8) {
        row.classList.add("table-primary"); // 🧊 Tier 4
    } else {
        row.classList.add("table-success"); // ❄️ Tier 5
    }
}

// Apply initial row styling
document.addEventListener("DOMContentLoaded", function() {
    const inputs = document.querySelectorAll("input[name^=\"multipliers[\"]");
    inputs.forEach(input => {
        validateMultiplier(input);
    });
});
</script>
';

// Include admin header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-3">
        <!-- Controls -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Sport Selection</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="teams.php?sport=NFL" class="btn btn-<?php echo $selected_sport === 'NFL' ? 'primary' : 'outline-primary'; ?>">
                        <i class="fas fa-football-ball me-1"></i> NFL Teams
                    </a>
                    <a href="teams.php?sport=MLB" class="btn btn-<?php echo $selected_sport === 'MLB' ? 'primary' : 'outline-primary'; ?>">
                        <i class="fas fa-baseball-ball me-1"></i> MLB Teams
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold"><?php echo $selected_sport; ?> Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="stat-value text-primary"><?php echo $total_teams; ?></div>
                        <div class="stat-label">Total Teams</div>
                    </div>
                    <div class="col-6">
                        <div class="stat-value text-info"><?php echo number_format($avg_multiplier, 2); ?></div>
                        <div class="stat-label">Average</div>
                    </div>
                </div>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="stat-value text-success"><?php echo number_format($min_multiplier, 2); ?></div>
                        <div class="stat-label">Lowest</div>
                    </div>
                    <div class="col-6">
                        <div class="stat-value text-danger"><?php echo number_format($max_multiplier, 2); ?></div>
                        <div class="stat-label">Highest</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small">Set All Teams To:</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control" id="bulk_value" step="0.01" min="0.5" max="2.0" value="1.00">
                        <button type="button" class="btn btn-outline-secondary" onclick="updateAllMultipliers(document.getElementById('bulk_value').value)">Apply</button>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <form id="resetForm" method="post" style="display: none;">
                        <input type="hidden" name="action" value="reset_to_defaults">
                        <input type="hidden" name="sport" value="<?php echo $selected_sport; ?>">
                    </form>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="resetToDefaults()">
                        <i class="fas fa-undo me-1"></i> Reset to Defaults
                    </button>
                    <a href="calculator.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calculator me-1"></i> Back to Calculator
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Hobby Market Tiers</h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="badge bg-danger me-2">🔥 Tier 1</span> 1.8-2.0 (Premium)
                </div>
                <div class="mb-2">
                    <span class="badge bg-warning me-2">🔥 Tier 2</span> 1.4-1.75 (High-Demand)
                </div>
                <div class="mb-2">
                    <span class="badge bg-info me-2">💥 Tier 3</span> 1.1-1.4 (Mid-Popularity)
                </div>
                <div class="mb-2">
                    <span class="badge bg-primary me-2">🧊 Tier 4</span> 0.8-1.0 (Low-Mid Market)
                </div>
                <div class="mb-2">
                    <span class="badge bg-success me-2">❄️ Tier 5</span> 0.5-0.8 (Low-Demand)
                </div>
                <small class="text-muted">Based on current hobby market trends</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-9">
        <!-- Teams Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold"><?php echo $selected_sport; ?> Team Popularity Multipliers</h6>
                <span class="small text-muted"><?php echo count($teams); ?> teams</span>
            </div>
            <div class="card-body">
                <?php if (empty($teams)): ?>
                    <div class="alert alert-warning">
                        No teams found for <?php echo $selected_sport; ?>. Please run the database setup first.
                        <a href="setup_database.php" class="alert-link">Setup Database</a>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="action" value="update_multipliers">
                        <input type="hidden" name="sport" value="<?php echo $selected_sport; ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">Code</th>
                                        <th>Team Name</th>
                                        <th style="width: 130px;">Multiplier</th>
                                        <th style="width: 100px;">Tier</th>
                                        <th style="width: 120px;">Example Price*</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teams as $team): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold"><?php echo htmlspecialchars($team['team_code']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                                            <td>
                                                <input type="number" 
                                                       class="form-control form-control-sm" 
                                                       name="multipliers[<?php echo $team['id']; ?>]" 
                                                       value="<?php echo number_format($team['popularity_multiplier'], 2); ?>" 
                                                       step="0.01" 
                                                       min="0.5" 
                                                       max="2.0" 
                                                       onblur="validateMultiplier(this)">
                                            </td>
                                            <td>
                                                <?php
                                                $multiplier = $team['popularity_multiplier'];
                                                if ($multiplier >= 1.8) {
                                                    echo '<span class="badge bg-danger">🔥 Tier 1</span>';
                                                } elseif ($multiplier >= 1.4) {
                                                    echo '<span class="badge bg-warning">🔥 Tier 2</span>';
                                                } elseif ($multiplier >= 1.1) {
                                                    echo '<span class="badge bg-info">💥 Tier 3</span>';
                                                } elseif ($multiplier >= 0.8) {
                                                    echo '<span class="badge bg-primary">🧊 Tier 4</span>';
                                                } else {
                                                    echo '<span class="badge bg-success">❄️ Tier 5</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-muted">
                                                $<?php echo number_format(8.00 * $team['popularity_multiplier'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">* Example based on $8.00 base price</small>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i> Save All Changes
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>