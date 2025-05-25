<?php
// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if testimonial ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid testimonial ID';
    header('Location: list.php');
    exit;
}

$testimonial_id = (int)$_GET['id'];

// Get testimonial data
try {
    $query = "SELECT * FROM testimonials WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $testimonial_id);
    $stmt->execute();
    $testimonial = $stmt->fetch();
    
    if (!$testimonial) {
        $_SESSION['error_message'] = 'Testimonial not found';
        header('Location: list.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header('Location: list.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $author_name = trim($_POST['author_name']);
    $author_location = trim($_POST['author_location']);
    $content = trim($_POST['content']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status = $_POST['status'] ?? 'published';
    
    // Validate inputs
    $errors = [];
    
    if (empty($author_name)) {
        $errors[] = 'Customer name is required';
    }
    
    if (empty($content)) {
        $errors[] = 'Testimonial content is required';
    }
    
    // If no errors, update the testimonial
    if (empty($errors)) {
        try {
            // Update testimonial
            $query = "UPDATE testimonials 
                      SET author_name = :author_name, 
                          author_location = :author_location, 
                          content = :content, 
                          is_featured = :is_featured, 
                          status = :status,
                          updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':author_name', $author_name);
            $stmt->bindParam(':author_location', $author_location);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':is_featured', $is_featured, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $testimonial_id);
            $stmt->execute();
            
            // Redirect to the list page with success message
            $_SESSION['success_message'] = 'Testimonial updated successfully!';
            header('Location: list.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Page variables
$page_title = 'Edit Testimonial';

// Header actions
$header_actions = '
<a href="list.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to List
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<?php if (isset($errors) && !empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <form action="edit.php?id=<?php echo $testimonial_id; ?>" method="post">
            <div class="row">
                <div class="col-md-8">
                    <!-- Main Content Fields -->
                    <div class="mb-3">
                        <label for="author_name" class="form-label">Customer Name *</label>
                        <input type="text" class="form-control" id="author_name" name="author_name" value="<?php echo htmlspecialchars($testimonial['author_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="author_location" class="form-label">Customer Location</label>
                        <input type="text" class="form-control" id="author_location" name="author_location" value="<?php echo htmlspecialchars($testimonial['author_location']); ?>" placeholder="e.g., New York, NY">
                        <div class="form-text">Optional: City, State, or Country</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Testimonial Content *</label>
                        <textarea class="form-control" id="content" name="content" rows="6" required><?php echo htmlspecialchars($testimonial['content']); ?></textarea>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Sidebar Fields -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Publishing</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="published" <?php echo $testimonial['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $testimonial['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                                <div class="form-text">Draft testimonials won't appear on your website.</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1" <?php echo $testimonial['is_featured'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_featured">Featured Testimonial</label>
                                <div class="form-text">Featured testimonials are displayed prominently on your homepage.</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Update Testimonial</button>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Information</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Created:</strong> <?php echo date('F j, Y', strtotime($testimonial['created_at'])); ?></p>
                            <?php if ($testimonial['updated_at'] && $testimonial['updated_at'] != $testimonial['created_at']): ?>
                                <p class="mb-2"><strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($testimonial['updated_at'])); ?></p>
                            <?php endif; ?>
                            <p class="mb-0"><strong>ID:</strong> <?php echo $testimonial_id; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
// Include admin footer
include_once '../includes/footer.php';
?>