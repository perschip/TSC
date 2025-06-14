<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Define the upload directory
$upload_dir = '../../uploads/ebay/';

// Check if a file name is provided
if (isset($_GET['file']) && !empty($_GET['file'])) {
    $file_name = $_GET['file'];
    
    // Sanitize the file name to prevent directory traversal
    $file_name = basename($file_name);
    $file_path = $upload_dir . $file_name;
    
    // Check if the file exists
    if (file_exists($file_path) && is_file($file_path)) {
        // Delete the file
        if (unlink($file_path)) {
            // Redirect back with success message
            header('Location: image_uploader.php?message=File deleted successfully&type=success');
            exit;
        } else {
            // Redirect back with error message
            header('Location: image_uploader.php?message=Failed to delete file&type=danger');
            exit;
        }
    } else {
        // Redirect back with error message
        header('Location: image_uploader.php?message=File not found&type=danger');
        exit;
    }
} else {
    // Redirect back with error message
    header('Location: image_uploader.php?message=No file specified&type=danger');
    exit;
}
?>
