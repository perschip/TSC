<?php
// admin/paypal/delete_product.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has admin privileges
if (!isAdmin()) {
    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: /admin/index.php');
    exit;
}

// Check if product ID is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    
    if ($product_id) {
        try {
            // Check if product exists
            $check_stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $check_stmt->execute([$product_id]);
            
            if ($check_stmt->rowCount() > 0) {
                // Delete the product
                $delete_stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $delete_stmt->execute([$product_id]);
                
                $_SESSION['success_message'] = 'Product deleted successfully.';
            } else {
                $_SESSION['error_message'] = 'Product not found.';
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Error deleting product: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Invalid product ID.';
    }
} else {
    $_SESSION['error_message'] = 'Invalid request.';
}

// Redirect back to products page
header('Location: products.php');
exit;
?>
