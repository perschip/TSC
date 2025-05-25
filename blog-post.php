<?php
// Include database connection and helper functions
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get post slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    // If no slug provided, redirect to blog list
    header('Location: blog.php');
    exit;
}

// Get the blog post
try {
    $query = "SELECT p.* FROM blog_posts p WHERE p.slug = :slug AND p.status = 'published' LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':slug', $slug);
    $stmt->execute();
    $post = $stmt->fetch();
    
    if (!$post) {
        // Post not found or not published
        header('Location: blog.php');
        exit;
    }
} catch (PDOException $e) {
    // Database error
    header('Location: blog.php');
    exit;
}

// Get post categories
try {
    $cat_query = "SELECT c.id, c.name, c.slug 
                  FROM blog_categories c 
                  JOIN blog_post_categories pc ON c.id = pc.category_id 
                  WHERE pc.post_id = :post_id";
    $cat_stmt = $pdo->prepare($cat_query);
    $cat_stmt->bindParam(':post_id', $post['id']);
    $cat_stmt->execute();
    $post_categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $post_categories = [];
}

// Get post tags
try {
    $tag_query = "SELECT t.id, t.name, t.slug 
                  FROM blog_tags t 
                  JOIN blog_post_tags pt ON t.id = pt.tag_id 
                  WHERE pt.post_id = :post_id";
    $tag_stmt = $pdo->prepare($tag_query);
    $tag_stmt->bindParam(':post_id', $post['id']);
    $tag_stmt->execute();
    $post_tags = $tag_stmt->fetchAll();
} catch (PDOException $e) {
    $post_tags = [];
}

// Get related posts
try {
    // Get posts from the same categories
    $related_query = "SELECT DISTINCT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.created_at 
                      FROM blog_posts p
                      JOIN blog_post_categories pc1 ON p.id = pc1.post_id
                      JOIN blog_post_categories pc2 ON pc1.category_id = pc2.category_id
                      WHERE pc2.post_id = :post_id
                      AND p.id != :post_id
                      AND p.status = 'published'
                      ORDER BY p.created_at DESC
                      LIMIT 3";
    $related_stmt = $pdo->prepare($related_query);
    $related_stmt->bindParam(':post_id', $post['id']);
    $related_stmt->execute();
    $related_posts = $related_stmt->fetchAll();
} catch (PDOException $e) {
    $related_posts = [];
}

// Get recent posts for sidebar
$recent_query = "SELECT id, title, slug, created_at 
                 FROM blog_posts 
                 WHERE status = 'published' AND id != :post_id
                 ORDER BY created_at DESC 
                 LIMIT 5";
$recent_stmt = $pdo->prepare($recent_query);
$recent_stmt->bindParam(':post_id', $post['id']);
$recent_stmt->execute();
$recent_posts = $recent_stmt->fetchAll();

// Get categories for sidebar
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

// Set page variables
$page_title = $post['title'];
$extra_css = '/assets/css/blog.css'; // Link to the blog-specific CSS

// Set meta description for SEO
$meta_description = !empty($post['meta_description']) ? $post['meta_description'] : $post['excerpt'];

// Include header
$extra_head = '<meta name="description" content="' . htmlspecialchars($meta_description) . '">';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Main Content - Blog Post -->
        <div class="col-lg-8">
            <article class="blog-post">
                <!-- Post Header -->
                <header class="blog-post-header">
                    <?php if (!empty($post_categories)): ?>
                        <div class="blog-categories mb-2">
                            <?php foreach ($post_categories as $category): ?>
                                <a href="blog.php?category=<?php echo htmlspecialchars($category['slug']); ?>" class="blog-category">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <h1 class="blog-post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                    
                    <div class="blog-post-meta">
                        <span><i class="far fa-calendar-alt me-1"></i> <?php echo date('F j, Y', strtotime($post['created_at'])); ?></span>
                    </div>
                </header>
                
                <!-- Featured Image -->
                <?php if (!empty($post['featured_image'])): ?>
                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" class="blog-post-featured-image img-fluid" alt="<?php echo htmlspecialchars($post['title']); ?>">
                <?php endif; ?>
                
                <!-- Post Content -->
                <div class="blog-post-content">
                    <?php echo nl2br($post['content']); ?>
                </div>
                
                <!-- Post Tags -->
                <?php if (!empty($post_tags)): ?>
                    <div class="blog-post-tags mt-4">
                        <h5>Tags:</h5>
                        <div class="blog-tag-cloud">
                            <?php foreach ($post_tags as $tag): ?>
                                <a href="blog-search.php?tag=<?php echo htmlspecialchars($tag['slug']); ?>" class="blog-tag">
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Post Navigation -->
                <div class="blog-post-navigation mt-5">
                    <div class="row">
                        <div class="col-6">
                            <?php
                            // Get previous post
                            $prev_query = "SELECT id, title, slug FROM blog_posts WHERE id < :post_id AND status = 'published' ORDER BY id DESC LIMIT 1";
                            $prev_stmt = $pdo->prepare($prev_query);
                            $prev_stmt->bindParam(':post_id', $post['id']);
                            $prev_stmt->execute();
                            $prev_post = $prev_stmt->fetch();
                            
                            if ($prev_post):
                            ?>
                                <a href="blog-post.php?slug=<?php echo htmlspecialchars($prev_post['slug']); ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-chevron-left me-1"></i> Previous Post
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 text-end">
                            <?php
                            // Get next post
                            $next_query = "SELECT id, title, slug FROM blog_posts WHERE id > :post_id AND status = 'published' ORDER BY id ASC LIMIT 1";
                            $next_stmt = $pdo->prepare($next_query);
                            $next_stmt->bindParam(':post_id', $post['id']);
                            $next_stmt->execute();
                            $next_post = $next_stmt->fetch();
                            
                            if ($next_post):
                            ?>
                                <a href="blog-post.php?slug=<?php echo htmlspecialchars($next_post['slug']); ?>" class="btn btn-outline-primary">
                                    Next Post <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Related Posts -->
                <?php if (!empty($related_posts)): ?>
                    <div class="related-posts">
                        <h3 class="mb-4">Related Posts</h3>
                        <div class="row">
                            <?php foreach ($related_posts as $related): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card related-post-card h-100">
                                        <?php if (!empty($related['featured_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" class="related-post-image card-img-top" alt="<?php echo htmlspecialchars($related['title']); ?>">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/600x400?text=Tristate+Cards" class="related-post-image card-img-top" alt="<?php echo htmlspecialchars($related['title']); ?>">
                                        <?php endif; ?>
                                        
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <a href="blog-post.php?slug=<?php echo htmlspecialchars($related['slug']); ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($related['title']); ?>
                                                </a>
                                            </h5>
                                            <div class="blog-meta">
                                                <i class="far fa-calendar-alt me-1"></i> <?php echo date('M j, Y', strtotime($related['created_at'])); ?>
                                            </div>
                                            <a href="blog-post.php?slug=<?php echo htmlspecialchars($related['slug']); ?>" class="btn btn-sm btn-outline-primary mt-2">
                                                Read More
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Search Widget -->
            <div class="card mb-4 blog-sidebar-section">
                <div class="card-body">
                    <h5 class="blog-sidebar-title">Search</h5>
                    <form action="blog-search.php" method="get">
                        <div class="input-group">
                            <input type="text" class="form-control" name="q" placeholder="Search posts...">
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
            <?php if (!empty($post_tags)): ?>
                <div class="card mb-4 blog-sidebar-section">
                    <div class="card-body">
                        <h5 class="blog-sidebar-title">Tags</h5>
                        <div class="blog-tag-cloud">
                            <?php foreach ($post_tags as $tag): ?>
                                <a href="blog-search.php?tag=<?php echo htmlspecialchars($tag['slug']); ?>" class="blog-tag">
                                    <?php echo htmlspecialchars($tag['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Whatnot Promo -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Join Us on Whatnot</h5>
                    <p>Follow us on Whatnot for live card breaks, exclusive deals, and more!</p>
                    <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars(getSetting('whatnot_username', 'tristate_cards')); ?>" class="btn btn-primary" target="_blank">
                        Follow on Whatnot
                    </a>
                </div>
            </div>
            
            <!-- Share Widget -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="blog-sidebar-title">Share This Post</h5>
                    <div class="social-links mt-3">
                        <?php
                        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                        $encoded_url = urlencode($current_url);
                        $encoded_title = urlencode($post['title']);
                        ?>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $encoded_url; ?>" target="_blank" title="Share on Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo $encoded_url; ?>&text=<?php echo $encoded_title; ?>" target="_blank" title="Share on Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $encoded_url; ?>" target="_blank" title="Share on LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="mailto:?subject=<?php echo $encoded_title; ?>&body=<?php echo $encoded_url; ?>" title="Share via Email">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>