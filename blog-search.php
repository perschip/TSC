<?php
// Include database connection and helper functions
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get search query or tag from URL
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$tag_slug = isset($_GET['tag']) ? trim($_GET['tag']) : '';

// Pagination
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 6; // Number of posts per page
$offset = ($current_page - 1) * $per_page;

// Initialize search parameters
$where_clause = " WHERE p.status = 'published' ";
$params = [];
$search_type = '';
$search_label = '';

// Build query based on search type
if (!empty($search_query)) {
    // Searching by keyword
    $where_clause .= " AND (p.title LIKE :search OR p.content LIKE :search OR p.excerpt LIKE :search) ";
    $params[':search'] = "%$search_query%";
    $search_type = 'query';
    $search_label = "\"$search_query\"";
} elseif (!empty($tag_slug)) {
    // Searching by tag
    // First get the tag info
    $tag_query = "SELECT id, name FROM blog_tags WHERE slug = :slug LIMIT 1";
    $tag_stmt = $pdo->prepare($tag_query);
    $tag_stmt->bindParam(':slug', $tag_slug);
    $tag_stmt->execute();
    $tag = $tag_stmt->fetch();
    
    if ($tag) {
        $tag_id = $tag['id'];
        $tag_name = $tag['name'];
        
        // Find posts with this tag
        $where_clause .= " AND p.id IN (SELECT post_id FROM blog_post_tags WHERE tag_id = :tag_id) ";
        $params[':tag_id'] = $tag_id;
        $search_type = 'tag';
        $search_label = "#$tag_name";
    } else {
        // Tag not found - show empty results
        $where_clause .= " AND 1=0 ";
        $search_type = 'tag';
        $search_label = "Unknown Tag";
    }
} else {
    // No search criteria - redirect to blog list
    header('Location: blog.php');
    exit;
}

// Get total posts count
$count_query = "SELECT COUNT(*) FROM blog_posts p $where_clause";
$count_stmt = $pdo->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_posts = $count_stmt->fetchColumn();

// Calculate total pages
$total_pages = ceil($total_posts / $per_page);

// Get posts with author and category info
$query = "SELECT p.*, 
                 (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                  FROM blog_categories c 
                  JOIN blog_post_categories pc ON c.id = pc.category_id 
                  WHERE pc.post_id = p.id) as categories,
                 (SELECT GROUP_CONCAT(c.slug SEPARATOR ', ') 
                  FROM blog_categories c 
                  JOIN blog_post_categories pc ON c.id = pc.category_id 
                  WHERE pc.post_id = p.id) as category_slugs
          FROM blog_posts p $where_clause
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

// Get all categories for sidebar
$categories_query = "SELECT c.*, COUNT(pc.post_id) as post_count 
                    FROM blog_categories c
                    LEFT JOIN blog_post_categories pc ON c.id = pc.category_id
                    LEFT JOIN blog_posts p ON pc.post_id = p.id AND p.status = 'published'
                    GROUP BY c.id
                    HAVING post_count > 0
                    ORDER BY c.name ASC";
$categories_stmt = $pdo->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll();

// Get popular tags for sidebar
$tags_query = "SELECT t.*, COUNT(pt.post_id) as post_count 
              FROM blog_tags t
              JOIN blog_post_tags pt ON t.id = pt.tag_id
              JOIN blog_posts p ON pt.post_id = p.id AND p.status = 'published'
              GROUP BY t.id
              ORDER BY post_count DESC, t.name ASC
              LIMIT 15";
$tags_stmt = $pdo->prepare($tags_query);
$tags_stmt->execute();
$tags = $tags_stmt->fetchAll();

// Get recent posts for sidebar
$recent_query = "SELECT id, title, slug, created_at 
                FROM blog_posts 
                WHERE status = 'published' 
                ORDER BY created_at DESC 
                LIMIT 5";
$recent_stmt = $pdo->prepare($recent_query);
$recent_stmt->execute();
$recent_posts = $recent_stmt->fetchAll();

// Set page variables
$page_title = 'Search Results: ' . $search_label;
$extra_css = '/assets/css/blog.css'; // Link to the blog-specific CSS

// Include header
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <h1>Search Results</h1>
        <p class="lead">
            <?php if ($search_type === 'query'): ?>
                Showing results for "<?php echo htmlspecialchars($search_query); ?>"
            <?php elseif ($search_type === 'tag'): ?>
                Showing posts tagged with <?php echo htmlspecialchars($search_label); ?>
            <?php endif; ?>
        </p>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <!-- Main Content - Blog Posts -->
        <div class="col-lg-8">
            <?php if (empty($posts)): ?>
                <div class="alert alert-info">
                    <h4>No posts found</h4>
                    <p>
                        <?php if ($search_type === 'query'): ?>
                            No posts were found matching your search criteria "<?php echo htmlspecialchars($search_query); ?>".
                        <?php elseif ($search_type === 'tag'): ?>
                            No posts were found with the tag <?php echo htmlspecialchars($search_label); ?>.
                        <?php endif; ?>
                    </p>
                    <a href="blog.php" class="btn btn-primary mt-3">View All Posts</a>
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <p>Found <?php echo $total_posts; ?> <?php echo $total_posts === 1 ? 'post' : 'posts'; ?> matching your search.</p>
                </div>
                
                <div class="row">
                    <?php foreach ($posts as $post): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card blog-card h-100">
                                <?php if (!empty($post['featured_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" class="blog-featured-image" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/600x400?text=Tristate+Cards" class="blog-featured-image" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <?php if (!empty($post['categories'])): ?>
                                        <div class="blog-categories">
                                            <?php 
                                            $category_names = explode(', ', $post['categories']);
                                            $category_slugs = explode(', ', $post['category_slugs']);
                                            for ($i = 0; $i < count($category_names); $i++): 
                                            ?>
                                                <a href="blog.php?category=<?php echo htmlspecialchars($category_slugs[$i]); ?>" class="blog-category">
                                                    <?php echo htmlspecialchars($category_names[$i]); ?>
                                                </a>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h5 class="card-title">
                                        <a href="blog-post.php?slug=<?php echo htmlspecialchars($post['slug']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($post['title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <div class="blog-meta">
                                        <i class="far fa-calendar-alt me-1"></i> <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                                    </div>
                                    
                                    <div class="blog-excerpt">
                                        <?php echo htmlspecialchars(substr($post['excerpt'], 0, 120) . (strlen($post['excerpt']) > 120 ? '...' : '')); ?>
                                    </div>
                                    
                                    <a href="blog-post.php?slug=<?php echo htmlspecialchars($post['slug']); ?>" class="btn btn-primary btn-sm">
                                        Read More
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Blog pagination" class="blog-pagination mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo $search_type === 'query' ? 'q=' . urlencode($search_query) : 'tag=' . urlencode($tag_slug); ?>&page=<?php echo $current_page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            // Show limited page numbers with ellipsis
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . ($search_type === 'query' ? 'q=' . urlencode($search_query) : 'tag=' . urlencode($tag_slug)) . '&page=1">1</a></li>';
                                if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo $search_type === 'query' ? 'q=' . urlencode($search_query) : 'tag=' . urlencode($tag_slug); ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php 
                            endfor; 
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="?' . ($search_type === 'query' ? 'q=' . urlencode($search_query) : 'tag=' . urlencode($tag_slug)) . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo $search_type === 'query' ? 'q=' . urlencode($search_query) : 'tag=' . urlencode($tag_slug); ?>&page=<?php echo $current_page + 1; ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Search Widget -->
            <div class="card mb-4 blog-sidebar-section">
                <div class="card-body">
                    <h5 class="blog-sidebar-title">Search</h5>
                    <form action="blog-search.php" method="get">
                        <div class="input-group">
                            <input type="text" class="form-control" name="q" placeholder="Search posts..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Categories Widget -->
            <div class="card mb-4 blog-sidebar-section">
                <div class="card-body">
                    <h5 class="blog-sidebar-title">Categories</h5>
                    <ul class="blog-sidebar-list">
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="blog.php?category=<?php echo htmlspecialchars($category['slug']); ?>" class="d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                    <span class="badge bg-primary rounded-pill"><?php echo $category['post_count']; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Recent Posts Widget -->
            <div class="card mb-4 blog-sidebar-section">
                <div class="card-body">
                    <h5 class="blog-sidebar-title">Recent Posts</h5>
                    <ul class="blog-sidebar-list">
                        <?php foreach ($recent_posts as $recent): ?>
                            <li>
                                <a href="blog-post.php?slug=<?php echo htmlspecialchars($recent['slug']); ?>">
                                    <?php echo htmlspecialchars($recent['title']); ?>
                                    <div class="small text-muted"><?php echo date('M j, Y', strtotime($recent['created_at'])); ?></div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Tags Widget -->
            <?php if (!empty($tags)): ?>
                <div class="card mb-4 blog-sidebar-section">
                    <div class="card-body">
                        <h5 class="blog-sidebar-title">Popular Tags</h5>
                        <div class="blog-tag-cloud">
                            <?php foreach ($tags as $tag): ?>
                                <a href="blog-search.php?tag=<?php echo htmlspecialchars($tag['slug']); ?>" class="blog-tag <?php echo ($search_type === 'tag' && isset($tag_id) && $tag['id'] == $tag_id) ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($tag['name']); ?> (<?php echo $tag['post_count']; ?>)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Back to Blog -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Browse All Posts</h5>
                    <p>View our complete collection of blog posts and articles.</p>
                    <a href="blog.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Blog
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>