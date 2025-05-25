<?php
// admin/breaks/export.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Get break ID
$break_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$break_id) {
    die('Invalid break ID');
}

try {
    // Get break details
    $break_query = "SELECT * FROM breaks WHERE id = :break_id";
    $break_stmt = $pdo->prepare($break_query);
    $break_stmt->execute([':break_id' => $break_id]);
    $break = $break_stmt->fetch();
    
    if (!$break) {
        die('Break not found');
    }
    
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
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Set headers for download
$filename = 'break_' . $break_id . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// Create file handle
$output = fopen('php://output', 'w');

// Write break summary
fputcsv($output, ['BREAK SUMMARY']);
fputcsv($output, ['Break Name:', $break['name']]);
fputcsv($output, ['Sport:', $break['sport']]);
fputcsv($output, ['Break Type:', ucfirst(str_replace('_', ' ', $break['break_type']))]);
fputcsv($output, ['Total Cost:', '$' . number_format($break['total_cost'], 2)]);
fputcsv($output, ['Profit Margin:', $break['profit_margin'] . '%']);
fputcsv($output, ['Spot Price:', '$' . number_format($break['spot_price'], 2)]);
fputcsv($output, ['Total Spots:', $break['total_spots']]);
fputcsv($output, ['Expected Profit:', '$' . number_format(($break['spot_price'] * $break['total_spots']) - $break['total_cost'], 2)]);
fputcsv($output, ['Created:', date('F j, Y', strtotime($break['created_at']))]);
fputcsv($output, []);

// Write boxes section
fputcsv($output, ['BOXES IN BREAK']);
fputcsv($output, ['Box Name', 'Quantity', 'Cost Per Box', 'Total Cost']);
foreach ($boxes as $box) {
    fputcsv($output, [
        $box['box_name'],
        $box['quantity'],
        '$' . number_format($box['cost_per_box'], 2),
        '$' . number_format($box['total_cost'], 2)
    ]);
}
fputcsv($output, []);

// Write spots section
fputcsv($output, ['TEAM SPOT PRICES']);
fputcsv($output, ['Team Code', 'Team Name', 'Price', 'Status', 'Buyer Name', 'Buyer Email']);
foreach ($spots as $spot) {
    fputcsv($output, [
        $spot['team_code'],
        $spot['team_name'],
        '$' . number_format($spot['price'], 2),
        $spot['is_sold'] ? 'SOLD' : 'AVAILABLE',
        $spot['buyer_name'] ?? '',
        $spot['buyer_email'] ?? ''
    ]);
}

fclose($output);
exit;
?>