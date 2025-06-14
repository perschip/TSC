<?php
// Product Release Calendar - Delete Release
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get release ID
$release_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($release_id <= 0) {
    $_SESSION['error_message'] = 'Invalid release ID';
    header('Location: index.php');
    exit;
}

// Check if confirmation is provided
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// If not confirmed, show confirmation page
if (!$confirmed) {
    // Get release data for confirmation
    try {
        $query = "SELECT title FROM product_releases WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $release_id]);
        $release = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$release) {
            $_SESSION['error_message'] = 'Release not found';
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error fetching release: ' . $e->getMessage();
        header('Location: index.php');
        exit;
    }
    
    // Include admin header
    include_once '../includes/header.php';
    ?>
    
    <div class="container-fluid">
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Delete Product Release</h1>
            <a href="index.php" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50 me-1"></i> Back to Releases
            </a>
        </div>
        
        <!-- Content Row -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Confirm Deletion</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <p><strong>Warning:</strong> You are about to delete the following product release:</p>
                            <p class="mb-0"><strong><?php echo htmlspecialchars($release['title']); ?></strong></p>
                        </div>
                        <p>This action cannot be undone. Are you sure you want to continue?</p>
                        
                        <div class="mt-4">
                            <a href="delete.php?id=<?php echo $release_id; ?>&confirm=yes" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i> Yes, Delete Release
                            </a>
                            <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    // Include admin footer
    include_once '../includes/footer.php';
    exit;
}

// If confirmed, delete the release
try {
    $query = "DELETE FROM product_releases WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $release_id]);
    
    $_SESSION['success_message'] = 'Product release deleted successfully!';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error deleting release: ' . $e->getMessage();
}

// Redirect back to index
header('Location: index.php');
exit;
?>
