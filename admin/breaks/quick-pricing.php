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
        
        // Define tier multipliers with more precise ranges
        $tier_multipliers = [
            'premium' => 2.0,    // 🔥 Premium (Chiefs, Yankees level)
            'high' => 1.6,       // 🔥 High-Demand (Packers, Rangers level)
            'popular' => 1.3,    // 💥 Popular (Chargers, Mets level)
            'standard' => 1.0,   // 🧊 Standard (Rams, Marlins level)
            'value' => 0.8,      // ❄️ Value (Raiders, Rockies level)
            'discount' => 0.6    // 💸 Discount (bottom tier)
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
                // Include the calculateBreakPricing function
                include_once 'calculator.php';
                calculateBreakPricing($pdo, $break_id);
                
                // Get updated spot price for this team
                $spot_query = "SELECT price FROM break_spots WHERE break_id = :break_id AND team_name = (SELECT team_name FROM teams WHERE id = :team_id)";
                $spot_stmt = $pdo->prepare($spot_query);
                $spot_stmt->execute([':break_id' => $break_id, ':team_id' => $team_id]);
                $new_price = $spot_stmt->fetch()['price'] ?? 0;
                
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
            // Include the calculateBreakPricing function
            include_once 'calculator.php';
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

// If not AJAX, redirect to calculator
header('Location: calculator.php');
exit;
?>