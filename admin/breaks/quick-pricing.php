<?php
// admin/breaks/quick-pricing.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Handle AJAX requests for quick pricing updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'update_team_tier') {
        $team_id = (int)$_POST['team_id'];
        $tier = $_POST['tier'];
        $break_id = (int)$_POST['break_id'];
        
        // Define tier multipliers with updated 5x range
        $tier_multipliers = [
            'premium' => 5.0,    // 🔥 Premium (Top tier: Chiefs, Yankees level)
            'high' => 4.0,       // 🔥 High-Demand (Strong popularity)
            'popular' => 3.0,    // 💥 Popular (Above average demand)
            'standard' => 2.0,   // 🧊 Standard (Average demand)
            'value' => 1.5,      // ❄️ Value (Below average)
            'discount' => 1.0    // 💸 Discount (Lowest tier)
        ];
        
        if (!isset($tier_multipliers[$tier])) {
            echo json_encode(['success' => false, 'message' => 'Invalid tier']);
            exit;
        }
        
        $multiplier = $tier_multipliers[$tier];
        
        try {
            // Update team multiplier
            $query = "UPDATE teams SET popularity_multiplier = :multiplier WHERE id = :team_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':multiplier' => $multiplier,
                ':team_id' => $team_id
            ]);
            
            // Recalculate break pricing if break_id provided
            if ($break_id > 0) {
                // Include the calculateBreakPricing function from calculator.php
                calculateBreakPricing($pdo, $break_id);
                
                // Get updated spot price for this team
                $spot_query = "SELECT price FROM break_spots WHERE break_id = :break_id AND team_name = (SELECT team_name FROM teams WHERE id = :team_id)";
                $spot_stmt = $pdo->prepare($spot_query);
                $spot_stmt->execute([':break_id' => $break_id, ':team_id' => $team_id]);
                $spot_result = $spot_stmt->fetch();
                $new_price = $spot_result ? $spot_result['price'] : 0;
                
                echo json_encode([
                    'success' => true, 
                    'new_price' => number_format($new_price, 2),
                    'new_multiplier' => $multiplier,
                    'tier' => $tier
                ]);
            } else {
                echo json_encode(['success' => true, 'new_multiplier' => $multiplier, 'tier' => $tier]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    // Handle bulk recalculation
    if (isset($_POST['action']) && $_POST['action'] === 'recalculate_break') {
        $break_id = (int)$_POST['break_id'];
        
        if ($break_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid break ID']);
            exit;
        }
        
        try {
            // Include and call the calculateBreakPricing function
            calculateBreakPricing($pdo, $break_id);
            
            // Get total count of updated spots
            $count_query = "SELECT COUNT(*) as count FROM break_spots WHERE break_id = :break_id";
            $count_stmt = $pdo->prepare($count_query);
            $count_stmt->execute([':break_id' => $break_id]);
            $spot_count = $count_stmt->fetch()['count'];
            
            echo json_encode([
                'success' => true, 
                'message' => "Updated {$spot_count} team prices successfully!",
                'spots_updated' => $spot_count
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    exit;
}

// Function to calculate break pricing with popularity multipliers (copied from calculator.php)
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

// If not AJAX, redirect to calculator
header('Location: calculator.php');
exit;
?>