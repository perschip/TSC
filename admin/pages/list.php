<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Delete page if requested
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $page_id = (int)$_GET['id'];
    
    try {
        // Delete the page
        $delete_page = "DELETE FROM pages WHERE id = :page_id";
        $stmt = $pdo->prepare($delete_page);
        $stmt->bindParam(':page_id', $page_id);
        $stmt->execute();
        
        $_SESSION['success_message'] = 'Page deleted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error deleting page: ' . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header('Location: list.php');
    exit;
}

// Get pages with pagination
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

// Get search term if provided
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = " WHERE title LIKE :search OR content LIKE :search ";
    $params[':search'] = "%$search%";
}

// Get total pages count
$count_query = "SELECT COUNT(*) FROM pages" . $where_clause;
$count_stmt = $pdo->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_pages_count = $count_stmt->fetchColumn();

// Calculate total pages for pagination
$total_pages = ceil($total_pages_count / $per_page);

// Get pages
$query = "SELECT p.* FROM pages p" . $where_clause . " ORDER BY p.created_at DESC LIMIT :offset, :per_page";

$stmt = $pdo->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$pages = $stmt->fetchAll();

// Page variables
$page_title = 'Pages';

// Add header action buttons
$header_actions = '
<a href="create.php" class="btn btn-sm btn-primary">
    <i class="fas fa-plus me-1"></i> Add New Page
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
                    <input type="text" class="form-control" placeholder="Search pages by title or content..." name="search" value="<?php echo htmlspecialchars($search); ?>">
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

<!-- Pages Table -->
<div class="card shadow mb-4">
    <div class="card-body">
        <?php if (empty($pages)): ?>
            <div class="alert alert-info">
                <?php if (!empty($search)): ?>
                    No pages found matching your search criteria. <a href="list.php">View all pages</a>
                <?php else: ?>
                    No pages have been created yet. <a href="create.php">Create your first page</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 70px;"></th>
                            <th>Title</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 170px;">Date</th>
                            <th style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($page['featured_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($page['featured_image']); ?>" alt="Page image" class="post-image">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center post-image">
                                            <i class="fas fa-file-alt text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($page['title']); ?></div>
                                    <div class="small text-muted text-truncate-2"><?php echo htmlspecialchars(substr($page['content'], 0, 100) . (strlen($page['content']) > 100 ? '...' : '')); ?></div>
                                </td>
                                <td>
                                    <span class="post-status <?php echo $page['status'] === 'published' ? 'status-published' : 'status-draft'; ?>">
                                        <?php echo ucfirst($page['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($page['created_at'])); ?></div>
                                    <div class="small text-muted">
                                        <?php 
                                        if ($page['updated_at'] && $page['updated_at'] != $page['created_at']) {
                                            echo 'Updated: ' . date('M j, Y', strtotime($page['updated_at']));
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit.php?id=<?php echo $page['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/<?php echo $page['slug']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="list.php?action=delete&id=<?php echo $page['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this page? This action cannot be undone.')">
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
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
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

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>