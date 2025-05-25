<?php
// admin/breaks/calculator.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_break':
                $name = trim($_POST['break_name']);
                $sport = $_POST['sport'];
                $break_type = $_POST['break_type'];
                $profit_margin = (float)$_POST['profit_margin'];
                
                try {
                    $query = "INSERT INTO breaks (name, sport, break_type, profit_margin) VALUES (:name, :sport, :break_type, :profit_margin)";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        ':name' => $name,
                        ':sport' => $sport,
                        ':break_type' => $break_type,
                        ':profit_margin' => $profit_margin
                    ]);
                    
                    $break_id = $pdo->lastInsertId();
                    $_SESSION['success_message'] = 'Break created successfully!';
                    header("Location: calculator.php?edit=" . $break_id);
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error creating break: ' . $e->getMessage();
                }
            case 'delete_break':
                $break_id = (int)$_POST['break_id'];
                
                try {
                    // Delete the break (cascade will handle related records)
                    $query = "DELETE FROM breaks WHERE id = :break_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':break_id' => $break_id]);
                    
                    $_SESSION['success_message'] = 'Break deleted successfully!';
                    header("Location: calculator.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error deleting break: ' . $e->getMessage();
                }
                break;
                
            case 'add_box':
                $break_id = (int)$_POST['break_id'];
                $box_name = trim($_POST['box_name']);
                $quantity = (int)$_POST['quantity'];
                $cost_per_box = (float)$_POST['cost_per_box'];
                
                try {
                    $query = "INSERT INTO break_boxes (break_id, box_name, quantity, cost_per_box) VALUES (:break_id, :box_name, :quantity, :cost_per_box)";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        ':break_id' => $break_id,
                        ':box_name' => $box_name,
                        ':quantity' => $quantity,
                        ':cost_per_box' => $cost_per_box
                    ]);
                    
                    // Recalculate break totals
                    calculateBreakPricing($pdo, $break_id);
                    
                    $_SESSION['success_message'] = 'Box added successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error adding box: ' . $e->getMessage();
                }
                break;
                
            case 'delete_box':
                $box_id = (int)$_POST['box_id'];
                $break_id = (int)$_POST['break_id'];
                
                try {
                    $query = "DELETE FROM break_boxes WHERE id = :box_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':box_id' => $box_id]);
                    
                    // Recalculate break totals
                    calculateBreakPricing($pdo, $break_id);
                    
                    $_SESSION['success_message'] = 'Box removed successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error removing box: ' . $e->getMessage();
                }
                break;
                
            case 'update_profit_margin':
                $break_id = (int)$_POST['break_id'];
                $profit_margin = (float)$_POST['profit_margin'];
                $custom_modifier = (float)$_POST['custom_modifier'];
                
                try {
                    $query = "UPDATE breaks SET profit_margin = :profit_margin, custom_modifier = :custom_modifier WHERE id = :break_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        ':profit_margin' => $profit_margin,
                        ':custom_modifier' => $custom_modifier,
                        ':break_id' => $break_id
                    ]);
                    
                    // Recalculate break totals
                    calculateBreakPricing($pdo, $break_id);
                    
                    $_SESSION['success_message'] = 'Settings updated successfully!';
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = 'Error updating settings: ' . $e->getMessage();
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        $redirect_url = isset($_GET['edit']) ? "calculator.php?edit=" . $_GET['edit'] : "calculator.php";
        header("Location: " . $redirect_url);
        exit;
    }
}

// Function to calculate break pricing with popularity multipliers
function calculateBreakPricing($pdo, $break_id) {
    // Get total cost of all boxes
    $cost_query = "SELECT SUM(total_cost) as total_cost FROM break_boxes WHERE break_id = :break_id";
    $cost_stmt = $pdo->prepare($cost_query);
    $cost_stmt->execute([':break_id' => $break_id]);
    $total_cost = $cost_stmt->fetch()['total_cost'] ?? 0;
    
    // Get break info
    $break_query = "SELECT * FROM breaks WHERE id = :break_id";
    $break_stmt = $pdo->prepare($break_query);
    $break_stmt->execute([':break_id' => $break_id]);
    $break = $break_stmt->fetch();
    
    if ($break && $total_cost > 0) {
        $sport = $break['sport'];
        $profit_margin = $break['profit_margin'];
        
        // Calculate total with profit and custom modifier
        $total_with_profit = $total_cost * (1 + ($profit_margin / 100));
        $custom_modifier = $break['custom_modifier'];
        $final_total = $total_with_profit * (1 + ($custom_modifier / 100));
        
        // Get teams with their popularity multipliers
        $teams_query = "SELECT team_name, team_code, popularity_multiplier FROM teams WHERE sport = :sport AND is_active = 1 ORDER BY team_name";
        $teams_stmt = $pdo->prepare($teams_query);
        $teams_stmt->execute([':sport' => $sport]);
        $teams = $teams_stmt->fetchAll();
        
        $team_count = count($teams);
        
        if ($team_count > 0) {
            // Calculate the sum of all multipliers to determine base price
            $total_multiplier = array_sum(array_column($teams, 'popularity_multiplier'));
            
            // Base price calculation: total revenue needed divided by sum of multipliers
            $base_price = $total_with_profit / $total_multiplier;
            
            // Calculate average spot price for display
            $avg_spot_price = $total_with_profit / $team_count;
            
            // Update break record with average price
            $update_query = "UPDATE breaks SET total_cost = :total_cost, spot_price = :spot_price, total_spots = :total_spots WHERE id = :break_id";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([
                ':total_cost' => $total_cost,
                ':spot_price' => $avg_spot_price,
                ':total_spots' => $team_count,
                ':break_id' => $break_id
            ]);
            
            // Delete existing spots
            $delete_spots = "DELETE FROM break_spots WHERE break_id = :break_id";
            $delete_stmt = $pdo->prepare($delete_spots);
            $delete_stmt->execute([':break_id' => $break_id]);
            
            // Insert new spots with individual pricing
            foreach ($teams as $team) {
                $team_price = $base_price * $team['popularity_multiplier'];
                
                $spot_query = "INSERT INTO break_spots (break_id, team_name, team_code, price) VALUES (:break_id, :team_name, :team_code, :price)";
                $spot_stmt = $pdo->prepare($spot_query);
                $spot_stmt->execute([
                    ':break_id' => $break_id,
                    ':team_name' => $team['team_name'],
                    ':team_code' => $team['team_code'],
                    ':price' => $team_price
                ]);
            }
        }
    }
}

// Get current break if editing
$current_break = null;
$boxes = [];
$spots = [];

if (isset($_GET['edit'])) {
    $break_id = (int)$_GET['edit'];
    
    // Get break details
    $break_query = "SELECT * FROM breaks WHERE id = :break_id";
    $break_stmt = $pdo->prepare($break_query);
    $break_stmt->execute([':break_id' => $break_id]);
    $current_break = $break_stmt->fetch();
    
    if ($current_break) {
        // Get boxes
        $boxes_query = "SELECT * FROM break_boxes WHERE break_id = :break_id ORDER BY created_at";
        $boxes_stmt = $pdo->prepare($boxes_query);
        $boxes_stmt->execute([':break_id' => $break_id]);
        $boxes = $boxes_stmt->fetchAll();
        
        // Get spots
        $spots_query = "SELECT * FROM break_spots WHERE break_id = :break_id ORDER BY team_name";
        $spots_stmt = $pdo->prepare($spots_query);
        $spots_stmt->execute([':break_id' => $break_id]);
        $spots = $spots_stmt->fetchAll();
    }
}

// Get all breaks for the list
$breaks_query = "SELECT b.*, 
                 (SELECT COUNT(*) FROM break_boxes WHERE break_id = b.id) as box_count,
                 (SELECT SUM(total_cost) FROM break_boxes WHERE break_id = b.id) as total_cost_calc
                 FROM breaks b 
                 ORDER BY b.created_at DESC";
$breaks_stmt = $pdo->prepare($breaks_query);
$breaks_stmt->execute();
$all_breaks = $breaks_stmt->fetchAll();

// Page variables
$page_title = 'Break Spot Calculator';
$extra_scripts = '
<script>
function deleteBox(boxId, breakId) {
    if (confirm("Are you sure you want to remove this box?")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_box">
            <input type="hidden" name="box_id" value="${boxId}">
            <input type="hidden" name="break_id" value="${breakId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteBreak(breakId, breakName) {
    if (confirm(`Are you sure you want to delete the break "${breakName}"?\n\nThis will permanently remove:\n• All boxes in this break\n• All team spot data\n• All associated records\n\nThis action cannot be undone!`)) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_break">
            <input type="hidden" name="break_id" value="${breakId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function formatCurrency(input) {
    let value = parseFloat(input.value);
    if (!isNaN(value)) {
        input.value = value.toFixed(2);
    }
}

// Auto-calculate totals
document.addEventListener("DOMContentLoaded", function() {
    const quantityInput = document.getElementById("quantity");
    const costInput = document.getElementById("cost_per_box");
    const totalSpan = document.getElementById("box_total");
    
    function calculateTotal() {
        const quantity = parseFloat(quantityInput?.value) || 0;
        const cost = parseFloat(costInput?.value) || 0;
        const total = quantity * cost;
        if (totalSpan) {
            totalSpan.textContent = "$" + total.toFixed(2);
        }
    }
    
    if (quantityInput && costInput) {
        quantityInput.addEventListener("input", calculateTotal);
        costInput.addEventListener("input", calculateTotal);
    }
});
</script>
';

// Include admin header
include_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-4">
        <!-- Create New Break -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">
                    <?php echo $current_break ? 'Edit Break: ' . htmlspecialchars($current_break['name']) : 'Create New Break'; ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if (!$current_break): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="create_break">
                        
                        <div class="mb-3">
                            <label for="break_name" class="form-label">Break Name</label>
                            <input type="text" class="form-control" id="break_name" name="break_name" required 
                                   placeholder="e.g., Phoenix Football Break #1">
                        </div>
                        
                        <div class="mb-3">
                            <label for="sport" class="form-label">Sport</label>
                            <select class="form-select" id="sport" name="sport" required>
                                <option value="NFL">NFL (32 teams)</option>
                                <option value="MLB">MLB (30 teams)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="break_type" class="form-label">Break Type</label>
                            <select class="form-select" id="break_type" name="break_type" required>
                                <option value="team">Team Break</option>
                                <option value="division">Division Break</option>
                                <option value="hit_draft">Hit Draft</option>
                                <option value="random">Random</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="profit_margin" class="form-label">Profit Margin (%)</label>
                            <input type="number" class="form-control" id="profit_margin" name="profit_margin" 
                                   value="25" min="0" max="100" step="0.1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="custom_modifier" class="form-label">Custom Modifier (%)</label>
                            <input type="number" class="form-control" id="custom_modifier" name="custom_modifier" 
                                   value="0" min="-50" max="50" step="0.1">
                            <div class="form-text">Additional adjustment (±50%). Use for special circumstances like holidays, premium products, etc.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Create Break</button>
                    </form>
                <?php else: ?>
                    <!-- Update Profit Margin and Custom Modifier -->
                    <form method="post" class="mb-3">
                        <input type="hidden" name="action" value="update_profit_margin">
                        <input type="hidden" name="break_id" value="<?php echo $current_break['id']; ?>">
                        
                        <div class="mb-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Profit %</span>
                                <input type="number" class="form-control" name="profit_margin" 
                                       value="<?php echo $current_break['profit_margin']; ?>" 
                                       min="0" max="100" step="0.1" required>
                                <button type="submit" class="btn btn-outline-primary">Update</button>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Custom %</span>
                                <input type="number" class="form-control" name="custom_modifier" 
                                       value="<?php echo $current_break['custom_modifier'] ?? 0; ?>" 
                                       min="-50" max="50" step="0.1">
                            </div>
                            <small class="text-muted">Custom modifier for special circumstances</small>
                        </div>
                    </form>
                    
                    <!-- Add Box Form -->
                    <form method="post">
                        <input type="hidden" name="action" value="add_box">
                        <input type="hidden" name="break_id" value="<?php echo $current_break['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="box_name" class="form-label">Box Name</label>
                            <input type="text" class="form-control" id="box_name" name="box_name" required 
                                   placeholder="e.g., Phoenix Blaster">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                       value="1" min="1" required>
                            </div>
                            <div class="col-6">
                                <label for="cost_per_box" class="form-label">Cost Per Box</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="cost_per_box" name="cost_per_box" 
                                           step="0.01" min="0" required onblur="formatCurrency(this)">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">Total: <span id="box_total">$0.00</span></small>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">Add Box</button>
                    </form>
                    
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="calculator.php" class="btn btn-outline-secondary">← Back to Breaks List</a>
                        <button type="button" class="btn btn-outline-danger" onclick="deleteBreak(<?php echo $current_break['id']; ?>, '<?php echo htmlspecialchars($current_break['name']); ?>')">
                            <i class="fas fa-trash me-1"></i> Delete Break
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Break Summary -->
        <?php if ($current_break): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Break Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="stat-value text-danger">$<?php echo number_format($current_break['total_cost'], 2); ?></div>
                            <div class="stat-label">Total Cost</div>
                        </div>
                        <div class="col-6">
                            <div class="stat-value text-success">$<?php echo number_format($current_break['spot_price'], 2); ?></div>
                            <div class="stat-label">Avg Price Per Spot</div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="stat-value text-primary"><?php echo $current_break['total_spots']; ?></div>
                            <div class="stat-label">Total Spots</div>
                        </div>
                        <div class="col-4">
                            <div class="stat-value text-info"><?php echo $current_break['profit_margin']; ?>%</div>
                            <div class="stat-label">Profit</div>
                        </div>
                        <div class="col-4">
                            <div class="stat-value text-warning"><?php echo $current_break['custom_modifier'] ?? 0; ?>%</div>
                            <div class="stat-label">Custom</div>
                        </div>
                    </div>
                    
                    <?php if ($current_break['spot_price'] > 0): ?>
                        <hr>
                        <div class="text-center">
                            <div class="stat-value text-success">$<?php echo number_format(($current_break['spot_price'] * $current_break['total_spots']) - $current_break['total_cost'], 2); ?></div>
                            <div class="stat-label">Expected Profit</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-8">
        <?php if ($current_break): ?>
            <!-- Boxes List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Boxes in Break</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($boxes)): ?>
                        <div class="alert alert-info">
                            No boxes added yet. Use the form on the left to add boxes to this break.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Box Name</th>
                                        <th>Quantity</th>
                                        <th>Cost Per Box</th>
                                        <th>Total Cost</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($boxes as $box): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($box['box_name']); ?></td>
                                            <td><?php echo $box['quantity']; ?></td>
                                            <td>$<?php echo number_format($box['cost_per_box'], 2); ?></td>
                                            <td>$<?php echo number_format($box['total_cost'], 2); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteBox(<?php echo $box['id']; ?>, <?php echo $current_break['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <th colspan="3">Total</th>
                                        <th>$<?php echo number_format($current_break['total_cost'], 2); ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Team Spots with Quick Pricing -->
            <?php if (!empty($spots)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Team Spot Prices</h6>
                        <div class="small">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickPricingModal">
                                <i class="fas fa-magic me-1"></i> Quick Pricing
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php 
                            // Sort spots by price (highest to lowest)
                            usort($spots, function($a, $b) {
                                return $b['price'] <=> $a['price'];
                            });
                            
                            foreach ($spots as $spot): 
                                // Get the team's actual multiplier for accurate tier detection
                                $team_query = "SELECT popularity_multiplier FROM teams WHERE team_code = :team_code AND sport = :sport";
                                $team_stmt = $pdo->prepare($team_query);
                                $team_stmt->execute([':team_code' => $spot['team_code'], ':sport' => $current_break['sport']]);
                                $team_multiplier = $team_stmt->fetch()['popularity_multiplier'] ?? 1.0;
                                
                                // Determine price tier based on multiplier, not price
                                if ($team_multiplier >= 1.95) {
                                    $tier_class = 'border-danger';
                                    $tier_badge = 'bg-danger';
                                    $tier_text = '🔥 Premium';
                                } elseif ($team_multiplier >= 1.45) {
                                    $tier_class = 'border-warning';
                                    $tier_badge = 'bg-warning';
                                    $tier_text = '🔥 High';
                                } elseif ($team_multiplier >= 1.15) {
                                    $tier_class = 'border-info';
                                    $tier_badge = 'bg-info';
                                    $tier_text = '💥 Popular';
                                } elseif ($team_multiplier >= 0.85) {
                                    $tier_class = 'border-primary';
                                    $tier_badge = 'bg-primary';
                                    $tier_text = '🧊 Standard';
                                } elseif ($team_multiplier >= 0.65) {
                                    $tier_class = 'border-success';
                                    $tier_badge = 'bg-success';
                                    $tier_text = '❄️ Value';
                                } else {
                                    $tier_class = 'border-secondary';
                                    $tier_badge = 'bg-secondary';
                                    $tier_text = '💸 Discount';
                                }
                                
                                $price = $spot['price'];
                            ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card <?php echo $tier_class; ?> h-100" id="team-card-<?php echo $spot['team_code']; ?>">
                                        <div class="card-body py-2 px-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($spot['team_code']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($spot['team_name']); ?></div>
                                                    <span class="badge <?php echo $tier_badge; ?> mt-1"><?php echo $tier_text; ?></span>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold text-success fs-5" id="price-<?php echo $spot['team_code']; ?>">$<?php echo number_format($spot['price'], 2); ?></div>
                                                    <?php if ($spot['is_sold']): ?>
                                                        <span class="badge bg-success">SOLD</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-outline-secondary">AVAILABLE</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pricing Summary -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Pricing Summary</h6>
                                        <div class="row text-center">
                                            <?php
                                            $prices = array_column($spots, 'price');
                                            $min_price = min($prices);
                                            $max_price = max($prices);
                                            $avg_price = array_sum($prices) / count($prices);
                                            $total_revenue = array_sum($prices);
                                            ?>
                                            <div class="col-3">
                                                <div class="stat-value text-success">$<?php echo number_format($min_price, 2); ?></div>
                                                <div class="stat-label">Lowest</div>
                                            </div>
                                            <div class="col-3">
                                                <div class="stat-value text-primary">$<?php echo number_format($avg_price, 2); ?></div>
                                                <div class="stat-label">Average</div>
                                            </div>
                                            <div class="col-3">
                                                <div class="stat-value text-danger">$<?php echo number_format($max_price, 2); ?></div>
                                                <div class="stat-label">Highest</div>
                                            </div>
                                            <div class="col-3">
                                                <div class="stat-value text-info">$<?php echo number_format($total_revenue, 2); ?></div>
                                                <div class="stat-label">Total Revenue</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Pricing Modal -->
                <div class="modal fade" id="quickPricingModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Quick Team Pricing</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="fas fa-magic me-2"></i>
                                            <strong>Quick Pricing:</strong> Select a team and choose a pricing tier. Prices will update automatically!
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Tier Reference -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6>Pricing Tiers:</h6>
                                        <div class="row text-center">
                                            <div class="col-2">
                                                <span class="badge bg-danger w-100">🔥 Premium</span>
                                                <small class="d-block">2.0x</small>
                                            </div>
                                            <div class="col-2">
                                                <span class="badge bg-warning w-100">🔥 High</span>
                                                <small class="d-block">1.6x</small>
                                            </div>
                                            <div class="col-2">
                                                <span class="badge bg-info w-100">💥 Popular</span>
                                                <small class="d-block">1.3x</small>
                                            </div>
                                            <div class="col-2">
                                                <span class="badge bg-primary w-100">🧊 Standard</span>
                                                <small class="d-block">1.0x</small>
                                            </div>
                                            <div class="col-2">
                                                <span class="badge bg-success w-100">❄️ Value</span>
                                                <small class="d-block">0.8x</small>
                                            </div>
                                            <div class="col-2">
                                                <span class="badge bg-secondary w-100">💸 Discount</span>
                                                <small class="d-block">0.6x</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Team Selection -->
                                <div class="row">
                                    <?php
                                    // Get teams with current multipliers for the modal
                                    $teams_query = "SELECT t.*, bs.price FROM teams t 
                                                   LEFT JOIN break_spots bs ON t.team_name = bs.team_name AND bs.break_id = :break_id
                                                   WHERE t.sport = :sport AND t.is_active = 1 
                                                   ORDER BY bs.price DESC";
                                    $teams_stmt = $pdo->prepare($teams_query);
                                    $teams_stmt->execute([':break_id' => $current_break['id'], ':sport' => $current_break['sport']]);
                                    $modal_teams = $teams_stmt->fetchAll();
                                    
                                    foreach ($modal_teams as $team):
                                        // Fix tier detection based on multiplier
                                        $multiplier = $team['popularity_multiplier'];
                                        if ($multiplier >= 1.95) {
                                            $current_tier = 'premium';
                                        } elseif ($multiplier >= 1.45) {
                                            $current_tier = 'high';
                                        } elseif ($multiplier >= 1.15) {
                                            $current_tier = 'popular';
                                        } elseif ($multiplier >= 0.85) {
                                            $current_tier = 'standard';
                                        } elseif ($multiplier >= 0.65) {
                                            $current_tier = 'value';
                                        } else {
                                            $current_tier = 'discount';
                                        }
                                    ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-body py-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?php echo $team['team_code']; ?></strong>
                                                            <div class="small text-muted"><?php echo $team['team_name']; ?></div>
                                                            <small class="text-info" id="modal-price-<?php echo $team['id']; ?>">$<?php echo number_format($team['price'], 2); ?></small>
                                                        </div>
                                                        <div>
                                                            <select class="form-select form-select-sm tier-selector" 
                                                                    data-team-id="<?php echo $team['id']; ?>" 
                                                                    data-team-code="<?php echo $team['team_code']; ?>">
                                                                <option value="premium" <?php echo $current_tier === 'premium' ? 'selected' : ''; ?>>🔥 Premium</option>
                                                                <option value="high" <?php echo $current_tier === 'high' ? 'selected' : ''; ?>>🔥 High</option>
                                                                <option value="popular" <?php echo $current_tier === 'popular' ? 'selected' : ''; ?>>💥 Popular</option>
                                                                <option value="standard" <?php echo $current_tier === 'standard' ? 'selected' : ''; ?>>🧊 Standard</option>
                                                                <option value="value" <?php echo $current_tier === 'value' ? 'selected' : ''; ?>>❄️ Value</option>
                                                                <option value="discount" <?php echo $current_tier === 'discount' ? 'selected' : ''; ?>>💸 Discount</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-warning" id="updateAllPrices">
                                    <i class="fas fa-sync me-1"></i> Update All Prices
                                </button>
                                <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                                    <i class="fas fa-refresh me-1"></i> Refresh Page
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- All Breaks List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">All Breaks</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($all_breaks)): ?>
                        <div class="alert alert-info">
                            No breaks created yet. Use the form on the left to create your first break.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Break Name</th>
                                        <th>Sport</th>
                                        <th>Type</th>
                                        <th>Boxes</th>
                                        <th>Total Cost</th>
                                        <th>Profit %</th>
                                        <th>Spot Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_breaks as $break): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($break['name']); ?></div>
                                                <div class="small text-muted"><?php echo date('M j, Y', strtotime($break['created_at'])); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $break['sport']; ?></span>
                                            </td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $break['break_type'])); ?></td>
                                            <td><?php echo $break['box_count']; ?></td>
                                            <td>$<?php echo number_format($break['total_cost'], 2); ?></td>
                                            <td><?php echo $break['profit_margin']; ?>%</td>
                                            <td>
                                                <?php if ($break['spot_price'] > 0): ?>
                                                    <span class="fw-bold text-success">$<?php echo number_format($break['spot_price'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $break['status'] === 'active' ? 'success' : ($break['status'] === 'draft' ? 'secondary' : 'warning'); ?>">
                                                    <?php echo ucfirst($break['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="calculator.php?edit=<?php echo $break['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Break">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="export.php?id=<?php echo $break['id']; ?>" class="btn btn-sm btn-outline-success" target="_blank" title="Export CSV">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteBreak(<?php echo $break['id']; ?>, '<?php echo htmlspecialchars($break['name']); ?>')" 
                                                            title="Delete Break">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>