<?php
// Include database connection
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$source = isset($_POST['source']) ? trim($_POST['source']) : 'website';
$coupon = isset($_POST['coupon']) ? trim($_POST['coupon']) : '';

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

// Validate name
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your name']);
    exit;
}

try {
    // Check if the email already exists
    $check_query = "SELECT id FROM subscribers WHERE email = :email";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        // Email already exists, update the record
        $update_query = "UPDATE subscribers SET 
                        name = :name, 
                        last_updated = NOW(),
                        source = :source
                        WHERE email = :email";
        
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->bindParam(':name', $name);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':source', $source);
        $update_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Your subscription has been updated']);
    } else {
        // Create the subscribers table if it doesn't exist
        $create_table_query = "CREATE TABLE IF NOT EXISTS subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            source VARCHAR(50) DEFAULT 'website',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('active', 'unsubscribed') DEFAULT 'active'
        )";
        $pdo->exec($create_table_query);
        
        // Insert new subscriber
        $insert_query = "INSERT INTO subscribers (email, name, source) VALUES (:email, :name, :source)";
        $insert_stmt = $pdo->prepare($insert_query);
        $insert_stmt->bindParam(':email', $email);
        $insert_stmt->bindParam(':name', $name);
        $insert_stmt->bindParam(':source', $source);
        $insert_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Thank you for subscribing!']);
    }
    
    // Handle coupon code if provided (from exit intent popup)
    if (!empty($coupon) && $source === 'exit_popup') {
        try {
            // Check if the coupon already exists
            $check_coupon_query = "SELECT id FROM coupons WHERE code = :code";
            $check_coupon_stmt = $pdo->prepare($check_coupon_query);
            $check_coupon_stmt->bindParam(':code', $coupon);
            $check_coupon_stmt->execute();
            
            if ($check_coupon_stmt->rowCount() === 0) {
                // Create a new coupon in the database
                $insert_coupon_query = "INSERT INTO coupons (code, description, discount_type, discount_value, 
                                        min_purchase, start_date, end_date, max_uses, is_active, created_at) 
                                        VALUES (:code, :description, 'percentage', 15, 0, 
                                        CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), 1, 1, NOW())";
                
                $description = "Exit intent special offer for {$email}";
                
                $insert_coupon_stmt = $pdo->prepare($insert_coupon_query);
                $insert_coupon_stmt->bindParam(':code', $coupon);
                $insert_coupon_stmt->bindParam(':description', $description);
                $insert_coupon_stmt->execute();
                
                // Log the coupon creation
                error_log("Created exit intent coupon: {$coupon} for {$email}");
            }
            
            // Associate the coupon with the subscriber
            $update_subscriber_query = "UPDATE subscribers SET coupon_code = :coupon WHERE email = :email";
            $update_subscriber_stmt = $pdo->prepare($update_subscriber_query);
            $update_subscriber_stmt->bindParam(':coupon', $coupon);
            $update_subscriber_stmt->bindParam(':email', $email);
            $update_subscriber_stmt->execute();
            
        } catch (PDOException $e) {
            // Log the error but continue with the subscription process
            error_log('Coupon creation error: ' . $e->getMessage());
        }
    }
    
    // Log the subscription for analytics
    $log_query = "INSERT INTO analytics_events (event_type, event_data, ip_address, user_agent) 
                 VALUES ('newsletter_signup', :event_data, :ip, :user_agent)";
    
    $event_data = json_encode([
        'email' => $email,
        'source' => $source,
        'coupon' => $coupon
    ]);
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Check if analytics_events table exists
    try {
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->bindParam(':event_data', $event_data);
        $log_stmt->bindParam(':ip', $ip);
        $log_stmt->bindParam(':user_agent', $user_agent);
        $log_stmt->execute();
    } catch (PDOException $e) {
        // Table might not exist, ignore this error
    }
    
} catch (PDOException $e) {
    // Log the error
    error_log('Newsletter subscription error: ' . $e->getMessage());
    
    // Return a generic error message to the user
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>
