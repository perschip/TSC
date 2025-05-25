<?php
// Include database connection
require_once '../includes/db.php';

// Get the track type and related ID
$type = isset($_POST['type']) ? $_POST['type'] : '';
$stream_id = isset($_POST['stream_id']) ? (int)$_POST['stream_id'] : 0;
$listing_id = isset($_POST['listing_id']) ? $_POST['listing_id'] : '';

// Get user info
$ip = $_SERVER['REMOTE_ADDR'];
$ua = $_SERVER['HTTP_USER_AGENT'];

try {
    // Track Whatnot clicks
    if ($type === 'whatnot') {
        $query = "INSERT INTO whatnot_clicks (visitor_ip, visitor_ua, stream_id, click_date) VALUES (:ip, :ua, :stream_id, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':ua', $ua);
        $stmt->bindParam(':stream_id', $stream_id);
        $stmt->execute();
    }
    
    // Track eBay clicks
    if ($type === 'ebay') {
        $query = "INSERT INTO ebay_clicks (listing_id, visitor_ip, visitor_ua, click_date) VALUES (:listing_id, :ip, :ua, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':listing_id', $listing_id);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':ua', $ua);
        $stmt->execute();
    }
    
    // Return success
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    // Return error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}