<?php
// admin/paypal/delete_coupon.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Check if form was submitted with coupon_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon_id'])) {
    $coupon_id = intval($_POST['coupon_id']);
    
    // Validate coupon ID
    if ($coupon_id <= 0) {
        $_SESSION['error_message'] = 'Invalid coupon ID.';
        header('Location: coupons.php');
        exit;
    }
    
    try {
        // Check if coupon exists
        $check_stmt = $pdo->prepare("SELECT id FROM coupons WHERE id = :id");
        $check_stmt->execute([':id' => $coupon_id]);
        
        if ($check_stmt->rowCount() === 0) {
            $_SESSION['error_message'] = 'Coupon not found.';
            header('Location: coupons.php');
            exit;
        }
        
        // Delete the coupon
        $delete_stmt = $pdo->prepare("DELETE FROM coupons WHERE id = :id");
        $delete_stmt->execute([':id' => $coupon_id]);
        
        $_SESSION['success_message'] = 'Coupon deleted successfully.';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = 'Invalid request.';
}

// Redirect back to coupons page
header('Location: coupons.php');
exit;
?>
