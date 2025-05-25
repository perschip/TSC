<?php
// Include database connection
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Initialize variables
$errors = [];
$author_name = '';
$author_location = '';
$content = '';
$is_featured = 0;
$status = 'published';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $author_name = trim($_POST['author_name']);
    $author_location = trim($_POST['author_location']);
    $content = trim($_POST['content']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $status = $_POST['status'] ?? 'published';
    
    // Validate inputs
    if (empty($author_name)) {
        $errors[] = 'Customer name is required';
    }
    
    if (empty($content)) {
        $errors[] = 'Testimonial content is required';
    }
    
    // If no errors, create the testimonial
    if (empty($errors)) {
        try {
            // Insert new testimonial
            $query = "INSERT INTO testimonials (author_name, author_location, content, is_featured, status, created_at) 
                      VALUES (:author_name, :author_location, :content, :is_featured, :status, NOW())";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':author_name', $author_name);
            $stmt->bindParam(':author_location', $author_location);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':is_featured', $is_featured, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            
            // Redirect to the list page with success message
            $_SESSION['success_message'] = 'Testimonial added successfully!';
            header('Location: list.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Page variables
$page_title = 'Add New Testimonial';

// Header actions
$header_actions = '
<a href="list.php" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to List
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<?php if (!empty($errors)): ?>
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
        <form action="create.php" method="post">
            <div class="row">
                <div class="col-md-8">
                    <!-- Main Content Fields -->
                    <div class="mb-3">
                        <label for="author_name" class="form-label">Customer Name *</label>
                        <input type="text" class="form-control" id="author_name" name="author_name" value="<?php echo htmlspecialchars($author_name); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="author_location" class="form-label">Customer Location</label>
                        <input type="text" class="form-control" id="author_location" name="author_location" value="<?php echo htmlspecialchars($author_location); ?>" placeholder="e.g., New York, NY">
                        <div class="form-text">Optional: City, State, or Country</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Testimonial Content *</label>
                        <textarea class="form-control" id="content" name="content" rows="6" required><?php echo htmlspecialchars($content); ?></textarea>
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
                                    <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                                <div class="form-text">Draft testimonials won't appear on your website.</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1" <?php echo $is_featured ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_featured">Featured Testimonial</label>
                                <div class="form-text">Featured testimonials are displayed prominently on your homepage.</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Add Testimonial</button>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tips</h5>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Keep testimonials brief and focused on specific benefits</li>
                                <li>Include the customer's location for authenticity</li>
                                <li>Feature your best testimonials on the homepage</li>
                                <li>Update testimonials regularly to keep content fresh</li>
                            </ul>
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