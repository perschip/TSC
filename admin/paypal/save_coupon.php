<?php
// admin/paypal/save_coupon.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $coupon_id = isset($_POST['coupon_id']) ? intval($_POST['coupon_id']) : null;
    $coupon_code = trim($_POST['coupon_code']);
    $discount_type = $_POST['discount_type'];
    $discount_value = floatval($_POST['discount_value']);
    $min_purchase = isset($_POST['min_purchase']) ? floatval($_POST['min_purchase']) : 0;
    $max_uses = !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null;
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $active = isset($_POST['active']) ? 1 : 0;

    // Validate required fields
    if (empty($coupon_code) || !in_array($discount_type, ['percentage', 'fixed']) || $discount_value <= 0) {
        $_SESSION['error_message'] = 'Please fill in all required fields with valid values.';
        header('Location: coupons.php');
        exit;
    }

    // Validate discount value for percentage (0-100)
    if ($discount_type === 'percentage' && ($discount_value <= 0 || $discount_value > 100)) {
        $_SESSION['error_message'] = 'Percentage discount must be between 1 and 100.';
        header('Location: coupons.php');
        exit;
    }

    try {
        // Check if coupon code already exists (for new coupons or when changing code)
        if ($coupon_id) {
            // Editing existing coupon
            $check_stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = :code AND id != :id");
            $check_stmt->execute([':code' => $coupon_code, ':id' => $coupon_id]);
        } else {
            // New coupon
            $check_stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = :code");
            $check_stmt->execute([':code' => $coupon_code]);
        }

        if ($check_stmt->rowCount() > 0) {
            $_SESSION['error_message'] = 'Coupon code already exists. Please use a unique code.';
            header('Location: coupons.php');
            exit;
        }

        // Prepare data for database
        $data = [
            ':code' => $coupon_code,
            ':discount_type' => $discount_type,
            ':discount_value' => $discount_value,
            ':min_purchase' => $min_purchase,
            ':active' => $active
        ];

        // Handle nullable fields
        if ($max_uses !== null) {
            $data[':max_uses'] = $max_uses;
        }
        if ($start_date !== null) {
            $data[':start_date'] = $start_date;
        }
        if ($end_date !== null) {
            $data[':end_date'] = $end_date;
        }

        if ($coupon_id) {
            // Update existing coupon
            $sql = "UPDATE coupons SET 
                    code = :code, 
                    discount_type = :discount_type, 
                    discount_value = :discount_value, 
                    min_purchase = :min_purchase, 
                    active = :active";
            
            // Add nullable fields conditionally
            if ($max_uses !== null) {
                $sql .= ", max_uses = :max_uses";
            } else {
                $sql .= ", max_uses = NULL";
            }
            
            if ($start_date !== null) {
                $sql .= ", start_date = :start_date";
            } else {
                $sql .= ", start_date = NULL";
            }
            
            if ($end_date !== null) {
                $sql .= ", end_date = :end_date";
            } else {
                $sql .= ", end_date = NULL";
            }
            
            $sql .= " WHERE id = :id";
            $data[':id'] = $coupon_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            $_SESSION['success_message'] = 'Coupon updated successfully.';
        } else {
            // Insert new coupon
            $sql = "INSERT INTO coupons (code, discount_type, discount_value, min_purchase, active";
            
            // Add nullable fields conditionally
            if ($max_uses !== null) {
                $sql .= ", max_uses";
            }
            if ($start_date !== null) {
                $sql .= ", start_date";
            }
            if ($end_date !== null) {
                $sql .= ", end_date";
            }
            
            $sql .= ") VALUES (:code, :discount_type, :discount_value, :min_purchase, :active";
            
            // Add nullable values conditionally
            if ($max_uses !== null) {
                $sql .= ", :max_uses";
            }
            if ($start_date !== null) {
                $sql .= ", :start_date";
            }
            if ($end_date !== null) {
                $sql .= ", :end_date";
            }
            
            $sql .= ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            $_SESSION['success_message'] = 'New coupon created successfully.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    }
}

// Redirect back to coupons page
header('Location: coupons.php');
exit;
?>
