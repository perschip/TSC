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
                        
                        // Validate multiplier range (updated to 5.0 max)
                        if ($multiplier >= 0.5 && $multiplier <= 5.0) {
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
                
                // Updated default multipliers with new 5x scale
                $default_multipliers = [
                    'NFL' => [
                        // 🔥 Tier 1: Premium Teams (4.5-5.0)
                        'KC' => 5.0,   // Mahomes is the absolute face of the hobby
                        'SF' => 4.8,   // Purdy + strong roster = top tier
                        'CHI' => 4.7,  // Caleb Williams mania in huge market
                        'DET' => 4.6,  // Gibbs, Amon-Ra, LaPorta = incredible rookie depth
                        'HOU' => 4.5,  // CJ Stroud + Tank Dell + Will Anderson
                        
                        // 🔥 Tier 2: High-Demand Teams (3.5-4.4)
                        'PHI' => 4.3,  // Jalen Hurts, DeVonta, AJ Brown
                        'CIN' => 4.2,  // Burrow + Chase = always sought after
                        'BUF' => 4.0,  // Allen + Kincaid still very popular
                        'MIN' => 3.9,  // JJ + McCarthy rookie hype
                        'PIT' => 3.8,  // Iconic brand, strong fan loyalty
                        'GB' => 3.7,   // Jordan Love breakthrough + history
                        'BAL' => 3.6,  // Lamar + Zay Flowers
                        'ATL' => 3.5,  // Bijan + Penix + London
                        
                        // 💥 Tier 3: Popular Teams (2.5-3.4)
                        'DAL' => 3.3,  // National team appeal
                        'IND' => 3.2,  // Anthony Richardson return
                        'ARI' => 3.1,  // Marvin Harrison Jr. chase
                        'LAC' => 3.0,  // Herbert still relevant
                        'NYJ' => 2.9,  // Rodgers + Wilson + market
                        'TEN' => 2.8,  // Will Levis upside
                        'NYG' => 2.7,  // Large market
                        'DEN' => 2.6,  // Nix rookie QB
                        'CLE' => 2.5,  // Defense-heavy but steady
                        
                        // 🧊 Tier 4: Standard Teams (1.5-2.4)
                        'LAR' => 2.3,  // Puka + Stafford
                        'SEA' => 2.2,  // JSN + strong following
                        'TB' => 2.1,   // Mayfield + Evans/Godwin
                        'WAS' => 2.0,  // Jayden Daniels potential
                        'NE' => 1.9,   // Maye might help rebound
                        'CAR' => 1.8,  // Young struggled but upside
                        'JAX' => 1.7,  // Lawrence + Etienne
                        'LV' => 1.6,   // Waiting for QB breakout
                        'NO' => 1.5,   // Aging but still Saints
                        
                        // ❄️ Tier 5: Value Teams (1.0-1.4)
                        'MIA' => 1.2,  // Tua/Waddle/Hill but hobby demand lags
                    ],
                    'MLB' => [
                        // 🔥 Tier 1: Premium Teams (4.5-5.0)
                        'NYY' => 5.0,  // Perennial #1 seller, huge fanbase, Jasson Domínguez
                        'LAD' => 4.9,  // Ohtani + Mookie + Yamamoto = national chase
                        'BAL' => 4.8,  // Gunnar + Adley + Holliday = Bowman kings
                        'ATL' => 4.7,  // Acuña + Harris + Strider + top farm
                        'CIN' => 4.5,  // Elly De La Cruz + CES + deep young core
                        
                        // 🔥 Tier 2: High Hobby Appeal (3.5-4.4)
                        'SEA' => 4.3,  // Julio Rodríguez = top-tier pull
                        'TEX' => 4.2,  // World Series champs + Carter + Langford
                        'DET' => 4.1,  // Jobe + Jung + Keith + Meadows
                        'SD' => 4.0,   // Tatis + Jackson Merrill + west coast
                        'CHC' => 3.9,  // Crow-Armstrong + huge market
                        'TB' => 3.8,   // Wander + tons of prospect hits
                        'BOS' => 3.7,  // Strong base + Mayer + Anthony
                        'PIT' => 3.6,  // Paul Skenes + Termarr Johnson
                        'ARI' => 3.5,  // Carroll + Lawlar + NL champs
                        
                        // 💥 Tier 3: Solid Mid-Tier Teams (2.5-3.4)
                        'NYM' => 3.3,  // Strong fanbase, large market
                        'STL' => 3.2,  // Classic brand + Walker + Winn
                        'CLE' => 3.1,  // Espino + Manzardo + depth
                        'TOR' => 3.0,  // Vladdy Jr. + Schneider
                        'MIL' => 2.9,  // Chourio hobby heat + Quero
                        'PHI' => 2.8,  // Harper + Bohm + Painter
                        'CWS' => 2.7,  // Montgomery + Colás prospects
                        'HOU' => 2.6,  // Some aging but still Astros brand
                        'WAS' => 2.5,  // Dylan Crews long-term help
                        
                        // 🧊 Tier 4: Standard Teams (1.5-2.4)
                        'SF' => 2.3,   // Solid legacy, some prospects
                        'MIA' => 2.2,  // Pérez + prospects
                        'LAA' => 2.1,  // Post-Ohtani but Neto + O'Hoppe
                        'KC' => 2.0,   // Witt strong but limited hobby love
                        'MIN' => 1.9,  // Brooks Lee + Lewis upside
                        'COL' => 1.8,  // Weak market but some young talent
                        'OAK' => 1.5,  // Very small hobby audience
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
                    
                    $_SESSION['success_message'] = "Reset {$updated_count} teams to updated default multipliers (5x scale)!";
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
        input.value = parseFloat(value).toFixed(1);
    });
}

function resetToDefaults() {
    if (confirm("Are you sure you want to reset all teams to updated default multipliers (5x scale)? This will overwrite your current settings.")) {
        document.getElementById("resetForm").submit();
    }
}

function validateMultiplier(input) {
    let value = parseFloat(input.value);
    if (isNaN(value) || value < 1.0) {
        input.value = "1.0";
    } else if (value > 5.0) {
        input.value = "5.0";
    } else {
        input.value = value.toFixed(1);
    }
    
    // Update row styling based on value
    const row = input.closest("tr");
    row.classList.remove("table-danger", "table-warning", "table-info", "table-primary", "table-success");
    
    if (value >= 4.5) {
        row.classList.add("table-danger"); // 🔥 Tier 1: Premium
    } else if (value >= 3.5) {
        row.classList.add("table-warning"); // 🔥 Tier 2: High-Demand
    } else if (value >= 2.5) {
        row.classList.add("table-info"); // 💥 Tier 3: Popular
    } else if (value >= 1.5) {
        row.classList.add("table-primary"); // 🧊 Tier 4: Standard
    } else {
        row.classList.add("table-success"); // ❄️ Tier 5: Value
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
                        <div class="stat-value text-info"><?php echo number_format($avg_multiplier, 1); ?></div>
                        <div class="stat-label">Average</div>
                    </div>
                </div>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="stat-value text-success"><?php echo number_format($min_multiplier, 1); ?></div>
                        <div class="stat-label">Lowest</div>
                    </div>
                    <div class="col-6">
                        <div class="stat-value text-danger"><?php echo number_format($max_multiplier, 1); ?></div>
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
                        <input type="number" class="form-control" id="bulk_value" step="0.1" min="1.0" max="5.0" value="2.0">
                        <button type="button" class="btn btn-outline-secondary" onclick="updateAllMultipliers(document.getElementById('bulk_value').value)">Apply</button>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <form id="resetForm" method="post" style="display: none;">
                        <input type="hidden" name="action" value="reset_to_defaults">
                        <input type="hidden" name="sport" value="<?php echo $selected_sport; ?>">
                    </form>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="resetToDefaults()">
                        <i class="fas fa-undo me-1"></i> Reset to Updated Defaults (5x)
                    </button>
                    <a href="calculator.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calculator me-1"></i> Back to Calculator
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Updated Legend -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Updated Hobby Market Tiers</h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="badge bg-danger me-2">🔥 Tier 1</span> 4.5-5.0 (Premium)
                </div>
                <div class="mb-2">
                    <span class="badge bg-warning me-2">🔥 Tier 2</span> 3.5-4.4 (High-Demand)
                </div>
                <div class="mb-2">
                    <span class="badge bg-info me-2">💥 Tier 3</span> 2.5-3.4 (Popular)
                </div>
                <div class="mb-2">
                    <span class="badge bg-primary me-2">🧊 Tier 4</span> 1.5-2.4 (Standard)
                </div>
                <div class="mb-2">
                    <span class="badge bg-success me-2">❄️ Tier 5</span> 1.0-1.4 (Value)
                </div>
                <small class="text-muted">Updated with 5x max multiplier range</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-9">
        <!-- Teams Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold"><?php echo $selected_sport; ?> Team Popularity Multipliers (Updated 5x Scale)</h6>
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
                                                       value="<?php echo number_format($team['popularity_multiplier'], 1); ?>" 
                                                       step="0.1" 
                                                       min="1.0" 
                                                       max="5.0" 
                                                       onblur="validateMultiplier(this)">
                                            </td>
                                            <td>
                                                <?php
                                                $multiplier = $team['popularity_multiplier'];
                                                if ($multiplier >= 4.5) {
                                                    echo '<span class="badge bg-danger">🔥 Tier 1</span>';
                                                } elseif ($multiplier >= 3.5) {
                                                    echo '<span class="badge bg-warning">🔥 Tier 2</span>';
                                                } elseif ($multiplier >= 2.5) {
                                                    echo '<span class="badge bg-info">💥 Tier 3</span>';
                                                } elseif ($multiplier >= 1.5) {
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