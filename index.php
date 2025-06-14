<?php
// index.php - Updated to use store styling while keeping all homepage features
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ebay-listings.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if Whatnot status needs update
try {
    if (time() - strtotime(getSetting('whatnot_last_check', date('Y-m-d H:i:s'))) > 60 * (int)getSetting('whatnot_check_interval', 15)) {
        checkWhatnotStatus();
        updateSetting('whatnot_last_check', date('Y-m-d H:i:s'));
    }
} catch (Exception $e) {
    // Silently handle any errors
}

// Get current Whatnot status
try {
    // Query the admin_whatnot table which is being edited in /admin/whatnot
    $status_query = "SELECT * FROM admin_whatnot WHERE status = 'active' ORDER BY id DESC LIMIT 1";
    $status_stmt = $pdo->prepare($status_query);
    $status_stmt->execute();
    $whatnot_status = $status_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug the Whatnot status (remove in production)
    // error_log('Whatnot status: ' . print_r($whatnot_status, true));
    
    // If no results, fall back to the old table
    if (!$whatnot_status) {
        $status_query = "SELECT * FROM whatnot_status ORDER BY created_at DESC LIMIT 1";
        $status_stmt = $pdo->prepare($status_query);
        $status_stmt->execute();
        $whatnot_status = $status_stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // error_log('Whatnot status error: ' . $e->getMessage());
    $whatnot_status = null;
}

// Set page variables
$page_title = ''; // Homepage doesn't need a specific title prefix
$meta_description = 'Discover our collection of sports cards, trading cards, and collectibles. Browse our latest eBay listings and Whatnot streams.';

// Get featured listings (favorites)
$featured_listings = getFrontendEbayListings($pdo, [
    'featured_only' => true,
    'limit' => 9, // For carousel (3 per slide)
    'sort' => 'last_updated',
    'order' => 'DESC'
]);

// Get selected category from URL
$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Get current page from URL
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 9; // 9 listings per page (3x3 grid)
$offset = ($current_page - 1) * $items_per_page;

// Get total listings count for pagination
$total_listings = getTotalListingsCount($pdo, $selected_category);
$total_pages = intval(ceil($total_listings / $items_per_page));

// Get regular listings with pagination
$listings = getFrontendEbayListings($pdo, [
    'category' => $selected_category,
    'limit' => $items_per_page,
    'offset' => $offset,
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'last_updated',
    'order' => isset($_GET['order']) ? $_GET['order'] : 'DESC'
]);

// Get categories for filter
$categories = getFrontendCategories($pdo);

// Get category counts
$category_counts = countListingsByCategory($pdo);

// Get recent blog posts for sidebar
try {
    // First try admin_blog_posts which is the current table being edited in /admin/blog
    $recent_posts_query = "SELECT id, title, slug, featured_image, created_at, DATE_FORMAT(created_at, '%M %d, %Y') as formatted_date 
                         FROM admin_blog_posts WHERE status = 'published' 
                         ORDER BY created_at DESC LIMIT 3";
    $recent_posts_stmt = $pdo->prepare($recent_posts_query);
    $recent_posts_stmt->execute();
    $recent_posts = $recent_posts_stmt->fetchAll();
    
    // If no results, fall back to blog_posts table
    if (empty($recent_posts)) {
        $recent_posts_query = "SELECT id, title, slug, featured_image, created_at, DATE_FORMAT(created_at, '%M %d, %Y') as formatted_date 
                             FROM blog_posts WHERE status = 'published' 
                             ORDER BY created_at DESC LIMIT 3";
        $recent_posts_stmt = $pdo->prepare($recent_posts_query);
        $recent_posts_stmt->execute();
        $recent_posts = $recent_posts_stmt->fetchAll();
        
        // If still no results, try without the status filter
        if (empty($recent_posts)) {
            $recent_posts_query = "SELECT id, title, slug, featured_image, created_at, DATE_FORMAT(created_at, '%M %d, %Y') as formatted_date 
                                 FROM blog_posts 
                                 ORDER BY created_at DESC LIMIT 3";
            $recent_posts_stmt = $pdo->prepare($recent_posts_query);
            $recent_posts_stmt->execute();
            $recent_posts = $recent_posts_stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    $recent_posts = [];
}

// Track page view for analytics
try {
    if (function_exists('track_page_view')) {
        track_page_view('home');
    }
} catch (Exception $e) {
    // Ignore
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    
    <!-- SEO and Social Media Meta Tags -->
    <meta name="keywords" content="sports cards, trading cards, collectibles, memorabilia, eBay listings, Whatnot streams">
    <meta property="og:title" content="<?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta property="og:type" content="website">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/index.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <!-- Additional CSS for immediate fixes -->
    <style>
        /* Force remove white line and add gradient - inline to override cache */
        .navbar {
            border-bottom: none !important;
            margin-bottom: 0 !important;
        }
        
        .hero-section {
            margin-top: 0 !important;
        }
        
        .hero-section h1 {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #e9ecef 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
        }
        
        /* Fallback for browsers that don't support gradient text */
        @supports not (-webkit-background-clip: text) {
            .hero-section h1 {
                background: none !important;
                color: white !important;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5) !important;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/"><?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php
                    // Get navigation items from database for header
                    try {
                        if ($pdo) {
                            // Use the navigation table with the structure provided
                            $nav_items = [];
                            
                            try {
                                $nav_query = "SELECT * FROM navigation WHERE location = 'header' AND is_active = 1 AND parent_id IS NULL ORDER BY display_order ASC";
                                $nav_stmt = $pdo->prepare($nav_query);
                                $nav_stmt->execute();
                                $nav_items = $nav_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Get child items for dropdown menus
                                $child_items = [];
                                $child_query = "SELECT * FROM navigation WHERE location = 'header' AND is_active = 1 AND parent_id IS NOT NULL ORDER BY display_order ASC";
                                $child_stmt = $pdo->prepare($child_query);
                                $child_stmt->execute();
                                $all_child_items = $child_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Group children by parent_id
                                foreach ($all_child_items as $child) {
                                    $child_items[$child['parent_id']][] = $child;
                                }
                            } catch (Exception $e) {
                                // If navigation table doesn't exist or has issues, try alternative tables
                                try {
                                    $nav_query = "SELECT * FROM navigation_items WHERE status = 'active' ORDER BY sort_order ASC";
                                    $nav_stmt = $pdo->prepare($nav_query);
                                    $nav_stmt->execute();
                                    $nav_items = $nav_stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Exception $e) {
                                    $nav_items = [];
                                }
                            }
                            
                            // Display navigation items
                            foreach ($nav_items as $nav_item) {
                                $id = $nav_item['id'] ?? 0;
                                $title = $nav_item['title'] ?? 'Unknown';
                                $url = $nav_item['url'] ?? '#';
                                $target = $nav_item['target'] ?? '';
                                $icon = $nav_item['icon'] ?? '';
                                
                                $target_attr = !empty($target) ? ' target="' . htmlspecialchars($target) . '"' : '';
                                $icon_html = !empty($icon) ? '<i class="' . htmlspecialchars($icon) . ' me-1"></i> ' : '';
                                
                                // Check if this item has children
                                if (isset($child_items[$id]) && !empty($child_items[$id])) {
                                    // This is a dropdown menu
                                    echo '<li class="nav-item dropdown">';
                                    echo '<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown' . $id . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
                                    echo $icon_html . htmlspecialchars($title);
                                    echo '</a>';
                                    echo '<ul class="dropdown-menu" aria-labelledby="navbarDropdown' . $id . '">';
                                    
                                    foreach ($child_items[$id] as $child) {
                                        $child_title = $child['title'] ?? 'Unknown';
                                        $child_url = $child['url'] ?? '#';
                                        $child_target = $child['target'] ?? '';
                                        $child_icon = $child['icon'] ?? '';
                                        
                                        $child_target_attr = !empty($child_target) ? ' target="' . htmlspecialchars($child_target) . '"' : '';
                                        $child_icon_html = !empty($child_icon) ? '<i class="' . htmlspecialchars($child_icon) . ' me-1"></i> ' : '';
                                        
                                        echo '<li><a class="dropdown-item" href="' . htmlspecialchars($child_url) . '"' . $child_target_attr . '>';
                                        echo $child_icon_html . htmlspecialchars($child_title);
                                        echo '</a></li>';
                                    }
                                    
                                    echo '</ul>';
                                    echo '</li>';
                                } else {
                                    // Regular menu item
                                    echo '<li class="nav-item">';
                                    echo '<a class="nav-link" href="' . htmlspecialchars($url) . '"' . $target_attr . '>';
                                    echo $icon_html . htmlspecialchars($title);
                                    echo '</a>';
                                    echo '</li>';
                                }
                            }
                            
                            // If no nav items found, don't show any links
                            if (empty($nav_items)) {
                                // No default links - all links should come from the database
                            }
                        } else {
                            // No default links if no database connection
                        }
                    } catch (Exception $e) {
                        // If navigation table doesn't exist, don't show any links
                    }
                    ?>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="/store/cart.php" class="btn btn-light me-2">
                        <i class="fas fa-shopping-cart"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1><?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?></h1>
            <p class="lead">Your trusted source for sports cards, collectibles, and memorabilia</p>
            <div class="hero-cta mt-4">
                <a href="#featured-listings" class="btn btn-primary btn-lg me-2">View Featured Cards</a>
                <a href="store/index.php" class="btn btn-success btn-lg me-2">Shop Our Store</a>
                <a href="<?php echo (!empty($whatnot_status) && isset($whatnot_status['status']) && $whatnot_status['status'] === 'live' && isset($whatnot_status['stream_url'])) ? htmlspecialchars($whatnot_status['stream_url']) : 'https://www.whatnot.com/user/' . htmlspecialchars(getSetting('whatnot_username', 'tscardbreaks')); ?>" target="_blank" class="btn btn-outline-light btn-lg">
                    <?php echo (!empty($whatnot_status) && isset($whatnot_status['status']) && $whatnot_status['status'] === 'live') ? '<i class="fas fa-circle text-danger me-2 pulse"></i>Watch Live Now' : '<i class="fas fa-video me-2"></i>Check Whatnot'; ?>
                </a>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="row">
            <!-- Main Content Column -->
            <div class="col-lg-8">
                <!-- Featured Listings Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="section-title mb-4">Featured Cards</h2>
                        
                        <?php if (!empty($featured_listings)): ?>
                        <!-- Featured Listings Carousel (3 per slide) -->
                        <div id="featuredListingsCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                            <!-- Carousel indicators -->
                            <div class="carousel-indicators">
                                <?php 
                                $total_slides = ceil(count($featured_listings) / 3);
                                for ($i = 0; $i < $total_slides; $i++): 
                                ?>
                                    <button type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide-to="<?php echo $i; ?>" <?php echo ($i === 0) ? 'class="active" aria-current="true"' : ''; ?> aria-label="Slide <?php echo $i + 1; ?>"></button>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- Carousel slides -->
                            <div class="carousel-inner">
                                <?php 
                                $chunks = array_chunk($featured_listings, 3); // Split listings into groups of 3
                                foreach ($chunks as $index => $slide_listings): 
                                ?>
                                    <div class="carousel-item <?php echo ($index === 0) ? 'active' : ''; ?>">
                                        <div class="row row-cols-1 row-cols-md-3 g-4">
                                            <?php foreach ($slide_listings as $listing): ?>
                                                <div class="col">
                                                    <div class="card h-100 listing-card">
                                                        <a href="<?php echo htmlspecialchars($listing['url'] ?? ''); ?>" target="_blank" class="card-img-link">
                                                            <div class="card-img-container">
                                                                <?php if (!empty($listing['image_url'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($listing['title'] ?? ''); ?>">
                                                                <?php else: ?>
                                                                    <img src="/assets/images/no-image.jpg" class="card-img-top" alt="No image available">
                                                                <?php endif; ?>
                                                            </div>
                                                        </a>
                                                        <div class="card-body">
                                                            <h5 class="card-title mb-4">
                                                                <a href="<?php echo htmlspecialchars($listing['url'] ?? ''); ?>" target="_blank" class="text-dark">
                                                                    <?php echo htmlspecialchars(substr($listing['title'] ?? '', 0, 70) . (strlen($listing['title'] ?? '') > 70 ? '...' : '')); ?>
                                                                </a>
                                                            </h5>
                                                        </div>
                                                        <div class="card-footer bg-transparent">
                                                            <!-- Price and category -->
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <p class="card-price mb-0 text-success fw-bold">$<?php echo number_format($listing['price'] ?? 0, 2); ?></p>
                                                                <?php if (!empty($listing['category'])): ?>
                                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($listing['category']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <!-- Quantity and view button -->
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small class="text-muted">Qty: <?php echo $listing['quantity'] ?? 0; ?></small>
                                                                <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'] ?? '')); ?>" class="btn btn-sm btn-primary" target="_blank">View on eBay</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Carousel controls -->
                            <button class="carousel-control-prev" type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#featuredListingsCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        </div>
                        <div class="text-center mt-4">
                            <a href="listings.php" class="btn btn-primary">View All Listings</a>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">No featured listings available at this time.</p>
                        <?php endif; ?>
                        
                        <hr class="my-5">
                        
                        <!-- Recent Listings Section -->
                        <h3 class="mt-5 mb-3">Recent Listings</h3>
                        
                        <?php if (!empty($listings)): ?>
                        <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                            <?php foreach ($listings as $listing): ?>
                            <div class="col">
                                <div class="card h-100 listing-card">
                                    <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'] ?? '')); ?>" target="_blank" class="card-link">
                                        <div class="card-img-container">
                                            <?php if (!empty($listing['image_url'])): ?>
                                                <img loading="lazy" src="<?php echo htmlspecialchars($listing['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($listing['title'] ?? ''); ?>">
                                            <?php else: ?>
                                                <div class="text-center text-muted pt-4">
                                                    <i class="fas fa-image fa-2x"></i>
                                                    <p class="mt-1 small">No image</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">
                                            <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'] ?? '')); ?>" target="_blank" class="text-dark">
                                                <?php echo htmlspecialchars($listing['title'] ?? ''); ?>
                                            </a>
                                        </h5>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <!-- Price and category -->
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <p class="card-price mb-0 text-success fw-bold">$<?php echo number_format($listing['price'] ?? 0, 2); ?></p>
                                            <?php if (!empty($listing['category'])): ?>
                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($listing['category']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Quantity and view button -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Qty: <?php echo $listing['quantity'] ?? 0; ?></small>
                                            <a href="https://www.ebay.com/itm/<?php echo htmlspecialchars(str_replace('EBAY-', '', $listing['sku'] ?? '')); ?>" class="btn btn-sm btn-primary" target="_blank">View on eBay</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Listings pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($selected_category) ? '&category=' . urlencode($selected_category) : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                if ($end_page - $start_page < 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($selected_category) ? '&category=' . urlencode($selected_category) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($selected_category) ? '&category=' . urlencode($selected_category) : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        <?php else: ?>
                        <p class="text-muted">No listings available at this time.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column -->
            <div class="col-lg-4">
                <!-- Store Promo -->
                <div class="card mb-4 sidebar">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Visit Our Store</h5>
                    </div>
                    <div class="card-body">
                        <p>Browse our complete collection of sports cards and collectibles in our online store.</p>
                        <a href="store/index.php" class="btn btn-success w-100">Shop Now</a>
                    </div>
                </div>

                <!-- Whatnot Status -->
                <div class="card mb-4 sidebar">
                    <div class="card-header bg-<?php echo (!empty($whatnot_status) && $is_live) ? 'danger' : 'primary'; ?> text-white">
                        <h5 class="card-title mb-0">
                            <?php echo (!empty($whatnot_status) && $is_live) ? '<i class="fas fa-circle text-white me-2 pulse"></i>Live Now on Whatnot' : 'Whatnot Schedule'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($whatnot_status)): ?>
                            <?php 
                            // Check if it's live based on different possible column names
                            $is_live = false;
                            if (isset($whatnot_status['status']) && strtolower($whatnot_status['status']) === 'live') {
                                $is_live = true;
                            } elseif (isset($whatnot_status['is_live']) && $whatnot_status['is_live'] == 1) {
                                $is_live = true;
                            } elseif (isset($whatnot_status['stream_status']) && strtolower($whatnot_status['stream_status']) === 'live') {
                                $is_live = true;
                            } elseif (isset($whatnot_status['active']) && $whatnot_status['active'] == 1) {
                                $is_live = true;
                            }
                            
                            // Get stream info with flexible column names
                            $stream_title = $whatnot_status['title'] ?? $whatnot_status['stream_title'] ?? $whatnot_status['name'] ?? $whatnot_status['stream_name'] ?? 'Live Stream';
                            $stream_url = $whatnot_status['stream_url'] ?? $whatnot_status['url'] ?? $whatnot_status['link'] ?? $whatnot_status['whatnot_url'] ?? '';
                            $scheduled_time = $whatnot_status['scheduled_time'] ?? $whatnot_status['start_time'] ?? $whatnot_status['scheduled_at'] ?? $whatnot_status['stream_date'] ?? null;
                            ?>
                            
                            <?php if ($is_live): ?>
                                <p class="mb-2"><strong>Stream Title:</strong> <?php echo htmlspecialchars($stream_title); ?></p>
                                <p class="mb-3"><strong>Started:</strong> <?php echo isset($whatnot_status['start_time']) ? date('g:i A', strtotime($whatnot_status['start_time'])) : 'Now'; ?></p>
                                <a href="<?php echo htmlspecialchars($stream_url ?: 'https://www.whatnot.com/user/' . getSetting('whatnot_username', 'tscardbreaks')); ?>" target="_blank" class="btn btn-danger w-100" onclick="logWhatnotClick('<?php echo htmlspecialchars($whatnot_status['stream_id'] ?? $whatnot_status['id'] ?? ''); ?>')">
                                    Watch Live Stream
                                </a>
                            <?php elseif ($scheduled_time && strtotime($scheduled_time) > time()): ?>
                                <p class="mb-2"><strong>Next Stream:</strong> <?php echo htmlspecialchars($stream_title); ?></p>
                                <p class="mb-3"><strong>Scheduled:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($scheduled_time)); ?></p>
                                <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars(getSetting('whatnot_username', 'tscardbreaks')); ?>" target="_blank" class="btn btn-primary w-100">
                                    Follow on Whatnot
                                </a>
                            <?php else: ?>
                                <p>We're not currently live on Whatnot.</p>
                                <p class="mb-3">Follow us to get notified when we go live!</p>
                                <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars(getSetting('whatnot_username', 'tscardbreaks')); ?>" target="_blank" class="btn btn-primary w-100">
                                    Follow on Whatnot
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Follow us on Whatnot for live breaks and exclusive deals!</p>
                            <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars(getSetting('whatnot_username', 'tscardbreaks')); ?>" target="_blank" class="btn btn-primary w-100">
                                Follow on Whatnot
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Blog Posts -->
                <div class="card mb-4 sidebar">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Blog Posts</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_posts)): ?>
                            <div class="list-group list-group-flush">
                            <?php foreach ($recent_posts as $post): ?>
                                <a href="blog-post.php?slug=<?php echo htmlspecialchars($post['slug']); ?>" class="list-group-item list-group-item-action border-0 px-0">
                                    <div class="d-flex">
                                        <?php if (!empty($post['featured_image'])): ?>
                                        <div class="flex-shrink-0" style="width: 70px; height: 70px;">
                                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="img-fluid rounded" style="width: 70px; height: 70px; object-fit: cover;">
                                        </div>
                                        <?php endif; ?>
                                        <div class="<?php echo !empty($post['featured_image']) ? 'ms-3' : ''; ?>">
                                            <h6 class="mb-1"><?php echo htmlspecialchars(substr($post['title'], 0, 50) . (strlen($post['title']) > 50 ? '...' : '')); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($post['formatted_date']); ?></small>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            </div>
                            <div class="mt-3 text-center">
                                <a href="blog.php" class="btn btn-sm btn-outline-primary">View All Posts</a>
                            </div>
                            <?php else: ?>
                            <p class="text-muted">No recent blog posts available.</p>
                            <a href="blog.php" class="btn btn-sm btn-outline-primary">Visit Our Blog</a>
                            <?php endif; ?>
                    </div>
                </div>
                    
                <!-- Testimonials -->
                <div class="card sidebar">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer Testimonials</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get featured testimonials
                        try {
                            if ($pdo) {
                                $testimonials_query = "SELECT * FROM testimonials WHERE status = 'published' AND is_featured = 1 ORDER BY created_at DESC LIMIT 2";
                                $testimonials_stmt = $pdo->prepare($testimonials_query);
                                $testimonials_stmt->execute();
                                $featured_testimonials = $testimonials_stmt->fetchAll();
                                
                                if (!empty($featured_testimonials)) {
                                    foreach ($featured_testimonials as $index => $testimonial) {
                                        ?>
                                        <div class="testimonial">
                                            <p class="testimonial-text">"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                                            <p class="testimonial-author">- <?php echo htmlspecialchars($testimonial['author_name']); ?></p>
                                        </div>
                                        <?php
                                        if ($index < count($featured_testimonials) - 1) {
                                            echo '<hr>';
                                        }
                                    }
                                } else {
                                    // Fallback to default testimonials if none are in the database
                                    ?>
                                    <div class="testimonial">
                                        <p class="testimonial-text">"Great selection of cards and fast shipping! Will definitely be buying from Tristate Cards again."</p>
                                        <p class="testimonial-author">- John D.</p>
                                    </div>
                                    <hr>
                                    <div class="testimonial">
                                        <p class="testimonial-text">"The cards I ordered were in perfect condition and arrived well-packaged. Excellent service!"</p>
                                        <p class="testimonial-author">- Sarah T.</p>
                                    </div>
                                    <?php
                                }
                            } else {
                                // No database connection, show default testimonials
                                ?>
                                <div class="testimonial">
                                    <p class="testimonial-text">"Great selection of cards and fast shipping! Will definitely be buying from Tristate Cards again."</p>
                                    <p class="testimonial-author">- John D.</p>
                                </div>
                                <hr>
                                <div class="testimonial">
                                    <p class="testimonial-text">"The cards I ordered were in perfect condition and arrived well-packaged. Excellent service!"</p>
                                    <p class="testimonial-author">- Sarah T.</p>
                                </div>
                                <?php
                            }
                        } catch (Exception $e) {
                            // If there's a database error, show the default testimonials
                            ?>
                            <div class="testimonial">
                                <p class="testimonial-text">"Great selection of cards and fast shipping! Will definitely be buying from Tristate Cards again."</p>
                                <p class="testimonial-author">- John D.</p>
                            </div>
                            <hr>
                            <div class="testimonial">
                                <p class="testimonial-text">"The cards I ordered were in perfect condition and arrived well-packaged. Excellent service!"</p>
                                <p class="testimonial-author">- Sarah T.</p>
                            </div>
                            <?php
                        }
                        ?>
                        <div class="text-center mt-3">
                            <a href="testimonials.php" class="btn btn-sm btn-outline-primary">See All Testimonials</a>
                        </div>
                    </div>
                </div>
                
                <!-- Social Media Links -->
                <div class="card sidebar mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Connect With Us</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-center">
                            <?php if ($ebay_username = getSetting('ebay_seller_id')): ?>
                                <a href="https://www.ebay.com/usr/<?php echo htmlspecialchars($ebay_username); ?>" class="btn btn-outline-secondary me-2 mb-2" target="_blank">
                                    <i class="fab fa-ebay"></i> eBay
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($whatnot_username = getSetting('whatnot_username')): ?>
                                <a href="https://www.whatnot.com/user/<?php echo htmlspecialchars($whatnot_username); ?>" class="btn btn-outline-secondary me-2 mb-2" target="_blank">
                                    <i class="fas fa-video"></i> Whatnot
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($instagram = getSetting('instagram_username')): ?>
                                <a href="https://www.instagram.com/<?php echo htmlspecialchars($instagram); ?>" class="btn btn-outline-secondary me-2 mb-2" target="_blank">
                                    <i class="fab fa-instagram"></i> Instagram
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($twitter = getSetting('twitter_username')): ?>
                                <a href="https://twitter.com/<?php echo htmlspecialchars($twitter); ?>" class="btn btn-outline-secondary me-2 mb-2" target="_blank">
                                    <i class="fab fa-twitter"></i> Twitter
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Newsletter Signup Section -->
    <section class="container my-5">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="section-title">Stay Updated</h3>
                <p class="lead">Subscribe to our newsletter for exclusive deals, new arrivals, and breaking news from the card collecting world.</p>
                <form id="newsletter-form" class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Enter your email address" required>
                            <button type="submit" class="btn btn-primary">Subscribe Now</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="footer-title"><?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?></h5>
                    <p class="text-muted">Your trusted source for collectible trading cards since 2005. We specialize in sports cards, gaming cards, and rare collectibles.</p>
                    <div class="social-icons">
                        <?php if ($instagram = getSetting('instagram_username')): ?>
                            <a href="https://www.instagram.com/<?php echo htmlspecialchars($instagram); ?>" class="text-white me-3" target="_blank"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        <?php if ($twitter = getSetting('twitter_username')): ?>
                            <a href="https://twitter.com/<?php echo htmlspecialchars($twitter); ?>" class="text-white me-3" target="_blank"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        <?php if ($ebay_username = getSetting('ebay_seller_id')): ?>
                            <a href="https://www.ebay.com/usr/<?php echo htmlspecialchars($ebay_username); ?>" class="text-white me-3" target="_blank"><i class="fab fa-ebay"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                // Get all footer categories
                try {
                    if ($pdo) {
                        // Get distinct categories from navigation table for footer
                        $categories_query = "SELECT DISTINCT category FROM navigation WHERE location = 'footer' AND is_active = 1 AND category IS NOT NULL ORDER BY category ASC";
                        $categories_stmt = $pdo->prepare($categories_query);
                        $categories_stmt->execute();
                        $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // If no categories found, create a default "Quick Links" category
                        if (empty($categories)) {
                            $categories = [['category' => 'Main']];
                        }
                        
                        // Loop through each category and create a column
                        $total_categories = count($categories);
                        $col_width = $total_categories > 0 ? 12 / min($total_categories, 3) : 12; // Max 3 columns
                        
                        foreach ($categories as $index => $cat) {
                            $category = $cat['category'];
                            
                            // Get items for this category
                            $items_query = "SELECT * FROM navigation WHERE location = 'footer' AND is_active = 1 AND category = :category ORDER BY display_order ASC";
                            $items_stmt = $pdo->prepare($items_query);
                            $items_stmt->bindParam(':category', $category);
                            $items_stmt->execute();
                            $footer_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Only show category if it has items
                            if (!empty($footer_items)) {
                                echo '<div class="col-md-' . $col_width . ' mb-4 mb-md-0">';
                                echo '<h5 class="footer-title">' . htmlspecialchars($category) . '</h5>';
                                echo '<ul class="list-unstyled footer-links">';
                                
                                foreach ($footer_items as $item) {
                                    $title = $item['title'] ?? 'Unknown';
                                    $url = $item['url'] ?? '#';
                                    $target = $item['target'] ?? '';
                                    $icon = $item['icon'] ?? '';
                                    
                                    $target_attr = !empty($target) ? ' target="' . htmlspecialchars($target) . '"' : '';
                                    $icon_html = !empty($icon) ? '<i class="' . htmlspecialchars($icon) . ' me-1"></i> ' : '';
                                    
                                    echo '<li><a href="' . htmlspecialchars($url) . '"' . $target_attr . '>' . $icon_html . htmlspecialchars($title) . '</a></li>';
                                }
                                
                                echo '</ul>';
                                echo '</div>';
                            }
                        }
                    } else {
                        // If no database connection, show empty column
                        echo '<div class="col-md-4 mb-4 mb-md-0">';
                        echo '<h5 class="footer-title">Quick Links</h5>';
                        echo '<ul class="list-unstyled footer-links"></ul>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    // If error occurs, show empty column
                    echo '<div class="col-md-4 mb-4 mb-md-0">';
                    echo '<h5 class="footer-title">Quick Links</h5>';
                    echo '<ul class="list-unstyled footer-links"></ul>';
                    echo '</div>';
                }
                ?>
                <div class="col-md-4">
                    <h5 class="footer-title">Contact Info</h5>
                    <address class="text-muted">
                        <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars(getSetting('contact_email', 'info@tristatecards.com')); ?></p>
                        <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars(getSetting('contact_phone', '(555) 123-4567')); ?></p>
                        <p><i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars(getSetting('business_address', 'Tristate Area')); ?></p>
                    </address>
                </div>
            </div>
            <hr class="mt-4 mb-3" style="border-color: rgba(255,255,255,0.2);">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getSetting('site_name', 'Tristate Cards')); ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="/privacy-policy.php" class="text-white-50 me-3">Privacy Policy</a>
                    <a href="/terms.php" class="text-white-50">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Track Whatnot clicks -->
    <script>
    function logWhatnotClick(streamId) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'track_click.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('type=whatnot&stream_id=' + streamId);
    }

    // Newsletter form submission
    document.addEventListener('DOMContentLoaded', function() {
        const newsletterForm = document.getElementById('newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = this.querySelector('input[type="email"]').value;
                
                // Send to backend
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'process_newsletter.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        alert('Thanks for subscribing! We\'ll keep you updated with the latest news.');
                    } else {
                        alert('There was an error processing your subscription. Please try again.');
                    }
                };
                xhr.send('email=' + encodeURIComponent(email) + '&source=homepage');
                this.reset();
            });
        }
    });
    </script>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>