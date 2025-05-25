<?php
// Include database connection and auth check
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Delete post if requested
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $post_id = (int)$_GET['id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete post category associations
        $delete_categories = "DELETE FROM blog_post_categories WHERE post_id = :post_id";
        $stmt = $pdo->prepare($delete_categories);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        
        // Delete post tag associations
        $delete_tags = "DELETE FROM blog_post_tags WHERE post_id = :post_id";
        $stmt = $pdo->prepare($delete_tags);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        
        // Delete the post
        $delete_post = "DELETE FROM blog_posts WHERE id = :post_id";
        $stmt = $pdo->prepare($delete_post);
        $stmt->bindParam(':post_id', $post_id);
        $stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = 'Blog post deleted successfully!';
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Error deleting post: ' . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header('Location: list.php');
    exit;
}

// Get posts with pagination
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

// Get total posts count
$count_query = "SELECT COUNT(*) FROM blog_posts" . $where_clause;
$count_stmt = $pdo->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_posts = $count_stmt->fetchColumn();

// Calculate total pages
$total_pages = ceil($total_posts / $per_page);

// Get posts
$query = "SELECT p.*, 
                 (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                  FROM blog_categories c 
                  JOIN blog_post_categories pc ON c.id = pc.category_id 
                  WHERE pc.post_id = p.id) as categories
          FROM blog_posts p" . $where_clause . "
          ORDER BY p.created_at DESC 
          LIMIT :offset, :per_page";

$stmt = $pdo->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$posts = $stmt->fetchAll();

// Page variables
$page_title = 'Blog Posts';

// Add header action buttons
$header_actions = '
<a href="create.php" class="btn btn-sm btn-primary">
    <i class="fas fa-plus me-1"></i> Add New Post
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
                    <input type="text" class="form-control" placeholder="Search posts by title or content..." name="search" value="<?php echo htmlspecialchars($search); ?>">
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

<!-- Posts Table -->
<div class="card shadow mb-4">
    <div class="card-body">
        <?php if (empty($posts)): ?>
            <div class="alert alert-info">
                <?php if (!empty($search)): ?>
                    No posts found matching your search criteria. <a href="list.php">View all posts</a>
                <?php else: ?>
                    No blog posts have been created yet. <a href="create.php">Create your first post</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 70px;"></th>
                            <th>Title</th>
                            <th>Categories</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 170px;">Date</th>
                            <th style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($post['featured_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="Post image" class="post-image">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center post-image">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($post['title']); ?></div>
                                    <div class="small text-muted truncate"><?php echo htmlspecialchars($post['excerpt']); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($post['categories'])): ?>
                                        <?php echo htmlspecialchars($post['categories']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Uncategorized</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="post-status <?php echo $post['status'] === 'published' ? 'status-published' : 'status-draft'; ?>">
                                        <?php echo ucfirst($post['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($post['created_at'])); ?></div>
                                    <div class="small text-muted">
                                        <?php 
                                        if ($post['updated_at'] && $post['updated_at'] != $post['created_at']) {
                                            echo 'Updated: ' . date('M j, Y', strtotime($post['updated_at']));
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/blog/<?php echo $post['slug']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="list.php?action=delete&id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
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

<?php 
// Include admin footer
include_once '../includes/footer.php'; 
?>