<?php
// track_click.php - Tracks clicks on eBay listings
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Initialize response
$response = ['success' => false, 'message' => ''];

// Check if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Get the listing ID
    $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
    
    if ($listing_id > 0) {
        try {
            global $pdo;
            
            // Get the listing details
            $stmt = $pdo->prepare("SELECT id, title, sku FROM ebay_listings WHERE id = :id");
            $stmt->execute([':id' => $listing_id]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($listing) {
                // Record the click in the existing ebay_clicks table
                $stmt = $pdo->prepare("INSERT INTO ebay_clicks 
                    (listing_id, listing_title, listing_sku, click_time, user_ip, user_agent, referrer) 
                    VALUES (:listing_id, :listing_title, :listing_sku, NOW(), :user_ip, :user_agent, :referrer)");
                
                $stmt->execute([
                    ':listing_id' => $listing_id,
                    ':listing_title' => $listing['title'],
                    ':listing_sku' => $listing['sku'],
                    ':user_ip' => $_SERVER['REMOTE_ADDR'],
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ':referrer' => $_SERVER['HTTP_REFERER'] ?? null
                ]);
                
                // Update the click count in the ebay_listings table
                $pdo->exec("UPDATE ebay_listings SET click_count = click_count + 1 WHERE id = $listing_id");
                
                $response = ['success' => true, 'message' => 'Click recorded successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Listing not found'];
            }
        } catch (PDOException $e) {
            $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            error_log('eBay click tracking error: ' . $e->getMessage());
        }
    } else {
        $response = ['success' => false, 'message' => 'Invalid listing ID'];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
