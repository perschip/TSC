<?php
// admin/ebay/update_table.php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Function to check if column exists
function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE :column");
        $stmt->execute([':column' => $column]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Add missing columns
$updates = [];

// Check and add is_favorite column
if (!columnExists($pdo, 'ebay_listings', 'is_favorite')) {
    try {
        $pdo->exec("ALTER TABLE ebay_listings ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0");
        $pdo->exec("CREATE INDEX idx_is_favorite ON ebay_listings (is_favorite)");
        $updates[] = "Added 'is_favorite' column";
    } catch (PDOException $e) {
        $errors[] = "Error adding is_favorite column: " . $e->getMessage();
    }
}

// Check and add category column
if (!columnExists($pdo, 'ebay_listings', 'category')) {
    try {
        $pdo->exec("ALTER TABLE ebay_listings ADD COLUMN category VARCHAR(100) DEFAULT NULL");
        $pdo->exec("CREATE INDEX idx_category ON ebay_listings (category)");
        $updates[] = "Added 'category' column";
    } catch (PDOException $e) {
        $errors[] = "Error adding category column: " . $e->getMessage();
    }
}

// Check and add image_url column if it doesn't exist
if (!columnExists($pdo, 'ebay_listings', 'image_url')) {
    try {
        $pdo->exec("ALTER TABLE ebay_listings ADD COLUMN image_url TEXT AFTER seller_id");
        $updates[] = "Added 'image_url' column";
    } catch (PDOException $e) {
        $errors[] = "Error adding image_url column: " . $e->getMessage();
    }
}

// Include admin header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Database Update Results</h6>
        </div>
        <div class="card-body">
            <?php if (!empty($updates)): ?>
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle me-2"></i> Database Updated Successfully</h5>
                    <ul class="mb-0">
                        <?php foreach ($updates as $update): ?>
                            <li><?php echo htmlspecialchars($update); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i> Errors Occurred</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Your database is already up to date. No changes were needed.
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="listings.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Listings
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>
