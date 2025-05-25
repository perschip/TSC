<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Delete testimonial if requested
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $testimonial_id = (int)$_GET['id'];
    
    try {
        // Delete the testimonial
        $delete_query = "DELETE FROM testimonials WHERE id = :id";
        $stmt = $pdo->prepare($delete_query);
        $stmt->bindParam(':id', $testimonial_id);
        $stmt->execute();
        
        $_SESSION['success_message'] = 'Testimonial deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting testimonial: ' . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header('Location: list.php');
    exit;
}

// Toggle featured status if requested
if (isset($_GET['action']) && $_GET['action'] === 'toggle_featured' && isset($_GET['id'])) {
    $testimonial_id = (int)$_GET['id'];
    
    try {
        // Toggle the is_featured status
        $toggle_query = "UPDATE testimonials SET is_featured = NOT is_featured WHERE id = :id";
        $stmt = $pdo->prepare($toggle_query);
        $stmt->bindParam(':id', $testimonial_id);
        $stmt->execute();
        
        $_SESSION['success_message'] = 'Testimonial featured status updated!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating testimonial: ' . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header('Location: list.php');
    exit;
}

// Get testimonials with pagination
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

// Get search term if provided
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = " WHERE author_name LIKE :search OR content LIKE :search ";
    $params[':search'] = "%$search%";
}

// Check if testimonials table exists, create it if not
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'testimonials'");
    if ($table_check->rowCount() == 0) {
        // Table doesn't exist, create it
        $pdo->exec("CREATE TABLE IF NOT EXISTS `testimonials` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `author_name` varchar(100) NOT NULL,
            `author_location` varchar(100) DEFAULT NULL,
            `content` text NOT NULL,
            `is_featured` tinyint(1) NOT NULL DEFAULT 0,
            `status` enum('published','draft') NOT NULL DEFAULT 'published',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Insert sample testimonials
        $pdo->exec("INSERT INTO `testimonials` (`author_name`, `author_location`, `content`, `is_featured`, `status`) VALUES
            ('Mike R.', 'New York', 'Tristate Cards always delivers quality cards and an amazing experience. Their card breaks are the best!', 1, 'published'),
            ('Sarah T.', 'New Jersey', 'Fast shipping, great communication, and amazing pulls. I\'m a customer for life!', 1, 'published');");
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database setup error: ' . $e->getMessage();
}

// Get total testimonials count
$count_query = "SELECT COUNT(*) FROM testimonials" . $where_clause;
$count_stmt = $pdo->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_testimonials = $count_stmt->fetchColumn();

// Calculate total pages
$total_pages = ceil($total_testimonials / $per_page);

// Get testimonials
$query = "SELECT * FROM testimonials" . $where_clause . " ORDER BY is_featured DESC, created_at DESC LIMIT :offset, :per_page";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$testimonials = $stmt->fetchAll();

// Page variables
$page_title = 'Customer Testimonials';

// Add header action buttons
$header_actions = '
<a href="create.php" class="btn btn-sm btn-primary">
    <i class="fas fa-plus me-1"></i> Add New Testimonial
</a>
';

// Include admin header
include_once '../includes/header.php';
?>

<!-- Search and Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form action="list.php" method="get" class="row g-3">
            <div class="col-md-8">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search testimonials by name or content..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <?php if (!empty($search)): ?>
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Testimonials Table -->
<div class="card shadow mb-4">
    <div class="card-body">
        <?php if (empty($testimonials)): ?>
            <div class="alert alert-info">
                <?php if (!empty($search)): ?>
                    No testimonials found matching your search criteria. <a href="list.php">View all testimonials</a>
                <?php else: ?>
                    No testimonials have been added yet. <a href="create.php">Add your first testimonial</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Testimonial</th>
                            <th style="width: 100px;">Featured</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 170px;">Date</th>
                            <th style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testimonials as $testimonial): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($testimonial['author_name']); ?></div>
                                    <?php if (!empty($testimonial['author_location'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($testimonial['author_location']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-truncate-2">
                                    <?php echo htmlspecialchars($testimonial['content']); ?>
                                </td>
                                <td class="text-center">
                                    <a href="list.php?action=toggle_featured&id=<?php echo $testimonial['id']; ?>" class="featured-toggle">
                                        <?php if ($testimonial['is_featured']): ?>
                                            <i class="fas fa-star text-warning"></i>
                                        <?php else: ?>
                                            <i class="far fa-star text-muted"></i>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="post-status <?php echo $testimonial['status'] === 'published' ? 'status-published' : 'status-draft'; ?>">
                                        <?php echo ucfirst($testimonial['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($testimonial['created_at'])); ?></div>
                                    <div class="small text-muted">
                                        <?php 
                                        if ($testimonial['updated_at'] && $testimonial['updated_at'] != $testimonial['created_at']) {
                                            echo 'Updated: ' . date('M j, Y', strtotime($testimonial['updated_at']));
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit.php?id=<?php echo $testimonial['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="list.php?action=delete&id=<?php echo $testimonial['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this testimonial? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add confirmation on delete buttons
    const deleteButtons = document.querySelectorAll('a[href*="action=delete"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this testimonial? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Add tooltip to featured stars
    const featuredToggles = document.querySelectorAll('.featured-toggle');
    featuredToggles.forEach(toggle => {
        const isFeatured = toggle.querySelector('.fas.fa-star') !== null;
        toggle.setAttribute('title', isFeatured ? 'Remove from featured' : 'Add to featured');
        toggle.addEventListener('mouseover', function() {
            this.style.opacity = '0.7';
        });
        toggle.addEventListener('mouseout', function() {
            this.style.opacity = '1';
        });
    });
});
</script>

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>