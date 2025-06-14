<?php
// listings.php - Frontend eBay listings page
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Initialize all variables with proper types
$current_page = 1;
$per_page = 12;
$offset = 0;
$total_listings = 0;
$total_pages = 1;
$listings = array();
$categories = array();
$category_counts = array();
$featured_listings = array();

// Get and validate current page
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $current_page = max(1, intval($_GET['page']));
}
$offset = ($current_page - 1) * $per_page;

// Get filters
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'last_updated';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Valid sort options
$valid_sorts = array('title', 'price', 'last_updated');
if (!in_array($sort, $valid_sorts)) {
    $sort = 'last_updated';
}

// Build WHERE clause for active listings only
$where_conditions = array("quantity > 0");
$params = array();

if (!empty($category_filter)) {
    $where_conditions[] = "category = :category";
    $params[':category'] = $category_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(title LIKE :search OR category LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count for pagination
try {
    $count_query = "SELECT COUNT(*) FROM ebay_listings $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $result = $count_stmt->fetchColumn();
    $total_listings = intval($result);
} catch (PDOException $e) {
    $total_listings = 0;
    error_log('Error counting listings: ' . $e->getMessage());
}

// Calculate total pages
if ($total_listings > 0) {
    $total_pages = intval(ceil($total_listings / $per_page));
} else {
    $total_pages = 1;
}

// Get listings
try {
    $query = "SELECT * FROM ebay_listings $where_clause ORDER BY $sort $order LIMIT :offset, :per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $listings = $stmt->fetchAll();
} catch (PDOException $e) {
    $listings = array();
    error_log('Error fetching listings: ' . $e->getMessage());
}

// Get categories for filter
try {
    $stmt = $pdo->query("SELECT name FROM ebay_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no categories in the table, fall back to distinct values from listings
    if (empty($categories)) {
        $stmt = $pdo->query("SELECT DISTINCT category FROM ebay_listings WHERE category IS NOT NULL AND category != '' AND quantity > 0 ORDER BY category");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $categories = array();
    error_log('Error fetching categories: ' . $e->getMessage());
}

// Get category counts for display
try {
    $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM ebay_listings WHERE category IS NOT NULL AND category != '' AND quantity > 0 GROUP BY category ORDER BY count DESC");
    $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $category_counts = array();
    foreach ($result as $cat => $count) {
        $category_counts[$cat] = intval($count);
    }
} catch (PDOException $e) {
    $category_counts = array();
}

// Get featured listings for carousel
try {
    $featured_stmt = $pdo->query("SELECT * FROM ebay_listings WHERE is_favorite = 1 AND quantity > 0 ORDER BY last_updated DESC LIMIT 8");
    $featured_listings = $featured_stmt->fetchAll();
} catch (PDOException $e) {
    $featured_listings = array();
}

// Page settings
$page_title = 'eBay Listings';
if (!empty($category_filter)) {
    $page_title .= ' - ' . ucfirst($category_filter);
}
if (!empty($search)) {
    $page_title .= ' - Search: ' . $search;
}

$meta_description = 'Browse our current eBay listings featuring sports cards, collectibles, and memorabilia. Find your next treasure!';

// Include CSS for listings
$extra_css = '/assets/css/ebay-listings.css';

// Include header
include_once 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2 mb-2">Our eBay Listings</h1>
            <p class="text-muted">
                Browse our current inventory of <?php echo number_format($total_listings); ?> available items
                <?php if (!empty($category_filter)): ?>
                    in <strong><?php echo htmlspecialchars($category_filter); ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!empty($featured_listings)): ?>
    <!-- Featured Listings Carousel -->
    <div class="row mb-5">
        <div class="col-12">
            <h3 class="h4 mb-3">Featured Items</h3>
            <div id="featuredListingsCarousel" class="carousel slide featured-carousel" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php 
                    $featured_chunks = array_chunk($featured_listings, 4);
                    foreach ($featured_chunks as $index => $chunk): 
                    ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <div class="row">
                            <?php foreach ($chunk as $listing): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100">
                                    <div class="card-img-container">
                                        <?php if (!empty($listing['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" 
                                                 class="card-img-top" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                                        <?php else: ?>
                                            <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                                <i class="fas fa-image text-muted fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <div class="title-container">
                                            <h6 class="card-title"><?php echo htmlspecialchars($listing['title']); ?></h6>
                                        </div>
                                        <div class="price-container mt-auto">
                                            <p class="card-price mb-0"><?php echo $listing['currency'] ?? 'USD'; ?> <?php echo number_format($listing['price'] ?? 0, 2); ?></p>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" 
                                           target="_blank" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-external-link-alt me-1"></i> View on eBay
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($featured_chunks) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
                
                <div class="carousel-indicators">
                    <?php foreach ($featured_chunks as $index => $chunk): ?>
                    <button type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                            <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?> 
                            aria-label="Slide <?php echo $index + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <form method="get" class="mb-4">
                        <?php if (!empty($category_filter)): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <?php endif; ?>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" 
                                   placeholder="Search items..." name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-secondary btn-sm" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Category Filter -->
                    <h6 class="mb-3">Categories</h6>
                    <div class="category-list">
                        <a href="?" class="list-group-item list-group-item-action <?php echo empty($category_filter) ? 'active' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>All Categories</span>
                                <span class="badge bg-primary rounded-pill"><?php echo number_format($total_listings); ?></span>
                            </div>
                        </a>
                        
                        <?php foreach ($categories as $category): ?>
                            <?php $count = isset($category_counts[$category]) ? $category_counts[$category] : 0; ?>
                            <?php if ($count > 0): ?>
                            <a href="?category=<?php echo urlencode($category); ?>" 
                               class="list-group-item list-group-item-action <?php echo $category_filter === $category ? 'active' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($category); ?></span>
                                    <span class="badge bg-secondary rounded-pill"><?php echo $count; ?></span>
                                </div>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Sort Options -->
                    <h6 class="mb-3 mt-4">Sort By</h6>
                    <form method="get">
                        <?php if (!empty($category_filter)): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        
                        <select name="sort" class="form-select form-select-sm mb-2" onchange="this.form.submit()">
                            <option value="last_updated" <?php echo $sort === 'last_updated' ? 'selected' : ''; ?>>Recently Added</option>
                            <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?>>Price</option>
                            <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title A-Z</option>
                        </select>
                        
                        <select name="order" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="desc" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>High to Low</option>
                            <option value="asc" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Low to High</option>
                        </select>
                    </form>

                    <!-- Clear Filters -->
                    <?php if (!empty($category_filter) || !empty($search)): ?>
                    <div class="mt-3">
                        <a href="?" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-times me-1"></i> Clear Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <?php if (!empty($search)): ?>
                        <p class="mb-0">Search results for "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
                    <?php endif; ?>
                    <small class="text-muted">
                        <?php 
                        $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
                        $start_item = ($current_page - 1) * $per_page + 1;

                        $end_item = min($current_page * $per_page, $total_listings);
                        ?>
                        Showing <?php echo number_format($start_item); ?>-<?php echo number_format($end_item); ?> 
                        of <?php echo number_format($total_listings); ?> items
                    </small>
                </div>
            </div>

            <?php if (empty($listings)): ?>
                <!-- Empty State -->
                <div class="listings-empty-state">
                    <i class="fas fa-search"></i>
                    <h4>No listings found</h4>
                    <p class="text-muted">
                        <?php if (!empty($search) || !empty($category_filter)): ?>
                            Try adjusting your search or browse all categories.
                        <?php else: ?>
                            We don't have any active listings at the moment. Check back soon!
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        <a href="?" class="btn btn-primary">View All Listings</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Listings Grid -->
                <div class="ebay-listings-grid">
                    <?php foreach ($listings as $listing): ?>
                        <div class="listing-card">
                            <div class="card-img-container">
                                <?php if (!empty($listing['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                        <i class="fas fa-image text-muted fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body">
                                <div class="title-container">
                                    <h6 class="card-title"><?php echo htmlspecialchars($listing['title']); ?></h6>
                                </div>
                                
                                <?php if (!empty($listing['category'])): ?>
                                    <div class="mb-2">
                                        <span class="card-category"><?php echo htmlspecialchars($listing['category']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="card-price"><?php echo $listing['currency'] ?? 'USD'; ?> <?php echo number_format($listing['price'] ?? 0, 2); ?></div>
                                    <div class="card-meta">
                                        <small class="text-muted">Qty: <?php echo $listing['quantity'] ?? 0; ?></small>
                                    </div>
                                </div>
                                <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'])); ?>" 
                                   target="_blank" class="btn btn-primary btn-sm w-100 mt-2">
                                    <i class="fas fa-external-link-alt me-1"></i> View on eBay
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Listings pagination" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                                <?php $prev_page = $current_page - 1; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $prev_page; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?>">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <?php $next_page = $current_page + 1; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $next_page; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode(strtolower($order)); ?>">
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
</div>

<?php include_once 'includes/footer.php'; ?>