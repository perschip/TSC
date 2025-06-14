<?php
// admin/paypal/coupon_stats.php
// This file displays coupon usage statistics

require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Set page title
$page_title = 'Coupon Usage Statistics';

// Get date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get coupon usage statistics
$coupon_stats = [];
try {
    // Check if coupon_id column exists in orders table
    $check_coupon_id = $pdo->query("SHOW COLUMNS FROM orders LIKE 'coupon_id'")->fetchAll();
    $coupon_id_exists = !empty($check_coupon_id);
    
    // Check if coupon_code column exists in orders table
    $check_coupon_code = $pdo->query("SHOW COLUMNS FROM orders LIKE 'coupon_code'")->fetchAll();
    $coupon_code_exists = !empty($check_coupon_code);
    
    // Check if discount_amount column exists in orders table
    $check_discount = $pdo->query("SHOW COLUMNS FROM orders LIKE 'discount_amount'")->fetchAll();
    $discount_column_exists = !empty($check_discount);
    
    // Determine the appropriate JOIN condition based on available columns
    if ($coupon_id_exists) {
        $join_condition = "c.id = o.coupon_id";
    } elseif ($coupon_code_exists) {
        $join_condition = "c.code = o.coupon_code";
    } else {
        // No direct coupon relation in orders table
        $join_condition = "1=0"; // This will result in no matches, but won't cause an error
    }
    
    // Get overall coupon usage with conditional column selection based on schema
    $query = "SELECT 
        c.id,
        c.code,
        c.discount_type,
        c.discount_value,
        c.uses_count,
        c.max_uses,
        COUNT(o.id) AS orders_count,
        SUM(IFNULL(o.total_amount, 0)) AS total_order_value,";
    
    if ($discount_column_exists) {
        $query .= "SUM(IFNULL(o.discount_amount, 0)) AS total_discount_amount,";
    } else {
        $query .= "0 AS total_discount_amount,";
    }
    
    $query .= "MIN(o.created_at) AS first_used,
        MAX(o.created_at) AS last_used
    FROM 
        coupons c
    LEFT JOIN 
        orders o ON $join_condition
    WHERE 
        (o.created_at IS NULL OR (o.created_at BETWEEN :start_date AND DATE_ADD(:end_date, INTERVAL 1 DAY)))
    GROUP BY 
        c.id
    ORDER BY 
        orders_count DESC";
    
    $stmt = $pdo->prepare($query);
    
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    $coupon_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error retrieving coupon statistics: ' . $e->getMessage();
}

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-chart-line me-2"></i> Coupon Usage Statistics</h5>
                    <div>
                        <a href="coupons.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-ticket-alt me-1"></i> Manage Coupons
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Date Range Filter -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <label for="start_date" class="col-form-label">From:</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-auto">
                                <label for="end_date" class="col-form-label">To:</label>
                            </div>
                            <div class="col-auto">
                                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Statistics Summary -->
                    <div class="row mb-4">
                        <?php
                        // Calculate summary statistics
                        $total_orders_with_coupons = 0;
                        $total_discount_amount = 0;
                        $total_order_value = 0;
                        $active_coupons = 0;
                        
                        foreach ($coupon_stats as $stat) {
                            $total_orders_with_coupons += $stat['orders_count'] ?? 0;
                            $total_discount_amount += $stat['total_discount_amount'] ?? 0;
                            $total_order_value += $stat['total_order_value'] ?? 0;
                            if ($stat['orders_count'] > 0) {
                                $active_coupons++;
                            }
                        }
                        
                        $discount_percentage = $total_order_value > 0 ? ($total_discount_amount / $total_order_value) * 100 : 0;
                        ?>
                        
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">Orders with Coupons</h6>
                                    <h2 class="mb-0"><?php echo number_format($total_orders_with_coupons); ?></h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">Total Discount Amount</h6>
                                    <h2 class="mb-0">$<?php echo number_format($total_discount_amount, 2); ?></h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">Average Discount</h6>
                                    <h2 class="mb-0"><?php echo number_format($discount_percentage, 1); ?>%</h2>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">Active Coupons</h6>
                                    <h2 class="mb-0"><?php echo number_format($active_coupons); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Coupon Usage Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Coupon Code</th>
                                    <th>Discount</th>
                                    <th>Orders</th>
                                    <th>Total Order Value</th>
                                    <th>Total Discount</th>
                                    <th>Avg. Discount %</th>
                                    <th>Usage</th>
                                    <th>First Used</th>
                                    <th>Last Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($coupon_stats)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <p class="text-muted mb-0">No coupon usage data found for the selected period.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($coupon_stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($stat['code']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($stat['discount_type'] === 'percentage'): ?>
                                                <span class="badge bg-info"><?php echo $stat['discount_value']; ?>%</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">$<?php echo number_format($stat['discount_value'], 2); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($stat['orders_count']); ?></td>
                                        <td>$<?php echo number_format($stat['total_order_value'] ?? 0, 2); ?></td>
                                        <td>$<?php echo number_format($stat['total_discount_amount'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php 
                                            $avg_discount_percent = $stat['total_order_value'] > 0 ? 
                                                ($stat['total_discount_amount'] / $stat['total_order_value']) * 100 : 0;
                                            echo number_format($avg_discount_percent, 1) . '%';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $usage_text = $stat['uses_count'] . ' used';
                                            if ($stat['max_uses'] !== null) {
                                                $usage_text .= ' / ' . $stat['max_uses'] . ' max';
                                                $usage_percent = ($stat['uses_count'] / $stat['max_uses']) * 100;
                                                
                                                echo '<div class="progress" style="height: 5px;">';
                                                echo '<div class="progress-bar ' . ($usage_percent > 80 ? 'bg-danger' : 'bg-success') . '" 
                                                    role="progressbar" style="width: ' . $usage_percent . '%;" 
                                                    aria-valuenow="' . $usage_percent . '" aria-valuemin="0" aria-valuemax="100"></div>';
                                                echo '</div>';
                                                echo '<small>' . $usage_text . '</small>';
                                            } else {
                                                echo $usage_text;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo $stat['first_used'] ? date('M j, Y', strtotime($stat['first_used'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $stat['last_used'] ? date('M j, Y', strtotime($stat['last_used'])) : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Tips for reducing bounce rate -->
                    <div class="alert alert-info mt-4">
                        <h5><i class="fas fa-lightbulb me-2"></i> Tips to Reduce Bounce Rate with Coupons</h5>
                        <ul class="mb-0">
                            <li>Create time-limited coupons to create urgency</li>
                            <li>Offer first-time visitor discounts to encourage initial purchases</li>
                            <li>Use exit-intent popups with special coupon offers to prevent users from leaving</li>
                            <li>Create targeted coupons for specific product categories</li>
                            <li>Analyze which coupon types lead to the highest conversion rates</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
