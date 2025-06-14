<?php
// checkout/remove_coupon.php
// This file handles removing a coupon from the session

session_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Check if request is AJAX and POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove coupon from session
    if (isset($_SESSION['coupon_code']) || isset($_SESSION['coupon_discount']) || isset($_SESSION['coupon_percent'])) {
        unset($_SESSION['coupon_code']);
        unset($_SESSION['coupon_discount']);
        unset($_SESSION['coupon_percent']);
        $response['success'] = true;
        $response['message'] = 'Coupon removed successfully.';
    } else {
        $response['message'] = 'No coupon to remove.';
    }
} else {
    $response['message'] = 'Invalid request.';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
